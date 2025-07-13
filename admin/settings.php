<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'ADMIN') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Configuraciones del Sistema";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Obtener configuraciones actuales
$settings = [
    'nombre_institucion' => 'Institución Educativa',
    'logo_path' => '/assets/images/logo.png',
    'email_admin' => 'admin@institucion.edu.pe',
    'telefono' => '(01) 234-5678',
    'direccion' => 'Jr. Educación 123, Lima, Perú',
    'anio_academico_actual' => date('Y'),
    'bimestre_actual' => 1,
    'max_intentos_login' => 3,
    'tiempo_sesion' => 30,
    'backup_automatico' => true,
    'notificaciones_email' => true,
    'mantenimiento' => false
];
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2><?php echo $page_title; ?></h2>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Configuración General -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Configuración General</h5>
                </div>
                <div class="card-body">
                    <form id="generalSettingsForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nombreInstitucion" class="form-label">Nombre de la Institución</label>
                                    <input type="text" class="form-control" id="nombreInstitucion" value="<?php echo $settings['nombre_institucion']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emailAdmin" class="form-label">Email Administrativo</label>
                                    <input type="email" class="form-control" id="emailAdmin" value="<?php echo $settings['email_admin']; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" value="<?php echo $settings['telefono']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="direccion" class="form-label">Dirección</label>
                                    <input type="text" class="form-control" id="direccion" value="<?php echo $settings['direccion']; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="logoFile" class="form-label">Logo de la Institución</label>
                            <input type="file" class="form-control" id="logoFile" accept="image/*">
                            <small class="form-text text-muted">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB</small>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Configuración Académica -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Configuración Académica</h5>
                </div>
                <div class="card-body">
                    <form id="academicSettingsForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="anioAcademico" class="form-label">Año Académico Actual</label>
                                    <input type="number" class="form-control" id="anioAcademico" value="<?php echo $settings['anio_academico_actual']; ?>" min="2020" max="2030">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bimestreActual" class="form-label">Bimestre Actual</label>
                                    <select class="form-select" id="bimestreActual">
                                        <option value="1" <?php echo $settings['bimestre_actual'] == 1 ? 'selected' : ''; ?>>Primer Bimestre</option>
                                        <option value="2" <?php echo $settings['bimestre_actual'] == 2 ? 'selected' : ''; ?>>Segundo Bimestre</option>
                                        <option value="3" <?php echo $settings['bimestre_actual'] == 3 ? 'selected' : ''; ?>>Tercer Bimestre</option>
                                        <option value="4" <?php echo $settings['bimestre_actual'] == 4 ? 'selected' : ''; ?>>Cuarto Bimestre</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Configuración de Seguridad -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Configuración de Seguridad</h5>
                </div>
                <div class="card-body">
                    <form id="securitySettingsForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maxIntentosLogin" class="form-label">Máximo Intentos de Login</label>
                                    <input type="number" class="form-control" id="maxIntentosLogin" value="<?php echo $settings['max_intentos_login']; ?>" min="1" max="10">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tiempoSesion" class="form-label">Tiempo de Sesión (minutos)</label>
                                    <input type="number" class="form-control" id="tiempoSesion" value="<?php echo $settings['tiempo_sesion']; ?>" min="15" max="120">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="backupAutomatico" <?php echo $settings['backup_automatico'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="backupAutomatico">
                                    Backup Automático Diario
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notificacionesEmail" <?php echo $settings['notificaciones_email'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notificacionesEmail">
                                    Notificaciones por Email
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-primary" onclick="saveSettings()">
                            <i class="fas fa-save"></i> Guardar Configuraciones
                        </button>
                        <button type="button" class="btn btn-warning" onclick="resetSettings()">
                            <i class="fas fa-undo"></i> Restablecer Valores
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Estado del Sistema -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Estado del Sistema</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="modoMantenimiento" <?php echo $settings['mantenimiento'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="modoMantenimiento">
                                Modo Mantenimiento
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            El sistema será inaccesible para todos los usuarios excepto administradores.
                        </small>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <strong>Versión del Sistema:</strong> 1.0.0<br>
                        <strong>Última Actualización:</strong> <?php echo date('d/m/Y H:i'); ?><br>
                        <strong>Base de Datos:</strong> MySQL 8.0<br>
                        <strong>PHP:</strong> <?php echo phpversion(); ?>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="card">
                <div class="card-header">
                    <h5>Acciones Rápidas</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary" onclick="clearCache()">
                            <i class="fas fa-broom"></i> Limpiar Caché
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="viewSystemLogs()">
                            <i class="fas fa-file-alt"></i> Ver Logs del Sistema
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="optimizeDatabase()">
                            <i class="fas fa-database"></i> Optimizar Base de Datos
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="testConnections()">
                            <i class="fas fa-plug"></i> Probar Conexiones
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function saveSettings() {
    if (confirm('¿Está seguro de guardar las configuraciones?')) {
        alert('Función de guardado en desarrollo');
        // Implementar guardado de configuraciones
    }
}

function resetSettings() {
    if (confirm('¿Está seguro de restablecer las configuraciones?')) {
        alert('Función de restablecimiento en desarrollo');
        // Implementar restablecimiento
    }
}

function clearCache() {
    if (confirm('¿Está seguro de limpiar el caché?')) {
        alert('Función de limpieza en desarrollo');
    }
}

function viewSystemLogs() {
    window.open('../logs/system.log', '_blank');
}

function optimizeDatabase() {
    if (confirm('¿Está seguro de optimizar la base de datos?')) {
        alert('Función de optimización en desarrollo');
    }
}

function testConnections() {
    alert('Función de prueba en desarrollo');
}

// Modo mantenimiento
document.getElementById('modoMantenimiento').addEventListener('change', function() {
    if (this.checked) {
        if (confirm('¿Está seguro de activar el modo mantenimiento?')) {
            alert('Función de mantenimiento en desarrollo');
        } else {
            this.checked = false;
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>