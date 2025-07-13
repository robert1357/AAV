<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $message = "Por favor ingrese su email.";
        $message_type = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "El formato del email no es válido.";
        $message_type = "danger";
    } else {
        try {
            // Verificar si el email existe
            $stmt = $pdo->prepare("SELECT id_personal, nombres, apellido_paterno FROM personal WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generar token de recuperación
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token en la base de datos
                $stmt = $pdo->prepare("
                    UPDATE personal 
                    SET token_recuperacion = ?, token_expiracion = ? 
                    WHERE id_personal = ?
                ");
                $stmt->execute([$token, $expiry, $user['id_personal']]);
                
                // Crear enlace de recuperación
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/auth/reset_password.php?token=" . $token;
                
                // Simular envío de email
                // En un entorno real, aquí se enviaría un email con PHPMailer o similar
                $message = "Se ha enviado un enlace de recuperación a su email. Por favor revise su bandeja de entrada.";
                $message_type = "success";
                
                // Para propósitos de demostración, mostrar el enlace
                if (isset($_GET['debug'])) {
                    $message .= "<br><br><strong>Debug:</strong> <a href='{$reset_link}'>Enlace de recuperación</a>";
                }
                
            } else {
                $message = "No se encontró una cuenta con ese email.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Error al procesar la solicitud. Inténtelo nuevamente.";
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Aula Virtual</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow mt-5">
                    <div class="card-header bg-warning text-dark text-center">
                        <h4><i class="fas fa-key"></i> Recuperar Contraseña</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?>">
                                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <p class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Ingrese su email para recibir un enlace de recuperación de contraseña.
                            </p>
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="ejemplo@email.com" required>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning btn-lg">
                                    <i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">
                            <a href="login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                            </a>
                        </p>
                    </div>
                </div>
                
                <!-- Información adicional -->
                <div class="card mt-3 bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-question-circle"></i> ¿Necesita ayuda?</h6>
                        <p class="text-muted mb-0">
                            Si no recibe el email de recuperación, verifique su carpeta de spam 
                            o contacte al administrador del sistema.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>