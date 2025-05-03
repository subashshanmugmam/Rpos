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
</div> <!-- end of main container -->

<!-- Sales Predictions Section -->
<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-chart-area"></i> Sales Forecast Next Month</h5>
        </div>
        <div class="card-body">
            <canvas id="predictionChart" height="100"></canvas>
            <table class="table table-striped mt-3" id="predictionTable">
                <thead>
                    <tr><th>Date</th><th>Predicted Sales</th><th>Confidence</th><th>Top Category</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Custom Prediction Feature -->
<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-magic"></i> Predict Sales for Custom Period</h5>
        </div>
        <div class="card-body">
            <form id="customPredictForm" class="row g-3">
                <div class="col-md-4">
                    <label for="startDate" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                </div>
                <div class="col-md-4">
                    <label for="endDate" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="endDate" name="endDate" required>
                </div>
                <div class="col-md-4 align-self-end">
                    <button type="submit" class="btn btn-primary">Predict</button>
                </div>
            </form>
            <div class="mt-4">
                <canvas id="customPredictionChart" height="80"></canvas>
                <table class="table table-striped mt-3" id="customPredictionTable">
                    <thead>
                        <tr><th>Date</th><th>Predicted Sales</th><th>Confidence</th><th>Top Category</th></tr>
                    </thead>
                    <tbody id="customPredictionTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

// Fetch predictions and render chart + table
document.addEventListener('DOMContentLoaded', function() {
    fetch('get_predictions.php')
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                console.error('Error in predictions:', data.error);
                return;
            }
            
            const predictions = data.predictions || [];
            if (predictions.length === 0) {
                console.warn('No prediction data available');
                return;
            }
            
            const labels = predictions.map(p => p.date);
            const values = predictions.map(p => p.total_sales);
            
            // Calculate confidence intervals (±10% as a placeholder since our data doesn't have this)
            const confidenceMargin = 0.1;
            const lowerValues = values.map(v => v * (1 - confidenceMargin));
            const upperValues = values.map(v => v * (1 + confidenceMargin));
            
            // Render Chart
            const ctx = document.getElementById('predictionChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Predicted Sales',
                        data: values,
                        borderColor: '#e74a3b',
                        fill: false,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Sales (₹)' } }
                    }
                }
            });
            
            // Populate table
            const tbody = document.querySelector('#predictionTable tbody');
            if (!tbody) return;
            
            predictions.forEach(prediction => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${prediction.date} (${prediction.day})</td>
                    <td>₹${prediction.total_sales.toFixed(2)}</td>
                    <td>${prediction.confidence}%</td>
                    <td>${prediction.categories[0].category}</td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => console.error('Error fetching predictions:', err));

    // Custom Prediction Feature
    const customForm = document.getElementById('customPredictForm');
    if (customForm) {
        customForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            if (!start || !end) return;
            
            fetch(`get_custom_prediction.php?start=${start}&end=${end}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error in custom predictions:', data.error);
                        return;
                    }
                    
                    const predictions = data.predictions || [];
                    if (predictions.length === 0) {
                        console.warn('No custom prediction data available');
                        return;
                    }
                    
                    // Chart
                    const ctx = document.getElementById('customPredictionChart').getContext('2d');
                    if (window.customPredictionChartInstance) window.customPredictionChartInstance.destroy();
                    window.customPredictionChartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: predictions.map(p => p.date),
                            datasets: [{
                                label: 'Predicted Sales',
                                data: predictions.map(p => p.total_sales),
                                borderColor: '#36b9cc',
                                fill: false,
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: { beginAtZero: true, title: { display: true, text: 'Sales (₹)' } }
                            }
                        }
                    });
                    // Table
                    const table = document.getElementById('customPredictionTable');
                    const tbody = table.querySelector('tbody');
                    tbody.innerHTML = '';
                    data.forEach(r => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${r.ds}</td>
                            <td>₹${r.yhat.toFixed(2)}</td>
                            <td>₹${r.yhat_lower.toFixed(2)}</td>
                            <td>₹${r.yhat_upper.toFixed(2)}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    table.style.display = data.length ? '' : 'none';
                })
                .catch(err => {
                    alert('Error fetching custom prediction.');
                    console.error(err);
                });
        });
    }
});

</script>

<?php
mysqli_close($conn);
include '../includes/footer/footer.php';
?>