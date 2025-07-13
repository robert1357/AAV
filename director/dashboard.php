<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'director') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Panel Principal - Director";

// Obtener datos del director
$stmt = $pdo->prepare("SELECT * FROM personal WHERE id_personal = ?");
$stmt->execute([$_SESSION['user_id']]);
$director = $stmt->fetch();

// Obtener estadísticas generales
$stats = [];

// Total de estudiantes activos
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM estudiantes e 
    JOIN matriculas m ON e.id_estudiante = m.id_estudiante 
    WHERE m.estado = 'ACTIVO'
");
$stats['total_estudiantes'] = $stmt->fetch()['total'];

// Total de docentes activos
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM personal 
    WHERE tipo_personal = 'DOCENTE' AND estado = 'ACTIVO'
");
$stats['total_docentes'] = $stmt->fetch()['total'];

// Total de cursos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cursos WHERE estado = 'ACTIVO'");
$stats['total_cursos'] = $stmt->fetch()['total'];

// Total de secciones activas
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM secciones s 
    JOIN anios_academicos a ON s.id_anio = a.id_anio 
    WHERE a.estado = 'ACTIVO'
");
$stats['total_secciones'] = $stmt->fetch()['total'];

// Estudiantes por grado
$stmt = $pdo->query("
    SELECT 
        g.numero_grado,
        g.descripcion,
        COUNT(DISTINCT m.id_estudiante) as total_estudiantes
    FROM grados g
    LEFT JOIN secciones s ON g.id_grado = s.id_grado
    LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion AND m.estado = 'ACTIVO'
    GROUP BY g.id_grado
    ORDER BY g.numero_grado
");
$estudiantes_por_grado = $stmt->fetchAll();

// Asistencia reciente
$stmt = $pdo->query("
    SELECT 
        DATE(fecha_registro) as fecha,
        COUNT(*) as total_registros,
        SUM(CASE WHEN estado = 'PRESENTE' THEN 1 ELSE 0 END) as presentes,
        SUM(CASE WHEN estado = 'AUSENTE' THEN 1 ELSE 0 END) as ausentes,
        SUM(CASE WHEN estado = 'TARDANZA' THEN 1 ELSE 0 END) as tardanzas
    FROM asistencias 
    WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha_registro)
    ORDER BY fecha DESC
    LIMIT 7
");
$asistencia_semanal = $stmt->fetchAll();

// Últimas matrículas
$stmt = $pdo->query("
    SELECT 
        e.nombres,
        e.apellido_paterno,
        e.apellido_materno,
        e.codigo_estudiante,
        g.numero_grado,
        s.letra_seccion,
        m.fecha_matricula
    FROM matriculas m
    JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    WHERE m.estado = 'ACTIVO'
    ORDER BY m.fecha_matricula DESC
    LIMIT 10
");
$ultimas_matriculas = $stmt->fetchAll();

// Alertas del sistema
$alertas = [];

// Estudiantes sin matrícula
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM estudiantes e 
    LEFT JOIN matriculas m ON e.id_estudiante = m.id_estudiante AND m.estado = 'ACTIVO'
    WHERE m.id_matricula IS NULL AND e.estado = 'ACTIVO'
");
$sin_matricula = $stmt->fetch()['total'];
if ($sin_matricula > 0) {
    $alertas[] = [
        'tipo' => 'warning',
        'mensaje' => "$sin_matricula estudiantes sin matrícula activa",
        'icono' => 'fas fa-exclamation-triangle'
    ];
}

// Docentes sin asignaciones
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM personal p 
    LEFT JOIN asignaciones a ON p.id_personal = a.id_personal AND a.estado = 'ACTIVO'
    WHERE p.tipo_personal = 'DOCENTE' AND p.estado = 'ACTIVO' AND a.id_asignacion IS NULL
");
$docentes_sin_asignacion = $stmt->fetch()['total'];
if ($docentes_sin_asignacion > 0) {
    $alertas[] = [
        'tipo' => 'info',
        'mensaje' => "$docentes_sin_asignacion docentes sin asignaciones",
        'icono' => 'fas fa-info-circle'
    ];
}

// Tareas vencidas sin calificar
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM entregas_tareas et
    JOIN tareas t ON et.id_tarea = t.id_tarea
    WHERE et.estado = 'ENTREGADO' AND t.fecha_limite < NOW()
");
$tareas_vencidas = $stmt->fetch()['total'];
if ($tareas_vencidas > 0) {
    $alertas[] = [
        'tipo' => 'danger',
        'mensaje' => "$tareas_vencidas entregas vencidas sin calificar",
        'icono' => 'fas fa-clock'
    ];
}

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
                            <h2 class="mb-1">Panel de Dirección</h2>
                            <p class="mb-0 opacity-75">
                                Bienvenido, <?= htmlspecialchars($director['nombres'] . ' ' . $director['apellido_paterno']) ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end align-items-center">
                                <div class="me-3">
                                    <small class="d-block opacity-75">Año Académico</small>
                                    <h3 class="mb-0"><?= date('Y') ?></h3>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                                    <i class="fas fa-school fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= number_format($stats['total_estudiantes']) ?></h4>
                    <small class="text-muted">Estudiantes Activos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= number_format($stats['total_docentes']) ?></h4>
                    <small class="text-muted">Docentes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-info mb-2">
                        <i class="fas fa-book fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= number_format($stats['total_cursos']) ?></h4>
                    <small class="text-muted">Cursos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= number_format($stats['total_secciones']) ?></h4>
                    <small class="text-muted">Secciones</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Contenido Principal -->
        <div class="col-lg-8">
            <!-- Estudiantes por Grado -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar text-primary"></i> Distribución de Estudiantes por Grado
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($estudiantes_por_grado as $grado): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-start border-primary border-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= $grado['numero_grado'] ?>° Grado</h6>
                                                <small class="text-muted"><?= htmlspecialchars($grado['descripcion']) ?></small>
                                            </div>
                                            <div class="text-end">
                                                <h4 class="mb-0 text-primary"><?= $grado['total_estudiantes'] ?></h4>
                                                <small class="text-muted">estudiantes</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Asistencia Semanal -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-check text-success"></i> Asistencia de los Últimos 7 Días
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($asistencia_semanal)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Presentes</th>
                                        <th class="text-center">Ausentes</th>
                                        <th class="text-center">Tardanzas</th>
                                        <th class="text-center">% Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($asistencia_semanal as $dia): ?>
                                        <?php 
                                        $porcentaje = $dia['total_registros'] > 0 ? 
                                            ($dia['presentes'] / $dia['total_registros']) * 100 : 0;
                                        $clase_porcentaje = $porcentaje >= 90 ? 'success' : 
                                                          ($porcentaje >= 80 ? 'warning' : 'danger');
                                        ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($dia['fecha'])) ?></td>
                                            <td class="text-center"><?= $dia['total_registros'] ?></td>
                                            <td class="text-center text-success"><?= $dia['presentes'] ?></td>
                                            <td class="text-center text-danger"><?= $dia['ausentes'] ?></td>
                                            <td class="text-center text-warning"><?= $dia['tardanzas'] ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $clase_porcentaje ?>">
                                                    <?= number_format($porcentaje, 1) ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No hay datos de asistencia recientes</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimas Matrículas -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus text-info"></i> Últimas Matrículas
                    </h5>
                    <a href="student_management.php" class="btn btn-sm btn-outline-info">Ver todas</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($ultimas_matriculas)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Código</th>
                                        <th>Grado/Sección</th>
                                        <th>Fecha Matrícula</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimas_matriculas as $matricula): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($matricula['nombres'] . ' ' . 
                                                    $matricula['apellido_paterno'] . ' ' . 
                                                    $matricula['apellido_materno']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($matricula['codigo_estudiante']) ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= $matricula['numero_grado'] ?>°<?= $matricula['letra_seccion'] ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($matricula['fecha_matricula'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No hay matrículas recientes</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Alertas del Sistema -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-exclamation-circle text-warning"></i> Alertas del Sistema
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($alertas)): ?>
                        <?php foreach ($alertas as $alerta): ?>
                            <div class="alert alert-<?= $alerta['tipo'] ?> py-2 mb-2">
                                <i class="<?= $alerta['icono'] ?>"></i>
                                <?= $alerta['mensaje'] ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-success py-2">
                            <i class="fas fa-check-circle"></i>
                            Todo funciona correctamente
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acceso Rápido -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-bolt text-primary"></i> Gestión Académica
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="student_management.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-graduate"></i> Gestión de Estudiantes
                        </a>
                        <a href="teacher_management.php" class="btn btn-outline-success">
                            <i class="fas fa-chalkboard-teacher"></i> Gestión de Docentes
                        </a>
                        <a href="course_management.php" class="btn btn-outline-info">
                            <i class="fas fa-book"></i> Gestión de Cursos
                        </a>
                        <a href="section_management.php" class="btn btn-outline-warning">
                            <i class="fas fa-users"></i> Gestión de Secciones
                        </a>
                    </div>
                </div>
            </div>

            <!-- Reportes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-line text-info"></i> Reportes
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="reports.php?type=attendance" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-calendar-check"></i> Reporte de Asistencia
                        </a>
                        <a href="reports.php?type=grades" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-star"></i> Reporte de Calificaciones
                        </a>
                        <a href="reports.php?type=enrollment" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-user-plus"></i> Reporte de Matrículas
                        </a>
                        <a href="reports.php?type=general" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-alt"></i> Reporte General
                        </a>
                    </div>
                </div>
            </div>

            <!-- Información del Sistema -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle text-secondary"></i> Información del Sistema
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Versión:</small>
                        <small>v1.0.0</small>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Último acceso:</small>
                        <small><?= date('d/m/Y H:i') ?></small>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Estado del sistema:</small>
                        <small class="text-success">Operativo</small>
                    </div>
                    <hr>
                    <div class="text-center">
                        <a href="system_settings.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-cog"></i> Configuración
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

.border-start {
    border-left-width: 4px !important;
}
</style>

<?php include '../includes/footer.php'; ?>