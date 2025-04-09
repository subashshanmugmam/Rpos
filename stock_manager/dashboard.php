<?php
// Start session and include database connection
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a stock manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'stock_manager') {
    header('Location: ../public/index.php');
    exit;
}

// Get database connection
$conn = getConnection();

// Get inventory statistics
// Total products
$products_query = "SELECT COUNT(*) as product_count FROM products WHERE status = 'active'";
$products_result = mysqli_query($conn, $products_query);
$products_data = mysqli_fetch_assoc($products_result);
$total_products = $products_data['product_count'];

// Low stock products count
$low_stock_query = "SELECT COUNT(*) as low_stock_count 
                    FROM products 
                    WHERE stock_quantity <= minimum_stock AND status = 'active'";
$low_stock_result = mysqli_query($conn, $low_stock_query);
$low_stock_data = mysqli_fetch_assoc($low_stock_result);
$low_stock_count = $low_stock_data['low_stock_count'];

// Out of stock products count
$out_of_stock_query = "SELECT COUNT(*) as out_of_stock_count 
                       FROM products 
                       WHERE stock_quantity = 0 AND status = 'active'";
$out_of_stock_result = mysqli_query($conn, $out_of_stock_query);
$out_of_stock_data = mysqli_fetch_assoc($out_of_stock_result);
$out_of_stock_count = $out_of_stock_data['out_of_stock_count'];

// Recent stock movements
$stock_movements_query = "SELECT sm.movement_id, sm.product_id, p.name as product_name, 
                          sm.quantity, sm.movement_type, sm.movement_date, sm.notes
                          FROM stock_movements sm
                          JOIN products p ON sm.product_id = p.product_id
                          ORDER BY sm.movement_date DESC
                          LIMIT 10";
$stock_movements_result = mysqli_query($conn, $stock_movements_query);

// Low stock products details
$low_stock_detail_query = "SELECT p.product_id, p.name, p.stock_quantity, p.minimum_stock,
                          c.name as category_name
                          FROM products p
                          LEFT JOIN categories c ON p.category_id = c.category_id
                          WHERE p.stock_quantity <= p.minimum_stock AND p.status = 'active'
                          ORDER BY (p.stock_quantity / p.minimum_stock) ASC
                          LIMIT 5";
$low_stock_detail_result = mysqli_query($conn, $low_stock_detail_query);

// Include header
include '../includes/header/header.php';
?>

<div class="welcome-banner">
    <div class="welcome-message">
        <h2>Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
        <p><?php echo date('l, F d, Y'); ?></p>
    </div>
    <div class="quick-actions">
        <a href="../stock_manager/inventory.php?action=add" class="action-button"><i class="fas fa-plus-circle"></i> Add Stock</a>
        <a href="../stock_manager/products.php?action=add" class="action-button"><i class="fas fa-box-open"></i> New Product</a>
    </div>
</div>

<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #3498db;">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-card-info">
            <h3>Total Products</h3>
            <p class="stat-value"><?php echo $total_products; ?></p>
            <p class="stat-label">In inventory</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #f39c12;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-card-info">
            <h3>Low Stock Products</h3>
            <p class="stat-value"><?php echo $low_stock_count; ?></p>
            <p class="stat-label">Need attention</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #e74c3c;">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-card-info">
            <h3>Out of Stock</h3>
            <p class="stat-value"><?php echo $out_of_stock_count; ?></p>
            <p class="stat-label">Require immediate action</p>
        </div>
    </div>
</div>

<div class="dashboard-row">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Products</h3>
        </div>
        <div class="card-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Min Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($low_stock_detail_result) > 0): ?>
                        <?php while ($product = mysqli_fetch_assoc($low_stock_detail_result)): ?>
                            <tr>
                                <td><a href="../stock_manager/edit_product.php?id=<?php echo $product['product_id']; ?>"><?php echo $product['name']; ?></a></td>
                                <td><?php echo $product['category_name']; ?></td>
                                <td><?php echo $product['stock_quantity']; ?></td>
                                <td><?php echo $product['minimum_stock']; ?></td>
                                <td>
                                    <?php if ($product['stock_quantity'] == 0): ?>
                                        <span class="status-badge status-red">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="status-badge status-yellow">Low Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">All products are well stocked</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="card-footer">
                <a href="../stock_manager/inventory.php?filter=low" class="btn-link">View All Low Stock</a>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Stock Activities</h3>
        </div>
        <div class="card-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($stock_movements_result) > 0): ?>
                        <?php while ($movement = mysqli_fetch_assoc($stock_movements_result)): ?>
                            <tr>
                                <td><?php echo $movement['product_name']; ?></td>
                                <td>
                                    <?php if ($movement['movement_type'] == 'in'): ?>
                                        <span class="status-badge status-green">Stock In</span>
                                    <?php elseif ($movement['movement_type'] == 'out'): ?>
                                        <span class="status-badge status-red">Stock Out</span>
                                    <?php elseif ($movement['movement_type'] == 'adjustment'): ?>
                                        <span class="status-badge status-blue">Adjustment</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $movement['quantity']; ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($movement['movement_date'])); ?></td>
                                <td><?php echo $movement['notes']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No recent stock activities</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="card-footer">
                <a href="../stock_manager/stock_reports.php" class="btn-link">View All Activities</a>
            </div>
        </div>
    </div>
</div>

<style>
    .welcome-banner {
        background-color: #3498db;
        padding: 20px;
        border-radius: 5px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .welcome-message h2 {
        margin: 0;
        font-size: 24px;
    }
    
    .welcome-message p {
        margin: 5px 0 0;
        opacity: 0.8;
    }
    
    .quick-actions {
        display: flex;
        gap: 10px;
    }
    
    .action-button {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        display: flex;
        align-items: center;
    }
    
    .action-button i {
        margin-right: 5px;
    }
    
    .action-button:hover {
        background-color: rgba(255, 255, 255, 0.3);
    }
    
    .dashboard-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        flex: 1;
        min-width: 200px;
        background-color: #fff;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        padding: 20px;
        display: flex;
        align-items: center;
    }
    
    .stat-card-icon {
        font-size: 24px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-right: 15px;
    }
    
    .stat-card-info h3 {
        margin: 0;
        font-size: 16px;
        color: #555;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: bold;
        margin: 0;
        color: #333;
    }
    
    .stat-label {
        font-size: 14px;
        color: #777;
        margin: 0;
    }
    
    .dashboard-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .dashboard-card {
        flex: 1;
        min-width: 300px;
        background-color: #fff;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    .card-header {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
    }
    
    .card-header h3 {
        margin: 0;
        font-size: 18px;
        display: flex;
        align-items: center;
    }
    
    .card-header h3 i {
        margin-right: 10px;
        color: #3498db;
    }
    
    .card-content {
        padding: 20px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        text-align: left;
        padding: 10px;
        border-bottom: 2px solid #eee;
        color: #555;
    }
    
    .data-table td {
        padding: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .data-table a {
        color: #3498db;
        text-decoration: none;
    }
    
    .data-table a:hover {
        text-decoration: underline;
    }
    
    .card-footer {
        padding: 15px 20px;
        border-top: 1px solid #eee;
        text-align: right;
    }
    
    .btn-link {
        display: inline-block;
        padding: 8px 15px;
        background-color: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 4px;
    }
    
    .btn-link:hover {
        background-color: #2980b9;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .status-green {
        background-color: #e8f5e9;
        color: #4CAF50;
    }
    
    .status-yellow {
        background-color: #fff8e1;
        color: #FFC107;
    }
    
    .status-red {
        background-color: #ffebee;
        color: #F44336;
    }
    
    .status-blue {
        background-color: #e3f2fd;
        color: #2196F3;
    }
</style>

<?php
// Close the database connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>