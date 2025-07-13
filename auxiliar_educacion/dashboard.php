<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de auxiliar de educación
check_permission(['AUXILIAR_EDUCACION']);

$page_title = 'Dashboard - Auxiliar de Educación';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Auxiliar Educación']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos del auxiliar
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas del auxiliar
$stats = [];

// Estudiantes bajo supervisión
$sql = "SELECT COUNT(DISTINCT m.id_estudiante) as total
        FROM auxiliares_educacion ae
        JOIN secciones s ON ae.id_seccion = s.id_seccion
        JOIN matriculas m ON s.id_seccion = m.id_seccion
        JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE ae.id_personal = ? AND aa.anio = YEAR(NOW()) AND m.estado = 'ACTIVO'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['students_supervised'] = $stmt->fetch()['total'];

// Incidencias disciplinarias este mes
$sql = "SELECT COUNT(*) as total FROM incidencias_disciplinarias 
        WHERE reportado_por = ? AND MONTH(fecha_incidencia) = MONTH(NOW()) AND YEAR(fecha_incidencia) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['disciplinary_incidents'] = $stmt->fetch()['total'];

// Asistencias registradas hoy
$sql = "SELECT COUNT(*) as total FROM asistencias a
        JOIN matriculas m ON a.id_matricula = m.id_matricula
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN auxiliares_educacion ae ON s.id_seccion = ae.id_seccion
        WHERE ae.id_personal = ? AND DATE(a.fecha) = CURDATE()";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['attendance_today'] = $stmt->fetch()['total'];

// Comunicaciones con padres
$sql = "SELECT COUNT(*) as total FROM comunicaciones_padres 
        WHERE enviado_por = ? AND MONTH(fecha_envio) = MONTH(NOW()) AND YEAR(fecha_envio) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['parent_communications'] = $stmt->fetch()['total'];

// Secciones asignadas
$sql = "SELECT s.*, g.numero_grado, COUNT(m.id_matricula) as total_estudiantes
        FROM auxiliares_educacion ae
        JOIN secciones s ON ae.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion
        LEFT JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE ae.id_personal = ? AND (aa.anio = YEAR(NOW()) OR aa.anio IS NULL)
        GROUP BY s.id_seccion
        ORDER BY g.numero_grado, s.letra_seccion";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$assigned_sections = $stmt->fetchAll();

// Estudiantes con problemas de asistencia
$sql = "SELECT e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante,
               g.numero_grado, s.letra_seccion,
               COUNT(CASE WHEN a.estado = 'AUSENTE' THEN 1 END) as ausencias,
               COUNT(a.id_asistencia) as total_registros
        FROM estudiantes e
        JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN auxiliares_educacion ae ON s.id_seccion = ae.id_seccion
        LEFT JOIN asistencias a ON m.id_matricula = a.id_matricula
        JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE ae.id_personal = ? AND aa.anio = YEAR(NOW()) 
        AND a.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY e.id_estudiante
        HAVING (ausencias / total_registros) > 0.2
        ORDER BY (ausencias / total_registros) DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$attendance_problems = $stmt->fetchAll();

// Últimas incidencias disciplinarias
$sql = "SELECT id.*, e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante
        FROM incidencias_disciplinarias id
        JOIN estudiantes e ON id.id_estudiante = e.id_estudiante
        WHERE id.reportado_por = ?
        ORDER BY id.fecha_incidencia DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$recent_incidents = $stmt->fetchAll();

// Estudiantes que requieren seguimiento
$sql = "SELECT e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante,
               g.numero_grado, s.letra_seccion, sp.motivo, sp.fecha_inicio
        FROM seguimiento_psicologico sp
        JOIN estudiantes e ON sp.id_estudiante = e.id_estudiante
        JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN auxiliares_educacion ae ON s.id_seccion = ae.id_seccion
        WHERE ae.id_personal = ? AND sp.estado = 'ACTIVO'
        ORDER BY sp.fecha_inicio DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$students_needing_followup = $stmt->fetchAll();

// Estadísticas de asistencia por sección
$sql = "SELECT g.numero_grado, s.letra_seccion,
               COUNT(CASE WHEN a.estado = 'PRESENTE' THEN 1 END) as presentes,
               COUNT(CASE WHEN a.estado = 'AUSENTE' THEN 1 END) as ausentes,
               COUNT(CASE WHEN a.estado = 'TARDANZA' THEN 1 END) as tardanzas,
               COUNT(a.id_asistencia) as total_registros
        FROM auxiliares_educacion ae
        JOIN secciones s ON ae.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN matriculas m ON s.id_seccion = m.id_seccion
        JOIN asistencias a ON m.id_matricula = a.id_matricula
        WHERE ae.id_personal = ? AND a.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY s.id_seccion
        ORDER BY g.numero_grado, s.letra_seccion";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$attendance_stats = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header del auxiliar -->
    <div class="admin-header">
        <h1><i class="fas fa-user-shield"></i> Panel Auxiliar de Educación</h1>
        <p>Apoyo educativo, disciplina y supervisión estudiantil</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="number"><?php echo $stats['students_supervised']; ?></div>
            <div class="label">Estudiantes Supervisados</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="number"><?php echo $stats['disciplinary_incidents']; ?></div>
            <div class="label">Incidencias Este Mes</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="number"><?php echo $stats['attendance_today']; ?></div>
            <div class="label">Asistencias Hoy</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-comment"></i>
            </div>
            <div class="number"><?php echo $stats['parent_communications']; ?></div>
            <div class="label">Comunicaciones Padres</div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión Auxiliar</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="student_support.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-hands-helping d-block mb-2"></i>
                                Apoyo Estudiantil
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="discipline.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-gavel d-block mb-2"></i>
                                Disciplina Escolar
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="attendance_support.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-clipboard-check d-block mb-2"></i>
                                Apoyo Asistencia
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="basic_admin.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-clipboard-list d-block mb-2"></i>
                                Admin Básica
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Secciones asignadas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-layer-group"></i> Secciones Asignadas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assigned_sections)): ?>
                        <p class="text-muted">No tiene secciones asignadas</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Grado</th>
                                        <th>Sección</th>
                                        <th>Estudiantes</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_sections as $section): ?>
                                        <tr>
                                            <td><?php echo $section['numero_grado']; ?>°</td>
                                            <td><?php echo $section['letra_seccion']; ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo $section['total_estudiantes']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="section_detail.php?id=<?php echo $section['id_seccion']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    Ver Detalles
                                                </a>
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
        
        <!-- Problemas de asistencia -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-clock text-warning"></i> Problemas de Asistencia</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($attendance_problems)): ?>
                        <p class="text-success">No hay problemas de asistencia</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($attendance_problems as $problem): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($problem['nombres'] . ' ' . $problem['apellido_paterno'] . ' ' . $problem['apellido_materno']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo $problem['codigo_estudiante']; ?> - 
                                                <?php echo $problem['numero_grado']; ?>° <?php echo $problem['letra_seccion']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-danger">
                                                <?php echo $problem['ausencias']; ?> ausencias
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo round(($problem['ausencias'] / $problem['total_registros']) * 100, 1); ?>%
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
    
    <div class="row mt-4">
        <!-- Últimas incidencias -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-circle"></i> Últimas Incidencias</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_incidents)): ?>
                        <p class="text-muted">No hay incidencias registradas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($recent_incidents as $incident): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($incident['nombres'] . ' ' . $incident['apellido_paterno'] . ' ' . $incident['apellido_materno']); ?>
                                            </h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($incident['descripcion']); ?></p>
                                            <small class="text-muted">
                                                <?php echo $incident['codigo_estudiante']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-warning"><?php echo $incident['tipo_incidencia']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($incident['fecha_incidencia']); ?>
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
        
        <!-- Estudiantes que requieren seguimiento -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-eye"></i> Seguimiento Especial</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($students_needing_followup)): ?>
                        <p class="text-muted">No hay estudiantes que requieran seguimiento especial</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($students_needing_followup as $student): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($student['nombres'] . ' ' . $student['apellido_paterno'] . ' ' . $student['apellido_materno']); ?>
                                            </h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($student['motivo']); ?></p>
                                            <small class="text-muted">
                                                <?php echo $student['codigo_estudiante']; ?> - 
                                                <?php echo $student['numero_grado']; ?>° <?php echo $student['letra_seccion']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info">Seguimiento</span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($student['fecha_inicio']); ?>
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
    
    <!-- Estadísticas de asistencia -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Asistencia por Sección (7 días)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($attendance_stats)): ?>
                        <p class="text-muted">No hay datos de asistencia disponibles</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Grado</th>
                                        <th>Sección</th>
                                        <th>Presentes</th>
                                        <th>Ausentes</th>
                                        <th>Tardanzas</th>
                                        <th>Total</th>
                                        <th>% Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo $stat['numero_grado']; ?>°</td>
                                            <td><?php echo $stat['letra_seccion']; ?></td>
                                            <td><span class="badge bg-success"><?php echo $stat['presentes']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $stat['ausentes']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $stat['tardanzas']; ?></span></td>
                                            <td><?php echo $stat['total_registros']; ?></td>
                                            <td>
                                                <?php 
                                                $percentage = $stat['total_registros'] > 0 ? round(($stat['presentes'] / $stat['total_registros']) * 100, 1) : 0;
                                                ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?php echo $percentage >= 85 ? 'bg-success' : ($percentage >= 75 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                         role="progressbar" style="width: <?php echo $percentage; ?>%">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
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
    </div>
</div>

<?php include '../includes/footer.php'; ?>
