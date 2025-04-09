<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a salesperson
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    header('Location: ../public/index.php');
    exit;
}

// Check if sale ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: pos.php');
    exit;
}

$saleId = (int)$_GET['id'];

// Get database connection
$conn = getConnection();

// Fetch sale information
$sale = null;
$query = "SELECT t.*, 
         c.first_name, c.last_name, c.email, c.phone, c.address,
         u.full_name as salesperson_name
         FROM sales_transactions t
         LEFT JOIN customers c ON t.customer_id = c.customer_id
         LEFT JOIN users u ON t.salesperson_id = u.user_id
         WHERE t.sale_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $saleId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $sale = $result->fetch_assoc();
} else {
    header('Location: pos.php');
    exit;
}
$stmt->close();

// Fetch sale items
$items = [];
$itemsQuery = "SELECT si.*, p.name, p.sku
             FROM sales_items si
             JOIN products p ON si.product_id = p.product_id
             WHERE si.sale_id = ?
             ORDER BY si.item_id";
$stmt = $conn->prepare($itemsQuery);
$stmt->bind_param("i", $saleId);
$stmt->execute();
$itemsResult = $stmt->get_result();
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}
$stmt->close();

// Get company information from settings
$company = [
    'name' => 'Retail POS System',
    'address' => '',
    'phone' => '',
    'email' => '',
    'website' => '',
    'tax_id' => ''
];

$settingsQuery = "SELECT * FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone', 'company_email', 'company_website', 'company_tax_id', 'currency')";
$settingsResult = mysqli_query($conn, $settingsQuery);
if ($settingsResult) {
    while ($row = mysqli_fetch_assoc($settingsResult)) {
        switch ($row['setting_key']) {
            case 'company_name':
                $company['name'] = $row['setting_value'];
                break;
            case 'company_address':
                $company['address'] = $row['setting_value'];
                break;
            case 'company_phone':
                $company['phone'] = $row['setting_value'];
                break;
            case 'company_email':
                $company['email'] = $row['setting_value'];
                break;
            case 'company_website':
                $company['website'] = $row['setting_value'];
                break;
            case 'company_tax_id':
                $company['tax_id'] = $row['setting_value'];
                break;
            case 'currency':
                $currency = $row['setting_value'];
                break;
        }
    }
    mysqli_free_result($settingsResult);
} else {
    $currency = '$';
}

// Include header
include '../includes/header/header.php';
?>

<div class="receipt-container">
    <div class="receipt-actions">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="pos.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to POS
        </a>
    </div>
    
    <div class="receipt" id="receipt">
        <div class="receipt-header">
            <h1><?php echo $company['name']; ?></h1>
            <?php if (!empty($company['address'])): ?>
                <p><?php echo $company['address']; ?></p>
            <?php endif; ?>
            <?php if (!empty($company['phone'])): ?>
                <p>Phone: <?php echo $company['phone']; ?></p>
            <?php endif; ?>
            <?php if (!empty($company['email'])): ?>
                <p>Email: <?php echo $company['email']; ?></p>
            <?php endif; ?>
            <?php if (!empty($company['tax_id'])): ?>
                <p>Tax ID: <?php echo $company['tax_id']; ?></p>
            <?php endif; ?>
        </div>
        
        <div class="receipt-info">
            <div class="row">
                <div class="col-6">
                    <strong>Invoice #:</strong> <?php echo $sale['invoice_number']; ?><br>
                    <strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($sale['sale_date'])); ?><br>
                    <strong>Salesperson:</strong> <?php echo $sale['salesperson_name']; ?><br>
                </div>
                <div class="col-6">
                    <?php if (!empty($sale['customer_id'])): ?>
                        <strong>Customer:</strong><br>
                        <?php echo $sale['first_name'] . ' ' . $sale['last_name']; ?><br>
                        <?php if (!empty($sale['phone'])): ?>
                            <?php echo $sale['phone']; ?><br>
                        <?php endif; ?>
                        <?php if (!empty($sale['email'])): ?>
                            <?php echo $sale['email']; ?><br>
                        <?php endif; ?>
                    <?php else: ?>
                        <strong>Customer:</strong> Walk-in Customer<br>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="receipt-items">
            <table>
                <thead>
                    <tr>
                        <th class="item-name">Description</th>
                        <th class="item-price">Unit Price</th>
                        <th class="item-qty">Qty</th>
                        <th class="item-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="item-name">
                                <?php echo $item['name']; ?>
                                <div class="item-sku">SKU: <?php echo $item['sku']; ?></div>
                            </td>
                            <td class="item-price"><?php echo $currency . number_format($item['unit_price'], 2); ?></td>
                            <td class="item-qty"><?php echo $item['quantity']; ?></td>
                            <td class="item-total"><?php echo $currency . number_format($item['total_price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="receipt-totals">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-value"><?php echo $currency . number_format($sale['subtotal'], 2); ?></div>
            </div>
            <?php if ($sale['discount_amount'] > 0): ?>
                <div class="total-row">
                    <div class="total-label">Discount:</div>
                    <div class="total-value">-<?php echo $currency . number_format($sale['discount_amount'], 2); ?></div>
                </div>
            <?php endif; ?>
            <div class="total-row">
                <div class="total-label">Tax:</div>
                <div class="total-value"><?php echo $currency . number_format($sale['tax_amount'], 2); ?></div>
            </div>
            <div class="total-row grand-total">
                <div class="total-label">Total:</div>
                <div class="total-value"><?php echo $currency . number_format($sale['total_amount'], 2); ?></div>
            </div>
            <div class="payment-method">
                <span>Payment Method: <?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?></span>
            </div>
        </div>
        
        <?php if (!empty($sale['notes'])): ?>
            <div class="receipt-notes">
                <p><strong>Notes:</strong> <?php echo nl2br($sale['notes']); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="receipt-footer">
            <p>Thank you for your business!</p>
            <?php if (!empty($company['website'])): ?>
                <p><?php echo $company['website']; ?></p>
            <?php endif; ?>
            <p>Printed on <?php echo date('M d, Y h:i A'); ?></p>
        </div>
    </div>
</div>

<style>
    /* Regular view styles */
    .receipt-container {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .receipt-actions {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }
    
    .receipt {
        background-color: #fff;
        border: 1px solid #ddd;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .receipt-header {
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #ddd;
    }
    
    .receipt-header h1 {
        margin: 0 0 10px;
        font-size: 1.8rem;
    }
    
    .receipt-header p {
        margin: 5px 0;
    }
    
    .receipt-info {
        margin-bottom: 20px;
    }
    
    .row {
        display: flex;
        flex-wrap: wrap;
    }
    
    .col-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .receipt-items table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    
    .receipt-items th, .receipt-items td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    
    .item-name {
        width: 50%;
    }
    
    .item-price, .item-qty {
        width: 15%;
        text-align: right;
    }
    
    .item-total {
        width: 20%;
        text-align: right;
    }
    
    .item-sku {
        font-size: 0.8rem;
        color: #666;
    }
    
    .receipt-totals {
        margin-top: 10px;
        border-top: 1px solid #ddd;
        padding-top: 10px;
    }
    
    .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }
    
    .grand-total {
        font-weight: bold;
        font-size: 1.2rem;
        padding-top: 5px;
        border-top: 1px solid #ddd;
        margin-top: 5px;
    }
    
    .payment-method {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #ddd;
        text-align: center;
        font-style: italic;
    }
    
    .receipt-notes {
        margin: 15px 0;
        padding: 10px;
        background-color: #f9f9f9;
        border-radius: 5px;
    }
    
    .receipt-footer {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
        text-align: center;
        font-size: 0.9rem;
    }
    
    /* Print styles */
    @media print {
        body * {
            visibility: hidden;
        }
        
        .receipt-actions {
            display: none;
        }
        
        #receipt, #receipt * {
            visibility: visible;
        }
        
        #receipt {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 0;
            margin: 0;
            border: none;
            box-shadow: none;
        }
        
        .container {
            width: 100%;
            max-width: 100%;
            padding: 0;
            margin: 0;
        }
    }
</style>

<?php
// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>