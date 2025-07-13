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

$response = ['success' => false, 'notifications' => [], 'unread_count' => 0];

try {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    $only_unread = $_GET['only_unread'] ?? false;
    
    // Construir query según tipo de usuario
    $where_conditions = [];
    $params = [];
    
    if ($user_type === 'student') {
        $where_conditions[] = "(n.tipo_destinatario = 'ESTUDIANTE' OR n.tipo_destinatario = 'TODOS')";
        $where_conditions[] = "(n.id_destinatario IS NULL OR n.id_destinatario = ?)";
        $params[] = $_SESSION['student_id'];
    } else {
        $where_conditions[] = "(n.tipo_destinatario = 'PERSONAL' OR n.tipo_destinatario = 'TODOS')";
        $where_conditions[] = "(n.id_destinatario IS NULL OR n.id_destinatario = ?)";
        $params[] = $_SESSION['user_id'];
    }
    
    if ($only_unread) {
        $where_conditions[] = "NOT EXISTS (
            SELECT 1 FROM notificaciones_leidas nl 
            WHERE nl.id_notificacion = n.id_notificacion AND nl.id_usuario = ?
        )";
        $params[] = $user_id;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Obtener notificaciones
    $sql = "
        SELECT n.*, 
               CASE WHEN nl.id_lectura IS NOT NULL THEN 1 ELSE 0 END as leida,
               p.nombres as remitente_nombres, p.apellido_paterno as remitente_apellido
        FROM notificaciones n
        LEFT JOIN notificaciones_leidas nl ON n.id_notificacion = nl.id_notificacion AND nl.id_usuario = ?
        LEFT JOIN personal p ON n.id_remitente = p.id_personal
        WHERE {$where_clause}
        ORDER BY n.fecha_creacion DESC, n.prioridad DESC
        LIMIT ? OFFSET ?
    ";
    
    $params = array_merge([$user_id], $params, [$limit, $offset]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    // Formatear notificaciones
    $formatted_notifications = [];
    foreach ($notifications as $notification) {
        $formatted_notifications[] = [
            'id' => $notification['id_notificacion'],
            'title' => $notification['titulo'],
            'message' => $notification['mensaje'],
            'type' => $notification['tipo'],
            'priority' => $notification['prioridad'],
            'date' => $notification['fecha_creacion'],
            'date_formatted' => formatDateTime($notification['fecha_creacion']),
            'read' => (bool)$notification['leida'],
            'sender' => $notification['remitente_nombres'] ? 
                       $notification['remitente_nombres'] . ' ' . $notification['remitente_apellido'] : 
                       'Sistema',
            'url' => $notification['url_accion'],
            'icon' => getNotificationIcon($notification['tipo'])
        ];
    }
    
    // Contar notificaciones no leídas
    $count_sql = "
        SELECT COUNT(*) 
        FROM notificaciones n
        WHERE {$where_clause}
        AND NOT EXISTS (
            SELECT 1 FROM notificaciones_leidas nl 
            WHERE nl.id_notificacion = n.id_notificacion AND nl.id_usuario = ?
        )
    ";
    
    $count_params = array_merge($params, [$user_id]);
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($count_params);
    $unread_count = $stmt->fetchColumn();
    
    $response['success'] = true;
    $response['notifications'] = $formatted_notifications;
    $response['unread_count'] = $unread_count;
    
} catch (PDOException $e) {
    $response['error'] = 'Error al obtener notificaciones';
    error_log("Error en get_notifications: " . $e->getMessage());
}

echo json_encode($response);

function formatDateTime($datetime) {
    $date = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->d == 0) {
        if ($diff->h == 0) {
            if ($diff->i == 0) {
                return 'Ahora';
            }
            return $diff->i . ' min';
        }
        return $diff->h . ' h';
    } elseif ($diff->d < 7) {
        return $diff->d . ' día' . ($diff->d > 1 ? 's' : '');
    } else {
        return $date->format('d/m/Y');
    }
}

function getNotificationIcon($type) {
    $icons = [
        'TAREA' => 'fas fa-tasks',
        'CALIFICACION' => 'fas fa-star',
        'ASISTENCIA' => 'fas fa-calendar-check',
        'MENSAJE' => 'fas fa-envelope',
        'SISTEMA' => 'fas fa-cog',
        'EMERGENCIA' => 'fas fa-exclamation-triangle',
        'EVENTO' => 'fas fa-calendar-alt',
        'RECORDATORIO' => 'fas fa-bell'
    ];
    
    return $icons[$type] ?? 'fas fa-info-circle';
}
?>