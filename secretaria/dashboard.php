<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'secretaria') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Panel Principal - Secretaría";

// Obtener datos de la secretaria
$stmt = $pdo->prepare("SELECT * FROM personal WHERE id_personal = ?");
$stmt->execute([$_SESSION['user_id']]);
$secretaria = $stmt->fetch();

// Obtener estadísticas del día
$hoy = date('Y-m-d');

// Matrículas del día
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM matriculas 
    WHERE DATE(fecha_matricula) = ? AND estado = 'ACTIVO'
");
$stmt->execute([$hoy]);
$matriculas_hoy = $stmt->fetch()['total'];

// Estudiantes registrados hoy
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM estudiantes 
    WHERE DATE(created_at) = ?
");
$stmt->execute([$hoy]);
$estudiantes_hoy = $stmt->fetch()['total'];

// Documentos pendientes de revisión (simulado)
$documentos_pendientes = 15; // En implementación real, vendría de una tabla de documentos

// Citas programadas para hoy (simulado)
$citas_hoy = 8; // En implementación real, vendría de una tabla de citas

// Últimas matrículas registradas
$stmt = $pdo->query("
    SELECT 
        e.nombres,
        e.apellido_paterno,
        e.apellido_materno,
        e.codigo_estudiante,
        g.numero_grado,
        s.letra_seccion,
        m.fecha_matricula,
        m.id_matricula
    FROM matriculas m
    JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    WHERE m.estado = 'ACTIVO'
    ORDER BY m.fecha_matricula DESC
    LIMIT 10
");
$ultimas_matriculas = $stmt->fetchAll();

// Estudiantes por grado (para estadísticas)
$stmt = $pdo->query("
    SELECT 
        g.numero_grado,
        g.descripcion,
        COUNT(DISTINCT m.id_estudiante) as total_estudiantes,
        g.capacidad_maxima
    FROM grados g
    LEFT JOIN secciones s ON g.id_grado = s.id_grado
    LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion AND m.estado = 'ACTIVO'
    GROUP BY g.id_grado
    ORDER BY g.numero_grado
");
$estudiantes_por_grado = $stmt->fetchAll();

// Tareas pendientes de secretaría
$tareas_pendientes = [
    [
        'tipo' => 'matricula',
        'descripcion' => 'Procesar documentos de matrícula pendientes',
        'cantidad' => 5,
        'urgencia' => 'alta',
        'icono' => 'fas fa-user-plus'
    ],
    [
        'tipo' => 'certificados',
        'descripcion' => 'Emitir certificados de estudios',
        'cantidad' => 8,
        'urgencia' => 'media',
        'icono' => 'fas fa-certificate'
    ],
    [
        'tipo' => 'constancias',
        'descripcion' => 'Generar constancias de matrícula',
        'cantidad' => 12,
        'urgencia' => 'baja',
        'icono' => 'fas fa-file-alt'
    ],
    [
        'tipo' => 'reportes',
        'descripcion' => 'Preparar reportes mensuales',
        'cantidad' => 3,
        'urgencia' => 'alta',
        'icono' => 'fas fa-chart-bar'
    ]
];

// Actividades recientes del sistema
$stmt = $pdo->query("
    SELECT 
        al.*,
        CASE 
            WHEN al.user_type = 'estudiante' THEN CONCAT('EST: ', e.nombres, ' ', e.apellido_paterno)
            WHEN al.user_type = 'docente' THEN CONCAT('DOC: ', p.nombres, ' ', p.apellido_paterno)
            WHEN al.user_type = 'director' THEN CONCAT('DIR: ', p.nombres, ' ', p.apellido_paterno)
            ELSE 'Usuario desconocido'
        END as usuario_nombre
    FROM activity_logs al
    LEFT JOIN estudiantes e ON al.user_id = e.id_estudiante AND al.user_type = 'estudiante'
    LEFT JOIN personal p ON al.user_id = p.id_personal AND al.user_type IN ('docente', 'director')
    WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY al.created_at DESC
    LIMIT 15
");
$actividades_recientes = $stmt->fetchAll();

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
                            <h2 class="mb-1">Panel de Secretaría</h2>
                            <p class="mb-0 opacity-75">
                                Bienvenida, <?= htmlspecialchars($secretaria['nombres'] . ' ' . $secretaria['apellido_paterno']) ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end align-items-center">
                                <div class="me-3">
                                    <small class="d-block opacity-75">Fecha de hoy</small>
                                    <h6 class="mb-0"><?= date('d/m/Y') ?></h6>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                                    <i class="fas fa-user-tie fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas del Día -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-user-plus fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $matriculas_hoy ?></h4>
                    <small class="text-muted">Matrículas Hoy</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $estudiantes_hoy ?></h4>
                    <small class="text-muted">Estudiantes Nuevos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-file-alt fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $documentos_pendientes ?></h4>
                    <small class="text-muted">Documentos Pendientes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-info mb-2">
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $citas_hoy ?></h4>
                    <small class="text-muted">Citas Programadas</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Contenido Principal -->
        <div class="col-lg-8">
            <!-- Tareas Pendientes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks text-warning"></i> Tareas Pendientes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($tareas_pendientes as $tarea): ?>
                            <?php 
                            $urgencia_class = '';
                            switch($tarea['urgencia']) {
                                case 'alta': $urgencia_class = 'border-danger'; break;
                                case 'media': $urgencia_class = 'border-warning'; break;
                                case 'baja': $urgencia_class = 'border-info'; break;
                            }
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-start <?= $urgencia_class ?> border-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="<?= $tarea['icono'] ?> me-2"></i>
                                                    <h6 class="mb-0"><?= htmlspecialchars($tarea['descripcion']) ?></h6>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-primary"><?= $tarea['cantidad'] ?> pendientes</span>
                                                    <span class="badge bg-<?= $tarea['urgencia'] === 'alta' ? 'danger' : ($tarea['urgencia'] === 'media' ? 'warning' : 'info') ?>">
                                                        <?= ucfirst($tarea['urgencia']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Últimas Matrículas -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list text-primary"></i> Últimas Matrículas
                    </h5>
                    <a href="student_enrollment.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($ultimas_matriculas)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código</th>
                                        <th>Estudiante</th>
                                        <th>Grado/Sección</th>
                                        <th>Fecha Matrícula</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimas_matriculas as $matricula): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($matricula['codigo_estudiante']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($matricula['nombres'] . ' ' . 
                                                    $matricula['apellido_paterno'] . ' ' . 
                                                    $matricula['apellido_materno']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= $matricula['numero_grado'] ?>°<?= $matricula['letra_seccion'] ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($matricula['fecha_matricula'])) ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="verDetallesMatricula(<?= $matricula['id_matricula'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" 
                                                            onclick="imprimirConstancia(<?= $matricula['id_matricula'] ?>)">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No hay matrículas registradas</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Distribución por Grado -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie text-info"></i> Distribución de Estudiantes por Grado
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($estudiantes_por_grado as $grado): ?>
                            <?php 
                            $porcentaje_ocupacion = $grado['capacidad_maxima'] > 0 ? 
                                ($grado['total_estudiantes'] / $grado['capacidad_maxima']) * 100 : 0;
                            $color_progreso = $porcentaje_ocupacion >= 90 ? 'danger' : 
                                            ($porcentaje_ocupacion >= 75 ? 'warning' : 'success');
                            ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="card-title"><?= $grado['numero_grado'] ?>° Grado</h6>
                                        <p class="card-text">
                                            <small class="text-muted"><?= htmlspecialchars($grado['descripcion']) ?></small>
                                        </p>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Matriculados: <?= $grado['total_estudiantes'] ?></span>
                                            <span>Capacidad: <?= $grado['capacidad_maxima'] ?></span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-<?= $color_progreso ?>" 
                                                 style="width: <?= $porcentaje_ocupacion ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= round($porcentaje_ocupacion, 1) ?>% ocupado</small>
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
            <!-- Acceso Rápido -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-bolt text-primary"></i> Acceso Rápido
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="student_enrollment.php" class="btn btn-outline-success">
                            <i class="fas fa-user-plus"></i> Nueva Matrícula
                        </a>
                        <a href="student_search.php" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Buscar Estudiante
                        </a>
                        <a href="certificates.php" class="btn btn-outline-info">
                            <i class="fas fa-certificate"></i> Generar Certificado
                        </a>
                        <a href="constancias.php" class="btn btn-outline-warning">
                            <i class="fas fa-file-alt"></i> Emitir Constancia
                        </a>
                        <a href="reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-chart-bar"></i> Reportes
                        </a>
                    </div>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-clock text-info"></i> Actividad Reciente (24h)
                    </h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (!empty($actividades_recientes)): ?>
                        <?php foreach ($actividades_recientes as $actividad): ?>
                            <div class="d-flex align-items-start mb-3">
                                <div class="bg-light rounded-circle p-2 me-3">
                                    <i class="fas fa-user fa-sm"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <small class="d-block">
                                        <strong><?= htmlspecialchars($actividad['usuario_nombre']) ?></strong>
                                    </small>
                                    <small class="text-muted d-block">
                                        <?= htmlspecialchars($actividad['action']) ?>
                                    </small>
                                    <small class="text-muted">
                                        <?= date('H:i', strtotime($actividad['created_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No hay actividad reciente</p>
                    <?php endif; ?>
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
    border-left-width: 3px !important;
}

.progress {
    border-radius: 4px;
}
</style>

<script>
function verDetallesMatricula(idMatricula) {
    // Implementar modal o redirección para ver detalles
    console.log('Ver detalles de matrícula:', idMatricula);
}

function imprimirConstancia(idMatricula) {
    // Implementar generación e impresión de constancia
    console.log('Imprimir constancia de matrícula:', idMatricula);
}
</script>

<?php include '../includes/footer.php'; ?>