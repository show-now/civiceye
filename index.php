 <?php
include 'db.php';

// Get statistics
$stats = [
    'total_complaints' => 0,
    'resolved_complaints' => 0,
    'pending_complaints' => 0,
    'active_users' => 0
];

// Fetch complaint statistics
$sql = "SELECT 
    COUNT(*) as total_complaints,
    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved_complaints,
    SUM(CASE WHEN status IN ('Pending', 'In Progress') THEN 1 ELSE 0 END) as pending_complaints
    FROM complaints";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats = array_merge($stats, $row);
}

// Fetch active users (approximation)
$sql = "SELECT COUNT(DISTINCT citizen_email) as active_users FROM complaints WHERE citizen_email != ''";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['active_users'] = $row['active_users'];
}

// Get recent complaints for the chart
$sql = "SELECT 
    DATE(created_at) as date, 
    COUNT(*) as count,
    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved
    FROM complaints 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 7";
$chart_data = [];
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $chart_data[] = $row;
}
$chart_data = array_reverse($chart_data); // Order from oldest to newest

// Get top categories
$sql = "SELECT c.name, COUNT(*) as count 
        FROM complaints comp
        JOIN categories c ON comp.category_id = c.id
        GROUP BY c.name 
        ORDER BY count DESC 
        LIMIT 5";
$top_categories = [];
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $top_categories[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CivicEye - Public Grievance Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f7fee7',
                            100: '#ecfccb',
                            200: '#d9f99d',
                            300: '#bef264',
                            400: '#a3e635',
                            500: '#84cc16',
                            600: '#65a30d',
                            700: '#4d7c0f',
                            800: '#3f6212',
                            900: '#365314',
                        }
                    },
                    fontFamily: {
                        'sf': ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif']
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.5s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1e293b;
        }
        
        .nav-gradient {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.7);
        }
        
        .hero-gradient {
              background: linear-gradient(135deg, rgba(247, 254, 231, 0.69) 0%, rgba(236, 252, 203, 0.24) 100%), url('https://images.unsplash.com/photo-1596176530529-78163a4f7af2?q=80&w=2427&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D');
            background-size: cover;
            background-position: center;
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border-radius: 18px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .chart-container {
            border-radius: 18px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card {
            transition: all 0.3s ease;
            border-radius: 18px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .btn-primary {
            background: #84cc16;
            color: white;
            border-radius: 50px;
            padding: 12px 28px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(132, 204, 22, 0.2);
        }
        
        .btn-primary:hover {
            background: #65a30d;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px rgba(132, 204, 22, 0.3);
        }
        
        .footer-link {
            transition: all 0.2s ease;
        }
        
        .footer-link:hover {
            color: #84cc16;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .section-title {
            position: relative;
            display: inline-block;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            width: 40%;
            height: 3px;
            background: #84cc16;
            bottom: -10px;
            left: 0;
            border-radius: 3px;
        }
        
        .accordion-content {
            transition: max-height 0.3s ease-out, padding 0.3s ease;
        }
    </style>
</head>
<body class="bg-slate-50">
    <!-- Navigation -->
    <nav class="nav-gradient text-slate-800 fixed w-full z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <a href="#" class="text-2xl font-bold flex items-center">
                        <img src="/static/civiceye.png" style="width: 50px;">&nbsp;CivicEye
                </a>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button type="button" class="text-slate-800 focus:outline-none" id="mobile-menu-button">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
                
                <!-- Desktop menu -->
                <div class="hidden md:flex space-x-8 items-center">
                    <a href="#" class="font-medium hover:text-primary-600 transition">Home</a>
                    <a href="#stats" class="font-medium hover:text-primary-600 transition">Statistics</a>
                    <a href="#faq" class="font-medium hover:text-primary-600 transition">FAQ</a>
                    <a href="report_complaint.php" class="font-medium hover:text-primary-600 transition">Report Issue</a>
                    <a href="performance_dashboard.php" class="font-medium hover:text-primary-600 transition">Performance</a>
                    <a href="hall_of_fame.php" class="btn-primary">Hall of Fame</a>
                </div>
            </div>
            
            <!-- Mobile menu -->
            <div class="hidden pb-4 md:hidden glass-effect rounded-lg mt-2" id="mobile-menu">
                <a href="#" class="block py-3 px-4 font-medium hover:bg-primary-50 hover:text-primary-600 transition">Home</a>
                <a href="#stats" class="block py-3 px-4 font-medium hover:bg-primary-50 hover:text-primary-600 transition">Statistics</a>
                <a href="#faq" class="block py-3 px-4 font-medium hover:bg-primary-50 hover:text-primary-600 transition">FAQ</a>
                <a href="report_complaint.php" class="block py-3 px-4 font-medium hover:bg-primary-50 hover:text-primary-600 transition">Report Issue</a>
                <a href="performance_dashboard.php" class="block py-3 px-4 font-medium hover:bg-primary-50 hover:text-primary-600 transition">Performance</a>
                <a href="hall_of_fame.php" class="block py-3 px-4 font-medium hover:bg-primary-50 hover:text-primary-600 transition">Hall of Fame</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-gradient text-slate-800 pt-24">
        <div class="container mx-auto px-4 py-24 md:py-32 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-6 animate-fade-in">Your Voice Matters.<br>Make It Heard.</h1>
            <p class="text-xl md:text-2xl mb-10 max-w-2xl mx-auto text-slate-600 animate-fade-in">Report civic issues and collaborate with authorities to create better communities for everyone.</p>
            <div class="flex flex-col sm:flex-row justify-center gap-4 animate-slide-up">
                <a href="report_complaint.php" class="btn-primary inline-flex items-center justify-center">
                    <i class="fas fa-plus-circle mr-2"></i>Report an Issue
                </a>
                <a href="hall_of_fame.php" class="inline-flex items-center justify-center bg-white text-primary-600 font-semibold px-6 py-3 rounded-full shadow-sm border border-slate-200 hover:shadow-md transition">
                    <i class="fas fa-trophy mr-2"></i>Hall of Fame
                </a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section id="stats" class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-4 section-title">Community Impact</h2>
            <p class="text-center text-slate-600 mb-12 max-w-2xl mx-auto">Transparent tracking of community engagement and issue resolution progress.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
                <!-- Total Complaints -->
                <div class="stat-card bg-white p-6 border border-slate-100">
                    <div class="text-primary-500 mb-4">
                        <i class="fas fa-file-alt text-3xl"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-slate-800 mb-2"><?php echo number_format($stats['total_complaints']); ?></h3>
                    <p class="text-slate-600">Total Complaints</p>
                </div>
                
                <!-- Resolved Issues -->
                <div class="stat-card bg-white p-6 border border-slate-100">
                    <div class="text-green-500 mb-4">
                        <i class="fas fa-check-circle text-3xl"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-slate-800 mb-2"><?php echo number_format($stats['resolved_complaints']); ?></h3>
                    <p class="text-slate-600">Issues Resolved</p>
                </div>
                
                <!-- Pending Issues -->
                <div class="stat-card bg-white p-6 border border-slate-100">
                    <div class="text-amber-500 mb-4">
                        <i class="fas fa-clock text-3xl"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-slate-800 mb-2"><?php echo number_format($stats['pending_complaints']); ?></h3>
                    <p class="text-slate-600">Pending Issues</p>
                </div>
                
                <!-- Active Users -->
                <div class="stat-card bg-white p-6 border border-slate-100">
                    <div class="text-purple-500 mb-4">
                        <i class="fas fa-users text-3xl"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-slate-800 mb-2"><?php echo number_format($stats['active_users']); ?></h3>
                    <p class="text-slate-600">Active Users</p>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Complaints Trend Chart -->
                <div class="bg-white p-6 chart-container border border-slate-100">
                    <h3 class="text-xl font-semibold mb-4 text-slate-800">Complaints Trend (Last 7 Days)</h3>
                    <canvas id="complaintsChart" height="300"></canvas>
                </div>
                
                <!-- Categories Chart -->
                <div class="bg-white p-6 chart-container border border-slate-100">
                    <h3 class="text-xl font-semibold mb-4 text-slate-800">Top Issue Categories</h3>
                    <canvas id="categoriesChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-slate-50">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-4 section-title">How It Works</h2>
            <p class="text-center text-slate-600 mb-12 max-w-2xl mx-auto">A simple, transparent process to report issues and track their resolution.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card bg-white p-6 text-center border border-slate-100">
                    <div class="text-primary-500 mb-4">
                        <div class="rounded-full bg-primary-50 p-4 inline-flex items-center justify-center">
                            <i class="fas fa-edit text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold mb-3 text-slate-800">Report an Issue</h3>
                    <p class="text-slate-600">Describe the problem, add photos, and pinpoint the location on a map.</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="feature-card bg-white p-6 text-center border border-slate-100">
                    <div class="text-primary-500 mb-4">
                        <div class="rounded-full bg-primary-50 p-4 inline-flex items-center justify-center">
                            <i class="fas fa-tasks text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold mb-3 text-slate-800">Track Progress</h3>
                    <p class="text-slate-600">Monitor the status of your complaint and receive updates on the resolution process.</p>
                </div>
                
                <!-- Feature 3 -->
                <div class="feature-card bg-white p-6 text-center border border-slate-100">
                    <div class="text-primary-500 mb-4">
                        <div class="rounded-full bg-primary-50 p-4 inline-flex items-center justify-center">
                            <i class="fas fa-check-double text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold mb-3 text-slate-800">Verify Resolution</h3>
                    <p class="text-slate-600">Confirm when issues are resolved and provide feedback on the solution.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-4 section-title">Frequently Asked Questions</h2>
            <p class="text-center text-slate-600 mb-12 max-w-2xl mx-auto">Find answers to common questions about reporting and tracking civic issues.</p>
            
            <div class="max-w-3xl mx-auto">
                <!-- FAQ 1 -->
                <div class="mb-4 bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <button class="accordion-button flex justify-between items-center w-full p-6 text-left font-semibold text-slate-800 hover:bg-slate-50 transition">
                        How long does it take to resolve a complaint?
                        <i class="fas fa-chevron-down ml-2 text-primary-500"></i>
                    </button>
                    <div class="accordion-content px-6 pb-6">
                        <p class="text-slate-600">The resolution time varies based on the complexity of the issue. Simple problems may be resolved within a few days, while more complex issues might take several weeks. You can track the progress of your complaint through our portal.</p>
                    </div>
                </div>
                
                <!-- FAQ 2 -->
                <div class="mb-4 bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <button class="accordion-button flex justify-between items-center w-full p-6 text-left font-semibold text-slate-800 hover:bg-slate-50 transition">
                        What types of issues can I report?
                        <i class="fas fa-chevron-down ml-2 text-primary-500"></i>
                    </button>
                    <div class="accordion-content px-6 pb-6 hidden">
                        <p class="text-slate-600">You can report various civic issues including road damage, garbage collection problems, street light outages, water supply issues, drainage problems, park maintenance, and other community-related concerns.</p>
                    </div>
                </div>
                
                <!-- FAQ 3 -->
                <div class="mb-4 bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <button class="accordion-button flex justify-between items-center w-full p-6 text-left font-semibold text-slate-800 hover:bg-slate-50 transition">
                        Is my personal information kept private?
                        <i class="fas fa-chevron-down ml-2 text-primary-500"></i>
                    </button>
                    <div class="accordion-content px-6 pb-6 hidden">
                        <p class="text-slate-600">Yes, we take privacy seriously. Your contact information is only used for official communication regarding your complaint and is not shared publicly. The issue details may be shared with the relevant authorities for resolution.</p>
                    </div>
                </div>
                
                <!-- FAQ 4 -->
                <div class="mb-4 bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <button class="accordion-button flex justify-between items-center w-full p-6 text-left font-semibold text-slate-800 hover:bg-slate-50 transition">
                        How can I check the status of my complaint?
                        <i class="fas fa-chevron-down ml-2 text-primary-500"></i>
                    </button>
                    <div class="accordion-content px-6 pb-6 hidden">
                        <p class="text-slate-600">Once you've submitted a complaint, you'll receive a unique tracking ID. You can use this ID to check the status on our website. We'll also send email updates as your complaint progresses through the resolution process.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-20 bg-primary-600 text-white">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-6">Ready to Make a Difference?</h2>
            <p class="text-xl mb-10 max-w-2xl mx-auto">Report issues in your community and help us create a better living environment for everyone.</p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="report_complaint.php" class="btn-primary bg-white text-primary-600 hover:bg-slate-100 inline-flex items-center justify-center">
                    Report an Issue Now
                </a>
                <a href="hall_of_fame.php" class="inline-flex items-center justify-center bg-primary-700 text-white font-semibold px-6 py-3 rounded-full hover:bg-primary-800 transition">
                    View Hall of Fame
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-white py-16">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Brand -->
                <div>
                    <h5 class="text-xl font-bold mb-4 flex items-center">
                        <img src="/static/civiceye.png" style="width: 50px;">&nbsp;CivicEye
                    </h5>
                    <p class="text-slate-400">Empowering communities through transparent grievance resolution and civic engagement.</p>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h5 class="text-lg font-semibold mb-4">Quick Links</h5>
                    <ul class="space-y-3">
                        <li><a href="#" class="footer-link text-slate-400">Home</a></li>
                        <li><a href="report_complaint.php" class="footer-link text-slate-400">Report Issue</a></li>
                        <li><a href="#faq" class="footer-link text-slate-400">Faq</a></li>
                        <li><a href="hall_of_fame.php" class="footer-link text-slate-400">Hall of Fame</a></li>
                    </ul>
                </div>
                
            </div>
            
            <div class="border-t border-slate-800 mt-12 pt-8 text-center text-slate-500">
                <p>CivicEye A Hackathon Project By DeadLock</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });

        // Accordion functionality
        document.querySelectorAll('.accordion-button').forEach(button => {
            button.addEventListener('click', () => {
                const content = button.nextElementSibling;
                const icon = button.querySelector('i');
                
                // Toggle content
                content.classList.toggle('hidden');
                
                // Rotate icon
                if (content.classList.contains('hidden')) {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                } else {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                }
            });
        });

        // Charts
        // Complaints Trend Chart
        const complaintsCtx = document.getElementById('complaintsChart').getContext('2d');
        const complaintsChart = new Chart(complaintsCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M j', strtotime($item['date'])) . "'"; }, $chart_data)); ?>],
                datasets: [
                    {
                        label: 'Total Complaints',
                        data: [<?php echo implode(',', array_column($chart_data, 'count')); ?>],
                        borderColor: '#84cc16',
                        backgroundColor: 'rgba(132, 204, 22, 0.1)',
                        tension: 0.3,
                        fill: true,
                        borderWidth: 2
                    },
                    {
                        label: 'Resolved',
                        data: [<?php echo implode(',', array_column($chart_data, 'resolved')); ?>],
                        borderColor: '#65a30d',
                        backgroundColor: 'rgba(101, 163, 13, 0.1)',
                        tension: 0.3,
                        fill: true,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Categories Chart
        const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
        const categoriesChart = new Chart(categoriesCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['name'] . "'"; }, $top_categories)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($top_categories, 'count')); ?>],
                    backgroundColor: [
                        '#84cc16', '#a3e635', '#bef264', '#d9f99d', '#ecfccb'
                    ],
                    borderWidth: 0,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                },
                cutout: '65%'
            }
        });
    </script>
</body>
</html>
<?php
// Close the database connection
mysqli_close($conn);
?