<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DIRECTOR') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Evaluación Docente - Director";

// Obtener docentes activos
$stmt = $pdo->query("SELECT * FROM personal WHERE cargo = 'DOCENTE' AND estado = 'ACTIVO' ORDER BY apellido_paterno");
$docentes = $stmt->fetchAll();

// Obtener años académicos
$stmt = $pdo->query("SELECT * FROM anios_academicos ORDER BY anio DESC");
$anos_academicos = $stmt->fetchAll();

// Procesar evaluación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_evaluacion'])) {
    try {
        $pdo->beginTransaction();
        
        // Llamar al procedimiento almacenado para registrar evaluación
        $stmt = $pdo->prepare("CALL sp_registrar_evaluacion_docente(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['id_docente'],
            $_POST['id_anio'],
            $_POST['puntaje_planificacion'],
            $_POST['puntaje_metodologia'],
            $_POST['puntaje_evaluacion'],
            $_POST['puntaje_interaccion'],
            $_POST['puntaje_recursos'],
            $_POST['observaciones'],
            $_SESSION['user_id'],
            $_POST['fecha_evaluacion']
        ]);
        
        $pdo->commit();
        $success_message = "Evaluación registrada exitosamente";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al registrar evaluación: " . $e->getMessage();
    }
}

// Obtener evaluaciones existentes
$evaluaciones = [];
if (isset($_GET['ver_evaluaciones'])) {
    $anio_filtro = $_GET['anio'] ?? date('Y');
    
    $stmt = $pdo->prepare("
        SELECT 
            ed.*,
            CONCAT(p.nombres, ' ', p.apellido_paterno, ' ', p.apellido_materno) as docente_nombre,
            a.anio,
            CONCAT(pe.nombres, ' ', pe.apellido_paterno) as evaluador_nombre,
            (ed.puntaje_planificacion + ed.puntaje_metodologia + ed.puntaje_evaluacion + 
             ed.puntaje_interaccion + ed.puntaje_recursos) as puntaje_total
        FROM evaluaciones_docentes ed
        JOIN personal p ON ed.id_docente = p.id_personal
        JOIN anios_academicos a ON ed.id_anio = a.id_anio
        JOIN personal pe ON ed.evaluador_id = pe.id_personal
        WHERE a.anio = ?
        ORDER BY ed.fecha_evaluacion DESC
    ");
    $stmt->execute([$anio_filtro]);
    $evaluaciones = $stmt->fetchAll();
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-star"></i> Evaluación Docente
                    </h3>
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaEvaluacionModal">
                            <i class="fas fa-plus"></i> Nueva Evaluación
                        </button>
                        <a href="?ver_evaluaciones=1&anio=<?= date('Y') ?>" class="btn btn-info">
                            <i class="fas fa-list"></i> Ver Evaluaciones
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= $success_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['ver_evaluaciones']) && !empty($evaluaciones)): ?>
                        <div class="mb-3">
                            <form method="GET" class="d-flex align-items-end gap-3">
                                <input type="hidden" name="ver_evaluaciones" value="1">
                                <div>
                                    <label for="anio" class="form-label">Filtrar por Año</label>
                                    <select name="anio" id="anio" class="form-select">
                                        <?php foreach ($anos_academicos as $ano): ?>
                                            <option value="<?= $ano['anio'] ?>" <?= (isset($_GET['anio']) && $_GET['anio'] == $ano['anio']) ? 'selected' : '' ?>>
                                                <?= $ano['anio'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-secondary">Filtrar</button>
                            </form>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Docente</th>
                                        <th>Fecha Evaluación</th>
                                        <th>Planificación</th>
                                        <th>Metodología</th>
                                        <th>Evaluación</th>
                                        <th>Interacción</th>
                                        <th>Recursos</th>
                                        <th>Total</th>
                                        <th>Calificación</th>
                                        <th>Evaluador</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($evaluaciones as $eval): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($eval['docente_nombre']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($eval['fecha_evaluacion'])) ?></td>
                                            <td><?= $eval['puntaje_planificacion'] ?></td>
                                            <td><?= $eval['puntaje_metodologia'] ?></td>
                                            <td><?= $eval['puntaje_evaluacion'] ?></td>
                                            <td><?= $eval['puntaje_interaccion'] ?></td>
                                            <td><?= $eval['puntaje_recursos'] ?></td>
                                            <td><strong><?= $eval['puntaje_total'] ?>/100</strong></td>
                                            <td>
                                                <?php
                                                $calificacion = '';
                                                $clase = '';
                                                if ($eval['puntaje_total'] >= 90) {
                                                    $calificacion = 'Excelente';
                                                    $clase = 'bg-success';
                                                } elseif ($eval['puntaje_total'] >= 80) {
                                                    $calificacion = 'Bueno';
                                                    $clase = 'bg-info';
                                                } elseif ($eval['puntaje_total'] >= 70) {
                                                    $calificacion = 'Regular';
                                                    $clase = 'bg-warning';
                                                } else {
                                                    $calificacion = 'Deficiente';
                                                    $clase = 'bg-danger';
                                                }
                                                ?>
                                                <span class="badge <?= $clase ?>"><?= $calificacion ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($eval['evaluador_nombre']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="verDetalles(<?= $eval['id_evaluacion'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif (isset($_GET['ver_evaluaciones'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hay evaluaciones registradas para el año seleccionado.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <h5>Sistema de Evaluación Docente</h5>
                            <p class="text-muted">Utilice los botones superiores para crear una nueva evaluación o ver evaluaciones existentes.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Evaluación -->
<div class="modal fade" id="nuevaEvaluacionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Evaluación Docente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_docente" class="form-label">Docente</label>
                                <select name="id_docente" id="id_docente" class="form-select" required>
                                    <option value="">Seleccione un docente...</option>
                                    <?php foreach ($docentes as $docente): ?>
                                        <option value="<?= $docente['id_personal'] ?>">
                                            <?= htmlspecialchars($docente['nombres'] . ' ' . $docente['apellido_paterno']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_anio" class="form-label">Año Académico</label>
                                <select name="id_anio" id="id_anio" class="form-select" required>
                                    <?php foreach ($anos_academicos as $ano): ?>
                                        <option value="<?= $ano['id_anio'] ?>" <?= $ano['anio'] == date('Y') ? 'selected' : '' ?>>
                                            <?= $ano['anio'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_evaluacion" class="form-label">Fecha de Evaluación</label>
                        <input type="date" name="fecha_evaluacion" id="fecha_evaluacion" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <h6 class="mb-3">Criterios de Evaluación (0-20 puntos cada uno)</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="puntaje_planificacion" class="form-label">Planificación Curricular</label>
                                <input type="number" name="puntaje_planificacion" id="puntaje_planificacion" 
                                       class="form-control" min="0" max="20" step="0.5" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="puntaje_metodologia" class="form-label">Metodología de Enseñanza</label>
                                <input type="number" name="puntaje_metodologia" id="puntaje_metodologia" 
                                       class="form-control" min="0" max="20" step="0.5" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="puntaje_evaluacion" class="form-label">Sistemas de Evaluación</label>
                                <input type="number" name="puntaje_evaluacion" id="puntaje_evaluacion" 
                                       class="form-control" min="0" max="20" step="0.5" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="puntaje_interaccion" class="form-label">Interacción con Estudiantes</label>
                                <input type="number" name="puntaje_interaccion" id="puntaje_interaccion" 
                                       class="form-control" min="0" max="20" step="0.5" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="puntaje_recursos" class="form-label">Uso de Recursos Didácticos</label>
                        <input type="number" name="puntaje_recursos" id="puntaje_recursos" 
                               class="form-control" min="0" max="20" step="0.5" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="guardar_evaluacion" class="btn btn-primary">Guardar Evaluación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function verDetalles(idEvaluacion) {
    // Implementar vista de detalles
    alert('Vista de detalles en desarrollo');
}

// Calcular total automáticamente
document.addEventListener('DOMContentLoaded', function() {
    const inputs = ['puntaje_planificacion', 'puntaje_metodologia', 'puntaje_evaluacion', 'puntaje_interaccion', 'puntaje_recursos'];
    
    inputs.forEach(input => {
        document.getElementById(input).addEventListener('input', calcularTotal);
    });
    
    function calcularTotal() {
        let total = 0;
        inputs.forEach(input => {
            const valor = parseFloat(document.getElementById(input).value) || 0;
            total += valor;
        });
        
        // Mostrar total en tiempo real si existe un elemento para ello
        const totalElement = document.getElementById('total_puntos');
        if (totalElement) {
            totalElement.textContent = total + '/100';
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>