<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DOCENTE') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Crear Curso - Docente";

// Obtener datos necesarios
$stmt = $pdo->query("SELECT * FROM grados ORDER BY numero_grado");
$grados = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM areas_academicas ORDER BY nombre");
$areas = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM anios_academicos WHERE estado = 'ACTIVO' ORDER BY anio DESC");
$anos_academicos = $stmt->fetchAll();

// Procesar creación de curso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_curso'])) {
    try {
        $pdo->beginTransaction();
        
        // Insertar el curso
        $stmt = $pdo->prepare("
            INSERT INTO cursos (
                codigo, nombre, descripcion, creditos, horas_semanales, 
                id_area, id_grado, estado, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVO', NOW())
        ");
        
        $stmt->execute([
            $_POST['codigo'],
            $_POST['nombre'],
            $_POST['descripcion'],
            $_POST['creditos'],
            $_POST['horas_semanales'],
            $_POST['id_area'],
            $_POST['id_grado']
        ]);
        
        $id_curso = $pdo->lastInsertId();
        
        // Asignar el docente al curso
        $stmt = $pdo->prepare("
            INSERT INTO asignaciones (
                id_personal, id_curso, id_seccion, id_anio, 
                fecha_asignacion, estado
            ) VALUES (?, ?, ?, ?, CURDATE(), 'ACTIVO')
        ");
        
        // Asignar a todas las secciones seleccionadas
        foreach ($_POST['secciones'] as $id_seccion) {
            $stmt->execute([
                $_SESSION['user_id'],
                $id_curso,
                $id_seccion,
                $_POST['id_anio']
            ]);
        }
        
        $pdo->commit();
        $success_message = "Curso creado y asignado exitosamente";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al crear curso: " . $e->getMessage();
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-plus-circle"></i> Crear Nuevo Curso
                    </h3>
                    <div class="card-tools">
                        <a href="my_courses.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver a Mis Cursos
                        </a>
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

                    <form method="POST" id="crearCursoForm">
                        <div class="row">
                            <!-- Información Básica del Curso -->
                            <div class="col-md-6">
                                <h5 class="mb-3 text-primary">
                                    <i class="fas fa-book"></i> Información del Curso
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="codigo" class="form-label">Código del Curso *</label>
                                    <input type="text" name="codigo" id="codigo" class="form-control" 
                                           placeholder="Ej: MAT001" required maxlength="10">
                                    <div class="form-text">Código único para identificar el curso</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre del Curso *</label>
                                    <input type="text" name="nombre" id="nombre" class="form-control" 
                                           placeholder="Ej: Matemática Básica" required maxlength="100">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <textarea name="descripcion" id="descripcion" class="form-control" 
                                              rows="4" placeholder="Describe los objetivos y contenido del curso..."></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="creditos" class="form-label">Créditos</label>
                                            <input type="number" name="creditos" id="creditos" class="form-control" 
                                                   min="1" max="10" value="1">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="horas_semanales" class="form-label">Horas Semanales</label>
                                            <input type="number" name="horas_semanales" id="horas_semanales" class="form-control" 
                                                   min="1" max="20" value="4">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configuración Académica -->
                            <div class="col-md-6">
                                <h5 class="mb-3 text-success">
                                    <i class="fas fa-cogs"></i> Configuración Académica
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="id_area" class="form-label">Área Académica *</label>
                                    <select name="id_area" id="id_area" class="form-select" required>
                                        <option value="">Seleccione un área...</option>
                                        <?php foreach ($areas as $area): ?>
                                            <option value="<?= $area['id_area'] ?>">
                                                <?= htmlspecialchars($area['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="id_grado" class="form-label">Grado *</label>
                                    <select name="id_grado" id="id_grado" class="form-select" required>
                                        <option value="">Seleccione un grado...</option>
                                        <?php foreach ($grados as $grado): ?>
                                            <option value="<?= $grado['id_grado'] ?>">
                                                <?= $grado['numero_grado'] ?>° - <?= htmlspecialchars($grado['descripcion']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="id_anio" class="form-label">Año Académico *</label>
                                    <select name="id_anio" id="id_anio" class="form-select" required>
                                        <?php foreach ($anos_academicos as $ano): ?>
                                            <option value="<?= $ano['id_anio'] ?>" <?= $ano['anio'] == date('Y') ? 'selected' : '' ?>>
                                                <?= $ano['anio'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Secciones a Asignar *</label>
                                    <div id="secciones-container" class="border rounded p-3">
                                        <p class="text-muted mb-0">Seleccione un grado para ver las secciones disponibles</p>
                                    </div>
                                    <div class="form-text">Seleccione las secciones donde dictará este curso</div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-12">
                                <h5 class="mb-3 text-warning">
                                    <i class="fas fa-clipboard-list"></i> Vista Previa del Curso
                                </h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <div id="vista-previa">
                                            <p class="text-muted">Complete la información para ver la vista previa</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="my_courses.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" name="crear_curso" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Crear Curso
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Cargar secciones cuando se selecciona un grado
document.getElementById('id_grado').addEventListener('change', function() {
    const gradoId = this.value;
    const seccionesContainer = document.getElementById('secciones-container');
    
    if (gradoId) {
        seccionesContainer.innerHTML = '<p class="text-muted mb-0">Cargando secciones...</p>';
        
        fetch(`../api/get_sections.php?grado_id=${gradoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    let html = '<div class="row">';
                    data.forEach(seccion => {
                        html += `
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input seccion-checkbox" type="checkbox" 
                                           name="secciones[]" value="${seccion.id_seccion}" 
                                           id="seccion_${seccion.id_seccion}">
                                    <label class="form-check-label" for="seccion_${seccion.id_seccion}">
                                        Sección ${seccion.letra_seccion}
                                        <small class="text-muted d-block">
                                            ${seccion.total_estudiantes || 0} estudiantes
                                        </small>
                                    </label>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    seccionesContainer.innerHTML = html;
                    
                    // Agregar event listeners para la vista previa
                    document.querySelectorAll('.seccion-checkbox').forEach(checkbox => {
                        checkbox.addEventListener('change', actualizarVistaPrevia);
                    });
                } else {
                    seccionesContainer.innerHTML = '<p class="text-warning mb-0">No hay secciones disponibles para este grado</p>';
                }
            })
            .catch(error => {
                seccionesContainer.innerHTML = '<p class="text-danger mb-0">Error al cargar las secciones</p>';
                console.error('Error:', error);
            });
    } else {
        seccionesContainer.innerHTML = '<p class="text-muted mb-0">Seleccione un grado para ver las secciones disponibles</p>';
    }
    
    actualizarVistaPrevia();
});

// Actualizar vista previa cuando cambien los campos
['codigo', 'nombre', 'descripcion', 'creditos', 'horas_semanales'].forEach(field => {
    document.getElementById(field).addEventListener('input', actualizarVistaPrevia);
});

document.getElementById('id_area').addEventListener('change', actualizarVistaPrevia);

function actualizarVistaPrevia() {
    const codigo = document.getElementById('codigo').value;
    const nombre = document.getElementById('nombre').value;
    const descripcion = document.getElementById('descripcion').value;
    const creditos = document.getElementById('creditos').value;
    const horas = document.getElementById('horas_semanales').value;
    const areaSelect = document.getElementById('id_area');
    const area = areaSelect.options[areaSelect.selectedIndex]?.text || '';
    const gradoSelect = document.getElementById('id_grado');
    const grado = gradoSelect.options[gradoSelect.selectedIndex]?.text || '';
    
    const seccionesSeleccionadas = Array.from(document.querySelectorAll('.seccion-checkbox:checked'))
        .map(cb => cb.nextElementSibling.textContent.trim().split('\n')[0]);
    
    let html = '';
    
    if (codigo || nombre) {
        html = `
            <div class="row">
                <div class="col-md-8">
                    <h6 class="mb-1">${codigo ? `[${codigo}] ` : ''}${nombre || 'Nombre del curso'}</h6>
                    <p class="text-muted small mb-2">${descripcion || 'Sin descripción'}</p>
                    <div class="d-flex gap-3">
                        ${area ? `<span class="badge bg-primary">${area}</span>` : ''}
                        ${grado ? `<span class="badge bg-success">${grado}</span>` : ''}
                        ${creditos ? `<span class="badge bg-info">${creditos} créditos</span>` : ''}
                        ${horas ? `<span class="badge bg-warning">${horas} hrs/sem</span>` : ''}
                    </div>
                </div>
                <div class="col-md-4">
                    <h6 class="small mb-1">Secciones asignadas:</h6>
                    <div class="d-flex flex-wrap gap-1">
                        ${seccionesSeleccionadas.length > 0 ? 
                            seccionesSeleccionadas.map(s => `<span class="badge bg-secondary">${s}</span>`).join(' ') :
                            '<span class="text-muted small">Ninguna seleccionada</span>'
                        }
                    </div>
                </div>
            </div>
        `;
    } else {
        html = '<p class="text-muted">Complete la información para ver la vista previa</p>';
    }
    
    document.getElementById('vista-previa').innerHTML = html;
}

// Validación del formulario
document.getElementById('crearCursoForm').addEventListener('submit', function(e) {
    const seccionesSeleccionadas = document.querySelectorAll('.seccion-checkbox:checked');
    
    if (seccionesSeleccionadas.length === 0) {
        e.preventDefault();
        alert('Debe seleccionar al menos una sección para el curso');
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>