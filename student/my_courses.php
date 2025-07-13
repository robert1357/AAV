<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Mis Cursos";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Obtener cursos del estudiante
$stmt = $pdo->prepare("
    SELECT c.*, g.nombre_grado, s.letra_seccion, aa.anio,
           p.nombres as docente_nombres, p.apellido_paterno as docente_apellido,
           COUNT(DISTINCT t.id_tarea) as total_tareas,
           COUNT(DISTINCT et.id_entrega) as tareas_entregadas,
           COUNT(DISTINCT cal.id_calificacion) as calificaciones_recibidas,
           AVG(cal.calificacion) as promedio_curso
    FROM matriculas m
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos aa ON s.id_anio = aa.id_anio
    JOIN asignaciones a ON s.id_seccion = a.id_seccion
    JOIN cursos c ON a.id_curso = c.id_curso
    LEFT JOIN personal p ON a.id_personal = p.id_personal
    LEFT JOIN tareas t ON c.id_curso = t.id_curso AND t.activo = 1 AND t.visible_estudiantes = 1
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea AND et.id_estudiante = ?
    LEFT JOIN calificaciones cal ON c.id_curso = cal.id_curso AND cal.id_estudiante = ?
    WHERE m.id_estudiante = ? AND m.estado = 'ACTIVO' AND c.activo = 1
    GROUP BY c.id_curso, a.id_asignacion
    ORDER BY c.nombre
");
$stmt->execute([$_SESSION['student_id'], $_SESSION['student_id'], $_SESSION['student_id']]);
$courses = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?php echo $page_title; ?></h2>
            </div>

            <!-- Estadísticas generales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h4><?php echo count($courses); ?></h4>
                            <p>Cursos Matriculados</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h4><?php echo array_sum(array_column($courses, 'total_tareas')); ?></h4>
                            <p>Tareas Asignadas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h4><?php echo array_sum(array_column($courses, 'tareas_entregadas')); ?></h4>
                            <p>Tareas Entregadas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <?php 
                            $promedios = array_filter(array_column($courses, 'promedio_curso'));
                            $promedio_general = !empty($promedios) ? round(array_sum($promedios) / count($promedios), 1) : 0;
                            ?>
                            <h4><?php echo $promedio_general; ?></h4>
                            <p>Promedio General</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de cursos -->
            <div class="row">
                <?php foreach ($courses as $course): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-book"></i>
                                <?php echo $course['codigo']; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $course['nombre']; ?></h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    <?php echo $course['nombre_grado'] . ' - Sección ' . $course['letra_seccion']; ?><br>
                                    <strong>Docente:</strong> <?php echo $course['docente_nombres'] . ' ' . $course['docente_apellido']; ?>
                                </small>
                            </p>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="border-end">
                                        <strong><?php echo $course['total_tareas']; ?></strong><br>
                                        <small>Tareas</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <strong><?php echo $course['tareas_entregadas']; ?></strong><br>
                                        <small>Entregadas</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <strong><?php echo $course['promedio_curso'] ? round($course['promedio_curso'], 1) : '-'; ?></strong><br>
                                    <small>Promedio</small>
                                </div>
                            </div>
                            
                            <!-- Progreso de tareas -->
                            <?php if ($course['total_tareas'] > 0): ?>
                            <div class="mb-2">
                                <small class="text-muted">Progreso de tareas:</small>
                                <div class="progress" style="height: 8px;">
                                    <?php 
                                    $progreso = ($course['tareas_entregadas'] / $course['total_tareas']) * 100;
                                    $color = $progreso >= 80 ? 'success' : ($progreso >= 60 ? 'warning' : 'danger');
                                    ?>
                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $progreso; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo round($progreso, 1); ?>% completado</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <small class="text-muted">Área: </small>
                                <span class="badge bg-secondary"><?php echo $course['area']; ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100" role="group">
                                <a href="course_detail.php?id=<?php echo $course['id_curso']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="assignments.php?course_id=<?php echo $course['id_curso']; ?>" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-tasks"></i> Tareas
                                </a>
                                <a href="grades.php?course_id=<?php echo $course['id_curso']; ?>" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-star"></i> Notas
                                </a>
                                <a href="materials.php?course_id=<?php echo $course['id_curso']; ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-folder"></i> Materiales
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($courses)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                            <h5>No tienes cursos matriculados</h5>
                            <p class="text-muted">Contacta a la secretaría para realizar tu matrícula.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tareas pendientes -->
            <?php if (!empty($courses)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-clock"></i> Tareas Próximas a Vencer</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Obtener tareas próximas a vencer
                            $stmt = $pdo->prepare("
                                SELECT t.*, c.nombre as nombre_curso, c.codigo
                                FROM tareas t
                                JOIN cursos c ON t.id_curso = c.id_curso
                                JOIN asignaciones a ON c.id_curso = a.id_curso
                                JOIN matriculas m ON a.id_seccion = m.id_seccion
                                WHERE m.id_estudiante = ? AND t.activo = 1 AND t.visible_estudiantes = 1
                                AND t.fecha_entrega > NOW() AND t.fecha_entrega <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                                AND NOT EXISTS (
                                    SELECT 1 FROM entregas_tareas et 
                                    WHERE et.id_tarea = t.id_tarea AND et.id_estudiante = ?
                                )
                                ORDER BY t.fecha_entrega ASC
                                LIMIT 5
                            ");
                            $stmt->execute([$_SESSION['student_id'], $_SESSION['student_id']]);
                            $upcoming_tasks = $stmt->fetchAll();
                            ?>
                            
                            <?php if (!empty($upcoming_tasks)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Curso</th>
                                                <th>Tarea</th>
                                                <th>Tipo</th>
                                                <th>Fecha de Entrega</th>
                                                <th>Estado</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcoming_tasks as $task): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $task['codigo']; ?></strong><br>
                                                    <small><?php echo $task['nombre_curso']; ?></small>
                                                </td>
                                                <td><?php echo $task['titulo']; ?></td>
                                                <td><span class="badge bg-info"><?php echo $task['tipo']; ?></span></td>
                                                <td>
                                                    <?php 
                                                    $fecha_entrega = new DateTime($task['fecha_entrega']);
                                                    $ahora = new DateTime();
                                                    $diff = $ahora->diff($fecha_entrega);
                                                    
                                                    echo $fecha_entrega->format('d/m/Y H:i') . '<br>';
                                                    
                                                    if ($diff->days == 0) {
                                                        echo '<small class="text-danger">Vence hoy</small>';
                                                    } elseif ($diff->days == 1) {
                                                        echo '<small class="text-warning">Vence mañana</small>';
                                                    } else {
                                                        echo '<small class="text-muted">Vence en ' . $diff->days . ' días</small>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning">Pendiente</span>
                                                </td>
                                                <td>
                                                    <a href="submit_assignment.php?id=<?php echo $task['id_tarea']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-upload"></i> Entregar
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <p class="text-muted">¡Excelente! No tienes tareas pendientes próximas a vencer.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>