<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    header('Location: ../public/index.php');
    exit;
}

$conn = getConnection();

// Get sales data for charts
$salesData = [
    'daily' => getSalesData($conn, 'DAY'),
    'weekly' => getSalesData($conn, 'WEEK'),
    'monthly' => getSalesData($conn, 'MONTH')
];

$topProducts = getTopProducts($conn);
$performanceMetrics = getPerformanceMetrics($conn);

function getSalesData($conn, $interval) {
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(sale_date, '%Y-%m-%d') AS period,
            SUM(total_amount) AS total
        FROM sales_transactions
        WHERE salesperson_id = ?
        AND sale_date >= DATE_SUB(NOW(), INTERVAL 1 $interval)
        GROUP BY period
        ORDER BY period
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getTopProducts($conn) {
    $stmt = $conn->prepare("
        SELECT p.name, SUM(si.quantity) AS total_quantity
        FROM sales_items si
        JOIN products p ON si.product_id = p.product_id
        JOIN sales_transactions st ON si.sale_id = st.sale_id
        WHERE st.salesperson_id = ?
        GROUP BY p.product_id
        ORDER BY total_quantity DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getPerformanceMetrics($conn) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_sales,
            SUM(total_amount) AS total_revenue,
            AVG(total_amount) AS avg_sale,
            MAX(total_amount) AS best_sale
        FROM sales_transactions
        WHERE salesperson_id = ?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

include '../includes/header/header.php';
?>

<div class="container">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Sales Dashboard</h2>
    </div>

    <!-- Performance Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5>Total Sales</h5>
                    <h2><?= $performanceMetrics['total_sales'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5>Total Revenue</h5>
                    <h2>₹<?= number_format($performanceMetrics['total_revenue'], 2) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5>Average Sale</h5>
                    <h2>₹<?= number_format($performanceMetrics['avg_sale'], 2) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5>Best Sale</h5>
                    <h2>₹<?= number_format($performanceMetrics['best_sale'], 2) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Charts -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Sales Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Top Products</h5>
                </div>
                <div class="card-body">
                    <canvas id="productsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/chart.js"></script>
<script>
// Sales Trend Chart
new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($salesData['weekly'], 'period')) ?>,
        datasets: [{
            label: 'Weekly Sales',
            data: <?= json_encode(array_column($salesData['weekly'], 'total')) ?>,
            borderColor: '#007bff',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Amount (₹)' }
            }
        }
    }
});

// Top Products Chart
new Chart(document.getElementById('productsChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($topProducts, 'name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($topProducts, 'total_quantity')) ?>,
            backgroundColor: [
                '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php
mysqli_close($conn);
include '../includes/footer/footer.php';
?>