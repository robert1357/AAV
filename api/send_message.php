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

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Método no permitido';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$recipient_type = $input['recipient_type'] ?? ''; // 'individual', 'group', 'course', 'section'
$recipient_ids = $input['recipient_ids'] ?? [];
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');
$priority = $input['priority'] ?? 'NORMAL'; // BAJA, NORMAL, ALTA, URGENTE
$course_id = $input['course_id'] ?? null;
$section_id = $input['section_id'] ?? null;

// Validaciones
if (empty($subject) || empty($message)) {
    $response['message'] = 'Asunto y mensaje son requeridos';
    echo json_encode($response);
    exit;
}

if (empty($recipient_ids) && $recipient_type === 'individual') {
    $response['message'] = 'Debe seleccionar al menos un destinatario';
    echo json_encode($response);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Crear el mensaje principal
    $stmt = $pdo->prepare("
        INSERT INTO mensajes (id_remitente, asunto, contenido, prioridad, fecha_envio, tipo_destinatario)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $subject, $message, $priority, $recipient_type]);
    $message_id = $pdo->lastInsertId();
    
    $recipients_list = [];
    
    switch ($recipient_type) {
        case 'individual':
            // Envío individual a usuarios específicos
            foreach ($recipient_ids as $recipient_id) {
                $recipients_list[] = $recipient_id;
            }
            break;
            
        case 'course':
            // Envío a todos los estudiantes de un curso
            if ($course_id) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT e.id_estudiante
                    FROM estudiantes e
                    JOIN matriculas m ON e.id_estudiante = m.id_estudiante
                    JOIN secciones s ON m.id_seccion = s.id_seccion
                    JOIN asignaciones a ON s.id_seccion = a.id_seccion
                    WHERE a.id_curso = ? AND m.estado = 'ACTIVO'
                ");
                $stmt->execute([$course_id]);
                $recipients_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            break;
            
        case 'section':
            // Envío a todos los estudiantes de una sección
            if ($section_id) {
                $stmt = $pdo->prepare("
                    SELECT e.id_estudiante
                    FROM estudiantes e
                    JOIN matriculas m ON e.id_estudiante = m.id_estudiante
                    WHERE m.id_seccion = ? AND m.estado = 'ACTIVO'
                ");
                $stmt->execute([$section_id]);
                $recipients_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            break;
            
        case 'teachers':
            // Envío a todos los docentes
            $stmt = $pdo->prepare("
                SELECT id_personal
                FROM personal
                WHERE cargo IN ('DOCENTE', 'DOCENTE_DAIP') AND activo = 1
            ");
            $stmt->execute();
            $recipients_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'staff':
            // Envío a todo el personal
            $stmt = $pdo->prepare("
                SELECT id_personal
                FROM personal
                WHERE activo = 1 AND id_personal != ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $recipients_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'all_students':
            // Envío a todos los estudiantes
            $stmt = $pdo->prepare("
                SELECT e.id_estudiante
                FROM estudiantes e
                JOIN matriculas m ON e.id_estudiante = m.id_estudiante
                WHERE m.estado = 'ACTIVO'
            ");
            $stmt->execute();
            $recipients_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
    }
    
    // Crear registros de destinatarios
    if (!empty($recipients_list)) {
        $stmt = $pdo->prepare("
            INSERT INTO mensaje_destinatarios (id_mensaje, id_destinatario, tipo_destinatario)
            VALUES (?, ?, ?)
        ");
        
        $dest_type = in_array($recipient_type, ['course', 'section', 'all_students']) ? 'ESTUDIANTE' : 'PERSONAL';
        
        foreach ($recipients_list as $recipient_id) {
            $stmt->execute([$message_id, $recipient_id, $dest_type]);
        }
    }
    
    // Crear notificaciones
    $notification_type = $priority === 'URGENTE' ? 'EMERGENCIA' : 'MENSAJE';
    
    $stmt = $pdo->prepare("
        INSERT INTO notificaciones (titulo, mensaje, tipo, prioridad, id_remitente, 
                                   tipo_destinatario, fecha_creacion, url_accion)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $notification_title = "Nuevo mensaje: " . $subject;
    $notification_message = substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '');
    $notification_url = "/messages/view.php?id=" . $message_id;
    
    if ($recipient_type === 'individual') {
        foreach ($recipients_list as $recipient_id) {
            $stmt->execute([
                $notification_title,
                $notification_message,
                $notification_type,
                $priority,
                $_SESSION['user_id'],
                'INDIVIDUAL',
                $notification_url
            ]);
            
            $notification_id = $pdo->lastInsertId();
            
            // Asociar notificación con destinatario específico
            $stmt2 = $pdo->prepare("
                UPDATE notificaciones 
                SET id_destinatario = ?
                WHERE id_notificacion = ?
            ");
            $stmt2->execute([$recipient_id, $notification_id]);
        }
    } else {
        // Notificación grupal
        $dest_type = in_array($recipient_type, ['course', 'section', 'all_students']) ? 'ESTUDIANTE' : 'PERSONAL';
        
        $stmt->execute([
            $notification_title,
            $notification_message,
            $notification_type,
            $priority,
            $_SESSION['user_id'],
            $dest_type,
            $notification_url
        ]);
    }
    
    $pdo->commit();
    
    $response['success'] = true;
    $response['message'] = 'Mensaje enviado exitosamente';
    $response['message_id'] = $message_id;
    $response['recipients_count'] = count($recipients_list);
    
    // Log de actividad
    logActivity($_SESSION['user_id'], 'SEND_MESSAGE', 
                "Mensaje enviado: '{$subject}' a " . count($recipients_list) . " destinatarios");
    
} catch (PDOException $e) {
    $pdo->rollBack();
    $response['message'] = 'Error al enviar mensaje';
    error_log("Error en send_message: " . $e->getMessage());
}

echo json_encode($response);

function logActivity($user_id, $action, $description) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_actividades (id_usuario, accion, descripcion, fecha, ip_address)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$user_id, $action, $description, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}
?>