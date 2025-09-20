 <?php
$servername = "sql102.infinityfree.com";
$username = "if0_37947537";       // MySQL username
$password = "n0thingandsl33p";           // MySQL password
$dbname = "if0_37947537_hackathon1";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>