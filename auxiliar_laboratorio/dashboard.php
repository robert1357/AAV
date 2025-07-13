<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de auxiliar de laboratorio
check_permission(['AUXILIAR_LABORATORIO']);

$page_title = 'Dashboard - Auxiliar de Laboratorio';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Auxiliar Laboratorio']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos del auxiliar
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas del laboratorio
$stats = [];

// Laboratorios asignados
$sql = "SELECT COUNT(*) as total FROM auxiliares_laboratorio WHERE id_personal = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['assigned_labs'] = $stmt->fetch()['total'];

// Equipos en mantenimiento
$sql = "SELECT COUNT(*) as total FROM equipos_laboratorio el
        JOIN auxiliares_laboratorio al ON el.id_laboratorio = al.id_laboratorio
        WHERE al.id_personal = ? AND el.estado = 'MANTENIMIENTO'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['equipment_maintenance'] = $stmt->fetch()['total'];

// Prácticas programadas esta semana
$sql = "SELECT COUNT(*) as total FROM practicas_laboratorio pl
        JOIN auxiliares_laboratorio al ON pl.id_laboratorio = al.id_laboratorio
        WHERE al.id_personal = ? AND pl.fecha_practica BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['practices_this_week'] = $stmt->fetch()['total'];

// Inventario bajo stock
$sql = "SELECT COUNT(*) as total FROM inventario_laboratorio il
        JOIN auxiliares_laboratorio al ON il.id_laboratorio = al.id_laboratorio
        WHERE al.id_personal = ? AND il.cantidad_actual <= il.cantidad_minima";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['low_stock_items'] = $stmt->fetch()['total'];

// Laboratorios asignados con detalles
$sql = "SELECT l.*, al.fecha_asignacion, al.responsabilidades,
               COUNT(DISTINCT el.id_equipo) as total_equipos,
               COUNT(DISTINCT il.id_item) as total_items
        FROM laboratorios l
        JOIN auxiliares_laboratorio al ON l.id_laboratorio = al.id_laboratorio
        LEFT JOIN equipos_laboratorio el ON l.id_laboratorio = el.id_laboratorio
        LEFT JOIN inventario_laboratorio il ON l.id_laboratorio = il.id_laboratorio
        WHERE al.id_personal = ?
        GROUP BY l.id_laboratorio
        ORDER BY l.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$assigned_laboratories = $stmt->fetchAll();

// Equipos que requieren mantenimiento
$sql = "SELECT el.*, l.nombre as laboratorio_nombre
        FROM equipos_laboratorio el
        JOIN laboratorios l ON el.id_laboratorio = l.id_laboratorio
        JOIN auxiliares_laboratorio al ON l.id_laboratorio = al.id_laboratorio
        WHERE al.id_personal = ? AND (el.estado = 'MANTENIMIENTO' OR el.proximo_mantenimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
        ORDER BY el.proximo_mantenimiento ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$equipment_needing_maintenance = $stmt->fetchAll();

// Prácticas programadas
$sql = "SELECT pl.*, l.nombre as laboratorio_nombre, c.nombre as curso_nombre,
               p.nombres as docente_nombres, p.apellido_paterno as docente_apellido
        FROM practicas_laboratorio pl
        JOIN laboratorios l ON pl.id_laboratorio = l.id_laboratorio
        JOIN auxiliares_laboratorio al ON l.id_laboratorio = al.id_laboratorio
        JOIN cursos c ON pl.id_curso = c.id_curso
        LEFT JOIN asignaciones a ON c.id_curso = a.id_curso
        LEFT JOIN personal p ON a.id_personal = p.id_personal
        WHERE al.id_personal = ? AND pl.fecha_practica >= CURDATE()
        ORDER BY pl.fecha_practica ASC, pl.hora_inicio ASC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$scheduled_practices = $stmt->fetchAll();

// Inventario con stock bajo
$sql = "SELECT il.*, l.nombre as laboratorio_nombre
        FROM inventario_laboratorio il
        JOIN laboratorios l ON il.id_laboratorio = l.id_laboratorio
        JOIN auxiliares_laboratorio al ON l.id_laboratorio = al.id_laboratorio
        WHERE al.id_personal = ? AND il.cantidad_actual <= il.cantidad_minima
        ORDER BY (il.cantidad_actual / il.cantidad_minima) ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$low_stock_inventory = $stmt->fetchAll();

// Últimas actividades del laboratorio
$sql = "SELECT 'Práctica' as tipo, pl.titulo as descripcion, pl.fecha_practica as fecha, l.nombre as laboratorio
        FROM practicas_laboratorio pl
        JOIN laboratorios l ON pl.id_laboratorio = l.id_laboratorio
        JOIN auxiliares_laboratorio al ON l.id_laboratorio = al.id_laboratorio
        WHERE al.id_personal = ? AND pl.fecha_practica >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'Mantenimiento' as tipo, CONCAT('Mantenimiento de ', el.nombre) as descripcion, 
               el.fecha_ultimo_mantenimiento as fecha, l.nombre as laboratorio
        FROM equipos_laboratorio el
        JOIN laboratorios l ON el.id_laboratorio = l.id_laboratorio
        JOIN auxiliares_laboratorio al ON l.id_laboratorio = al.id_laboratorio
        WHERE al.id_personal = ? AND el.fecha_ultimo_mantenimiento >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY fecha DESC
        LIMIT 15";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $user_id]);
$recent_activities = $stmt->fetchAll();

// Incidentes de seguridad
$sql = "SELECT is.*, l.nombre as laboratorio_nombre
        FROM incidentes_seguridad is
        JOIN laboratorios l ON is.id_laboratorio = l.id_laboratorio
        JOIN auxiliares_laboratorio al ON l.id_laboratorio = al.id_laboratorio
        WHERE al.id_personal = ? AND is.fecha_incidente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY is.fecha_incidente DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$security_incidents = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header del auxiliar -->
    <div class="admin-header">
        <h1><i class="fas fa-flask"></i> Panel Auxiliar de Laboratorio</h1>
        <p>Apoyo en equipos, mantenimiento y seguridad de laboratorios</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-vials"></i>
            </div>
            <div class="number"><?php echo $stats['assigned_labs']; ?></div>
            <div class="label">Laboratorios Asignados</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-wrench"></i>
            </div>
            <div class="number"><?php echo $stats['equipment_maintenance']; ?></div>
            <div class="label">Equipos en Mantenimiento</div>
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
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="number"><?php echo $stats['low_stock_items']; ?></div>
            <div class="label">Items Stock Bajo</div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión de Laboratorio</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="equipment_support.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-tools d-block mb-2"></i>
                                Apoyo en Equipos
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="maintenance_support.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-wrench d-block mb-2"></i>
                                Apoyo Mantenimiento
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="safety_support.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-shield-alt d-block mb-2"></i>
                                Apoyo Seguridad
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="../jefe_laboratorio/dashboard.php" class="btn btn-outline-primary w-100">
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
        <!-- Laboratorios asignados -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-vials"></i> Laboratorios Asignados</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assigned_laboratories)): ?>
                        <p class="text-muted">No tiene laboratorios asignados</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Laboratorio</th>
                                        <th>Equipos</th>
                                        <th>Items</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_laboratories as $lab): ?>
                                        <tr>
                                            <td>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($lab['nombre']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($lab['ubicacion']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $lab['total_equipos']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $lab['total_items']; ?></span>
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
        
        <!-- Equipos que requieren mantenimiento -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-wrench text-warning"></i> Mantenimiento Requerido</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($equipment_needing_maintenance)): ?>
                        <p class="text-success">No hay equipos que requieran mantenimiento</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($equipment_needing_maintenance as $equipment): ?>
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
                                            <span class="badge <?php echo $equipment['estado'] === 'MANTENIMIENTO' ? 'bg-danger' : 'bg-warning'; ?>">
                                                <?php echo $equipment['estado'] === 'MANTENIMIENTO' ? 'En Mantenimiento' : 'Próximo'; ?>
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
    </div>
    
    <div class="row mt-4">
        <!-- Prácticas programadas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-alt"></i> Prácticas Programadas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($scheduled_practices)): ?>
                        <p class="text-muted">No hay prácticas programadas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($scheduled_practices as $practice): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($practice['titulo']); ?></h6>
                                            <p class="mb-1 small">
                                                <?php echo htmlspecialchars($practice['curso_nombre']); ?> - 
                                                <?php echo htmlspecialchars($practice['laboratorio_nombre']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($practice['docente_nombres'] . ' ' . $practice['docente_apellido']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary">
                                                <?php echo date('H:i', strtotime($practice['hora_inicio'])); ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($practice['fecha_practica']); ?>
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
        
        <!-- Inventario con stock bajo -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-boxes text-danger"></i> Inventario Stock Bajo</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($low_stock_inventory)): ?>
                        <p class="text-success">Inventario en niveles adecuados</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($low_stock_inventory as $item): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($item['nombre_item']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($item['laboratorio_nombre']); ?></p>
                                            <small class="text-muted">
                                                Mínimo: <?php echo $item['cantidad_minima']; ?> <?php echo htmlspecialchars($item['unidad_medida']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-danger">
                                                <?php echo $item['cantidad_actual']; ?> <?php echo htmlspecialchars($item['unidad_medida']); ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo round(($item['cantidad_actual'] / $item['cantidad_minima']) * 100, 0); ?>% del mínimo
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
        <!-- Actividades recientes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Actividades Recientes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activities)): ?>
                        <p class="text-muted">No hay actividades recientes</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['descripcion']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($activity['laboratorio']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info"><?php echo $activity['tipo']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($activity['fecha']); ?>
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
        
        <!-- Incidentes de seguridad -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shield-alt text-warning"></i> Incidentes de Seguridad</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($security_incidents)): ?>
                        <p class="text-success">No hay incidentes de seguridad reportados</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($security_incidents as $incident): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($incident['descripcion']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($incident['laboratorio_nombre']); ?></p>
                                            <small class="text-muted">
                                                Severidad: <?php echo $incident['nivel_severidad']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $incident['estado'] === 'RESUELTO' ? 'bg-success' : 'bg-warning'; ?>">
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
</div>

<?php include '../includes/footer.php'; ?>
