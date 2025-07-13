<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'AUXILIAR_EDUCACION') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Apoyo Estudiantil - Auxiliar de Educación";

// Procesar registro de incidencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_incidencia'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO incidencias_estudiantes (
                id_estudiante, id_personal, tipo_incidencia, descripcion, 
                gravedad, fecha_incidencia, acciones_tomadas, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVA')
        ");
        
        // Obtener ID del estudiante por código
        $stmt_estudiante = $pdo->prepare("SELECT id_estudiante FROM estudiantes WHERE codigo_estudiante = ?");
        $stmt_estudiante->execute([$_POST['codigo_estudiante']]);
        $estudiante = $stmt_estudiante->fetch();
        
        if (!$estudiante) {
            throw new Exception("Estudiante no encontrado");
        }
        
        $stmt->execute([
            $estudiante['id_estudiante'],
            $_SESSION['user_id'],
            $_POST['tipo_incidencia'],
            $_POST['descripcion'],
            $_POST['gravedad'],
            $_POST['fecha_incidencia'],
            $_POST['acciones_tomadas']
        ]);
        
        $pdo->commit();
        $success_message = "Incidencia registrada exitosamente";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al registrar incidencia: " . $e->getMessage();
    }
}

// Obtener incidencias recientes
$stmt = $pdo->prepare("
    SELECT 
        i.*,
        CONCAT(e.apellido_paterno, ' ', e.apellido_materno, ', ', e.nombres) as estudiante_nombre,
        e.codigo_estudiante,
        g.numero_grado,
        s.letra_seccion
    FROM incidencias_estudiantes i
    JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
    JOIN matriculas m ON e.id_estudiante = m.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    WHERE i.id_personal = ? AND m.estado = 'ACTIVO'
    ORDER BY i.fecha_incidencia DESC, i.created_at DESC
    LIMIT 20
");
$stmt->execute([$_SESSION['user_id']]);
$incidencias_recientes = $stmt->fetchAll();

// Obtener estudiantes para búsqueda
$estudiantes_busqueda = [];
if (isset($_GET['buscar_estudiante']) && !empty($_GET['codigo_estudiante'])) {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            g.numero_grado,
            s.letra_seccion,
            a.anio,
            COUNT(i.id_incidencia) as total_incidencias
        FROM estudiantes e
        JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN anios_academicos a ON m.id_anio = a.id_anio
        LEFT JOIN incidencias_estudiantes i ON e.id_estudiante = i.id_estudiante
        WHERE (e.codigo_estudiante LIKE ? OR 
               e.nombres LIKE ? OR 
               e.apellido_paterno LIKE ? OR 
               e.apellido_materno LIKE ?)
        AND m.estado = 'ACTIVO' AND a.estado = 'ACTIVO'
        GROUP BY e.id_estudiante
        ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres
        LIMIT 10
    ");
    $busqueda = '%' . $_GET['codigo_estudiante'] . '%';
    $stmt->execute([$busqueda, $busqueda, $busqueda, $busqueda]);
    $estudiantes_busqueda = $stmt->fetchAll();
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
                        <i class="fas fa-user-friends"></i> Apoyo Estudiantil
                    </h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaIncidenciaModal">
                        <i class="fas fa-plus"></i> Registrar Incidencia
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
                                <i class="fas fa-search"></i> Buscar Estudiante
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-8">
                                    <label for="codigo_estudiante" class="form-label">Código o Nombre del Estudiante</label>
                                    <input type="text" name="codigo_estudiante" id="codigo_estudiante" 
                                           class="form-control" placeholder="Ingrese código o nombre del estudiante..."
                                           value="<?= $_GET['codigo_estudiante'] ?? '' ?>">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="buscar_estudiante" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                    <a href="student_support.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resultados de búsqueda -->
                    <?php if (!empty($estudiantes_busqueda)): ?>
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
                                                <th>Incidencias</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($estudiantes_busqueda as $estudiante): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($estudiante['codigo_estudiante']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($estudiante['apellido_paterno'] . ' ' . 
                                                            $estudiante['apellido_materno'] . ', ' . $estudiante['nombres']) ?>
                                                    </td>
                                                    <td><?= $estudiante['numero_grado'] ?>° <?= $estudiante['letra_seccion'] ?></td>
                                                    <td><?= $estudiante['anio'] ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $estudiante['total_incidencias'] > 0 ? 'warning' : 'success' ?>">
                                                            <?= $estudiante['total_incidencias'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="registrarIncidencia('<?= $estudiante['codigo_estudiante'] ?>', '<?= htmlspecialchars($estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres']) ?>')">
                                                            <i class="fas fa-plus"></i> Incidencia
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

                    <!-- Incidencias recientes -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-clock"></i> Incidencias Recientes
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($incidencias_recientes)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Estudiante</th>
                                                <th>Grado/Sección</th>
                                                <th>Tipo</th>
                                                <th>Gravedad</th>
                                                <th>Descripción</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($incidencias_recientes as $incidencia): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($incidencia['fecha_incidencia'])) ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($incidencia['estudiante_nombre']) ?></strong>
                                                        <br><small class="text-muted"><?= $incidencia['codigo_estudiante'] ?></small>
                                                    </td>
                                                    <td><?= $incidencia['numero_grado'] ?>° <?= $incidencia['letra_seccion'] ?></td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?= getTipoIncidenciaTexto($incidencia['tipo_incidencia']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= getGravedadBadgeClass($incidencia['gravedad']) ?>">
                                                            <?= ucfirst($incidencia['gravedad']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars(substr($incidencia['descripcion'], 0, 50)) ?>
                                                        <?= strlen($incidencia['descripcion']) > 50 ? '...' : '' ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $incidencia['estado'] === 'ACTIVA' ? 'warning' : 'success' ?>">
                                                            <?= $incidencia['estado'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="verDetalle(<?= $incidencia['id_incidencia'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($incidencia['estado'] === 'ACTIVA'): ?>
                                                            <button class="btn btn-sm btn-outline-success" 
                                                                    onclick="resolverIncidencia(<?= $incidencia['id_incidencia'] ?>)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                                    <h5>No hay incidencias registradas</h5>
                                    <p class="text-muted">Comience registrando incidencias para hacer seguimiento del comportamiento estudiantil.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Incidencia -->
<div class="modal fade" id="nuevaIncidenciaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formNuevaIncidencia">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Nueva Incidencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_codigo_estudiante" class="form-label">Código del Estudiante *</label>
                                <input type="text" name="codigo_estudiante" id="modal_codigo_estudiante" 
                                       class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fecha_incidencia" class="form-label">Fecha de Incidencia *</label>
                                <input type="date" name="fecha_incidencia" id="fecha_incidencia" 
                                       class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tipo_incidencia" class="form-label">Tipo de Incidencia *</label>
                                <select name="tipo_incidencia" id="tipo_incidencia" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="DISCIPLINARIA">Disciplinaria</option>
                                    <option value="ACADEMICA">Académica</option>
                                    <option value="CONVIVENCIA">Convivencia</option>
                                    <option value="ASISTENCIA">Asistencia</option>
                                    <option value="CONDUCTUAL">Conductual</option>
                                    <option value="FAMILIAR">Familiar</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gravedad" class="form-label">Gravedad *</label>
                                <select name="gravedad" id="gravedad" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="leve">Leve</option>
                                    <option value="moderada">Moderada</option>
                                    <option value="grave">Grave</option>
                                    <option value="muy_grave">Muy Grave</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción de la Incidencia *</label>
                        <textarea name="descripcion" id="descripcion" class="form-control" rows="4" required
                                  placeholder="Describa detalladamente lo ocurrido..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="acciones_tomadas" class="form-label">Acciones Tomadas *</label>
                        <textarea name="acciones_tomadas" id="acciones_tomadas" class="form-control" rows="3" required
                                  placeholder="Describa las acciones inmediatas tomadas para resolver la situación..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="registrar_incidencia" class="btn btn-primary">
                        <i class="fas fa-save"></i> Registrar Incidencia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function registrarIncidencia(codigoEstudiante, nombreEstudiante) {
    document.getElementById('modal_codigo_estudiante').value = codigoEstudiante;
    document.getElementById('modal_codigo_estudiante').setAttribute('readonly', true);
    
    const modalTitle = document.querySelector('#nuevaIncidenciaModal .modal-title');
    modalTitle.innerHTML = 'Registrar Incidencia - ' + nombreEstudiante;
    
    new bootstrap.Modal(document.getElementById('nuevaIncidenciaModal')).show();
}

function verHistorial(idEstudiante) {
    window.open(`../reports/student_incidents.php?estudiante=${idEstudiante}`, '_blank');
}

function verDetalle(idIncidencia) {
    fetch(`../api/get_incident_details.php?id=${idIncidencia}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
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

function resolverIncidencia(idIncidencia) {
    if (confirm('¿Confirma que desea marcar esta incidencia como resuelta?')) {
        fetch('../api/resolve_incident.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_incidencia: idIncidencia
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al resolver la incidencia');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al resolver la incidencia');
        });
    }
}

// Limpiar modal al cerrarse
document.getElementById('nuevaIncidenciaModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('formNuevaIncidencia').reset();
    document.getElementById('modal_codigo_estudiante').removeAttribute('readonly');
    document.querySelector('#nuevaIncidenciaModal .modal-title').innerHTML = 'Registrar Nueva Incidencia';
});
</script>

<?php
// Funciones auxiliares
function getTipoIncidenciaTexto($tipo) {
    $tipos = [
        'DISCIPLINARIA' => 'Disciplinaria',
        'ACADEMICA' => 'Académica',
        'CONVIVENCIA' => 'Convivencia',
        'ASISTENCIA' => 'Asistencia',
        'CONDUCTUAL' => 'Conductual',
        'FAMILIAR' => 'Familiar',
        'OTRO' => 'Otro'
    ];
    return $tipos[$tipo] ?? $tipo;
}

function getGravedadBadgeClass($gravedad) {
    switch ($gravedad) {
        case 'leve': return 'success';
        case 'moderada': return 'warning';
        case 'grave': return 'danger';
        case 'muy_grave': return 'dark';
        default: return 'secondary';
    }
}

include '../includes/footer.php'; 
?>