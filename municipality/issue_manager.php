 <?php
session_start();
include 'db.php';
if(!isset($_SESSION['logged_in'])) header("Location: login.php");

$mun_id = $_SESSION['municipality_id'];
$mun_name = $_SESSION['municipality_name'];

/* ---------------- EXPORT FUNCTIONALITY ---------------- */
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $filters = $_GET;
    unset($filters['export']);
    
    if ($export_type === 'csv') {
        exportCSV($conn, $mun_id, $filters);
    } elseif ($export_type === 'pdf') {
        exportPDF($conn, $mun_id, $filters);
    }
    exit;
}

/* ---------------- CLOSE COMPLAINT ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_complaint'])) {
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
            $resolverImage = $dbDir . $fileName; // <-- Save relative path in DB
        }
    }

    $stmt = $conn->prepare("UPDATE complaints 
        SET status='Closed', closed_at=NOW(), resolver_image=?, remarks=?, false_info=? 
        WHERE id=? AND municipality_id=?");
    $stmt->bind_param("ssiii", $resolverImage, $remarks, $false_info, $id, $mun_id);
    $stmt->execute();
    $stmt->close();

    header("Refresh:0");
}

/* ---------------- FILTERS ---------------- */
$where=["c.municipality_id=?"];
$params=[$mun_id]; $types="i";

// Multi-status
if(isset($_GET['status']) && is_array($_GET['status']) && count($_GET['status'])>0){
    $placeholders=implode(",",array_fill(0,count($_GET['status']),"?"));
    $where[]="c.status IN ($placeholders)";
    foreach($_GET['status'] as $s){ $params[]=$s; $types.="s"; }
}

// Category
if(isset($_GET['category_id']) && $_GET['category_id']!=""){ $where[]="c.category_id=?"; $params[]=$_GET['category_id']; $types.="i"; }

// Date range
if(!empty($_GET['from_date'])){ $where[]="DATE(c.created_at) >= ?"; $params[]=$_GET['from_date']; $types.="s"; }
if(!empty($_GET['to_date'])){ $where[]="DATE(c.created_at) <= ?"; $params[]=$_GET['to_date']; $types.="s"; }

// Year & Month
if(!empty($_GET['year'])){ $where[]="YEAR(c.created_at)=?"; $params[]=$_GET['year']; $types.="i"; }
if(!empty($_GET['month'])){ $where[]="MONTH(c.created_at)=?"; $params[]=$_GET['month']; $types.="i"; }

// Has image
if(isset($_GET['has_image'])){
    if($_GET['has_image']=="1") $where[]="c.resolver_image IS NOT NULL";
    if($_GET['has_image']=="0") $where[]="c.resolver_image IS NULL";
}

// Has remarks
if(isset($_GET['has_remarks'])){
    if($_GET['has_remarks']=="1") $where[]="c.remarks<>''";
    if($_GET['has_remarks']=="0") $where[]="c.remarks IS NULL OR c.remarks=''";
}

// False info
if(isset($_GET['false_info']) && $_GET['false_info']=="1"){ $where[]="c.false_info=1"; }

// Search by citizen or description
if(!empty($_GET['search'])){
    $where[]="(c.citizen_name LIKE ? OR c.description LIKE ?)";
    $params[]="%".$_GET['search']."%";
    $params[]="%".$_GET['search']."%";
    $types.="ss";
}

// Sorting
$order="ORDER BY c.created_at DESC";
if(isset($_GET['sort']) && $_GET['sort']=="oldest") $order="ORDER BY c.created_at ASC";

$sql="SELECT c.*, cat.name AS cat_name FROM complaints c JOIN categories cat ON c.category_id=cat.id WHERE ".implode(" AND ",$where)." $order";
$stmt=$conn->prepare($sql);
if(count($params)>0) $stmt->bind_param($types,...$params);
$stmt->execute();
$result=$stmt->get_result();

/* ---------------- GET CATEGORIES ---------------- */
$cats=$conn->query("SELECT * FROM categories ORDER BY name ASC");

/* ---------------- EXPORT FUNCTIONS ---------------- */
function exportCSV($conn, $mun_id, $filters) {
    // Apply the same filtering logic as above but for export
    $where=["c.municipality_id=?"];
    $params=[$mun_id]; $types="i";
    
    // Apply all filters (same logic as above)
    if(isset($filters['status']) && is_array($filters['status']) && count($filters['status'])>0){
        $placeholders=implode(",",array_fill(0,count($filters['status']),"?"));
        $where[]="c.status IN ($placeholders)";
        foreach($filters['status'] as $s){ $params[]=$s; $types.="s"; }
    }
    
    if(isset($filters['category_id']) && $filters['category_id']!=""){ $where[]="c.category_id=?"; $params[]=$filters['category_id']; $types.="i"; }
    if(!empty($filters['from_date'])){ $where[]="DATE(c.created_at) >= ?"; $params[]=$filters['from_date']; $types.="s"; }
    if(!empty($filters['to_date'])){ $where[]="DATE(c.created_at) <= ?"; $params[]=$filters['to_date']; $types.="s"; }
    if(!empty($filters['year'])){ $where[]="YEAR(c.created_at)=?"; $params[]=$filters['year']; $types.="i"; }
    if(!empty($filters['month'])){ $where[]="MONTH(c.created_at)=?"; $params[]=$filters['month']; $types.="i"; }
    
    if(isset($filters['has_image'])){
        if($filters['has_image']=="1") $where[]="c.resolver_image IS NOT NULL";
        if($filters['has_image']=="0") $where[]="c.resolver_image IS NULL";
    }
    
    if(isset($filters['has_remarks'])){
        if($filters['has_remarks']=="1") $where[]="c.remarks<>''";
        if($filters['has_remarks']=="0") $where[]="c.remarks IS NULL OR c.remarks=''";
    }
    
    if(isset($filters['false_info']) && $filters['false_info']=="1"){ $where[]="c.false_info=1"; }
    
    if(!empty($filters['search'])){
        $where[]="(c.citizen_name LIKE ? OR c.description LIKE ?)";
        $params[]="%".$filters['search']."%";
        $params[]="%".$filters['search']."%";
        $types.="ss";
    }
    
    $order="ORDER BY c.created_at DESC";
    if(isset($filters['sort']) && $filters['sort']=="oldest") $order="ORDER BY c.created_at ASC";
    
    $sql="SELECT c.*, cat.name AS cat_name FROM complaints c JOIN categories cat ON c.category_id=cat.id WHERE ".implode(" AND ",$where)." $order";
    $stmt=$conn->prepare($sql);
    if(count($params)>0) $stmt->bind_param($types,...$params);
    $stmt->execute();
    $result=$stmt->get_result();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=complaints_export_' . date('Y-m-d') . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, array('ID', 'Citizen Name', 'Category', 'Status', 'Description', 'Created At', 'Closed At', 'False Info', 'Remarks'));
    
    // Add data rows
    while($row = $result->fetch_assoc()) {
        fputcsv($output, array(
            $row['id'],
            $row['citizen_name'],
            $row['cat_name'],
            $row['status'],
            $row['description'],
            $row['created_at'],
            $row['closed_at'] ? $row['closed_at'] : 'N/A',
            $row['false_info'] ? 'Yes' : 'No',
            $row['remarks'] ? $row['remarks'] : 'N/A'
        ));
    }
    
    fclose($output);
    exit;
}

function exportPDF($conn, $mun_id, $filters) {
    // For PDF generation, we'll use a simple approach with a PDF library
    // This is a placeholder - you might want to use a library like TCPDF or Dompdf
    
    // Apply the same filtering logic as above
    $where=["c.municipality_id=?"];
    $params=[$mun_id]; $types="i";
    
    // Apply all filters (same logic as above)
    if(isset($filters['status']) && is_array($filters['status']) && count($filters['status'])>0){
        $placeholders=implode(",",array_fill(0,count($filters['status']),"?"));
        $where[]="c.status IN ($placeholders)";
        foreach($filters['status'] as $s){ $params[]=$s; $types.="s"; }
    }
    
    if(isset($filters['category_id']) && $filters['category_id']!=""){ $where[]="c.category_id=?"; $params[]=$filters['category_id']; $types.="i"; }
    if(!empty($filters['from_date'])){ $where[]="DATE(c.created_at) >= ?"; $params[]=$filters['from_date']; $types.="s"; }
    if(!empty($filters['to_date'])){ $where[]="DATE(c.created_at) <= ?"; $params[]=$filters['to_date']; $types.="s"; }
    if(!empty($filters['year'])){ $where[]="YEAR(c.created_at)=?"; $params[]=$filters['year']; $types.="i"; }
    if(!empty($filters['month'])){ $where[]="MONTH(c.created_at)=?"; $params[]=$filters['month']; $types.="i"; }
    
    if(isset($filters['has_image'])){
        if($filters['has_image']=="1") $where[]="c.resolver_image IS NOT NULL";
        if($filters['has_image']=="0") $where[]="c.resolver_image IS NULL";
    }
    
    if(isset($filters['has_remarks'])){
        if($filters['has_remarks']=="1") $where[]="c.remarks<>''";
        if($filters['has_remarks']=="0") $where[]="c.remarks IS NULL OR c.remarks=''";
    }
    
    if(isset($filters['false_info']) && $filters['false_info']=="1"){ $where[]="c.false_info=1"; }
    
    if(!empty($filters['search'])){
        $where[]="(c.citizen_name LIKE ? OR c.description LIKE ?)";
        $params[]="%".$filters['search']."%";
        $params[]="%".$filters['search']."%";
        $types.="ss";
    }
    
    $order="ORDER BY c.created_at DESC";
    if(isset($filters['sort']) && $filters['sort']=="oldest") $order="ORDER BY c.created_at ASC";
    
    $sql="SELECT c.*, cat.name AS cat_name FROM complaints c JOIN categories cat ON c.category_id=cat.id WHERE ".implode(" AND ",$where)." $order";
    $stmt=$conn->prepare($sql);
    if(count($params)>0) $stmt->bind_param($types,...$params);
    $stmt->execute();
    $result=$stmt->get_result();
    
    // For a real implementation, you would use a PDF library here
    // This is a simplified version that outputs HTML that can be saved as PDF
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename=complaints_export_' . date('Y-m-d') . '.pdf');
    
    // Simple HTML output that could be converted to PDF with a library like Dompdf
    echo "<html><body>";
    echo "<h1>Complaints Export - " . date('Y-m-d') . "</h1>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Citizen Name</th><th>Category</th><th>Status</th><th>Created At</th><th>Closed At</th><th>False Info</th></tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['citizen_name'] . "</td>";
        echo "<td>" . $row['cat_name'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . ($row['closed_at'] ? $row['closed_at'] : 'N/A') . "</td>";
        echo "<td>" . ($row['false_info'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</body></html>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Manager - <?= $_SESSION['municipality_name'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Previous styles remain the same, adding export button styles */
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
            max-width: 1400px;
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
            max-width: 1400px;
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
        
        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 25px;
        }
        
        .page-header {
            margin-bottom: 25px;
        }
        
        .page-header h2 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
            border-bottom: 2px solid #b31b1b;
            padding-bottom: 10px;
        }
        
        /* Filter Panel */
        .filter-panel {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            margin-bottom: 15px;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .filter-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            background: #f8f9fa;
        }
        
        .filter-control:focus {
            outline: none;
            border-color: #0d3d66;
            box-shadow: 0 0 0 3px rgba(13, 61, 102, 0.15);
            background: #fff;
        }
        
        .btn-filter {
            background: #0d3d66;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            grid-column: 1 / -1;
            justify-self: start;
        }
        
        .btn-filter:hover {
            background: #0a2e4d;
        }
        
        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }
        
        .issues-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .issues-table th {
            background: #0d3d66;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .issues-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .issues-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .issues-table tr:hover {
            background: #e9ecef;
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #ffeaa7;
            color: #d35400;
        }
        
        .status-inprogress {
            background: #d6eaf8;
            color: #2980b9;
        }
        
        .status-closed {
            background: #d5f5e3;
            color: #27ae60;
        }
        
        /* Action form */
        .action-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .form-input-small {
            padding: 8px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn-action {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-action:hover {
            background: #219653;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .filter-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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
                gap: 10px;
            }
            
            .nav-links a {
                margin: 0;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .action-form {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .government-header {
                padding: 15px;
            }
            
            .main-nav {
                padding: 12px 15px;
            }
            
            .filter-panel {
                padding: 15px;
            }
            
            .table-container {
                padding: 15px;
            }
            
            .issues-table {
                font-size: 14px;
            }
            
            .issues-table th, 
            .issues-table td {
                padding: 8px 10px;
            }
        }
        
        /* Print Styles */
        @media print {
            .government-header, .main-nav, .filter-panel, .btn-action {
                display: none;
            }
            
            body {
                background: white;
                color: black;
            }
            
            .table-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        /* Export buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #2c3e50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn-export:hover {
            background: #1a252f;
        }
        
        .btn-export.csv {
            background: #27ae60;
        }
        
        .btn-export.csv:hover {
            background: #219653;
        }
        
        .btn-export.pdf {
            background: #c0392b;
        }
        
        .btn-export.pdf:hover {
            background: #a93226;
        }
        
        /* Rest of the CSS remains the same */
        /* ... (all the previous CSS styles) ... */
    </style>
</head>
<body>
    <!-- Government Header -->
    <header class="government-header">
        <div class="header-content">
            <div class="agency-info">
                <h1>Municipal Government Portal</h1>
                <p>Grievance Management System</p>
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
                <a href="index.php"><i class="fas fa-chart-bar"></i> Analytics</a>
                <a href="logout.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h2>Issue Management</h2>
            <p>Manage and resolve citizen complaints and grievances</p>
        </div>
        
        <!-- Export Buttons -->
        <div class="export-buttons">
            <?php
            // Build query string for export while preserving filters
            $export_query = $_GET;
            unset($export_query['export']);
            $query_string = http_build_query($export_query);
            ?>
            <a href="?<?= $query_string ?>&export=csv" class="btn-export csv">
                <i class="fas fa-file-csv"></i> Export as CSV
            </a>
            <a href="?<?= $query_string ?>&export=pdf" class="btn-export pdf">
                <i class="fas fa-file-pdf"></i> Export as PDF
            </a>
        </div>
        
        <!-- Filter Panel -->
        <div class="filter-panel">
            <form method="GET">
                <div class="filter-grid">
                    <!-- Status Filter -->
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status[]" multiple class="filter-control" size="3">
                            <option value="Pending" <?= in_array("Pending", $_GET['status']??[])?"selected":"" ?>>Pending</option>
                            <option value="In Progress" <?= in_array("In Progress", $_GET['status']??[])?"selected":"" ?>>In Progress</option>
                            <option value="Closed" <?= in_array("Closed", $_GET['status']??[])?"selected":"" ?>>Closed</option>
                        </select>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category_id" class="filter-control">
                            <option value="">All Categories</option>
                            <?php while($c=$cats->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= (isset($_GET['category_id']) && $_GET['category_id']==$c['id'])?"selected":"" ?>>
                                <?= $c['name'] ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- Date Range -->
                    <div class="filter-group">
                        <label class="filter-label">From Date</label>
                        <input type="date" name="from_date" value="<?= $_GET['from_date']??'' ?>" class="filter-control">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">To Date</label>
                        <input type="date" name="to_date" value="<?= $_GET['to_date']??'' ?>" class="filter-control">
                    </div>
                    
                    <!-- Year Filter -->
                    <div class="filter-group">
                        <label class="filter-label">Year</label>
                        <select name="year" class="filter-control">
                            <option value="">All Years</option>
                            <?php for($y=2020;$y<=date('Y');$y++): ?>
                            <option value="<?= $y ?>" <?= (isset($_GET['year']) && $_GET['year']==$y)?"selected":"" ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <!-- Month Filter -->
                    <div class="filter-group">
                        <label class="filter-label">Month</label>
                        <select name="month" class="filter-control">
                            <option value="">All Months</option>
                            <?php for($m=1;$m<=12;$m++): ?>
                            <option value="<?= $m ?>" <?= (isset($_GET['month']) && $_GET['month']==$m)?"selected":"" ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <!-- Has Image Filter -->
                    <div class="filter-group">
                        <label class="filter-label">Has Resolution Image</label>
                        <select name="has_image" class="filter-control">
                            <option value="">All</option>
                            <option value="1" <?= isset($_GET['has_image']) && $_GET['has_image']=="1"?"selected":""?>>Yes</option>
                            <option value="0" <?= isset($_GET['has_image']) && $_GET['has_image']=="0"?"selected":""?>>No</option>
                        </select>
                    </div>
                    
                    <!-- Has Remarks Filter -->
                    <div class="filter-group">
                        <label class="filter-label">Has Remarks</label>
                        <select name="has_remarks" class="filter-control">
                            <option value="">All</option>
                            <option value="1" <?= isset($_GET['has_remarks']) && $_GET['has_remarks']=="1"?"selected":""?>>Yes</option>
                            <option value="0" <?= isset($_GET['has_remarks']) && $_GET['has_remarks']=="0"?"selected":""?>>No</option>
                        </select>
                    </div>
                    
                    <!-- False Info Filter -->
                    <div class="filter-group">
                        <label class="filter-label">False Information</label>
                        <select name="false_info" class="filter-control">
                            <option value="">All</option>
                            <option value="1" <?= isset($_GET['false_info']) && $_GET['false_info']=="1"?"selected":""?>>Yes Only</option>
                        </select>
                    </div>
                    
                    <!-- Search -->
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search']??'') ?>" 
                               placeholder="Citizen or description" class="filter-control">
                    </div>
                    
                    <!-- Sort -->
                    <div class="filter-group">
                        <label class="filter-label">Sort By</label>
                        <select name="sort" class="filter-control">
                            <option value="latest">Newest First</option>
                            <option value="oldest" <?= isset($_GET['sort']) && $_GET['sort']=="oldest"?"selected":""?>>Oldest First</option>
                        </select>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Issues Table -->
        <div class="table-container">
            <table class="issues-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Citizen</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Closed</th>
                        <th>False Info</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row=$result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['citizen_name']) ?></td>
                        <td><?= htmlspecialchars($row['cat_name']) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '', $row['status'])) ?>">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                        <td><?= $row['closed_at'] ? date('M j, Y', strtotime($row['closed_at'])) : '-' ?></td>
                        <td><?= isset($row['false_info']) && $row['false_info']==1?"Yes":"No" ?></td>
                        <td>
                            <?php if($row['status']!="Closed"): ?>
                            <form method="POST" enctype="multipart/form-data" class="action-form">
                                <input type="hidden" name="close_complaint" value="1">
                                <input type="hidden" name="complaint_id" value="<?= $row['id'] ?>">
                                
                                <input type="file" name="resolver_image" class="form-input-small" required title="Resolution image">
                                <input type="text" name="remarks" placeholder="Remarks" class="form-input-small" required>
                                
                                <label class="checkbox-group">
                                    <input type="checkbox" name="false_info"> 
                                    <span style="font-size: 13px;">False Info</span>
                                </label>
                                
                                <button type="submit" class="btn-action">
                                    <i class="fas fa-check"></i> Resolve
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="status-badge status-closed">Resolved</span>
                            <?php endif; ?>
                            <a href="view_complaint.php?id=<?= $row['id'] ?>" class="status-badge status-closed">View in Detail</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html