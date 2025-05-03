<?php
// Test prediction script to verify it works
// To run this, use the web browser or CLI

// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up the path variables
$python = '/opt/lampp/htdocs/Rpos/venv/bin/python';  // Use the Python we found in the system
$script = '/opt/lampp/htdocs/Rpos/Retail-POS-system/ai_module/predict_json.py';

// Create debug info
$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'python_exists' => file_exists($python),
    'script_exists' => file_exists($script),
    'command' => "$python $script"
];

try {
    // Check if Python executable exists
    if (!file_exists($python)) {
        throw new Exception("Python executable not found at $python");
    }

    // Check if the script exists
    if (!file_exists($script)) {
        throw new Exception("Python script not found at $script");
    }

    // Execute the prediction script
    $cmd = escapeshellcmd("$python $script 2>&1"); // Capture both stdout and stderr
    $output = shell_exec($cmd);
    $debug['output_length'] = strlen($output);
    
    // Check if we got any output
    if (empty($output)) {
        throw new Exception("No output from prediction script. Command executed: $cmd");
    }

    // Try to decode the JSON to ensure it's valid
    $json_data = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $debug['json_error'] = json_last_error_msg();
        throw new Exception("Invalid JSON output: " . json_last_error_msg() . "\nRaw output: " . substr($output, 0, 500));
    }

    // Success - display the results
    header('Content-Type: application/json');
    echo $output;
    
} catch (Exception $e) {
    // Log error
    error_log("Prediction error: " . $e->getMessage());
    
    // Output debug info for troubleshooting
    $debug['error'] = $e->getMessage();
    $debug['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'debug' => $debug], JSON_PRETTY_PRINT);
}
?>
