<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DOCENTE') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Materiales de Curso - Docente";

// Procesar subida de material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_material'])) {
    try {
        $pdo->beginTransaction();
        
        $archivo_subido = null;
        
        // Manejar subida de archivo
        if (isset($_FILES['archivo_material']) && $_FILES['archivo_material']['error'] === UPLOAD_ERR_OK) {
            $archivo_temp = $_FILES['archivo_material']['tmp_name'];
            $nombre_archivo = $_FILES['archivo_material']['name'];
            $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
            $tamaño_archivo = $_FILES['archivo_material']['size'];
            
            // Validaciones
            $extensiones_permitidas = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'mp4', 'avi', 'mp3'];
            $tamaño_maximo = 50 * 1024 * 1024; // 50MB
            
            if (!in_array(strtolower($extension), $extensiones_permitidas)) {
                throw new Exception("Tipo de archivo no permitido.");
            }
            
            if ($tamaño_archivo > $tamaño_maximo) {
                throw new Exception("El archivo es demasiado grande. Máximo 50MB.");
            }
            
            // Crear directorio si no existe
            $directorio_destino = '../uploads/materials/';
            if (!is_dir($directorio_destino)) {
                mkdir($directorio_destino, 0755, true);
            }
            
            // Generar nombre único
            $nombre_unico = 'material_' . $_POST['id_asignacion'] . '_' . time() . '.' . $extension;
            $ruta_destino = $directorio_destino . $nombre_unico;
            
            if (!move_uploaded_file($archivo_temp, $ruta_destino)) {
                throw new Exception("Error al subir el archivo");
            }
            
            $archivo_subido = $nombre_unico;
        }
        
        // Insertar material en la base de datos
        $stmt = $pdo->prepare("
            INSERT INTO materiales (
                id_asignacion, titulo, descripcion, tipo_material, 
                archivo_adjunto, enlace_externo, es_visible, fecha_publicacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_POST['id_asignacion'],
            $_POST['titulo'],
            $_POST['descripcion'],
            $_POST['tipo_material'],
            $archivo_subido,
            $_POST['enlace_externo'] ?: null,
            isset($_POST['es_visible']) ? 1 : 0
        ]);
        
        $pdo->commit();
        $success_message = "Material subido exitosamente";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al subir material: " . $e->getMessage();
        
        // Eliminar archivo si hay error
        if ($archivo_subido && file_exists('../uploads/materials/' . $archivo_subido)) {
            unlink('../uploads/materials/' . $archivo_subido);
        }
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

// Obtener materiales del docente
$filtro_asignacion = $_GET['asignacion'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';

$sql = "
    SELECT 
        m.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        g.numero_grado,
        s.letra_seccion,
        COUNT(dm.id_descarga) as total_descargas
    FROM materiales m
    JOIN asignaciones a ON m.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    LEFT JOIN descargas_materiales dm ON m.id_material = dm.id_material
    WHERE a.id_personal = ?
";

$params = [$_SESSION['user_id']];

if ($filtro_asignacion) {
    $sql .= " AND a.id_asignacion = ?";
    $params[] = $filtro_asignacion;
}

if ($filtro_tipo) {
    $sql .= " AND m.tipo_material = ?";
    $params[] = $filtro_tipo;
}

$sql .= " GROUP BY m.id_material ORDER BY m.fecha_publicacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materiales = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-folder-open"></i> Materiales de Curso
                    </h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subirMaterialModal">
                        <i class="fas fa-upload"></i> Subir Material
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
                            <label for="tipo" class="form-label">Tipo de Material</label>
                            <select name="tipo" id="tipo" class="form-select" onchange="aplicarFiltros()">
                                <option value="">Todos los tipos</option>
                                <option value="presentacion" <?= $filtro_tipo === 'presentacion' ? 'selected' : '' ?>>Presentación</option>
                                <option value="documento" <?= $filtro_tipo === 'documento' ? 'selected' : '' ?>>Documento</option>
                                <option value="video" <?= $filtro_tipo === 'video' ? 'selected' : '' ?>>Video</option>
                                <option value="audio" <?= $filtro_tipo === 'audio' ? 'selected' : '' ?>>Audio</option>
                                <option value="enlace" <?= $filtro_tipo === 'enlace' ? 'selected' : '' ?>>Enlace</option>
                                <option value="imagen" <?= $filtro_tipo === 'imagen' ? 'selected' : '' ?>>Imagen</option>
                                <option value="otro" <?= $filtro_tipo === 'otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <a href="materials.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpiar filtros
                            </a>
                        </div>
                    </div>

                    <!-- Lista de materiales -->
                    <?php if (!empty($materiales)): ?>
                        <div class="row">
                            <?php foreach ($materiales as $material): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1">
                                                    <?= htmlspecialchars($material['titulo']) ?>
                                                </h6>
                                                <small class="text-muted">
                                                    [<?= $material['curso_codigo'] ?>] <?= htmlspecialchars($material['curso_nombre']) ?>
                                                    <br><?= $material['numero_grado'] ?>° <?= $material['letra_seccion'] ?>
                                                </small>
                                            </div>
                                            <div class="d-flex flex-column align-items-end">
                                                <span class="badge <?= getTipoMaterialBadgeClass($material['tipo_material']) ?>">
                                                    <?= getTipoMaterialTexto($material['tipo_material']) ?>
                                                </span>
                                                <?php if (!$material['es_visible']): ?>
                                                    <span class="badge bg-secondary mt-1">Oculto</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text">
                                                <?= nl2br(htmlspecialchars(substr($material['descripcion'], 0, 100))) ?>
                                                <?= strlen($material['descripcion']) > 100 ? '...' : '' ?>
                                            </p>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Publicado</small>
                                                    <strong><?= date('d/m/Y', strtotime($material['fecha_publicacion'])) ?></strong>
                                                    <small class="d-block text-muted"><?= date('H:i', strtotime($material['fecha_publicacion'])) ?></small>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Descargas</small>
                                                    <strong class="text-info"><?= $material['total_descargas'] ?></strong>
                                                    <small class="d-block text-muted">veces</small>
                                                </div>
                                            </div>
                                            
                                            <!-- Información del archivo/enlace -->
                                            <div class="alert alert-light py-2">
                                                <?php if ($material['archivo_adjunto']): ?>
                                                    <small>
                                                        <i class="fas fa-file"></i> 
                                                        <?= htmlspecialchars($material['archivo_adjunto']) ?>
                                                    </small>
                                                <?php elseif ($material['enlace_externo']): ?>
                                                    <small>
                                                        <i class="fas fa-link"></i> 
                                                        <a href="<?= htmlspecialchars($material['enlace_externo']) ?>" target="_blank">
                                                            Enlace externo
                                                        </a>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <div class="d-flex gap-1">
                                                <?php if ($material['archivo_adjunto']): ?>
                                                    <a href="../uploads/materials/<?= $material['archivo_adjunto'] ?>" 
                                                       target="_blank" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php elseif ($material['enlace_externo']): ?>
                                                    <a href="<?= htmlspecialchars($material['enlace_externo']) ?>" 
                                                       target="_blank" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-outline-secondary btn-sm" 
                                                        onclick="editarMaterial(<?= $material['id_material'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <button class="btn btn-outline-<?= $material['es_visible'] ? 'warning' : 'success' ?> btn-sm" 
                                                        onclick="toggleVisibilidad(<?= $material['id_material'] ?>, <?= $material['es_visible'] ?>)">
                                                    <i class="fas fa-eye<?= $material['es_visible'] ? '-slash' : '' ?>"></i>
                                                </button>
                                                
                                                <button class="btn btn-outline-info btn-sm flex-fill" 
                                                        onclick="verEstadisticas(<?= $material['id_material'] ?>)">
                                                    <i class="fas fa-chart-line"></i> Stats
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5>No hay materiales disponibles</h5>
                            <p class="text-muted">
                                <?php if ($filtro_asignacion || $filtro_tipo): ?>
                                    No se encontraron materiales con los filtros aplicados.
                                <?php else: ?>
                                    Sube tu primer material para compartir contenido con tus estudiantes.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Subir Material -->
<div class="modal fade" id="subirMaterialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="formSubirMaterial">
                <div class="modal-header">
                    <h5 class="modal-title">Subir Nuevo Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="id_asignacion_modal" class="form-label">Curso/Sección *</label>
                        <select name="id_asignacion" id="id_asignacion_modal" class="form-select" required>
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
                        <label for="titulo_modal" class="form-label">Título del Material *</label>
                        <input type="text" name="titulo" id="titulo_modal" class="form-control" required maxlength="200"
                               placeholder="Ej: Presentación - Teorema de Pitágoras">
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion_modal" class="form-label">Descripción</label>
                        <textarea name="descripcion" id="descripcion_modal" class="form-control" rows="3"
                                  placeholder="Describe el contenido del material..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipo_material_modal" class="form-label">Tipo de Material *</label>
                        <select name="tipo_material" id="tipo_material_modal" class="form-select" required onchange="toggleTipoMaterial()">
                            <option value="">Seleccione...</option>
                            <option value="presentacion">Presentación</option>
                            <option value="documento">Documento</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="imagen">Imagen</option>
                            <option value="enlace">Enlace externo</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="archivo-section">
                        <label for="archivo_material" class="form-label">Archivo</label>
                        <input type="file" name="archivo_material" id="archivo_material" class="form-control"
                               accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.mp4,.avi,.mp3">
                        <div class="form-text">
                            Formatos permitidos: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, JPG, JPEG, PNG, MP4, AVI, MP3<br>
                            Tamaño máximo: 50MB
                        </div>
                    </div>
                    
                    <div class="mb-3" id="enlace-section" style="display: none;">
                        <label for="enlace_externo" class="form-label">URL del Enlace</label>
                        <input type="url" name="enlace_externo" id="enlace_externo" class="form-control"
                               placeholder="https://ejemplo.com/recurso">
                        <div class="form-text">
                            Enlace a recursos externos como videos de YouTube, documentos en la nube, etc.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="es_visible" id="es_visible" checked>
                            <label class="form-check-label" for="es_visible">
                                Visible para estudiantes
                            </label>
                            <div class="form-text">
                                Si no está marcado, el material estará oculto hasta que lo actives.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="subir_material" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Subir Material
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function aplicarFiltros() {
    const asignacion = document.getElementById('asignacion').value;
    const tipo = document.getElementById('tipo').value;
    
    const params = new URLSearchParams();
    if (asignacion) params.append('asignacion', asignacion);
    if (tipo) params.append('tipo', tipo);
    
    window.location.href = 'materials.php?' + params.toString();
}

function toggleTipoMaterial() {
    const tipo = document.getElementById('tipo_material_modal').value;
    const archivoSection = document.getElementById('archivo-section');
    const enlaceSection = document.getElementById('enlace-section');
    
    if (tipo === 'enlace') {
        archivoSection.style.display = 'none';
        enlaceSection.style.display = 'block';
        document.getElementById('archivo_material').required = false;
        document.getElementById('enlace_externo').required = true;
    } else {
        archivoSection.style.display = 'block';
        enlaceSection.style.display = 'none';
        document.getElementById('archivo_material').required = false; // Opcional para flexibilidad
        document.getElementById('enlace_externo').required = false;
    }
}

function editarMaterial(idMaterial) {
    alert('Función de edición en desarrollo');
}

function toggleVisibilidad(idMaterial, esVisible) {
    const accion = esVisible ? 'ocultar' : 'mostrar';
    if (confirm(`¿Confirmas que deseas ${accion} este material?`)) {
        fetch('../api/toggle_material_visibility.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_material: idMaterial,
                es_visible: !esVisible
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al cambiar la visibilidad');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cambiar la visibilidad');
        });
    }
}

function verEstadisticas(idMaterial) {
    window.open(`../reports/material_stats.php?id=${idMaterial}`, '_blank');
}

// Validación de archivo
document.getElementById('archivo_material').addEventListener('change', function() {
    const archivo = this.files[0];
    if (archivo) {
        const tamaño = archivo.size;
        const tamañoMaximo = 50 * 1024 * 1024; // 50MB
        
        if (tamaño > tamañoMaximo) {
            alert('El archivo es demasiado grande. Tamaño máximo: 50MB');
            this.value = '';
            return;
        }
    }
});

// Limpiar modal al cerrarse
document.getElementById('subirMaterialModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('formSubirMaterial').reset();
    toggleTipoMaterial();
});
</script>

<?php
// Funciones auxiliares
function getTipoMaterialBadgeClass($tipo) {
    $classes = [
        'presentacion' => 'bg-primary',
        'documento' => 'bg-info',
        'video' => 'bg-danger',
        'audio' => 'bg-warning',
        'imagen' => 'bg-success',
        'enlace' => 'bg-secondary',
        'otro' => 'bg-dark'
    ];
    return $classes[$tipo] ?? 'bg-secondary';
}

function getTipoMaterialTexto($tipo) {
    $textos = [
        'presentacion' => 'Presentación',
        'documento' => 'Documento',
        'video' => 'Video',
        'audio' => 'Audio',
        'imagen' => 'Imagen',
        'enlace' => 'Enlace',
        'otro' => 'Otro'
    ];
    return $textos[$tipo] ?? 'Desconocido';
}

include '../includes/footer.php'; 
?>