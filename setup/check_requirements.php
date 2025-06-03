<?php
/**
 * System requirements and setup checker for LiveSupport
 * Run this script to verify all requirements are met for file upload functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to check directory and create if needed
function checkDirectory($path, $create = true) {
    $fullPath = __DIR__ . '/../' . $path;
    $exists = is_dir($fullPath);
    $writable = $exists && is_writable($fullPath);
    
    if (!$exists && $create) {
        $created = mkdir($fullPath, 0755, true);
        if ($created) {
            $exists = true;
            $writable = is_writable($fullPath);
        }
    }
    
    return [
        'path' => $path,
        'exists' => $exists,
        'writable' => $writable,
        'full_path' => $fullPath
    ];
}

// Function to check database table and columns
function checkDatabase() {
    try {
        require_once '../includes/config.php';
        require_once '../includes/db.php';
        
        // Check if messages table has file columns
        $columns = $db->fetchAll("SHOW COLUMNS FROM messages");
        $columnNames = array_column($columns, 'Field');
        
        $requiredColumns = ['file_path', 'file_name', 'file_size', 'file_type'];
        $missingColumns = array_diff($requiredColumns, $columnNames);
        
        // Check subscription table for allow_file_upload
        $subColumns = $db->fetchAll("SHOW COLUMNS FROM subscriptions");
        $subColumnNames = array_column($subColumns, 'Field');
        
        $hasFileUploadColumn = in_array('allow_file_upload', $subColumnNames);
        
        return [
            'connection' => true,
            'messages_table_ok' => empty($missingColumns),
            'missing_columns' => $missingColumns,
            'subscriptions_file_column' => $hasFileUploadColumn,
            'error' => null
        ];
    } catch (Exception $e) {
        return [
            'connection' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Function to check PHP configuration
function checkPHPConfig() {
    $uploadMaxFilesize = ini_get('upload_max_filesize');
    $postMaxSize = ini_get('post_max_size');
    $maxFileUploads = ini_get('max_file_uploads');
    $maxExecutionTime = ini_get('max_execution_time');
    $memoryLimit = ini_get('memory_limit');
    
    return [
        'upload_max_filesize' => $uploadMaxFilesize,
        'post_max_size' => $postMaxSize,
        'max_file_uploads' => $maxFileUploads,
        'max_execution_time' => $maxExecutionTime,
        'memory_limit' => $memoryLimit,
        'file_uploads_enabled' => ini_get('file_uploads') ? true : false
    ];
}

// Start output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiveSupport Setup Checker</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .section h3 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>LiveSupport Setup Checker</h1>
    <p>This script checks if your system is properly configured for the LiveSupport file upload functionality.</p>

    <?php
    // Check directories
    echo "<div class='section'>";
    echo "<h3>Directory Structure</h3>";
    
    $directories = [
        'uploads',
        'uploads/messages',
        'uploads/payments',
        'logs'
    ];
    
    echo "<table>";
    echo "<tr><th>Directory</th><th>Exists</th><th>Writable</th><th>Status</th></tr>";
    
    $directoriesOk = true;
    foreach ($directories as $dir) {
        $check = checkDirectory($dir);
        $status = 'status-ok';
        $statusText = 'OK';
        
        if (!$check['exists']) {
            $status = 'status-error';
            $statusText = 'Missing';
            $directoriesOk = false;
        } elseif (!$check['writable']) {
            $status = 'status-warning';
            $statusText = 'Not Writable';
            $directoriesOk = false;
        }
        
        echo "<tr>";
        echo "<td>{$check['path']}</td>";
        echo "<td>" . ($check['exists'] ? '✓' : '✗') . "</td>";
        echo "<td>" . ($check['writable'] ? '✓' : '✗') . "</td>";
        echo "<td class='$status'>$statusText</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (!$directoriesOk) {
        echo "<p class='status-error'><strong>Action Required:</strong> Create missing directories and set proper permissions (755).</p>";
    } else {
        echo "<p class='status-ok'><strong>✓ All directories are properly configured.</strong></p>";
    }
    echo "</div>";

    // Check database
    echo "<div class='section'>";
    echo "<h3>Database Configuration</h3>";
    
    $dbCheck = checkDatabase();
    if (!$dbCheck['connection']) {
        echo "<p class='status-error'><strong>Database Connection Failed:</strong> {$dbCheck['error']}</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Check</th><th>Status</th><th>Details</th></tr>";
        
        // Messages table check
        if ($dbCheck['messages_table_ok']) {
            echo "<tr><td>Messages Table</td><td class='status-ok'>✓ OK</td><td>All file columns present</td></tr>";
        } else {
            echo "<tr><td>Messages Table</td><td class='status-error'>✗ Missing Columns</td><td>Missing: " . implode(', ', $dbCheck['missing_columns']) . "</td></tr>";
        }
        
        // Subscriptions table check
        if ($dbCheck['subscriptions_file_column']) {
            echo "<tr><td>Subscriptions Table</td><td class='status-ok'>✓ OK</td><td>allow_file_upload column present</td></tr>";
        } else {
            echo "<tr><td>Subscriptions Table</td><td class='status-error'>✗ Missing Column</td><td>allow_file_upload column missing</td></tr>";
        }
        
        echo "</table>";
        
        if (!$dbCheck['messages_table_ok'] || !$dbCheck['subscriptions_file_column']) {
            echo "<p class='status-error'><strong>Action Required:</strong> Run the database migration script.</p>";
            echo "<div class='code'>";
            echo "mysql -u username -p database_name < setup/database_migration.sql";
            echo "</div>";
        } else {
            echo "<p class='status-ok'><strong>✓ Database is properly configured.</strong></p>";
        }
    }
    echo "</div>";

    // Check PHP configuration
    echo "<div class='section'>";
    echo "<h3>PHP Configuration</h3>";
    
    $phpConfig = checkPHPConfig();
    
    echo "<table>";
    echo "<tr><th>Setting</th><th>Current Value</th><th>Recommended</th><th>Status</th></tr>";
    
    $configChecks = [
        ['file_uploads_enabled', $phpConfig['file_uploads_enabled'] ? 'On' : 'Off', 'On', $phpConfig['file_uploads_enabled']],
        ['upload_max_filesize', $phpConfig['upload_max_filesize'], '≥ 10M', true],
        ['post_max_size', $phpConfig['post_max_size'], '≥ 10M', true],
        ['max_file_uploads', $phpConfig['max_file_uploads'], '≥ 10', $phpConfig['max_file_uploads'] >= 10],
        ['max_execution_time', $phpConfig['max_execution_time'], '≥ 30', $phpConfig['max_execution_time'] >= 30 || $phpConfig['max_execution_time'] == 0],
        ['memory_limit', $phpConfig['memory_limit'], '≥ 128M', true]
    ];
    
    $phpOk = true;
    foreach ($configChecks as $check) {
        [$setting, $current, $recommended, $status] = $check;
        
        if (!$status) {
            $phpOk = false;
        }
        
        echo "<tr>";
        echo "<td>$setting</td>";
        echo "<td>$current</td>";
        echo "<td>$recommended</td>";
        echo "<td class='" . ($status ? 'status-ok' : 'status-warning') . "'>" . ($status ? '✓' : '⚠') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (!$phpOk) {
        echo "<p class='status-warning'><strong>⚠ Some PHP settings may need adjustment for optimal file upload performance.</strong></p>";
    } else {
        echo "<p class='status-ok'><strong>✓ PHP is properly configured for file uploads.</strong></p>";
    }
    echo "</div>";

    // Overall status
    echo "<div class='section'>";
    echo "<h3>Overall Status</h3>";
    
    if ($directoriesOk && $dbCheck['connection'] && $dbCheck['messages_table_ok'] && $dbCheck['subscriptions_file_column']) {
        echo "<p class='status-ok'><strong>✓ Your system is ready for LiveSupport file upload functionality!</strong></p>";
    } else {
        echo "<p class='status-error'><strong>⚠ Some issues need to be resolved before file uploads will work properly.</strong></p>";
    }
    echo "</div>";

    // Additional information
    echo "<div class='section'>";
    echo "<h3>Additional Information</h3>";
    echo "<ul>";
    echo "<li>Supported file types: Images (JPG, PNG, GIF), Documents (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX), Text files (TXT), Archives (ZIP, RAR)</li>";
    echo "<li>Maximum file size: 10MB per file (configurable in functions.php)</li>";
    echo "<li>File uploads are enabled for Standard and Premium subscription plans</li>";
    echo "<li>Files are stored in the uploads/messages directory with unique names</li>";
    echo "<li>All file access is controlled through secure download handlers</li>";
    echo "</ul>";
    echo "</div>";
    ?>

</body>
</html>