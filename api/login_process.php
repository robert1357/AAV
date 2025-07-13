<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../auth/login.php?error=Método no permitido');
}

// Validar datos
$user_type = sanitize_input($_POST['user_type'] ?? '');
$dni = sanitize_input($_POST['dni'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validaciones básicas
if (empty($user_type) || empty($dni) || empty($password)) {
    redirect('../auth/login.php?error=Todos los campos son obligatorios');
}

if (!in_array($user_type, ['student', 'staff'])) {
    redirect('../auth/login.php?error=Tipo de usuario inválido');
}

if (!preg_match('/^\d{8}$/', $dni)) {
    redirect('../auth/login.php?error=DNI debe tener 8 dígitos');
}

// Obtener información del cliente
$ip = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];

try {
    $db = new Database();
    
    if ($user_type === 'student') {
        // Validar estudiante usando procedimiento almacenado
        $result = $db->validateStudentLogin($dni, $password, $ip, $userAgent);
        
        if ($result && count($result) > 0) {
            $loginResult = $result[0];
            
            if ($loginResult['status'] === 'EXITOSO') {
                // Obtener datos del estudiante
                $pdo = $db->getConnection();
                $sql = "SELECT * FROM estudiantes WHERE dni = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$dni]);
                $student = $stmt->fetch();
                
                if ($student) {
                    // Iniciar sesión
                    $_SESSION['user_id'] = $student['id_estudiante'];
                    $_SESSION['user_type'] = 'student';
                    $_SESSION['user_role'] = 'ESTUDIANTE';
                    $_SESSION['user_name'] = $student['nombres'] . ' ' . $student['apellido_paterno'];
                    $_SESSION['dni'] = $student['dni'];
                    $_SESSION['last_activity'] = time();
                    
                    // Manejar "recordar sesión"
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 días
                        
                        // Guardar token en base de datos
                        $sql = "UPDATE estudiantes SET remember_token = ? WHERE id_estudiante = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$token, $student['id_estudiante']]);
                    }
                    
                    redirect('../student/dashboard.php');
                } else {
                    redirect('../auth/login.php?error=Error al obtener datos del estudiante');
                }
            } else {
                $error = $loginResult['mensaje'] ?? 'Credenciales inválidas';
                redirect('../auth/login.php?error=' . urlencode($error));
            }
        } else {
            redirect('../auth/login.php?error=Error en el sistema de autenticación');
        }
        
    } else {
        // Validar personal
        $user = $db->validateStaffLogin($dni, $password);
        
        if ($user) {
            // Registrar acceso exitoso
            $db->logStaffAccess($user['id_personal'], $ip, $userAgent, 'EXITOSO', 'Login exitoso');
            
            // Iniciar sesión
            $_SESSION['user_id'] = $user['id_personal'];
            $_SESSION['user_type'] = 'staff';
            $_SESSION['user_role'] = $user['cargo'];
            $_SESSION['user_name'] = $user['nombres'] . ' ' . $user['apellido_paterno'];
            $_SESSION['dni'] = $user['dni'];
            $_SESSION['last_activity'] = time();
            
            // Manejar "recordar sesión"
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 días
                
                // Guardar token en base de datos
                $pdo = $db->getConnection();
                $sql = "UPDATE personal SET remember_token = ? WHERE id_personal = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$token, $user['id_personal']]);
            }
            
            // Redirigir según rol
            $role = $user['cargo'];
            if (isset(ROLE_PATHS[$role])) {
                redirect('../' . ROLE_PATHS[$role] . '/dashboard.php');
            } else {
                redirect('../index.php');
            }
            
        } else {
            // Registrar intento fallido
            $db->logStaffAccess(0, $ip, $userAgent, 'FALLIDO', 'Credenciales inválidas');
            redirect('../auth/login.php?error=Credenciales inválidas');
        }
    }
    
} catch (Exception $e) {
    error_log("Error en login: " . $e->getMessage());
    redirect('../auth/login.php?error=Error interno del sistema');
}
?>
