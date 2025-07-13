<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar si el registro está habilitado
$registro_habilitado = false; // Cambiar a true para habilitar registro público

if (!$registro_habilitado) {
    header('Location: login.php');
    exit;
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres = trim($_POST['nombres']);
    $apellido_paterno = trim($_POST['apellido_paterno']);
    $apellido_materno = trim($_POST['apellido_materno']);
    $dni = trim($_POST['dni']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $cargo = $_POST['cargo'];
    
    // Validaciones
    if (empty($nombres) || empty($apellido_paterno) || empty($dni) || empty($email) || empty($password)) {
        $error_message = "Todos los campos obligatorios deben ser completados.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "El formato del email no es válido.";
    } elseif (strlen($dni) !== 8 || !is_numeric($dni)) {
        $error_message = "El DNI debe tener 8 dígitos.";
    } elseif (strlen($password) < 6) {
        $error_message = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Las contraseñas no coinciden.";
    } else {
        try {
            // Verificar si el DNI ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM personal WHERE dni = ?");
            $stmt->execute([$dni]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Ya existe un usuario con este DNI.";
            } else {
                // Verificar si el email ya existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM personal WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error_message = "Ya existe un usuario con este email.";
                } else {
                    // Insertar nuevo usuario
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO personal (nombres, apellido_paterno, apellido_materno, dni, email, 
                                            password_hash, telefono, direccion, cargo, activo, primer_acceso)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
                    ");
                    
                    $stmt->execute([
                        $nombres, $apellido_paterno, $apellido_materno, $dni, $email,
                        $password_hash, $telefono, $direccion, $cargo
                    ]);
                    
                    $success_message = "Usuario registrado exitosamente. Puede iniciar sesión ahora.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Error al registrar el usuario. Inténtelo nuevamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Aula Virtual</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow mt-5">
                    <div class="card-header bg-primary text-white text-center">
                        <h4><i class="fas fa-user-plus"></i> Registro de Usuario</h4>
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

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nombres" class="form-label">Nombres *</label>
                                        <input type="text" class="form-control" id="nombres" name="nombres" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="apellido_paterno" class="form-label">Apellido Paterno *</label>
                                        <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="apellido_materno" class="form-label">Apellido Materno</label>
                                        <input type="text" class="form-control" id="apellido_materno" name="apellido_materno">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="dni" class="form-label">DNI *</label>
                                        <input type="text" class="form-control" id="dni" name="dni" maxlength="8" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="telefono" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección</label>
                                <input type="text" class="form-control" id="direccion" name="direccion">
                            </div>
                            
                            <div class="mb-3">
                                <label for="cargo" class="form-label">Cargo *</label>
                                <select class="form-select" id="cargo" name="cargo" required>
                                    <option value="">Seleccionar cargo</option>
                                    <option value="DOCENTE">Docente</option>
                                    <option value="AUXILIAR_EDUCACION">Auxiliar de Educación</option>
                                    <option value="SECRETARIA">Secretaria</option>
                                    <option value="AUXILIAR_LABORATORIO">Auxiliar de Laboratorio</option>
                                    <option value="AUXILIAR_BIBLIOTECA">Auxiliar de Biblioteca</option>
                                    <option value="PERSONAL_ADMINISTRATIVO">Personal Administrativo</option>
                                    <option value="PERSONAL_VIGILANCIA">Personal de Vigilancia</option>
                                    <option value="PSICOLOGO">Psicólogo</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Contraseña *</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="form-text">Mínimo 6 caracteres</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        Acepto los términos y condiciones del sistema
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus"></i> Registrarse
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validar DNI en tiempo real
        document.getElementById('dni').addEventListener('input', function(e) {
            const value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });
        
        // Validar confirmación de contraseña
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = e.target.value;
            
            if (password !== confirmPassword) {
                e.target.setCustomValidity('Las contraseñas no coinciden');
            } else {
                e.target.setCustomValidity('');
            }
        });
    </script>
</body>
</html>