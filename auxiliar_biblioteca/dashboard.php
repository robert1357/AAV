<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos de auxiliar de biblioteca
check_permission(['AUXILIAR_BIBLIOTECA']);

$page_title = 'Dashboard - Auxiliar de Biblioteca';
$admin_styles = true;
$show_breadcrumb = true;
$breadcrumb_pages = [
    ['name' => 'Dashboard Auxiliar Biblioteca']
];

$current_user = get_current_user_info();
$user_id = $_SESSION['user_id'];

// Obtener datos del auxiliar
$db = new Database();
$pdo = $db->getConnection();

// Estadísticas de la biblioteca
$stats = [];

// Total de libros
$sql = "SELECT COUNT(*) as total FROM libros WHERE activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['total_books'] = $stmt->fetch()['total'];

// Préstamos activos
$sql = "SELECT COUNT(*) as total FROM prestamos_libros WHERE estado = 'ACTIVO'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['active_loans'] = $stmt->fetch()['total'];

// Préstamos vencidos
$sql = "SELECT COUNT(*) as total FROM prestamos_libros 
        WHERE estado = 'ACTIVO' AND fecha_devolucion < CURDATE()";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['overdue_loans'] = $stmt->fetch()['total'];

// Libros catalogados este mes
$sql = "SELECT COUNT(*) as total FROM libros 
        WHERE MONTH(fecha_registro) = MONTH(NOW()) AND YEAR(fecha_registro) = YEAR(NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats['cataloged_this_month'] = $stmt->fetch()['total'];

// Libros más prestados
$sql = "SELECT l.titulo, l.autor, l.isbn, COUNT(pl.id_prestamo) as total_prestamos
        FROM libros l
        LEFT JOIN prestamos_libros pl ON l.id_libro = pl.id_libro
        WHERE l.activo = 1 AND pl.fecha_prestamo >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY l.id_libro
        ORDER BY total_prestamos DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$most_borrowed_books = $stmt->fetchAll();

// Préstamos por devolver
$sql = "SELECT pl.*, l.titulo, l.autor, e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante
        FROM prestamos_libros pl
        JOIN libros l ON pl.id_libro = l.id_libro
        JOIN estudiantes e ON pl.id_estudiante = e.id_estudiante
        WHERE pl.estado = 'ACTIVO' AND pl.fecha_devolucion <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        ORDER BY pl.fecha_devolucion ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$loans_due_soon = $stmt->fetchAll();

// Préstamos vencidos
$sql = "SELECT pl.*, l.titulo, l.autor, e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante
        FROM prestamos_libros pl
        JOIN libros l ON pl.id_libro = l.id_libro
        JOIN estudiantes e ON pl.id_estudiante = e.id_estudiante
        WHERE pl.estado = 'ACTIVO' AND pl.fecha_devolucion < CURDATE()
        ORDER BY pl.fecha_devolucion ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$overdue_loans = $stmt->fetchAll();

// Libros por área académica
$sql = "SELECT area_academica, COUNT(*) as total
        FROM libros
        WHERE activo = 1
        GROUP BY area_academica
        ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$books_by_area = $stmt->fetchAll();

// Últimas adquisiciones
$sql = "SELECT * FROM libros
        WHERE activo = 1
        ORDER BY fecha_registro DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$recent_acquisitions = $stmt->fetchAll();

// Actividades de promoción de lectura
$sql = "SELECT * FROM actividades_lectura
        WHERE fecha_actividad >= CURDATE()
        ORDER BY fecha_actividad ASC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$reading_activities = $stmt->fetchAll();

// Estadísticas de préstamos por mes
$sql = "SELECT MONTH(fecha_prestamo) as mes, COUNT(*) as total
        FROM prestamos_libros
        WHERE YEAR(fecha_prestamo) = YEAR(NOW())
        GROUP BY MONTH(fecha_prestamo)
        ORDER BY mes";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$loans_by_month = $stmt->fetchAll();

// Usuarios más activos
$sql = "SELECT e.nombres, e.apellido_paterno, e.apellido_materno, e.codigo_estudiante,
               COUNT(pl.id_prestamo) as total_prestamos
        FROM estudiantes e
        JOIN prestamos_libros pl ON e.id_estudiante = pl.id_estudiante
        WHERE pl.fecha_prestamo >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY e.id_estudiante
        ORDER BY total_prestamos DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$most_active_users = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="admin-fade-in">
    <!-- Header del auxiliar -->
    <div class="admin-header">
        <h1><i class="fas fa-book-open"></i> Panel Auxiliar de Biblioteca</h1>
        <p>Gestión bibliográfica y promoción de la lectura</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-books"></i>
            </div>
            <div class="number"><?php echo $stats['total_books']; ?></div>
            <div class="label">Total de Libros</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-hand-holding"></i>
            </div>
            <div class="number"><?php echo $stats['active_loans']; ?></div>
            <div class="label">Préstamos Activos</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="number"><?php echo $stats['overdue_loans']; ?></div>
            <div class="label">Préstamos Vencidos</div>
        </div>
        
        <div class="admin-stat-card">
            <div class="icon">
                <i class="fas fa-plus-circle"></i>
            </div>
            <div class="number"><?php echo $stats['cataloged_this_month']; ?></div>
            <div class="label">Catalogados Este Mes</div>
        </div>
    </div>
    
    <!-- Navegación rápida -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Gestión Bibliotecaria</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="book_management.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-book d-block mb-2"></i>
                                Gestión de Libros
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="lending.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-handshake d-block mb-2"></i>
                                Préstamos
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="cataloging.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-tags d-block mb-2"></i>
                                Catalogación
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="reading_promotion.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-heart d-block mb-2"></i>
                                Promoción Lectura
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Préstamos por devolver -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-undo text-warning"></i> Préstamos Por Devolver</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($loans_due_soon)): ?>
                        <p class="text-muted">No hay préstamos próximos a vencer</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($loans_due_soon as $loan): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($loan['titulo']); ?></h6>
                                            <p class="mb-1 small">
                                                <?php echo htmlspecialchars($loan['nombres'] . ' ' . $loan['apellido_paterno'] . ' ' . $loan['apellido_materno']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo $loan['codigo_estudiante']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $loan['fecha_devolucion'] < date('Y-m-d') ? 'bg-danger' : 'bg-warning'; ?>">
                                                <?php echo $loan['fecha_devolucion'] < date('Y-m-d') ? 'Vencido' : 'Próximo'; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($loan['fecha_devolucion']); ?>
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
        
        <!-- Libros más prestados -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-star"></i> Libros Más Prestados</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($most_borrowed_books)): ?>
                        <p class="text-muted">No hay datos de préstamos</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($most_borrowed_books as $book): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($book['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($book['autor']); ?></p>
                                            <small class="text-muted">
                                                ISBN: <?php echo htmlspecialchars($book['isbn']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary">
                                                <?php echo $book['total_prestamos']; ?> préstamos
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
        <!-- Libros por área académica -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Libros por Área Académica</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($books_by_area)): ?>
                        <p class="text-muted">No hay datos disponibles</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Área</th>
                                        <th>Cantidad</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($books_by_area as $area): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($area['area_academica']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $area['total']; ?></span></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo ($area['total'] / $stats['total_books']) * 100; ?>%">
                                                        <?php echo round(($area['total'] / $stats['total_books']) * 100, 1); ?>%
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
        
        <!-- Usuarios más activos -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-friends"></i> Usuarios Más Activos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($most_active_users)): ?>
                        <p class="text-muted">No hay datos de usuarios</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($most_active_users as $user): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($user['nombres'] . ' ' . $user['apellido_paterno'] . ' ' . $user['apellido_materno']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo $user['codigo_estudiante']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success">
                                                <?php echo $user['total_prestamos']; ?> préstamos
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
        <!-- Últimas adquisiciones -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-plus-circle"></i> Últimas Adquisiciones</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_acquisitions)): ?>
                        <p class="text-muted">No hay adquisiciones recientes</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($recent_acquisitions as $book): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($book['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($book['autor']); ?></p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($book['editorial']); ?> - <?php echo $book['anio_publicacion']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info"><?php echo $book['area_academica']; ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo format_date($book['fecha_registro']); ?>
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
        
        <!-- Actividades de promoción -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-heart"></i> Actividades de Promoción</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reading_activities)): ?>
                        <p class="text-muted">No hay actividades programadas</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($reading_activities as $activity): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['titulo']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($activity['descripcion']); ?></p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($activity['dirigido_a']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary"><?php echo $activity['tipo_actividad']; ?></span>
                                            <br>
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
</div>

<?php include '../includes/footer.php'; ?>
