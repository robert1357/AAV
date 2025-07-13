<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Mis Tareas";

// Obtener datos del estudiante
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        m.id_matricula,
        m.id_seccion,
        g.numero_grado,
        s.letra_seccion,
        a.anio,
        a.id_anio
    FROM estudiantes e
    JOIN matriculas m ON e.id_estudiante = m.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos a ON m.id_anio = a.id_anio
    WHERE e.id_estudiante = ? AND m.estado = 'ACTIVO' AND a.estado = 'ACTIVO'
");
$stmt->execute([$_SESSION['user_id']]);
$estudiante = $stmt->fetch();

if (!$estudiante) {
    header('Location: ../auth/login.php?error=sin_matricula');
    exit();
}

// Filtros
$filtro_estado = $_GET['estado'] ?? 'todas';
$filtro_curso = $_GET['curso'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

// Construir query con filtros
$whereConditions = [
    "a.id_seccion = ?",
    "a.id_anio = ?",
    "t.estado = 'ACTIVA'"
];
$params = [$estudiante['id_seccion'], $estudiante['id_anio']];

if ($filtro_curso) {
    $whereConditions[] = "c.id_curso = ?";
    $params[] = $filtro_curso;
}

if ($filtro_fecha) {
    $whereConditions[] = "DATE(t.fecha_limite) = ?";
    $params[] = $filtro_fecha;
}

// Obtener tareas con información de entrega
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        c.color as curso_color,
        CONCAT(p.nombres, ' ', p.apellido_paterno) as docente_nombre,
        et.id_entrega,
        et.fecha_entrega,
        et.estado as estado_entrega,
        et.calificacion,
        et.observaciones_docente,
        DATEDIFF(t.fecha_limite, NOW()) as dias_restantes,
        CASE 
            WHEN et.id_entrega IS NULL THEN 'pendiente'
            WHEN et.estado = 'ENTREGADO' THEN 'entregado'
            WHEN et.estado = 'CALIFICADO' THEN 'calificado'
            WHEN et.estado = 'TARDE' THEN 'tarde'
            ELSE 'pendiente'
        END as estado_tarea
    FROM tareas t
    JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN personal p ON a.id_personal = p.id_personal
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea 
        AND et.id_matricula = ?
    WHERE " . implode(' AND ', $whereConditions) . "
    ORDER BY 
        CASE 
            WHEN et.id_entrega IS NULL AND t.fecha_limite < NOW() THEN 1
            WHEN et.id_entrega IS NULL THEN 2
            WHEN et.estado = 'ENTREGADO' THEN 3
            ELSE 4
        END,
        t.fecha_limite ASC
");

array_unshift($params, $estudiante['id_matricula']);
$stmt->execute($params);
$tareas = $stmt->fetchAll();

// Filtrar por estado si se especifica
if ($filtro_estado !== 'todas') {
    $tareas = array_filter($tareas, function($tarea) use ($filtro_estado) {
        return $tarea['estado_tarea'] === $filtro_estado;
    });
}

// Obtener lista de cursos para filtro
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id_curso, c.nombre, c.codigo
    FROM cursos c
    JOIN asignaciones a ON c.id_curso = a.id_curso
    WHERE a.id_seccion = ? AND a.id_anio = ? AND a.estado = 'ACTIVO'
    ORDER BY c.nombre
");
$stmt->execute([$estudiante['id_seccion'], $estudiante['id_anio']]);
$cursos_filtro = $stmt->fetchAll();

// Estadísticas
$total_tareas = count($tareas);
$tareas_pendientes = count(array_filter($tareas, fn($t) => $t['estado_tarea'] === 'pendiente'));
$tareas_entregadas = count(array_filter($tareas, fn($t) => in_array($t['estado_tarea'], ['entregado', 'calificado'])));
$tareas_tarde = count(array_filter($tareas, fn($t) => $t['estado_tarea'] === 'tarde' || ($t['estado_tarea'] === 'pendiente' && $t['dias_restantes'] < 0)));

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-tasks text-primary"></i> Mis Tareas</h2>
                    <p class="text-muted">
                        <?= $estudiante['numero_grado'] ?>° Grado "<?= $estudiante['letra_seccion'] ?>" • 
                        Año Académico <?= $estudiante['anio'] ?>
                    </p>
                </div>
                <div>
                    <button class="btn btn-outline-primary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-list fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $total_tareas ?></h4>
                    <small class="text-muted">Total Tareas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $tareas_pendientes ?></h4>
                    <small class="text-muted">Pendientes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $tareas_entregadas ?></h4>
                    <small class="text-muted">Entregadas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-danger mb-2">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <h4 class="mb-1"><?= $tareas_tarde ?></h4>
                    <small class="text-muted">Atrasadas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="todas" <?= $filtro_estado === 'todas' ? 'selected' : '' ?>>Todas</option>
                        <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                        <option value="entregado" <?= $filtro_estado === 'entregado' ? 'selected' : '' ?>>Entregadas</option>
                        <option value="calificado" <?= $filtro_estado === 'calificado' ? 'selected' : '' ?>>Calificadas</option>
                        <option value="tarde" <?= $filtro_estado === 'tarde' ? 'selected' : '' ?>>Atrasadas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Curso</label>
                    <select name="curso" class="form-select">
                        <option value="">Todos los cursos</option>
                        <?php foreach ($cursos_filtro as $curso): ?>
                            <option value="<?= $curso['id_curso'] ?>" <?= $filtro_curso == $curso['id_curso'] ? 'selected' : '' ?>>
                                [<?= $curso['codigo'] ?>] <?= htmlspecialchars($curso['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha límite</label>
                    <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <a href="assignments.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Tareas -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list text-primary"></i> Lista de Tareas
                <?php if ($filtro_estado !== 'todas' || $filtro_curso || $filtro_fecha): ?>
                    <small class="text-muted">(Filtrado: <?= count($tareas) ?> resultados)</small>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($tareas)): ?>
                <div class="row">
                    <?php foreach ($tareas as $tarea): ?>
                        <?php
                        // Determinar el estado visual
                        $estado_class = '';
                        $estado_icon = '';
                        $estado_text = '';
                        
                        switch ($tarea['estado_tarea']) {
                            case 'pendiente':
                                if ($tarea['dias_restantes'] < 0) {
                                    $estado_class = 'border-danger';
                                    $estado_icon = 'fas fa-exclamation-triangle text-danger';
                                    $estado_text = 'Vencida';
                                } elseif ($tarea['dias_restantes'] <= 1) {
                                    $estado_class = 'border-warning';
                                    $estado_icon = 'fas fa-clock text-warning';
                                    $estado_text = 'Urgente';
                                } else {
                                    $estado_class = 'border-info';
                                    $estado_icon = 'fas fa-hourglass-half text-info';
                                    $estado_text = 'Pendiente';
                                }
                                break;
                            case 'entregado':
                                $estado_class = 'border-primary';
                                $estado_icon = 'fas fa-paper-plane text-primary';
                                $estado_text = 'Entregada';
                                break;
                            case 'calificado':
                                $estado_class = 'border-success';
                                $estado_icon = 'fas fa-check-circle text-success';
                                $estado_text = 'Calificada';
                                break;
                            case 'tarde':
                                $estado_class = 'border-danger';
                                $estado_icon = 'fas fa-times-circle text-danger';
                                $estado_text = 'Entregada tarde';
                                break;
                        }
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 <?= $estado_class ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span class="badge" style="background-color: <?= $tarea['curso_color'] ?? '#6c757d' ?>">
                                        [<?= $tarea['curso_codigo'] ?>]
                                    </span>
                                    <i class="<?= $estado_icon ?>"></i>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <a href="submit_assignment.php?id=<?= $tarea['id_tarea'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($tarea['titulo']) ?>
                                        </a>
                                    </h6>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <?= htmlspecialchars($tarea['curso_nombre']) ?><br>
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($tarea['docente_nombre']) ?>
                                        </small>
                                    </p>
                                    
                                    <?php if ($tarea['descripcion']): ?>
                                        <p class="card-text">
                                            <?= htmlspecialchars(substr($tarea['descripcion'], 0, 100)) ?>
                                            <?= strlen($tarea['descripcion']) > 100 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> 
                                            <?= date('d/m/Y H:i', strtotime($tarea['fecha_limite'])) ?>
                                        </small>
                                        <span class="badge bg-secondary"><?= $estado_text ?></span>
                                    </div>

                                    <?php if ($tarea['dias_restantes'] >= 0 && $tarea['estado_tarea'] === 'pendiente'): ?>
                                        <div class="alert alert-info py-2 mb-2">
                                            <small>
                                                <i class="fas fa-info-circle"></i>
                                                <?php if ($tarea['dias_restantes'] == 0): ?>
                                                    ¡Vence hoy!
                                                <?php elseif ($tarea['dias_restantes'] == 1): ?>
                                                    Vence mañana
                                                <?php else: ?>
                                                    <?= $tarea['dias_restantes'] ?> días restantes
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php elseif ($tarea['dias_restantes'] < 0 && $tarea['estado_tarea'] === 'pendiente'): ?>
                                        <div class="alert alert-danger py-2 mb-2">
                                            <small>
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Vencida hace <?= abs($tarea['dias_restantes']) ?> días
                                            </small>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($tarea['estado_tarea'] === 'calificado' && $tarea['calificacion']): ?>
                                        <div class="alert alert-success py-2 mb-2">
                                            <small>
                                                <i class="fas fa-star"></i>
                                                Calificación: <strong><?= number_format($tarea['calificacion'], 2) ?></strong>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex gap-2">
                                        <a href="submit_assignment.php?id=<?= $tarea['id_tarea'] ?>" 
                                           class="btn btn-sm btn-primary flex-fill">
                                            <i class="fas fa-eye"></i> Ver Detalles
                                        </a>
                                        <?php if ($tarea['estado_tarea'] === 'pendiente'): ?>
                                            <a href="submit_assignment.php?id=<?= $tarea['id_tarea'] ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-upload"></i> Entregar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                    <h5>No hay tareas disponibles</h5>
                    <p class="text-muted">
                        <?php if ($filtro_estado !== 'todas' || $filtro_curso || $filtro_fecha): ?>
                            No se encontraron tareas con los filtros aplicados.
                        <?php else: ?>
                            Aún no se han asignado tareas para este período académico.
                        <?php endif; ?>
                    </p>
                    <?php if ($filtro_estado !== 'todas' || $filtro_curso || $filtro_fecha): ?>
                        <a href="assignments.php" class="btn btn-primary">
                            <i class="fas fa-times"></i> Quitar Filtros
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.badge {
    font-size: 0.75rem;
}

.alert {
    border: none;
    border-radius: 0.5rem;
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,0.125);
    background-color: rgba(0,0,0,0.03);
}

.text-decoration-none:hover {
    text-decoration: underline !important;
}
</style>

<?php include '../includes/footer.php'; ?>