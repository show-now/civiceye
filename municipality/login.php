 <?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db.php';

// Redirect if already logged in
if (isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, name FROM municipalities WHERE username=? AND password=?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        $_SESSION['logged_in'] = true;
        $_SESSION['municipality_id'] = $user['id'];
        $_SESSION['municipality_name'] = $user['name'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Login Portal - Government Grievance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background-color: #f8f9fa;
            color: #212529;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-image: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.9)), 
                              url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="none" stroke="%23dee2e6" stroke-width="1"/></svg>');
        }
        
        /* Government Header */
        .government-banner {
            width: 100%;
            max-width: 600px;
            background: #0d3d66;
            color: white;
            padding: 15px 25px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            text-align: center;
            border-bottom: 4px solid #b31b1b;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .government-banner h1 {
            font-size: 22px;
            font-weight: bold;
            margin: 0;
            letter-spacing: 0.5px;
        }
        
        .government-banner p {
            font-size: 14px;
            margin: 5px 0 0;
            opacity: 0.9;
        }
        
        /* Login Container */
        .login-container {
            width: 100%;
            max-width: 600px;
            background: white;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #ced4da;
            border-top: none;
        }
        
        /* Login Content */
        .login-content {
            padding: 30px;
            display: flex;
            flex-direction: column;
        }
        
        .agency-info {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .agency-info h2 {
            color: #0d3d66;
            font-size: 20px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .agency-info p {
            color: #495057;
            font-size: 15px;
        }
        
        /* Form Styles */
        .login-form {
            width: 100%;
        }
        
        .form-title {
            font-size: 18px;
            color: #0d3d66;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 15px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 16px;
            transition: all 0.2s;
            background-color: #f8f9fa;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #0d3d66;
            box-shadow: 0 0 0 3px rgba(13, 61, 102, 0.15);
            background-color: #fff;
        }
        
        .btn-login {
            background-color: #0d3d66;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 4px;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
            letter-spacing: 0.5px;
        }
        
        .btn-login:hover {
            background-color: #0a2e4d;
        }
        
        /* Error Message */
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #f5c6cb;
            font-size: 15px;
        }
        
        /* Footer */
        .login-footer {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
        
        .help-link {
            color: #0d3d66;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            margin-top: 10px;
        }
        
        .help-link:hover {
            text-decoration: underline;
        }
        
        .security-notice {
            background: #e2e3e5;
            padding: 12px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 13px;
            color: #383d41;
            text-align: center;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .government-banner h1 {
                font-size: 20px;
            }
            
            .government-banner p {
                font-size: 13px;
            }
            
            .login-content {
                padding: 20px;
            }
            
            .agency-info h2 {
                font-size: 18px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .government-banner {
                padding: 12px 20px;
            }
            
            .government-banner h1 {
                font-size: 18px;
            }
            
            .login-content {
                padding: 15px;
            }
            
            .form-input {
                padding: 10px 12px;
                font-size: 16px; /* Prevent zoom on iOS */
            }
            
            .btn-login {
                padding: 12px;
            }
        }
        
        /* Focus styles for accessibility */
        .btn-login:focus,
        .form-input:focus,
        .help-link:focus {
            outline: 2px solid #0d3d66;
            outline-offset: 2px;
        }
        
        /* Government Seal */
        .seal-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .government-seal {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #0d3d66;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #b31b1b;
            font-size: 12px;
            text-align: center;
            line-height: 1.2;
            padding: 5px;
        }
    </style>
</head>
<body>
    <div class="government-banner">
        <h1>MUNICIPAL PORTAL</h1>
        <p>Grievance Management System</p>
    </div>
    
    <div class="login-container">
    <div class="login-content">
            <div class="agency-info">
                <p>Authorized Access Only - Unauthorized use is prohibited</p>
            </div>
            
            <?php if($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
            <?php endif; ?>
            
            <div class="login-form">
                <h3 class="form-title">Secure Login</h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" placeholder="Enter your username" required autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" placeholder="Enter your password" required autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-lock"></i> SECURE LOGIN
                    </button>
                </form>
            </div>
            
            <div class="login-footer">
                
                <div class="security-notice">
                    <i class="fas fa-info-circle"></i> This system contains privileged information. All activities are monitored.
                </div>
            </div>
        </div>
    </div>
</body>
</html