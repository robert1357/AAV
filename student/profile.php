<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Mi Perfil - Estudiante";

// Obtener datos completos del estudiante
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        m.fecha_matricula,
        g.numero_grado,
        g.descripcion as grado_descripcion,
        s.letra_seccion,
        a.anio,
        COUNT(DISTINCT n.id_nota) as total_notas,
        AVG(n.nota) as promedio_general,
        COUNT(DISTINCT et.id_entrega) as tareas_entregadas,
        COUNT(DISTINCT mat.id_material) as materiales_descargados
    FROM estudiantes e
    JOIN matriculas m ON e.id_estudiante = m.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos a ON m.id_anio = a.id_anio
    LEFT JOIN notas n ON m.id_matricula = n.id_matricula
    LEFT JOIN entregas_tareas et ON m.id_matricula = et.id_matricula
    LEFT JOIN descargas_materiales dm ON e.id_estudiante = dm.id_estudiante
    LEFT JOIN materiales mat ON dm.id_material = mat.id_material
    WHERE e.id_estudiante = ? AND m.estado = 'ACTIVO' AND a.estado = 'ACTIVO'
    GROUP BY e.id_estudiante
");
$stmt->execute([$_SESSION['user_id']]);
$estudiante = $stmt->fetch();

if (!$estudiante) {
    header('Location: dashboard.php?error=perfil_no_encontrado');
    exit();
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    try {
        // Usar el procedimiento almacenado sp_cambiar_password_estudiante
        $stmt = $pdo->prepare("CALL sp_cambiar_password_estudiante(?, ?, ?)");
        $stmt->execute([
            $estudiante['dni'],
            $_POST['password_actual'],
            $_POST['password_nueva']
        ]);
        
        $result = $stmt->fetch();
        $success_message = $result['mensaje'];
        
    } catch (Exception $e) {
        $error_message = "Error al cambiar contraseña: " . $e->getMessage();
    }
}

// Procesar actualización de datos de contacto del apoderado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_apoderado'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE estudiantes 
            SET apoderado_nombres = ?, 
                apoderado_celular = ?, 
                apoderado_email = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id_estudiante = ?
        ");
        
        $stmt->execute([
            $_POST['apoderado_nombres'],
            $_POST['apoderado_celular'],
            $_POST['apoderado_email'],
            $_SESSION['user_id']
        ]);
        
        $pdo->commit();
        $success_message = "Datos del apoderado actualizados exitosamente";
        
        // Recargar datos del estudiante
        $stmt = $pdo->prepare("SELECT * FROM estudiantes WHERE id_estudiante = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $estudiante = array_merge($estudiante, $stmt->fetch());
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al actualizar datos: " . $e->getMessage();
    }
}

// Obtener historial académico (notas por bimestre)
$stmt = $pdo->prepare("
    SELECT 
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        b.numero_bimestre,
        n.nota,
        n.observaciones,
        n.fecha_registro
    FROM notas n
    JOIN cursos c ON n.id_curso = c.id_curso
    JOIN bimestres b ON n.id_bimestre = b.id_bimestre
    JOIN matriculas m ON n.id_matricula = m.id_matricula
    WHERE m.id_estudiante = ? AND m.estado = 'ACTIVO'
    ORDER BY c.nombre, b.numero_bimestre
");
$stmt->execute([$_SESSION['user_id']]);
$historial_notas = $stmt->fetchAll();

// Obtener actividad reciente
$stmt = $pdo->prepare("
    SELECT 
        'tarea' as tipo,
        t.titulo as descripcion,
        et.fecha_entrega as fecha,
        'Tarea entregada' as accion
    FROM entregas_tareas et
    JOIN tareas t ON et.id_tarea = t.id_tarea
    JOIN matriculas m ON et.id_matricula = m.id_matricula
    WHERE m.id_estudiante = ?
    
    UNION ALL
    
    SELECT 
        'material' as tipo,
        mat.titulo as descripcion,
        dm.fecha_descarga as fecha,
        'Material descargado' as accion
    FROM descargas_materiales dm
    JOIN materiales mat ON dm.id_material = mat.id_material
    WHERE dm.id_estudiante = ?
    
    ORDER BY fecha DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$actividad_reciente = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Información Personal -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header text-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-circle"></i> Mi Perfil
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img src="../assets/images/avatars/default.png" 
                             alt="Foto de perfil" class="rounded-circle" width="100" height="100">
                    </div>
                    
                    <h5><?= htmlspecialchars($estudiante['nombres'] . ' ' . $estudiante['apellido_paterno']) ?></h5>
                    <p class="text-muted"><?= htmlspecialchars($estudiante['codigo_estudiante']) ?></p>
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <h6><?= $estudiante['numero_grado'] ?>°</h6>
                            <small class="text-muted">Grado</small>
                        </div>
                        <div class="col-4">
                            <h6><?= $estudiante['letra_seccion'] ?></h6>
                            <small class="text-muted">Sección</small>
                        </div>
                        <div class="col-4">
                            <h6><?= $estudiante['anio'] ?></h6>
                            <small class="text-muted">Año</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <h6 class="text-<?= $estudiante['promedio_general'] >= 14 ? 'success' : ($estudiante['promedio_general'] >= 11 ? 'warning' : 'danger') ?>">
                                <?= number_format($estudiante['promedio_general'], 1) ?>
                            </h6>
                            <small class="text-muted">Promedio</small>
                        </div>
                        <div class="col-6">
                            <h6 class="text-info"><?= $estudiante['tareas_entregadas'] ?></h6>
                            <small class="text-muted">Tareas entregadas</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos del Apoderado -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-users"></i> Datos del Apoderado
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editarApoderadoModal">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
                <div class="card-body">
                    <p><strong>Nombre:</strong><br><?= htmlspecialchars($estudiante['apoderado_nombres']) ?></p>
                    <p><strong>Parentesco:</strong><br><?= htmlspecialchars($estudiante['apoderado_parentesco']) ?></p>
                    <p><strong>Celular:</strong><br><?= htmlspecialchars($estudiante['apoderado_celular']) ?></p>
                    <?php if ($estudiante['apoderado_email']): ?>
                        <p><strong>Email:</strong><br><?= htmlspecialchars($estudiante['apoderado_email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="col-lg-8">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Información Académica -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-graduation-cap"></i> Información Académica
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Código de Estudiante:</strong><br><?= htmlspecialchars($estudiante['codigo_estudiante']) ?></p>
                            <p><strong>DNI:</strong><br><?= htmlspecialchars($estudiante['dni']) ?></p>
                            <p><strong>Fecha de Nacimiento:</strong><br><?= date('d/m/Y', strtotime($estudiante['fecha_nacimiento'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Grado y Sección:</strong><br><?= $estudiante['numero_grado'] ?>° "<?= $estudiante['letra_seccion'] ?>"</p>
                            <p><strong>Año Académico:</strong><br><?= $estudiante['anio'] ?></p>
                            <p><strong>Fecha de Matrícula:</strong><br><?= date('d/m/Y', strtotime($estudiante['fecha_matricula'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historial de Notas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-clipboard-list"></i> Historial de Calificaciones
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($historial_notas)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th>1° Bim</th>
                                        <th>2° Bim</th>
                                        <th>3° Bim</th>
                                        <th>4° Bim</th>
                                        <th>Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $cursos_agrupados = [];
                                    foreach ($historial_notas as $nota) {
                                        $cursos_agrupados[$nota['curso_codigo']][$nota['numero_bimestre']] = $nota['nota'];
                                        $cursos_agrupados[$nota['curso_codigo']]['nombre'] = $nota['curso_nombre'];
                                    }
                                    
                                    foreach ($cursos_agrupados as $codigo => $curso): 
                                        $notas_bimestres = array_filter($curso, 'is_numeric');
                                        $promedio_curso = !empty($notas_bimestres) ? array_sum($notas_bimestres) / count($notas_bimestres) : 0;
                                    ?>
                                        <tr>
                                            <td><strong>[<?= $codigo ?>] <?= htmlspecialchars($curso['nombre']) ?></strong></td>
                                            <?php for ($bim = 1; $bim <= 4; $bim++): ?>
                                                <td>
                                                    <?php if (isset($curso[$bim])): ?>
                                                        <span class="badge bg-<?= $curso[$bim] >= 14 ? 'success' : ($curso[$bim] >= 11 ? 'warning' : 'danger') ?>">
                                                            <?= $curso[$bim] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>
                                            <td>
                                                <strong class="text-<?= $promedio_curso >= 14 ? 'success' : ($promedio_curso >= 11 ? 'warning' : 'danger') ?>">
                                                    <?= $promedio_curso > 0 ? number_format($promedio_curso, 1) : '-' ?>
                                                </strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-clipboard-list fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No hay calificaciones registradas aún</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-clock"></i> Actividad Reciente
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($actividad_reciente)): ?>
                        <div class="timeline">
                            <?php foreach ($actividad_reciente as $actividad): ?>
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <div class="timeline-marker bg-<?= $actividad['tipo'] === 'tarea' ? 'primary' : 'info' ?>"></div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1"><?= htmlspecialchars($actividad['descripcion']) ?></h6>
                                        <p class="mb-1 text-muted"><?= htmlspecialchars($actividad['accion']) ?></p>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($actividad['fecha'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No hay actividad registrada</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cambio de Contraseña -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-key"></i> Seguridad de la Cuenta
                    </h6>
                </div>
                <div class="card-body">
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#cambiarPasswordModal">
                        <i class="fas fa-key"></i> Cambiar Contraseña
                    </button>
                    <p class="text-muted mt-2 mb-0">
                        <small>Último acceso: <?= date('d/m/Y H:i') ?></small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cambiar Contraseña -->
<div class="modal fade" id="cambiarPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formCambiarPassword">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="password_actual" class="form-label">Contraseña Actual *</label>
                        <input type="password" name="password_actual" id="password_actual" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_nueva" class="form-label">Nueva Contraseña *</label>
                        <input type="password" name="password_nueva" id="password_nueva" class="form-control" required minlength="6">
                        <div class="form-text">Mínimo 6 caracteres</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirmacion" class="form-label">Confirmar Nueva Contraseña *</label>
                        <input type="password" name="password_confirmacion" id="password_confirmacion" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="cambiar_password" class="btn btn-warning">
                        <i class="fas fa-key"></i> Cambiar Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Apoderado -->
<div class="modal fade" id="editarApoderadoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Actualizar Datos del Apoderado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_apoderado_nombres" class="form-label">Nombres Completos *</label>
                        <input type="text" name="apoderado_nombres" id="edit_apoderado_nombres" 
                               class="form-control" value="<?= htmlspecialchars($estudiante['apoderado_nombres']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_apoderado_celular" class="form-label">Número de Celular *</label>
                        <input type="tel" name="apoderado_celular" id="edit_apoderado_celular" 
                               class="form-control" value="<?= htmlspecialchars($estudiante['apoderado_celular']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_apoderado_email" class="form-label">Correo Electrónico</label>
                        <input type="email" name="apoderado_email" id="edit_apoderado_email" 
                               class="form-control" value="<?= htmlspecialchars($estudiante['apoderado_email']) ?>">
                    </div>
                    
                    <div class="alert alert-info">
                        <small><i class="fas fa-info-circle"></i> Estos datos serán utilizados para comunicaciones importantes del colegio.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="actualizar_apoderado" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Datos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.timeline-marker {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    position: relative;
    top: 8px;
}
</style>

<script>
// Validación de contraseña
document.getElementById('formCambiarPassword').addEventListener('submit', function(e) {
    const nuevaPassword = document.getElementById('password_nueva').value;
    const confirmacion = document.getElementById('password_confirmacion').value;
    
    if (nuevaPassword !== confirmacion) {
        e.preventDefault();
        alert('Las contraseñas no coinciden');
        return false;
    }
    
    if (nuevaPassword.length < 6) {
        e.preventDefault();
        alert('La nueva contraseña debe tener al menos 6 caracteres');
        return false;
    }
    
    return true;
});

// Limpiar formularios al cerrar modales
document.getElementById('cambiarPasswordModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('formCambiarPassword').reset();
});
</script>

<?php include '../includes/footer.php'; ?>