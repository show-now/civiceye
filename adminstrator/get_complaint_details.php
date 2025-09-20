 <?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_POST['complaint_id'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    
    $sql = "SELECT c.*, cat.name AS cat_name, m.name AS mun_name 
            FROM complaints c 
            JOIN categories cat ON c.category_id=cat.id 
            LEFT JOIN municipalities m ON c.municipality_id=m.id 
            WHERE c.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $complaint = $result->fetch_assoc();
        echo json_encode(['success' => true, 'complaint' => $complaint]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No complaint ID provided']);
