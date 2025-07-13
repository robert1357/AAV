<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de personal administrativo
check_permission(['ADMINISTRATIVO']);

$page_title = 'Dashboard - Personal Administrativo';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Personal Administrativo']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos del personal administrativo
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas administrativas
$stats = [];

// Documentos procesados este mes
$sql = "SELECT COUNT(*) as total FROM documentos_administrativos 
        WHERE procesado_por = ? AND MONTH(fecha_procesamiento) = MONTH(NOW()) AND YEAR(fecha_procesamiento) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['documents_processed'] = $stmt->fetch()['total'];

// Archivos gestionados
$sql = "SELECT COUNT(*) as total FROM archivos_gestion 
        WHERE gestionado_por = ? AND MONTH(fecha_gestion) = MONTH(NOW()) AND YEAR(fecha_gestion) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['files_managed'] = $stmt->fetch()['total'];

// Comunicaciones enviadas
$sql = "SELECT COUNT(*) as total FROM comunicaciones_internas 
        WHERE enviado_por = ? AND MONTH(fecha_envio) = MONTH(NOW()) AND YEAR(fecha_envio) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['communications_sent'] = $stmt->fetch()['total'];

// Tareas pendientes
$sql = "SELECT COUNT(*) as total FROM tareas_administrativas 
        WHERE asignado_a = ? AND estado = 'PENDIENTE'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$stats['pending_tasks'] = $stmt->fetch()['total'];

// Tareas administrativas asignadas
$sql = "SELECT * FROM tareas_administrativas
        WHERE asignado_a = ? AND estado IN ('PENDIENTE', 'EN_PROCESO')
        ORDER BY fecha_limite ASC, prioridad DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$assigned_tasks = $stmt->fetchAll();

// Documentos por procesar
$sql = "SELECT da.*, td.nombre as tipo_nombre
        FROM documentos_administrativos da
        JOIN tipos_documento td ON da.tipo_documento = td.id_tipo
        WHERE da.estado = 'PENDIENTE' AND da.area_responsable = 'ADMINISTRATIVO'
        ORDER BY da.fecha_recepcion ASC
        LIMIT 15";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$pending_documents = $stmt->fetchAll();

// Últimas comunicaciones internas
$sql = "SELECT ci.*, p.nombres, p.apellido_paterno
        FROM comunicaciones_internas ci
        LEFT JOIN personal p ON ci.destinatario_id = p.id_personal
        WHERE ci.enviado_por = ?
        ORDER BY ci.fecha_envio DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$recent_communications = $stmt->fetchAll();

// Archivos gestionados recientemente
$sql = "SELECT * FROM archivos_gestion
        WHERE gestionado_por = ?
        ORDER BY fecha_gestion DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$recent_files = $stmt->fetchAll();

// Solicitudes de soporte
$sql = "SELECT ss.*, p.nombres, p.apellido_paterno
        FROM solicitudes_soporte ss
        JOIN personal p ON ss.solicitante_id = p.id_personal
        WHERE ss.tipo_soporte = 'ADMINISTRATIVO' AND ss.estado = 'PENDIENTE'
        ORDER BY ss.fecha_solicitud ASC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$support_requests = $stmt->fetchAll();

// Estadísticas de productividad
$sql = "SELECT 
            COUNT(CASE WHEN estado = 'COMPLETADO' THEN 1 END) as completadas,
            COUNT(CASE WHEN estado = 'PENDIENTE' THEN 1 END) as pendientes,
            COUNT(CASE WHEN estado = 'EN_PROCESO' THEN 1 END) as en_proceso,
            COUNT(*) as total
        FROM tareas_administrativas
        WHERE asignado_a = ? AND MONTH(fecha_asignacion) = MONTH(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$productivity_stats = $stmt->fetch();

// Calendario de actividades
$sql = "SELECT * FROM calendario_administrativo
        WHERE fecha_actividad >= CURDATE() AND fecha_actividad <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY fecha_actividad ASC, hora_inicio ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$upcoming_activities = $stmt->fetchAll();

// Documentos más procesados
$sql = "SELECT td.nombre, COUNT(da.id_documento) as total
        FROM documentos_administrativos da
        JOIN tipos_documento td ON da.tipo_documento = td.id_tipo
        WHERE da.procesado_por = ? AND da.fecha_procesamiento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY da.tipo_documento
        ORDER BY total DESC
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$most_processed_docs = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header del personal administrativo -->
    <div class="admin-header">
        <h1><i class="fas fa-briefcase"></i> Panel Personal Administrativo</h1>
        <p>Gestión administrativa, documentación y apoyo institucional</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="number"><?php echo $stats['documents_processed']; ?></div>
            <div class="label">Documentos Procesados</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-folder"></i>
            </div>
            <div class="number"><?php echo $stats['files_managed']; ?></div>
            <div class="label">Archivos Gestionados</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-paper-plane"></i>
            </div>
            <div class="number"><?php echo $stats['communications_sent']; ?></div>
            <div class="label">Comunicaciones Enviadas</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="number"><?php echo $stats['pending_tasks']; ?></div>
            <div class="label">Tareas Pendientes</div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión Administrativa</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="documentation.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-file-alt d-block mb-2"></i>
                                Documentación
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="general_admin.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-cog d-block mb-2"></i>
                                Admin General
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="admin_support.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-hands-helping d-block mb-2"></i>
                                Apoyo Admin
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="../secretaria/dashboard.php" class="btn btn-outline-primary w-100">
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
        <!-- Tareas asignadas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-clipboard-list"></i> Tareas Asignadas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assigned_tasks)): ?>
                        <p class="text-muted">No tiene tareas asignadas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($assigned_tasks as $task): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($task['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($task['descripcion']); ?></p>
                                            <small class="text-muted">
                                                Límite: <?php echo format_date($task['fecha_limite']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $task['estado'] === 'PENDIENTE' ? 'bg-warning' : 'bg-info'; ?>">
                                                <?php echo $task['estado']; ?>
                                            </span>
                                            <br>
                                            <span class="badge <?php echo $task['prioridad'] === 'ALTA' ? 'bg-danger' : ($task['prioridad'] === 'MEDIA' ? 'bg-warning' : 'bg-success'); ?>">
                                                <?php echo $task['prioridad']; ?>
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
        
        <!-- Documentos pendientes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-file-alt text-warning"></i> Documentos Pendientes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_documents)): ?>
                        <p class="text-success">No hay documentos pendientes</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($pending_documents as $doc): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($doc['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($doc['tipo_nombre']); ?></p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($doc['remitente']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-warning">Pendiente</span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($doc['fecha_recepcion']); ?>
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
        <!-- Estadísticas de productividad -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Productividad Este Mes</h5>
                </div>
                <div class="card-body">
                    <?php if ($productivity_stats['total'] > 0): ?>
                        <div class="mb-3">
                            <strong>Completadas:</strong>
                            <span class="badge bg-success"><?php echo $productivity_stats['completadas']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>En Proceso:</strong>
                            <span class="badge bg-info"><?php echo $productivity_stats['en_proceso']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Pendientes:</strong>
                            <span class="badge bg-warning"><?php echo $productivity_stats['pendientes']; ?></span>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-success" style="width: <?php echo ($productivity_stats['completadas'] / $productivity_stats['total']) * 100; ?>%">
                                <?php echo round(($productivity_stats['completadas'] / $productivity_stats['total']) * 100, 1); ?>%
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No hay datos de productividad</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Documentos más procesados -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Documentos Más Procesados</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($most_processed_docs)): ?>
                        <p class="text-muted">No hay datos disponibles</p>
                    <?php else: ?>
                        <?php foreach ($most_processed_docs as $doc): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo htmlspecialchars($doc['nombre']); ?></span>
                                    <span class="badge bg-primary"><?php echo $doc['total']; ?></span>
                                </div>
                                <div class="progress mt-1" style="height: 5px;">
                                    <div class="progress-bar" style="width: <?php echo ($doc['total'] / max(array_column($most_processed_docs, 'total'))) * 100; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Próximas actividades -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-alt"></i> Próximas Actividades</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_activities)): ?>
                        <p class="text-muted">No hay actividades programadas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($upcoming_activities as $activity): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['titulo']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($activity['hora_inicio'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <?php echo format_date($activity['fecha_actividad']); ?>
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
        <!-- Comunicaciones recientes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-paper-plane"></i> Comunicaciones Recientes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_communications)): ?>
                        <p class="text-muted">No hay comunicaciones recientes</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($recent_communications as $comm): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($comm['asunto']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars(substr($comm['mensaje'], 0, 100)) . '...'; ?></p>
                                            <small class="text-muted">
                                                Para: <?php echo htmlspecialchars($comm['nombres'] . ' ' . $comm['apellido_paterno']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info"><?php echo $comm['tipo']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($comm['fecha_envio']); ?>
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
        
        <!-- Solicitudes de soporte -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-hands-helping"></i> Solicitudes de Soporte</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($support_requests)): ?>
                        <p class="text-muted">No hay solicitudes de soporte</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($support_requests as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($request['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($request['descripcion']); ?></p>
                                            <small class="text-muted">
                                                De: <?php echo htmlspecialchars($request['nombres'] . ' ' . $request['apellido_paterno']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-warning">Pendiente</span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($request['fecha_solicitud']); ?>
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
