<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['tipo_usuario'], ['director', 'secretaria'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Decodificar JSON si es necesario
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $_POST = array_merge($_POST, $input);
    }

    // Validar datos requeridos para el estudiante
    $required_student_fields = ['nombres', 'apellido_paterno', 'apellido_materno', 'dni', 'fecha_nacimiento', 'genero'];
    foreach ($required_student_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo $field del estudiante es obligatorio");
        }
    }

    // Validar datos requeridos para la matrícula
    $required_enrollment_fields = ['id_seccion', 'id_anio'];
    foreach ($required_enrollment_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo $field de la matrícula es obligatorio");
        }
    }

    // Datos del estudiante
    $nombres = formatName($_POST['nombres']);
    $apellido_paterno = formatName($_POST['apellido_paterno']);
    $apellido_materno = formatName($_POST['apellido_materno']);
    $dni = trim($_POST['dni']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $genero = strtoupper($_POST['genero']);
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    
    // Datos de matrícula
    $id_seccion = intval($_POST['id_seccion']);
    $id_anio = intval($_POST['id_anio']);

    // Datos del apoderado (opcionales)
    $apoderado_nombres = formatName($_POST['apoderado_nombres'] ?? '');
    $apoderado_apellidos = formatName($_POST['apoderado_apellidos'] ?? '');
    $apoderado_dni = trim($_POST['apoderado_dni'] ?? '');
    $apoderado_telefono = trim($_POST['apoderado_telefono'] ?? '');
    $apoderado_email = trim($_POST['apoderado_email'] ?? '');
    $parentesco = trim($_POST['parentesco'] ?? '');

    // Validaciones
    $validation = validateInput($dni, 'dni', true);
    if (!$validation['valid']) {
        throw new Exception($validation['message']);
    }

    if (!validateDate($fecha_nacimiento)) {
        throw new Exception('Fecha de nacimiento inválida');
    }

    if (!in_array($genero, ['MASCULINO', 'FEMENINO'])) {
        throw new Exception('Género debe ser MASCULINO o FEMENINO');
    }

    if (!empty($email)) {
        $validation = validateInput($email, 'email');
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }
    }

    if (!empty($telefono)) {
        $validation = validateInput($telefono, 'phone');
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }
    }

    // Verificar que no exista estudiante con el mismo DNI
    $stmt = $pdo->prepare("SELECT id_estudiante FROM estudiantes WHERE dni = ?");
    $stmt->execute([$dni]);
    if ($stmt->fetch()) {
        throw new Exception('Ya existe un estudiante registrado con este DNI');
    }

    // Verificar que la sección existe y tiene capacidad
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            g.numero_grado,
            g.descripcion as grado_descripcion,
            a.anio,
            COUNT(m.id_matricula) as matriculados_actuales
        FROM secciones s
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN anios_academicos a ON s.id_anio = a.id_anio
        LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion AND m.estado = 'ACTIVO'
        WHERE s.id_seccion = ? AND s.id_anio = ?
        GROUP BY s.id_seccion
    ");
    $stmt->execute([$id_seccion, $id_anio]);
    $seccion = $stmt->fetch();

    if (!$seccion) {
        throw new Exception('Sección no encontrada');
    }

    if ($seccion['matriculados_actuales'] >= $seccion['capacidad_maxima']) {
        throw new Exception('La sección ha alcanzado su capacidad máxima');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Generar código de estudiante
        $codigo_estudiante = generateStudentCode($seccion['anio'], $pdo);

        // Insertar estudiante
        $stmt = $pdo->prepare("
            INSERT INTO estudiantes (
                codigo_estudiante,
                nombres,
                apellido_paterno,
                apellido_materno,
                dni,
                fecha_nacimiento,
                genero,
                email,
                telefono,
                direccion,
                estado,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', NOW(), NOW())
        ");

        $stmt->execute([
            $codigo_estudiante,
            $nombres,
            $apellido_paterno,
            $apellido_materno,
            $dni,
            $fecha_nacimiento,
            $genero,
            $email,
            $telefono,
            $direccion
        ]);

        $id_estudiante = $pdo->lastInsertId();

        // Usar el procedimiento almacenado para matricular
        if (function_exists('mysqli_connect')) {
            // Si tenemos MySQLi disponible, usar el procedimiento almacenado
            $stmt = $pdo->prepare("CALL sp_matricular_estudiante(?, ?, ?)");
            $stmt->execute([$id_estudiante, $id_seccion, $id_anio]);
        } else {
            // Alternativa manual si no podemos usar el procedimiento
            $stmt = $pdo->prepare("
                INSERT INTO matriculas (
                    id_estudiante,
                    id_seccion,
                    id_anio,
                    fecha_matricula,
                    estado,
                    matriculado_por,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, NOW(), 'ACTIVO', ?, NOW(), NOW())
            ");
            
            $stmt->execute([$id_estudiante, $id_seccion, $id_anio, $_SESSION['user_id']]);
        }

        $id_matricula = $pdo->lastInsertId();

        // Insertar apoderado si se proporcionaron datos
        $id_apoderado = null;
        if (!empty($apoderado_nombres) && !empty($apoderado_apellidos)) {
            $stmt = $pdo->prepare("
                INSERT INTO apoderados (
                    nombres,
                    apellidos,
                    dni,
                    telefono,
                    email,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $apoderado_nombres,
                $apoderado_apellidos,
                $apoderado_dni,
                $apoderado_telefono,
                $apoderado_email
            ]);

            $id_apoderado = $pdo->lastInsertId();

            // Relacionar estudiante con apoderado
            $stmt = $pdo->prepare("
                INSERT INTO estudiante_apoderados (
                    id_estudiante,
                    id_apoderado,
                    parentesco,
                    es_principal,
                    created_at
                ) VALUES (?, ?, ?, 1, NOW())
            ");

            $stmt->execute([$id_estudiante, $id_apoderado, $parentesco]);
        }

        // Log de actividad
        logActivity(
            $pdo, 
            $_SESSION['user_id'], 
            $_SESSION['tipo_usuario'], 
            'ESTUDIANTE_MATRICULADO',
            "Estudiante $codigo_estudiante matriculado en {$seccion['numero_grado']}°{$seccion['letra_seccion']} - {$seccion['anio']}"
        );

        $pdo->commit();

        // Calcular edad
        $edad = calculateAge($fecha_nacimiento);

        echo json_encode([
            'success' => true,
            'message' => 'Estudiante matriculado exitosamente',
            'data' => [
                'estudiante' => [
                    'id_estudiante' => $id_estudiante,
                    'codigo_estudiante' => $codigo_estudiante,
                    'nombres' => $nombres,
                    'apellido_paterno' => $apellido_paterno,
                    'apellido_materno' => $apellido_materno,
                    'nombre_completo' => "$nombres $apellido_paterno $apellido_materno",
                    'dni' => $dni,
                    'fecha_nacimiento' => $fecha_nacimiento,
                    'edad' => $edad,
                    'genero' => $genero,
                    'email' => $email,
                    'telefono' => $telefono,
                    'direccion' => $direccion
                ],
                'matricula' => [
                    'id_matricula' => $id_matricula,
                    'fecha_matricula' => date('Y-m-d H:i:s'),
                    'estado' => 'ACTIVO'
                ],
                'seccion' => [
                    'id_seccion' => $id_seccion,
                    'letra_seccion' => $seccion['letra_seccion'],
                    'numero_grado' => $seccion['numero_grado'],
                    'grado_descripcion' => $seccion['grado_descripcion'],
                    'anio' => $seccion['anio'],
                    'capacidad_actual' => $seccion['matriculados_actuales'] + 1,
                    'capacidad_maxima' => $seccion['capacidad_maxima']
                ],
                'apoderado' => $id_apoderado ? [
                    'id_apoderado' => $id_apoderado,
                    'nombres' => $apoderado_nombres,
                    'apellidos' => $apoderado_apellidos,
                    'dni' => $apoderado_dni,
                    'telefono' => $apoderado_telefono,
                    'email' => $apoderado_email,
                    'parentesco' => $parentesco
                ] : null
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en enroll_student.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>