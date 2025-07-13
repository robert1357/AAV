<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de administrador
check_permission(['ADMIN']);

$page_title = 'Panel de Administración';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Administración']
];

// Obtener estadísticas generales
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas de usuarios
$stats = [];

// Total de estudiantes
$sql = "SELECT COUNT(*) as total FROM estudiantes WHERE activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['total_students'] = $stmt->fetch()['total'];

// Total de personal
$sql = "SELECT COUNT(*) as total FROM personal WHERE activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['total_staff'] = $stmt->fetch()['total'];

// Total de cursos
$sql = "SELECT COUNT(*) as total FROM cursos WHERE activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['total_courses'] = $stmt->fetch()['total'];

// Total de matrículas este año
$sql = "SELECT COUNT(*) as total FROM matriculas m 
        JOIN anios_academicos a ON m.id_anio = a.id_anio 
        WHERE a.anio = YEAR(NOW()) AND m.estado = 'ACTIVO'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['total_enrollments'] = $stmt->fetch()['total'];

// Usuarios por rol
$sql = "SELECT cargo, COUNT(*) as total FROM personal WHERE activo = 1 GROUP BY cargo";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$users_by_role = $stmt->fetchAll();

// Últimas matrículas
$sql = "SELECT e.nombres, e.apellido_paterno, e.apellido_materno, m.fecha_matricula,
               g.numero_grado, s.letra_seccion
        FROM matriculas m
        JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN anios_academicos a ON m.id_anio = a.id_anio
        WHERE a.anio = YEAR(NOW())
        ORDER BY m.fecha_matricula DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$recent_enrollments = $stmt->fetchAll();

// Actividad reciente del sistema
$sql = "SELECT 'Acceso Personal' as tipo, p.nombres, p.apellido_paterno, 
               h.fecha_acceso as fecha, h.resultado
        FROM historial_accesos_personal h
        JOIN personal p ON h.id_personal = p.id_personal
        WHERE h.fecha_acceso >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        UNION ALL
        SELECT 'Acceso Estudiante' as tipo, e.nombres, e.apellido_paterno,
               h.fecha_acceso as fecha, h.resultado
        FROM historial_accesos_estudiantes h
        JOIN estudiantes e ON h.id_estudiante = e.id_estudiante
        WHERE h.fecha_acceso >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY fecha DESC
        LIMIT 15";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$recent_activity = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header de administración -->
    <div class="admin-header">
        <h1><i class="fas fa-cogs"></i> Panel de Administración</h1>
        <p>Gestión completa del sistema de aula virtual</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="number"><?php echo $stats['total_students']; ?></div>
            <div class="label">Estudiantes Activos</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="number"><?php echo $stats['total_staff']; ?></div>
            <div class="label">Personal Activo</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="number"><?php echo $stats['total_courses']; ?></div>
            <div class="label">Cursos Activos</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="number"><?php echo $stats['total_enrollments']; ?></div>
            <div class="label">Matrículas <?php echo date('Y'); ?></div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tachometer-alt"></i> Acceso Rápido</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="manage_students.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-user-graduate d-block mb-2"></i>
                                Estudiantes
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="manage_teachers.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chalkboard-teacher d-block mb-2"></i>
                                Docentes
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="manage_courses.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-book d-block mb-2"></i>
                                Cursos
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="manage_users.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-users-cog d-block mb-2"></i>
                                Usuarios
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-bar d-block mb-2"></i>
                                Reportes
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-3">
                            <a href="settings.php" class="btn btn-outline-primary w-100">
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
        <!-- Distribución de usuarios por rol -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Personal por Rol</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($users_by_role)): ?>
                        <p class="text-muted">No hay datos disponibles</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rol</th>
                                        <th>Cantidad</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users_by_role as $role): ?>
                                        <tr>
                                            <td><?php echo ROLES[$role['cargo']] ?? $role['cargo']; ?></td>
                                            <td><span class="badge bg-primary"><?php echo $role['total']; ?></span></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo ($role['total'] / $stats['total_staff']) * 100; ?>%">
                                                        <?php echo round(($role['total'] / $stats['total_staff']) * 100, 1); ?>%
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
        
        <!-- Últimas matrículas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-plus"></i> Últimas Matrículas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_enrollments)): ?>
                        <p class="text-muted">No hay matrículas recientes</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_enrollments as $enrollment): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($enrollment['nombres'] . ' ' . $enrollment['apellido_paterno'] . ' ' . $enrollment['apellido_materno']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo $enrollment['numero_grado']; ?>° - Sección <?php echo $enrollment['letra_seccion']; ?>
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo format_date($enrollment['fecha_matricula']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Actividad reciente -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Actividad Reciente (24h)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activity)): ?>
                        <p class="text-muted">No hay actividad reciente</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Usuario</th>
                                        <th>Resultado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $activity['tipo']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($activity['nombres'] . ' ' . $activity['apellido_paterno']); ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $activity['resultado'] === 'EXITOSO' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $activity['resultado']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo format_datetime($activity['fecha']); ?></td>
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
