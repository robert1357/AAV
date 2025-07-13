<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DOCENTE') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Mis Cursos";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Obtener cursos asignados al docente
$stmt = $pdo->prepare("
    SELECT c.*, g.nombre_grado, s.letra_seccion, aa.anio,
           COUNT(DISTINCT m.id_estudiante) as total_estudiantes,
           COUNT(DISTINCT t.id_tarea) as total_tareas
    FROM asignaciones a
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos aa ON s.id_anio = aa.id_anio
    LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion AND m.estado = 'ACTIVO'
    LEFT JOIN tareas t ON c.id_curso = t.id_curso AND t.activo = 1
    WHERE a.id_personal = ? AND c.activo = 1
    GROUP BY a.id_asignacion
    ORDER BY g.numero_grado, s.letra_seccion, c.nombre
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?php echo $page_title; ?></h2>
                <div>
                    <button class="btn btn-success" onclick="location.href='create_course.php'">
                        <i class="fas fa-plus"></i> Crear Curso
                    </button>
                </div>
            </div>

            <!-- Estadísticas generales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h4><?php echo count($courses); ?></h4>
                            <p>Cursos Asignados</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h4><?php echo array_sum(array_column($courses, 'total_estudiantes')); ?></h4>
                            <p>Total Estudiantes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h4><?php echo array_sum(array_column($courses, 'total_tareas')); ?></h4>
                            <p>Tareas Creadas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h4><?php echo array_sum(array_column($courses, 'horas_semanales')); ?></h4>
                            <p>Horas Semanales</p>
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
                                    Año Académico: <?php echo $course['anio']; ?>
                                </small>
                            </p>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="border-end">
                                        <strong><?php echo $course['total_estudiantes']; ?></strong><br>
                                        <small>Estudiantes</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <strong><?php echo $course['total_tareas']; ?></strong><br>
                                        <small>Tareas</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <strong><?php echo $course['horas_semanales']; ?></strong><br>
                                    <small>Horas/Sem</small>
                                </div>
                            </div>
                            
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
                                <a href="manage_students.php?course_id=<?php echo $course['id_curso']; ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-users"></i> Estudiantes
                                </a>
                                <a href="assignments.php?course_id=<?php echo $course['id_curso']; ?>" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-tasks"></i> Tareas
                                </a>
                                <a href="grades.php?course_id=<?php echo $course['id_curso']; ?>" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-star"></i> Notas
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
                            <h5>No tienes cursos asignados</h5>
                            <p class="text-muted">Contacta al administrador para que te asigne cursos.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>