# CivicEye

**CivicEye** is a comprehensive public grievance portal that empowers citizens to report civic issues and track their resolution. It provides a transparent interface for citizens, administrators, and municipal authorities to collaborate and improve their communities through efficient issue reporting, management, and resolution tracking.

---

Demo: [https://manager.ct.ws](https://manager.ct.ws)

This is a demo link hosted on a free hosting server. Some features, such as email and certain functionalities, may be missing or limited due to the restrictions of free hosting.

## Colabrators
[@Sreeju S (Sreeju7733)](https://github.com/Sreeju7733)
<br>
[@Tilak L](https://github.com/tilak-cloudx)
<br>
[@Mohammed Umair](https://github.com/mumairms)
<br>
[@Dinesh R](https://github.com/dinesh-cloudx)


## 🚀 Setup and Installation Guide

### Prerequisites
- PHP 7.x or higher
- MySQL/MariaDB
- Web server (Apache/Nginx recommended)
- Python 3.6+ (for automation scripts)
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) library (included or install via Composer)

### Installation Steps

1. **Clone the Repository**
   ```bash
   git clone https://github.com/show-now/civiceye.git
   cd civiceye
   ```

2. **Install PHPMailer**
   - If not already present, install via Composer:
     ```bash
     composer require phpmailer/phpmailer
     ```
   - Or, download PHPMailer and place it in a `phpmailer/` directory at the project root.

3. **Configure Database**
   - Edit every `db.php` file (in root, `adminstrator/`, `municipality/`) with your MySQL credentials:
     ```php
     $servername = "your_db_server";
     $username = "your_db_user";
     $password = "your_db_password";
     $dbname = "your_db_name";
     ```
   - Import `if0_37947537_hackathon1.sql` into your MySQL server to set up the database schema.

4. **Set Up Web Server**
   - Place the project in your web server's root directory.
   - Ensure write permissions for any file upload directories.

5. **Configure Automation Script**
   - Install required Python packages:
     ```bash
     pip install mysql-connector-python tweepy
     ```
   - Create `config.json` from the template below with your credentials
   - Set up a cron job or task scheduler to run the automation weekly

6. **Access the Application**
   - Open `http://localhost/civiceye` or your server's corresponding URL in your browser.

---

## 🏗️ Project Architecture Overview

```
civiceye/
│
├── adminstrator/
│   ├── admin.php                # Admin dashboard and management
│   ├── db.php                   # Admin-side DB connection
│   ├── get_complaint_details.php# AJAX/fetch complaint info
│   ├── index.php                # Admin landing page
│   ├── login.php                # Admin login logic
│   └── logout.php               # Admin logout logic
│
├── municipality/
│   ├── db.php                   # Municipality-side DB connection
│   ├── index.php                # Municipality dashboard
│   ├── issue_manager.php        # Issue tracking/management
│   ├── login.php                # Municipality portal login
│   ├── logout.php               # Municipality logout
│   └── view_complaint.php       # Municipality complaint view
│
├── static/
│   ├── civiceye.png             # Branding
│   └── civic-logo.png           # Branding
│
├── automation/                   # Python automation scripts
│   ├── weekly_report.py         # Weekly Twitter and email automation
│   ├── config.json              # Configuration file (create from template)
│   └── weekly_report_log.json   # Automation log (auto-generated)
│
├── phpmailer/                   # PHPMailer library (for email notifications)
│   └── ...                      # (PHPMailer files)
│
├── close.php                    # Script for closing issues
├── db.php                       # Main DB connection
├── hall_of_fame.php             # Community leaderboard
├── .htaccess                    # configuration of website-access 
├── if0_37947537_hackathon1.sql  # MySQL schema
├── index.php                    # Public homepage
├── report_complaint.php         # Citizen report submission
└── view_complaint.php           # Public complaint view
```

---

## 🤖 Weekly Automation Setup

CivicEye includes a Python automation script that posts weekly updates to Twitter and sends summary emails to municipalities.

### Configuration

Create a `config.json` file in the `automation/` directory:

```json
{
    "db_host": "localhost",
    "db_user": "civiceye_user",
    "db_password": "db_password",
    "db_name": "civiceye_db",
    
    "twitter_api_key": "twitter_api_key",
    "twitter_api_secret": "twitter_api_secret",
    "twitter_access_token": "twitter_access_token",
    "twitter_access_secret": "twitter_access_secret",
    
    "smtp_server": "smtp.gmail.com",
    "smtp_port": 587,
    "email_user": "example@gmail.com",
    "email_password": "email_password"
}
```

### Scheduling the Automation

**Linux/Mac (cron):**
```bash
# Run every Monday at 9 AM
0 9 * * 1 /usr/bin/python3 /path/to/civiceye/automation/weekly_report.py
```

**Windows (Task Scheduler):**
- Create a new task that runs weekly
- Action: Start a program → Python executable
- Arguments: Path to weekly_report.py

### Twitter API Setup
1. Apply for a Twitter Developer account at https://developer.twitter.com/
2. Create a new app and generate API keys
3. Add these keys to your config.json file

---

## 📦 Tech Stack

- **Languages:** PHP, HTML5, CSS3, JavaScript (ES6+), Python  

- **Frameworks & Libraries:**  
  - [Tailwind CSS](https://tailwindcss.com/) (utility-first CSS)  
  - [Bootstrap 5](https://getbootstrap.com/) (UI components)  
  - [Font Awesome](https://fontawesome.com/) (icons)  
  - [Chart.js](https://www.chartjs.org/) (data visualization)  
  - [PHPMailer](https://github.com/PHPMailer/PHPMailer) (email notifications)  
  - [Tweepy](https://www.tweepy.org/) (Twitter API integration)  

- **Database:** MySQL (8.0+)  

- **APIs & Integrations:**  
  - Twitter API v2 (automated posting & social integration)  
  - SMTP/Email API (transactional notifications)  
  - Geolocation API (browser-based location services)  

- **Automation & Scripting:**  
  - Python (automation & scripting)  
  - Tweepy (Twitter API integration)  
  - mysql-connector-python (MySQL database connector)  
  - smtplib (SMTP email automation)  

- **Development Tools:**  
  - Git & GitHub (version control)  
  - Composer (PHP dependency management)  

- **Deployment & Infrastructure:**  
  - Apache / Nginx (web servers)  
  - cron / Task Scheduler (automation & scheduled jobs)  
  - phpMyAdmin / Adminer (database management)  


---

## 📝 File-by-File Functionality

### Root Directory Files
- **index.php**: Public landing page with project overview, statistics, and feature links.
- **db.php**: MySQL connection logic for the public side.
- **report_complaint.php**: Form for users to submit civic issues with details and optional images. Uses PHPMailer to notify administrators/authorities.
- **view_complaint.php**: Detailed view for individual complaints including status, images, and updates.
- **hall_of_fame.php**: Shows top contributors and successfully resolved cases (community leaderboard).
- **close.php**: Script for marking complaints as closed.
- **if0_37947537_hackathon1.sql**: Complete SQL schema for all tables, users, complaints, and relationships.

### `adminstrator/` Directory
- **admin.php**: Admin dashboard for viewing/managing all complaints, users, and analytics.
- **db.php**: MySQL connection logic specifically for the admin panel.
- **get_complaint_details.php**: Fetches complaint details (typically for AJAX requests in the admin panel).
- **index.php**: Admin landing/dashboard page.
- **login.php**: Admin authentication system.
- **logout.php**: Admin logout functionality.

### `municipality/` Directory
- **index.php**: Municipality dashboard for tracking assigned complaints.
- **issue_manager.php**: Main interface for resolving and updating complaints (municipality-side management).
- **view_complaint.php**: Detailed complaint view for municipality users.
- **db.php**: MySQL connection logic for municipality panel.
- **login.php**: Municipality user authentication.
- **logout.php**: Municipality user logout.

### `automation/` Directory
- **weekly_report.py**: Python script for automated weekly Twitter posts and municipality emails.
- **config.json**: Configuration file for automation (database, Twitter, and email settings).
- **weekly_report_log.json**: Log file of automation runs (auto-generated).

### `static/` Directory
- **civiceye.png**, **civic-logo.png**: Branding and logo assets.

### `phpmailer/` Directory
- **PHPMailer files**: Email notification library used to send automated emails for complaint submissions, status updates, and notifications to users and staff.

---

## ✉️ Email Notification Integration

- **PHPMailer** is integrated throughout the application for automated email notifications.
- **Python automation** sends weekly summary emails to municipalities with statistics.
- Emails are triggered when:
  - A new complaint is submitted (notifications to administrators/municipal officers)
  - Complaint status is updated (notifications to the submitting user)
  - Weekly summaries are generated (to municipalities)
  - Other significant events occur in the complaint lifecycle
- To configure PHPMailer, edit the email settings in relevant PHP files (typically in `report_complaint.php`, admin/municipality files, or a dedicated mail helper).

---

## 🔗 Source Code

GitHub Repository: [https://github.com/show-now/civiceye](https://github.com/show-now/civiceye)

---

## 💡 Key Features

- **Citizen Reporting**: Easy-to-use form for reporting civic issues with geolocation support
- **Transparent Tracking**: Real-time status updates and resolution tracking
- **Multi-tier Access**: Separate interfaces for citizens, administrators, and municipal authorities
- **Email Notifications**: Automated updates for all stakeholders
- **Social Media Integration**: Weekly Twitter updates for community engagement
- **Community Engagement**: Hall of Fame to recognize active contributors
- **Data Visualization**: Charts and statistics for better insights
- **Automated Reporting**: Weekly summaries via Twitter and email

---

## 🚀 Deployment Notes

1. Ensure all file permissions are set correctly for uploads and logs
2. Set up proper cron jobs for the automation scripts
3. Configure email settings for both PHP and Python components
4. Set up Twitter API credentials for social media integration
5. Regularly backup the database and log files

---

## Demo
### Overall View
https://github.com/user-attachments/assets/19318678-2012-4048-903b-2ef772ac8ff1

### Citizen Platform
https://github.com/user-attachments/assets/e31353be-a6f1-4631-8087-d32108b2e2e2

### Admin Console
https://github.com/user-attachments/assets/2a38ed65-119f-4cad-a8cf-9091c6284d03

### Municipality Platform
[https://youtube.com/shorts/L9h23oJbfI8?feature=share]



*For questions, contributions, or issues, please open an issue on the GitHub repository!*
