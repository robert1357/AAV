<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DOCENTE') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Gestión de Tareas - Docente";

// Procesar creación de tarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_tarea'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO tareas (
                id_asignacion, titulo, descripcion, instrucciones, 
                tipo, fecha_limite, puntos_maximos, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVA')
        ");
        
        $stmt->execute([
            $_POST['id_asignacion'],
            $_POST['titulo'],
            $_POST['descripcion'],
            $_POST['instrucciones'],
            $_POST['tipo'],
            $_POST['fecha_limite'],
            $_POST['puntos_maximos']
        ]);
        
        $pdo->commit();
        $success_message = "Tarea creada exitosamente";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al crear tarea: " . $e->getMessage();
    }
}

// Obtener asignaciones del docente
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        g.numero_grado,
        s.letra_seccion,
        an.anio
    FROM asignaciones a
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos an ON a.id_anio = an.id_anio
    WHERE a.id_personal = ? AND a.estado = 'ACTIVO' AND an.estado = 'ACTIVO'
    ORDER BY c.nombre, g.numero_grado, s.letra_seccion
");
$stmt->execute([$_SESSION['user_id']]);
$asignaciones = $stmt->fetchAll();

// Obtener tareas del docente
$filtro_asignacion = $_GET['asignacion'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$sql = "
    SELECT 
        t.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        g.numero_grado,
        s.letra_seccion,
        COUNT(et.id_entrega) as total_entregas,
        COUNT(CASE WHEN et.estado = 'ENTREGADO' THEN 1 END) as entregas_pendientes,
        COUNT(CASE WHEN et.estado = 'CALIFICADO' THEN 1 END) as entregas_calificadas,
        DATEDIFF(t.fecha_limite, NOW()) as dias_restantes
    FROM tareas t
    JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea
    WHERE a.id_personal = ?
";

$params = [$_SESSION['user_id']];

if ($filtro_asignacion) {
    $sql .= " AND a.id_asignacion = ?";
    $params[] = $filtro_asignacion;
}

if ($filtro_estado) {
    switch ($filtro_estado) {
        case 'activas':
            $sql .= " AND t.fecha_limite >= NOW() AND t.estado = 'ACTIVA'";
            break;
        case 'vencidas':
            $sql .= " AND t.fecha_limite < NOW()";
            break;
        case 'pendientes_calificar':
            $sql .= " AND EXISTS (SELECT 1 FROM entregas_tareas et2 WHERE et2.id_tarea = t.id_tarea AND et2.estado = 'ENTREGADO')";
            break;
    }
}

$sql .= " GROUP BY t.id_tarea ORDER BY t.fecha_limite DESC, t.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tareas = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-tasks"></i> Gestión de Tareas
                    </h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaTareaModal">
                        <i class="fas fa-plus"></i> Nueva Tarea
                    </button>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $success_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filtros -->
                    <div class="row mb-4">
                        <div class="col-md-5">
                            <label for="asignacion" class="form-label">Filtrar por Curso</label>
                            <select name="asignacion" id="asignacion" class="form-select" onchange="aplicarFiltros()">
                                <option value="">Todos los cursos</option>
                                <?php foreach ($asignaciones as $asig): ?>
                                    <option value="<?= $asig['id_asignacion'] ?>" <?= $filtro_asignacion == $asig['id_asignacion'] ? 'selected' : '' ?>>
                                        [<?= $asig['curso_codigo'] ?>] <?= htmlspecialchars($asig['curso_nombre']) ?> - 
                                        <?= $asig['numero_grado'] ?>° <?= $asig['letra_seccion'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="estado" class="form-label">Estado</label>
                            <select name="estado" id="estado" class="form-select" onchange="aplicarFiltros()">
                                <option value="">Todas las tareas</option>
                                <option value="activas" <?= $filtro_estado === 'activas' ? 'selected' : '' ?>>Activas</option>
                                <option value="vencidas" <?= $filtro_estado === 'vencidas' ? 'selected' : '' ?>>Vencidas</option>
                                <option value="pendientes_calificar" <?= $filtro_estado === 'pendientes_calificar' ? 'selected' : '' ?>>Pendientes de calificar</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <a href="assignments.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpiar filtros
                            </a>
                        </div>
                    </div>

                    <!-- Lista de tareas -->
                    <?php if (!empty($tareas)): ?>
                        <div class="row">
                            <?php foreach ($tareas as $tarea): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100 <?= getTareaCardClass($tarea) ?>">
                                        <div class="card-header d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1">
                                                    <?= htmlspecialchars($tarea['titulo']) ?>
                                                </h6>
                                                <small class="text-muted">
                                                    [<?= $tarea['curso_codigo'] ?>] <?= htmlspecialchars($tarea['curso_nombre']) ?>
                                                    <br><?= $tarea['numero_grado'] ?>° <?= $tarea['letra_seccion'] ?>
                                                </small>
                                            </div>
                                            <span class="badge <?= getEstadoBadgeClass($tarea) ?>">
                                                <?= getEstadoTexto($tarea) ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text">
                                                <?= nl2br(htmlspecialchars(substr($tarea['descripcion'], 0, 100))) ?>
                                                <?= strlen($tarea['descripcion']) > 100 ? '...' : '' ?>
                                            </p>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Fecha límite</small>
                                                    <strong class="<?= getFechaClass($tarea['dias_restantes']) ?>">
                                                        <?= date('d/m/Y', strtotime($tarea['fecha_limite'])) ?>
                                                    </strong>
                                                    <small class="d-block <?= getFechaClass($tarea['dias_restantes']) ?>">
                                                        <?= getFechaTexto($tarea['dias_restantes']) ?>
                                                    </small>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Puntos</small>
                                                    <strong><?= $tarea['puntos_maximos'] ?> pts</strong>
                                                    <small class="d-block text-muted"><?= ucfirst($tarea['tipo']) ?></small>
                                                </div>
                                            </div>
                                            
                                            <!-- Estadísticas de entregas -->
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <div class="text-info">
                                                        <strong><?= $tarea['total_entregas'] ?></strong>
                                                        <small class="d-block">Total</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-warning">
                                                        <strong><?= $tarea['entregas_pendientes'] ?></strong>
                                                        <small class="d-block">Pendientes</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-success">
                                                        <strong><?= $tarea['entregas_calificadas'] ?></strong>
                                                        <small class="d-block">Calificadas</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        onclick="verTarea(<?= $tarea['id_tarea'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary btn-sm" 
                                                        onclick="editarTarea(<?= $tarea['id_tarea'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-success btn-sm flex-fill" 
                                                        onclick="verEntregas(<?= $tarea['id_tarea'] ?>)">
                                                    <i class="fas fa-clipboard-check"></i> Entregas
                                                    <?php if ($tarea['entregas_pendientes'] > 0): ?>
                                                        <span class="badge bg-warning text-dark ms-1"><?= $tarea['entregas_pendientes'] ?></span>
                                                    <?php endif; ?>
                                                </button>
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
                                <?php if ($filtro_asignacion || $filtro_estado): ?>
                                    No se encontraron tareas con los filtros aplicados.
                                <?php else: ?>
                                    Crea tu primera tarea para comenzar a asignar trabajos a tus estudiantes.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Tarea -->
<div class="modal fade" id="nuevaTareaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formNuevaTarea">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nueva Tarea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="id_asignacion" class="form-label">Curso/Sección *</label>
                        <select name="id_asignacion" id="id_asignacion" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($asignaciones as $asig): ?>
                                <option value="<?= $asig['id_asignacion'] ?>">
                                    [<?= $asig['curso_codigo'] ?>] <?= htmlspecialchars($asig['curso_nombre']) ?> - 
                                    <?= $asig['numero_grado'] ?>° <?= $asig['letra_seccion'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título de la Tarea *</label>
                        <input type="text" name="titulo" id="titulo" class="form-control" required maxlength="200"
                               placeholder="Ej: Ensayo sobre la Independencia del Perú">
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción *</label>
                        <textarea name="descripcion" id="descripcion" class="form-control" rows="4" required
                                  placeholder="Describe detalladamente lo que deben hacer los estudiantes..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="instrucciones" class="form-label">Instrucciones Específicas</label>
                        <textarea name="instrucciones" id="instrucciones" class="form-control" rows="3"
                                  placeholder="Instrucciones adicionales, formato requerido, criterios de evaluación..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="tipo" class="form-label">Tipo *</label>
                                <select name="tipo" id="tipo" class="form-select" required>
                                    <option value="individual">Individual</option>
                                    <option value="grupal">Grupal</option>
                                    <option value="investigacion">Investigación</option>
                                    <option value="practica">Práctica</option>
                                    <option value="ensayo">Ensayo</option>
                                    <option value="proyecto">Proyecto</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="puntos_maximos" class="form-label">Puntos Máximos *</label>
                                <input type="number" name="puntos_maximos" id="puntos_maximos" class="form-control" 
                                       min="1" max="100" value="20" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="fecha_limite" class="form-label">Fecha Límite *</label>
                                <input type="datetime-local" name="fecha_limite" id="fecha_limite" class="form-control" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_tarea" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Tarea
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function aplicarFiltros() {
    const asignacion = document.getElementById('asignacion').value;
    const estado = document.getElementById('estado').value;
    
    const params = new URLSearchParams();
    if (asignacion) params.append('asignacion', asignacion);
    if (estado) params.append('estado', estado);
    
    window.location.href = 'assignments.php?' + params.toString();
}

function verTarea(idTarea) {
    fetch(`../api/get_task_details.php?id=${idTarea}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar modal con detalles
                alert('Vista de detalles en desarrollo');
            } else {
                alert('Error al cargar los detalles');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los detalles');
        });
}

function editarTarea(idTarea) {
    alert('Función de edición en desarrollo');
}

function verEntregas(idTarea) {
    window.location.href = `grade_assignments.php?ver_entregas=${idTarea}`;
}

// Establecer fecha mínima como ahora
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('fecha_limite').min = now.toISOString().slice(0, 16);
    
    // Establecer fecha por defecto (una semana desde ahora)
    const nextWeek = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
    document.getElementById('fecha_limite').value = nextWeek.toISOString().slice(0, 16);
});
</script>

<?php
// Funciones auxiliares
function getTareaCardClass($tarea) {
    if ($tarea['dias_restantes'] < 0) return 'border-danger';
    if ($tarea['dias_restantes'] <= 1) return 'border-warning';
    if ($tarea['entregas_pendientes'] > 0) return 'border-info';
    return '';
}

function getEstadoBadgeClass($tarea) {
    if ($tarea['dias_restantes'] < 0) return 'bg-danger';
    if ($tarea['dias_restantes'] <= 1) return 'bg-warning';
    if ($tarea['entregas_pendientes'] > 0) return 'bg-info';
    return 'bg-success';
}

function getEstadoTexto($tarea) {
    if ($tarea['dias_restantes'] < 0) return 'Vencida';
    if ($tarea['dias_restantes'] <= 1) return 'Próxima a vencer';
    if ($tarea['entregas_pendientes'] > 0) return 'Con entregas';
    return 'Activa';
}

function getFechaClass($dias) {
    if ($dias < 0) return 'text-danger';
    if ($dias <= 1) return 'text-warning';
    return 'text-success';
}

function getFechaTexto($dias) {
    if ($dias < 0) return 'Vencida hace ' . abs($dias) . ' días';
    if ($dias == 0) return 'Vence hoy';
    if ($dias == 1) return 'Vence mañana';
    return 'Faltan ' . $dias . ' días';
}

include '../includes/footer.php'; 
?>