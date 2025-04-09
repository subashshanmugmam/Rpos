<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a salesperson
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "salesperson") {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

// Get database connection
$conn = getConnection();

// Get search term
$term = isset($_GET["term"]) ? $_GET["term"] : "";
$term = sanitizeInput($term, $conn);

// Search for products
$query = "SELECT p.*, c.name as category_name 
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.category_id
          WHERE p.status = 'active' 
          AND p.stock_quantity > 0
          AND (p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ?)
          ORDER BY p.name
          LIMIT 30";

$stmt = $conn->prepare($query);
$searchTerm = "%" . $term . "%";
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    // Format price for display
    $row["selling_price"] = "$" . number_format($row["selling_price"], 2);
    $products[] = $row;
}

$stmt->close();
mysqli_close($conn);

// Return JSON response
header("Content-Type: application/json");
echo json_encode($products);
?>