<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Materiales de Estudio - Estudiante";

// Obtener datos del estudiante y matrícula
$stmt = $pdo->prepare("
    SELECT m.*, g.numero_grado, s.letra_seccion, a.anio
    FROM matriculas m
    JOIN secciones s ON m.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    JOIN anios_academicos a ON m.id_anio = a.id_anio
    WHERE m.id_estudiante = ? AND m.estado = 'ACTIVO' AND a.estado = 'ACTIVO'
");
$stmt->execute([$_SESSION['user_id']]);
$matricula_actual = $stmt->fetch();

if (!$matricula_actual) {
    $error_message = "No se encontró matrícula activa para el estudiante.";
    include '../includes/header.php';
    include '../includes/navbar.php';
    echo '<div class="container mt-4"><div class="alert alert-danger">' . $error_message . '</div></div>';
    include '../includes/footer.php';
    exit();
}

// Registrar descarga de material
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    try {
        // Verificar que el material existe y es visible
        $stmt = $pdo->prepare("
            SELECT m.*, a.id_seccion, a.id_anio
            FROM materiales m
            JOIN asignaciones a ON m.id_asignacion = a.id_asignacion
            WHERE m.id_material = ? AND m.es_visible = 1
        ");
        $stmt->execute([$_GET['download']]);
        $material = $stmt->fetch();
        
        if ($material && $material['id_seccion'] == $matricula_actual['id_seccion'] && 
            $material['id_anio'] == $matricula_actual['id_anio']) {
            
            // Registrar descarga
            $stmt = $pdo->prepare("
                INSERT INTO descargas_materiales (id_material, id_estudiante, fecha_descarga, ip_address)
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $_GET['download'],
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR']
            ]);
            
            // Redirigir al archivo
            if ($material['archivo_adjunto']) {
                $archivo_path = '../uploads/materials/' . $material['archivo_adjunto'];
                if (file_exists($archivo_path)) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $material['archivo_adjunto'] . '"');
                    header('Content-Length: ' . filesize($archivo_path));
                    readfile($archivo_path);
                    exit();
                }
            } elseif ($material['enlace_externo']) {
                header('Location: ' . $material['enlace_externo']);
                exit();
            }
        }
    } catch (Exception $e) {
        $error_message = "Error al descargar el material.";
    }
}

// Filtros
$filtro_curso = $_GET['curso'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';

// Obtener cursos del estudiante
$stmt = $pdo->prepare("
    SELECT DISTINCT c.*, a.id_asignacion
    FROM cursos c
    JOIN asignaciones a ON c.id_curso = a.id_curso
    WHERE a.id_seccion = ? AND a.id_anio = ? AND a.estado = 'ACTIVO'
    ORDER BY c.nombre
");
$stmt->execute([$matricula_actual['id_seccion'], $matricula_actual['id_anio']]);
$cursos_disponibles = $stmt->fetchAll();

// Obtener materiales disponibles para el estudiante
$sql = "
    SELECT 
        m.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        CONCAT(p.nombres, ' ', p.apellido_paterno) as docente_nombre,
        COUNT(dm.id_descarga) as mis_descargas
    FROM materiales m
    JOIN asignaciones a ON m.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN personal p ON a.id_personal = p.id_personal
    LEFT JOIN descargas_materiales dm ON m.id_material = dm.id_material AND dm.id_estudiante = ?
    WHERE a.id_seccion = ? AND a.id_anio = ? AND m.es_visible = 1
";

$params = [$_SESSION['user_id'], $matricula_actual['id_seccion'], $matricula_actual['id_anio']];

if ($filtro_curso) {
    $sql .= " AND c.id_curso = ?";
    $params[] = $filtro_curso;
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3><i class="fas fa-folder-open"></i> Materiales de Estudio</h3>
                    <p class="text-muted mb-0">
                        <?= $matricula_actual['numero_grado'] ?>° <?= $matricula_actual['letra_seccion'] ?> - 
                        Año <?= $matricula_actual['anio'] ?>
                    </p>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label for="curso" class="form-label">Curso</label>
                            <select name="curso" id="curso" class="form-select">
                                <option value="">Todos los cursos</option>
                                <?php foreach ($cursos_disponibles as $curso): ?>
                                    <option value="<?= $curso['id_curso'] ?>" <?= $filtro_curso == $curso['id_curso'] ? 'selected' : '' ?>>
                                        [<?= $curso['codigo'] ?>] <?= htmlspecialchars($curso['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="tipo" class="form-label">Tipo de Material</label>
                            <select name="tipo" id="tipo" class="form-select">
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
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="materials.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de materiales -->
            <?php if (!empty($materiales)): ?>
                <div class="row">
                    <?php foreach ($materiales as $material): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="card-title mb-1">
                                            <?= htmlspecialchars($material['titulo']) ?>
                                        </h6>
                                        <small class="text-muted">
                                            [<?= $material['curso_codigo'] ?>] <?= htmlspecialchars($material['curso_nombre']) ?>
                                            <br><?= htmlspecialchars($material['docente_nombre']) ?>
                                        </small>
                                    </div>
                                    <span class="badge <?= getTipoMaterialBadgeClass($material['tipo_material']) ?>">
                                        <?= getTipoMaterialTexto($material['tipo_material']) ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <?php if ($material['descripcion']): ?>
                                        <p class="card-text">
                                            <?= nl2br(htmlspecialchars(substr($material['descripcion'], 0, 120))) ?>
                                            <?= strlen($material['descripcion']) > 120 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Publicado</small>
                                            <strong><?= date('d/m/Y', strtotime($material['fecha_publicacion'])) ?></strong>
                                            <small class="d-block text-muted"><?= date('H:i', strtotime($material['fecha_publicacion'])) ?></small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Mis descargas</small>
                                            <strong class="text-info"><?= $material['mis_descargas'] ?></strong>
                                            <small class="d-block text-muted">veces</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex gap-2">
                                        <?php if ($material['archivo_adjunto']): ?>
                                            <a href="?download=<?= $material['id_material'] ?>" 
                                               class="btn btn-primary flex-fill">
                                                <i class="fas fa-download"></i> Descargar
                                            </a>
                                            <button class="btn btn-outline-info" 
                                                    onclick="previewMaterial(<?= $material['id_material'] ?>, '<?= htmlspecialchars($material['titulo']) ?>', '<?= $material['archivo_adjunto'] ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        <?php elseif ($material['enlace_externo']): ?>
                                            <a href="?download=<?= $material['id_material'] ?>" 
                                               target="_blank" class="btn btn-primary flex-fill">
                                                <i class="fas fa-external-link-alt"></i> Abrir Enlace
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-outline-secondary" 
                                                onclick="verDetalles(<?= $material['id_material'] ?>)">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Estadísticas -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h5 class="text-primary"><?= count($materiales) ?></h5>
                                        <small class="text-muted">Materiales disponibles</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h5 class="text-info"><?= count(array_unique(array_column($materiales, 'curso_codigo'))) ?></h5>
                                        <small class="text-muted">Cursos con materiales</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h5 class="text-success"><?= array_sum(array_column($materiales, 'mis_descargas')) ?></h5>
                                        <small class="text-muted">Total mis descargas</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h5 class="text-warning"><?= count(array_filter($materiales, fn($m) => $m['mis_descargas'] == 0)) ?></h5>
                                        <small class="text-muted">Sin descargar</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                    <h5>No hay materiales disponibles</h5>
                    <p class="text-muted">
                        <?php if ($filtro_curso || $filtro_tipo): ?>
                            No se encontraron materiales con los filtros aplicados.
                        <?php else: ?>
                            Aún no hay materiales de estudio publicados en tus cursos.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para preview de materiales -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewTitle">Vista Previa del Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Contenido cargado dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" id="downloadButton" class="btn btn-primary">
                    <i class="fas fa-download"></i> Descargar
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal para detalles del material -->
<div class="modal fade" id="detallesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detallesContent">
                <!-- Contenido cargado dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function previewMaterial(idMaterial, titulo, archivo) {
    const extension = archivo.split('.').pop().toLowerCase();
    const modalTitle = document.getElementById('previewTitle');
    const modalContent = document.getElementById('previewContent');
    const downloadButton = document.getElementById('downloadButton');
    
    modalTitle.textContent = titulo;
    downloadButton.href = `?download=${idMaterial}`;
    
    // Limpiar contenido previo
    modalContent.innerHTML = '';
    
    const filePath = `../uploads/materials/${archivo}`;
    
    if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
        // Vista previa de imagen
        modalContent.innerHTML = `
            <div class="text-center">
                <img src="${filePath}" class="img-fluid" alt="${titulo}" style="max-height: 500px;">
            </div>
        `;
    } else if (extension === 'pdf') {
        // Vista previa de PDF
        modalContent.innerHTML = `
            <div class="text-center">
                <embed src="${filePath}" type="application/pdf" width="100%" height="500px">
                <p class="mt-2 text-muted">Si no puedes ver el PDF, <a href="${filePath}" target="_blank">ábrelo en una nueva ventana</a></p>
            </div>
        `;
    } else if (['mp4', 'webm', 'ogg'].includes(extension)) {
        // Vista previa de video
        modalContent.innerHTML = `
            <div class="text-center">
                <video controls style="max-width: 100%; max-height: 500px;">
                    <source src="${filePath}" type="video/${extension}">
                    Tu navegador no soporta la reproducción de video.
                </video>
            </div>
        `;
    } else if (['mp3', 'wav', 'ogg'].includes(extension)) {
        // Vista previa de audio
        modalContent.innerHTML = `
            <div class="text-center">
                <audio controls class="w-100">
                    <source src="${filePath}" type="audio/${extension}">
                    Tu navegador no soporta la reproducción de audio.
                </audio>
            </div>
        `;
    } else {
        // Archivo no previsualizable
        modalContent.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-file fa-3x text-muted mb-3"></i>
                <h5>Vista previa no disponible</h5>
                <p class="text-muted">Este tipo de archivo no se puede previsualizar en el navegador.</p>
                <p>Descarga el archivo para abrirlo con la aplicación adecuada.</p>
            </div>
        `;
    }
    
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

function verDetalles(idMaterial) {
    fetch(`../api/get_material_details.php?id=${idMaterial}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('detallesContent').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('detallesModal')).show();
            } else {
                alert('Error al cargar los detalles del material');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los detalles');
        });
}
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