<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DOCENTE') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Registro de Calificaciones - Docente";

// Procesar registro de nota usando el procedimiento almacenado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_nota'])) {
    try {
        // Usar el procedimiento almacenado sp_registrar_nota
        $stmt = $pdo->prepare("CALL sp_registrar_nota(?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['codigo_estudiante'],
            $_POST['codigo_curso'],
            $_POST['bimestre'],
            $_POST['anio'],
            $_POST['nota'],
            $_POST['observaciones'],
            $_SESSION['user_id']
        ]);
        
        $result = $stmt->fetch();
        $success_message = $result['mensaje'];
        
    } catch (Exception $e) {
        $error_message = "Error al registrar nota: " . $e->getMessage();
    }
}

// Obtener cursos asignados al docente
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        c.*,
        g.numero_grado,
        s.letra_seccion,
        a.anio
    FROM cursos c
    JOIN asignaciones asig ON c.id_curso = asig.id_curso
    JOIN secciones s ON asig.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos a ON asig.id_anio = a.id_anio
    WHERE asig.id_personal = ? AND asig.estado = 'ACTIVO' AND a.estado = 'ACTIVO'
    ORDER BY c.nombre, g.numero_grado, s.letra_seccion
");
$stmt->execute([$_SESSION['user_id']]);
$cursos_docente = $stmt->fetchAll();

// Obtener bimestres del año actual
$stmt = $pdo->prepare("
    SELECT b.*, a.anio
    FROM bimestres b
    JOIN anios_academicos a ON b.id_anio = a.id_anio
    WHERE a.estado = 'ACTIVO'
    ORDER BY b.numero_bimestre
");
$stmt->execute();
$bimestres = $stmt->fetchAll();

// Filtros para visualización
$filtro_curso = $_GET['curso'] ?? '';
$filtro_bimestre = $_GET['bimestre'] ?? '';
$filtro_seccion = $_GET['seccion'] ?? '';

// Obtener estudiantes y notas si hay filtros aplicados
$estudiantes_notas = [];
if ($filtro_curso && $filtro_bimestre) {
    $sql = "
        SELECT 
            e.id_estudiante,
            e.codigo_estudiante,
            e.dni,
            CONCAT(e.apellido_paterno, ' ', e.apellido_materno, ', ', e.nombres) as nombre_completo,
            g.numero_grado,
            s.letra_seccion,
            n.nota,
            n.observaciones,
            n.fecha_registro,
            c.codigo as curso_codigo,
            c.nombre as curso_nombre,
            b.numero_bimestre
        FROM estudiantes e
        JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN asignaciones asig ON s.id_seccion = asig.id_seccion
        JOIN cursos c ON asig.id_curso = c.id_curso
        JOIN anios_academicos a ON asig.id_anio = a.id_anio
        LEFT JOIN notas n ON m.id_matricula = n.id_matricula 
            AND c.id_curso = n.id_curso 
            AND EXISTS (
                SELECT 1 FROM bimestres b2 
                WHERE b2.id_bimestre = n.id_bimestre 
                AND b2.numero_bimestre = ?
            )
        JOIN bimestres b ON a.id_anio = b.id_anio AND b.numero_bimestre = ?
        WHERE asig.id_personal = ? 
        AND c.id_curso = ? 
        AND m.estado = 'ACTIVO'
        AND a.estado = 'ACTIVO'
    ";
    
    $params = [$filtro_bimestre, $filtro_bimestre, $_SESSION['user_id'], $filtro_curso];
    
    if ($filtro_seccion) {
        $sql .= " AND s.id_seccion = ?";
        $params[] = $filtro_seccion;
    }
    
    $sql .= " ORDER BY g.numero_grado, s.letra_seccion, e.apellido_paterno, e.apellido_materno, e.nombres";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $estudiantes_notas = $stmt->fetchAll();
}

// Obtener secciones para el curso seleccionado
$secciones_curso = [];
if ($filtro_curso) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.*
        FROM secciones s
        JOIN asignaciones asig ON s.id_seccion = asig.id_seccion
        WHERE asig.id_curso = ? AND asig.id_personal = ?
        ORDER BY s.letra_seccion
    ");
    $stmt->execute([$filtro_curso, $_SESSION['user_id']]);
    $secciones_curso = $stmt->fetchAll();
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
                        <i class="fas fa-clipboard-list"></i> Registro de Calificaciones
                    </h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registrarNotaModal">
                        <i class="fas fa-plus"></i> Registrar Nota Individual
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
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-filter"></i> Filtros para Visualización
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label for="curso" class="form-label">Curso *</label>
                                    <select name="curso" id="curso" class="form-select" required onchange="cargarSecciones()">
                                        <option value="">Seleccione un curso...</option>
                                        <?php foreach ($cursos_docente as $curso): ?>
                                            <option value="<?= $curso['id_curso'] ?>" <?= $filtro_curso == $curso['id_curso'] ? 'selected' : '' ?>>
                                                [<?= $curso['codigo'] ?>] <?= htmlspecialchars($curso['nombre']) ?> - 
                                                <?= $curso['numero_grado'] ?>° <?= $curso['letra_seccion'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="bimestre" class="form-label">Bimestre *</label>
                                    <select name="bimestre" id="bimestre" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($bimestres as $bim): ?>
                                            <option value="<?= $bim['numero_bimestre'] ?>" <?= $filtro_bimestre == $bim['numero_bimestre'] ? 'selected' : '' ?>>
                                                <?= $bim['numero_bimestre'] ?>° Bimestre (<?= $bim['anio'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="seccion" class="form-label">Sección (Opcional)</label>
                                    <select name="seccion" id="seccion" class="form-select">
                                        <option value="">Todas las secciones</option>
                                        <?php foreach ($secciones_curso as $seccion): ?>
                                            <option value="<?= $seccion['id_seccion'] ?>" <?= $filtro_seccion == $seccion['id_seccion'] ? 'selected' : '' ?>>
                                                Sección <?= $seccion['letra_seccion'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Ver
                                    </button>
                                    <a href="grades.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla de calificaciones -->
                    <?php if (!empty($estudiantes_notas)): ?>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    Lista de Calificaciones
                                    <?php if ($filtro_curso && $filtro_bimestre): ?>
                                        <?php
                                        $curso_info = array_filter($cursos_docente, fn($c) => $c['id_curso'] == $filtro_curso)[0];
                                        ?>
                                        - <?= htmlspecialchars($curso_info['nombre']) ?> (<?= $filtro_bimestre ?>° Bimestre)
                                    <?php endif; ?>
                                </h6>
                                <div>
                                    <button class="btn btn-success btn-sm" onclick="exportarNotas()">
                                        <i class="fas fa-file-excel"></i> Exportar
                                    </button>
                                    <button class="btn btn-info btn-sm" onclick="registroMasivo()">
                                        <i class="fas fa-upload"></i> Registro Masivo
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>N°</th>
                                                <th>Código</th>
                                                <th>Estudiante</th>
                                                <th>Grado/Sección</th>
                                                <th>Nota</th>
                                                <th>Estado</th>
                                                <th>Fecha Registro</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($estudiantes_notas as $index => $estudiante): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($estudiante['codigo_estudiante']) ?></td>
                                                    <td><?= htmlspecialchars($estudiante['nombre_completo']) ?></td>
                                                    <td><?= $estudiante['numero_grado'] ?>° <?= $estudiante['letra_seccion'] ?></td>
                                                    <td>
                                                        <?php if ($estudiante['nota'] !== null): ?>
                                                            <span class="badge fs-6 bg-<?= $estudiante['nota'] >= 14 ? 'success' : ($estudiante['nota'] >= 11 ? 'warning' : 'danger') ?>">
                                                                <?= $estudiante['nota'] ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sin nota</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($estudiante['nota'] !== null): ?>
                                                            <?php if ($estudiante['nota'] >= 14): ?>
                                                                <span class="badge bg-success">Aprobado</span>
                                                            <?php elseif ($estudiante['nota'] >= 11): ?>
                                                                <span class="badge bg-warning">En proceso</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Desaprobado</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Pendiente</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?= $estudiante['fecha_registro'] ? 
                                                            date('d/m/Y', strtotime($estudiante['fecha_registro'])) : 
                                                            '-' ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="editarNota('<?= $estudiante['codigo_estudiante'] ?>', '<?= htmlspecialchars($estudiante['nombre_completo']) ?>', <?= $estudiante['nota'] ?? 'null' ?>, '<?= htmlspecialchars($estudiante['observaciones'] ?? '') ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($estudiante['observaciones']): ?>
                                                            <button class="btn btn-sm btn-outline-info" 
                                                                    onclick="verObservaciones('<?= htmlspecialchars($estudiante['observaciones']) ?>')">
                                                                <i class="fas fa-comment"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Estadísticas -->
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <?php
                                        $total_estudiantes = count($estudiantes_notas);
                                        $con_nota = count(array_filter($estudiantes_notas, fn($e) => $e['nota'] !== null));
                                        $aprobados = count(array_filter($estudiantes_notas, fn($e) => $e['nota'] >= 14));
                                        $desaprobados = count(array_filter($estudiantes_notas, fn($e) => $e['nota'] !== null && $e['nota'] < 11));
                                        $promedio = $con_nota > 0 ? array_sum(array_column(array_filter($estudiantes_notas, fn($e) => $e['nota'] !== null), 'nota')) / $con_nota : 0;
                                        ?>
                                        <div class="row text-center">
                                            <div class="col-md-2">
                                                <div class="card bg-light">
                                                    <div class="card-body py-2">
                                                        <h6 class="text-primary mb-0"><?= $total_estudiantes ?></h6>
                                                        <small>Total</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card bg-light">
                                                    <div class="card-body py-2">
                                                        <h6 class="text-info mb-0"><?= $con_nota ?></h6>
                                                        <small>Con nota</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card bg-light">
                                                    <div class="card-body py-2">
                                                        <h6 class="text-success mb-0"><?= $aprobados ?></h6>
                                                        <small>Aprobados</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card bg-light">
                                                    <div class="card-body py-2">
                                                        <h6 class="text-danger mb-0"><?= $desaprobados ?></h6>
                                                        <small>Desaprobados</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card bg-light">
                                                    <div class="card-body py-2">
                                                        <h6 class="text-warning mb-0"><?= number_format($promedio, 2) ?></h6>
                                                        <small>Promedio</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card bg-light">
                                                    <div class="card-body py-2">
                                                        <h6 class="text-secondary mb-0"><?= $total_estudiantes - $con_nota ?></h6>
                                                        <small>Pendientes</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($filtro_curso && $filtro_bimestre): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5>No se encontraron estudiantes</h5>
                            <p class="text-muted">No hay estudiantes matriculados en este curso para el bimestre seleccionado.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h5>Seleccione un curso y bimestre</h5>
                            <p class="text-muted">Use los filtros para ver la lista de estudiantes y sus calificaciones.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Registrar Nota Individual -->
<div class="modal fade" id="registrarNotaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formRegistrarNota">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Nota Individual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_codigo_estudiante" class="form-label">Código del Estudiante *</label>
                        <input type="text" name="codigo_estudiante" id="modal_codigo_estudiante" 
                               class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_codigo_curso" class="form-label">Código del Curso *</label>
                        <select name="codigo_curso" id="modal_codigo_curso" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($cursos_docente as $curso): ?>
                                <option value="<?= $curso['codigo'] ?>">
                                    [<?= $curso['codigo'] ?>] <?= htmlspecialchars($curso['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_bimestre" class="form-label">Bimestre *</label>
                                <select name="bimestre" id="modal_bimestre" class="form-select" required>
                                    <?php foreach ($bimestres as $bim): ?>
                                        <option value="<?= $bim['numero_bimestre'] ?>">
                                            <?= $bim['numero_bimestre'] ?>° Bimestre
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_anio" class="form-label">Año *</label>
                                <select name="anio" id="modal_anio" class="form-select" required>
                                    <?php foreach ($bimestres as $bim): ?>
                                        <option value="<?= $bim['anio'] ?>" <?= $bim['anio'] == date('Y') ? 'selected' : '' ?>>
                                            <?= $bim['anio'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_nota" class="form-label">Nota (0-20) *</label>
                        <input type="number" name="nota" id="modal_nota" class="form-control" 
                               min="0" max="20" step="0.5" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_observaciones" class="form-label">Observaciones</label>
                        <textarea name="observaciones" id="modal_observaciones" 
                                  class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="registrar_nota" class="btn btn-primary">
                        <i class="fas fa-save"></i> Registrar Nota
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cargarSecciones() {
    const cursoId = document.getElementById('curso').value;
    const seccionSelect = document.getElementById('seccion');
    
    // Resetear secciones
    seccionSelect.innerHTML = '<option value="">Todas las secciones</option>';
    
    if (cursoId) {
        fetch(`../api/get_course_sections.php?curso=${cursoId}&docente=<?= $_SESSION['user_id'] ?>`)
            .then(response => response.json())
            .then(data => {
                data.forEach(seccion => {
                    const option = document.createElement('option');
                    option.value = seccion.id_seccion;
                    option.textContent = `Sección ${seccion.letra_seccion}`;
                    seccionSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error:', error));
    }
}

function editarNota(codigoEstudiante, nombreEstudiante, notaActual, observacionesActuales) {
    document.getElementById('modal_codigo_estudiante').value = codigoEstudiante;
    document.getElementById('modal_codigo_estudiante').setAttribute('readonly', true);
    
    if (notaActual !== null) {
        document.getElementById('modal_nota').value = notaActual;
    }
    if (observacionesActuales) {
        document.getElementById('modal_observaciones').value = observacionesActuales;
    }
    
    // Preseleccionar curso y bimestre actuales
    const urlParams = new URLSearchParams(window.location.search);
    const cursoActual = urlParams.get('curso');
    const bimestreActual = urlParams.get('bimestre');
    
    if (cursoActual) {
        // Buscar el código del curso
        const cursoSelect = document.getElementById('modal_codigo_curso');
        for (let option of cursoSelect.options) {
            if (option.value.includes(cursoActual)) {
                option.selected = true;
                break;
            }
        }
    }
    
    if (bimestreActual) {
        document.getElementById('modal_bimestre').value = bimestreActual;
    }
    
    document.querySelector('#registrarNotaModal .modal-title').textContent = 
        `Editar Nota - ${nombreEstudiante}`;
    
    new bootstrap.Modal(document.getElementById('registrarNotaModal')).show();
}

function verObservaciones(observaciones) {
    alert(`Observaciones:\n\n${observaciones}`);
}

function exportarNotas() {
    const urlParams = new URLSearchParams(window.location.search);
    const queryString = urlParams.toString();
    window.open(`../api/export_grades.php?${queryString}`, '_blank');
}

function registroMasivo() {
    alert('Función de registro masivo en desarrollo');
}

// Limpiar modal al cerrarse
document.getElementById('registrarNotaModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('formRegistrarNota').reset();
    document.getElementById('modal_codigo_estudiante').removeAttribute('readonly');
    document.querySelector('#registrarNotaModal .modal-title').textContent = 'Registrar Nota Individual';
});

// Cargar secciones al cargar la página si hay un curso seleccionado
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('curso').value) {
        cargarSecciones();
    }
});
</script>

<?php include '../includes/footer.php'; ?>