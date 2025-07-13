<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DOCENTE') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Crear Tarea";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$course_id = $_GET['course_id'] ?? null;
$message = '';
$message_type = '';

// Obtener cursos del docente
$stmt = $pdo->prepare("
    SELECT c.*, g.nombre_grado, s.letra_seccion
    FROM asignaciones a
    JOIN cursos c ON a.id_curso = c.id_curso
    JOIN secciones s ON a.id_seccion = s.id_seccion
    JOIN grados g ON s.id_grado = g.id_grado
    WHERE a.id_personal = ? AND c.activo = 1
    ORDER BY g.numero_grado, s.letra_seccion, c.nombre
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $course_id = $_POST['course_id'];
    $tipo = $_POST['tipo'];
    $fecha_entrega = $_POST['fecha_entrega'];
    $hora_entrega = $_POST['hora_entrega'];
    $puntaje_maximo = $_POST['puntaje_maximo'];
    $instrucciones = trim($_POST['instrucciones']);
    $permite_entrega_tardia = isset($_POST['permite_entrega_tardia']) ? 1 : 0;
    $visible_estudiantes = isset($_POST['visible_estudiantes']) ? 1 : 0;
    
    if (empty($titulo) || empty($descripcion) || empty($course_id) || empty($fecha_entrega)) {
        $message = "Todos los campos obligatorios deben ser completados.";
        $message_type = "danger";
    } elseif (strtotime($fecha_entrega . ' ' . $hora_entrega) <= time()) {
        $message = "La fecha de entrega debe ser futura.";
        $message_type = "danger";
    } else {
        try {
            $fecha_entrega_completa = $fecha_entrega . ' ' . $hora_entrega;
            
            $stmt = $pdo->prepare("
                INSERT INTO tareas (id_curso, titulo, descripcion, tipo, fecha_entrega, 
                                  puntaje_maximo, instrucciones, permite_entrega_tardia, 
                                  visible_estudiantes, creado_por, fecha_creacion, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
            ");
            
            $stmt->execute([
                $course_id, $titulo, $descripcion, $tipo, $fecha_entrega_completa,
                $puntaje_maximo, $instrucciones, $permite_entrega_tardia,
                $visible_estudiantes, $_SESSION['user_id']
            ]);
            
            $tarea_id = $pdo->lastInsertId();
            
            $message = "Tarea creada exitosamente.";
            $message_type = "success";
            
            // Crear notificación para estudiantes si es visible
            if ($visible_estudiantes) {
                $stmt = $pdo->prepare("
                    INSERT INTO notificaciones (titulo, mensaje, tipo, id_remitente, tipo_destinatario, fecha_creacion)
                    VALUES (?, ?, 'TAREA', ?, 'ESTUDIANTE', NOW())
                ");
                
                $notif_titulo = "Nueva tarea: " . $titulo;
                $notif_mensaje = "Se ha publicado una nueva tarea en el curso. Fecha de entrega: " . date('d/m/Y H:i', strtotime($fecha_entrega_completa));
                
                $stmt->execute([$notif_titulo, $notif_mensaje, $_SESSION['user_id']]);
            }
            
        } catch (PDOException $e) {
            $message = "Error al crear la tarea.";
            $message_type = "danger";
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?php echo $page_title; ?></h2>
                <a href="assignments.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Tareas
                </a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="titulo" class="form-label">Título de la Tarea *</label>
                                    <input type="text" class="form-control" id="titulo" name="titulo" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción *</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="4" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instrucciones" class="form-label">Instrucciones Detalladas</label>
                                    <textarea class="form-control" id="instrucciones" name="instrucciones" rows="6" 
                                            placeholder="Proporcione instrucciones detalladas sobre cómo completar la tarea..."></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="course_id" class="form-label">Curso *</label>
                                    <select class="form-select" id="course_id" name="course_id" required>
                                        <option value="">Seleccionar curso</option>
                                        <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id_curso']; ?>" <?php echo ($course_id == $course['id_curso']) ? 'selected' : ''; ?>>
                                            <?php echo $course['nombre'] . ' - ' . $course['nombre_grado'] . ' ' . $course['letra_seccion']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tipo" class="form-label">Tipo de Tarea *</label>
                                    <select class="form-select" id="tipo" name="tipo" required>
                                        <option value="">Seleccionar tipo</option>
                                        <option value="TAREA">Tarea</option>
                                        <option value="PROYECTO">Proyecto</option>
                                        <option value="INVESTIGACION">Investigación</option>
                                        <option value="PRACTICA">Práctica</option>
                                        <option value="EXAMEN">Examen</option>
                                        <option value="ENSAYO">Ensayo</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fecha_entrega" class="form-label">Fecha de Entrega *</label>
                                    <input type="date" class="form-control" id="fecha_entrega" name="fecha_entrega" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hora_entrega" class="form-label">Hora de Entrega *</label>
                                    <input type="time" class="form-control" id="hora_entrega" name="hora_entrega" 
                                           value="23:59" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="puntaje_maximo" class="form-label">Puntaje Máximo *</label>
                                    <input type="number" class="form-control" id="puntaje_maximo" name="puntaje_maximo" 
                                           min="1" max="20" value="20" required>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="permite_entrega_tardia" 
                                               name="permite_entrega_tardia">
                                        <label class="form-check-label" for="permite_entrega_tardia">
                                            Permitir entrega tardía
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="visible_estudiantes" 
                                               name="visible_estudiantes" checked>
                                        <label class="form-check-label" for="visible_estudiantes">
                                            Visible para estudiantes
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" onclick="history.back()">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Crear Tarea
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-llenar fecha mínima
document.getElementById('fecha_entrega').min = new Date().toISOString().split('T')[0];

// Validación de fecha
document.getElementById('fecha_entrega').addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate < today) {
        alert('La fecha de entrega no puede ser anterior a hoy');
        this.value = '';
    }
});

// Contador de caracteres para descripción
document.getElementById('descripcion').addEventListener('input', function() {
    const maxLength = 500;
    const currentLength = this.value.length;
    
    if (currentLength > maxLength) {
        this.value = this.value.substring(0, maxLength);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>