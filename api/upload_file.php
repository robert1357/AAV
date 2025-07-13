<?php
header('Content-Type: application/json');
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$response = ['success' => false, 'message' => '', 'file_info' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Método no permitido';
    echo json_encode($response);
    exit;
}

// Verificar si se subió un archivo
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Error al subir el archivo';
    echo json_encode($response);
    exit;
}

$file = $_FILES['file'];
$upload_type = $_POST['upload_type'] ?? 'general'; // general, material, assignment, profile_pic
$course_id = $_POST['course_id'] ?? null;
$assignment_id = $_POST['assignment_id'] ?? null;

// Configuración de upload según tipo
$upload_configs = [
    'material' => [
        'path' => '../uploads/materials/',
        'max_size' => 50 * 1024 * 1024, // 50MB
        'allowed_types' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif']
    ],
    'assignment' => [
        'path' => '../uploads/assignments/',
        'max_size' => 20 * 1024 * 1024, // 20MB
        'allowed_types' => ['pdf', 'doc', 'docx', 'zip', 'rar']
    ],
    'profile_pic' => [
        'path' => '../uploads/profile_pics/',
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif']
    ],
    'general' => [
        'path' => '../uploads/',
        'max_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']
    ]
];

$config = $upload_configs[$upload_type] ?? $upload_configs['general'];

// Validar tamaño del archivo
if ($file['size'] > $config['max_size']) {
    $response['message'] = 'El archivo es demasiado grande. Tamaño máximo: ' . formatBytes($config['max_size']);
    echo json_encode($response);
    exit;
}

// Validar tipo de archivo
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_extension, $config['allowed_types'])) {
    $response['message'] = 'Tipo de archivo no permitido. Tipos permitidos: ' . implode(', ', $config['allowed_types']);
    echo json_encode($response);
    exit;
}

// Crear directorio si no existe
if (!file_exists($config['path'])) {
    mkdir($config['path'], 0755, true);
}

// Generar nombre único para el archivo
$file_name = time() . '_' . uniqid() . '.' . $file_extension;
$file_path = $config['path'] . $file_name;

// Mover archivo
if (move_uploaded_file($file['tmp_name'], $file_path)) {
    try {
        // Registrar archivo en la base de datos
        $stmt = $pdo->prepare("
            INSERT INTO archivos_subidos (nombre_original, nombre_archivo, ruta_archivo, tipo_upload, 
                                         tamaño, extension, id_usuario, id_curso, id_tarea, fecha_subida)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $file['name'],
            $file_name,
            $file_path,
            $upload_type,
            $file['size'],
            $file_extension,
            $_SESSION['user_id'],
            $course_id,
            $assignment_id
        ]);
        
        $file_id = $pdo->lastInsertId();
        
        $response['success'] = true;
        $response['message'] = 'Archivo subido exitosamente';
        $response['file_info'] = [
            'id' => $file_id,
            'name' => $file['name'],
            'size' => $file['size'],
            'size_formatted' => formatBytes($file['size']),
            'type' => $file_extension,
            'url' => str_replace('../', '/', $file_path)
        ];
        
        // Log de actividad
        logActivity($_SESSION['user_id'], 'UPLOAD_FILE', "Archivo subido: {$file['name']}");
        
    } catch (PDOException $e) {
        // Eliminar archivo si hay error en BD
        unlink($file_path);
        $response['message'] = 'Error al registrar el archivo en la base de datos';
    }
} else {
    $response['message'] = 'Error al mover el archivo al directorio de destino';
}

echo json_encode($response);

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function logActivity($user_id, $action, $description) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_actividades (id_usuario, accion, descripcion, fecha, ip_address)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$user_id, $action, $description, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Log error silencioso
    }
}
?>