<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'salesperson')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$python = '/opt/lampp/htdocs/Rpos/venv/bin/python';  // Updated to use available Python
$script = '/opt/lampp/htdocs/Rpos/Retail-POS-system/ai_module/predict_json.py';

$start = isset($_GET['start']) ? escapeshellarg($_GET['start']) : '';
$end = isset($_GET['end']) ? escapeshellarg($_GET['end']) : '';

$cmd = escapeshellcmd("$python $script");
if ($start && $end) {
    $cmd .= " --start $start --end $end";
}

$output = shell_exec($cmd);

// Handle empty output
if (empty($output)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate custom predictions']);
    exit;
}

header('Content-Type: application/json');
echo $output;
