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

// Fetch sales reports
$reportType = isset($_GET['report']) ? $_GET['report'] : 'sales';
$reportData = [];

// Function to read CSV files
function readCSV($filePath) {
    $data = [];
    if (file_exists($filePath)) {
        $file = fopen($filePath, 'r');
        $headers = fgetcsv($file);
        while (($row = fgetcsv($file)) !== false) {
            $data[] = array_combine($headers, $row);
        }
        fclose($file);
    }
    return $data;
}

if ($reportType === 'sales') {
    $query = "SELECT t.sale_id, t.invoice_number, t.total_amount, t.sale_date, c.first_name, c.last_name, u.full_name as salesperson
              FROM sales_transactions t
              LEFT JOIN customers c ON t.customer_id = c.customer_id
              LEFT JOIN users u ON t.salesperson_id = u.user_id
              ORDER BY t.sale_date DESC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $reportData[] = $row;
        }
        mysqli_free_result($result);
    }
} elseif ($reportType === 'predictions') {
    // Get AI prediction data from API
    $predictionsEndpoint = '../salesperson/get_predictions.php';
    $jsonResponse = file_get_contents($predictionsEndpoint);
    $predictionsData = json_decode($jsonResponse, true);
    
    $dailySalesData = [];
    $categorySalesData = [];
    
    if ($predictionsData && isset($predictionsData['predictions'])) {
        foreach ($predictionsData['predictions'] as $prediction) {
            $dailySalesData[] = [
                'Date' => $prediction['date'],
                'Day' => $prediction['day'],
                'Predicted' => $prediction['total_sales'],
                'Confidence' => $prediction['confidence']
            ];
            
            foreach ($prediction['categories'] as $category) {
                $categorySalesData[] = [
                    'Date' => $prediction['date'],
                    'Day' => $prediction['day'],
                    'Category' => $category['category'],
                    'Sales' => $category['sales'],
                    'Percentage' => $category['percentage']
                ];
            }
        }
    }

    // Get actual daily sales from DB for the same dates as predictions
    $predictionDates = array_column($dailySalesData, 'Date');
    $actualSales = [];
    if (!empty($predictionDates)) {
        // Convert the dates to a format suitable for SQL IN clause
        $dateList = array_map(function($d) use ($conn) { return "'" . $conn->real_escape_string($d) . "'"; }, $predictionDates);
        $dateStr = implode(',', $dateList);
        
        // Query to get actual sales data
        $query = "SELECT DATE(sale_date) as sale_date, SUM(total_amount) as total_sales FROM sales_transactions WHERE DATE(sale_date) IN ($dateStr) GROUP BY DATE(sale_date)";
        $result = mysqli_query($conn, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $actualSales[$row['sale_date']] = $row['total_sales'];
            }
            mysqli_free_result($result);
        }
    }
}

// Include header
include '../includes/header/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-chart-bar"></i> Reports</h2>
</div>

<div class="report-tabs">
    <a href="?report=sales" class="tab <?php echo $reportType === 'sales' ? 'active' : ''; ?>">Sales Report</a>
    <a href="?report=predictions" class="tab <?php echo $reportType === 'predictions' ? 'active' : ''; ?>">AI Predictions</a>
</div>

<?php if ($reportType === 'sales'): ?>
<div class="card">
    <div class="card-header">
        <h3>Sales Report</h3>
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Salesperson</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($reportData) > 0): ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?php echo $row['invoice_number']; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($row['sale_date'])); ?></td>
                            <td><?php echo $row['first_name'] ? $row['first_name'] . ' ' . $row['last_name'] : 'Walk-in Customer'; ?></td>
                            <td><?php echo $row['salesperson']; ?></td>
                            <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No sales data available.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'predictions'): ?>
<div class="card">
    <div class="card-header">
        <h3>Daily Sales Predictions</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($dailySalesData)): ?>
            <div class="prediction-summary">
                <p>The AI model has analyzed your sales data and made the following predictions:</p>
            </div>
            <canvas id="salesPredictionChart" height="80"></canvas>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var ctx = document.getElementById('salesPredictionChart').getContext('2d');
                    var labels = <?php echo json_encode(array_column($dailySalesData, 'Date')); ?>;
                    var predicted = <?php echo json_encode(array_map(function($row) { return floatval($row['Predicted_Sales']); }, $dailySalesData)); ?>;
                    var actual = <?php
                        $actualArr = [];
                        foreach ($dailySalesData as $row) {
                            $date = $row['Date'];
                            $actualArr[] = isset($actualSales[$date]) ? floatval($actualSales[$date]) : null;
                        }
                        echo json_encode($actualArr);
                    ?>;
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Predicted Sales',
                                    data: predicted,
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                    fill: false,
                                    tension: 0.2
                                },
                                {
                                    label: 'Actual Sales',
                                    data: actual,
                                    borderColor: 'rgba(255, 99, 132, 1)',
                                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                    fill: false,
                                    tension: 0.2
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'top' },
                                title: { display: true, text: 'Actual vs Predicted Daily Sales' }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                });
            </script>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Predicted Sales</th>
                        <th>Actual Sales</th>
                        <th>Confidence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailySalesData as $i => $row): ?>
                        <tr>
                            <td><?php echo $row['Date']; ?></td>
                            <td>$<?php echo number_format(floatval($row['Predicted_Sales']), 2); ?></td>
                            <td>
                                <?php 
                                $date = $row['Date'];
                                echo isset($actualSales[$date]) ? '$' . number_format(floatval($actualSales[$date]), 2) : '<span style="color:gray">N/A</span>';
                                ?>
                            </td>
                            <td><?php echo isset($row['Confidence']) ? $row['Confidence'] . '%' : 'N/A'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <p>No daily sales prediction data is available. Please run the AI module to generate predictions.</p>
                <p><code>cd /opt/lampp/htdocs/Rpos/Retail-POS-system && source ai_module_env/bin/activate && cd ai_module && python main.py --now</code></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Category Sales Predictions</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($categorySalesData)): ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Predicted Sales</th>
                        <th>Growth Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorySalesData as $row): ?>
                        <tr>
                            <td><?php echo $row['Category']; ?></td>
                            <td>$<?php echo number_format(floatval($row['Predicted_Sales']), 2); ?></td>
                            <td>
                                <?php 
                                if (isset($row['Growth_Rate'])) {
                                    $growth = floatval($row['Growth_Rate']);
                                    $icon = $growth > 0 ? '↑' : ($growth < 0 ? '↓' : '→');
                                    $color = $growth > 0 ? 'green' : ($growth < 0 ? 'red' : 'orange');
                                    echo "<span style='color: $color'>$icon " . abs($growth) . "%</span>";
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No category sales prediction data is available.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<style>
    .page-header {
        margin-bottom: 20px;
    }
    .card {
        background-color: #fff;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }
    .card-header {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
    }
    .card-body {
        padding: 20px;
    }
    .table {
        width: 100%;
        margin-bottom: 1rem;
        color: #212529;
        border-collapse: collapse;
    }
    .table th,
    .table td {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
    }
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0, 0, 0, 0.05);
    }
    .report-tabs {
        display: flex;
        margin-bottom: 20px;
    }
    .tab {
        padding: 10px 20px;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px 5px 0 0;
        margin-right: 5px;
        text-decoration: none;
        color: #495057;
    }
    .tab.active {
        background-color: #fff;
        border-bottom-color: #fff;
        font-weight: bold;
    }
    .prediction-summary {
        margin-bottom: 20px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 5px;
    }
    .alert {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .alert-info {
        background-color: #d1ecf1;
        color: #0c5460;
    }
</style>

<?php
// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>