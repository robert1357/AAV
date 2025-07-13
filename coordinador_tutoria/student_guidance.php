<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'COORDINADOR_TUTORIA') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Orientación Estudiantil - Coordinador de Tutoría";

// Procesar registro de sesión de tutoría
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_sesion'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO sesiones_tutoria (
                id_estudiante, id_tutor, tipo_sesion, tema_principal, 
                objetivos, actividades_realizadas, acuerdos_compromisos,
                observaciones, fecha_sesion, duracion_minutos, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'COMPLETADA')
        ");
        
        // Obtener ID del estudiante
        $stmt_estudiante = $pdo->prepare("SELECT id_estudiante FROM estudiantes WHERE codigo_estudiante = ?");
        $stmt_estudiante->execute([$_POST['codigo_estudiante']]);
        $estudiante = $stmt_estudiante->fetch();
        
        if (!$estudiante) {
            throw new Exception("Estudiante no encontrado");
        }
        
        $stmt->execute([
            $estudiante['id_estudiante'],
            $_SESSION['user_id'],
            $_POST['tipo_sesion'],
            $_POST['tema_principal'],
            $_POST['objetivos'],
            $_POST['actividades_realizadas'],
            $_POST['acuerdos_compromisos'],
            $_POST['observaciones'],
            $_POST['fecha_sesion'],
            $_POST['duracion_minutos']
        ]);
        
        $pdo->commit();
        $success_message = "Sesión de tutoría registrada exitosamente";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al registrar sesión: " . $e->getMessage();
    }
}

// Obtener sesiones de tutoría recientes
$stmt = $pdo->prepare("
    SELECT 
        st.*,
        CONCAT(e.apellido_paterno, ' ', e.apellido_materno, ', ', e.nombres) as estudiante_nombre,
        e.codigo_estudiante,
        g.numero_grado,
        s.letra_seccion
    FROM sesiones_tutoria st
    JOIN estudiantes e ON st.id_estudiante = e.id_estudiante
    JOIN matriculas m ON e.id_estudiante = m.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    WHERE st.id_tutor = ? AND m.estado = 'ACTIVO'
    ORDER BY st.fecha_sesion DESC, st.created_at DESC
    LIMIT 15
");
$stmt->execute([$_SESSION['user_id']]);
$sesiones_recientes = $stmt->fetchAll();

// Obtener estudiantes con necesidades de seguimiento
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        g.numero_grado,
        s.letra_seccion,
        COUNT(st.id_sesion) as total_sesiones,
        MAX(st.fecha_sesion) as ultima_sesion,
        COUNT(CASE WHEN i.gravedad IN ('grave', 'muy_grave') THEN 1 END) as incidencias_graves,
        AVG(n.nota) as promedio_general
    FROM estudiantes e
    JOIN matriculas m ON e.id_estudiante = m.id_estudiante
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos a ON m.id_anio = a.id_anio
    LEFT JOIN sesiones_tutoria st ON e.id_estudiante = st.id_estudiante
    LEFT JOIN incidencias_estudiantes i ON e.id_estudiante = i.id_estudiante
    LEFT JOIN notas n ON m.id_matricula = n.id_matricula
    WHERE m.estado = 'ACTIVO' AND a.estado = 'ACTIVO'
    GROUP BY e.id_estudiante
    HAVING (promedio_general < 11 OR incidencias_graves > 0 OR total_sesiones = 0)
    ORDER BY incidencias_graves DESC, promedio_general ASC
    LIMIT 10
");
$stmt->execute();
$estudiantes_seguimiento = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-user-graduate"></i> Orientación Estudiantil
                    </h3>
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaSesionModal">
                            <i class="fas fa-plus"></i> Nueva Sesión
                        </button>
                        <button type="button" class="btn btn-info" onclick="generarReporte()">
                            <i class="fas fa-chart-line"></i> Reporte General
                        </button>
                    </div>
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

                    <div class="row">
                        <!-- Estudiantes que requieren seguimiento -->
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-exclamation-triangle text-warning"></i> 
                                        Estudiantes que Requieren Seguimiento
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($estudiantes_seguimiento)): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($estudiantes_seguimiento as $estudiante): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">
                                                            <?= htmlspecialchars($estudiante['apellido_paterno'] . ' ' . 
                                                                $estudiante['apellido_materno'] . ', ' . $estudiante['nombres']) ?>
                                                        </h6>
                                                        <p class="mb-1">
                                                            <small class="text-muted">
                                                                <?= $estudiante['codigo_estudiante'] ?> - 
                                                                <?= $estudiante['numero_grado'] ?>° <?= $estudiante['letra_seccion'] ?>
                                                            </small>
                                                        </p>
                                                        <small>
                                                            <?php if ($estudiante['promedio_general'] < 11): ?>
                                                                <span class="badge bg-danger me-1">Bajo rendimiento</span>
                                                            <?php endif; ?>
                                                            <?php if ($estudiante['incidencias_graves'] > 0): ?>
                                                                <span class="badge bg-warning me-1"><?= $estudiante['incidencias_graves'] ?> incidencias graves</span>
                                                            <?php endif; ?>
                                                            <?php if ($estudiante['total_sesiones'] == 0): ?>
                                                                <span class="badge bg-info">Sin sesiones</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="btn-group-vertical btn-group-sm">
                                                            <button class="btn btn-outline-primary btn-sm" 
                                                                    onclick="nuevaSesionEstudiante('<?= $estudiante['codigo_estudiante'] ?>', '<?= htmlspecialchars($estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres']) ?>')">
                                                                <i class="fas fa-plus"></i>
                                                            </button>
                                                            <button class="btn btn-outline-info btn-sm" 
                                                                    onclick="verHistorial(<?= $estudiante['id_estudiante'] ?>)">
                                                                <i class="fas fa-history"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                            <p class="text-muted mb-0">No hay estudiantes que requieran seguimiento inmediato</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sesiones recientes -->
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-clock"></i> Sesiones de Tutoría Recientes
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($sesiones_recientes)): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach (array_slice($sesiones_recientes, 0, 8) as $sesion): ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?= htmlspecialchars($sesion['estudiante_nombre']) ?></h6>
                                                            <p class="mb-1">
                                                                <strong><?= htmlspecialchars($sesion['tema_principal']) ?></strong>
                                                            </p>
                                                            <small class="text-muted">
                                                                <?= date('d/m/Y', strtotime($sesion['fecha_sesion'])) ?> - 
                                                                <?= $sesion['duracion_minutos'] ?> min - 
                                                                <?= getTipoSesionTexto($sesion['tipo_sesion']) ?>
                                                            </small>
                                                        </div>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="verDetalleSesion(<?= $sesion['id_sesion'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (count($sesiones_recientes) > 8): ?>
                                            <div class="text-center mt-2">
                                                <a href="tutoring_reports.php" class="btn btn-sm btn-outline-secondary">
                                                    Ver todas las sesiones
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-comments fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No hay sesiones de tutoría registradas</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas rápidas -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Estadísticas del Mes</h6>
                                    <div class="row text-center">
                                        <?php
                                        // Obtener estadísticas del mes actual
                                        $stmt = $pdo->prepare("
                                            SELECT 
                                                COUNT(*) as sesiones_mes,
                                                COUNT(DISTINCT id_estudiante) as estudiantes_atendidos,
                                                AVG(duracion_minutos) as duracion_promedio,
                                                COUNT(CASE WHEN tipo_sesion = 'INDIVIDUAL' THEN 1 END) as individuales,
                                                COUNT(CASE WHEN tipo_sesion = 'GRUPAL' THEN 1 END) as grupales
                                            FROM sesiones_tutoria 
                                            WHERE id_tutor = ? 
                                            AND MONTH(fecha_sesion) = MONTH(CURDATE()) 
                                            AND YEAR(fecha_sesion) = YEAR(CURDATE())
                                        ");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $stats = $stmt->fetch();
                                        ?>
                                        <div class="col-md-2">
                                            <h5 class="text-primary"><?= $stats['sesiones_mes'] ?? 0 ?></h5>
                                            <small class="text-muted">Sesiones este mes</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h5 class="text-info"><?= $stats['estudiantes_atendidos'] ?? 0 ?></h5>
                                            <small class="text-muted">Estudiantes atendidos</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h5 class="text-success"><?= round($stats['duracion_promedio'] ?? 0) ?> min</h5>
                                            <small class="text-muted">Duración promedio</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h5 class="text-warning"><?= $stats['individuales'] ?? 0 ?></h5>
                                            <small class="text-muted">Sesiones individuales</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h5 class="text-secondary"><?= $stats['grupales'] ?? 0 ?></h5>
                                            <small class="text-muted">Sesiones grupales</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h5 class="text-danger"><?= count($estudiantes_seguimiento) ?></h5>
                                            <small class="text-muted">Requieren seguimiento</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Sesión -->
<div class="modal fade" id="nuevaSesionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="formNuevaSesion">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Nueva Sesión de Tutoría</h5>
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
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="fecha_sesion" class="form-label">Fecha de Sesión *</label>
                                <input type="date" name="fecha_sesion" id="fecha_sesion" 
                                       class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="duracion_minutos" class="form-label">Duración (minutos) *</label>
                                <input type="number" name="duracion_minutos" id="duracion_minutos" 
                                       class="form-control" min="5" max="180" value="45" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tipo_sesion" class="form-label">Tipo de Sesión *</label>
                                <select name="tipo_sesion" id="tipo_sesion" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="INDIVIDUAL">Individual</option>
                                    <option value="GRUPAL">Grupal</option>
                                    <option value="FAMILIAR">Familiar</option>
                                    <option value="SEGUIMIENTO">Seguimiento</option>
                                    <option value="ORIENTACION_VOCACIONAL">Orientación Vocacional</option>
                                    <option value="APOYO_ACADEMICO">Apoyo Académico</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tema_principal" class="form-label">Tema Principal *</label>
                                <input type="text" name="tema_principal" id="tema_principal" class="form-control" required
                                       placeholder="Ej: Estrategias de estudio, Manejo de ansiedad...">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="objetivos" class="form-label">Objetivos de la Sesión *</label>
                        <textarea name="objetivos" id="objetivos" class="form-control" rows="2" required
                                  placeholder="Describa los objetivos específicos que se buscan alcanzar..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="actividades_realizadas" class="form-label">Actividades Realizadas *</label>
                        <textarea name="actividades_realizadas" id="actividades_realizadas" class="form-control" rows="3" required
                                  placeholder="Describa las actividades, técnicas y estrategias utilizadas durante la sesión..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="acuerdos_compromisos" class="form-label">Acuerdos y Compromisos *</label>
                        <textarea name="acuerdos_compromisos" id="acuerdos_compromisos" class="form-control" rows="3" required
                                  placeholder="Detalle los acuerdos y compromisos establecidos con el estudiante..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones y Recomendaciones</label>
                        <textarea name="observaciones" id="observaciones" class="form-control" rows="3"
                                  placeholder="Observaciones adicionales, recomendaciones para futuras sesiones..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="registrar_sesion" class="btn btn-primary">
                        <i class="fas fa-save"></i> Registrar Sesión
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function nuevaSesionEstudiante(codigoEstudiante, nombreEstudiante) {
    document.getElementById('modal_codigo_estudiante').value = codigoEstudiante;
    document.getElementById('modal_codigo_estudiante').setAttribute('readonly', true);
    
    const modalTitle = document.querySelector('#nuevaSesionModal .modal-title');
    modalTitle.innerHTML = 'Nueva Sesión de Tutoría - ' + nombreEstudiante;
    
    new bootstrap.Modal(document.getElementById('nuevaSesionModal')).show();
}

function verHistorial(idEstudiante) {
    window.open(`../reports/student_tutoring.php?estudiante=${idEstudiante}`, '_blank');
}

function verDetalleSesion(idSesion) {
    fetch(`../api/get_tutoring_session.php?id=${idSesion}`)
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

function generarReporte() {
    window.open('../reports/tutoring_general.php', '_blank');
}

// Limpiar modal al cerrarse
document.getElementById('nuevaSesionModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('formNuevaSesion').reset();
    document.getElementById('modal_codigo_estudiante').removeAttribute('readonly');
    document.querySelector('#nuevaSesionModal .modal-title').innerHTML = 'Registrar Nueva Sesión de Tutoría';
});
</script>

<?php
// Funciones auxiliares
function getTipoSesionTexto($tipo) {
    $tipos = [
        'INDIVIDUAL' => 'Individual',
        'GRUPAL' => 'Grupal',
        'FAMILIAR' => 'Familiar',
        'SEGUIMIENTO' => 'Seguimiento',
        'ORIENTACION_VOCACIONAL' => 'Orientación Vocacional',
        'APOYO_ACADEMICO' => 'Apoyo Académico'
    ];
    return $tipos[$tipo] ?? $tipo;
}

include '../includes/footer.php'; 
?>