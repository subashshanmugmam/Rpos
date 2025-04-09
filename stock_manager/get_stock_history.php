<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a stock manager
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "stock_manager") {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

// Get database connection
$conn = getConnection();

// Get product ID
$productId = isset($_GET["product_id"]) ? (int)$_GET["product_id"] : 0;

if ($productId <= 0) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Invalid product ID"]);
    exit;
}

// Get stock movement history
$query = "SELECT sm.*, 
          CONCAT(u.full_name, ' (', u.username, ')') as user_name
          FROM stock_movements sm
          LEFT JOIN users u ON sm.performed_by = u.user_id
          WHERE sm.product_id = ?
          ORDER BY sm.movement_date DESC
          LIMIT 50";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

$stmt->close();
mysqli_close($conn);

// Return JSON response
header("Content-Type: application/json");
echo json_encode($history);
?>