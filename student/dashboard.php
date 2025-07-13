<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Panel Principal - Estudiante";

// Obtener datos del estudiante y matrícula
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        m.fecha_matricula,
        g.numero_grado,
        g.descripcion as grado_descripcion,
        s.letra_seccion,
        a.anio,
        a.estado as anio_estado
    FROM estudiantes e
    JOIN matriculas m ON e.id_estudiante = m.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos a ON m.id_anio = a.id_anio
    WHERE e.id_estudiante = ? AND m.estado = 'ACTIVO' AND a.estado = 'ACTIVO'
");
$stmt->execute([$_SESSION['user_id']]);
$estudiante = $stmt->fetch();

if (!$estudiante) {
    header('Location: ../auth/login.php?error=sin_matricula');
    exit();
}

// Obtener estadísticas académicas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT n.id_nota) as total_notas,
        AVG(n.nota) as promedio_general,
        COUNT(CASE WHEN n.nota >= 14 THEN 1 END) as notas_aprobadas,
        COUNT(CASE WHEN n.nota < 11 THEN 1 END) as notas_desaprobadas
    FROM matriculas m
    LEFT JOIN notas n ON m.id_matricula = n.id_matricula
    WHERE m.id_estudiante = ? AND m.estado = 'ACTIVO'
");
$stmt->execute([$_SESSION['user_id']]);
$estadisticas_notas = $stmt->fetch();

// Obtener tareas pendientes
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        CONCAT(p.nombres, ' ', p.apellido_paterno) as docente_nombre,
        et.fecha_entrega,
        et.estado as estado_entrega,
        DATEDIFF(t.fecha_limite, NOW()) as dias_restantes
    FROM tareas t
    JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN personal p ON a.id_personal = p.id_personal
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea 
        AND et.id_matricula = (
            SELECT id_matricula FROM matriculas 
            WHERE id_estudiante = ? AND estado = 'ACTIVO' LIMIT 1
        )
    WHERE a.id_seccion = ? AND a.id_anio = ? 
    AND t.estado = 'ACTIVA'
    AND (et.id_entrega IS NULL OR et.estado != 'CALIFICADO')
    ORDER BY t.fecha_limite ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $estudiante['id_seccion'], $estudiante['id_anio']]);
$tareas_pendientes = $stmt->fetchAll();

// Obtener materiales recientes
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo
    FROM materiales m
    JOIN asignaciones a ON m.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    WHERE a.id_seccion = ? AND a.id_anio = ? 
    AND m.es_visible = 1
    ORDER BY m.fecha_publicacion DESC
    LIMIT 5
");
$stmt->execute([$estudiante['id_seccion'], $estudiante['id_anio']]);
$materiales_recientes = $stmt->fetchAll();

// Obtener cursos del estudiante
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        a.id_asignacion,
        CONCAT(p.nombres, ' ', p.apellido_paterno) as docente_nombre,
        COUNT(DISTINCT t.id_tarea) as total_tareas,
        COUNT(DISTINCT et.id_entrega) as tareas_entregadas,
        AVG(n.nota) as promedio_curso
    FROM cursos c
    JOIN asignaciones a ON c.id_curso = a.id_curso
    JOIN personal p ON a.id_personal = p.id_personal
    LEFT JOIN tareas t ON a.id_asignacion = t.id_asignacion
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea 
        AND et.id_matricula = (
            SELECT id_matricula FROM matriculas 
            WHERE id_estudiante = ? AND estado = 'ACTIVO' LIMIT 1
        )
    LEFT JOIN notas n ON c.id_curso = n.id_curso 
        AND n.id_matricula = (
            SELECT id_matricula FROM matriculas 
            WHERE id_estudiante = ? AND estado = 'ACTIVO' LIMIT 1
        )
    WHERE a.id_seccion = ? AND a.id_anio = ? AND a.estado = 'ACTIVO'
    GROUP BY c.id_curso
    ORDER BY c.nombre
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $estudiante['id_seccion'], $estudiante['id_anio']]);
$cursos = $stmt->fetchAll();

// Obtener próximas fechas importantes
$stmt = $pdo->prepare("
    SELECT 
        'tarea' as tipo,
        t.titulo as evento,
        t.fecha_limite as fecha,
        c.nombre as curso
    FROM tareas t
    JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    WHERE a.id_seccion = ? AND a.id_anio = ?
    AND t.fecha_limite >= NOW()
    AND t.estado = 'ACTIVA'
    ORDER BY t.fecha_limite ASC
    LIMIT 3
");
$stmt->execute([$estudiante['id_seccion'], $estudiante['id_anio']]);
$proximos_eventos = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <!-- Header del Dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-1">¡Bienvenido, <?= htmlspecialchars($estudiante['nombres']) ?>!</h2>
                            <p class="mb-0 opacity-75">
                                <?= $estudiante['numero_grado'] ?>° Grado "<?= $estudiante['letra_seccion'] ?>" • 
                                Año Académico <?= $estudiante['anio'] ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end align-items-center">
                                <div class="me-3">
                                    <small class="d-block opacity-75">Tu promedio actual</small>
                                    <h3 class="mb-0">
                                        <?= number_format($estadisticas_notas['promedio_general'] ?? 0, 1) ?>
                                    </h3>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                                    <i class="fas fa-graduation-cap fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas Rápidas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-tasks fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= count($tareas_pendientes) ?></h4>
                    <small class="text-muted">Tareas Pendientes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $estadisticas_notas['notas_aprobadas'] ?? 0 ?></h4>
                    <small class="text-muted">Notas Aprobadas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-info mb-2">
                        <i class="fas fa-book fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= count($cursos) ?></h4>
                    <small class="text-muted">Cursos Activos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-folder-open fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= count($materiales_recientes) ?></h4>
                    <small class="text-muted">Materiales Nuevos</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Contenido Principal -->
        <div class="col-lg-8">
            <!-- Tareas Pendientes -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-tasks text-primary"></i> Tareas Pendientes
                    </h6>
                    <a href="assignments.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($tareas_pendientes)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($tareas_pendientes as $tarea): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="submit_assignment.php?id=<?= $tarea['id_tarea'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($tarea['titulo']) ?>
                                                </a>
                                            </h6>
                                            <p class="mb-1 text-muted">
                                                [<?= $tarea['curso_codigo'] ?>] <?= htmlspecialchars($tarea['curso_nombre']) ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($tarea['docente_nombre']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="mb-1">
                                                <?php 
                                                $urgencia = '';
                                                $clase = '';
                                                if ($tarea['dias_restantes'] < 0) {
                                                    $urgencia = 'Vencida';
                                                    $clase = 'bg-danger';
                                                } elseif ($tarea['dias_restantes'] == 0) {
                                                    $urgencia = 'Vence hoy';
                                                    $clase = 'bg-warning';
                                                } elseif ($tarea['dias_restantes'] == 1) {
                                                    $urgencia = 'Vence mañana';
                                                    $clase = 'bg-warning';
                                                } else {
                                                    $urgencia = $tarea['dias_restantes'] . ' días';
                                                    $clase = 'bg-info';
                                                }
                                                ?>
                                                <span class="badge <?= $clase ?>"><?= $urgencia ?></span>
                                            </div>
                                            <small class="text-muted d-block">
                                                <?= date('d/m/Y', strtotime($tarea['fecha_limite'])) ?>
                                            </small>
                                            <?php if ($tarea['estado_entrega']): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-check"></i> Entregada
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>¡Excelente trabajo!</h5>
                            <p class="text-muted">No tienes tareas pendientes por entregar.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mis Cursos -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-book text-success"></i> Mis Cursos
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($cursos as $curso): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <a href="course_detail.php?id=<?= $curso['id_asignacion'] ?>" class="text-decoration-none">
                                                [<?= $curso['codigo'] ?>] <?= htmlspecialchars($curso['nombre']) ?>
                                            </a>
                                        </h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($curso['docente_nombre']) ?><br>
                                                <i class="fas fa-clock"></i> <?= $curso['horas_semanales'] ?> hrs/semana
                                            </small>
                                        </p>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small class="text-muted d-block">Tareas</small>
                                                <strong><?= $curso['total_tareas'] ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Entregadas</small>
                                                <strong class="text-success"><?= $curso['tareas_entregadas'] ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Promedio</small>
                                                <strong class="text-<?= ($curso['promedio_curso'] >= 14) ? 'success' : (($curso['promedio_curso'] >= 11) ? 'warning' : 'danger') ?>">
                                                    <?= $curso['promedio_curso'] ? number_format($curso['promedio_curso'], 1) : '-' ?>
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Próximos Eventos -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-calendar text-warning"></i> Próximas Fechas
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($proximos_eventos)): ?>
                        <?php foreach ($proximos_eventos as $evento): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary text-white rounded p-2 me-3">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= htmlspecialchars($evento['evento']) ?></h6>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($evento['curso']) ?><br>
                                        <?= date('d/m/Y H:i', strtotime($evento['fecha'])) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No hay eventos próximos</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Materiales Recientes -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-folder-open text-info"></i> Materiales Recientes
                    </h6>
                    <a href="materials.php" class="btn btn-sm btn-outline-info">Ver todos</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($materiales_recientes)): ?>
                        <?php foreach ($materiales_recientes as $material): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-info text-white rounded p-2 me-3">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?= htmlspecialchars(substr($material['titulo'], 0, 30)) ?>
                                        <?= strlen($material['titulo']) > 30 ? '...' : '' ?>
                                    </h6>
                                    <small class="text-muted">
                                        [<?= $material['curso_codigo'] ?>] <?= htmlspecialchars($material['curso_nombre']) ?><br>
                                        <?= date('d/m/Y', strtotime($material['fecha_publicacion'])) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No hay materiales nuevos</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acceso Rápido -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-bolt text-primary"></i> Acceso Rápido
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="assignments.php" class="btn btn-outline-primary">
                            <i class="fas fa-tasks"></i> Mis Tareas
                        </a>
                        <a href="materials.php" class="btn btn-outline-info">
                            <i class="fas fa-folder-open"></i> Materiales de Estudio
                        </a>
                        <a href="grades.php" class="btn btn-outline-success">
                            <i class="fas fa-chart-line"></i> Mis Calificaciones
                        </a>
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user"></i> Mi Perfil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.list-group-item {
    transition: background-color 0.2s ease-in-out;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}
</style>

<?php include '../includes/footer.php'; ?>