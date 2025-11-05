<?php
/**
 * Database Connection Handler
 * PDO-based database connection with error handling
 */

// Prevent direct access
if (!defined('LT_INIT')) {
    die('Direct access not permitted');
}

/**
 * Get PDO database connection
 * 
 * @return PDO Database connection object
 * @throws Exception if connection fails
 */
function get_db_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            log_error('Database connection failed: ' . $e->getMessage());
            
            if (DEBUG_MODE) {
                die('Database connection failed: ' . $e->getMessage());
            } else {
                die('Database connection error. Please check your configuration.');
            }
        }
    }
    
    return $pdo;
}

/**
 * Execute a prepared SQL query
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement Executed statement
 */
function db_query($sql, $params = []) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        log_error('Query error: ' . $e->getMessage() . ' | SQL: ' . $sql);
        throw $e;
    }
}

/**
 * Fetch a single row
 * 
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array|false Row data or false
 */
function db_fetch($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetch();
}

/**
 * Fetch all rows
 * 
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array Array of rows
 */
function db_fetch_all($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert a row and return the last insert ID
 * 
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return string Last insert ID
 */
function db_insert($sql, $params = []) {
    db_query($sql, $params);
    return get_db_connection()->lastInsertId();
}

/**
 * Get table name with prefix
 * 
 * @param string $table Table name without prefix
 * @return string Full table name with prefix
 */
function table($table) {
    return DB_PREFIX . $table;
}

/**
 * Check if database tables exist
 * 
 * @return bool True if tables exist
 */
function db_tables_exist() {
    try {
        $pdo = get_db_connection();
        $tables = ['links', 'clicks', 'users', 'sessions', 'login_attempts'];
        
        foreach ($tables as $table) {
            $sql = "SHOW TABLES LIKE '" . table($table) . "'";
            $stmt = $pdo->query($sql);
            if ($stmt->rowCount() === 0) {
                return false;
            }
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

