<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'PSICOLOGO') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Evaluación Estudiantil - Psicóloga";

// Procesar registro de atención psicológica
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_atencion'])) {
    try {
        // Usar el procedimiento almacenado sp_registrar_atencion_psicologica
        $stmt = $pdo->prepare("CALL sp_registrar_atencion_psicologica(?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['codigo_estudiante'],
            $_POST['fecha_atencion'],
            $_POST['motivo'],
            $_POST['observaciones'],
            $_POST['derivado_por'] ?: null,
            $_POST['tipo_atencion']
        ]);
        
        $result = $stmt->fetch();
        $success_message = $result['mensaje'];
        
    } catch (Exception $e) {
        $error_message = "Error al registrar atención: " . $e->getMessage();
    }
}

// Obtener estudiantes para evaluación
$estudiantes_filtro = [];
if (isset($_GET['buscar_estudiante']) && !empty($_GET['codigo_estudiante'])) {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            g.numero_grado,
            s.letra_seccion,
            a.anio,
            COUNT(ap.id_atencion) as total_atenciones,
            MAX(ap.fecha_atencion) as ultima_atencion
        FROM estudiantes e
        JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN anios_academicos a ON m.id_anio = a.id_anio
        LEFT JOIN atencion_psicologica ap ON e.id_estudiante = ap.id_estudiante
        WHERE e.codigo_estudiante LIKE ? AND m.estado = 'ACTIVO' AND a.estado = 'ACTIVO'
        GROUP BY e.id_estudiante
        ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres
    ");
    $stmt->execute(['%' . $_GET['codigo_estudiante'] . '%']);
    $estudiantes_filtro = $stmt->fetchAll();
}

// Obtener personal para derivaciones
$stmt = $pdo->query("
    SELECT id_personal, CONCAT(nombres, ' ', apellido_paterno) as nombre_completo, cargo
    FROM personal 
    WHERE cargo IN ('COORDINADOR_TUTORIA', 'DIRECTOR', 'AUXILIAR_EDUCACION') 
    AND estado = 'ACTIVO'
    ORDER BY cargo, apellido_paterno
");
$personal_derivacion = $stmt->fetchAll();

// Obtener historial de atenciones recientes
$stmt = $pdo->prepare("
    SELECT 
        ap.*,
        CONCAT(e.apellido_paterno, ' ', e.apellido_materno, ', ', e.nombres) as estudiante_nombre,
        e.codigo_estudiante,
        g.numero_grado,
        s.letra_seccion,
        CONCAT(p.nombres, ' ', p.apellido_paterno) as derivado_por_nombre
    FROM atencion_psicologica ap
    JOIN estudiantes e ON ap.id_estudiante = e.id_estudiante
    JOIN matriculas m ON e.id_estudiante = m.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    LEFT JOIN personal p ON ap.derivado_por = p.id_personal
    WHERE ap.id_psicologo = ? AND m.estado = 'ACTIVO'
    ORDER BY ap.fecha_atencion DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$atenciones_recientes = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-user-md"></i> Evaluación y Atención Estudiantil
                    </h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaAtencionModal">
                        <i class="fas fa-plus"></i> Nueva Atención
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

                    <!-- Búsqueda de estudiantes -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-search"></i> Buscar Estudiante para Evaluación
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-6">
                                    <label for="codigo_estudiante" class="form-label">Código o Nombre del Estudiante</label>
                                    <input type="text" name="codigo_estudiante" id="codigo_estudiante" 
                                           class="form-control" placeholder="Ingrese código o nombre..."
                                           value="<?= $_GET['codigo_estudiante'] ?? '' ?>">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" name="buscar_estudiante" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                    <a href="student_evaluation.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resultados de búsqueda -->
                    <?php if (!empty($estudiantes_filtro)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Estudiantes Encontrados</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Código</th>
                                                <th>Nombre Completo</th>
                                                <th>Grado/Sección</th>
                                                <th>Año</th>
                                                <th>Atenciones</th>
                                                <th>Última Atención</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($estudiantes_filtro as $estudiante): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($estudiante['codigo_estudiante']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($estudiante['apellido_paterno'] . ' ' . 
                                                            $estudiante['apellido_materno'] . ', ' . $estudiante['nombres']) ?>
                                                    </td>
                                                    <td><?= $estudiante['numero_grado'] ?>° <?= $estudiante['letra_seccion'] ?></td>
                                                    <td><?= $estudiante['anio'] ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $estudiante['total_atenciones'] > 0 ? 'info' : 'secondary' ?>">
                                                            <?= $estudiante['total_atenciones'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= $estudiante['ultima_atencion'] ? 
                                                            date('d/m/Y', strtotime($estudiante['ultima_atencion'])) : 
                                                            'Nunca' ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="nuevaAtencion('<?= $estudiante['codigo_estudiante'] ?>', '<?= htmlspecialchars($estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres']) ?>')">
                                                            <i class="fas fa-plus"></i> Nueva Atención
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="verHistorial(<?= $estudiante['id_estudiante'] ?>)">
                                                            <i class="fas fa-history"></i> Historial
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Atenciones recientes -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-clock"></i> Atenciones Recientes
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($atenciones_recientes)): ?>
                                <div class="timeline">
                                    <?php foreach ($atenciones_recientes as $atencion): ?>
                                        <div class="timeline-item mb-3">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <div class="timeline-marker bg-<?= 
                                                        $atencion['tipo_atencion'] === 'INDIVIDUAL' ? 'primary' :
                                                        ($atencion['tipo_atencion'] === 'GRUPAL' ? 'success' :
                                                        ($atencion['tipo_atencion'] === 'FAMILIAR' ? 'warning' : 'info'))
                                                    ?>"></div>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h6 class="mb-1">
                                                                        <?= htmlspecialchars($atencion['estudiante_nombre']) ?>
                                                                        <small class="text-muted">(<?= $atencion['codigo_estudiante'] ?>)</small>
                                                                    </h6>
                                                                    <p class="text-muted mb-2">
                                                                        <?= $atencion['numero_grado'] ?>° <?= $atencion['letra_seccion'] ?> • 
                                                                        <?= date('d/m/Y', strtotime($atencion['fecha_atencion'])) ?>
                                                                    </p>
                                                                    <p class="mb-2">
                                                                        <strong>Motivo:</strong> <?= nl2br(htmlspecialchars(substr($atencion['motivo'], 0, 100))) ?>
                                                                        <?= strlen($atencion['motivo']) > 100 ? '...' : '' ?>
                                                                    </p>
                                                                </div>
                                                                <div class="text-end">
                                                                    <span class="badge bg-<?= 
                                                                        $atencion['tipo_atencion'] === 'INDIVIDUAL' ? 'primary' :
                                                                        ($atencion['tipo_atencion'] === 'GRUPAL' ? 'success' :
                                                                        ($atencion['tipo_atencion'] === 'FAMILIAR' ? 'warning' : 'info'))
                                                                    ?>">
                                                                        <?= ucfirst(strtolower($atencion['tipo_atencion'])) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <?php if ($atencion['derivado_por_nombre']): ?>
                                                                        <small class="text-muted">
                                                                            <i class="fas fa-share"></i> Derivado por: <?= htmlspecialchars($atencion['derivado_por_nombre']) ?>
                                                                        </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div>
                                                                    <button class="btn btn-sm btn-outline-primary" 
                                                                            onclick="verDetalleAtencion(<?= $atencion['id_atencion'] ?>)">
                                                                        <i class="fas fa-eye"></i> Ver detalles
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                                    <h5>No hay atenciones registradas</h5>
                                    <p class="text-muted">Comience registrando una nueva atención psicológica.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Atención -->
<div class="modal fade" id="nuevaAtencionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formNuevaAtencion">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Nueva Atención Psicológica</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_codigo_estudiante" class="form-label">Código del Estudiante *</label>
                                <input type="text" name="codigo_estudiante" id="modal_codigo_estudiante" 
                                       class="form-control" required>
                                <div class="form-text">Ingrese el código del estudiante</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fecha_atencion" class="form-label">Fecha de Atención *</label>
                                <input type="date" name="fecha_atencion" id="fecha_atencion" 
                                       class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tipo_atencion" class="form-label">Tipo de Atención *</label>
                                <select name="tipo_atencion" id="tipo_atencion" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="INDIVIDUAL">Individual</option>
                                    <option value="GRUPAL">Grupal</option>
                                    <option value="FAMILIAR">Familiar</option>
                                    <option value="PREVENTIVA">Preventiva</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="derivado_por" class="form-label">Derivado por (Opcional)</label>
                                <select name="derivado_por" id="derivado_por" class="form-select">
                                    <option value="">Consulta espontánea</option>
                                    <?php foreach ($personal_derivacion as $personal): ?>
                                        <option value="<?= $personal['id_personal'] ?>">
                                            <?= htmlspecialchars($personal['nombre_completo']) ?> (<?= $personal['cargo'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo de la Consulta *</label>
                        <textarea name="motivo" id="motivo" class="form-control" rows="4" required
                                  placeholder="Describa el motivo de la consulta psicológica..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones y Evaluación *</label>
                        <textarea name="observaciones" id="observaciones" class="form-control" rows="6" required
                                  placeholder="Incluya observaciones, evaluación realizada, intervenciones aplicadas y recomendaciones..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="registrar_atencion" class="btn btn-primary">
                        <i class="fas fa-save"></i> Registrar Atención
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.timeline-marker {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px var(--bs-primary);
    position: relative;
    top: 8px;
}

.timeline-marker.bg-primary {
    box-shadow: 0 0 0 2px var(--bs-primary);
}

.timeline-marker.bg-success {
    box-shadow: 0 0 0 2px var(--bs-success);
}

.timeline-marker.bg-warning {
    box-shadow: 0 0 0 2px var(--bs-warning);
}

.timeline-marker.bg-info {
    box-shadow: 0 0 0 2px var(--bs-info);
}
</style>

<script>
function nuevaAtencion(codigoEstudiante, nombreEstudiante) {
    document.getElementById('modal_codigo_estudiante').value = codigoEstudiante;
    document.getElementById('modal_codigo_estudiante').setAttribute('readonly', true);
    
    // Añadir información del estudiante al modal
    const modalTitle = document.querySelector('#nuevaAtencionModal .modal-title');
    modalTitle.innerHTML = 'Registrar Nueva Atención - ' + nombreEstudiante;
    
    new bootstrap.Modal(document.getElementById('nuevaAtencionModal')).show();
}

function verHistorial(idEstudiante) {
    // Implementar vista de historial completo
    window.open(`psychology_reports.php?estudiante=${idEstudiante}`, '_blank');
}

function verDetalleAtencion(idAtencion) {
    // Implementar vista de detalle de atención
    fetch(`../api/get_psychology_attention.php?id=${idAtencion}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar modal con detalles
                alert('Vista de detalle en desarrollo');
            } else {
                alert('Error al cargar los detalles');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los detalles');
        });
}

// Limpiar modal al cerrarse
document.getElementById('nuevaAtencionModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('formNuevaAtencion').reset();
    document.getElementById('modal_codigo_estudiante').removeAttribute('readonly');
    document.querySelector('#nuevaAtencionModal .modal-title').innerHTML = 'Registrar Nueva Atención Psicológica';
});
</script>

<?php include '../includes/footer.php'; ?>