 <?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db.php';

// Check if logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['municipality_id'])) {
    header("Location: login.php");
    exit;
}

$mun_id = $_SESSION['municipality_id'];
$mun_name = $_SESSION['municipality_name'];

// Fetch complaint counts
$statuses = ['Pending','In Progress','Closed'];
$counts = [];
foreach($statuses as $s){
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE municipality_id=? AND status=?");
    $stmt->bind_param("is",$mun_id,$s);
    $stmt->execute();
    $counts[$s]=$stmt->get_result()->fetch_assoc()['cnt'];
}

// False info
$stmt=$conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE municipality_id=? AND false_info=1");
$stmt->bind_param("i",$mun_id);
$stmt->execute();
$false_info=$stmt->get_result()->fetch_assoc()['cnt'];

// Total complaints
$total_complaints = array_sum($counts);

// Monthly data for line chart
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE municipality_id=? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->bind_param("is", $mun_id, $month);
    $stmt->execute();
    $monthly_data[$month] = $stmt->get_result()->fetch_assoc()['cnt'];
}

// Category distribution
$categories_data = [];
$cat_stmt = $conn->prepare("SELECT c.name, COUNT(*) as count 
                           FROM complaints comp 
                           JOIN categories c ON comp.category_id = c.id 
                           WHERE comp.municipality_id = ? 
                           GROUP BY comp.category_id");
$cat_stmt->bind_param("i", $mun_id);
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();
while ($row = $cat_result->fetch_assoc()) {
    $categories_data[$row['name']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - <?= $_SESSION['municipality_name'] ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        /* Government Header */
        .government-header {
            background: linear-gradient(135deg, #0d3d66 0%, #1a5f9e 100%);
            color: white;
            padding: 15px 25px;
            border-bottom: 4px solid #b31b1b;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .agency-info h1 {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .agency-info p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Navigation */
        .main-nav {
            background: #2c3e50;
            padding: 12px 25px;
        }
        
        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .welcome-text {
            color: #ecf0f1;
            font-weight: 500;
        }
        
        .nav-links a {
            color: #ecf0f1;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 12px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Dashboard Content */
        .dashboard {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .dashboard-header {
            margin-bottom: 30px;
        }
        
        .dashboard-header h2 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
            border-bottom: 2px solid #b31b1b;
            padding-bottom: 10px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #0d3d66;
        }
        
        .stat-card.pending {
            border-left-color: #e67e22;
        }
        
        .stat-card.in-progress {
            border-left-color: #3498db;
        }
        
        .stat-card.closed {
            border-left-color: #27ae60;
        }
        
        .stat-card.false {
            border-left-color: #e74c3c;
        }
        
        .stat-title {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header-content, .nav-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .agency-info {
                margin-bottom: 15px;
            }
            
            .nav-content {
                align-items: stretch;
            }
            
            .nav-links {
                display: flex;
                flex-direction: column;
            }
            
            .nav-links a {
                margin: 5px 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                padding: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .government-header {
                padding: 15px;
            }
            
            .main-nav {
                padding: 12px 15px;
            }
            
            .dashboard {
                padding: 0 15px;
            }
            
            .stat-value {
                font-size: 28px;
            }
        }
        
        /* Print Styles */
        @media print {
            .main-nav, .government-header {
                display: none;
            }
            
            body {
                background: white;
                color: black;
            }
            
            .stat-card, .chart-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <!-- Government Header -->
    <header class="government-header">
        <div class="header-content">
            <div class="agency-info">
                <h1>Municipal Government Analytics Portal</h1>
                <p>Official Performance Metrics Dashboard</p>
            </div>
        </div>
    </header>
    
    <!-- Navigation -->
    <nav class="main-nav">
        <div class="nav-content">
            <div class="welcome-text">
                <i class="fas fa-user-circle"></i> Welcome, <?= $_SESSION['municipality_name'] ?>
            </div>
            <div class="nav-links">
                <a href="issue_manager.php"><i class="fas fa-tasks"></i> Issue Manager</a>
                <a href="logout.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>
    
    <!-- Dashboard Content -->
    <main class="dashboard">
        <div class="dashboard-header">
            <h2>Analytics Dashboard</h2>
            <p>Comprehensive overview of grievance metrics and performance indicators</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">TOTAL COMPLAINTS</div>
                <div class="stat-value"><?= $total_complaints ?></div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-title">PENDING RESOLUTION</div>
                <div class="stat-value"><?= $counts['Pending'] ?></div>
            </div>
            
            <div class="stat-card in-progress">
                <div class="stat-title">IN PROGRESS</div>
                <div class="stat-value"><?= $counts['In Progress'] ?></div>
            </div>
            
            <div class="stat-card closed">
                <div class="stat-title">SUCCESSFULLY CLOSED</div>
                <div class="stat-value"><?= $counts['Closed'] ?></div>
            </div>
            
            <div class="stat-card false">
                <div class="stat-title">FALSE INFORMATION CASES</div>
                <div class="stat-value"><?= $false_info ?></div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Complaint Status Distribution</h3>
                </div>
                <canvas id="statusChart" height="250"></canvas>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Information Accuracy</h3>
                </div>
                <canvas id="falseChart" height="250"></canvas>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Monthly Complaint Trends</h3>
                </div>
                <canvas id="monthlyChart" height="250"></canvas>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Complaints by Category</h3>
                </div>
                <canvas id="categoryChart" height="250"></canvas>
            </div>
        </div>
    </main>

    <script>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Closed'],
                datasets: [{
                    data: [<?= $counts['Pending'] ?>, <?= $counts['In Progress'] ?>, <?= $counts['Closed'] ?>],
                    backgroundColor: ['#e67e22', '#3498db', '#27ae60'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // False Info Chart
        const falseCtx = document.getElementById('falseChart').getContext('2d');
        new Chart(falseCtx, {
            type: 'pie',
            data: {
                labels: ['False Information', 'Verified Information'],
                datasets: [{
                    data: [<?= $false_info ?>, <?= $total_complaints - $false_info ?>],
                    backgroundColor: ['#e74c3c', '#2ecc71'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Trends Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($monthly_data)) ?>,
                datasets: [{
                    label: 'Complaints',
                    data: <?= json_encode(array_values($monthly_data)) ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($categories_data)) ?>,
                datasets: [{
                    label: 'Complaints by Category',
                    data: <?= json_encode(array_values($categories_data)) ?>,
                    backgroundColor: [
                        'rgba(231, 76, 60, 0.7)',
                        'rgba(241, 196, 15, 0.7)',
                        'rgba(46, 204, 113, 0.7)',
                        'rgba(52, 152, 219, 0.7)',
                        'rgba(155, 89, 182, 0.7)',
                        'rgba(230, 126, 34, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html