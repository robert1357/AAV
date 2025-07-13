<?php
session_start();

// Si el usuario ya está logueado, redirigir al dashboard correspondiente
if (isset($_SESSION['user_id']) && isset($_SESSION['tipo_usuario'])) {
    switch ($_SESSION['tipo_usuario']) {
        case 'estudiante':
            header('Location: ../student/dashboard.php');
            break;
        case 'docente':
            header('Location: ../teacher/dashboard.php');
            break;
        case 'director':
            header('Location: ../director/dashboard.php');
            break;
        case 'secretaria':
            header('Location: ../secretaria/dashboard.php');
            break;
        case 'psicologo':
            header('Location: ../psicologa/dashboard.php');
            break;
        case 'auxiliar_educacion':
            header('Location: ../auxiliar_educacion/dashboard.php');
            break;
        case 'coordinador_tutoria':
            header('Location: ../coordinador_tutoria/dashboard.php');
            break;
        case 'jefe_laboratorio':
            header('Location: ../jefe_laboratorio/dashboard.php');
            break;
        default:
            // Si hay sesión pero tipo de usuario no válido, destruir sesión
            session_destroy();
            break;
    }
    if (isset($_SESSION['user_id'])) {
        exit();
    }
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$error_message = '';
$success_message = '';

// Manejar envío del formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error_message = 'Por favor, ingresa email y contraseña.';
    } else {
        try {
            // Buscar usuario en tabla de estudiantes
            $stmt = $pdo->prepare("
                SELECT 
                    id_estudiante as user_id,
                    'estudiante' as tipo_usuario,
                    email,
                    password_hash,
                    nombres,
                    apellido_paterno,
                    apellido_materno,
                    estado,
                    ultimo_acceso
                FROM estudiantes 
                WHERE email = ? AND estado = 'ACTIVO'
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Si no se encuentra en estudiantes, buscar en personal
            if (!$user) {
                $stmt = $pdo->prepare("
                    SELECT 
                        id_personal as user_id,
                        tipo_personal as tipo_usuario,
                        email,
                        password_hash,
                        nombres,
                        apellido_paterno,
                        apellido_materno,
                        estado,
                        ultimo_acceso
                    FROM personal 
                    WHERE email = ? AND estado = 'ACTIVO'
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
            }

            if ($user && password_verify($password, $user['password_hash'])) {
                // Login exitoso
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['tipo_usuario'] = strtolower($user['tipo_usuario']);
                $_SESSION['user_name'] = $user['nombres'] . ' ' . $user['apellido_paterno'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['last_activity'] = time();

                // Actualizar último acceso
                $table = ($_SESSION['tipo_usuario'] === 'estudiante') ? 'estudiantes' : 'personal';
                $id_field = ($_SESSION['tipo_usuario'] === 'estudiante') ? 'id_estudiante' : 'id_personal';
                
                $stmt = $pdo->prepare("UPDATE $table SET ultimo_acceso = NOW() WHERE $id_field = ?");
                $stmt->execute([$user['user_id']]);

                // Log de actividad
                logActivity(
                    $pdo,
                    $user['user_id'],
                    $_SESSION['tipo_usuario'],
                    'LOGIN',
                    'Usuario inició sesión desde IP: ' . $_SERVER['REMOTE_ADDR']
                );

                // Configurar cookie si "recordar sesión" está marcado
                if ($remember) {
                    setcookie('remember_user', $user['user_id'], time() + (30 * 24 * 60 * 60), '/'); // 30 días
                }

                // Redirigir según tipo de usuario
                switch ($_SESSION['tipo_usuario']) {
                    case 'estudiante':
                        header('Location: ../student/dashboard.php');
                        break;
                    case 'docente':
                        header('Location: ../teacher/dashboard.php');
                        break;
                    case 'director':
                        header('Location: ../director/dashboard.php');
                        break;
                    case 'secretaria':
                        header('Location: ../secretaria/dashboard.php');
                        break;
                    case 'psicologo':
                        header('Location: ../psicologa/dashboard.php');
                        break;
                    case 'auxiliar_educacion':
                        header('Location: ../auxiliar_educacion/dashboard.php');
                        break;
                    case 'coordinador_tutoria':
                        header('Location: ../coordinador_tutoria/dashboard.php');
                        break;
                    case 'jefe_laboratorio':
                        header('Location: ../jefe_laboratorio/dashboard.php');
                        break;
                    default:
                        header('Location: ../index.php');
                        break;
                }
                exit();
            } else {
                $error_message = 'Email o contraseña incorrectos.';
                
                // Log de intento fallido
                $ip = $_SERVER['REMOTE_ADDR'];
                error_log("Intento de login fallido para email: $email desde IP: $ip");
            }
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
            $error_message = 'Error del sistema. Por favor, intenta más tarde.';
        }
    }
}

// Manejar mensajes de URL
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'logout':
            $success_message = 'Sesión cerrada correctamente.';
            break;
        case 'session_expired':
            $error_message = 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.';
            break;
        case 'access_denied':
            $error_message = 'Acceso denegado. No tienes permisos para acceder a esa página.';
            break;
    }
}

$page_title = "Iniciar Sesión - Aula Virtual";
include '../includes/header.php';
?>

<div class="min-vh-100 d-flex align-items-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="fas fa-school fa-3x text-primary"></i>
                            </div>
                            <h2 class="h4 text-gray-900 mb-2">¡Bienvenido de vuelta!</h2>
                            <p class="text-muted">Ingresa a tu cuenta del Aula Virtual</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($error_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= htmlspecialchars($success_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="loginForm">
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?= htmlspecialchars($email ?? '') ?>"
                                           placeholder="tu-email@ejemplo.com"
                                           required>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="••••••••"
                                           required>
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Recordar mi sesión
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Iniciar Sesión
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <a href="../index.php" class="btn btn-link text-muted">
                                <i class="fas fa-arrow-left me-1"></i>
                                Volver al inicio
                            </a>
                        </div>

                        <div class="row text-center mt-4">
                            <div class="col-12">
                                <small class="text-muted">
                                    ¿Problemas para acceder? Contacta con la administración
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="text-center mt-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-white-50">
                                <i class="fas fa-user-graduate fa-2x mb-2"></i>
                                <div>Estudiantes</div>
                                <small>Tareas y calificaciones</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-white-50">
                                <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                                <div>Docentes</div>
                                <small>Gestión de cursos</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-white-50">
                                <i class="fas fa-users-cog fa-2x mb-2"></i>
                                <div>Administración</div>
                                <small>Control del sistema</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.95);
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
}

.input-group-text {
    background-color: #f8f9fa;
    border-right: none;
}

.form-control {
    border-left: none;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.text-white-50 {
    color: rgba(255, 255, 255, 0.7) !important;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.6s ease-out;
}

/* Loading animation */
.btn-loading {
    position: relative;
    color: transparent !important;
}

.btn-loading::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 1.2rem;
    height: 1.2rem;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    color: white;
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });

    // Form submission with loading state
    const loginForm = document.getElementById('loginForm');
    const submitBtn = loginForm.querySelector('button[type="submit"]');
    
    loginForm.addEventListener('submit', function() {
        submitBtn.classList.add('btn-loading');
        submitBtn.disabled = true;
        
        // Re-enable after 10 seconds as fallback
        setTimeout(() => {
            submitBtn.classList.remove('btn-loading');
            submitBtn.disabled = false;
        }, 10000);
    });

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 150);
            }
        }, 5000);
    });

    // Focus on email input
    document.getElementById('email').focus();
});

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php include '../includes/footer.php'; ?>