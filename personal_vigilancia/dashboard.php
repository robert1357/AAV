<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de personal de vigilancia
check_permission(['VIGILANCIA']);

$page_title = 'Dashboard - Personal de Vigilancia';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Vigilancia']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos del personal de vigilancia
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas de seguridad
$stats = [];

// Reportes de seguridad este mes
$sql = "SELECT COUNT(*) as total FROM reportes_seguridad 
        WHERE reportado_por = ? AND MONTH(fecha_reporte) = MONTH(NOW()) AND YEAR(fecha_reporte) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['security_reports'] = $stmt->fetch()['total'];

// Visitas registradas hoy
$sql = "SELECT COUNT(*) as total FROM registro_visitas 
        WHERE registrado_por = ? AND DATE(fecha_entrada) = CURDATE()";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['visits_today'] = $stmt->fetch()['total'];

// Incidentes de seguridad este mes
$sql = "SELECT COUNT(*) as total FROM incidentes_seguridad 
        WHERE reportado_por = ? AND MONTH(fecha_incidente) = MONTH(NOW()) AND YEAR(fecha_incidente) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['security_incidents'] = $stmt->fetch()['total'];

// Turnos asignados esta semana
$sql = "SELECT COUNT(*) as total FROM turnos_vigilancia 
        WHERE id_personal = ? AND fecha_turno BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) 
        AND DATE_ADD(CURDATE(), INTERVAL 6-WEEKDAY(CURDATE()) DAY)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['shifts_this_week'] = $stmt->fetch()['total'];

// Turnos asignados
$sql = "SELECT tv.*, DATE_FORMAT(tv.hora_inicio, '%H:%i') as inicio, DATE_FORMAT(tv.hora_fin, '%H:%i') as fin
        FROM turnos_vigilancia tv
        WHERE tv.id_personal = ? AND tv.fecha_turno >= CURDATE()
        ORDER BY tv.fecha_turno ASC, tv.hora_inicio ASC
        LIMIT 7";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$assigned_shifts = $stmt->fetchAll();

// Últimos reportes de seguridad
$sql = "SELECT * FROM reportes_seguridad
        WHERE reportado_por = ?
        ORDER BY fecha_reporte DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$recent_reports = $stmt->fetchAll();

// Visitas del día
$sql = "SELECT rv.*, COALESCE(rv.motivo_visita, 'No especificado') as motivo
        FROM registro_visitas rv
        WHERE rv.registrado_por = ? AND DATE(rv.fecha_entrada) = CURDATE()
        ORDER BY rv.fecha_entrada DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$today_visits = $stmt->fetchAll();

// Incidentes de seguridad pendientes
$sql = "SELECT is.*, l.nombre as laboratorio_nombre
        FROM incidentes_seguridad is
        LEFT JOIN laboratorios l ON is.id_laboratorio = l.id_laboratorio
        WHERE is.estado = 'PENDIENTE' OR is.estado = 'EN_INVESTIGACION'
        ORDER BY is.fecha_incidente DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$pending_incidents = $stmt->fetchAll();

// Áreas bajo vigilancia
$sql = "SELECT av.*, tv.fecha_turno, tv.hora_inicio, tv.hora_fin
        FROM areas_vigilancia av
        LEFT JOIN turnos_vigilancia tv ON av.id_area = tv.id_area
        WHERE tv.id_personal = ? AND tv.fecha_turno = CURDATE()
        ORDER BY tv.hora_inicio";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$surveillance_areas = $stmt->fetchAll();

// Equipos de seguridad asignados
$sql = "SELECT es.*, tv.fecha_turno
        FROM equipos_seguridad es
        JOIN turnos_vigilancia tv ON es.id_area = tv.id_area
        WHERE tv.id_personal = ? AND tv.fecha_turno = CURDATE() AND es.estado = 'ACTIVO'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$security_equipment = $stmt->fetchAll();

// Alertas de seguridad activas
$sql = "SELECT * FROM alertas_seguridad
        WHERE estado = 'ACTIVA' AND fecha_alerta >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY nivel_alerta DESC, fecha_alerta DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$active_alerts = $stmt->fetchAll();

// Control de acceso
$sql = "SELECT ca.*, p.nombres, p.apellido_paterno
        FROM control_acceso ca
        LEFT JOIN personal p ON ca.id_personal = p.id_personal
        WHERE ca.controlado_por = ? AND DATE(ca.fecha_acceso) = CURDATE()
        ORDER BY ca.fecha_acceso DESC
        LIMIT 15";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$access_control = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header de vigilancia -->
    <div class="admin-header">
        <h1><i class="fas fa-shield-alt"></i> Panel de Vigilancia</h1>
        <p>Seguridad, control de acceso y monitoreo de instalaciones</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="number"><?php echo $stats['security_reports']; ?></div>
            <div class="label">Reportes Este Mes</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="number"><?php echo $stats['visits_today']; ?></div>
            <div class="label">Visitas Hoy</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="number"><?php echo $stats['security_incidents']; ?></div>
            <div class="label">Incidentes Este Mes</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="number"><?php echo $stats['shifts_this_week']; ?></div>
            <div class="label">Turnos Esta Semana</div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión de Seguridad</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="security_reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-file-alt d-block mb-2"></i>
                                Reportes de Seguridad
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="access_control.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-key d-block mb-2"></i>
                                Control de Acceso
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="facility_monitoring.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-video d-block mb-2"></i>
                                Monitoreo
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="visitor_registration.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-clipboard-list d-block mb-2"></i>
                                Registro Visitas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Turnos asignados -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-alt"></i> Turnos Próximos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assigned_shifts)): ?>
                        <p class="text-muted">No tiene turnos asignados</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Hora Inicio</th>
                                        <th>Hora Fin</th>
                                        <th>Área</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_shifts as $shift): ?>
                                        <tr>
                                            <td><?php echo format_date($shift['fecha_turno']); ?></td>
                                            <td><?php echo $shift['inicio']; ?></td>
                                            <td><?php echo $shift['fin']; ?></td>
                                            <td><?php echo htmlspecialchars($shift['area_asignada']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Alertas activas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bell text-danger"></i> Alertas Activas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($active_alerts)): ?>
                        <p class="text-success">No hay alertas activas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($active_alerts as $alert): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($alert['descripcion']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($alert['ubicacion']); ?></p>
                                            <small class="text-muted">
                                                <?php echo format_datetime($alert['fecha_alerta']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $alert['nivel_alerta'] === 'ALTA' ? 'bg-danger' : ($alert['nivel_alerta'] === 'MEDIA' ? 'bg-warning' : 'bg-info'); ?>">
                                                <?php echo $alert['nivel_alerta']; ?>
                                            </span>
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
        <!-- Visitas del día -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users"></i> Visitas de Hoy</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($today_visits)): ?>
                        <p class="text-muted">No hay visitas registradas hoy</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($today_visits as $visit): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($visit['nombre_visitante']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($visit['motivo']); ?></p>
                                            <small class="text-muted">
                                                DNI: <?php echo htmlspecialchars($visit['dni_visitante']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $visit['fecha_salida'] ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $visit['fecha_salida'] ? 'Salió' : 'En Instalaciones'; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($visit['fecha_entrada'])); ?>
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
        
        <!-- Incidentes pendientes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-circle text-warning"></i> Incidentes Pendientes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_incidents)): ?>
                        <p class="text-success">No hay incidentes pendientes</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($pending_incidents as $incident): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($incident['descripcion']); ?></h6>
                                            <p class="mb-1 small">
                                                <?php echo $incident['laboratorio_nombre'] ? htmlspecialchars($incident['laboratorio_nombre']) : 'Área general'; ?>
                                            </p>
                                            <small class="text-muted">
                                                Severidad: <?php echo $incident['nivel_severidad']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-warning">
                                                <?php echo $incident['estado']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($incident['fecha_incidente']); ?>
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
        <!-- Control de acceso -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-key"></i> Control de Acceso Hoy</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($access_control)): ?>
                        <p class="text-muted">No hay registros de acceso hoy</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($access_control as $access): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($access['nombres'] . ' ' . $access['apellido_paterno']); ?>
                                            </h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($access['area_acceso']); ?></p>
                                            <small class="text-muted">
                                                <?php echo $access['tipo_acceso']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $access['acceso_autorizado'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $access['acceso_autorizado'] ? 'Autorizado' : 'Denegado'; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($access['fecha_acceso'])); ?>
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
        
        <!-- Últimos reportes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-file-alt"></i> Últimos Reportes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_reports)): ?>
                        <p class="text-muted">No hay reportes recientes</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($recent_reports as $report): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($report['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars(substr($report['descripcion'], 0, 100)) . '...'; ?></p>
                                            <small class="text-muted">
                                                <?php echo $report['area_reporte']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info"><?php echo $report['tipo_reporte']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($report['fecha_reporte']); ?>
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
