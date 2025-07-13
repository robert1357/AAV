<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DOCENTE') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Calificar Tareas - Docente";

// Procesar calificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calificar_entrega'])) {
    try {
        $pdo->beginTransaction();
        
        // Actualizar calificación y observaciones
        $stmt = $pdo->prepare("
            UPDATE entregas_tareas 
            SET calificacion = ?, 
                observaciones_docente = ?, 
                fecha_calificacion = NOW(),
                estado = 'CALIFICADO'
            WHERE id_entrega = ?
        ");
        
        $stmt->execute([
            $_POST['calificacion'],
            $_POST['observaciones_docente'],
            $_POST['id_entrega']
        ]);
        
        // Obtener información para registrar nota en el sistema
        $stmt = $pdo->prepare("
            SELECT 
                et.id_entrega,
                t.puntos_maximos,
                m.id_estudiante,
                e.codigo_estudiante,
                c.codigo as curso_codigo,
                a.anio
            FROM entregas_tareas et
            JOIN tareas t ON et.id_tarea = t.id_tarea
            JOIN matriculas m ON et.id_matricula = m.id_matricula
            JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
            JOIN asignaciones asig ON t.id_asignacion = asig.id_asignacion
            JOIN cursos c ON asig.id_curso = c.id_curso
            JOIN anios_academicos a ON asig.id_anio = a.id_anio
            WHERE et.id_entrega = ?
        ");
        $stmt->execute([$_POST['id_entrega']]);
        $entrega_info = $stmt->fetch();
        
        // Convertir calificación de tarea a nota vigesimal (opcional)
        $nota_vigesimal = ($_POST['calificacion'] / $entrega_info['puntos_maximos']) * 20;
        
        $pdo->commit();
        $success_message = "Tarea calificada exitosamente";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al calificar tarea: " . $e->getMessage();
    }
}

// Obtener tareas del docente para calificar
$filtro_curso = $_GET['curso'] ?? '';
$filtro_estado = $_GET['estado'] ?? 'pendientes';

$sql = "
    SELECT 
        t.id_tarea,
        t.titulo,
        t.fecha_limite,
        t.puntos_maximos,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        COUNT(et.id_entrega) as total_entregas,
        COUNT(CASE WHEN et.estado = 'CALIFICADO' THEN 1 END) as entregas_calificadas,
        COUNT(CASE WHEN et.estado = 'ENTREGADO' THEN 1 END) as entregas_pendientes
    FROM tareas t
    JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea
    WHERE a.id_personal = ?
";

$params = [$_SESSION['user_id']];

if ($filtro_curso) {
    $sql .= " AND c.id_curso = ?";
    $params[] = $filtro_curso;
}

$sql .= " GROUP BY t.id_tarea";

if ($filtro_estado === 'pendientes') {
    $sql .= " HAVING entregas_pendientes > 0";
} elseif ($filtro_estado === 'completadas') {
    $sql .= " HAVING entregas_pendientes = 0 AND total_entregas > 0";
}

$sql .= " ORDER BY t.fecha_limite DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tareas = $stmt->fetchAll();

// Obtener cursos del docente para filtro
$stmt = $pdo->prepare("
    SELECT DISTINCT c.*
    FROM cursos c
    JOIN asignaciones a ON c.id_curso = a.id_curso
    WHERE a.id_personal = ?
    ORDER BY c.nombre
");
$stmt->execute([$_SESSION['user_id']]);
$cursos_docente = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clipboard-check"></i> Calificar Tareas
                    </h3>
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
                        <div class="col-md-4">
                            <label for="curso" class="form-label">Filtrar por Curso</label>
                            <select name="curso" id="curso" class="form-select" onchange="aplicarFiltros()">
                                <option value="">Todos los cursos</option>
                                <?php foreach ($cursos_docente as $curso): ?>
                                    <option value="<?= $curso['id_curso'] ?>" <?= $filtro_curso == $curso['id_curso'] ? 'selected' : '' ?>>
                                        [<?= $curso['codigo'] ?>] <?= htmlspecialchars($curso['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="estado" class="form-label">Estado de Calificación</label>
                            <select name="estado" id="estado" class="form-select" onchange="aplicarFiltros()">
                                <option value="todas" <?= $filtro_estado === 'todas' ? 'selected' : '' ?>>Todas las tareas</option>
                                <option value="pendientes" <?= $filtro_estado === 'pendientes' ? 'selected' : '' ?>>Con entregas pendientes</option>
                                <option value="completadas" <?= $filtro_estado === 'completadas' ? 'selected' : '' ?>>Totalmente calificadas</option>
                            </select>
                        </div>
                    </div>

                    <!-- Lista de Tareas -->
                    <?php if (!empty($tareas)): ?>
                        <div class="row">
                            <?php foreach ($tareas as $tarea): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header">
                                            <h6 class="card-title mb-1">
                                                <?= htmlspecialchars($tarea['titulo']) ?>
                                            </h6>
                                            <small class="text-muted">
                                                [<?= $tarea['curso_codigo'] ?>] <?= htmlspecialchars($tarea['curso_nombre']) ?>
                                            </small>
                                        </div>
                                        <div class="card-body">
                                            <div class="row text-center mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Fecha límite</small>
                                                    <strong><?= date('d/m/Y', strtotime($tarea['fecha_limite'])) ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Puntos máx.</small>
                                                    <strong><?= $tarea['puntos_maximos'] ?> pts</strong>
                                                </div>
                                            </div>
                                            
                                            <div class="progress mb-3" style="height: 25px;">
                                                <?php 
                                                $porcentaje_calificadas = $tarea['total_entregas'] > 0 ? 
                                                    ($tarea['entregas_calificadas'] / $tarea['total_entregas']) * 100 : 0;
                                                ?>
                                                <div class="progress-bar bg-success" style="width: <?= $porcentaje_calificadas ?>%">
                                                    <?= round($porcentaje_calificadas, 1) ?>%
                                                </div>
                                            </div>
                                            
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
                                            <button class="btn btn-primary btn-sm w-100" 
                                                    onclick="verEntregas(<?= $tarea['id_tarea'] ?>)">
                                                <i class="fas fa-edit"></i> Calificar Entregas
                                                <?php if ($tarea['entregas_pendientes'] > 0): ?>
                                                    <span class="badge bg-warning text-dark ms-1"><?= $tarea['entregas_pendientes'] ?></span>
                                                <?php endif; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                            <h5>No hay tareas para calificar</h5>
                            <p class="text-muted">
                                <?php if ($filtro_estado !== 'todas' || $filtro_curso): ?>
                                    No se encontraron tareas con los filtros aplicados.
                                <?php else: ?>
                                    Aún no tienes tareas con entregas para calificar.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para calificar entrega -->
<div class="modal fade" id="calificarEntregaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formCalificarEntrega">
                <div class="modal-header">
                    <h5 class="modal-title">Calificar Entrega</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_entrega" id="modal_id_entrega">
                    
                    <div id="detalle-entrega-contenido">
                        <!-- Contenido cargado dinámicamente -->
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="calificacion" class="form-label">Calificación *</label>
                                <input type="number" name="calificacion" id="calificacion" 
                                       class="form-control form-control-lg text-center" 
                                       min="0" step="0.5" required>
                                <div class="form-text">
                                    Puntos obtenidos (de <span id="puntos-maximos">0</span>)
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="observaciones_docente" class="form-label">Observaciones del Docente</label>
                                <textarea name="observaciones_docente" id="observaciones_docente" 
                                          class="form-control" rows="4"
                                          placeholder="Escriba sus comentarios y sugerencias para el estudiante..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="calificar_entrega" class="btn btn-success">
                        <i class="fas fa-check"></i> Guardar Calificación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para ver entregas de una tarea -->
<div class="modal fade" id="entregasTareaModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Entregas de la Tarea</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="entregas-contenido">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>
</div>

<script>
function aplicarFiltros() {
    const curso = document.getElementById('curso').value;
    const estado = document.getElementById('estado').value;
    
    const params = new URLSearchParams();
    if (curso) params.append('curso', curso);
    if (estado) params.append('estado', estado);
    
    window.location.href = 'grade_assignments.php?' + params.toString();
}

function verEntregas(idTarea) {
    fetch(`../api/get_task_submissions.php?id_tarea=${idTarea}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('entregas-contenido').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('entregasTareaModal')).show();
            } else {
                alert('Error al cargar las entregas');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar las entregas');
        });
}

function calificarEntrega(idEntrega, puntosMaximos) {
    fetch(`../api/get_submission_details.php?id_entrega=${idEntrega}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modal_id_entrega').value = idEntrega;
                document.getElementById('detalle-entrega-contenido').innerHTML = data.html;
                document.getElementById('puntos-maximos').textContent = puntosMaximos;
                document.getElementById('calificacion').setAttribute('max', puntosMaximos);
                
                // Cargar calificación existente si la hay
                if (data.entrega.calificacion) {
                    document.getElementById('calificacion').value = data.entrega.calificacion;
                    document.getElementById('observaciones_docente').value = data.entrega.observaciones_docente || '';
                }
                
                new bootstrap.Modal(document.getElementById('calificarEntregaModal')).show();
            } else {
                alert('Error al cargar los detalles de la entrega');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los detalles');
        });
}

// Actualizar calificación vigesimal en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const calificacionInput = document.getElementById('calificacion');
    if (calificacionInput) {
        calificacionInput.addEventListener('input', function() {
            const puntos = parseFloat(this.value) || 0;
            const puntosMaximos = parseFloat(document.getElementById('puntos-maximos').textContent) || 20;
            const notaVigesimal = (puntos / puntosMaximos) * 20;
            
            // Mostrar equivalencia vigesimal si existe un elemento para ello
            const equivalenciaElement = document.getElementById('equivalencia-vigesimal');
            if (equivalenciaElement) {
                equivalenciaElement.textContent = notaVigesimal.toFixed(1) + '/20';
                equivalenciaElement.className = 'badge ' + (notaVigesimal >= 14 ? 'bg-success' : notaVigesimal >= 11 ? 'bg-warning' : 'bg-danger');
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>