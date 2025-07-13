<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de jefe de laboratorio
check_permission(['JEFE_LABORATORIO']);

$page_title = 'Dashboard - Jefe de Laboratorio';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Jefe Laboratorio']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos del jefe de laboratorio
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas de laboratorios
$stats = [];

// Total de laboratorios
$sql = "SELECT COUNT(*) as total FROM laboratorios WHERE activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['total_labs'] = $stmt->fetch()['total'];

// Equipos que requieren mantenimiento
$sql = "SELECT COUNT(*) as total FROM equipos_laboratorio 
        WHERE estado = 'MANTENIMIENTO' OR proximo_mantenimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['equipment_maintenance'] = $stmt->fetch()['total'];

// Prácticas programadas esta semana
$sql = "SELECT COUNT(*) as total FROM practicas_laboratorio 
        WHERE fecha_practica BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['practices_this_week'] = $stmt->fetch()['total'];

// Incidentes de seguridad este mes
$sql = "SELECT COUNT(*) as total FROM incidentes_seguridad 
        WHERE MONTH(fecha_incidente) = MONTH(NOW()) AND YEAR(fecha_incidente) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['security_incidents'] = $stmt->fetch()['total'];

// Laboratorios con detalles
$sql = "SELECT l.*, 
               COUNT(DISTINCT el.id_equipo) as total_equipos,
               COUNT(DISTINCT pl.id_practica) as practicas_mes,
               COUNT(DISTINCT al.id_personal) as auxiliares_asignados
        FROM laboratorios l
        LEFT JOIN equipos_laboratorio el ON l.id_laboratorio = el.id_laboratorio
        LEFT JOIN practicas_laboratorio pl ON l.id_laboratorio = pl.id_laboratorio 
               AND MONTH(pl.fecha_practica) = MONTH(NOW())
        LEFT JOIN auxiliares_laboratorio al ON l.id_laboratorio = al.id_laboratorio
        WHERE l.activo = 1
        GROUP BY l.id_laboratorio
        ORDER BY l.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$laboratories = $stmt->fetchAll();

// Equipos críticos que requieren atención
$sql = "SELECT el.*, l.nombre as laboratorio_nombre
        FROM equipos_laboratorio el
        JOIN laboratorios l ON el.id_laboratorio = l.id_laboratorio
        WHERE el.estado = 'MANTENIMIENTO' OR el.estado = 'FUERA_SERVICIO' 
        OR el.proximo_mantenimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY el.proximo_mantenimiento ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$critical_equipment = $stmt->fetchAll();

// Horarios de laboratorio para hoy
$sql = "SELECT hl.*, l.nombre as laboratorio_nombre, c.nombre as curso_nombre,
               p.nombres as docente_nombres, p.apellido_paterno as docente_apellido
        FROM horarios_laboratorio hl
        JOIN laboratorios l ON hl.id_laboratorio = l.id_laboratorio
        JOIN cursos c ON hl.id_curso = c.id_curso
        LEFT JOIN asignaciones a ON c.id_curso = a.id_curso
        LEFT JOIN personal p ON a.id_personal = p.id_personal
        WHERE hl.dia_semana = DAYOFWEEK(CURDATE())
        ORDER BY hl.hora_inicio";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$today_lab_schedule = $stmt->fetchAll();

// Próximas prácticas programadas
$sql = "SELECT pl.*, l.nombre as laboratorio_nombre, c.nombre as curso_nombre,
               p.nombres as docente_nombres, p.apellido_paterno as docente_apellido
        FROM practicas_laboratorio pl
        JOIN laboratorios l ON pl.id_laboratorio = l.id_laboratorio
        JOIN cursos c ON pl.id_curso = c.id_curso
        LEFT JOIN asignaciones a ON c.id_curso = a.id_curso
        LEFT JOIN personal p ON a.id_personal = p.id_personal
        WHERE pl.fecha_practica >= CURDATE()
        ORDER BY pl.fecha_practica ASC, pl.hora_inicio ASC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$upcoming_practices = $stmt->fetchAll();

// Inventario con stock crítico
$sql = "SELECT il.*, l.nombre as laboratorio_nombre
        FROM inventario_laboratorio il
        JOIN laboratorios l ON il.id_laboratorio = l.id_laboratorio
        WHERE il.cantidad_actual <= il.cantidad_minima
        ORDER BY (il.cantidad_actual / il.cantidad_minima) ASC
        LIMIT 15";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$critical_inventory = $stmt->fetchAll();

// Personal del laboratorio
$sql = "SELECT p.nombres, p.apellido_paterno, p.apellido_materno, p.cargo,
               COUNT(DISTINCT al.id_laboratorio) as labs_asignados
        FROM personal p
        LEFT JOIN auxiliares_laboratorio al ON p.id_personal = al.id_personal
        WHERE p.cargo IN ('AUXILIAR_LABORATORIO', 'JEFE_LABORATORIO') AND p.activo = 1
        GROUP BY p.id_personal
        ORDER BY p.cargo, p.apellido_paterno";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$lab_staff = $stmt->fetchAll();

// Protocolos de seguridad pendientes
$sql = "SELECT ps.*, l.nombre as laboratorio_nombre
        FROM protocolos_seguridad ps
        JOIN laboratorios l ON ps.id_laboratorio = l.id_laboratorio
        WHERE ps.estado = 'PENDIENTE_REVISION' OR ps.fecha_revision < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        ORDER BY ps.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$pending_protocols = $stmt->fetchAll();

// Mantenimientos programados
$sql = "SELECT mp.*, el.nombre as equipo_nombre, l.nombre as laboratorio_nombre
        FROM mantenimientos_programados mp
        JOIN equipos_laboratorio el ON mp.id_equipo = el.id_equipo
        JOIN laboratorios l ON el.id_laboratorio = l.id_laboratorio
        WHERE mp.fecha_programada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        AND mp.estado = 'PROGRAMADO'
        ORDER BY mp.fecha_programada ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$scheduled_maintenance = $stmt->fetchAll();

// Utilización de laboratorios
$sql = "SELECT l.nombre, l.capacidad_maxima,
               COUNT(pl.id_practica) as practicas_mes,
               ROUND((COUNT(pl.id_practica) * 2 / 30) * 100, 1) as porcentaje_uso
        FROM laboratorios l
        LEFT JOIN practicas_laboratorio pl ON l.id_laboratorio = pl.id_laboratorio
               AND pl.fecha_practica >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        WHERE l.activo = 1
        GROUP BY l.id_laboratorio
        ORDER BY porcentaje_uso DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$lab_utilization = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header del jefe de laboratorio -->
    <div class="admin-header">
        <h1><i class="fas fa-microscope"></i> Panel Jefe de Laboratorio</h1>
        <p>Gestión integral de laboratorios, equipos y protocolos de seguridad</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-vials"></i>
            </div>
            <div class="number"><?php echo $stats['total_labs']; ?></div>
            <div class="label">Laboratorios Activos</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-wrench"></i>
            </div>
            <div class="number"><?php echo $stats['equipment_maintenance']; ?></div>
            <div class="label">Equipos Mantenimiento</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="number"><?php echo $stats['practices_this_week']; ?></div>
            <div class="label">Prácticas Esta Semana</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="number"><?php echo $stats['security_incidents']; ?></div>
            <div class="label">Incidentes Este Mes</div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión de Laboratorios</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="equipment_management.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-tools d-block mb-2"></i>
                                Gestión Equipos
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="lab_schedule.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-calendar d-block mb-2"></i>
                                Horarios Lab
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="safety_protocols.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-shield-alt d-block mb-2"></i>
                                Protocolos
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="maintenance.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-wrench d-block mb-2"></i>
                                Mantenimiento
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="lab_reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-line d-block mb-2"></i>
                                Reportes
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="inventory.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-boxes d-block mb-2"></i>
                                Inventario
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Estado de laboratorios -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-vials"></i> Estado de Laboratorios</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($laboratories)): ?>
                        <p class="text-muted">No hay laboratorios registrados</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Laboratorio</th>
                                        <th>Equipos</th>
                                        <th>Prácticas/Mes</th>
                                        <th>Auxiliares</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($laboratories as $lab): ?>
                                        <tr>
                                            <td>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($lab['nombre']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($lab['ubicacion']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $lab['total_equipos']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $lab['practicas_mes']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $lab['auxiliares_asignados']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">Activo</span>
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
        
        <!-- Utilización de laboratorios -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Utilización (30 días)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($lab_utilization)): ?>
                        <p class="text-muted">No hay datos de utilización</p>
                    <?php else: ?>
                        <?php foreach ($lab_utilization as $util): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo htmlspecialchars($util['nombre']); ?></span>
                                    <span class="badge bg-primary"><?php echo $util['porcentaje_uso']; ?>%</span>
                                </div>
                                <div class="progress mt-1" style="height: 8px;">
                                    <div class="progress-bar" style="width: <?php echo $util['porcentaje_uso']; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $util['practicas_mes']; ?> prácticas</small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <!-- Equipos críticos -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-triangle text-warning"></i> Equipos Críticos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($critical_equipment)): ?>
                        <p class="text-success">No hay equipos críticos</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($critical_equipment as $equipment): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($equipment['nombre']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($equipment['laboratorio_nombre']); ?></p>
                                            <small class="text-muted">
                                                Código: <?php echo htmlspecialchars($equipment['codigo']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $equipment['estado'] === 'FUERA_SERVICIO' ? 'bg-danger' : 'bg-warning'; ?>">
                                                <?php echo $equipment['estado']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($equipment['proximo_mantenimiento']); ?>
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
        
        <!-- Inventario crítico -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-boxes text-danger"></i> Inventario Crítico</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($critical_inventory)): ?>
                        <p class="text-success">Inventario en niveles normales</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($critical_inventory as $item): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($item['nombre_item']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($item['laboratorio_nombre']); ?></p>
                                            <small class="text-muted">
                                                Min: <?php echo $item['cantidad_minima']; ?> <?php echo htmlspecialchars($item['unidad_medida']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-danger">
                                                <?php echo $item['cantidad_actual']; ?> <?php echo htmlspecialchars($item['unidad_medida']); ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo round(($item['cantidad_actual'] / $item['cantidad_minima']) * 100, 0); ?>%
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
        <!-- Horario de hoy -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-clock"></i> Horario de Laboratorios Hoy</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($today_lab_schedule)): ?>
                        <p class="text-muted">No hay actividades programadas para hoy</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($today_lab_schedule as $schedule): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($schedule['laboratorio_nombre']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($schedule['curso_nombre']); ?></p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($schedule['docente_nombres'] . ' ' . $schedule['docente_apellido']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary">
                                                <?php echo date('H:i', strtotime($schedule['hora_inicio'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['hora_fin'])); ?>
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
        
        <!-- Mantenimientos programados -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-check"></i> Mantenimientos Programados</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($scheduled_maintenance)): ?>
                        <p class="text-muted">No hay mantenimientos programados</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($scheduled_maintenance as $maintenance): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($maintenance['equipo_nombre']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($maintenance['laboratorio_nombre']); ?></p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($maintenance['tipo_mantenimiento']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-warning">Programado</span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($maintenance['fecha_programada']); ?>
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
    
    <!-- Personal del laboratorio -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users"></i> Personal de Laboratorio</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($lab_staff)): ?>
                        <p class="text-muted">No hay personal de laboratorio registrado</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Cargo</th>
                                        <th>Laboratorios Asignados</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lab_staff as $staff): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($staff['nombres'] . ' ' . $staff['apellido_paterno'] . ' ' . $staff['apellido_materno']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $staff['cargo'] === 'JEFE_LABORATORIO' ? 'bg-primary' : 'bg-secondary'; ?>">
                                                    <?php echo ROLES[$staff['cargo']] ?? $staff['cargo']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $staff['labs_asignados']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">Activo</span>
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
