# CivicEye

**CivicEye** is a lightweight public grievance portal that enables citizens to report civic issues and track their resolution. The platform offers a transparent, collaborative process for engaging with authorities and improving communities.

## 🚀 Setup and Installation Guide

### Prerequisites
- PHP 7.x or higher
- MySQL/MariaDB
- Web server (Apache/Nginx recommended)

### Installation Steps

1. **Clone the Repository**
   ```bash
   git clone https://github.com/show-now/civiceye.git
   cd civiceye
   ```

2. **Configure Database**
   - Edit `db.php` with your MySQL credentials:
     ```php
     $servername = "your_db_server";
     $username = "your_db_user";
     $password = "your_db_password";
     $dbname = "your_db_name";
     ```
   - Import the provided SQL schema (if available) to your MySQL database.

3. **Set Up Web Server**
   - Place the project in your web server's root directory.
   - Make sure the server can write to any `uploads/` directories (if required).

4. **Access the Application**
   - Open `http://localhost/civiceye` in your browser.

## 🏗️ Project Architecture Overview

```
civiceye/
│
├── index.php                # Main landing page
├── db.php                   # Database connection logic
├── report_complaint.php     # Submit a new complaint
├── view_complaint.php       # View complaint details
├── hall_of_fame.php         # Highlights active contributors
│
├── adminstrator/
│   └── admin.php            # Admin dashboard and management
│
├── municipality/
│   └── issue_manager.php    # Municipality-side issue management
│
├── static/                  # Images, custom CSS, etc.
│
└── phpmailer/               # PHPMailer library for email notifications
```

- **Frontend:** Mix of PHP, HTML, and Tailwind CSS for UI; Font Awesome for icons; Chart.js for statistics.
- **Backend:** PHP for routing, logic, and database interactions.
- **Database:** MySQL handles users, complaints, and status tracking.
- **Email:** PHPMailer used for sending notifications.

## 📦 Tech Stack

- **Languages:** PHP, HTML, CSS, JavaScript
- **Frameworks/Libraries:** 
  - [Tailwind CSS](https://tailwindcss.com/) (via CDN)
  - [Font Awesome](https://fontawesome.com/) (icons)
  - [Chart.js](https://www.chartjs.org/) (statistics/visualizations)
  - [Bootstrap 5](https://getbootstrap.com/) (used in some components)
  - [PHPMailer](https://github.com/PHPMailer/PHPMailer) (email notifications)
- **Database:** MySQL
- **Tools:** 
  - Web server: Apache or Nginx
  - Version control: Git & GitHub

## 📝 Code and Functionality Description

- **`index.php`**: The public homepage. Explains how CivicEye works, shows statistics, and links to main features.
- **`report_complaint.php`**: Allows users to submit civic issues, including details and images. Geolocation can be recorded.
- **`view_complaint.php`**: Displays detailed information about a complaint, including status, images, and updates.
- **`hall_of_fame.php`**: Showcases top contributors and the most impactful resolutions.
- **`adminstrator/admin.php`**: Admin dashboard to manage complaints, analytics, and user management.
- **`municipality/issue_manager.php`**: Municipality interface for tracking, managing, and resolving complaints.
- **`db.php`**: Handles all database connections and credentials.
- **`phpmailer/`**: Contains the PHPMailer library, used for sending automated email notifications regarding complaint status and updates.

## 🔗 Source Code

GitHub: [https://github.com/show-now/civiceye](https://github.com/show-now/civiceye)

---

*For questions, contributions, or issues, please open an issue on the GitHub repository!*
