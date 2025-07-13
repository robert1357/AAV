<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Mis Calificaciones";

// Obtener datos del estudiante
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        m.id_matricula,
        g.numero_grado,
        s.letra_seccion,
        a.anio
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

// Obtener calificaciones por curso
$stmt = $pdo->prepare("
    SELECT 
        c.id_curso,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        c.horas_semanales,
        CONCAT(p.nombres, ' ', p.apellido_paterno) as docente_nombre,
        n.nota,
        n.tipo_evaluacion,
        n.fecha_evaluacion,
        n.observaciones,
        COUNT(n.id_nota) as total_notas,
        AVG(n.nota) as promedio_curso,
        MIN(n.nota) as nota_minima,
        MAX(n.nota) as nota_maxima
    FROM cursos c
    JOIN asignaciones a ON c.id_curso = a.id_curso
    JOIN personal p ON a.id_personal = p.id_personal
    LEFT JOIN notas n ON c.id_curso = n.id_curso AND n.id_matricula = ?
    WHERE a.id_seccion = ? AND a.id_anio = ? AND a.estado = 'ACTIVO'
    GROUP BY c.id_curso, c.nombre, c.codigo, c.horas_semanales, p.nombres, p.apellido_paterno
    ORDER BY c.nombre
");
$stmt->execute([$estudiante['id_matricula'], $estudiante['id_seccion'], $estudiante['id_anio']]);
$cursos_notas = $stmt->fetchAll();

// Obtener todas las notas detalladas
$stmt = $pdo->prepare("
    SELECT 
        n.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo
    FROM notas n
    JOIN cursos c ON n.id_curso = c.id_curso
    WHERE n.id_matricula = ?
    ORDER BY n.fecha_evaluacion DESC, c.nombre
");
$stmt->execute([$estudiante['id_matricula']]);
$todas_notas = $stmt->fetchAll();

// Calcular estadísticas generales
$total_notas = count($todas_notas);
$promedio_general = 0;
$notas_aprobadas = 0;
$notas_desaprobadas = 0;

if ($total_notas > 0) {
    $suma_notas = array_sum(array_column($todas_notas, 'nota'));
    $promedio_general = $suma_notas / $total_notas;
    
    foreach ($todas_notas as $nota) {
        if ($nota['nota'] >= 14) {
            $notas_aprobadas++;
        } elseif ($nota['nota'] < 11) {
            $notas_desaprobadas++;
        }
    }
}

// Obtener estadísticas por bimestre/trimestre
$stmt = $pdo->prepare("
    SELECT 
        QUARTER(n.fecha_evaluacion) as periodo,
        COUNT(*) as total_evaluaciones,
        AVG(n.nota) as promedio_periodo,
        MIN(n.nota) as nota_min,
        MAX(n.nota) as nota_max
    FROM notas n
    WHERE n.id_matricula = ?
    GROUP BY QUARTER(n.fecha_evaluacion)
    ORDER BY periodo
");
$stmt->execute([$estudiante['id_matricula']]);
$estadisticas_periodo = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-chart-line text-success"></i> Mis Calificaciones</h2>
                    <p class="text-muted">
                        <?= $estudiante['numero_grado'] ?>° Grado "<?= $estudiante['letra_seccion'] ?>" • 
                        Año Académico <?= $estudiante['anio'] ?>
                    </p>
                </div>
                <div class="text-end">
                    <div class="bg-light rounded p-3">
                        <h4 class="mb-1 text-<?= ($promedio_general >= 14) ? 'success' : (($promedio_general >= 11) ? 'warning' : 'danger') ?>">
                            <?= number_format($promedio_general, 2) ?>
                        </h4>
                        <small class="text-muted">Promedio General</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas Resumen -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-calculator fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $total_notas ?></h4>
                    <small class="text-muted">Total Evaluaciones</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $notas_aprobadas ?></h4>
                    <small class="text-muted">Notas Aprobadas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-danger mb-2">
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $notas_desaprobadas ?></h4>
                    <small class="text-muted">Notas Desaprobadas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-info mb-2">
                        <i class="fas fa-book fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= count($cursos_notas) ?></h4>
                    <small class="text-muted">Cursos</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Calificaciones por Curso -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-book text-primary"></i> Calificaciones por Curso
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($cursos_notas)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Curso</th>
                                        <th>Docente</th>
                                        <th class="text-center">N° Evaluaciones</th>
                                        <th class="text-center">Promedio</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cursos_notas as $curso): ?>
                                        <?php 
                                        $promedio = $curso['promedio_curso'] ?? 0;
                                        $estado_class = '';
                                        $estado_text = '';
                                        
                                        if ($promedio >= 18) {
                                            $estado_class = 'success';
                                            $estado_text = 'EXCELENTE';
                                        } elseif ($promedio >= 14) {
                                            $estado_class = 'success';
                                            $estado_text = 'APROBADO';
                                        } elseif ($promedio >= 11) {
                                            $estado_class = 'warning';
                                            $estado_text = 'EN PROCESO';
                                        } else {
                                            $estado_class = 'danger';
                                            $estado_text = 'DESAPROBADO';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong>[<?= $curso['curso_codigo'] ?>] <?= htmlspecialchars($curso['curso_nombre']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= $curso['horas_semanales'] ?> hrs/semana</small>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($curso['docente_nombre']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?= $curso['total_notas'] ?? 0 ?></span>
                                            </td>
                                            <td class="text-center">
                                                <strong class="text-<?= $estado_class ?>">
                                                    <?= $promedio ? number_format($promedio, 2) : '-' ?>
                                                </strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $estado_class ?>"><?= $estado_text ?></span>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="verDetallesCurso(<?= $curso['id_curso'] ?>)">
                                                    <i class="fas fa-eye"></i> Ver Detalles
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h5>No hay calificaciones disponibles</h5>
                            <p class="text-muted">Aún no se han registrado calificaciones para este período académico.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historial de Evaluaciones -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-info"></i> Historial de Evaluaciones
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($todas_notas)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Curso</th>
                                        <th>Tipo Evaluación</th>
                                        <th class="text-center">Nota</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todas_notas as $nota): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($nota['fecha_evaluacion'])) ?></td>
                                            <td>
                                                <small>
                                                    [<?= $nota['curso_codigo'] ?>] 
                                                    <?= htmlspecialchars($nota['curso_nombre']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($nota['tipo_evaluacion']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= ($nota['nota'] >= 14) ? 'success' : (($nota['nota'] >= 11) ? 'warning' : 'danger') ?>">
                                                    <?= number_format($nota['nota'], 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($nota['observaciones'] ?? '') ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No hay evaluaciones registradas.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar con Estadísticas -->
        <div class="col-lg-4">
            <!-- Progreso por Períodos -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-bar text-warning"></i> Progreso por Períodos
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($estadisticas_periodo)): ?>
                        <?php foreach ($estadisticas_periodo as $periodo): ?>
                            <?php 
                            $periodo_nombre = '';
                            switch($periodo['periodo']) {
                                case 1: $periodo_nombre = '1er Trimestre'; break;
                                case 2: $periodo_nombre = '2do Trimestre'; break;
                                case 3: $periodo_nombre = '3er Trimestre'; break;
                                case 4: $periodo_nombre = '4to Trimestre'; break;
                            }
                            $promedio_periodo = $periodo['promedio_periodo'];
                            $color_clase = ($promedio_periodo >= 14) ? 'success' : (($promedio_periodo >= 11) ? 'warning' : 'danger');
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                                <div>
                                    <h6 class="mb-1"><?= $periodo_nombre ?></h6>
                                    <small class="text-muted"><?= $periodo['total_evaluaciones'] ?> evaluaciones</small>
                                </div>
                                <div class="text-end">
                                    <h5 class="mb-0 text-<?= $color_clase ?>">
                                        <?= number_format($promedio_periodo, 2) ?>
                                    </h5>
                                    <small class="text-muted">
                                        Min: <?= number_format($periodo['nota_min'], 1) ?> | 
                                        Max: <?= number_format($periodo['nota_max'], 1) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">Sin datos de períodos</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gráfico de Rendimiento -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-pie text-success"></i> Distribución de Notas
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($total_notas > 0): ?>
                        <?php 
                        $porcentaje_aprobadas = ($notas_aprobadas / $total_notas) * 100;
                        $porcentaje_desaprobadas = ($notas_desaprobadas / $total_notas) * 100;
                        $porcentaje_proceso = 100 - $porcentaje_aprobadas - $porcentaje_desaprobadas;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Aprobadas (≥14)</small>
                                <small><?= round($porcentaje_aprobadas, 1) ?>%</small>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?= $porcentaje_aprobadas ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>En Proceso (11-13)</small>
                                <small><?= round($porcentaje_proceso, 1) ?>%</small>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-warning" style="width: <?= $porcentaje_proceso ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Desaprobadas (&lt;11)</small>
                                <small><?= round($porcentaje_desaprobadas, 1) ?>%</small>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-danger" style="width: <?= $porcentaje_desaprobadas ?>%"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Sin datos para mostrar</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Consejos Académicos -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb text-warning"></i> Consejos Académicos
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($promedio_general >= 18): ?>
                        <div class="alert alert-success">
                            <strong>¡Excelente rendimiento!</strong><br>
                            Mantén este nivel de dedicación y sigue así.
                        </div>
                    <?php elseif ($promedio_general >= 14): ?>
                        <div class="alert alert-info">
                            <strong>Buen rendimiento</strong><br>
                            Puedes mejorar aún más con dedicación extra.
                        </div>
                    <?php elseif ($promedio_general >= 11): ?>
                        <div class="alert alert-warning">
                            <strong>Necesitas mejorar</strong><br>
                            Considera estudiar más y pedir ayuda a tus profesores.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <strong>Requiere atención urgente</strong><br>
                            Habla con tus profesores y busca apoyo académico.
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <h6>Recomendaciones:</h6>
                        <ul class="small">
                            <li>Revisa regularmente tus calificaciones</li>
                            <li>Identifica áreas de mejora</li>
                            <li>Participa activamente en clases</li>
                            <li>Completa todas las tareas a tiempo</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Detalles del Curso -->
<div class="modal fade" id="detallesCursoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Curso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detallesCursoContent">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>
</div>

<script>
function verDetallesCurso(idCurso) {
    // Aquí se cargarían los detalles específicos del curso
    $('#detallesCursoModal').modal('show');
    $('#detallesCursoContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>');
    
    // Simular carga de datos
    setTimeout(() => {
        $('#detallesCursoContent').html(`
            <div class="alert alert-info">
                <strong>Funcionalidad en desarrollo</strong><br>
                Los detalles específicos del curso se mostrarán aquí.
            </div>
        `);
    }, 1000);
}
</script>

<?php include '../includes/footer.php'; ?>