<?php
// Load WordPress
require_once '../../../wp-load.php';

// Test debug function
if (function_exists('stockcartl_debug')) {
    $debug = stockcartl_debug();
    
    // Try logging something
    $debug->log_info('Test debug entry', array('test' => 'This is a test'));
    $debug->log_warning('Test warning entry', array('test' => 'This is a warning test'));
    $debug->log_error('Test error entry', array('test' => 'This is an error test'));
    $debug->log_critical('Test critical entry', array('test' => 'This is a critical test'));
    
    // Get log content
    $log_content = $debug->get_log_content();
    
    echo '<h1>Debug Test</h1>';
    echo '<h2>Log Content:</h2>';
    echo '<pre>' . esc_html($log_content) . '</pre>';
    
    echo '<h2>Log Directory:</h2>';
    echo '<p>' . esc_html($debug->log_dir) . '</p>';
    
    echo '<h2>Is Writable:</h2>';
    echo '<p>' . (is_writable($debug->log_dir) ? 'Yes' : 'No') . '</p>';
} else {
    echo '<h1>Debug function not available</h1>';
}