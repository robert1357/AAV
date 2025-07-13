<?php
header('Content-Type: application/json');
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos (solo docentes y personal autorizado)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['cargo'], ['DOCENTE', 'DOCENTE_DAIP', 'AUXILIAR_EDUCACION', 'ADMIN'])) {
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

$course_id = $input['course_id'] ?? null;
$section_id = $input['section_id'] ?? null;
$date = $input['date'] ?? date('Y-m-d');
$attendance_data = $input['attendance'] ?? [];

if (!$course_id || !$section_id || empty($attendance_data)) {
    $response['message'] = 'Datos incompletos';
    echo json_encode($response);
    exit;
}

try {
    // Verificar que el docente tiene asignado este curso
    if ($_SESSION['cargo'] === 'DOCENTE' || $_SESSION['cargo'] === 'DOCENTE_DAIP') {
        $stmt = $pdo->prepare("
            SELECT a.id_asignacion
            FROM asignaciones a
            WHERE a.id_personal = ? AND a.id_curso = ? AND a.id_seccion = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $course_id, $section_id]);
        
        if (!$stmt->fetch()) {
            $response['message'] = 'No tiene permisos para este curso';
            echo json_encode($response);
            exit;
        }
    }
    
    // Verificar que la fecha no sea futura
    if (strtotime($date) > time()) {
        $response['message'] = 'No se puede marcar asistencia para fechas futuras';
        echo json_encode($response);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Eliminar registros existentes para esta fecha, curso y sección
    $stmt = $pdo->prepare("
        DELETE FROM asistencias 
        WHERE id_curso = ? AND id_seccion = ? AND fecha = ?
    ");
    $stmt->execute([$course_id, $section_id, $date]);
    
    // Insertar nuevos registros de asistencia
    $stmt = $pdo->prepare("
        INSERT INTO asistencias (id_estudiante, id_curso, id_seccion, fecha, estado, observaciones, registrado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $attendance_count = [
        'presente' => 0,
        'ausente' => 0,
        'tardanza' => 0,
        'justificado' => 0
    ];
    
    foreach ($attendance_data as $student_attendance) {
        $student_id = $student_attendance['student_id'];
        $status = $student_attendance['status']; // presente, ausente, tardanza, justificado
        $observations = $student_attendance['observations'] ?? '';
        
        // Validar que el estudiante pertenece a la sección
        $stmt_check = $pdo->prepare("
            SELECT m.id_matricula
            FROM matriculas m
            WHERE m.id_estudiante = ? AND m.id_seccion = ? AND m.estado = 'ACTIVO'
        ");
        $stmt_check->execute([$student_id, $section_id]);
        
        if ($stmt_check->fetch()) {
            $stmt->execute([
                $student_id,
                $course_id,
                $section_id,
                $date,
                strtoupper($status),
                $observations,
                $_SESSION['user_id']
            ]);
            
            $attendance_count[$status]++;
        }
    }
    
    // Registrar resumen de asistencia
    $stmt = $pdo->prepare("
        INSERT INTO resumen_asistencias (id_curso, id_seccion, fecha, total_estudiantes, 
                                       presentes, ausentes, tardanzas, justificados, registrado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        total_estudiantes = VALUES(total_estudiantes),
        presentes = VALUES(presentes),
        ausentes = VALUES(ausentes),
        tardanzas = VALUES(tardanzas),
        justificados = VALUES(justificados),
        registrado_por = VALUES(registrado_por),
        fecha_actualizacion = NOW()
    ");
    
    $total_students = array_sum($attendance_count);
    
    $stmt->execute([
        $course_id,
        $section_id,
        $date,
        $total_students,
        $attendance_count['presente'],
        $attendance_count['ausente'],
        $attendance_count['tardanza'],
        $attendance_count['justificado'],
        $_SESSION['user_id']
    ]);
    
    $pdo->commit();
    
    $response['success'] = true;
    $response['message'] = 'Asistencia registrada exitosamente';
    $response['summary'] = [
        'total_students' => $total_students,
        'present' => $attendance_count['presente'],
        'absent' => $attendance_count['ausente'],
        'late' => $attendance_count['tardanza'],
        'justified' => $attendance_count['justificado'],
        'attendance_percentage' => $total_students > 0 ? 
            round(($attendance_count['presente'] + $attendance_count['tardanza']) / $total_students * 100, 2) : 0
    ];
    
    // Log de actividad
    logActivity($_SESSION['user_id'], 'MARK_ATTENDANCE', 
                "Asistencia registrada - Curso: {$course_id}, Sección: {$section_id}, Fecha: {$date}");
    
    // Enviar notificaciones a estudiantes ausentes (opcional)
    if ($attendance_count['ausente'] > 0) {
        // notifyAbsentStudents($course_id, $section_id, $date, $attendance_data);
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    $response['message'] = 'Error al registrar asistencia';
    error_log("Error en mark_attendance: " . $e->getMessage());
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