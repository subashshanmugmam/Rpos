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

// Initialize variables
$message = '';
$messageType = '';

// Handle form submission for adding product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $name = sanitizeInput($_POST['name'], $conn);
    $sku = sanitizeInput($_POST['sku'], $conn);
    $description = sanitizeInput($_POST['description'], $conn);
    $categoryId = (int)$_POST['category_id'];
    $supplierId = (int)$_POST['supplier_id'];
    $costPrice = (float)$_POST['cost_price'];
    $sellingPrice = (float)$_POST['selling_price'];
    $stockQuantity = (int)$_POST['stock_quantity'];
    $minimumStock = (int)$_POST['minimum_stock'];
    $status = sanitizeInput($_POST['status'], $conn);
    
    // Validate SKU uniqueness
    $skuCheck = "SELECT product_id FROM products WHERE sku = ? LIMIT 1";
    $stmt = $conn->prepare($skuCheck);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = "Error: SKU already exists. Please use a different SKU.";
        $messageType = "danger";
    } else {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert new product
            $insertQuery = "INSERT INTO products (name, sku, description, category_id, supplier_id, 
                                              cost_price, selling_price, stock_quantity, minimum_stock, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("sssiiddiis", $name, $sku, $description, $categoryId, $supplierId, 
                            $costPrice, $sellingPrice, $stockQuantity, $minimumStock, $status);
            
            if ($stmt->execute()) {
                $productId = $stmt->insert_id;
                $stmt->close();
                
                // If stock quantity > 0, record the initial stock movement
                if ($stockQuantity > 0) {
                    $movementType = "in";
                    $notes = "Initial stock on product creation";
                    
                    $movementQuery = "INSERT INTO stock_movements (product_id, quantity, movement_type, notes, performed_by) 
                                     VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($movementQuery);
                    $stmt->bind_param("iissi", $productId, $stockQuantity, $movementType, $notes, $_SESSION['user_id']);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Commit the transaction
                $conn->commit();
                
                $message = "Product added successfully!";
                $messageType = "success";
                
                // Optional: Clear form data on success
                $formReset = true;
            } else {
                throw new Exception("Failed to add product: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            // Roll back the transaction in case of error
            $conn->rollback();
            $message = "Error adding product: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Get all categories for dropdown
$categories = [];
$categoriesQuery = "SELECT category_id, name FROM categories WHERE status = 'active' ORDER BY name ASC";
$result = mysqli_query($conn, $categoriesQuery);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    mysqli_free_result($result);
}

// Get all suppliers for dropdown
$suppliers = [];
$suppliersQuery = "SELECT supplier_id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC";
$result = mysqli_query($conn, $suppliersQuery);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $suppliers[] = $row;
    }
    mysqli_free_result($result);
}

// Include header
include '../includes/header/header.php';
?>

<div class="product-form-container">
    <div class="page-header">
        <h2><i class="fas fa-box-open"></i> Add New Product</h2>
        <div class="page-actions">
            <a href="inventory.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3>Product Information</h3>
        </div>
        <div class="card-body">
            <form method="post" action="" id="productForm">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="name">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               value="<?php echo isset($_POST['name']) && !isset($formReset) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="sku">SKU <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sku" name="sku" required
                               value="<?php echo isset($_POST['sku']) && !isset($formReset) ? htmlspecialchars($_POST['sku']) : ''; ?>">
                        <small class="form-text text-muted">Unique Stock Keeping Unit identifier</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) && !isset($formReset) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="category_id">Category <span class="text-danger">*</span></label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>" 
                                    <?php echo (isset($_POST['category_id']) && !isset($formReset) && $_POST['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                        <select class="form-control" id="supplier_id" name="supplier_id" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['supplier_id']; ?>"
                                    <?php echo (isset($_POST['supplier_id']) && !isset($formReset) && $_POST['supplier_id'] == $supplier['supplier_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cost_price">Cost Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" required
                                   value="<?php echo isset($_POST['cost_price']) && !isset($formReset) ? htmlspecialchars($_POST['cost_price']) : '0.00'; ?>">
                        </div>
                        <small class="form-text text-muted">Price paid to supplier</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="selling_price">Selling Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" step="0.01" min="0" class="form-control" id="selling_price" name="selling_price" required
                                   value="<?php echo isset($_POST['selling_price']) && !isset($formReset) ? htmlspecialchars($_POST['selling_price']) : '0.00'; ?>">
                        </div>
                        <small class="form-text text-muted">Price to be charged to customers</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="stock_quantity">Initial Stock Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="stock_quantity" name="stock_quantity" required
                               value="<?php echo isset($_POST['stock_quantity']) && !isset($formReset) ? htmlspecialchars($_POST['stock_quantity']) : '0'; ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="minimum_stock">Minimum Stock Level <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="minimum_stock" name="minimum_stock" required
                               value="<?php echo isset($_POST['minimum_stock']) && !isset($formReset) ? htmlspecialchars($_POST['minimum_stock']) : '10'; ?>">
                        <small class="form-text text-muted">Threshold for low stock alert</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status <span class="text-danger">*</span></label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="active" <?php echo (!isset($_POST['status']) || (isset($_POST['status']) && $_POST['status'] === 'active')) ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Add Product
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .product-form-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
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
    
    .card-header h3 {
        margin: 0;
        font-size: 18px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -10px;
        margin-left: -10px;
        margin-bottom: 15px;
    }
    
    .form-group {
        margin-bottom: 1rem;
        padding-right: 10px;
        padding-left: 10px;
    }
    
    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .form-control {
        display: block;
        width: 100%;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    textarea.form-control {
        height: auto;
    }
    
    .form-actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
    }
    
    .btn {
        display: inline-block;
        font-weight: 400;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        user-select: none;
        border: 1px solid transparent;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        border-radius: 0.25rem;
        transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .btn-success {
        color: #fff;
        background-color: #28a745;
        border-color: #28a745;
    }
    
    .btn-secondary {
        color: #fff;
        background-color: #6c757d;
        border-color: #6c757d;
    }
    
    .alert {
        position: relative;
        padding: 0.75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: 0.25rem;
    }
    
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }
    
    .text-danger {
        color: #dc3545;
    }
    
    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 80%;
        color: #6c757d;
    }
    
    .input-group {
        position: relative;
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        width: 100%;
    }
    
    .input-group-prepend {
        display: flex;
        margin-right: -1px;
    }
    
    .input-group-text {
        display: flex;
        align-items: center;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #495057;
        text-align: center;
        white-space: nowrap;
        background-color: #e9ecef;
        border: 1px solid #ced4da;
        border-radius: 0.25rem 0 0 0.25rem;
    }
    
    @media (max-width: 767.98px) {
        .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>

<script>
    // Calculate selling price based on cost price with a default markup
    document.getElementById('cost_price').addEventListener('input', function() {
        const costPrice = parseFloat(this.value) || 0;
        const markup = 1.4; // 40% markup
        const suggestedPrice = (costPrice * markup).toFixed(2);
        
        // Only suggest if selling price is empty or 0
        const sellingPriceInput = document.getElementById('selling_price');
        if (!sellingPriceInput.value || parseFloat(sellingPriceInput.value) === 0) {
            sellingPriceInput.value = suggestedPrice;
        }
    });
    
    // SKU generator
    document.getElementById('name').addEventListener('blur', function() {
        const skuInput = document.getElementById('sku');
        // Only suggest if SKU is empty
        if (!skuInput.value) {
            // Generate SKU from product name (first 3 chars uppercase) + random number
            const namePrefix = this.value.replace(/[^a-zA-Z0-9]/g, '').substring(0, 3).toUpperCase();
            const randomNum = Math.floor(1000 + Math.random() * 9000); // 4-digit random number
            skuInput.value = namePrefix + '-' + randomNum;
        }
    });
    
    // Form validation
    document.getElementById('productForm').addEventListener('submit', function(e) {
        const costPrice = parseFloat(document.getElementById('cost_price').value);
        const sellingPrice = parseFloat(document.getElementById('selling_price').value);
        
        // Validate selling price >= cost price
        if (sellingPrice < costPrice) {
            e.preventDefault();
            alert('Selling price cannot be less than cost price!');
        }
    });
</script>

<?php
// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>