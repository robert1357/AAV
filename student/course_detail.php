<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$page_title = "Detalles del Curso - Estudiante";
$id_asignacion = $_GET['id'];

// Obtener datos del estudiante y matrícula
$stmt = $pdo->prepare("
    SELECT m.*, g.numero_grado, s.letra_seccion, a.anio
    FROM matriculas m
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos a ON m.id_anio = a.id_anio
    WHERE m.id_estudiante = ? AND m.estado = 'ACTIVO' AND a.estado = 'ACTIVO'
");
$stmt->execute([$_SESSION['user_id']]);
$matricula_actual = $stmt->fetch();

if (!$matricula_actual) {
    header('Location: dashboard.php?error=no_matricula');
    exit();
}

// Obtener detalles del curso
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        c.descripcion as curso_descripcion,
        c.creditos,
        c.horas_semanales,
        CONCAT(p.nombres, ' ', p.apellido_paterno, ' ', p.apellido_materno) as docente_nombre,
        p.email as docente_email,
        g.numero_grado,
        s.letra_seccion,
        an.anio,
        ar.nombre as area_nombre
    FROM asignaciones a
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN personal p ON a.id_personal = p.id_personal
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos an ON a.id_anio = an.id_anio
    LEFT JOIN areas_academicas ar ON c.id_area = ar.id_area
    WHERE a.id_asignacion = ? 
    AND a.id_seccion = ? 
    AND a.id_anio = ?
    AND a.estado = 'ACTIVO'
");
$stmt->execute([$id_asignacion, $matricula_actual['id_seccion'], $matricula_actual['id_anio']]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: dashboard.php?error=curso_no_encontrado');
    exit();
}

// Obtener materiales del curso
$stmt = $pdo->prepare("
    SELECT * FROM materiales 
    WHERE id_asignacion = ? AND es_visible = 1
    ORDER BY fecha_publicacion DESC
    LIMIT 5
");
$stmt->execute([$id_asignacion]);
$materiales_recientes = $stmt->fetchAll();

// Obtener tareas del curso
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        et.fecha_entrega,
        et.estado as estado_entrega,
        et.calificacion,
        CASE 
            WHEN et.id_entrega IS NULL THEN 'PENDIENTE'
            WHEN et.estado = 'CALIFICADO' THEN 'CALIFICADO'
            WHEN et.fecha_entrega > t.fecha_limite THEN 'ENTREGADO_TARDE'
            WHEN et.estado = 'ENTREGADO' THEN 'ENTREGADO'
            ELSE 'PENDIENTE'
        END as estado_final,
        DATEDIFF(t.fecha_limite, NOW()) as dias_restantes
    FROM tareas t
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea AND et.id_matricula = ?
    WHERE t.id_asignacion = ? AND t.estado = 'ACTIVA'
    ORDER BY t.fecha_limite ASC
    LIMIT 5
");
$stmt->execute([$matricula_actual['id_matricula'], $id_asignacion]);
$tareas_recientes = $stmt->fetchAll();

// Obtener notas del estudiante en este curso
$stmt = $pdo->prepare("
    SELECT 
        n.*,
        b.numero_bimestre,
        b.fecha_inicio,
        b.fecha_fin
    FROM notas n
    JOIN bimestres b ON n.id_bimestre = b.id_bimestre
    WHERE n.id_matricula = ? AND n.id_curso = ?
    ORDER BY b.numero_bimestre
");
$stmt->execute([$matricula_actual['id_matricula'], $curso['id_curso']]);
$notas = $stmt->fetchAll();

// Calcular estadísticas
$promedio_general = !empty($notas) ? array_sum(array_column($notas, 'nota')) / count($notas) : 0;
$total_tareas = count($tareas_recientes);
$tareas_pendientes = count(array_filter($tareas_recientes, fn($t) => $t['estado_final'] === 'PENDIENTE'));
$tareas_entregadas = count(array_filter($tareas_recientes, fn($t) => in_array($t['estado_final'], ['ENTREGADO', 'ENTREGADO_TARDE', 'CALIFICADO'])));

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Información del curso -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="card-title mb-1">
                                [<?= $curso['curso_codigo'] ?>] <?= htmlspecialchars($curso['curso_nombre']) ?>
                            </h4>
                            <p class="text-muted mb-0">
                                <?= $curso['numero_grado'] ?>° <?= $curso['letra_seccion'] ?> • 
                                <?= $curso['anio'] ?> • 
                                <?= htmlspecialchars($curso['area_nombre']) ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary"><?= $curso['creditos'] ?> créditos</span>
                            <span class="badge bg-info"><?= $curso['horas_semanales'] ?> hrs/sem</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($curso['curso_descripcion']): ?>
                        <div class="mb-4">
                            <h6>Descripción del Curso:</h6>
                            <p class="text-muted"><?= nl2br(htmlspecialchars($curso['curso_descripcion'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user"></i> Docente:</h6>
                            <p><?= htmlspecialchars($curso['docente_nombre']) ?></p>
                            <?php if ($curso['docente_email']): ?>
                                <p>
                                    <small class="text-muted">
                                        <i class="fas fa-envelope"></i> 
                                        <a href="mailto:<?= $curso['docente_email'] ?>"><?= $curso['docente_email'] ?></a>
                                    </small>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-chart-line"></i> Tu Rendimiento:</h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <h5 class="mb-0 text-<?= $promedio_general >= 14 ? 'success' : ($promedio_general >= 11 ? 'warning' : 'danger') ?>">
                                            <?= number_format($promedio_general, 1) ?>
                                        </h5>
                                        <small class="text-muted">Promedio</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <h5 class="mb-0 text-info"><?= count($notas) ?></h5>
                                        <small class="text-muted">Evaluaciones</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tareas recientes -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-tasks"></i> Tareas Recientes
                    </h6>
                    <a href="assignments.php?curso=<?= $curso['id_curso'] ?>" class="btn btn-sm btn-outline-primary">
                        Ver todas
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($tareas_recientes)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($tareas_recientes as $tarea): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($tarea['titulo']) ?></h6>
                                        <p class="mb-1 text-muted">
                                            <?= htmlspecialchars(substr($tarea['descripcion'], 0, 100)) ?>
                                            <?= strlen($tarea['descripcion']) > 100 ? '...' : '' ?>
                                        </p>
                                        <small class="text-muted">
                                            Vence: <?= date('d/m/Y', strtotime($tarea['fecha_limite'])) ?>
                                            <?php if ($tarea['dias_restantes'] >= 0): ?>
                                                (<?= $tarea['dias_restantes'] == 0 ? 'Hoy' : $tarea['dias_restantes'] . ' días' ?>)
                                            <?php else: ?>
                                                (Vencida)
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?= getEstadoTareaBadgeClass($tarea['estado_final']) ?> mb-1">
                                            <?= getEstadoTareaTexto($tarea['estado_final']) ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?= $tarea['puntos_maximos'] ?> pts</small>
                                        <?php if ($tarea['estado_final'] === 'CALIFICADO'): ?>
                                            <br>
                                            <span class="badge bg-<?= $tarea['calificacion'] >= ($tarea['puntos_maximos'] * 0.7) ? 'success' : 'danger' ?>">
                                                <?= $tarea['calificacion'] ?>/<?= $tarea['puntos_maximos'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-tasks fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No hay tareas asignadas aún</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Materiales recientes -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-folder-open"></i> Materiales de Estudio
                    </h6>
                    <a href="materials.php?curso=<?= $curso['id_curso'] ?>" class="btn btn-sm btn-outline-primary">
                        Ver todos
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($materiales_recientes)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($materiales_recientes as $material): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($material['titulo']) ?></h6>
                                        <?php if ($material['descripcion']): ?>
                                            <p class="mb-1 text-muted">
                                                <?= htmlspecialchars(substr($material['descripcion'], 0, 80)) ?>
                                                <?= strlen($material['descripcion']) > 80 ? '...' : '' ?>
                                            </p>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($material['fecha_publicacion'])) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?= getTipoMaterialBadgeClass($material['tipo_material']) ?> mb-2">
                                            <?= getTipoMaterialTexto($material['tipo_material']) ?>
                                        </span>
                                        <br>
                                        <?php if ($material['archivo_adjunto']): ?>
                                            <a href="../uploads/materials/<?= $material['archivo_adjunto'] ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php elseif ($material['enlace_externo']): ?>
                                            <a href="<?= htmlspecialchars($material['enlace_externo']) ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-folder-open fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No hay materiales disponibles aún</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Panel lateral -->
        <div class="col-lg-4">
            <!-- Estadísticas rápidas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-chart-pie"></i> Resumen del Curso
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-primary mb-0"><?= $total_tareas ?></h4>
                                <small class="text-muted">Total Tareas</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-warning mb-0"><?= $tareas_pendientes ?></h4>
                                <small class="text-muted">Pendientes</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h4 class="text-success mb-0"><?= $tareas_entregadas ?></h4>
                                <small class="text-muted">Entregadas</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h4 class="text-info mb-0"><?= count($materiales_recientes) ?></h4>
                                <small class="text-muted">Materiales</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notas por bimestre -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-clipboard-list"></i> Mis Calificaciones
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($notas)): ?>
                        <?php foreach ($notas as $nota): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong><?= $nota['numero_bimestre'] ?>° Bimestre</strong>
                                    <br>
                                    <small class="text-muted">
                                        <?= date('d/m', strtotime($nota['fecha_inicio'])) ?> - 
                                        <?= date('d/m', strtotime($nota['fecha_fin'])) ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge fs-6 bg-<?= $nota['nota'] >= 14 ? 'success' : ($nota['nota'] >= 11 ? 'warning' : 'danger') ?>">
                                        <?= $nota['nota'] ?>
                                    </span>
                                    <?php if ($nota['observaciones']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-comment" title="<?= htmlspecialchars($nota['observaciones']) ?>"></i>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>Promedio General:</strong>
                            <span class="badge fs-5 bg-<?= $promedio_general >= 14 ? 'success' : ($promedio_general >= 11 ? 'warning' : 'danger') ?>">
                                <?= number_format($promedio_general, 1) ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-clipboard-list fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No hay calificaciones registradas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-bolt"></i> Acciones Rápidas
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="assignments.php?curso=<?= $curso['id_curso'] ?>" class="btn btn-outline-primary">
                            <i class="fas fa-tasks"></i> Ver Todas las Tareas
                        </a>
                        <a href="materials.php?curso=<?= $curso['id_curso'] ?>" class="btn btn-outline-info">
                            <i class="fas fa-folder-open"></i> Materiales de Estudio
                        </a>
                        <?php if ($curso['docente_email']): ?>
                            <a href="mailto:<?= $curso['docente_email'] ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-envelope"></i> Contactar Docente
                            </a>
                        <?php endif; ?>
                        <a href="dashboard.php" class="btn btn-outline-dark">
                            <i class="fas fa-arrow-left"></i> Volver al Inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Funciones auxiliares
function getEstadoTareaBadgeClass($estado) {
    switch ($estado) {
        case 'PENDIENTE': return 'bg-secondary';
        case 'ENTREGADO': return 'bg-success';
        case 'CALIFICADO': return 'bg-info';
        case 'ENTREGADO_TARDE': return 'bg-warning';
        default: return 'bg-secondary';
    }
}

function getEstadoTareaTexto($estado) {
    switch ($estado) {
        case 'PENDIENTE': return 'Pendiente';
        case 'ENTREGADO': return 'Entregado';
        case 'CALIFICADO': return 'Calificado';
        case 'ENTREGADO_TARDE': return 'Tarde';
        default: return $estado;
    }
}

function getTipoMaterialBadgeClass($tipo) {
    $classes = [
        'presentacion' => 'bg-primary',
        'documento' => 'bg-info',
        'video' => 'bg-danger',
        'audio' => 'bg-warning',
        'imagen' => 'bg-success',
        'enlace' => 'bg-secondary',
        'otro' => 'bg-dark'
    ];
    return $classes[$tipo] ?? 'bg-secondary';
}

function getTipoMaterialTexto($tipo) {
    $textos = [
        'presentacion' => 'Presentación',
        'documento' => 'Documento',
        'video' => 'Video',
        'audio' => 'Audio',
        'imagen' => 'Imagen',
        'enlace' => 'Enlace',
        'otro' => 'Otro'
    ];
    return $textos[$tipo] ?? 'Desconocido';
}

include '../includes/footer.php'; 
?>