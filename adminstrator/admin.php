 <?php
session_start();
include 'db.php';

/* ---------------- AUTH ---------------- */
$ADMIN_USER = "admin";
$ADMIN_PASS = "admin";

if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['login'])) {
        if ($_POST['username'] === $ADMIN_USER && $_POST['password'] === $ADMIN_PASS) {
            $_SESSION['logged_in'] = true;
            header("Location: admin.php");
            exit;
        } else {
            header("Location: login.php");
        }
    }
}

/* ---------------- LOGOUT ---------------- */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

/* ---------------- ADD CATEGORY ---------------- */
if (isset($_POST['add_category'])) {
    $cat_name = trim($_POST['cat_name']);
    $cat_email = trim($_POST['cat_email']);
    if($cat_name && $cat_email){
        $stmt = $conn->prepare("INSERT INTO categories (name,email) VALUES (?,?)");
        $stmt->bind_param("ss",$cat_name,$cat_email);
        $stmt->execute(); $stmt->close();
        header("Location: admin.php#categories");
        exit;
    }
}

/* ---------------- ADD MUNICIPALITY ---------------- */
if (isset($_POST['add_municipality'])) {
    $mun_name = trim($_POST['mun_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']); // plain text for simplicity
    if($mun_name && $username && $email && $password){
        $stmt = $conn->prepare("INSERT INTO municipalities (name, username, email, password) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss",$mun_name,$username,$email,$password);
        $stmt->execute(); $stmt->close();
        header("Location: admin.php#municipalities");
        exit;
    }
}

/* ---------------- CLOSE COMPLAINT ---------------- */
if (isset($_POST['close_complaint'])) {
    $id = (int)$_POST['complaint_id'];
    $remarks = trim($_POST['remarks']);
    $false_info = isset($_POST['false_info']) ? 1 : 0;

    $resolverImage = null;
    if (isset($_FILES['resolver_image']) && $_FILES['resolver_image']['size'] > 0) {
        $uploadDir = "../uploads/resolver/"; // for saving file in server
        $dbDir     = "uploads/resolver/";    // for saving path in DB

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $fileName   = time() . "_" . basename($_FILES['resolver_image']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['resolver_image']['tmp_name'], $targetFile)) {
            $resolverImage = $dbDir . $fileName; // <-- store relative path in DB
        }
    }

    $stmt = $conn->prepare("UPDATE complaints 
        SET status='Closed', closed_at=NOW(), resolver_image=?, remarks=?, false_info=? 
        WHERE id=?");
    $stmt->bind_param("ssii", $resolverImage, $remarks, $false_info, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php#complaints");
    exit;
}

/* ---------------- DELETE CATEGORY ---------------- */
if (isset($_GET['delete_category'])) {
    $id = (int)$_GET['delete_category'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute(); $stmt->close();
    header("Location: admin.php#categories");
    exit;
}

/* ---------------- DELETE MUNICIPALITY ---------------- */
if (isset($_GET['delete_municipality'])) {
    $id = (int)$_GET['delete_municipality'];
    $stmt = $conn->prepare("DELETE FROM municipalities WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute(); $stmt->close();
    header("Location: admin.php#municipalities");
    exit;
}

/* ---------------- FETCH DATA ---------------- */
$cats = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$muns = $conn->query("SELECT * FROM municipalities ORDER BY name ASC");

/* Filters */
$where=[]; $params=[]; $types="";
if(isset($_GET['status']) && $_GET['status']!==""){ $where[]="c.status=?"; $params[]=$_GET['status']; $types.="s"; }
if(isset($_GET['category_id']) && $_GET['category_id']!==""){ $where[]="c.category_id=?"; $params[]=$_GET['category_id']; $types.="i"; }
if(isset($_GET['municipality_id']) && $_GET['municipality_id']!==""){ $where[]="c.municipality_id=?"; $params[]=$_GET['municipality_id']; $types.="i"; }
$order=(isset($_GET['sort']) && $_GET['sort']=="oldest")?"ORDER BY c.created_at ASC":"ORDER BY c.created_at DESC";

$sql="SELECT c.*, cat.name AS cat_name, m.name AS mun_name FROM complaints c 
      JOIN categories cat ON c.category_id=cat.id 
      LEFT JOIN municipalities m ON c.municipality_id=m.id";
if($where) $sql.=" WHERE ".implode(" AND ",$where);
$sql.=" $order";

$stmt=$conn->prepare($sql);
if($where) $stmt->bind_param($types,...$params);
$stmt->execute();
$result=$stmt->get_result();

/* ---------------- ANALYTICS DATA ---------------- */
$status_counts=[];
foreach(['Pending','In Progress','Closed'] as $s){
    $status_counts[]=$conn->query("SELECT COUNT(*) FROM complaints WHERE status='$s'")->fetch_row()[0];
}

// Year-wise
$year_res=$conn->query("SELECT YEAR(created_at) as yr, COUNT(*) as cnt FROM complaints GROUP BY YEAR(created_at) ORDER BY yr ASC");
$year_labels=$year_data=[];
while($r=$year_res->fetch_assoc()){ $year_labels[]=$r['yr']; $year_data[]=$r['cnt']; }

// Category-wise
$cat_res=$conn->query("SELECT cat.name as cname, COUNT(*) as cnt FROM complaints c JOIN categories cat ON c.category_id=cat.id GROUP BY c.category_id");
$cat_labels=$cat_data=[];
while($r=$cat_res->fetch_assoc()){ $cat_labels[]=$r['cname']; $cat_data[]=$r['cnt']; }

// Municipality-wise
$mun_res=$conn->query("SELECT m.name as mname, COUNT(*) as cnt FROM complaints c LEFT JOIN municipalities m ON c.municipality_id=m.id GROUP BY c.municipality_id");
$mun_labels=$mun_data=[];
while($r=$mun_res->fetch_assoc()){ $mun_labels[]=$r['mname']; $mun_data[]=$r['cnt']; }

// Month-wise
$month_res=$conn->query("SELECT DATE_FORMAT(created_at,'%Y-%m') as mon, COUNT(*) as cnt FROM complaints GROUP BY DATE_FORMAT(created_at,'%Y-%m')");
$month_labels=$month_data=[];
while($r=$month_res->fetch_assoc()){ $month_labels[]=$r['mon']; $month_data[]=$r['cnt']; }

// False Info vs Verified
$false_count=$conn->query("SELECT COUNT(*) FROM complaints WHERE false_info=1")->fetch_row()[0];
$true_count=$conn->query("SELECT COUNT(*) FROM complaints WHERE false_info=0")->fetch_row()[0];

// Total complaints count
$total_complaints = $conn->query("SELECT COUNT(*) FROM complaints")->fetch_row()[0];
$closed_complaints = $conn->query("SELECT COUNT(*) FROM complaints WHERE status='Closed'")->fetch_row()[0];
$pending_complaints = $conn->query("SELECT COUNT(*) FROM complaints WHERE status='Pending'")->fetch_row()[0];
$inprogress_complaints = $conn->query("SELECT COUNT(*) FROM complaints WHERE status='In Progress'")->fetch_row()[0];

// Recent complaints for dashboard
$recent_complaints = $conn->query("SELECT c.*, cat.name AS cat_name, m.name AS mun_name FROM complaints c 
                                  JOIN categories cat ON c.category_id=cat.id 
                                  LEFT JOIN municipalities m ON c.municipality_id=m.id 
                                  ORDER BY c.created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CivicEye</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4CAF50',
                        'primary-light': '#E8F5E9',
                        'primary-dark': '#388E3C',
                        secondary: '#8BC34A',
                        accent: '#CDDC39',
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar {
            transition: all 0.3s ease;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: absolute;
                z-index: 50;
                height: 100vh;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
            .overlay.open {
                display: block;
            }
        }
        .active-section {
            display: block;
        }
        .inactive-section {
            display: none;
        }
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .status-inprogress {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        .status-closed {
            background-color: #D1FAE5;
            color: #065F46;
        }
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar bg-white w-64 px-4 py-6 shadow-lg">
            <div class="flex items-center justify-center mb-8">
                <img src="/static/civiceye.png" style="width: 50px;">
                <h1 class="text-xl font-bold text-primary ml-2">CivilEye</h1>
            </div>
            
            <nav>
                <a href="#" class="nav-link flex items-center py-3 px-4 rounded-lg text-primary bg-primary-light mb-2" data-section="dashboard">
                    <i class="fas fa-home mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="nav-link flex items-center py-3 px-4 rounded-lg text-gray-600 hover:bg-primary-light hover:text-primary mb-2" data-section="complaints">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <span>Complaints</span>
                </a>
                <a href="#" class="nav-link flex items-center py-3 px-4 rounded-lg text-gray-600 hover:bg-primary-light hover:text-primary mb-2" data-section="analytics">
                    <i class="fas fa-chart-bar mr-3"></i>
                    <span>Analytics</span>
                </a>
                <a href="#" class="nav-link flex items-center py-3 px-4 rounded-lg text-gray-600 hover:bg-primary-light hover:text-primary mb-2" data-section="categories">
                    <i class="fas fa-tags mr-3"></i>
                    <span>Categories</span>
                </a>
                <a href="#" class="nav-link flex items-center py-3 px-4 rounded-lg text-gray-600 hover:bg-primary-light hover:text-primary mb-2" data-section="municipalities">
                    <i class="fas fa-building mr-3"></i>
                    <span>Municipalities</span>
                </a>
            </nav>
            
            <div class="mt-8 pt-8 border-t border-gray-200">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-primary-light flex items-center justify-center">
                        <i class="fas fa-user text-primary"></i>
                    </div>
                    <div class="ml-3">
                        <p class="font-medium">Admin User</p>
                        <p class="text-sm text-gray-500">Administrator</p>
                    </div>
                </div>
            </div>

            <a href="?logout=1" class="bg-primary-light text-primary px-4 py-2 rounded-lg hover:bg-primary hover:text-white transition-colors">
                <i class="fas fa-sign-out-alt mr-2"></i>Logout
            </a>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center">
                        <button id="menu-toggle" class="md:hidden text-gray-500 hover:text-primary mr-4">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h2 class="text-xl font-semibold text-gray-800" id="section-title">Dashboard</h2>
                    </div>
                    <div class="text-sm text-gray-500">
                        <i class="far fa-clock mr-1"></i>
                        <span id="current-time"><?php echo date('M j, Y g:i A'); ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Dashboard Section -->
            <div id="dashboard" class="section active-section p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-primary">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Total Complaints</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?php echo $total_complaints; ?></h3>
                                <p class="text-green-500 text-sm mt-1"><i class="fas fa-chart-line mr-1"></i>All time complaints</p>
                            </div>
                            <div class="bg-primary-light p-3 rounded-lg">
                                <i class="fas fa-exclamation-circle text-primary text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-secondary">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Pending Complaints</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?php echo $pending_complaints; ?></h3>
                                <p class="text-red-500 text-sm mt-1"><i class="fas fa-clock mr-1"></i>Awaiting action</p>
                            </div>
                            <div class="bg-orange-100 p-3 rounded-lg">
                                <i class="fas fa-hourglass-half text-secondary text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-accent">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">In Progress</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?php echo $inprogress_complaints; ?></h3>
                                <p class="text-blue-500 text-sm mt-1"><i class="fas fa-cog mr-1"></i>Being resolved</p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-lg">
                                <i class="fas fa-tasks text-accent text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Closed Complaints</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?php echo $closed_complaints; ?></h3>
                                <p class="text-green-500 text-sm mt-1"><i class="fas fa-check mr-1"></i>Successfully resolved</p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-lg">
                                <i class="fas fa-check-circle text-purple-500 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Complaints -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-semibold text-gray-800">Recent Complaints</h3>
                            <a href="#" class="nav-link text-sm bg-primary text-white px-3 py-1 rounded-lg hover:bg-primary-dark" data-section="complaints">View All</a>
                        </div>
                        <div class="space-y-4">
                            <?php 
                            $recent_complaints = $conn->query("SELECT c.*, cat.name AS cat_name, m.name AS mun_name FROM complaints c 
                                                            JOIN categories cat ON c.category_id=cat.id 
                                                            LEFT JOIN municipalities m ON c.municipality_id=m.id 
                                                            ORDER BY c.created_at DESC LIMIT 5");
                            while($recent = $recent_complaints->fetch_assoc()): 
                                $statusClass = 'status-' . strtolower(str_replace(' ', '', $recent['status']));
                            ?>
                            <div class="flex items-center justify-between border-b pb-3">
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($recent['citizen_name'] ?? 'Unknown'); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($recent['cat_name'] ?? ''); ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                                    <?php echo $recent['status']; ?>
                                </span>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-semibold text-gray-800">Complaints Status</h3>
                        </div>
                        <canvas id="statusChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Complaints Section -->
            <div id="complaints" class="section inactive-section">
                <div class="px-6 pb-6">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-semibold text-gray-800">Manage Complaints</h3>
                            <button class="text-sm bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark">Export Report</button>
                        </div>
                        
                        <!-- Filters -->
                        <form method="GET" class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-4">
                            <input type="hidden" name="section" value="complaints">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    <option value="">All</option>
                                    <option value="Pending" <?= isset($_GET['status']) && $_GET['status']=="Pending"?"selected":""?>>Pending</option>
                                    <option value="In Progress" <?= isset($_GET['status']) && $_GET['status']=="In Progress"?"selected":""?>>In Progress</option>
                                    <option value="Closed" <?= isset($_GET['status']) && $_GET['status']=="Closed"?"selected":""?>>Closed</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                <select name="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    <option value="">All</option>
                                    <?php $cats->data_seek(0); while($c=$cats->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= isset($_GET['category_id']) && $_GET['category_id']==$c['id']?"selected":""?>><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Municipality</label>
                                <select name="municipality_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    <option value="">All</option>
                                    <?php $muns->data_seek(0); while($m=$muns->fetch_assoc()): ?>
                                    <option value="<?= $m['id'] ?>" <?= isset($_GET['municipality_id']) && $_GET['municipality_id']==$m['id']?"selected":""?>><?= htmlspecialchars($m['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                                <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    <option value="latest">Latest First</option>
                                    <option value="oldest" <?= isset($_GET['sort']) && $_GET['sort']=="oldest"?"selected":""?>>Oldest First</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark">Apply Filters</button>
                            </div>
                        </form>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Citizen</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Municipality</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    // Re-execute query for this section
                                    $stmt->execute();
                                    $result=$stmt->get_result();
                                    while($row=$result->fetch_assoc()): 
                                        $statusClass = 'status-' . strtolower(str_replace(' ', '', $row['status']));
                                    ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">#<?= $row['id'] ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['citizen_name']??'') ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($row['citizen_email']??'') ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['cat_name']??'') ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['mun_name']??'') ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?= $row['status'] ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <button class="text-primary hover:text-primary-dark view-complaint" data-id="<?= $row['id'] ?>">
                                                <?php if($row['status']!="Closed"): ?>
                                                <i class="fas fa-edit"></i> Resolve
                                                <?php else: ?>
                                                <i class="fas fa-eye"></i> View
                                                <?php endif; ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Analytics Section -->
            <div id="analytics" class="section inactive-section">
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Complaints by Category</h3>
                        <canvas id="categoryChart" height="250"></canvas>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Complaints by Municipality</h3>
                        <canvas id="municipalityChart" height="250"></canvas>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Monthly Complaints Trend</h3>
                        <canvas id="monthChart" height="250"></canvas>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">False Information Analysis</h3>
                        <canvas id="falseChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Categories Section -->
            <div id="categories" class="section inactive-section">
                <div class="px-6 pb-6">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-semibold text-gray-800">Manage Categories</h3>
                            <button class="text-sm bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark" onclick="toggleCategoryForm()">Add New Category</button>
                        </div>
                        
                        <!-- Add Category Form (Initially Hidden) -->
                        <div id="category-form" class="mb-6 p-4 border border-gray-200 rounded-lg hidden">
                            <h4 class="font-medium text-gray-800 mb-4">Add New Category</h4>
                            <form method="POST">
                                <input type="hidden" name="add_category" value="1">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                        <input type="text" name="cat_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" name="cat_email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-2">
                                    <button type="button" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100" onclick="toggleCategoryForm()">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">Add Category</button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    $cats = $conn->query("SELECT * FROM categories ORDER BY name ASC");
                                    while($cat = $cats->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap"><?= $cat['id'] ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($cat['name']) ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($cat['email']) ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="admin.php?delete_category=<?= $cat['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this category?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Municipalities Section -->
            <div id="municipalities" class="section inactive-section">
                <div class="px-6 pb-6">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-semibold text-gray-800">Manage Municipalities</h3>
                            <button class="text-sm bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark" onclick="toggleMunicipalityForm()">Add New Municipality</button>
                        </div>
                        
                        <!-- Add Municipality Form (Initially Hidden) -->
                        <div id="municipality-form" class="mb-6 p-4 border border-gray-200 rounded-lg hidden">
                            <h4 class="font-medium text-gray-800 mb-4">Add New Municipality</h4>
                            <form method="POST">
                                <input type="hidden" name="add_municipality" value="1">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                        <input type="text" name="mun_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                        <input type="text" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                        <input type="text" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-2">
                                    <button type="button" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100" onclick="toggleMunicipalityForm()">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">Add Municipality</button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    $muns = $conn->query("SELECT * FROM municipalities ORDER BY name ASC");
                                    while($mun = $muns->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap"><?= $mun['id'] ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($mun['name']) ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($mun['username']) ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($mun['email']) ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="admin.php?delete_municipality=<?= $mun['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this municipality?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Overlay -->
    <div id="overlay" class="overlay"></div>
    
    <!-- Complaint Detail Modal -->
    <div id="complaint-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-2xl w-11/12 md:w-3/4 lg:w-2/3 max-w-4xl max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b sticky top-0 bg-white">
                <h3 class="text-lg font-semibold text-gray-800">Complaint Details <span id="complaint-id"></span></h3>
                <button id="close-complaint-modal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="complaint-details">
                    <div class="loading-spinner"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Update current time
        function updateCurrentTime() {
            const now = new Date();
            const options = { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true };
            document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', options);
        }
        
        setInterval(updateCurrentTime, 60000); // Update every minute
        updateCurrentTime(); // Initial call

        // Toggle mobile menu
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('open');
        });
        
        // Close mobile menu when clicking overlay
        document.getElementById('overlay').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.remove('open');
            this.classList.remove('open');
        });
        
        // Close complaint modal
        document.getElementById('close-complaint-modal').addEventListener('click', function() {
            document.getElementById('complaint-modal').classList.add('hidden');
        });
        
        // Navigation between sections
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const section = this.getAttribute('data-section');
                
                // Update active nav link
                document.querySelectorAll('.nav-link').forEach(nav => {
                    nav.classList.remove('text-primary', 'bg-primary-light');
                    nav.classList.add('text-gray-600', 'hover:bg-primary-light', 'hover:text-primary');
                });
                this.classList.add('text-primary', 'bg-primary-light');
                this.classList.remove('text-gray-600', 'hover:bg-primary-light', 'hover:text-primary');
                
                // Update section title
                document.getElementById('section-title').textContent = 
                    section.charAt(0).toUpperCase() + section.slice(1);
                
                // Show selected section, hide others
                document.querySelectorAll('.section').forEach(sec => {
                    sec.classList.remove('active-section');
                    sec.classList.add('inactive-section');
                });
                document.getElementById(section).classList.remove('inactive-section');
                document.getElementById(section).classList.add('active-section');
                
                // Update URL hash
                window.location.hash = section;
            });
        });
        
        // Check URL hash on page load
        if (window.location.hash) {
            const section = window.location.hash.substring(1);
            const link = document.querySelector(`.nav-link[data-section="${section}"]`);
            if (link) {
                link.click();
            }
        }
        
        // View complaint details
        document.querySelectorAll('.view-complaint').forEach(button => {
            button.addEventListener('click', function() {
                const complaintId = this.getAttribute('data-id');
                document.getElementById('complaint-id').textContent = '#' + complaintId;
                document.getElementById('complaint-modal').classList.remove('hidden');
                
                // Show loading spinner
                document.getElementById('complaint-details').innerHTML = '<div class="loading-spinner"></div>';
                
                // Fetch complaint details via AJAX
                fetchComplaintDetails(complaintId);
            });
        });
        
        // Fetch complaint details via AJAX
        function fetchComplaintDetails(complaintId) {
            const formData = new FormData();
            formData.append('complaint_id', complaintId);
            
            fetch('get_complaint_details.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const complaint = data.complaint;
                    const statusClass = 'status-' + complaint.status.toLowerCase().replace(' ', '');
let imageHtml = '';
if (complaint.image) {
    let images = [];

    try {
        // Try parsing JSON string (e.g. '["uploads/xxx.jpeg"]')
        images = JSON.parse(complaint.image);
    } catch {
        // If it’s not JSON, just use it as single string
        images = [complaint.image];
    }

    // Render all images
    imageHtml = `
        <div class="mb-4">
            <h4 class="text-sm font-medium text-gray-500 mb-1">Complaint Image</h4>
            ${images.map(img => `
                <img src="../${img}" 
                     alt="Complaint Image" 
                     class="max-w-full h-auto rounded-lg border border-gray-200 mb-2">
            `).join('')}
        </div>
    `;
}

let resolverImageHtml = '';
if (complaint.resolver_image) {
    let rImages = [];

    try {
        rImages = JSON.parse(complaint.resolver_image);
    } catch {
        rImages = [complaint.resolver_image];
    }

    resolverImageHtml = `
        <div class="mb-4">
            <h4 class="text-sm font-medium text-gray-500 mb-1">Resolution Image</h4>
            ${rImages.map(img => `
                <img src="../${img}" 
                     alt="Resolution Image" 
                     class="max-w-full h-auto rounded-lg border border-gray-200 mb-2">
            `).join('')}
        </div>
    `;
}


                    
                    let resolutionForm = '';
                    if (complaint.status !== 'Closed') {
                        resolutionForm = `
                            <hr class="my-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4">Resolve Complaint</h4>
                            <form method="POST" enctype="multipart/form-data" id="resolve-form">
                                <input type="hidden" name="close_complaint" value="1">
                                <input type="hidden" name="complaint_id" value="${complaintId}">
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Resolved Image</label>
                                    <input type="file" name="resolver_image" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                                    <textarea name="remarks" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-light" rows="3" placeholder="Enter resolution details"></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="false_info" class="rounded border-gray-300 text-primary focus:ring-primary">
                                        <span class="ml-2 text-sm text-gray-700">Mark as False Information</span>
                                    </label>
                                </div>
                                
                                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark">Close Complaint</button>
                            </form>
                        `;
                    } else {
                        resolutionForm = `
                            <hr class="my-6">
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Resolution Remarks</h4>
                                <p class="text-gray-800">${complaint.remarks || 'No remarks provided.'}</p>
                            </div>
                            ${resolverImageHtml}
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Closed At</h4>
                                <p class="text-gray-800">${complaint.closed_at ? new Date(complaint.closed_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' }) : 'N/A'}</p>
                            </div>
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Marked as False Information</h4>
                                <p class="text-gray-800">${complaint.false_info ? 'Yes' : 'No'}</p>
                            </div>
                        `;
                    }
                    
                    document.getElementById('complaint-details').innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Complaint ID</h4>
                                <p class="text-gray-800">#${complaint.id}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Status</h4>
                                <p class="text-gray-800"><span class="px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">${complaint.status}</span></p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Citizen Name</h4>
                                <p class="text-gray-800">${complaint.citizen_name || 'N/A'}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Email</h4>
                                <p class="text-gray-800">${complaint.citizen_email || 'N/A'}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Phone</h4>
                                <p class="text-gray-800">${complaint.citizen_phone || 'N/A'}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Category</h4>
                                <p class="text-gray-800">${complaint.cat_name || 'N/A'}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Municipality</h4>
                                <p class="text-gray-800">${complaint.mun_name || 'N/A'}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Created At</h4>
                                <p class="text-gray-800">${new Date(complaint.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' })}</p>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Location</h4>
                            <p class="text-gray-800">${complaint.location || 'N/A'}</p>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Description</h4>
                            <p class="text-gray-800">${complaint.description || 'No description provided.'}</p>
                        </div>
                        
                        ${imageHtml}
                        
                        ${resolutionForm}
                    `;
                    
                    // Add event listener to the resolve form
                    const resolveForm = document.getElementById('resolve-form');
                    if (resolveForm) {
                        resolveForm.addEventListener('submit', function(e) {
                            if (!confirm('Are you sure you want to close this complaint?')) {
                                e.preventDefault();
                            }
                        });
                    }
                } else {
                    document.getElementById('complaint-details').innerHTML = `
                        <div class="text-center py-4 text-red-600">
                            <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                            <p>Error loading complaint details. Please try again.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('complaint-details').innerHTML = `
                    <div class="text-center py-4 text-red-600">
                        <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                        <p>Error loading complaint details. Please try again.</p>
                    </div>
                `;
            });
        }
        
        // Toggle category form
        function toggleCategoryForm() {
            const form = document.getElementById('category-form');
            form.classList.toggle('hidden');
        }
        
        // Toggle municipality form
        function toggleMunicipalityForm() {
            const form = document.getElementById('municipality-form');
            form.classList.toggle('hidden');
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Status Chart
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'In Progress', 'Closed'],
                    datasets: [{
                        data: <?= json_encode($status_counts) ?>,
                        backgroundColor: ['#F59E0B', '#3B82F6', '#10B981']
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
            
            // Category Chart
            new Chart(document.getElementById('categoryChart'), {
                type: 'pie',
                data: {
                    labels: <?= json_encode($cat_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($cat_data) ?>,
                        backgroundColor: ['#4CAF50', '#8BC34A', '#CDDC39', '#FFC107', '#FF9800', '#FF5722']
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
            
            // Municipality Chart
            new Chart(document.getElementById('municipalityChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($mun_labels) ?>,
                    datasets: [{
                        label: 'Complaints',
                        data: <?= json_encode($mun_data) ?>,
                        backgroundColor: '#4CAF50'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Month Chart
            new Chart(document.getElementById('monthChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($month_labels) ?>,
                    datasets: [{
                        label: 'Complaints',
                        data: <?= json_encode($month_data) ?>,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // False Info Chart
            new Chart(document.getElementById('falseChart'), {
                type: 'pie',
                data: {
                    labels: ['False Info', 'Verified'],
                    datasets: [{
                        data: [<?= $false_count ?>, <?= $true_count ?>],
                        backgroundColor: ['#EF4444', '#10B981']
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
        });
    </script>
</body>
</html