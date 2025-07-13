<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'docente') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Panel Principal - Docente";

// Obtener datos del docente
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        COUNT(DISTINCT a.id_asignacion) as total_asignaciones,
        COUNT(DISTINCT s.id_seccion) as total_secciones
    FROM personal p
    LEFT JOIN asignaciones a ON p.id_personal = a.id_personal AND a.estado = 'ACTIVO'
    LEFT JOIN secciones s ON a.id_seccion = s.id_seccion
    WHERE p.id_personal = ?
    GROUP BY p.id_personal
");
$stmt->execute([$_SESSION['user_id']]);
$docente = $stmt->fetch();

if (!$docente) {
    header('Location: ../auth/login.php?error=acceso_denegado');
    exit();
}

// Obtener cursos asignados
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        a.id_asignacion,
        s.letra_seccion,
        g.numero_grado,
        g.descripcion as grado_descripcion,
        aa.anio,
        COUNT(DISTINCT m.id_matricula) as total_estudiantes,
        COUNT(DISTINCT t.id_tarea) as total_tareas,
        COUNT(DISTINCT et.id_entrega) as entregas_pendientes
    FROM asignaciones a
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos aa ON a.id_anio = aa.id_anio
    LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion AND m.estado = 'ACTIVO'
    LEFT JOIN tareas t ON a.id_asignacion = t.id_asignacion AND t.estado = 'ACTIVA'
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea AND et.estado = 'ENTREGADO'
    WHERE a.id_personal = ? AND a.estado = 'ACTIVO' AND aa.estado = 'ACTIVO'
    GROUP BY a.id_asignacion
    ORDER BY g.numero_grado, s.letra_seccion, c.nombre
");
$stmt->execute([$_SESSION['user_id']]);
$cursos_asignados = $stmt->fetchAll();

// Obtener estadísticas generales
$total_estudiantes = 0;
$total_tareas_activas = 0;
$total_entregas_pendientes = 0;

foreach ($cursos_asignados as $curso) {
    $total_estudiantes += $curso['total_estudiantes'];
    $total_tareas_activas += $curso['total_tareas'];
    $total_entregas_pendientes += $curso['entregas_pendientes'];
}

// Obtener tareas próximas a vencer
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        s.letra_seccion,
        g.numero_grado,
        COUNT(et.id_entrega) as entregas_recibidas,
        COUNT(DISTINCT m.id_matricula) as total_estudiantes_seccion,
        DATEDIFF(t.fecha_limite, NOW()) as dias_restantes
    FROM tareas t
    JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion AND m.estado = 'ACTIVO'
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea
    WHERE a.id_personal = ? AND t.estado = 'ACTIVA'
    AND t.fecha_limite BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    GROUP BY t.id_tarea
    ORDER BY t.fecha_limite ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$tareas_proximas = $stmt->fetchAll();

// Obtener entregas recientes para calificar
$stmt = $pdo->prepare("
    SELECT 
        et.*,
        t.titulo as tarea_titulo,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        CONCAT(e.nombres, ' ', e.apellido_paterno) as estudiante_nombre,
        e.codigo_estudiante,
        s.letra_seccion,
        g.numero_grado
    FROM entregas_tareas et
    JOIN tareas t ON et.id_tarea = t.id_tarea
    JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN matriculas m ON et.id_matricula = m.id_matricula
    JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    WHERE a.id_personal = ? AND et.estado = 'ENTREGADO'
    ORDER BY et.fecha_entrega DESC
    LIMIT 8
");
$stmt->execute([$_SESSION['user_id']]);
$entregas_recientes = $stmt->fetchAll();

// Obtener materiales recién publicados
$stmt = $pdo->prepare("
    SELECT 
        mat.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        s.letra_seccion,
        g.numero_grado
    FROM materiales mat
    JOIN asignaciones a ON mat.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    WHERE a.id_personal = ?
    ORDER BY mat.fecha_publicacion DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$materiales_recientes = $stmt->fetchAll();

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
                            <h2 class="mb-1">¡Bienvenido, Prof. <?= htmlspecialchars($docente['nombres']) ?>!</h2>
                            <p class="mb-0 opacity-75">
                                Panel de Control Docente • <?= count($cursos_asignados) ?> cursos asignados
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end align-items-center">
                                <div class="me-3">
                                    <small class="d-block opacity-75">Estudiantes bajo tu cargo</small>
                                    <h3 class="mb-0"><?= $total_estudiantes ?></h3>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                                    <i class="fas fa-chalkboard-teacher fa-2x"></i>
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
                        <i class="fas fa-book fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= count($cursos_asignados) ?></h4>
                    <small class="text-muted">Cursos Asignados</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $total_estudiantes ?></h4>
                    <small class="text-muted">Estudiantes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-tasks fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $total_tareas_activas ?></h4>
                    <small class="text-muted">Tareas Activas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-danger mb-2">
                        <i class="fas fa-clipboard-check fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $total_entregas_pendientes ?></h4>
                    <small class="text-muted">Por Calificar</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Contenido Principal -->
        <div class="col-lg-8">
            <!-- Mis Cursos -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-book text-primary"></i> Mis Cursos
                    </h5>
                    <a href="courses.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($cursos_asignados)): ?>
                        <div class="row">
                            <?php foreach ($cursos_asignados as $curso): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0">
                                                    <a href="course_detail.php?id=<?= $curso['id_asignacion'] ?>" class="text-decoration-none">
                                                        [<?= $curso['codigo'] ?>] <?= htmlspecialchars($curso['nombre']) ?>
                                                    </a>
                                                </h6>
                                                <span class="badge bg-info"><?= $curso['numero_grado'] ?>°<?= $curso['letra_seccion'] ?></span>
                                            </div>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <?= $curso['grado_descripcion'] ?> • <?= $curso['horas_semanales'] ?> hrs/semana
                                                </small>
                                            </p>
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Estudiantes</small>
                                                    <strong><?= $curso['total_estudiantes'] ?></strong>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Tareas</small>
                                                    <strong class="text-primary"><?= $curso['total_tareas'] ?></strong>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Pendientes</small>
                                                    <strong class="text-danger"><?= $curso['entregas_pendientes'] ?></strong>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <div class="btn-group w-100" role="group">
                                                    <a href="assignments.php?curso=<?= $curso['id_asignacion'] ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-tasks"></i> Tareas
                                                    </a>
                                                    <a href="materials.php?curso=<?= $curso['id_asignacion'] ?>" 
                                                       class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-folder"></i> Materiales
                                                    </a>
                                                    <a href="grade_assignments.php?curso=<?= $curso['id_asignacion'] ?>" 
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-star"></i> Calificar
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                            <h5>No tienes cursos asignados</h5>
                            <p class="text-muted">Contacta con la administración para obtener asignaciones.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Entregas Recientes para Calificar -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-clipboard-check text-danger"></i> Entregas por Calificar
                    </h5>
                    <a href="grade_assignments.php" class="btn btn-sm btn-outline-danger">Ver todas</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($entregas_recientes)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Tarea</th>
                                        <th>Curso</th>
                                        <th>Fecha Entrega</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entregas_recientes as $entrega): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($entrega['estudiante_nombre']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= $entrega['codigo_estudiante'] ?> • 
                                                        <?= $entrega['numero_grado'] ?>°<?= $entrega['letra_seccion'] ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($entrega['tarea_titulo']) ?></strong>
                                            </td>
                                            <td>
                                                <small>
                                                    [<?= $entrega['curso_codigo'] ?>] 
                                                    <?= htmlspecialchars($entrega['curso_nombre']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small><?= date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])) ?></small>
                                            </td>
                                            <td>
                                                <a href="grade_assignments.php?entrega=<?= $entrega['id_entrega'] ?>" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="fas fa-star"></i> Calificar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>¡Todo al día!</h5>
                            <p class="text-muted">No hay entregas pendientes de calificación.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Tareas Próximas a Vencer -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-clock text-warning"></i> Tareas Próximas a Vencer
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($tareas_proximas)): ?>
                        <?php foreach ($tareas_proximas as $tarea): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning text-white rounded p-2 me-3">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <a href="assignment_detail.php?id=<?= $tarea['id_tarea'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($tarea['titulo']) ?>
                                        </a>
                                    </h6>
                                    <small class="text-muted">
                                        [<?= $tarea['curso_codigo'] ?>] <?= $tarea['numero_grado'] ?>°<?= $tarea['letra_seccion'] ?><br>
                                        Vence: <?= date('d/m/Y H:i', strtotime($tarea['fecha_limite'])) ?>
                                    </small>
                                    <div class="mt-1">
                                        <span class="badge bg-info">
                                            <?= $tarea['entregas_recibidas'] ?>/<?= $tarea['total_estudiantes_seccion'] ?> entregas
                                        </span>
                                        <?php if ($tarea['dias_restantes'] <= 1): ?>
                                            <span class="badge bg-danger">
                                                <?= $tarea['dias_restantes'] == 0 ? 'Vence hoy' : 'Vence mañana' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <?= $tarea['dias_restantes'] ?> días
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No hay tareas próximas a vencer</p>
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
                                        [<?= $material['curso_codigo'] ?>] <?= $material['numero_grado'] ?>°<?= $material['letra_seccion'] ?><br>
                                        <?= date('d/m/Y', strtotime($material['fecha_publicacion'])) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No hay materiales recientes</p>
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
                            <i class="fas fa-plus"></i> Crear Tarea
                        </a>
                        <a href="materials.php" class="btn btn-outline-info">
                            <i class="fas fa-upload"></i> Subir Material
                        </a>
                        <a href="grade_assignments.php" class="btn btn-outline-success">
                            <i class="fas fa-star"></i> Calificar Entregas
                        </a>
                        <a href="attendance.php" class="btn btn-outline-warning">
                            <i class="fas fa-clipboard-list"></i> Registro de Asistencia
                        </a>
                        <a href="reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-chart-bar"></i> Generar Reportes
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

.btn-group .btn {
    font-size: 0.8rem;
}

.table td, .table th {
    vertical-align: middle;
}
</style>

<?php include '../includes/footer.php'; ?>