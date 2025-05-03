<?php
// Start session and include database connection
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/index.php');
    exit;
}

// Get database connection
$conn = getConnection();

// Get overall statistics for dashboard
// Total sales today
$today = date('Y-m-d');
$sales_query = "SELECT COUNT(*) as sales_count, SUM(total_amount) as total_sales 
                FROM sales_transactions 
                WHERE DATE(sale_date) = '$today'";
$sales_result = mysqli_query($conn, $sales_query);
$sales_data = mysqli_fetch_assoc($sales_result);
$today_sales_count = $sales_data['sales_count'] ?: 0;
$today_sales_amount = $sales_data['total_sales'] ?: 0;

// Total users
$users_query = "SELECT COUNT(*) as user_count FROM users WHERE status = 'active'";
$users_result = mysqli_query($conn, $users_query);
$users_data = mysqli_fetch_assoc($users_result);
$total_users = $users_data['user_count'];

// Total products
$products_query = "SELECT COUNT(*) as product_count FROM products WHERE status = 'active'";
$products_result = mysqli_query($conn, $products_query);
$products_data = mysqli_fetch_assoc($products_result);
$total_products = $products_data['product_count'];

// Recent sales
$recent_sales_query = "SELECT t.sale_id, t.invoice_number, t.total_amount, t.sale_date, 
                       c.first_name, c.last_name, u.full_name as salesperson
                       FROM sales_transactions t
                       LEFT JOIN customers c ON t.customer_id = c.customer_id
                       LEFT JOIN users u ON t.salesperson_id = u.user_id
                       ORDER BY t.sale_date DESC
                       LIMIT 5";
$recent_sales_result = mysqli_query($conn, $recent_sales_query);

// Low stock products
$low_stock_query = "SELECT product_id, name, stock_quantity, minimum_stock
                   FROM products
                   WHERE stock_quantity <= minimum_stock AND status = 'active'
                   ORDER BY (stock_quantity / minimum_stock) ASC
                   LIMIT 5";
$low_stock_result = mysqli_query($conn, $low_stock_query);

// Include header
include '../includes/header/header.php';
?>

<div class="welcome-banner">
    <div class="welcome-message">
        <h2>Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
        <p><?php echo date('l, F d, Y'); ?></p>
    </div>
    <div class="quick-actions">
        <a href="../admin/reports.php" class="action-button"><i class="fas fa-chart-bar"></i> View Reports</a>
        <a href="../admin/reports.php?report=predictions" class="action-button"><i class="fas fa-robot"></i> View Predictions</a>
        <a href="../admin/products.php?action=add" class="action-button"><i class="fas fa-box-open"></i> Add Product</a>
        <a href="../admin/users.php?action=add" class="action-button"><i class="fas fa-user-plus"></i> Add User</a>
    </div>
</div>

<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #2196F3;">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-card-info">
            <h3>Today's Sales</h3>
            <p class="stat-value"><?php echo $today_sales_count; ?></p>
            <p class="stat-label">Total: $<?php echo number_format($today_sales_amount, 2); ?></p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #673AB7;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-card-info">
            <h3>Total Users</h3>
            <p class="stat-value"><?php echo $total_users; ?></p>
            <p class="stat-label">Active accounts</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #FF9800;">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-card-info">
            <h3>Total Products</h3>
            <p class="stat-value"><?php echo $total_products; ?></p>
            <p class="stat-label">In inventory</p>
        </div>
    </div>
</div>

<div class="dashboard-row">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-shopping-bag"></i> Recent Sales Transactions</h3>
        </div>
        <div class="card-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Salesperson</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($recent_sales_result) > 0): ?>
                        <?php while ($sale = mysqli_fetch_assoc($recent_sales_result)): ?>
                            <tr>
                                <td><a href="../admin/view_sale.php?id=<?php echo $sale['sale_id']; ?>"><?php echo $sale['invoice_number']; ?></a></td>
                                <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                <td><?php echo $sale['first_name'] ? $sale['first_name'] . ' ' . $sale['last_name'] : 'Walk-in Customer'; ?></td>
                                <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><?php echo $sale['salesperson']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No sales recorded yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="card-footer">
                <a href="../admin/reports.php?report=sales" class="btn-link">View All Sales</a>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Products</h3>
        </div>
        <div class="card-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($low_stock_result) > 0): ?>
                        <?php while ($product = mysqli_fetch_assoc($low_stock_result)): ?>
                            <tr>
                                <td><a href="../admin/edit_product.php?id=<?php echo $product['product_id']; ?>"><?php echo $product['name']; ?></a></td>
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
                            <td colspan="4">No low stock products</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="card-footer">
                <a href="../admin/products.php" class="btn-link">Manage Products</a>
            </div>
        </div>
    </div>
</div>

<style>
    .welcome-banner {
        background-color: #2c3e50;
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
        color: #2c3e50;
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
        color: #2c3e50;
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
        background-color: #2c3e50;
        color: white;
        text-decoration: none;
        border-radius: 4px;
    }
    
    .btn-link:hover {
        background-color: #34495e;
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
</style>

<?php
// Close the database connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>