 <?php
include 'db.php';
if(isset($_GET['id'])){
    $id = intval($_GET['id']);
    $conn->query("UPDATE complaints SET status='Closed', closed_at=NOW() WHERE id=$id");
}
header("Location: admin.php");
exit;
?>