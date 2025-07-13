<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$token = $_GET['token'] ?? '';
$error_message = '';
$success_message = '';
$valid_token = false;
$user_data = null;

// Verificar token
if ($token) {
    try {
        $stmt = $pdo->prepare("
            SELECT id_personal, nombres, apellido_paterno, email, token_expiracion 
            FROM personal 
            WHERE token_recuperacion = ? AND activo = 1
        ");
        $stmt->execute([$token]);
        $user_data = $stmt->fetch();
        
        if ($user_data) {
            if (strtotime($user_data['token_expiracion']) > time()) {
                $valid_token = true;
            } else {
                $error_message = "El enlace de recuperación ha expirado. Solicite uno nuevo.";
            }
        } else {
            $error_message = "El enlace de recuperación no es válido.";
        }
    } catch (PDOException $e) {
        $error_message = "Error al verificar el enlace de recuperación.";
    }
} else {
    $error_message = "No se proporcionó un token de recuperación válido.";
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error_message = "Todos los campos son obligatorios.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Las contraseñas no coinciden.";
    } else {
        try {
            // Actualizar contraseña
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                UPDATE personal 
                SET password_hash = ?, token_recuperacion = NULL, token_expiracion = NULL, primer_acceso = 0
                WHERE id_personal = ?
            ");
            $stmt->execute([$password_hash, $user_data['id_personal']]);
            
            $success_message = "Su contraseña ha sido actualizada exitosamente. Ahora puede iniciar sesión.";
            $valid_token = false; // Deshabilitar el formulario
            
        } catch (PDOException $e) {
            $error_message = "Error al actualizar la contraseña. Inténtelo nuevamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - Aula Virtual</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow mt-5">
                    <div class="card-header bg-success text-white text-center">
                        <h4><i class="fas fa-lock"></i> Restablecer Contraseña</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($valid_token): ?>
                            <div class="mb-3">
                                <p class="text-muted">
                                    <i class="fas fa-user"></i> 
                                    Restablecer contraseña para: 
                                    <strong><?php echo htmlspecialchars($user_data['nombres'] . ' ' . $user_data['apellido_paterno']); ?></strong>
                                </p>
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nueva Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               placeholder="Ingrese nueva contraseña" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">La contraseña debe tener al menos 6 caracteres.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="Confirme nueva contraseña" required>
                                    </div>
                                </div>
                                
                                <!-- Indicador de fortaleza de contraseña -->
                                <div class="mb-3">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small class="form-text text-muted" id="passwordStrengthText">Ingrese una contraseña</small>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> Actualizar Contraseña
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <p class="text-muted">
                                    <?php if ($success_message): ?>
                                        <a href="login.php" class="btn btn-primary">
                                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                                        </a>
                                    <?php else: ?>
                                        <a href="forgot_password.php" class="btn btn-warning">
                                            <i class="fas fa-redo"></i> Solicitar Nuevo Enlace
                                        </a>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">
                            <a href="login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('new_password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');
            
            let strength = 0;
            let text = '';
            let color = '';
            
            if (password.length >= 6) strength += 20;
            if (password.length >= 8) strength += 20;
            if (/[a-z]/.test(password)) strength += 20;
            if (/[A-Z]/.test(password)) strength += 20;
            if (/[0-9]/.test(password)) strength += 20;
            
            if (strength < 40) {
                text = 'Débil';
                color = 'bg-danger';
            } else if (strength < 80) {
                text = 'Media';
                color = 'bg-warning';
            } else {
                text = 'Fuerte';
                color = 'bg-success';
            }
            
            strengthBar.style.width = strength + '%';
            strengthBar.className = 'progress-bar ' + color;
            strengthText.textContent = text;
        });
        
        // Validate password confirmation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>