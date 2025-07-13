<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'ADMIN') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Backup y Restauración";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Obtener lista de backups existentes
$backup_dir = '../backup/backups/';
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'file' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => filemtime($backup_dir . $file)
            ];
        }
    }
    // Ordenar por fecha descendente
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2><?php echo $page_title; ?></h2>
        </div>
    </div>

    <div class="row">
        <!-- Crear backup -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Crear Backup</h5>
                </div>
                <div class="card-body">
                    <form id="backupForm">
                        <div class="mb-3">
                            <label for="backupName" class="form-label">Nombre del Backup</label>
                            <input type="text" class="form-control" id="backupName" value="backup_<?php echo date('Y-m-d_H-i-s'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="backupDescription" class="form-label">Descripción</label>
                            <textarea class="form-control" id="backupDescription" rows="3" placeholder="Descripción opcional del backup"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeData" checked>
                                <label class="form-check-label" for="includeData">
                                    Incluir datos (estructura + datos)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="compressBackup" checked>
                                <label class="form-check-label" for="compressBackup">
                                    Comprimir backup
                                </label>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="button" class="btn btn-primary" onclick="createBackup()">
                                <i class="fas fa-database"></i> Crear Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Restaurar backup -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Restaurar Backup</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Advertencia:</strong> La restauración reemplazará todos los datos actuales.
                    </div>
                    <form id="restoreForm">
                        <div class="mb-3">
                            <label for="restoreFile" class="form-label">Subir archivo de backup</label>
                            <input type="file" class="form-control" id="restoreFile" accept=".sql,.zip">
                        </div>
                        <div class="d-grid">
                            <button type="button" class="btn btn-danger" onclick="restoreBackup()">
                                <i class="fas fa-upload"></i> Restaurar Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de backups -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Backups Existentes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($backups)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No hay backups disponibles.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Archivo</th>
                                        <th>Fecha</th>
                                        <th>Tamaño</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td><?php echo $backup['file']; ?></td>
                                        <td><?php echo date('d/m/Y H:i:s', $backup['date']); ?></td>
                                        <td><?php echo formatBytes($backup['size']); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="downloadBackup('<?php echo $backup['file']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="restoreBackupFile('<?php echo $backup['file']; ?>')">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteBackup('<?php echo $backup['file']; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function createBackup() {
    const name = document.getElementById('backupName').value;
    const description = document.getElementById('backupDescription').value;
    const includeData = document.getElementById('includeData').checked;
    const compress = document.getElementById('compressBackup').checked;
    
    if (!name) {
        alert('Por favor ingrese un nombre para el backup');
        return;
    }
    
    if (confirm('¿Está seguro de crear el backup?')) {
        alert('Función de backup en desarrollo');
        // Implementar creación de backup
    }
}

function restoreBackup() {
    const file = document.getElementById('restoreFile').files[0];
    if (!file) {
        alert('Por favor seleccione un archivo de backup');
        return;
    }
    
    if (confirm('¿Está seguro de restaurar el backup? Esto reemplazará todos los datos actuales.')) {
        alert('Función de restauración en desarrollo');
        // Implementar restauración
    }
}

function downloadBackup(filename) {
    window.location.href = '../backup/backups/' + filename;
}

function restoreBackupFile(filename) {
    if (confirm('¿Está seguro de restaurar este backup? Esto reemplazará todos los datos actuales.')) {
        alert('Función de restauración en desarrollo');
        // Implementar restauración desde archivo existente
    }
}

function deleteBackup(filename) {
    if (confirm('¿Está seguro de eliminar este backup?')) {
        alert('Función de eliminación en desarrollo');
        // Implementar eliminación de backup
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>