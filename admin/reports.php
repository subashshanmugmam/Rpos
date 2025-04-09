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
}

// Include header
include '../includes/header/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-chart-bar"></i> Reports</h2>
</div>

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
</style>

<?php
// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>