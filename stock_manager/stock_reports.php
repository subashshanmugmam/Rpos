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

// Get time period for reports
$timePeriod = isset($_GET['period']) ? $_GET['period'] : '30days';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Get categories for filter
$categories = [];
$categoryQuery = "SELECT category_id, name FROM categories WHERE status = 'active' ORDER BY name";
$categoryResult = $conn->query($categoryQuery);
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Calculate report data
// 1. Inventory Value Summary
$inventoryValueQuery = "SELECT 
                         SUM(p.stock_quantity * p.cost_price) AS total_cost_value,
                         SUM(p.stock_quantity * p.selling_price) AS total_retail_value,
                         SUM(p.stock_quantity * (p.selling_price - p.cost_price)) AS total_potential_profit,
                         COUNT(p.product_id) AS total_products,
                         SUM(p.stock_quantity) AS total_quantity
                        FROM products p";

if ($categoryFilter > 0) {
    $inventoryValueQuery .= " WHERE p.category_id = $categoryFilter";
}

$inventoryValueResult = $conn->query($inventoryValueQuery);
$inventorySummary = $inventoryValueResult->fetch_assoc();

// 2. Low Stock Items
$lowStockQuery = "SELECT p.*, c.name as category_name,
                  (p.minimum_stock - p.stock_quantity) as shortage
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.category_id
                  WHERE p.stock_quantity < p.minimum_stock";

if ($categoryFilter > 0) {
    $lowStockQuery .= " AND p.category_id = $categoryFilter";
}

$lowStockQuery .= " ORDER BY shortage DESC";
$lowStockResult = $conn->query($lowStockQuery);
$lowStockItems = [];
if ($lowStockResult) {
    while ($row = $lowStockResult->fetch_assoc()) {
        $lowStockItems[] = $row;
    }
}

// 3. Get timeframe for stock movement
$startDate = '';
switch ($timePeriod) {
    case '7days':
        $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
        break;
    case '30days':
    default:
        $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        break;
    case '90days':
        $startDate = date('Y-m-d H:i:s', strtotime('-90 days'));
        break;
    case 'year':
        $startDate = date('Y-m-d H:i:s', strtotime('-1 year'));
        break;
    case 'all':
        $startDate = '1970-01-01 00:00:00';
        break;
}

// 4. Stock Movement Trends
$stockMovementQuery = "SELECT 
                        DATE(sm.movement_date) as movement_day,
                        SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as stock_in,
                        SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as stock_out
                      FROM stock_movements sm
                      JOIN products p ON sm.product_id = p.product_id
                      WHERE sm.movement_date >= '$startDate'";

if ($categoryFilter > 0) {
    $stockMovementQuery .= " AND p.category_id = $categoryFilter";
}

$stockMovementQuery .= " GROUP BY DATE(sm.movement_date)
                        ORDER BY movement_day";

$stockMovementResult = $conn->query($stockMovementQuery);
$stockMovements = [];
$stockInTotal = 0;
$stockOutTotal = 0;

if ($stockMovementResult) {
    while ($row = $stockMovementResult->fetch_assoc()) {
        $stockMovements[] = $row;
        $stockInTotal += $row['stock_in'];
        $stockOutTotal += $row['stock_out'];
    }
}

// 5. Top selling products
$topSellingQuery = "SELECT 
                    p.product_id, 
                    p.name, 
                    p.sku, 
                    c.name as category_name,
                    p.stock_quantity,
                    p.minimum_stock,
                    SUM(sm.quantity) as total_sold
                FROM products p
                JOIN stock_movements sm ON p.product_id = sm.product_id
                LEFT JOIN categories c ON p.category_id = c.category_id
                WHERE sm.movement_type = 'out'
                AND sm.movement_date >= '$startDate'";

if ($categoryFilter > 0) {
    $topSellingQuery .= " AND p.category_id = $categoryFilter";
}

$topSellingQuery .= " GROUP BY p.product_id
                    ORDER BY total_sold DESC
                    LIMIT 10";

$topSellingResult = $conn->query($topSellingQuery);
$topSellingProducts = [];
if ($topSellingResult) {
    while ($row = $topSellingResult->fetch_assoc()) {
        $topSellingProducts[] = $row;
    }
}

// 6. Inventory by Category
$categoryStatsQuery = "SELECT 
                        c.name AS category_name,
                        COUNT(p.product_id) AS product_count,
                        SUM(p.stock_quantity) AS total_stock,
                        SUM(p.stock_quantity * p.cost_price) AS inventory_value
                      FROM categories c
                      LEFT JOIN products p ON c.category_id = p.category_id";

if ($categoryFilter > 0) {
    $categoryStatsQuery .= " WHERE c.category_id = $categoryFilter";
}

$categoryStatsQuery .= " GROUP BY c.category_id
                        ORDER BY inventory_value DESC";

$categoryStatsResult = $conn->query($categoryStatsQuery);
$categoryStats = [];
if ($categoryStatsResult) {
    while ($row = $categoryStatsResult->fetch_assoc()) {
        $categoryStats[] = $row;
    }
}

// Include header
include '../includes/header/header.php';
?>

<div class="stock-report-container">
    <div class="page-header">
        <h2><i class="fas fa-chart-bar"></i> Stock Reports</h2>
        <div class="page-actions">
            <a href="inventory.php" class="btn btn-primary">
                <i class="fas fa-boxes"></i> Back to Inventory
            </a>
            <button onclick="printReport()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Report Filters -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5>Report Filters</h5>
        </div>
        <div class="card-body">
            <form method="get" id="reportFilterForm" class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <label for="period">Time Period:</label>
                        <select id="period" name="period" class="form-control" onchange="this.form.submit()">
                            <option value="7days" <?php echo $timePeriod === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30days" <?php echo $timePeriod === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90days" <?php echo $timePeriod === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="year" <?php echo $timePeriod === 'year' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="all" <?php echo $timePeriod === 'all' ? 'selected' : ''; ?>>All Time</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label for="category">Category:</label>
                        <select id="category" name="category" class="form-control" onchange="this.form.submit()">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>" <?php echo $categoryFilter == $category['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo $category['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Summary -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-chart-pie"></i> Inventory Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="summary-card bg-light">
                        <span class="value">
                            <?php echo isset($inventorySummary['total_products']) ? $inventorySummary['total_products'] : 0; ?>
                        </span>
                        <span class="label">Total Products</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card bg-light">
                        <span class="value">
                            <?php echo isset($inventorySummary['total_quantity']) ? number_format($inventorySummary['total_quantity']) : 0; ?>
                        </span>
                        <span class="label">Units in Stock</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card bg-light">
                        <span class="value">
                            $<?php echo isset($inventorySummary['total_cost_value']) ? number_format($inventorySummary['total_cost_value'], 2) : '0.00'; ?>
                        </span>
                        <span class="label">Inventory Cost Value</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card bg-light">
                        <span class="value">
                            $<?php echo isset($inventorySummary['total_retail_value']) ? number_format($inventorySummary['total_retail_value'], 2) : '0.00'; ?>
                        </span>
                        <span class="label">Inventory Retail Value</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Stock Movement Trends -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5><i class="fas fa-exchange-alt"></i> Stock Movement Trends</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stockMovements)): ?>
                        <div class="chart-container" style="position: relative; height: 250px;">
                            <canvas id="stockMovementChart"></canvas>
                        </div>
                        <div class="stock-movement-summary">
                            <div class="stock-in">
                                <strong>Total Stock In:</strong> <?php echo number_format($stockInTotal); ?> units
                            </div>
                            <div class="stock-out">
                                <strong>Total Stock Out:</strong> <?php echo number_format($stockOutTotal); ?> units
                            </div>
                            <div class="net-flow <?php echo $stockInTotal - $stockOutTotal >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <strong>Net Flow:</strong> <?php echo number_format($stockInTotal - $stockOutTotal); ?> units
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No stock movement data available for the selected period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Inventory by Category -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5><i class="fas fa-tags"></i> Inventory by Category</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($categoryStats)): ?>
                        <div class="chart-container" style="position: relative; height: 250px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No category data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Low Stock Items -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($lowStockItems)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Current Stock</th>
                                        <th>Min. Stock</th>
                                        <th>Shortage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockItems as $item): ?>
                                        <tr>
                                            <td><?php echo $item['name']; ?></td>
                                            <td><?php echo $item['sku']; ?></td>
                                            <td><?php echo $item['stock_quantity']; ?></td>
                                            <td><?php echo $item['minimum_stock']; ?></td>
                                            <td class="text-danger font-weight-bold">
                                                <?php echo $item['minimum_stock'] - $item['stock_quantity']; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No low stock items found!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Selling Products -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5><i class="fas fa-fire"></i> Top Selling Products</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($topSellingProducts)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Units Sold</th>
                                        <th>Current Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topSellingProducts as $product): ?>
                                        <tr>
                                            <td><?php echo $product['name']; ?></td>
                                            <td><?php echo $product['category_name']; ?></td>
                                            <td class="font-weight-bold"><?php echo $product['total_sold']; ?></td>
                                            <td>
                                                <?php if ($product['stock_quantity'] < $product['minimum_stock']): ?>
                                                    <span class="text-danger font-weight-bold">
                                                        <?php echo $product['stock_quantity']; ?> (Low)
                                                    </span>
                                                <?php else: ?>
                                                    <?php echo $product['stock_quantity']; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No sales data available for the selected period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Category wise Inventory Details (visible only when All Categories are selected) -->
    <?php if ($categoryFilter == 0 && !empty($categoryStats)): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-folder"></i> Inventory by Category Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Number of Products</th>
                            <th>Total Stock Units</th>
                            <th>Inventory Value</th>
                            <th>% of Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalValue = 0;
                        foreach ($categoryStats as $stat) {
                            $totalValue += $stat['inventory_value']; 
                        }
                        
                        foreach ($categoryStats as $stat): 
                            $percentValue = $totalValue > 0 ? ($stat['inventory_value'] / $totalValue) * 100 : 0;
                        ?>
                            <tr>
                                <td><?php echo $stat['category_name']; ?></td>
                                <td><?php echo $stat['product_count']; ?></td>
                                <td><?php echo $stat['total_stock']; ?></td>
                                <td>$<?php echo number_format($stat['inventory_value'], 2); ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $percentValue; ?>%" 
                                             aria-valuenow="<?php echo $percentValue; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo number_format($percentValue, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="timestamp text-right mb-4">
        <small class="text-muted">
            Report Generated: <?php echo date('F d, Y h:i A'); ?>
        </small>
    </div>
</div>

<style>
    .stock-report-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .page-actions {
        display: flex;
        gap: 10px;
    }
    
    .summary-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 15px;
        border-radius: 5px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        height: 100%;
    }
    
    .summary-card .value {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .summary-card .label {
        font-size: 14px;
        text-align: center;
        color: #666;
    }
    
    .stock-movement-summary {
        display: flex;
        justify-content: space-around;
        margin-top: 15px;
        padding-top: 10px;
        border-top: 1px solid #eee;
    }
    
    .stock-in, .stock-out, .net-flow {
        text-align: center;
        padding: 5px 10px;
    }
    
    .text-success {
        color: #28a745;
    }
    
    .text-danger {
        color: #dc3545;
    }
    
    @media print {
        .btn, 
        .no-print {
            display: none !important;
        }
        
        .card {
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: #f8f9fa !important;
            color: #212529 !important;
            padding: 10px 15px;
        }
    }
    
    @media (max-width: 768px) {
        .row > div {
            margin-bottom: 20px;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Stock Movement Chart Data
    <?php if (!empty($stockMovements)): ?>
    const stockMovementData = {
        labels: [<?php 
            echo implode(', ', array_map(function($item) {
                return '"' . date('M d', strtotime($item['movement_day'])) . '"';
            }, $stockMovements));
        ?>],
        datasets: [
            {
                label: 'Stock In',
                backgroundColor: 'rgba(40, 167, 69, 0.2)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 2,
                data: [<?php 
                    echo implode(', ', array_map(function($item) {
                        return $item['stock_in'];
                    }, $stockMovements));
                ?>]
            },
            {
                label: 'Stock Out',
                backgroundColor: 'rgba(220, 53, 69, 0.2)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 2,
                data: [<?php 
                    echo implode(', ', array_map(function($item) {
                        return $item['stock_out'];
                    }, $stockMovements));
                ?>]
            }
        ]
    };
    
    const stockMovementChartConfig = {
        type: 'line',
        data: stockMovementData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    };
    
    new Chart(
        document.getElementById('stockMovementChart').getContext('2d'),
        stockMovementChartConfig
    );
    <?php endif; ?>
    
    // Category Chart Data
    <?php if (!empty($categoryStats)): ?>
    const categoryData = {
        labels: [<?php 
            echo implode(', ', array_map(function($item) {
                return '"' . $item['category_name'] . '"';
            }, $categoryStats));
        ?>],
        datasets: [
            {
                label: 'Inventory Value',
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(199, 199, 199, 0.7)',
                    'rgba(83, 102, 255, 0.7)',
                    'rgba(40, 159, 64, 0.7)',
                    'rgba(210, 199, 199, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(199, 199, 199, 1)',
                    'rgba(83, 102, 255, 1)',
                    'rgba(40, 159, 64, 1)',
                    'rgba(210, 199, 199, 1)'
                ],
                borderWidth: 1,
                data: [<?php 
                    echo implode(', ', array_map(function($item) {
                        return $item['inventory_value'];
                    }, $categoryStats));
                ?>]
            }
        ]
    };
    
    const categoryChartConfig = {
        type: 'pie',
        data: categoryData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: $${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    };
    
    new Chart(
        document.getElementById('categoryChart').getContext('2d'),
        categoryChartConfig
    );
    <?php endif; ?>
    
    // Print functionality
    function printReport() {
        window.print();
    }
</script>

<?php
// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>