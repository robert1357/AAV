<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'COORDINADOR_CIENCIAS') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Coordinación de Ciencias";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Cursos del área de ciencias
$stmt = $pdo->prepare("
    SELECT c.*, g.nombre_grado, s.letra_seccion,
           p.nombres as docente_nombres, p.apellido_paterno as docente_apellido,
           COUNT(DISTINCT m.id_estudiante) as total_estudiantes,
           AVG(cal.calificacion) as promedio_curso
    FROM cursos c
    JOIN asignaciones a ON c.id_curso = a.id_curso
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    LEFT JOIN personal p ON a.id_personal = p.id_personal
    LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion AND m.estado = 'ACTIVO'
    LEFT JOIN calificaciones cal ON c.id_curso = cal.id_curso
    WHERE c.area = 'CIENCIAS' AND c.activo = 1
    GROUP BY c.id_curso, a.id_asignacion
    ORDER BY g.numero_grado, s.letra_seccion, c.nombre
");
$stmt->execute();
$courses = $stmt->fetchAll();

// Docentes del área
$stmt = $pdo->prepare("
    SELECT DISTINCT p.*, COUNT(DISTINCT a.id_curso) as cursos_asignados
    FROM personal p
    JOIN asignaciones a ON p.id_personal = a.id_personal
    JOIN cursos c ON a.id_curso = c.id_curso
    WHERE c.area = 'CIENCIAS' AND p.activo = 1 AND c.activo = 1
    GROUP BY p.id_personal
    ORDER BY p.apellido_paterno, p.apellido_materno
");
$stmt->execute();
$teachers = $stmt->fetchAll();

// Estadísticas del área
$stats = [
    'total_cursos' => count($courses),
    'total_docentes' => count($teachers),
    'total_estudiantes' => array_sum(array_column($courses, 'total_estudiantes')),
    'promedio_area' => 0
];

$promedios = array_filter(array_column($courses, 'promedio_curso'));
if (!empty($promedios)) {
    $stats['promedio_area'] = round(array_sum($promedios) / count($promedios), 2);
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><?php echo $page_title; ?></h2>
                    <p class="text-muted">Supervisión y coordinación del área curricular de ciencias</p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planificationModal">
                        <i class="fas fa-calendar-plus"></i> Nueva Planificación
                    </button>
                </div>
            </div>

            <!-- Estadísticas del área -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h4><?php echo $stats['total_cursos']; ?></h4>
                            <p>Cursos de Ciencias</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h4><?php echo $stats['total_docentes']; ?></h4>
                            <p>Docentes del Área</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h4><?php echo $stats['total_estudiantes']; ?></h4>
                            <p>Estudiantes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h4><?php echo $stats['promedio_area']; ?></h4>
                            <p>Promedio del Área</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Cursos del área -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-flask"></i> Cursos del Área de Ciencias</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Curso</th>
                                            <th>Grado/Sección</th>
                                            <th>Docente</th>
                                            <th>Estudiantes</th>
                                            <th>Promedio</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $course['codigo']; ?></strong><br>
                                                <small><?php echo $course['nombre']; ?></small>
                                            </td>
                                            <td><?php echo $course['nombre_grado'] . ' - ' . $course['letra_seccion']; ?></td>
                                            <td><?php echo $course['docente_nombres'] . ' ' . $course['docente_apellido']; ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $course['total_estudiantes']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($course['promedio_curso']): ?>
                                                    <span class="fw-bold"><?php echo round($course['promedio_curso'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewCourseDetails(<?php echo $course['id_curso']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" onclick="viewGrades(<?php echo $course['id_curso']; ?>)">
                                                        <i class="fas fa-chart-bar"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="generateReport(<?php echo $course['id_curso']; ?>)">
                                                        <i class="fas fa-file-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel de coordinación -->
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6><i class="fas fa-users"></i> Docentes del Área</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($teachers as $teacher): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div>
                                    <strong><?php echo $teacher['nombres'] . ' ' . $teacher['apellido_paterno']; ?></strong><br>
                                    <small class="text-muted"><?php echo $teacher['cursos_asignados']; ?> curso(s)</small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="contactTeacher(<?php echo $teacher['id_personal']; ?>)">
                                    <i class="fas fa-envelope"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-tasks"></i> Actividades Coordinadas</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary btn-sm" onclick="planWeeklyMeeting()">
                                    <i class="fas fa-calendar-week"></i> Reunión Semanal
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="organizeScienceFair()">
                                    <i class="fas fa-microscope"></i> Feria de Ciencias
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="reviewCurriculum()">
                                    <i class="fas fa-book-open"></i> Revisión Curricular
                                </button>
                                <button class="btn btn-outline-warning btn-sm" onclick="evaluateTeachers()">
                                    <i class="fas fa-clipboard-check"></i> Evaluación Docente
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="manageResources()">
                                    <i class="fas fa-boxes"></i> Recursos Didácticos
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plan de trabajo -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-clipboard-list"></i> Plan de Trabajo - Área de Ciencias</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Objetivos del Área</h6>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item">
                                            <i class="fas fa-check-circle text-success"></i> Mejorar rendimiento académico en ciencias
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-hourglass-half text-warning"></i> Implementar metodologías activas
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-clock text-info"></i> Organizar feria de ciencias anual
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-clock text-info"></i> Capacitar docentes en nuevas tecnologías
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Próximas Actividades</h6>
                                    <div class="timeline">
                                        <div class="d-flex mb-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-primary rounded-circle" style="width: 12px; height: 12px;"></div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <strong>15 Enero</strong><br>
                                                <small>Reunión de coordinación semanal</small>
                                            </div>
                                        </div>
                                        <div class="d-flex mb-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-success rounded-circle" style="width: 12px; height: 12px;"></div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <strong>20 Enero</strong><br>
                                                <small>Evaluación de laboratorios</small>
                                            </div>
                                        </div>
                                        <div class="d-flex mb-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-warning rounded-circle" style="width: 12px; height: 12px;"></div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <strong>25 Enero</strong><br>
                                                <small>Planificación feria de ciencias</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewCourseDetails(courseId) {
    window.location.href = '../admin/course_detail.php?id=' + courseId;
}

function viewGrades(courseId) {
    window.location.href = '../reports/course_grades.php?course_id=' + courseId;
}

function generateReport(courseId) {
    alert('Generando reporte del curso ID: ' + courseId);
}

function contactTeacher(teacherId) {
    alert('Contactando al docente ID: ' + teacherId);
}

function planWeeklyMeeting() {
    alert('Planificando reunión semanal');
}

function organizeScienceFair() {
    alert('Organizando feria de ciencias');
}

function reviewCurriculum() {
    alert('Revisando currículo del área');
}

function evaluateTeachers() {
    alert('Evaluando desempeño docente');
}

function manageResources() {
    alert('Gestionando recursos didácticos');
}
</script>

<?php require_once '../includes/footer.php'; ?>