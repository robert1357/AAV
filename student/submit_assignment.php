<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: assignments.php');
    exit();
}

$page_title = "Entregar Tarea - Estudiante";
$id_tarea = $_GET['id'];

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
    header('Location: assignments.php?error=no_matricula');
    exit();
}

// Obtener detalles de la tarea
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        CONCAT(p.nombres, ' ', p.apellido_paterno) as docente_nombre,
        DATEDIFF(t.fecha_limite, NOW()) as dias_restantes,
        CASE 
            WHEN t.fecha_limite < NOW() THEN 'VENCIDA'
            WHEN DATEDIFF(t.fecha_limite, NOW()) <= 1 THEN 'URGENTE'
            ELSE 'ACTIVA'
        END as estado_tarea
    FROM tareas t
    JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN personal p ON a.id_personal = p.id_personal
    WHERE t.id_tarea = ? 
    AND a.id_seccion = ? 
    AND a.id_anio = ?
");
$stmt->execute([$id_tarea, $matricula_actual['id_seccion'], $matricula_actual['id_anio']]);
$tarea = $stmt->fetch();

if (!$tarea) {
    header('Location: assignments.php?error=tarea_no_encontrada');
    exit();
}

// Verificar si ya existe una entrega
$stmt = $pdo->prepare("
    SELECT * FROM entregas_tareas 
    WHERE id_tarea = ? AND id_matricula = ?
");
$stmt->execute([$id_tarea, $matricula_actual['id_matricula']]);
$entrega_existente = $stmt->fetch();

// Procesar entrega de tarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_tarea'])) {
    try {
        $pdo->beginTransaction();
        
        $archivo_subido = null;
        
        // Manejar subida de archivo
        if (isset($_FILES['archivo_tarea']) && $_FILES['archivo_tarea']['error'] === UPLOAD_ERR_OK) {
            $archivo_temp = $_FILES['archivo_tarea']['tmp_name'];
            $nombre_archivo = $_FILES['archivo_tarea']['name'];
            $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
            $tamaño_archivo = $_FILES['archivo_tarea']['size'];
            
            // Validaciones
            $extensiones_permitidas = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'ppt', 'pptx'];
            $tamaño_maximo = 10 * 1024 * 1024; // 10MB
            
            if (!in_array(strtolower($extension), $extensiones_permitidas)) {
                throw new Exception("Tipo de archivo no permitido. Extensiones permitidas: " . implode(', ', $extensiones_permitidas));
            }
            
            if ($tamaño_archivo > $tamaño_maximo) {
                throw new Exception("El archivo es demasiado grande. Tamaño máximo: 10MB");
            }
            
            // Crear directorio si no existe
            $directorio_destino = '../uploads/assignments/';
            if (!is_dir($directorio_destino)) {
                mkdir($directorio_destino, 0755, true);
            }
            
            // Generar nombre único
            $codigo_estudiante = $_SESSION['codigo_estudiante'] ?? $_SESSION['user_id'];
            $nombre_unico = 'tarea_' . $id_tarea . '_' . $codigo_estudiante . '_' . time() . '.' . $extension;
            $ruta_destino = $directorio_destino . $nombre_unico;
            
            if (!move_uploaded_file($archivo_temp, $ruta_destino)) {
                throw new Exception("Error al subir el archivo");
            }
            
            $archivo_subido = $nombre_unico;
        }
        
        // Insertar o actualizar entrega (el trigger se encargará de validaciones adicionales)
        if ($entrega_existente) {
            // Actualizar entrega existente
            $stmt = $pdo->prepare("
                UPDATE entregas_tareas 
                SET contenido_respuesta = ?, 
                    archivo_adjunto = COALESCE(?, archivo_adjunto),
                    fecha_entrega = NOW(),
                    estado = 'ENTREGADO'
                WHERE id_entrega = ?
            ");
            $stmt->execute([
                $_POST['contenido_respuesta'],
                $archivo_subido,
                $entrega_existente['id_entrega']
            ]);
        } else {
            // Nueva entrega
            $stmt = $pdo->prepare("
                INSERT INTO entregas_tareas (
                    id_tarea, 
                    id_matricula, 
                    contenido_respuesta, 
                    archivo_adjunto, 
                    fecha_entrega,
                    estado
                ) VALUES (?, ?, ?, ?, NOW(), 'ENTREGADO')
            ");
            $stmt->execute([
                $id_tarea,
                $matricula_actual['id_matricula'],
                $_POST['contenido_respuesta'],
                $archivo_subido
            ]);
        }
        
        $pdo->commit();
        
        // Redirigir con mensaje de éxito
        header('Location: assignments.php?success=tarea_entregada');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al enviar tarea: " . $e->getMessage();
        
        // Eliminar archivo subido si hay error
        if ($archivo_subido && file_exists('../uploads/assignments/' . $archivo_subido)) {
            unlink('../uploads/assignments/' . $archivo_subido);
        }
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Información de la tarea -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks"></i> <?= htmlspecialchars($tarea['titulo']) ?>
                    </h5>
                    <span class="badge fs-6 bg-<?= 
                        $tarea['estado_tarea'] === 'VENCIDA' ? 'danger' : 
                        ($tarea['estado_tarea'] === 'URGENTE' ? 'warning' : 'success') 
                    ?>">
                        <?= $tarea['estado_tarea'] === 'VENCIDA' ? 'VENCIDA' : 
                            ($tarea['estado_tarea'] === 'URGENTE' ? 'URGENTE' : 'ACTIVA') ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <p class="mb-2">
                                <strong>Curso:</strong> [<?= $tarea['curso_codigo'] ?>] <?= htmlspecialchars($tarea['curso_nombre']) ?><br>
                                <strong>Docente:</strong> <?= htmlspecialchars($tarea['docente_nombre']) ?>
                            </p>
                            
                            <div class="alert alert-light">
                                <h6>Descripción:</h6>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($tarea['descripcion'])) ?></p>
                            </div>
                            
                            <?php if ($tarea['instrucciones']): ?>
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Instrucciones:</h6>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($tarea['instrucciones'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6>Información de Entrega</h6>
                                    <div class="mb-3">
                                        <div class="text-<?= $tarea['dias_restantes'] < 0 ? 'danger' : ($tarea['dias_restantes'] <= 1 ? 'warning' : 'success') ?>">
                                            <i class="fas fa-clock fa-2x"></i>
                                            <h5 class="mt-2">
                                                <?= date('d/m/Y', strtotime($tarea['fecha_limite'])) ?>
                                            </h5>
                                            <small>
                                                <?= date('H:i', strtotime($tarea['fecha_limite'])) ?>
                                            </small>
                                        </div>
                                        <div class="mt-2">
                                            <?php if ($tarea['dias_restantes'] >= 0): ?>
                                                <small class="text-muted">
                                                    <?= $tarea['dias_restantes'] == 0 ? 'Vence hoy' : 
                                                        'Faltan ' . $tarea['dias_restantes'] . ' días' ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-danger">
                                                    Vencida hace <?= abs($tarea['dias_restantes']) ?> días
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <span class="badge bg-primary fs-6">
                                            <?= $tarea['puntos_maximos'] ?> puntos máx.
                                        </span>
                                    </div>
                                    <div>
                                        <small class="text-muted">Tipo: <?= ucfirst($tarea['tipo']) ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estado de entrega actual -->
            <?php if ($entrega_existente): ?>
                <div class="card mb-4 border-<?= $entrega_existente['estado'] === 'CALIFICADO' ? 'success' : 'info' ?>">
                    <div class="card-header bg-<?= $entrega_existente['estado'] === 'CALIFICADO' ? 'light-success' : 'light-info' ?>">
                        <h6 class="mb-0">
                            <i class="fas fa-check-circle"></i> Tu Entrega Actual
                            <?php if ($entrega_existente['estado'] === 'CALIFICADO'): ?>
                                <span class="badge bg-success ms-2">Calificada</span>
                            <?php else: ?>
                                <span class="badge bg-info ms-2">Entregada</span>
                            <?php endif; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p><strong>Fecha de entrega:</strong> <?= date('d/m/Y H:i', strtotime($entrega_existente['fecha_entrega'])) ?></p>
                                
                                <?php if ($entrega_existente['estado'] === 'CALIFICADO'): ?>
                                    <div class="mb-3">
                                        <strong>Calificación:</strong> 
                                        <span class="badge fs-5 bg-<?= $entrega_existente['calificacion'] >= ($tarea['puntos_maximos'] * 0.7) ? 'success' : 'danger' ?>">
                                            <?= $entrega_existente['calificacion'] ?>/<?= $tarea['puntos_maximos'] ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($entrega_existente['observaciones_docente']): ?>
                                        <div class="alert alert-info">
                                            <strong>Observaciones del docente:</strong><br>
                                            <?= nl2br(htmlspecialchars($entrega_existente['observaciones_docente'])) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <strong>Tu respuesta:</strong><br>
                                    <div class="border rounded p-3 bg-light">
                                        <?= nl2br(htmlspecialchars($entrega_existente['contenido_respuesta'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <?php if ($entrega_existente['archivo_adjunto']): ?>
                                    <div class="text-center">
                                        <strong>Archivo adjunto:</strong><br>
                                        <a href="../uploads/assignments/<?= $entrega_existente['archivo_adjunto'] ?>" 
                                           target="_blank" class="btn btn-outline-primary mt-2">
                                            <i class="fas fa-download"></i> Descargar archivo
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulario de entrega -->
            <?php if ($tarea['estado_tarea'] !== 'VENCIDA' || $entrega_existente): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-paper-plane"></i> 
                            <?= $entrega_existente ? 'Actualizar Entrega' : 'Enviar Tarea' ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($tarea['estado_tarea'] === 'URGENTE'): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <strong>¡Atención!</strong> Esta tarea vence muy pronto. Asegúrate de enviarla a tiempo.
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="formEntregarTarea">
                            <div class="mb-4">
                                <label for="contenido_respuesta" class="form-label">
                                    Tu Respuesta <span class="text-danger">*</span>
                                </label>
                                <textarea name="contenido_respuesta" id="contenido_respuesta" 
                                          class="form-control" rows="8" required
                                          placeholder="Escribe tu respuesta completa aquí..."><?= $entrega_existente ? htmlspecialchars($entrega_existente['contenido_respuesta']) : '' ?></textarea>
                                <div class="form-text">
                                    Desarrolla tu respuesta de manera clara y completa. Puedes incluir explicaciones, procedimientos, conclusiones, etc.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="archivo_tarea" class="form-label">
                                    Archivo Adjunto (Opcional)
                                </label>
                                <input type="file" name="archivo_tarea" id="archivo_tarea" 
                                       class="form-control" 
                                       accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.ppt,.pptx">
                                <div class="form-text">
                                    Formatos permitidos: PDF, DOC, DOCX, TXT, JPG, JPEG, PNG, PPT, PPTX<br>
                                    Tamaño máximo: 10MB
                                    <?php if ($entrega_existente && $entrega_existente['archivo_adjunto']): ?>
                                        <br><strong>Archivo actual:</strong> <?= $entrega_existente['archivo_adjunto'] ?>
                                        <small class="text-muted">(Si seleccionas un nuevo archivo, reemplazará al actual)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmar_entrega" required>
                                    <label class="form-check-label" for="confirmar_entrega">
                                        Confirmo que he revisado mi respuesta y deseo enviarla
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="assignments.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver a Tareas
                                </a>
                                <button type="submit" name="enviar_tarea" class="btn btn-success btn-lg">
                                    <i class="fas fa-paper-plane"></i> 
                                    <?= $entrega_existente ? 'Actualizar Entrega' : 'Enviar Tarea' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-times-circle"></i> Tarea Vencida
                        </h6>
                    </div>
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-3x text-danger mb-3"></i>
                        <h5>Esta tarea ya venció</h5>
                        <p class="text-muted">
                            La fecha límite era el <?= date('d/m/Y', strtotime($tarea['fecha_limite'])) ?> 
                            a las <?= date('H:i', strtotime($tarea['fecha_limite'])) ?>
                        </p>
                        <p class="text-muted">
                            No es posible enviar la tarea después de la fecha límite. 
                            Contacta con tu docente si necesitas una extensión.
                        </p>
                        <a href="assignments.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Volver a Tareas
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Validación antes de enviar
document.getElementById('formEntregarTarea').addEventListener('submit', function(e) {
    const contenido = document.getElementById('contenido_respuesta').value.trim();
    const confirmar = document.getElementById('confirmar_entrega').checked;
    
    if (contenido.length < 10) {
        e.preventDefault();
        alert('La respuesta debe tener al menos 10 caracteres');
        return false;
    }
    
    if (!confirmar) {
        e.preventDefault();
        alert('Debes confirmar que deseas enviar la tarea');
        return false;
    }
    
    // Confirmación final
    const confirmacion = confirm('¿Estás seguro de que deseas enviar esta tarea? Una vez enviada, podrás modificarla pero quedará registrada la fecha de entrega.');
    if (!confirmacion) {
        e.preventDefault();
        return false;
    }
    
    return true;
});

// Validación de archivo
document.getElementById('archivo_tarea').addEventListener('change', function() {
    const archivo = this.files[0];
    if (archivo) {
        const tamaño = archivo.size;
        const extensión = archivo.name.split('.').pop().toLowerCase();
        const extensionesPermitidas = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'ppt', 'pptx'];
        const tamañoMaximo = 10 * 1024 * 1024; // 10MB
        
        if (!extensionesPermitidas.includes(extensión)) {
            alert('Tipo de archivo no permitido. Formatos válidos: ' + extensionesPermitidas.join(', '));
            this.value = '';
            return;
        }
        
        if (tamaño > tamañoMaximo) {
            alert('El archivo es demasiado grande. Tamaño máximo: 10MB');
            this.value = '';
            return;
        }
    }
});

// Auto-guardar borrador (cada 30 segundos)
setInterval(function() {
    const contenido = document.getElementById('contenido_respuesta').value;
    if (contenido.length > 0) {
        localStorage.setItem('borrador_tarea_<?= $id_tarea ?>', contenido);
    }
}, 30000);

// Cargar borrador al iniciar
document.addEventListener('DOMContentLoaded', function() {
    const borrador = localStorage.getItem('borrador_tarea_<?= $id_tarea ?>');
    if (borrador && !document.getElementById('contenido_respuesta').value) {
        if (confirm('Se encontró un borrador guardado. ¿Deseas cargarlo?')) {
            document.getElementById('contenido_respuesta').value = borrador;
        }
    }
});

// Limpiar borrador al enviar exitosamente
<?php if (isset($_GET['success'])): ?>
localStorage.removeItem('borrador_tarea_<?= $id_tarea ?>');
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>