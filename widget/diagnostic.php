<?php
/**
 * LiveSupport Widget Diagnostic Tool
 * This script checks your database and configuration to ensure the widget works correctly
 */

// Set error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Function to check if a table exists
function tableExists($tableName) {
    global $db;
    try {
        $db->fetch("SELECT 1 FROM $tableName LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if a column exists in a table
function columnExists($tableName, $columnName) {
    global $db;
    try {
        $db->fetch("SELECT $columnName FROM $tableName LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if a directory is writable
function isDirectoryWritable($dir) {
    return file_exists($dir) && is_dir($dir) && is_writable($dir);
}

// Function to format result
function formatResult($test, $result, $details = '') {
    $status = $result ? 'PASS' : 'FAIL';
    $color = $result ? 'green' : 'red';
    return "<tr>
                <td>{$test}</td>
                <td style='color: {$color}; font-weight: bold;'>{$status}</td>
                <td>{$details}</td>
            </tr>";
}

// Start output
echo "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>LiveSupport Widget Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #4a6cf7;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .section {
            margin-bottom: 30px;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .code {
            font-family: monospace;
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 3px;
            white-space: pre-wrap;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin-bottom: 20px;
        }
        .error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 10px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>LiveSupport Widget Diagnostic Tool</h1>
    <p>This tool checks your LiveSupport installation to ensure the widget works correctly.</p>
";

// Environment Check
echo "<div class='section'>
    <h2>Environment Check</h2>
    <table>
        <tr>
            <th>Test</th>
            <th>Status</th>
            <th>Details</th>
        </tr>";

// PHP Version Check
$phpVersion = phpversion();
$phpVersionCheck = version_compare($phpVersion, '7.0.0', '>=');
echo formatResult(
    'PHP Version', 
    $phpVersionCheck, 
    "Current: {$phpVersion}, Required: 7.0.0 or higher"
);

// CORS Headers Check
$corsHeadersCheck = false;
$corsDetails = '';
if (function_exists('apache_get_modules') && in_array('mod_headers', apache_get_modules())) {
    $corsHeadersCheck = true;
    $corsDetails = 'mod_headers is available, which is required for CORS headers';
} else {
    $corsHeadersCheck = false;
    $corsDetails = 'mod_headers not detected. This may affect the widget\'s ability to communicate cross-domain';
}
echo formatResult('CORS Headers Support', $corsHeadersCheck, $corsDetails);

// File Paths Check
$embedJsExists = file_exists(__DIR__ . '/embed.js');
$apiPhpExists = file_exists(__DIR__ . '/api.php');
$cssExists = file_exists('../assets/css/chat-widget.css');

echo formatResult('Embed JS File', $embedJsExists, 'File: ' . __DIR__ . '/embed.js');
echo formatResult('API PHP File', $apiPhpExists, 'File: ' . __DIR__ . '/api.php');
echo formatResult('CSS File', $cssExists, 'File: ' . __DIR__ . '/../assets/css/chat-widget.css');

// Check if logs directory exists and is writable
$logsDir = __DIR__ . '/../logs';
$logsDirExists = isDirectoryWritable($logsDir);
if (!$logsDirExists) {
    // Try to create it
    @mkdir($logsDir, 0755, true);
    $logsDirExists = isDirectoryWritable($logsDir);
}
echo formatResult('Logs Directory', $logsDirExists, 'Directory: ' . $logsDir);

echo "</table></div>";

// Database Tables Check
echo "<div class='section'>
    <h2>Database Tables Check</h2>
    <table>
        <tr>
            <th>Table</th>
            <th>Status</th>
            <th>Details</th>
        </tr>";

// Check required tables
$tablesRequired = [
    'users' => ['widget_id', 'subscription_id', 'subscription_expiry'],
    'visitors' => ['id', 'user_id', 'ip_address', 'user_agent', 'url', 'last_active'],
    'messages' => ['id', 'user_id', 'visitor_id', 'message', 'sender_type', 'read'],
    'widget_settings' => ['user_id', 'theme', 'position', 'primary_color', 'greeting_message']
];

foreach ($tablesRequired as $table => $columns) {
    $tableExistsCheck = tableExists($table);
    
    if ($tableExistsCheck) {
        $columnChecks = [];
        foreach ($columns as $column) {
            $columnCheck = columnExists($table, $column);
            $columnChecks[] = "$column: " . ($columnCheck ? 'Yes' : 'No');
            
            if (!$columnCheck && $table === 'messages' && $column === 'read') {
                // Special check for messages.read field which may need backticks
                $columnCheck = columnExists($table, '`read`');
                if ($columnCheck) {
                    $columnChecks[count($columnChecks) - 1] = "$column: Yes (using backticks)";
                }
            }
        }
        $details = 'Required columns: ' . implode(', ', $columnChecks);
    } else {
        $details = 'Table does not exist';
    }
    
    echo formatResult("Table: $table", $tableExistsCheck, $details);
}

echo "</table></div>";

// Widget Settings and Data
echo "<div class='section'>
    <h2>Widget Data Check</h2>
    <table>
        <tr>
            <th>Test</th>
            <th>Status</th>
            <th>Details</th>
        </tr>";

// Check for users with widget_id
try {
    $usersWithWidgetId = $db->fetch("SELECT COUNT(*) as count FROM users WHERE widget_id IS NOT NULL AND widget_id != ''");
    $usersWithWidgetIdCount = $usersWithWidgetId['count'] ?? 0;
    echo formatResult(
        'Users with Widget ID', 
        $usersWithWidgetIdCount > 0, 
        "Found $usersWithWidgetIdCount users with widget ID"
    );
    
    // Get a sample widget ID
    $sampleUser = $db->fetch("SELECT id, name, widget_id FROM users WHERE widget_id IS NOT NULL AND widget_id != '' LIMIT 1");
    if ($sampleUser) {
        echo formatResult(
            'Sample Widget ID', 
            true, 
            "User: {$sampleUser['name']}, Widget ID: {$sampleUser['widget_id']}"
        );
    }
} catch (Exception $e) {
    echo formatResult('Users with Widget ID', false, 'Error: ' . $e->getMessage());
}

// Check recent messages
try {
    $recentMessages = $db->fetchAll("SELECT * FROM messages ORDER BY created_at DESC LIMIT 5");
    $hasRecentMessages = !empty($recentMessages);
    
    if ($hasRecentMessages) {
        $messagesDetails = [];
        foreach ($recentMessages as $msg) {
            $time = date('Y-m-d H:i:s', strtotime($msg['created_at']));
            $type = $msg['sender_type'];
            $msgStart = substr($msg['message'], 0, 30) . (strlen($msg['message']) > 30 ? '...' : '');
            $messagesDetails[] = "[{$time}] {$type}: {$msgStart}";
        }
        $details = "Recent messages:<br>" . implode('<br>', $messagesDetails);
    } else {
        $details = "No recent messages found";
    }
    
    echo formatResult('Recent Messages', $hasRecentMessages, $details);
} catch (Exception $e) {
    echo formatResult('Recent Messages', false, 'Error: ' . $e->getMessage());
}

// Check for visitors
try {
    $visitorCount = $db->fetch("SELECT COUNT(*) as count FROM visitors");
    $hasVisitors = ($visitorCount['count'] ?? 0) > 0;
    
    echo formatResult(
        'Visitors', 
        $hasVisitors, 
        "Found " . ($visitorCount['count'] ?? 0) . " visitors in database"
    );
} catch (Exception $e) {
    echo formatResult('Visitors', false, 'Error: ' . $e->getMessage());
}

echo "</table></div>";

// Test Widget Embed Code
if (isset($sampleUser) && $sampleUser) {
    $widgetId = $sampleUser['widget_id'];
    $siteUrl = rtrim(SITE_URL, '/');
    
    echo "<div class='section'>
        <h2>Widget Embed Code</h2>
        <p>Use this code to embed the widget on client websites:</p>
        <div class='code'>&lt;script&gt;
var WIDGET_ID = \"{$widgetId}\";
&lt;/script&gt;
&lt;script src=\"{$siteUrl}/widget/embed.js\" async&gt;&lt;/script&gt;</div>

        <div class='warning'>
            <strong>Important:</strong> The code above uses your live widget ID. Make sure to test it on a separate website.
        </div>
    </div>";
}

// Test URLs
echo "<div class='section'>
    <h2>API URL Tests</h2>
    <p>Testing API endpoint URLs for accessibility and CORS configuration:</p>
    <table>
        <tr>
            <th>Endpoint</th>
            <th>Status</th>
            <th>Details</th>
        </tr>";

$apiUrls = [
    'Widget JS' => rtrim(SITE_URL, '/') . '/widget/embed.js',
    'Widget CSS' => rtrim(SITE_URL, '/') . '/assets/css/chat-widget.css',
    'API Endpoint' => rtrim(SITE_URL, '/') . '/widget/api.php?action=get_config&widget_id=test'
];

foreach ($apiUrls as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $success = $httpCode >= 200 && $httpCode < 400;
    $details = "HTTP Code: $httpCode";
    
    if (!empty($error)) {
        $details .= ", Error: $error";
    }
    
    if ($success && $name === 'API Endpoint') {
        // Check for CORS headers in API response
        $hasAccessControlHeader = strpos($response, 'Access-Control-Allow-Origin') !== false;
        $details .= ", CORS Headers: " . ($hasAccessControlHeader ? 'Yes' : 'No');
    }
    
    echo formatResult($name, $success, $details . " <a href='$url' target='_blank'>Open URL</a>");
}

echo "</table></div>";

// Check Database Connection
echo "<div class='section'>
    <h2>Database Connection Check</h2>";

try {
    // Test database connection with a simple query
    $testQuery = $db->fetch("SELECT 1 as test");
    $dbConnected = isset($testQuery['test']) && $testQuery['test'] == 1;
    
    if ($dbConnected) {
        echo "<div class='success'><strong>Database Connection:</strong> Success! Database connection is working properly.</div>";
    } else {
        echo "<div class='error'><strong>Database Connection:</strong> Failed to connect to database or execute test query.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'><strong>Database Connection Error:</strong> " . $e->getMessage() . "</div>";
}

echo "</div>";

// Overall Status and Recommendations
echo "<div class='section'>
    <h2>Summary and Recommendations</h2>";

// Count issues
$issues = [];

if (!$embedJsExists) $issues[] = "Embed JS file is missing. Create it at " . __DIR__ . "/embed.js";
if (!$apiPhpExists) $issues[] = "API PHP file is missing. Create it at " . __DIR__ . "/api.php";
if (!$cssExists) $issues[] = "CSS file is missing. Create it at ../assets/css/chat-widget.css";
if (!$logsDirExists) $issues[] = "Logs directory is not writable. Create it at " . $logsDir;

foreach ($tablesRequired as $table => $columns) {
    if (!tableExists($table)) {
        $issues[] = "Table '$table' does not exist in the database.";
    } else {
        foreach ($columns as $column) {
            $columnExists = columnExists($table, $column);
            if (!$columnExists && $table === 'messages' && $column === 'read') {
                // Special check for 'read' column which might need backticks
                $columnExists = columnExists($table, '`read`');
            }
            
            if (!$columnExists) {
                $issues[] = "Column '$column' is missing from table '$table'.";
            }
        }
    }
}

if (count($issues) > 0) {
    echo "<div class='error'>
        <strong>Issues Found:</strong>
        <ul>";
    
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    
    echo "</ul>
    </div>";
    
    echo "<p>Please fix the issues above to ensure the widget works correctly.</p>";
} else {
    echo "<div class='success'>
        <strong>All checks passed!</strong> Your LiveSupport widget installation appears to be correctly configured.
    </div>";
}

// Next Steps
echo "<h3>Next Steps:</h3>
<ol>
    <li>Test the widget embed code on a separate website to ensure it loads correctly.</li>
    <li>Check the logs directory for any error messages if the widget isn't working.</li>
    <li>Make sure your web server has CORS headers enabled for cross-domain communication.</li>
    <li>If you're still having issues, try using the improved API file with logging enabled.</li>
</ol>";

echo "</div>";

// End output
echo "</body></html>";
?>