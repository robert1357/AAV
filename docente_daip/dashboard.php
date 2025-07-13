<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de docente DAIP
check_permission(['DOCENTE']);

$page_title = 'Dashboard - Docente DAIP';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Docente DAIP']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Verificar que el docente esté asignado al DAIP
$db = new Database();
$pdo = $db->getConnection();

// Verificar asignación DAIP
$sql = "SELECT * FROM asignaciones_daip WHERE id_personal = ? AND activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$daip_assignment = $stmt->fetch();

if (!$daip_assignment) {
    redirect('../teacher/dashboard.php?error=sin_asignacion_daip');
}

// Estadísticas DAIP
$stats = [];

// Recursos digitales creados
$sql = "SELECT COUNT(*) as total FROM recursos_digitales 
        WHERE creado_por = ? AND MONTH(fecha_creacion) = MONTH(NOW()) AND YEAR(fecha_creacion) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['digital_resources'] = $stmt->fetch()['total'];

// Capacitaciones realizadas
$sql = "SELECT COUNT(*) as total FROM capacitaciones_tecnologicas 
        WHERE facilitador_id = ? AND MONTH(fecha_capacitacion) = MONTH(NOW()) AND YEAR(fecha_capacitacion) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['tech_trainings'] = $stmt->fetch()['total'];

// Proyectos de innovación activos
$sql = "SELECT COUNT(*) as total FROM proyectos_innovacion 
        WHERE coordinador_id = ? AND estado = 'ACTIVO'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['active_projects'] = $stmt->fetch()['total'];

// Soporte técnico brindado
$sql = "SELECT COUNT(*) as total FROM soporte_tecnologico 
        WHERE atendido_por = ? AND MONTH(fecha_atencion) = MONTH(NOW()) AND YEAR(fecha_atencion) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['tech_support'] = $stmt->fetch()['total'];

// Recursos digitales más utilizados
$sql = "SELECT rd.*, COUNT(urd.id_uso) as total_usos
        FROM recursos_digitales rd
        LEFT JOIN uso_recursos_digitales urd ON rd.id_recurso = urd.id_recurso
        WHERE rd.creado_por = ? AND urd.fecha_uso >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY rd.id_recurso
        ORDER BY total_usos DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$popular_resources = $stmt->fetchAll();

// Proyectos de innovación en desarrollo
$sql = "SELECT pi.*, COUNT(DISTINCT pp.id_personal) as participantes
        FROM proyectos_innovacion pi
        LEFT JOIN participantes_proyecto pp ON pi.id_proyecto = pp.id_proyecto
        WHERE pi.coordinador_id = ? AND pi.estado IN ('ACTIVO', 'EN_DESARROLLO')
        GROUP BY pi.id_proyecto
        ORDER BY pi.fecha_inicio DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$innovation_projects = $stmt->fetchAll();

// Próximas capacitaciones
$sql = "SELECT * FROM capacitaciones_tecnologicas
        WHERE facilitador_id = ? AND fecha_capacitacion >= CURDATE()
        ORDER BY fecha_capacitacion ASC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$upcoming_trainings = $stmt->fetchAll();

// Solicitudes de soporte pendientes
$sql = "SELECT st.*, p.nombres, p.apellido_paterno, p.cargo
        FROM soporte_tecnologico st
        JOIN personal p ON st.solicitante_id = p.id_personal
        WHERE st.estado = 'PENDIENTE' AND st.area_soporte = 'DAIP'
        ORDER BY st.fecha_solicitud ASC
        LIMIT 15";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$pending_support = $stmt->fetchAll();

// Equipos tecnológicos bajo responsabilidad
$sql = "SELECT et.*, COUNT(mt.id_mantenimiento) as mantenimientos_pendientes
        FROM equipos_tecnologicos et
        LEFT JOIN mantenimientos_tecnologicos mt ON et.id_equipo = mt.id_equipo 
               AND mt.estado = 'PENDIENTE'
        WHERE et.responsable_id = ?
        GROUP BY et.id_equipo
        ORDER BY et.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$tech_equipment = $stmt->fetchAll();

// Uso de plataformas digitales
$sql = "SELECT pd.nombre_plataforma, COUNT(upd.id_uso) as total_usos,
               COUNT(DISTINCT upd.id_usuario) as usuarios_unicos
        FROM plataformas_digitales pd
        LEFT JOIN uso_plataformas_digitales upd ON pd.id_plataforma = upd.id_plataforma
        WHERE upd.fecha_uso >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY pd.id_plataforma
        ORDER BY total_usos DESC
        LIMIT 8";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$platform_usage = $stmt->fetchAll();

// Capacitaciones por área
$sql = "SELECT ct.area_dirigida, COUNT(*) as total_capacitaciones,
               COUNT(DISTINCT ct.id_capacitacion) as capacitaciones_unicas
        FROM capacitaciones_tecnologicas ct
        WHERE ct.facilitador_id = ? AND ct.fecha_capacitacion >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY ct.area_dirigida
        ORDER BY total_capacitaciones DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$trainings_by_area = $stmt->fetchAll();

// Innovaciones implementadas
$sql = "SELECT ii.*, pi.titulo as proyecto_titulo
        FROM innovaciones_implementadas ii
        JOIN proyectos_innovacion pi ON ii.id_proyecto = pi.id_proyecto
        WHERE pi.coordinador_id = ? AND ii.fecha_implementacion >= DATE_SUB(NOW(), INTERVAL 180 DAY)
        ORDER BY ii.fecha_implementacion DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$implemented_innovations = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header del docente DAIP -->
    <div class="admin-header">
        <h1><i class="fas fa-laptop-code"></i> Panel Docente DAIP</h1>
        <p>Aula de Innovación Pedagógica - Recursos digitales y tecnología educativa</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-cloud"></i>
            </div>
            <div class="number"><?php echo $stats['digital_resources']; ?></div>
            <div class="label">Recursos Digitales</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="number"><?php echo $stats['tech_trainings']; ?></div>
            <div class="label">Capacitaciones</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div class="number"><?php echo $stats['active_projects']; ?></div>
            <div class="label">Proyectos Activos</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-headset"></i>
            </div>
            <div class="number"><?php echo $stats['tech_support']; ?></div>
            <div class="label">Soporte Técnico</div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión DAIP</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="digital_resources.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-cloud d-block mb-2"></i>
                                Recursos Digitales
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="innovation_projects.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-lightbulb d-block mb-2"></i>
                                Proyectos Innovación
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="technology_training.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-graduation-cap d-block mb-2"></i>
                                Capacitación Tech
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="daip_reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-bar d-block mb-2"></i>
                                Reportes DAIP
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="tech_support.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-headset d-block mb-2"></i>
                                Soporte Técnico
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="../cist/dashboard.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-link d-block mb-2"></i>
                                Coordinación CIST
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Proyectos de innovación -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-lightbulb"></i> Proyectos de Innovación</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($innovation_projects)): ?>
                        <p class="text-muted">No hay proyectos de innovación activos</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($innovation_projects as $project): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($project['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($project['descripcion']); ?></p>
                                            <small class="text-muted">
                                                <?php echo $project['participantes']; ?> participantes
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $project['estado'] === 'ACTIVO' ? 'bg-success' : 'bg-info'; ?>">
                                                <?php echo $project['estado']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($project['fecha_inicio']); ?>
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
        
        <!-- Recursos digitales populares -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-star"></i> Recursos Más Utilizados</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($popular_resources)): ?>
                        <p class="text-muted">No hay datos de uso de recursos</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($popular_resources as $resource): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($resource['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($resource['descripcion']); ?></p>
                                            <small class="text-muted">
                                                <?php echo $resource['tipo_recurso']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary">
                                                <?php echo $resource['total_usos']; ?> usos
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($resource['fecha_creacion']); ?>
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
                    <?php if (empty($upcoming_trainings)): ?>
                        <p class="text-muted">No hay capacitaciones programadas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($upcoming_trainings as $training): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($training['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($training['descripcion']); ?></p>
                                            <small class="text-muted">
                                                Dirigido a: <?php echo htmlspecialchars($training['area_dirigida']); ?>
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
        
        <!-- Soporte técnico pendiente -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-headset text-warning"></i> Soporte Pendiente</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_support)): ?>
                        <p class="text-success">No hay solicitudes de soporte pendientes</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($pending_support as $support): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($support['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($support['descripcion']); ?></p>
                                            <small class="text-muted">
                                                De: <?php echo htmlspecialchars($support['nombres'] . ' ' . $support['apellido_paterno']); ?> 
                                                (<?php echo $support['cargo']; ?>)
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-warning">Pendiente</span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($support['fecha_solicitud']); ?>
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
        <!-- Equipos tecnológicos -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-laptop"></i> Equipos Tecnológicos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tech_equipment)): ?>
                        <p class="text-muted">No hay equipos asignados</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Equipo</th>
                                        <th>Estado</th>
                                        <th>Mant.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tech_equipment as $equipment): ?>
                                        <tr>
                                            <td>
                                                <small><?php echo htmlspecialchars($equipment['nombre']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $equipment['estado'] === 'OPERATIVO' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo $equipment['estado']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($equipment['mantenimientos_pendientes'] > 0): ?>
                                                    <span class="badge bg-danger"><?php echo $equipment['mantenimientos_pendientes']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">0</span>
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
        
        <!-- Uso de plataformas -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line"></i> Uso de Plataformas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($platform_usage)): ?>
                        <p class="text-muted">No hay datos de uso</p>
                    <?php else: ?>
                        <?php foreach ($platform_usage as $usage): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="small"><?php echo htmlspecialchars($usage['nombre_plataforma']); ?></span>
                                    <span class="badge bg-primary"><?php echo $usage['total_usos']; ?></span>
                                </div>
                                <div class="progress mt-1" style="height: 5px;">
                                    <div class="progress-bar" style="width: <?php echo ($usage['total_usos'] / max(array_column($platform_usage, 'total_usos'))) * 100; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $usage['usuarios_unicos']; ?> usuarios únicos</small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Capacitaciones por área -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Capacitaciones por Área</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($trainings_by_area)): ?>
                        <p class="text-muted">No hay datos de capacitaciones</p>
                    <?php else: ?>
                        <?php foreach ($trainings_by_area as $training): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="small"><?php echo htmlspecialchars($training['area_dirigida']); ?></span>
                                    <span class="badge bg-info"><?php echo $training['total_capacitaciones']; ?></span>
                                </div>
                                <div class="progress mt-1" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo ($training['total_capacitaciones'] / max(array_column($trainings_by_area, 'total_capacitaciones'))) * 100; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
