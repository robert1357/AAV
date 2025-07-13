<?php
/**
 * Configuración de la base de datos
 * Sistema de Aula Virtual
 */

// Configuración de la base de datos
$db_config = [
    'host' => 'localhost',
    'dbname' => 'aula_virtual',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]
];

try {
    // Crear conexión PDO
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
    
    // Configurar zona horaria para MySQL
    $pdo->exec("SET time_zone = '-05:00'"); // Zona horaria de Perú
    
} catch (PDOException $e) {
    // Log del error (en producción usar un sistema de logs apropiado)
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    
    // Mostrar mensaje genérico al usuario
    die("Error de conexión a la base de datos. Por favor, contacte al administrador del sistema.");
}

/**
 * Función para ejecutar consultas preparadas de manera segura
 */
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Error en consulta SQL: " . $e->getMessage());
        throw new Exception("Error en la consulta a la base de datos");
    }
}

/**
 * Función para iniciar transacciones de manera segura
 */
function beginTransaction($pdo) {
    try {
        $pdo->beginTransaction();
    } catch (PDOException $e) {
        error_log("Error al iniciar transacción: " . $e->getMessage());
        throw new Exception("Error al iniciar transacción");
    }
}

/**
 * Función para hacer commit de transacciones
 */
function commitTransaction($pdo) {
    try {
        $pdo->commit();
    } catch (PDOException $e) {
        error_log("Error al hacer commit: " . $e->getMessage());
        $pdo->rollBack();
        throw new Exception("Error al confirmar transacción");
    }
}

/**
 * Función para hacer rollback de transacciones
 */
function rollbackTransaction($pdo) {
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (PDOException $e) {
        error_log("Error al hacer rollback: " . $e->getMessage());
    }
}

/**
 * Función para verificar el estado de la conexión
 */
function checkDatabaseConnection($pdo) {
    try {
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (PDOException $e) {
        error_log("Error al verificar conexión: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para obtener información de la base de datos
 */
function getDatabaseInfo($pdo) {
    try {
        $stmt = $pdo->query("SELECT VERSION() as version");
        $version = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT DATABASE() as database_name");
        $database = $stmt->fetchColumn();
        
        return [
            'version' => $version,
            'database' => $database,
            'charset' => $db_config['charset'] ?? 'utf8mb4'
        ];
    } catch (PDOException $e) {
        error_log("Error al obtener información de BD: " . $e->getMessage());
        return null;
    }
}

// Configurar manejo de errores PHP para desarrollo
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // En producción, ocultar errores y solo logearlos
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL & ~E_NOTICE);
}

// Configurar zona horaria de PHP
date_default_timezone_set('America/Lima');

// Variables globales útiles
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'avi', 'mov', 'wmv']);
define('ALLOWED_AUDIO_TYPES', ['mp3', 'wav', 'ogg']);

// Configuración de sesiones
ini_set('session.cookie_lifetime', 28800); // 8 horas
ini_set('session.gc_maxlifetime', 28800);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en HTTPS
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

/**
 * Función para limpiar datos de entrada
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Función para validar CSRF tokens (implementar según necesidades)
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Función para generar CSRF tokens
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>