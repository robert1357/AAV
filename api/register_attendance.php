<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['tipo_usuario'], ['docente', 'auxiliar_educacion', 'director'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Decodificar JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos JSON inválidos');
    }

    // Validar datos requeridos
    $required_fields = ['id_asignacion', 'fecha_registro', 'asistencias'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("El campo $field es obligatorio");
        }
    }

    $id_asignacion = intval($input['id_asignacion']);
    $fecha_registro = $input['fecha_registro'];
    $asistencias = $input['asistencias'];
    $observaciones_generales = $input['observaciones_generales'] ?? '';

    // Validar formato de fecha
    if (!validateDate($fecha_registro)) {
        throw new Exception('Formato de fecha inválido');
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

    // Validar que las asistencias sean un array
    if (!is_array($asistencias) || empty($asistencias)) {
        throw new Exception('Debe proporcionar al menos una asistencia');
    }

    // Verificar si ya existe registro de asistencia para esta fecha y asignación
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as existe
        FROM asistencias a
        JOIN matriculas m ON a.id_matricula = m.id_matricula
        WHERE m.id_seccion = ? AND m.id_anio = ? 
        AND DATE(a.fecha_registro) = ? AND a.id_asignacion = ?
    ");
    $stmt->execute([$asignacion['id_seccion'], $asignacion['id_anio'], $fecha_registro, $id_asignacion]);
    $ya_existe = $stmt->fetch()['existe'];

    if ($ya_existe > 0) {
        throw new Exception('Ya existe un registro de asistencia para esta fecha y curso');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    $registros_exitosos = 0;
    $registros_fallidos = [];

    try {
        foreach ($asistencias as $asistencia) {
            // Validar datos de cada asistencia
            if (!isset($asistencia['id_matricula']) || !isset($asistencia['estado'])) {
                $registros_fallidos[] = [
                    'matricula' => $asistencia['id_matricula'] ?? 'unknown',
                    'error' => 'Datos incompletos'
                ];
                continue;
            }

            $id_matricula = intval($asistencia['id_matricula']);
            $estado = strtoupper(trim($asistencia['estado']));
            $observaciones = trim($asistencia['observaciones'] ?? '');

            // Validar estado
            $estados_validos = ['PRESENTE', 'AUSENTE', 'TARDANZA', 'JUSTIFICADO'];
            if (!in_array($estado, $estados_validos)) {
                $registros_fallidos[] = [
                    'matricula' => $id_matricula,
                    'error' => 'Estado de asistencia inválido'
                ];
                continue;
            }

            // Verificar que la matrícula pertenece a la sección
            $stmt = $pdo->prepare("
                SELECT m.*, e.nombres, e.apellido_paterno, e.codigo_estudiante
                FROM matriculas m
                JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
                WHERE m.id_matricula = ? AND m.id_seccion = ? AND m.id_anio = ? AND m.estado = 'ACTIVO'
            ");
            $stmt->execute([$id_matricula, $asignacion['id_seccion'], $asignacion['id_anio']]);
            $matricula = $stmt->fetch();

            if (!$matricula) {
                $registros_fallidos[] = [
                    'matricula' => $id_matricula,
                    'error' => 'Matrícula no encontrada o no pertenece a esta sección'
                ];
                continue;
            }

            // Registrar asistencia
            $stmt = $pdo->prepare("
                INSERT INTO asistencias (
                    id_matricula,
                    id_asignacion,
                    fecha_registro,
                    estado,
                    observaciones,
                    registrado_por,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $id_matricula,
                $id_asignacion,
                $fecha_registro,
                $estado,
                $observaciones,
                $_SESSION['user_id']
            ]);

            $registros_exitosos++;
        }

        // Si hay observaciones generales, registrarlas en una tabla de observaciones generales
        if (!empty($observaciones_generales)) {
            $stmt = $pdo->prepare("
                INSERT INTO observaciones_asistencia (
                    id_asignacion,
                    fecha_registro,
                    observaciones,
                    registrado_por,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $id_asignacion,
                $fecha_registro,
                $observaciones_generales,
                $_SESSION['user_id']
            ]);
        }

        // Log de actividad
        logActivity(
            $pdo, 
            $_SESSION['user_id'], 
            $_SESSION['tipo_usuario'], 
            'ASISTENCIA_REGISTRADA',
            "Asistencia registrada para {$asignacion['curso_nombre']} - {$asignacion['numero_grado']}°{$asignacion['letra_seccion']} - $fecha_registro ($registros_exitosos registros)"
        );

        $pdo->commit();

        // Preparar respuesta
        $response = [
            'success' => true,
            'message' => "Asistencia registrada exitosamente",
            'data' => [
                'registros_exitosos' => $registros_exitosos,
                'registros_fallidos' => count($registros_fallidos),
                'detalles_fallidos' => $registros_fallidos,
                'curso_info' => [
                    'nombre' => $asignacion['curso_nombre'],
                    'grado_seccion' => $asignacion['numero_grado'] . '°' . $asignacion['letra_seccion']
                ],
                'fecha_registro' => $fecha_registro,
                'registrado_por' => $_SESSION['user_id']
            ]
        ];

        // Si hay registros fallidos, ajustar el mensaje
        if (!empty($registros_fallidos)) {
            $response['message'] = "Asistencia registrada con algunas advertencias";
            $response['warnings'] = $registros_fallidos;
        }

        echo json_encode($response);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en register_attendance.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>