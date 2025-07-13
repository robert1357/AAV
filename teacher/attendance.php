<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DOCENTE') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Registro de Asistencia - Docente";

// Procesar registro de asistencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_asistencia'])) {
    try {
        $pdo->beginTransaction();
        
        $fecha = $_POST['fecha'];
        $id_asignacion = $_POST['id_asignacion'];
        $asistencias = $_POST['asistencia'] ?? [];
        
        // Eliminar registros existentes para esa fecha y asignación
        $stmt = $pdo->prepare("
            DELETE FROM asistencias 
            WHERE fecha = ? AND id_asignacion = ?
        ");
        $stmt->execute([$fecha, $id_asignacion]);
        
        // Insertar nuevos registros
        $stmt = $pdo->prepare("
            INSERT INTO asistencias (id_matricula, id_asignacion, fecha, estado, observaciones)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($asistencias as $id_matricula => $datos) {
            $estado = $datos['estado'];
            $observaciones = $datos['observaciones'] ?? null;
            
            $stmt->execute([
                $id_matricula,
                $id_asignacion,
                $fecha,
                $estado,
                $observaciones
            ]);
        }
        
        $pdo->commit();
        $success_message = "Asistencia registrada exitosamente para " . count($asistencias) . " estudiantes";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al registrar asistencia: " . $e->getMessage();
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

// Filtros y datos
$filtro_asignacion = $_GET['asignacion'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? date('Y-m-d');

$estudiantes_asistencia = [];
$asistencias_existentes = [];

if ($filtro_asignacion) {
    // Obtener estudiantes de la asignación
    $stmt = $pdo->prepare("
        SELECT 
            m.id_matricula,
            e.codigo_estudiante,
            CONCAT(e.apellido_paterno, ' ', e.apellido_materno, ', ', e.nombres) as nombre_completo,
            e.sexo,
            g.numero_grado,
            s.letra_seccion
        FROM matriculas m
        JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
        JOIN asignaciones a ON m.id_seccion = a.id_seccion AND m.id_anio = a.id_anio
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        WHERE a.id_asignacion = ? AND m.estado = 'ACTIVO'
        ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres
    ");
    $stmt->execute([$filtro_asignacion]);
    $estudiantes_asistencia = $stmt->fetchAll();
    
    // Obtener asistencias existentes para esa fecha
    $stmt = $pdo->prepare("
        SELECT *
        FROM asistencias
        WHERE id_asignacion = ? AND fecha = ?
    ");
    $stmt->execute([$filtro_asignacion, $filtro_fecha]);
    $asistencias_existentes = [];
    foreach ($stmt->fetchAll() as $asistencia) {
        $asistencias_existentes[$asistencia['id_matricula']] = $asistencia;
    }
}

// Obtener estadísticas de asistencia
$estadisticas = [];
if ($filtro_asignacion) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT a.id_matricula) as total_estudiantes,
            COUNT(CASE WHEN a.estado = 'PRESENTE' THEN 1 END) as presentes,
            COUNT(CASE WHEN a.estado = 'AUSENTE' THEN 1 END) as ausentes,
            COUNT(CASE WHEN a.estado = 'TARDANZA' THEN 1 END) as tardanzas,
            COUNT(CASE WHEN a.estado = 'JUSTIFICADO' THEN 1 END) as justificados
        FROM asistencias a
        WHERE a.id_asignacion = ? AND a.fecha = ?
    ");
    $stmt->execute([$filtro_asignacion, $filtro_fecha]);
    $estadisticas = $stmt->fetch();
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
                        <i class="fas fa-user-check"></i> Registro de Asistencia
                    </h3>
                    <div>
                        <button type="button" class="btn btn-info" onclick="verReporteAsistencia()">
                            <i class="fas fa-chart-bar"></i> Reporte de Asistencia
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

                    <!-- Filtros -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-filter"></i> Seleccionar Curso y Fecha
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-6">
                                    <label for="asignacion" class="form-label">Curso/Sección *</label>
                                    <select name="asignacion" id="asignacion" class="form-select" required>
                                        <option value="">Seleccione curso y sección...</option>
                                        <?php foreach ($asignaciones as $asig): ?>
                                            <option value="<?= $asig['id_asignacion'] ?>" <?= $filtro_asignacion == $asig['id_asignacion'] ? 'selected' : '' ?>>
                                                [<?= $asig['curso_codigo'] ?>] <?= htmlspecialchars($asig['curso_nombre']) ?> - 
                                                <?= $asig['numero_grado'] ?>° <?= $asig['letra_seccion'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="fecha" class="form-label">Fecha *</label>
                                    <input type="date" name="fecha" id="fecha" class="form-control" 
                                           value="<?= $filtro_fecha ?>" required max="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Cargar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Estadísticas -->
                    <?php if (!empty($estadisticas) && $estadisticas['total_estudiantes'] > 0): ?>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Resumen de Asistencia - <?= date('d/m/Y', strtotime($filtro_fecha)) ?></h6>
                                        <div class="row text-center">
                                            <div class="col">
                                                <div class="text-primary">
                                                    <h4><?= count($estudiantes_asistencia) ?></h4>
                                                    <small>Total Estudiantes</small>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="text-success">
                                                    <h4><?= $estadisticas['presentes'] ?></h4>
                                                    <small>Presentes</small>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="text-danger">
                                                    <h4><?= $estadisticas['ausentes'] ?></h4>
                                                    <small>Ausentes</small>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="text-warning">
                                                    <h4><?= $estadisticas['tardanzas'] ?></h4>
                                                    <small>Tardanzas</small>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="text-info">
                                                    <h4><?= $estadisticas['justificados'] ?></h4>
                                                    <small>Justificados</small>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="text-secondary">
                                                    <h4><?= number_format(($estadisticas['presentes'] / count($estudiantes_asistencia)) * 100, 1) ?>%</h4>
                                                    <small>% Asistencia</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Lista de estudiantes -->
                    <?php if (!empty($estudiantes_asistencia)): ?>
                        <form method="POST" id="formAsistencia">
                            <input type="hidden" name="fecha" value="<?= $filtro_fecha ?>">
                            <input type="hidden" name="id_asignacion" value="<?= $filtro_asignacion ?>">
                            
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Lista de Estudiantes</h6>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-success" onclick="marcarTodos('PRESENTE')">
                                            <i class="fas fa-check"></i> Todos Presentes
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="marcarTodos('AUSENTE')">
                                            <i class="fas fa-times"></i> Todos Ausentes
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="5%">N°</th>
                                                    <th width="15%">Código</th>
                                                    <th width="35%">Estudiante</th>
                                                    <th width="10%">Sexo</th>
                                                    <th width="20%">Estado</th>
                                                    <th width="15%">Observaciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($estudiantes_asistencia as $index => $estudiante): ?>
                                                    <?php 
                                                    $asistencia_actual = $asistencias_existentes[$estudiante['id_matricula']] ?? null;
                                                    $estado_actual = $asistencia_actual['estado'] ?? 'PRESENTE';
                                                    $observaciones_actual = $asistencia_actual['observaciones'] ?? '';
                                                    ?>
                                                    <tr>
                                                        <td><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($estudiante['codigo_estudiante']) ?></td>
                                                        <td>
                                                            <?= htmlspecialchars($estudiante['nombre_completo']) ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= $estudiante['sexo'] === 'MASCULINO' ? 'primary' : 'pink' ?>">
                                                                <?= $estudiante['sexo'] === 'MASCULINO' ? 'M' : 'F' ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <select name="asistencia[<?= $estudiante['id_matricula'] ?>][estado]" 
                                                                    class="form-select form-select-sm estado-asistencia" required>
                                                                <option value="PRESENTE" <?= $estado_actual === 'PRESENTE' ? 'selected' : '' ?>>
                                                                    ✓ Presente
                                                                </option>
                                                                <option value="AUSENTE" <?= $estado_actual === 'AUSENTE' ? 'selected' : '' ?>>
                                                                    ✗ Ausente
                                                                </option>
                                                                <option value="TARDANZA" <?= $estado_actual === 'TARDANZA' ? 'selected' : '' ?>>
                                                                    ⏰ Tardanza
                                                                </option>
                                                                <option value="JUSTIFICADO" <?= $estado_actual === 'JUSTIFICADO' ? 'selected' : '' ?>>
                                                                    📋 Justificado
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="text" 
                                                                   name="asistencia[<?= $estudiante['id_matricula'] ?>][observaciones]" 
                                                                   class="form-control form-control-sm"
                                                                   placeholder="Opcional..."
                                                                   value="<?= htmlspecialchars($observaciones_actual) ?>">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="mt-3 d-flex justify-content-between">
                                        <div>
                                            <small class="text-muted">
                                                Fecha: <strong><?= date('d/m/Y', strtotime($filtro_fecha)) ?></strong> | 
                                                Total estudiantes: <strong><?= count($estudiantes_asistencia) ?></strong>
                                            </small>
                                        </div>
                                        <button type="submit" name="registrar_asistencia" class="btn btn-success btn-lg">
                                            <i class="fas fa-save"></i> Guardar Asistencia
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php elseif ($filtro_asignacion): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No hay estudiantes</h5>
                            <p class="text-muted">No se encontraron estudiantes matriculados en esta asignación.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-check fa-3x text-muted mb-3"></i>
                            <h5>Selecciona un curso y fecha</h5>
                            <p class="text-muted">Usa los filtros superiores para cargar la lista de estudiantes y registrar su asistencia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function marcarTodos(estado) {
    const selects = document.querySelectorAll('.estado-asistencia');
    selects.forEach(select => {
        select.value = estado;
    });
    
    // Actualizar contadores si existen
    actualizarContadores();
}

function actualizarContadores() {
    const selects = document.querySelectorAll('.estado-asistencia');
    const contadores = {
        'PRESENTE': 0,
        'AUSENTE': 0,
        'TARDANZA': 0,
        'JUSTIFICADO': 0
    };
    
    selects.forEach(select => {
        if (contadores.hasOwnProperty(select.value)) {
            contadores[select.value]++;
        }
    });
    
    // Actualizar display si existe
    Object.keys(contadores).forEach(estado => {
        const elemento = document.getElementById(`contador-${estado.toLowerCase()}`);
        if (elemento) {
            elemento.textContent = contadores[estado];
        }
    });
}

function verReporteAsistencia() {
    const asignacion = document.getElementById('asignacion').value;
    if (!asignacion) {
        alert('Selecciona un curso primero');
        return;
    }
    
    const url = `../reports/attendance_report.php?asignacion=${asignacion}`;
    window.open(url, '_blank');
}

// Confirmar antes de enviar si hay cambios
document.getElementById('formAsistencia').addEventListener('submit', function(e) {
    const confirmacion = confirm('¿Confirmas que deseas guardar esta asistencia? Los datos anteriores para esta fecha serán reemplazados.');
    if (!confirmacion) {
        e.preventDefault();
        return false;
    }
});

// Agregar event listeners para actualizar contadores en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('.estado-asistencia');
    selects.forEach(select => {
        select.addEventListener('change', actualizarContadores);
    });
    
    // Actualizar contadores iniciales
    actualizarContadores();
});

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey) {
        switch(e.key) {
            case '1':
                e.preventDefault();
                marcarTodos('PRESENTE');
                break;
            case '2':
                e.preventDefault();
                marcarTodos('AUSENTE');
                break;
            case '3':
                e.preventDefault();
                marcarTodos('TARDANZA');
                break;
            case '4':
                e.preventDefault();
                marcarTodos('JUSTIFICADO');
                break;
        }
    }
});
</script>

<style>
.estado-asistencia {
    font-size: 0.875rem;
}

.estado-asistencia option[value="PRESENTE"] {
    color: #198754;
}

.estado-asistencia option[value="AUSENTE"] {
    color: #dc3545;
}

.estado-asistencia option[value="TARDANZA"] {
    color: #ffc107;
}

.estado-asistencia option[value="JUSTIFICADO"] {
    color: #0dcaf0;
}

.bg-pink {
    background-color: #e91e63 !important;
}
</style>

<?php include '../includes/footer.php'; ?>