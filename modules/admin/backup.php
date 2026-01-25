<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin']);

$db = Database::getInstance();

/**
 * Very basic MySQL backup logic for PHP environments where mysqldump might not be available
 */
function backupDatabase($db, $dbname) {
    try {
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $return = "";

        foreach($tables as $table) {
            // Get table structure
            $result = $db->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_ASSOC);
            $return .= "\n\n" . $result["Create Table"] . ";\n\n";

            // Get table data
            $stmt = $db->query("SELECT * FROM $table");
            $columnCount = $stmt->columnCount();

            while($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $return .= "INSERT INTO $table VALUES(";
                for($j=0; $j < $columnCount; $j++) {
                    if (isset($row[$j])) {
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = str_replace("\n","\\n",$row[$j]);
                        $return .= '"'.$row[$j].'"' ;
                    } else {
                        $return .= 'NULL';
                    }
                    if ($j < ($columnCount-1)) { $return.= ','; }
                }
                $return .= ");\n";
            }
        }

        return $return;
    } catch (Exception $e) {
        return false;
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'download') {
    $backup_data = backupDatabase($db, DB_NAME);
    
    if ($backup_data) {
        $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        echo $backup_data;
        exit;
    } else {
        setFlashMessage('error', 'Failed to generate backup.');
        redirect('modules/admin/settings.php');
    }
} else {
    redirect('modules/admin/settings.php');
}
