<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['tipo_usuario'], ['docente', 'director'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Validar datos requeridos
    $required_fields = ['titulo', 'descripcion', 'id_asignacion', 'fecha_limite'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo $field es obligatorio");
        }
    }

    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $id_asignacion = intval($_POST['id_asignacion']);
    $fecha_limite = $_POST['fecha_limite'];
    $instrucciones = trim($_POST['instrucciones'] ?? '');
    $archivo_adjunto = null;

    // Validar fecha límite
    if (!validateDate($fecha_limite, 'Y-m-d H:i')) {
        throw new Exception('Formato de fecha límite inválido');
    }

    // Verificar que la fecha límite sea futura
    $fecha_limite_obj = new DateTime($fecha_limite);
    $ahora = new DateTime();
    if ($fecha_limite_obj <= $ahora) {
        throw new Exception('La fecha límite debe ser futura');
    }

    // Verificar que la asignación pertenece al docente (excepto director)
    if ($_SESSION['tipo_usuario'] === 'docente') {
        $stmt = $pdo->prepare("
            SELECT a.*, c.nombre as curso_nombre, s.letra_seccion, g.numero_grado
            FROM asignaciones a
            JOIN cursos c ON a.id_curso = c.id_curso
            JOIN secciones s ON a.id_seccion = s.id_seccion
            JOIN grados g ON s.id_grado = g.id_grado
            WHERE a.id_asignacion = ? AND a.id_personal = ? AND a.estado = 'ACTIVO'
        ");
        $stmt->execute([$id_asignacion, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, c.nombre as curso_nombre, s.letra_seccion, g.numero_grado
            FROM asignaciones a
            JOIN cursos c ON a.id_curso = c.id_curso
            JOIN secciones s ON a.id_seccion = s.id_seccion
            JOIN grados g ON s.id_grado = g.id_grado
            WHERE a.id_asignacion = ? AND a.estado = 'ACTIVO'
        ");
        $stmt->execute([$id_asignacion]);
    }
    
    $asignacion = $stmt->fetch();
    if (!$asignacion) {
        throw new Exception('Asignación no encontrada o sin permisos');
    }

    // Procesar archivo adjunto si existe
    if (isset($_FILES['archivo_adjunto']) && $_FILES['archivo_adjunto']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['archivo_adjunto'];
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        // Validar archivo
        $validation = validateFileUpload($file, $allowed_types, $max_size);
        if (!$validation['valid']) {
            throw new Exception(implode(', ', $validation['errors']));
        }

        // Crear directorio si no existe
        $upload_dir = '../uploads/tasks/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generar nombre único
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'task_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $upload_dir . $filename;

        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Error al subir el archivo adjunto');
        }

        $archivo_adjunto = 'uploads/tasks/' . $filename;
    }

    // Insertar tarea en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO tareas (
            titulo,
            descripcion,
            instrucciones,
            id_asignacion,
            fecha_limite,
            archivo_adjunto,
            estado,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVA', NOW(), NOW())
    ");

    $stmt->execute([
        $titulo,
        $descripcion,
        $instrucciones,
        $id_asignacion,
        $fecha_limite,
        $archivo_adjunto
    ]);

    $tarea_id = $pdo->lastInsertId();

    // Log de actividad
    logActivity(
        $pdo, 
        $_SESSION['user_id'], 
        $_SESSION['tipo_usuario'], 
        'TAREA_CREADA',
        "Tarea '$titulo' creada para {$asignacion['curso_nombre']} - {$asignacion['numero_grado']}°{$asignacion['letra_seccion']}"
    );

    // Obtener número de estudiantes afectados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_estudiantes
        FROM matriculas m
        WHERE m.id_seccion = ? AND m.id_anio = ? AND m.estado = 'ACTIVO'
    ");
    $stmt->execute([$asignacion['id_seccion'], $asignacion['id_anio']]);
    $total_estudiantes = $stmt->fetch()['total_estudiantes'];

    echo json_encode([
        'success' => true,
        'message' => 'Tarea creada exitosamente',
        'data' => [
            'id_tarea' => $tarea_id,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'instrucciones' => $instrucciones,
            'fecha_limite' => $fecha_limite,
            'archivo_adjunto' => $archivo_adjunto,
            'curso_info' => [
                'nombre' => $asignacion['curso_nombre'],
                'grado_seccion' => $asignacion['numero_grado'] . '°' . $asignacion['letra_seccion']
            ],
            'total_estudiantes_afectados' => $total_estudiantes,
            'estado' => 'ACTIVA'
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en create_task.php: " . $e->getMessage());
    
    // Si se subió un archivo y hubo error, intentar eliminarlo
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>