<?php
/**
 * Database Connection File
 * 
 * This file establishes a connection to the MySQL database and provides
 * helper functions for database operations throughout the application.
 */

/**
 * Get database connection
 * 
 * @return mysqli A database connection
 */
function getConnection() {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'retail_pos';
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

/**
 * Sanitize user input to prevent SQL injection
 * 
 * @param string $data The input data to sanitize
 * @param mysqli $conn Database connection
 * @return string The sanitized data
 */
function sanitizeInput($data, $conn) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = $conn->real_escape_string($data);
    return $data;
}

/**
 * Generate a unique invoice number
 * 
 * @param mysqli $conn Database connection (for checking existing invoice numbers)
 * @param string $prefix Prefix for the invoice number (default: 'INV')
 * @return string A unique invoice number
 */
function generateInvoiceNumber($conn, $prefix = 'INV') {
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    $invoiceNumber = $prefix . '-' . date('Ymd', $timestamp) . '-' . $random;
    
    // Check if the invoice number already exists
    $sql = "SELECT invoice_number FROM sales_transactions WHERE invoice_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $invoiceNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If invoice number already exists, generate a new one
    if ($result->num_rows > 0) {
        $stmt->close();
        return generateInvoiceNumber($conn, $prefix); // Recursively generate a new one
    }
    
    $stmt->close();
    return $invoiceNumber;
}

/**
 * Format currency value
 * 
 * @param float $amount The amount to format
 * @param string $currencySymbol Currency symbol (default: '$')
 * @return string Formatted currency value
 */
function formatCurrency($amount, $currencySymbol = '$') {
    return $currencySymbol . number_format($amount, 2);
}

/**
 * Calculate tax amount based on subtotal and tax rate
 * 
 * @param float $subtotal The subtotal amount
 * @param float $taxRate Tax rate percentage
 * @return float The tax amount
 */
function calculateTax($subtotal, $taxRate) {
    return ($subtotal * $taxRate) / 100;
}

/**
 * Log database errors to file
 * 
 * @param string $message Error message
 * @param mysqli $conn Database connection
 */
function logDatabaseError($message, $conn) {
    $errorTime = date('Y-m-d H:i:s');
    $errorMessage = "[$errorTime] $message. MySQL Error: " . $conn->error . "\n";
    
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/dbms_project/logs/db_errors.log';
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    error_log($errorMessage, 3, $logFile);
}

/**
 * Check if a table exists in the database
 * 
 * @param string $tableName The table name to check
 * @param mysqli $conn Database connection
 * @return bool True if the table exists, false otherwise
 */
function tableExists($tableName, $conn) {
    $tableName = sanitizeInput($tableName, $conn);
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}
?>