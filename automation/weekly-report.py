#!/usr/bin/env python3
"""
CivicEye Weekly Automation Script
Posts updates to Twitter and sends emails to municipalities with weekly statistics.
"""

import mysql.connector
import smtplib
import tweepy
import json
import os
from datetime import datetime, timedelta
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

class CivicEyeAutomation:
    def __init__(self, config_file='config.json'):
        # Load configuration
        with open(config_file, 'r') as f:
            self.config = json.load(f)
        
        # Database connection
        self.db_connection = mysql.connector.connect(
            host=self.config['db_host'],
            user=self.config['db_user'],
            password=self.config['db_password'],
            database=self.config['db_name']
        )
        
        # Twitter API setup
        auth = tweepy.OAuthHandler(
            self.config['twitter_api_key'], 
            self.config['twitter_api_secret']
        )
        auth.set_access_token(
            self.config['twitter_access_token'], 
            self.config['twitter_access_secret']
        )
        self.twitter_api = tweepy.API(auth)
        
        # Email setup
        self.smtp_server = self.config['smtp_server']
        self.smtp_port = self.config['smtp_port']
        self.email_user = self.config['email_user']
        self.email_password = self.config['email_password']
    
    def get_weekly_stats(self):
        """Get weekly statistics from the database"""
        cursor = self.db_connection.cursor(dictionary=True)
        
        # Calculate date range (last 7 days)
        end_date = datetime.now()
        start_date = end_date - timedelta(days=7)
        
        # Query for new complaints in the last week
        query = """
            SELECT COUNT(*) as total_complaints, 
                   COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_complaints,
                   m.name as municipality_name,
                   m.email as municipality_email
            FROM complaints c
            JOIN municipalities m ON c.municipality_id = m.id
            WHERE c.created_at BETWEEN %s AND %s
            GROUP BY c.municipality_id
        """
        
        cursor.execute(query, (start_date, end_date))
        results = cursor.fetchall()
        
        # Get top categories
        category_query = """
            SELECT category, COUNT(*) as count
            FROM complaints
            WHERE created_at BETWEEN %s AND %s
            GROUP BY category
            ORDER BY count DESC
            LIMIT 5
        """
        
        cursor.execute(category_query, (start_date, end_date))
        top_categories = cursor.fetchall()
        
        cursor.close()
        
        return {
            'municipality_stats': results,
            'top_categories': top_categories,
            'period': {
                'start': start_date.strftime('%Y-%m-%d'),
                'end': end_date.strftime('%Y-%m-%d')
            }
        }
    
    def generate_tweet(self, stats):
        """Generate a tweet with weekly statistics"""
        total_complaints = sum([m['total_complaints'] for m in stats['municipality_stats']])
        resolved_complaints = sum([m['resolved_complaints'] for m in stats['municipality_stats']])
        resolution_rate = (resolved_complaints / total_complaints * 100) if total_complaints > 0 else 0
        
        # Get top category
        top_category = stats['top_categories'][0]['category'] if stats['top_categories'] else "N/A"
        
        tweet = f"📊 CivicEye Weekly Report ({stats['period']['start']} to {stats['period']['end']})\n\n"
        tweet += f"• {total_complaints} new complaints reported\n"
        tweet += f"• {resolved_complaints} complaints resolved ({resolution_rate:.1f}% resolution rate)\n"
        tweet += f"• Top issue: {top_category}\n\n"
        tweet += "#CivicEye #CommunityEngagement"
        
        # Ensure tweet is within character limit
        if len(tweet) > 280:
            tweet = tweet[:277] + "..."
        
        return tweet
    
    def post_to_twitter(self, tweet):
        """Post the generated tweet to Twitter"""
        try:
            self.twitter_api.update_status(tweet)
            print("Successfully posted to Twitter")
            return True
        except Exception as e:
            print(f"Error posting to Twitter: {e}")
            return False
    
    def generate_email_content(self, stats, municipality_name):
        """Generate HTML email content for a municipality"""
        # Find stats for this specific municipality
        mun_stats = next(
            (m for m in stats['municipality_stats'] if m['municipality_name'] == municipality_name), 
            None
        )
        
        if not mun_stats:
            return None
        
        total = mun_stats['total_complaints']
        resolved = mun_stats['resolved_complaints']
        resolution_rate = (resolved / total * 100) if total > 0 else 0
        
        html = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>CivicEye Weekly Report for {municipality_name}</title>
            <style>
                body {{ font-family: Arial, sans-serif; line-height: 1.6; color: #333; }}
                .header {{ background-color: #4CAF50; color: white; padding: 20px; text-align: center; }}
                .content {{ padding: 20px; }}
                .stats {{ background-color: #f9f9f9; padding: 15px; border-radius: 5px; }}
                .footer {{ background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; }}
            </style>
        </head>
        <body>
            <div class="header">
                <h1>CivicEye Weekly Report</h1>
                <h2>{municipality_name}</h2>
                <p>Period: {stats['period']['start']} to {stats['period']['end']}</p>
            </div>
            
            <div class="content">
                <h3>Weekly Summary</h3>
                <div class="stats">
                    <p><strong>Total Complaints:</strong> {total}</p>
                    <p><strong>Resolved Complaints:</strong> {resolved}</p>
                    <p><strong>Resolution Rate:</strong> {resolution_rate:.1f}%</p>
                </div>
                
                <h3>Top Complaint Categories</h3>
                <ol>
        """
        
        for category in stats['top_categories']:
            html += f"<li>{category['category']}: {category['count']} complaints</li>"
        
        html += """
                </ol>
                
                <p>Log in to <a href="http://manager.ct.ws/municipality">Municipality Dashboard</a> to view and manage these complaints.</p>
            </div>
            
            <div class="footer">
                <p>This is an automated message from CivicEye. Please do not reply to this email.</p>
                <p><a href="http://manager.ct.ws">CivicEye Public Grievance Portal</a></p>
            </div>
        </body>
        </html>
        """
        
        return html
    
    def send_emails(self, stats):
        """Send weekly report emails to each municipality"""
        try:
            server = smtplib.SMTP(self.smtp_server, self.smtp_port)
            server.starttls()
            server.login(self.email_user, self.email_password)
            
            for municipality in stats['municipality_stats']:
                email_content = self.generate_email_content(stats, municipality['municipality_name'])
                
                if email_content:
                    msg = MIMEMultipart('alternative')
                    msg['Subject'] = f"CivicEye Weekly Report for {municipality['municipality_name']}"
                    msg['From'] = self.email_user
                    msg['To'] = municipality['municipality_email']
                    
                    # Create both HTML and plain text versions
                    text = f"CivicEye Weekly Report for {municipality['municipality_name']}\n\n"
                    text += f"Period: {stats['period']['start']} to {stats['period']['end']}\n"
                    text += f"Total Complaints: {municipality['total_complaints']}\n"
                    text += f"Resolved Complaints: {municipality['resolved_complaints']}\n\n"
                    text += "Log in to Municipality Dashboard to view details."
                    
                    part1 = MIMEText(text, 'plain')
                    part2 = MIMEText(email_content, 'html')
                    
                    msg.attach(part1)
                    msg.attach(part2)
                    
                    server.sendmail(
                        self.email_user, 
                        municipality['municipality_email'], 
                        msg.as_string()
                    )
                    
                    print(f"Email sent to {municipality['municipality_name']}")
            
            server.quit()
            return True
            
        except Exception as e:
            print(f"Error sending emails: {e}")
            return False
    
    def run_weekly_report(self):
        """Run the complete weekly report process"""
        print(f"Running CivicEye weekly report for {datetime.now().strftime('%Y-%m-%d')}")
        
        # Get statistics
        stats = self.get_weekly_stats()
        
        # Post to Twitter
        tweet = self.generate_tweet(stats)
        twitter_success = self.post_to_twitter(tweet)
        
        # Send emails to municipalities
        email_success = self.send_emails(stats)
        
        # Log results
        result = {
            'timestamp': datetime.now().isoformat(),
            'twitter_success': twitter_success,
            'email_success': email_success,
            'stats': stats
        }
        
        # Save log
        with open('weekly_report_log.json', 'a') as log_file:
            log_file.write(json.dumps(result) + '\n')
        
        print("Weekly report completed")
        return result

def main():
    # Check if config file exists
    if not os.path.exists('config.json'):
        print("Error: config.json file not found.")
        print("Please create a config file with database, Twitter, and email settings.")
        return
    
    # Initialize and run the automation
    automation = CivicEyeAutomation()
    automation.run_weekly_report()

if __name__ == "__main__":
    main()
