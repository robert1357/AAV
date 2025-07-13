<?php
// Configuraciones generales del sistema

// Configuración del sitio
define('SITE_NAME', 'Aula Virtual');
define('SITE_DESCRIPTION', 'Sistema de Gestión Educativa');
define('SITE_VERSION', '1.0.0');

// Configuración de la aplicación
define('APP_ENV', 'development'); // development, production
define('APP_DEBUG', true);
define('APP_TIMEZONE', 'America/Lima');

// Configuración de sesiones
define('SESSION_TIMEOUT', 1800); // 30 minutos
define('SESSION_NAME', 'AULA_VIRTUAL_SESSION');

// Configuración de archivos
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar']);
define('UPLOAD_PATH', '../uploads/');

// Configuración de email
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM_EMAIL', 'no-reply@aulavirtual.edu');
define('MAIL_FROM_NAME', 'Aula Virtual');

// Configuración de seguridad
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configuración académica
define('CURRENT_YEAR', date('Y'));
define('CURRENT_BIMESTER', 1);
define('GRADES', ['1' => '1° Grado', '2' => '2° Grado', '3' => '3° Grado', '4' => '4° Grado', '5' => '5° Grado']);
define('SECTIONS', ['A', 'B', 'C', 'D']);

// Configuración de roles
define('ROLES', [
    'ADMIN' => 'Administrador',
    'DIRECTOR' => 'Director',
    'JEFE_LABORATORIO' => 'Jefe de Laboratorio',
    'COORDINADOR_CIENCIAS' => 'Coordinador de Ciencias',
    'COORDINADOR_LETRAS' => 'Coordinador de Letras',
    'COORDINADOR_TUTORIA' => 'Coordinador de Tutoría',
    'DOCENTE' => 'Docente',
    'DOCENTE_DAIP' => 'Docente DAIP',
    'AUXILIAR_EDUCACION' => 'Auxiliar de Educación',
    'AUXILIAR_LABORATORIO' => 'Auxiliar de Laboratorio',
    'AUXILIAR_BIBLIOTECA' => 'Auxiliar de Biblioteca',
    'SECRETARIA' => 'Secretaría',
    'PERSONAL_ADMINISTRATIVO' => 'Personal Administrativo',
    'PERSONAL_VIGILANCIA' => 'Personal de Vigilancia',
    'PSICOLOGO' => 'Psicólogo',
    'CIST' => 'CIST'
]);

// Configuración de áreas curriculares
define('AREAS', [
    'MATEMATICAS' => 'Matemáticas',
    'COMUNICACION' => 'Comunicación',
    'CIENCIAS' => 'Ciencias',
    'LETRAS' => 'Letras',
    'EDUCACION_FISICA' => 'Educación Física',
    'ARTE' => 'Arte',
    'RELIGION' => 'Religión',
    'TUTORIA' => 'Tutoría'
]);

// Configuración de base de datos (si no existe database.php)
if (!file_exists(__DIR__ . '/database.php')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'aula_virtual');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

// Configuración de zona horaria
date_default_timezone_set(APP_TIMEZONE);

// Configuración de errores según el ambiente
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

// Headers de seguridad
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    if (APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
?>