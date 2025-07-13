<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de CIST
check_permission(['CIST']);

$page_title = 'Dashboard - CIST';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard CIST']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos del CIST
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas del CIST
$stats = [];

// Sistemas activos
$sql = "SELECT COUNT(*) as total FROM sistemas_informaticos WHERE estado = 'ACTIVO'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['active_systems'] = $stmt->fetch()['total'];

// Innovaciones en desarrollo
$sql = "SELECT COUNT(*) as total FROM innovaciones_tecnologicas 
        WHERE coordinador_id = ? AND estado IN ('EN_DESARROLLO', 'PILOTO')";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['innovations_development'] = $stmt->fetch()['total'];

// Capacitaciones digitales este mes
$sql = "SELECT COUNT(*) as total FROM capacitaciones_digitales 
        WHERE coordinador_id = ? AND MONTH(fecha_capacitacion) = MONTH(NOW()) AND YEAR(fecha_capacitacion) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['digital_trainings'] = $stmt->fetch()['total'];

// Incidentes técnicos resueltos
$sql = "SELECT COUNT(*) as total FROM incidentes_tecnicos 
        WHERE resuelto_por = ? AND estado = 'RESUELTO' AND MONTH(fecha_resolucion) = MONTH(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['resolved_incidents'] = $stmt->fetch()['total'];

// Sistemas informáticos bajo gestión
$sql = "SELECT si.*, COUNT(it.id_incidente) as incidentes_activos
        FROM sistemas_informaticos si
        LEFT JOIN incidentes_tecnicos it ON si.id_sistema = it.id_sistema 
               AND it.estado = 'ABIERTO'
        WHERE si.responsable_id = ?
        GROUP BY si.id_sistema
        ORDER BY si.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$managed_systems = $stmt->fetchAll();

// Innovaciones tecnológicas en desarrollo
$sql = "SELECT it.*, COUNT(DISTINCT pit.id_personal) as equipo_desarrollo
        FROM innovaciones_tecnologicas it
        LEFT JOIN participantes_innovacion_tech pit ON it.id_innovacion = pit.id_innovacion
        WHERE it.coordinador_id = ? AND it.estado IN ('EN_DESARROLLO', 'PILOTO', 'IMPLEMENTACION')
        GROUP BY it.id_innovacion
        ORDER BY it.fecha_inicio DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$tech_innovations = $stmt->fetchAll();

// Infraestructura tecnológica
$sql = "SELECT tipo_infraestructura, COUNT(*) as total, 
               SUM(CASE WHEN estado = 'OPERATIVO' THEN 1 ELSE 0 END) as operativos
        FROM infraestructura_tecnologica
        GROUP BY tipo_infraestructura
        ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tech_infrastructure = $stmt->fetchAll();

// Soluciones desarrolladas
$sql = "SELECT sd.*, it.titulo as innovacion_titulo
        FROM soluciones_desarrolladas sd
        LEFT JOIN innovaciones_tecnologicas it ON sd.id_innovacion = it.id_innovacion
        WHERE sd.desarrollador_id = ?
        ORDER BY sd.fecha_desarrollo DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$developed_solutions = $stmt->fetchAll();

// Próximas capacitaciones digitales
$sql = "SELECT * FROM capacitaciones_digitales
        WHERE coordinador_id = ? AND fecha_capacitacion >= CURDATE()
        ORDER BY fecha_capacitacion ASC
        LIMIT 8";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$upcoming_digital_trainings = $stmt->fetchAll();

// Incidentes técnicos pendientes
$sql = "SELECT it.*, si.nombre as sistema_nombre, p.nombres, p.apellido_paterno
        FROM incidentes_tecnicos it
        LEFT JOIN sistemas_informaticos si ON it.id_sistema = si.id_sistema
        LEFT JOIN personal p ON it.reportado_por = p.id_personal
        WHERE it.estado IN ('ABIERTO', 'EN_PROGRESO') AND it.asignado_a = ?
        ORDER BY it.prioridad DESC, it.fecha_reporte ASC
        LIMIT 15";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$pending_incidents = $stmt->fetchAll();

// Reportes tecnológicos recientes
$sql = "SELECT * FROM reportes_tecnologicos
        WHERE generado_por = ?
        ORDER BY fecha_generacion DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$tech_reports = $stmt->fetchAll();

// Mantenimiento de equipos programado
$sql = "SELECT met.*, et.nombre as equipo_nombre
        FROM mantenimientos_equipos_tech met
        JOIN equipos_tecnologicos et ON met.id_equipo = et.id_equipo
        WHERE met.responsable_id = ? AND met.fecha_programada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        AND met.estado = 'PROGRAMADO'
        ORDER BY met.fecha_programada ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$scheduled_tech_maintenance = $stmt->fetchAll();

// Evolución de incidentes por mes
$sql = "SELECT MONTH(fecha_reporte) as mes, COUNT(*) as total_incidentes,
               SUM(CASE WHEN estado = 'RESUELTO' THEN 1 ELSE 0 END) as resueltos
        FROM incidentes_tecnicos
        WHERE YEAR(fecha_reporte) = YEAR(NOW()) AND asignado_a = ?
        GROUP BY MONTH(fecha_reporte)
        ORDER BY mes";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$incidents_evolution = $stmt->fetchAll();

// Personal técnico coordinado
$sql = "SELECT p.nombres, p.apellido_paterno, p.apellido_materno, p.cargo,
               COUNT(DISTINCT ad.id_asignacion) as asignaciones_daip
        FROM personal p
        LEFT JOIN asignaciones_daip ad ON p.id_personal = ad.id_personal
        WHERE p.cargo IN ('DOCENTE') AND ad.activo = 1
        GROUP BY p.id_personal
        ORDER BY p.apellido_paterno";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tech_staff = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header del CIST -->
    <div class="admin-header">
        <h1><i class="fas fa-microchip"></i> Panel CIST</h1>
        <p>Coordinación de Innovación y Soporte Tecnológico</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-server"></i>
            </div>
            <div class="number"><?php echo $stats['active_systems']; ?></div>
            <div class="label">Sistemas Activos</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-rocket"></i>
            </div>
            <div class="number"><?php echo $stats['innovations_development']; ?></div>
            <div class="label">Innovaciones en Desarrollo</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="number"><?php echo $stats['digital_trainings']; ?></div>
            <div class="label">Capacitaciones Digitales</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="number"><?php echo $stats['resolved_incidents']; ?></div>
            <div class="label">Incidentes Resueltos</div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Coordinación Tecnológica</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="system_support.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-life-ring d-block mb-2"></i>
                                Soporte del Sistema
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="innovation.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-lightbulb d-block mb-2"></i>
                                Innovación
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="digital_training.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-laptop d-block mb-2"></i>
                                Capacitación Digital
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="tech_reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-line d-block mb-2"></i>
                                Reportes Tech
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="infrastructure.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-network-wired d-block mb-2"></i>
                                Infraestructura
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="../admin/settings.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-cog d-block mb-2"></i>
                                Configuración
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Sistemas informáticos -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-server"></i> Sistemas Informáticos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($managed_systems)): ?>
                        <p class="text-muted">No hay sistemas asignados</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Sistema</th>
                                        <th>Versión</th>
                                        <th>Estado</th>
                                        <th>Incidentes</th>
                                        <th>Última Actualización</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($managed_systems as $system): ?>
                                        <tr>
                                            <td>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($system['nombre']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($system['descripcion']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($system['version']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $system['estado'] === 'ACTIVO' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo $system['estado']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($system['incidentes_activos'] > 0): ?>
                                                    <span class="badge bg-danger"><?php echo $system['incidentes_activos']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo format_date($system['fecha_actualizacion']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Infraestructura tecnológica -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-network-wired"></i> Infraestructura</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tech_infrastructure)): ?>
                        <p class="text-muted">No hay datos de infraestructura</p>
                    <?php else: ?>
                        <?php foreach ($tech_infrastructure as $infra): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo htmlspecialchars($infra['tipo_infraestructura']); ?></span>
                                    <span class="badge bg-primary"><?php echo $infra['total']; ?></span>
                                </div>
                                <div class="progress mt-1" style="height: 8px;">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?php echo ($infra['operativos'] / $infra['total']) * 100; ?>%">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $infra['operativos']; ?> de <?php echo $infra['total']; ?> operativos
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <!-- Innovaciones tecnológicas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-rocket"></i> Innovaciones en Desarrollo</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tech_innovations)): ?>
                        <p class="text-muted">No hay innovaciones en desarrollo</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($tech_innovations as $innovation): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($innovation['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($innovation['descripcion']); ?></p>
                                            <small class="text-muted">
                                                Equipo: <?php echo $innovation['equipo_desarrollo']; ?> personas
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $innovation['estado'] === 'IMPLEMENTACION' ? 'bg-success' : 'bg-info'; ?>">
                                                <?php echo $innovation['estado']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($innovation['fecha_inicio']); ?>
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
                    <h5><i class="fas fa-exclamation-triangle text-warning"></i> Incidentes Pendientes</h5>
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
                                            <h6 class="mb-1"><?php echo htmlspecialchars($incident['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($incident['descripcion']); ?></p>
                                            <small class="text-muted">
                                                Sistema: <?php echo htmlspecialchars($incident['sistema_nombre']); ?> |
                                                Por: <?php echo htmlspecialchars($incident['nombres'] . ' ' . $incident['apellido_paterno']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $incident['prioridad'] === 'ALTA' ? 'bg-danger' : ($incident['prioridad'] === 'MEDIA' ? 'bg-warning' : 'bg-info'); ?>">
                                                <?php echo $incident['prioridad']; ?>
                                            </span>
                                            <br>
                                            <span class="badge bg-warning"><?php echo $incident['estado']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($incident['fecha_reporte']); ?>
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
        <!-- Próximas capacitaciones -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-alt"></i> Próximas Capacitaciones</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_digital_trainings)): ?>
                        <p class="text-muted">No hay capacitaciones programadas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($upcoming_digital_trainings as $training): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($training['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($training['descripcion']); ?></p>
                                            <small class="text-muted">
                                                Dirigido a: <?php echo htmlspecialchars($training['dirigido_a']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info"><?php echo $training['modalidad']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($training['fecha_capacitacion']); ?>
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
        
        <!-- Soluciones desarrolladas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-code"></i> Soluciones Desarrolladas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($developed_solutions)): ?>
                        <p class="text-muted">No hay soluciones desarrolladas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($developed_solutions as $solution): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($solution['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($solution['descripcion']); ?></p>
                                            <small class="text-muted">
                                                <?php echo $solution['innovacion_titulo'] ? 'Parte de: ' . htmlspecialchars($solution['innovacion_titulo']) : 'Solución independiente'; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success"><?php echo $solution['estado']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($solution['fecha_desarrollo']); ?>
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
    
    <!-- Mantenimiento programado -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-wrench"></i> Mantenimiento Tecnológico Programado</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($scheduled_tech_maintenance)): ?>
                        <p class="text-muted">No hay mantenimientos programados</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Equipo</th>
                                        <th>Tipo Mantenimiento</th>
                                        <th>Fecha Programada</th>
                                        <th>Duración Estimada</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scheduled_tech_maintenance as $maintenance): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($maintenance['equipo_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($maintenance['tipo_mantenimiento']); ?></td>
                                            <td><?php echo format_date($maintenance['fecha_programada']); ?></td>
                                            <td><?php echo $maintenance['duracion_estimada']; ?> horas</td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $maintenance['estado']; ?></span>
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
