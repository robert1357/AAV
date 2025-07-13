<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
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
    if (empty($_POST['id_tarea'])) {
        throw new Exception('ID de tarea es obligatorio');
    }

    $id_tarea = intval($_POST['id_tarea']);
    $comentarios = trim($_POST['comentarios'] ?? '');

    // Obtener información de la tarea y verificar permisos
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            a.id_seccion,
            a.id_anio,
            m.id_matricula,
            m.id_estudiante,
            c.nombre as curso_nombre,
            c.codigo as curso_codigo
        FROM tareas t
        JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
        JOIN cursos c ON a.id_curso = c.id_curso
        JOIN matriculas m ON a.id_seccion = m.id_seccion AND a.id_anio = m.id_anio
        WHERE t.id_tarea = ? AND m.id_estudiante = ? AND m.estado = 'ACTIVO' AND t.estado = 'ACTIVA'
    ");
    $stmt->execute([$id_tarea, $_SESSION['user_id']]);
    $tarea = $stmt->fetch();

    if (!$tarea) {
        throw new Exception('Tarea no encontrada o no tienes permisos para acceder a ella');
    }

    // Verificar si ya existe una entrega
    $stmt = $pdo->prepare("
        SELECT id_entrega, estado 
        FROM entregas_tareas 
        WHERE id_tarea = ? AND id_matricula = ?
    ");
    $stmt->execute([$id_tarea, $tarea['id_matricula']]);
    $entrega_existente = $stmt->fetch();

    if ($entrega_existente && $entrega_existente['estado'] === 'CALIFICADO') {
        throw new Exception('Esta tarea ya ha sido calificada y no se puede modificar');
    }

    // Configuración de archivo
    $upload_dir = '../uploads/assignments/';
    $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
    $max_size = 25 * 1024 * 1024; // 25MB

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
        $filename = 'entrega_' . $id_tarea . '_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;

        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Error al subir el archivo');
        }

        $archivo_url = 'uploads/assignments/' . $filename;
        $nombre_archivo = $file['name'];
        $tamaño_archivo = $file['size'];
    }

    // Validar que hay contenido (texto o archivo)
    if (empty($comentarios) && empty($archivo_url)) {
        throw new Exception('Debe proporcionar comentarios o subir un archivo');
    }

    // Determinar el estado de la entrega
    $fecha_actual = new DateTime();
    $fecha_limite = new DateTime($tarea['fecha_limite']);
    $estado_entrega = ($fecha_actual > $fecha_limite) ? 'TARDE' : 'ENTREGADO';

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        if ($entrega_existente) {
            // Actualizar entrega existente
            $stmt = $pdo->prepare("
                UPDATE entregas_tareas 
                SET 
                    comentarios = ?,
                    archivo_url = COALESCE(?, archivo_url),
                    nombre_archivo = COALESCE(?, nombre_archivo),
                    tamaño_archivo = COALESCE(?, tamaño_archivo),
                    fecha_entrega = NOW(),
                    estado = ?,
                    updated_at = NOW()
                WHERE id_entrega = ?
            ");
            
            $stmt->execute([
                $comentarios,
                $archivo_url,
                $nombre_archivo,
                $tamaño_archivo,
                $estado_entrega,
                $entrega_existente['id_entrega']
            ]);
            
            $entrega_id = $entrega_existente['id_entrega'];
            $accion = 'actualizada';
            
        } else {
            // Crear nueva entrega
            $stmt = $pdo->prepare("
                INSERT INTO entregas_tareas (
                    id_tarea,
                    id_matricula,
                    comentarios,
                    archivo_url,
                    nombre_archivo,
                    tamaño_archivo,
                    fecha_entrega,
                    estado,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $id_tarea,
                $tarea['id_matricula'],
                $comentarios,
                $archivo_url,
                $nombre_archivo,
                $tamaño_archivo,
                $estado_entrega
            ]);
            
            $entrega_id = $pdo->lastInsertId();
            $accion = 'enviada';
        }

        // Log de actividad
        logActivity(
            $pdo, 
            $_SESSION['user_id'], 
            $_SESSION['tipo_usuario'], 
            'TAREA_ENTREGADA',
            "Tarea '{$tarea['titulo']}' $accion para el curso {$tarea['curso_codigo']}"
        );

        $pdo->commit();

        // Preparar respuesta
        $response_data = [
            'id_entrega' => $entrega_id,
            'id_tarea' => $id_tarea,
            'titulo_tarea' => $tarea['titulo'],
            'curso_info' => [
                'nombre' => $tarea['curso_nombre'],
                'codigo' => $tarea['curso_codigo']
            ],
            'comentarios' => $comentarios,
            'archivo_url' => $archivo_url,
            'nombre_archivo' => $nombre_archivo,
            'tamaño_archivo' => $tamaño_archivo ? formatBytes($tamaño_archivo) : null,
            'fecha_entrega' => date('Y-m-d H:i:s'),
            'estado' => $estado_entrega,
            'es_tarde' => $estado_entrega === 'TARDE',
            'accion' => $accion
        ];

        echo json_encode([
            'success' => true,
            'message' => "Tarea $accion exitosamente" . ($estado_entrega === 'TARDE' ? ' (entregada después del plazo)' : ''),
            'data' => $response_data
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // Log del error
    error_log("Error en submit_assignment.php: " . $e->getMessage());
    
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