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
    $required_fields = ['id_entrega', 'calificacion'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception("El campo $field es obligatorio");
        }
    }

    $id_entrega = intval($_POST['id_entrega']);
    $calificacion = floatval($_POST['calificacion']);
    $observaciones_docente = trim($_POST['observaciones_docente'] ?? '');

    // Validar calificación (escala 0-20)
    if ($calificacion < 0 || $calificacion > 20) {
        throw new Exception('La calificación debe estar entre 0 y 20');
    }

    // Obtener información de la entrega y verificar permisos
    $stmt = $pdo->prepare("
        SELECT 
            et.*,
            t.titulo as tarea_titulo,
            t.id_asignacion,
            a.id_personal,
            c.nombre as curso_nombre,
            c.codigo as curso_codigo,
            CONCAT(e.nombres, ' ', e.apellido_paterno, ' ', e.apellido_materno) as estudiante_nombre,
            e.codigo_estudiante,
            m.id_curso,
            m.id_matricula,
            s.letra_seccion,
            g.numero_grado
        FROM entregas_tareas et
        JOIN tareas t ON et.id_tarea = t.id_tarea
        JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
        JOIN cursos c ON a.id_curso = c.id_curso
        JOIN matriculas m ON et.id_matricula = m.id_matricula
        JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        WHERE et.id_entrega = ?
    ");
    $stmt->execute([$id_entrega]);
    $entrega = $stmt->fetch();

    if (!$entrega) {
        throw new Exception('Entrega no encontrada');
    }

    // Verificar permisos del docente (excepto director)
    if ($_SESSION['tipo_usuario'] === 'docente' && $entrega['id_personal'] != $_SESSION['user_id']) {
        throw new Exception('No tienes permisos para calificar esta entrega');
    }

    // Verificar que la entrega esté en estado válido para calificar
    if (!in_array($entrega['estado'], ['ENTREGADO', 'TARDE'])) {
        throw new Exception('Esta entrega no puede ser calificada en su estado actual');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Actualizar la entrega con la calificación
        $stmt = $pdo->prepare("
            UPDATE entregas_tareas 
            SET 
                calificacion = ?,
                observaciones_docente = ?,
                estado = 'CALIFICADO',
                fecha_calificacion = NOW(),
                updated_at = NOW()
            WHERE id_entrega = ?
        ");
        
        $stmt->execute([$calificacion, $observaciones_docente, $id_entrega]);

        // Registrar o actualizar la nota en la tabla de notas
        $fecha_evaluacion = date('Y-m-d');
        $tipo_evaluacion = 'TAREA';

        // Verificar si ya existe una nota para esta tarea y estudiante
        $stmt = $pdo->prepare("
            SELECT id_nota 
            FROM notas 
            WHERE id_matricula = ? AND id_curso = ? AND tipo_evaluacion = ? 
            AND observaciones LIKE ?
        ");
        $observacion_busqueda = "%{$entrega['tarea_titulo']}%";
        $stmt->execute([$entrega['id_matricula'], $entrega['id_curso'], $tipo_evaluacion, $observacion_busqueda]);
        $nota_existente = $stmt->fetch();

        if ($nota_existente) {
            // Actualizar nota existente
            $stmt = $pdo->prepare("
                UPDATE notas 
                SET 
                    nota = ?,
                    fecha_evaluacion = ?,
                    observaciones = ?,
                    updated_at = NOW()
                WHERE id_nota = ?
            ");
            
            $observaciones_nota = "Tarea: {$entrega['tarea_titulo']}";
            if (!empty($observaciones_docente)) {
                $observaciones_nota .= " - " . $observaciones_docente;
            }
            
            $stmt->execute([$calificacion, $fecha_evaluacion, $observaciones_nota, $nota_existente['id_nota']]);
        } else {
            // Crear nueva nota
            $stmt = $pdo->prepare("
                INSERT INTO notas (
                    id_matricula,
                    id_curso,
                    nota,
                    tipo_evaluacion,
                    fecha_evaluacion,
                    observaciones,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $observaciones_nota = "Tarea: {$entrega['tarea_titulo']}";
            if (!empty($observaciones_docente)) {
                $observaciones_nota .= " - " . $observaciones_docente;
            }
            
            $stmt->execute([
                $entrega['id_matricula'],
                $entrega['id_curso'],
                $calificacion,
                $tipo_evaluacion,
                $fecha_evaluacion,
                $observaciones_nota
            ]);
        }

        // Log de actividad
        logActivity(
            $pdo, 
            $_SESSION['user_id'], 
            $_SESSION['tipo_usuario'], 
            'ENTREGA_CALIFICADA',
            "Entrega calificada: {$entrega['tarea_titulo']} - {$entrega['estudiante_nombre']} - Nota: $calificacion"
        );

        $pdo->commit();

        // Preparar respuesta
        $estado_academico = getAcademicStatus($calificacion);
        
        echo json_encode([
            'success' => true,
            'message' => 'Entrega calificada exitosamente',
            'data' => [
                'id_entrega' => $id_entrega,
                'calificacion' => $calificacion,
                'observaciones_docente' => $observaciones_docente,
                'estado_academico' => $estado_academico,
                'estudiante_info' => [
                    'nombre' => $entrega['estudiante_nombre'],
                    'codigo' => $entrega['codigo_estudiante'],
                    'grado_seccion' => $entrega['numero_grado'] . '°' . $entrega['letra_seccion']
                ],
                'tarea_info' => [
                    'titulo' => $entrega['tarea_titulo'],
                    'curso' => $entrega['curso_nombre'],
                    'codigo_curso' => $entrega['curso_codigo']
                ],
                'fecha_calificacion' => date('Y-m-d H:i:s'),
                'estado' => 'CALIFICADO'
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en grade_submission.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>