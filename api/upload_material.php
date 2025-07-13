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
    $required_fields = ['titulo', 'id_asignacion'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo $field es obligatorio");
        }
    }

    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $id_asignacion = intval($_POST['id_asignacion']);
    $es_visible = isset($_POST['es_visible']) ? 1 : 0;
    $fecha_disponible = $_POST['fecha_disponible'] ?? null;

    // Validar que la asignación pertenece al docente (excepto director)
    if ($_SESSION['tipo_usuario'] === 'docente') {
        $stmt = $pdo->prepare("
            SELECT id_asignacion 
            FROM asignaciones 
            WHERE id_asignacion = ? AND id_personal = ? AND estado = 'ACTIVO'
        ");
        $stmt->execute([$id_asignacion, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('No tienes permisos para esta asignación');
        }
    }

    // Configuración de archivo
    $upload_dir = '../uploads/materials/';
    $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
    $max_size = 50 * 1024 * 1024; // 50MB

    $archivo_url = null;
    $nombre_archivo = null;
    $tamaño_archivo = null;

    // Procesar archivo si se subió
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['archivo'];
        
        // Validar archivo
        $validation = validateFileUpload($file, $allowed_types, $max_size);
        if (!$validation['valid']) {
            throw new Exception(implode(', ', $validation['errors']));
        }

        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generar nombre único para el archivo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'material_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $upload_dir . $filename;

        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Error al subir el archivo');
        }

        $archivo_url = 'uploads/materials/' . $filename;
        $nombre_archivo = $file['name'];
        $tamaño_archivo = $file['size'];
    }

    // Validar que hay contenido (texto o archivo)
    if (empty($descripcion) && empty($archivo_url)) {
        throw new Exception('Debe proporcionar una descripción o subir un archivo');
    }

    // Insertar material en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO materiales (
            titulo, 
            descripcion, 
            archivo_url, 
            nombre_archivo, 
            tamaño_archivo, 
            id_asignacion, 
            es_visible, 
            fecha_disponible, 
            fecha_publicacion,
            created_at, 
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
    ");

    $stmt->execute([
        $titulo,
        $descripcion,
        $archivo_url,
        $nombre_archivo,
        $tamaño_archivo,
        $id_asignacion,
        $es_visible,
        $fecha_disponible
    ]);

    $material_id = $pdo->lastInsertId();

    // Log de actividad
    logActivity(
        $pdo, 
        $_SESSION['user_id'], 
        $_SESSION['tipo_usuario'], 
        'MATERIAL_CREADO',
        "Material '$titulo' creado para asignación $id_asignacion"
    );

    // Obtener información del material creado para la respuesta
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            c.nombre as curso_nombre,
            c.codigo as curso_codigo,
            s.letra_seccion,
            g.numero_grado
        FROM materiales m
        JOIN asignaciones a ON m.id_asignacion = a.id_asignacion
        JOIN cursos c ON a.id_curso = c.id_curso
        JOIN secciones s ON a.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        WHERE m.id_material = ?
    ");
    $stmt->execute([$material_id]);
    $material_info = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Material subido exitosamente',
        'data' => [
            'id_material' => $material_id,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'archivo_url' => $archivo_url,
            'nombre_archivo' => $nombre_archivo,
            'tamaño_archivo' => $tamaño_archivo ? formatBytes($tamaño_archivo) : null,
            'curso_info' => $material_info ? [
                'curso_nombre' => $material_info['curso_nombre'],
                'curso_codigo' => $material_info['curso_codigo'],
                'seccion' => $material_info['numero_grado'] . '°' . $material_info['letra_seccion']
            ] : null,
            'fecha_publicacion' => date('Y-m-d H:i:s'),
            'es_visible' => $es_visible
        ]
    ]);

} catch (Exception $e) {
    // Log del error
    error_log("Error en upload_material.php: " . $e->getMessage());
    
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