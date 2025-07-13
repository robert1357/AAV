<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de psicóloga
check_permission(['PSICOLOGO']);

$page_title = 'Dashboard - Psicóloga';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Psicóloga']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos de la psicóloga
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas de psicología
$stats = [];

// Atenciones este mes
$sql = "SELECT COUNT(*) as total FROM atencion_psicologica 
        WHERE id_psicologo = ? AND MONTH(fecha_atencion) = MONTH(NOW()) AND YEAR(fecha_atencion) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['sessions_this_month'] = $stmt->fetch()['total'];

// Casos activos de seguimiento
$sql = "SELECT COUNT(*) as total FROM seguimiento_psicologico 
        WHERE id_psicologo = ? AND estado = 'ACTIVO'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['active_cases'] = $stmt->fetch()['total'];

// Estudiantes atendidos
$sql = "SELECT COUNT(DISTINCT id_estudiante) as total FROM atencion_psicologica 
        WHERE id_psicologo = ? AND fecha_atencion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['students_attended'] = $stmt->fetch()['total'];

// Intervenciones preventivas
$sql = "SELECT COUNT(*) as total FROM atencion_psicologica 
        WHERE id_psicologo = ? AND tipo_atencion = 'PREVENTIVA' AND MONTH(fecha_atencion) = MONTH(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['preventive_interventions'] = $stmt->fetch()['total'];

// Atenciones por tipo
$sql = "SELECT tipo_atencion, COUNT(*) as total
        FROM atencion_psicologica
        WHERE id_psicologo = ? AND fecha_atencion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY tipo_atencion
        ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$sessions_by_type = $stmt->fetchAll();

// Estudiantes con seguimiento activo
$sql = "SELECT sp.*, e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante,
               g.numero_grado, s.letra_seccion
        FROM seguimiento_psicologico sp
        JOIN estudiantes e ON sp.id_estudiante = e.id_estudiante
        JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE sp.id_psicologo = ? AND sp.estado = 'ACTIVO' AND aa.anio = YEAR(NOW())
        ORDER BY sp.prioridad DESC, sp.fecha_inicio ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$active_followups = $stmt->fetchAll();

// Últimas atenciones
$sql = "SELECT ap.*, e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante
        FROM atencion_psicologica ap
        JOIN estudiantes e ON ap.id_estudiante = e.id_estudiante
        WHERE ap.id_psicologo = ?
        ORDER BY ap.fecha_atencion DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$recent_sessions = $stmt->fetchAll();

// Estudiantes derivados por otros profesionales
$sql = "SELECT ap.*, e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante,
               p.nombres as derivado_por_nombres, p.apellido_paterno as derivado_por_apellido
        FROM atencion_psicologica ap
        JOIN estudiantes e ON ap.id_estudiante = e.id_estudiante
        LEFT JOIN personal p ON ap.derivado_por = p.id_personal
        WHERE ap.id_psicologo = ? AND ap.derivado_por IS NOT NULL
        ORDER BY ap.fecha_atencion DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$referred_students = $stmt->fetchAll();

// Alertas de salud mental
$sql = "SELECT e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante,
               sp.nivel_riesgo, sp.observaciones, sp.fecha_ultima_sesion
        FROM seguimiento_psicologico sp
        JOIN estudiantes e ON sp.id_estudiante = e.id_estudiante
        WHERE sp.id_psicologo = ? AND sp.estado = 'ACTIVO' 
        AND sp.nivel_riesgo IN ('ALTO', 'CRITICO')
        ORDER BY sp.nivel_riesgo DESC, sp.fecha_ultima_sesion ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$mental_health_alerts = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header de psicóloga -->
    <div class="admin-header">
        <h1><i class="fas fa-heart"></i> Panel de Psicología</h1>
        <p>Atención psicológica y bienestar estudiantil</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="number"><?php echo $stats['sessions_this_month']; ?></div>
            <div class="label">Atenciones Este Mes</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="number"><?php echo $stats['active_cases']; ?></div>
            <div class="label">Casos Activos</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="number"><?php echo $stats['students_attended']; ?></div>
            <div class="label">Estudiantes Atendidos</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="number"><?php echo $stats['preventive_interventions']; ?></div>
            <div class="label">Intervenciones Preventivas</div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión Psicológica</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="student_evaluation.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-user-check d-block mb-2"></i>
                                Evaluación Estudiantil
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="counseling.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-comments d-block mb-2"></i>
                                Consejería
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="mental_health.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-brain d-block mb-2"></i>
                                Salud Mental
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="psychology_reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-line d-block mb-2"></i>
                                Reportes
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="interventions.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-hands-helping d-block mb-2"></i>
                                Intervenciones
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="../coordinador_tutoria/dashboard.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-link d-block mb-2"></i>
                                Coordinación
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Atenciones por tipo -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Atenciones por Tipo (30 días)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($sessions_by_type)): ?>
                        <p class="text-muted">No hay atenciones registradas</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Cantidad</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_sessions = array_sum(array_column($sessions_by_type, 'total'));
                                    foreach ($sessions_by_type as $type): 
                                    ?>
                                        <tr>
                                            <td><?php echo $type['tipo_atencion']; ?></td>
                                            <td><span class="badge bg-primary"><?php echo $type['total']; ?></span></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo ($type['total'] / $total_sessions) * 100; ?>%">
                                                        <?php echo round(($type['total'] / $total_sessions) * 100, 1); ?>%
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
        
        <!-- Alertas de salud mental -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-triangle text-danger"></i> Alertas de Salud Mental</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($mental_health_alerts)): ?>
                        <p class="text-success">No hay alertas de salud mental</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($mental_health_alerts as $alert): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($alert['nombres'] . ' ' . $alert['apellido_paterno'] . ' ' . $alert['apellido_materno']); ?>
                                            </h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($alert['observaciones']); ?></p>
                                            <small class="text-muted">
                                                <?php echo $alert['codigo_estudiante']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $alert['nivel_riesgo'] === 'CRITICO' ? 'bg-danger' : 'bg-warning'; ?>">
                                                <?php echo $alert['nivel_riesgo']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($alert['fecha_ultima_sesion']); ?>
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
        <!-- Seguimiento activo -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-clipboard-check"></i> Seguimiento Activo</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($active_followups)): ?>
                        <p class="text-muted">No hay casos en seguimiento activo</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($active_followups as $followup): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($followup['nombres'] . ' ' . $followup['apellido_paterno'] . ' ' . $followup['apellido_materno']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo $followup['codigo_estudiante']; ?> - 
                                                <?php echo $followup['numero_grado']; ?>° <?php echo $followup['letra_seccion']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $followup['prioridad'] === 'ALTA' ? 'bg-danger' : ($followup['prioridad'] === 'MEDIA' ? 'bg-warning' : 'bg-success'); ?>">
                                                <?php echo $followup['prioridad']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($followup['fecha_inicio']); ?>
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
        
        <!-- Últimas atenciones -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Últimas Atenciones</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_sessions)): ?>
                        <p class="text-muted">No hay atenciones registradas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($recent_sessions as $session): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($session['nombres'] . ' ' . $session['apellido_paterno'] . ' ' . $session['apellido_materno']); ?>
                                            </h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($session['motivo']); ?></p>
                                            <small class="text-muted">
                                                <?php echo $session['codigo_estudiante']; ?>
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
