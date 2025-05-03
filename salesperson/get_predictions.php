<?php
session_start();

// Only allow salesperson
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Path to Python interpreter and script
$python = '/opt/lampp/htdocs/Rpos/venv/bin/python';  // Updated to use available Python
$script = '/opt/lampp/htdocs/Rpos/Retail-POS-system/ai_module/predict_json.py';

// Execute Python script
$cmd = escapeshellcmd("$python $script");
$output = shell_exec($cmd);

// Handle empty output
if (empty($output)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate predictions']);
    exit;
}

// Return JSON
header('Content-Type: application/json');
echo $output;
