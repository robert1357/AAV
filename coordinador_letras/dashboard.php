<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de coordinador de letras
check_permission(['COORD_LETRAS']);

$page_title = 'Dashboard - Coordinador de Letras';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Coordinador Letras']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos del coordinador
$db = new Database();
$pdo = $db->getConnection();

// Obtener área académica de letras
$sql = "SELECT id_area FROM areas_academicas WHERE nombre = 'LETRAS'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$area_letras = $stmt->fetch();
$area_id = $area_letras['id_area'] ?? 0;

// Estadísticas del área
$stats = [];

// Cursos del área de letras
$sql = "SELECT COUNT(*) as total FROM cursos WHERE id_area = ? AND activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$area_id]);
$stats['total_courses'] = $stmt->fetch()['total'];

// Docentes del área
$sql = "SELECT COUNT(DISTINCT a.id_personal) as total
        FROM asignaciones a
        JOIN cursos c ON a.id_curso = c.id_curso
        WHERE c.id_area = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$area_id]);
$stats['total_teachers'] = $stmt->fetch()['total'];

// Estudiantes en cursos del área
$sql = "SELECT COUNT(DISTINCT m.id_estudiante) as total
        FROM matriculas m
        JOIN asignaciones a ON m.id_seccion = a.id_seccion
        JOIN cursos c ON a.id_curso = c.id_curso
        JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE c.id_area = ? AND aa.anio = YEAR(NOW()) AND m.estado = 'ACTIVO'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$area_id]);
$stats['total_students'] = $stmt->fetch()['total'];

// Promedio general del área
$sql = "SELECT AVG(n.nota) as promedio
        FROM notas n
        JOIN cursos c ON n.id_curso = c.id_curso
        JOIN matriculas m ON n.id_matricula = m.id_matricula
        JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE c.id_area = ? AND aa.anio = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$area_id]);
$result = $stmt->fetch();
$stats['average_grade'] = $result['promedio'] ? round($result['promedio'], 2) : 0;

// Rendimiento por curso del área
$sql = "SELECT c.nombre as curso_nombre, c.codigo,
               AVG(n.nota) as promedio, COUNT(n.id_nota) as total_notas
        FROM cursos c
        LEFT JOIN notas n ON c.id_curso = n.id_curso
        JOIN matriculas m ON n.id_matricula = m.id_matricula
        JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE c.id_area = ? AND aa.anio = YEAR(NOW())
        GROUP BY c.id_curso
        ORDER BY promedio DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$area_id]);
$course_performance = $stmt->fetchAll();

// Docentes del área
$sql = "SELECT DISTINCT p.nombres, p.apellido_paterno, p.apellido_materno,
               COUNT(DISTINCT c.id_curso) as total_cursos
        FROM personal p
        JOIN asignaciones a ON p.id_personal = a.id_personal
        JOIN cursos c ON a.id_curso = c.id_curso
        WHERE c.id_area = ? AND p.activo = 1
        GROUP BY p.id_personal
        ORDER BY p.apellido_paterno";
$stmt = $pdo->prepare($sql);
$stmt->execute([$area_id]);
$area_teachers = $stmt->fetchAll();

// Estudiantes destacados en comprensión lectora
$sql = "SELECT e.nombres, e.apellido_paterno, e.apellido_materno,
               AVG(n.nota) as promedio, COUNT(n.id_nota) as total_notas
        FROM estudiantes e
        JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        JOIN notas n ON m.id_matricula = n.id_matricula
        JOIN cursos c ON n.id_curso = c.id_curso
        JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE c.id_area = ? AND aa.anio = YEAR(NOW())
        GROUP BY e.id_estudiante
        HAVING promedio >= 16
        ORDER BY promedio DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$area_id]);
$outstanding_students = $stmt->fetchAll();

// Recursos bibliográficos del área
$sql = "SELECT COUNT(*) as total FROM libros WHERE area_academica = 'LETRAS'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$total_books = $stmt->fetch()['total'];

// Materiales del área
$sql = "SELECT COUNT(*) as total FROM materiales_curso mc
        JOIN cursos c ON mc.id_curso = c.id_curso
        WHERE c.id_area = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$area_id]);
$total_materials = $stmt->fetch()['total'];

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header del coordinador -->
    <div class="admin-header">
        <h1><i class="fas fa-book-reader"></i> Coordinación de Letras</h1>
        <p>Gestión académica y coordinación del área de letras y humanidades</p>
    </div>
    
    <!-- Estadísticas del área -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="number"><?php echo $stats['total_courses']; ?></div>
            <div class="label">Cursos de Letras</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="number"><?php echo $stats['total_teachers']; ?></div>
            <div class="label">Docentes del Área</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="number"><?php echo $stats['total_students']; ?></div>
            <div class="label">Estudiantes</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="number"><?php echo $stats['average_grade']; ?></div>
            <div class="label">Promedio General</div>
        </div>
    </div>
    
    <!-- Navegación del coordinador -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión del Área</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="curriculum_management.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-graduation-cap d-block mb-2"></i>
                                Gestión Curricular
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="teacher_coordination.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-users d-block mb-2"></i>
                                Coordinación Docente
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="area_reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-bar d-block mb-2"></i>
                                Reportes del Área
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="resources.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-folder-open d-block mb-2"></i>
                                Recursos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Rendimiento por curso -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Rendimiento por Curso</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($course_performance)): ?>
                        <p class="text-muted">No hay datos de rendimiento disponibles</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th>Código</th>
                                        <th>Promedio</th>
                                        <th>Total Notas</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_performance as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['curso_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($course['codigo']); ?></td>
                                            <td>
                                                <strong class="<?php echo $course['promedio'] >= 14 ? 'text-success' : ($course['promedio'] >= 11 ? 'text-warning' : 'text-danger'); ?>">
                                                    <?php echo number_format($course['promedio'], 2); ?>
                                                </strong>
                                            </td>
                                            <td><?php echo $course['total_notas']; ?></td>
                                            <td>
                                                <?php if ($course['promedio'] >= 14): ?>
                                                    <span class="badge bg-success">Excelente</span>
                                                <?php elseif ($course['promedio'] >= 11): ?>
                                                    <span class="badge bg-warning">Regular</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Necesita Mejora</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Información del área -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Información del Área</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Libros Disponibles:</strong>
                        <span class="badge bg-primary"><?php echo $total_books; ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Materiales de Curso:</strong>
                        <span class="badge bg-info"><?php echo $total_materials; ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Promedio General:</strong>
                        <span class="badge <?php echo $stats['average_grade'] >= 14 ? 'bg-success' : ($stats['average_grade'] >= 11 ? 'bg-warning' : 'bg-danger'); ?>">
                            <?php echo $stats['average_grade']; ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Año Académico:</strong>
                        <span class="badge bg-info"><?php echo date('Y'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <!-- Docentes del área -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users"></i> Docentes del Área</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($area_teachers)): ?>
                        <p class="text-muted">No hay docentes asignados al área</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($area_teachers as $teacher): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($teacher['nombres'] . ' ' . $teacher['apellido_paterno'] . ' ' . $teacher['apellido_materno']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo $teacher['total_cursos']; ?> curso(s) asignado(s)
                                        </small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo $teacher['total_cursos']; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Estudiantes destacados -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-award"></i> Estudiantes Destacados</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($outstanding_students)): ?>
                        <p class="text-muted">No hay estudiantes destacados actualmente</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($outstanding_students as $student): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($student['nombres'] . ' ' . $student['apellido_paterno'] . ' ' . $student['apellido_materno']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo $student['total_notas']; ?> evaluaciones
                                        </small>
                                    </div>
                                    <span class="badge bg-success rounded-pill">
                                        <?php echo number_format($student['promedio'], 2); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
