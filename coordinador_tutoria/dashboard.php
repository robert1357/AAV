<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de coordinador de tutoría
check_permission(['COORD_TUTORIA']);

$page_title = 'Dashboard - Coordinador de Tutoría';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Coordinador Tutoría']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos del coordinador
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas de tutoría
$stats = [];

// Total de estudiantes
$sql = "SELECT COUNT(*) as total FROM estudiantes WHERE activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['total_students'] = $stmt->fetch()['total'];

// Tutores asignados
$sql = "SELECT COUNT(DISTINCT at.id_personal) as total
        FROM asignacion_tutoria at
        JOIN anios_academicos aa ON at.id_anio = aa.id_anio
        WHERE aa.anio = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['total_tutors'] = $stmt->fetch()['total'];

// Estudiantes con atención psicológica
$sql = "SELECT COUNT(DISTINCT ap.id_estudiante) as total
        FROM atencion_psicologica ap
        WHERE ap.fecha_atencion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['students_with_psychology'] = $stmt->fetch()['total'];

// Casos de seguimiento activos
$sql = "SELECT COUNT(*) as total FROM seguimiento_psicologico 
        WHERE estado = 'ACTIVO'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['active_cases'] = $stmt->fetch()['total'];

// Asignaciones de tutoría por grado
$sql = "SELECT g.numero_grado, COUNT(DISTINCT at.id_personal) as total_tutores,
               COUNT(DISTINCT s.id_seccion) as total_secciones
        FROM grados g
        LEFT JOIN secciones s ON g.id_grado = s.id_grado
        LEFT JOIN asignacion_tutoria at ON s.id_seccion = at.id_seccion
        LEFT JOIN anios_academicos aa ON at.id_anio = aa.id_anio
        WHERE aa.anio = YEAR(NOW()) OR aa.anio IS NULL
        GROUP BY g.numero_grado
        ORDER BY g.numero_grado";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tutoring_by_grade = $stmt->fetchAll();

// Estudiantes con alertas académicas
$sql = "SELECT e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante,
               g.numero_grado, s.letra_seccion, AVG(n.nota) as promedio
        FROM estudiantes e
        JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN notas n ON m.id_matricula = n.id_matricula
        JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE aa.anio = YEAR(NOW()) AND m.estado = 'ACTIVO'
        GROUP BY e.id_estudiante
        HAVING promedio < 11
        ORDER BY promedio ASC
        LIMIT 15";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$academic_alerts = $stmt->fetchAll();

// Últimas atenciones psicológicas
$sql = "SELECT ap.fecha_atencion, ap.motivo, ap.tipo_atencion,
               e.nombres, e.apellido_paterno, e.apellido_materno,
               p.nombres as psicologo_nombres, p.apellido_paterno as psicologo_apellido
        FROM atencion_psicologica ap
        JOIN estudiantes e ON ap.id_estudiante = e.id_estudiante
        JOIN personal p ON ap.id_psicologo = p.id_personal
        ORDER BY ap.fecha_atencion DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$recent_psychology_sessions = $stmt->fetchAll();

// Tutores activos
$sql = "SELECT p.nombres, p.apellido_paterno, p.apellido_materno,
               g.numero_grado, s.letra_seccion, at.fecha_asignacion
        FROM asignacion_tutoria at
        JOIN personal p ON at.id_personal = p.id_personal
        JOIN secciones s ON at.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN anios_academicos aa ON at.id_anio = aa.id_anio
        WHERE aa.anio = YEAR(NOW())
        ORDER BY g.numero_grado, s.letra_seccion";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$active_tutors = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header del coordinador -->
    <div class="admin-header">
        <h1><i class="fas fa-user-friends"></i> Coordinación de Tutoría</h1>
        <p>Orientación y apoyo integral a los estudiantes</p>
    </div>
    
    <!-- Estadísticas de tutoría -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="number"><?php echo $stats['total_students']; ?></div>
            <div class="label">Total Estudiantes</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="number"><?php echo $stats['total_tutors']; ?></div>
            <div class="label">Tutores Asignados</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-heart"></i>
            </div>
            <div class="number"><?php echo $stats['students_with_psychology']; ?></div>
            <div class="label">Atención Psicológica</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="number"><?php echo $stats['active_cases']; ?></div>
            <div class="label">Casos Activos</div>
        </div>
    </div>
    
    <!-- Navegación del coordinador -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión de Tutoría</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="student_guidance.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-hands-helping d-block mb-2"></i>
                                Orientación Estudiantil
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="tutoring_schedule.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-calendar-alt d-block mb-2"></i>
                                Horarios de Tutoría
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="counseling_reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-line d-block mb-2"></i>
                                Reportes de Consejería
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="student_support.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-life-ring d-block mb-2"></i>
                                Apoyo Estudiantil
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Tutoría por grado -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-layer-group"></i> Tutoría por Grado</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tutoring_by_grade)): ?>
                        <p class="text-muted">No hay datos de tutoría disponibles</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Grado</th>
                                        <th>Secciones</th>
                                        <th>Tutores</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tutoring_by_grade as $grade): ?>
                                        <tr>
                                            <td><?php echo $grade['numero_grado']; ?>°</td>
                                            <td><?php echo $grade['total_secciones']; ?></td>
                                            <td><?php echo $grade['total_tutores']; ?></td>
                                            <td>
                                                <?php if ($grade['total_tutores'] == $grade['total_secciones']): ?>
                                                    <span class="badge bg-success">Completo</span>
                                                <?php elseif ($grade['total_tutores'] > 0): ?>
                                                    <span class="badge bg-warning">Parcial</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Sin Asignar</span>
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
        
        <!-- Tutores activos -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-tie"></i> Tutores Activos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($active_tutors)): ?>
                        <p class="text-muted">No hay tutores asignados</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($active_tutors as $tutor): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($tutor['nombres'] . ' ' . $tutor['apellido_paterno'] . ' ' . $tutor['apellido_materno']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo $tutor['numero_grado']; ?>° <?php echo $tutor['letra_seccion']; ?>
                                            </small>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo format_date($tutor['fecha_asignacion']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <!-- Alertas académicas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-triangle text-warning"></i> Alertas Académicas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($academic_alerts)): ?>
                        <p class="text-success">No hay estudiantes con alertas académicas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($academic_alerts as $alert): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($alert['nombres'] . ' ' . $alert['apellido_paterno'] . ' ' . $alert['apellido_materno']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo $alert['codigo_estudiante']; ?> - 
                                            <?php echo $alert['numero_grado']; ?>° <?php echo $alert['letra_seccion']; ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-danger rounded-pill">
                                        <?php echo number_format($alert['promedio'], 2); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Últimas atenciones psicológicas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-heart"></i> Últimas Atenciones Psicológicas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_psychology_sessions)): ?>
                        <p class="text-muted">No hay atenciones psicológicas recientes</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($recent_psychology_sessions as $session): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($session['nombres'] . ' ' . $session['apellido_paterno'] . ' ' . $session['apellido_materno']); ?>
                                            </h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($session['motivo']); ?></p>
                                            <small class="text-muted">
                                                Por: <?php echo htmlspecialchars($session['psicologo_nombres'] . ' ' . $session['psicologo_apellido']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info"><?php echo $session['tipo_atencion']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($session['fecha_atencion']); ?>
                                            </small>
                                        </div>
                                    </div>
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
