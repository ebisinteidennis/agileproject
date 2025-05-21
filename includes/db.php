<?php 
require_once 'config.php'; 

class Database {
    private $conn;
    
    public function __construct() {
        try {
            $this->conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            // Log the error details
            error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . print_r($params, true));
            throw new PDOException("Query Error: " . $e->getMessage());
        }
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        // Handle reserved words in column names
        $columns = [];
        $placeholders = [];
        
        foreach(array_keys($data) as $column) {
            // If the column is a reserved word (like 'read'), add backticks
            if (in_array(strtolower($column), ['read', 'order', 'group', 'key', 'index', 'primary', 'unique'])) {
                $columns[] = "`$column`";
            } else {
                $columns[] = $column;
            }
            
            $placeholders[] = ":" . str_replace("`", "", $column);
        }
        
        $columnList = implode(", ", $columns);
        $placeholderList = implode(", ", $placeholders);
        
        $sql = "INSERT INTO $table ($columnList) VALUES ($placeholderList)";
        $this->query($sql, $data);
        
        return $this->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setClauses = [];
        foreach(array_keys($data) as $column) {
            // Handle reserved words
            if (in_array(strtolower($column), ['read', 'order', 'group', 'key', 'index', 'primary', 'unique'])) {
                $setClauses[] = "`$column` = :$column";
            } else {
                $setClauses[] = "$column = :$column";
            }
        }
        
        $setClause = implode(", ", $setClauses);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        
        $params = array_merge($data, $whereParams);
        $this->query($sql, $params);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $this->query($sql, $params);
    }
    
    /**
     * Get the ID of the last inserted row
     * 
     * @return string|int The last insert ID
     */
    public function lastInsertId() {
        try {
            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            error_log('Error getting last insert ID: ' . $e->getMessage());
            return 0;
        }
    }
}

$db = new Database();
?>