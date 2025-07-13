<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['tipo_usuario'], ['docente', 'director', 'secretaria'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }

    if (empty($_GET['id_seccion'])) {
        throw new Exception('ID de sección es obligatorio');
    }

    $id_seccion = intval($_GET['id_seccion']);
    $id_anio = intval($_GET['id_anio'] ?? 0);

    // Si no se proporciona año, usar el año académico activo
    if (!$id_anio) {
        $stmt = $pdo->query("SELECT id_anio FROM anios_academicos WHERE estado = 'ACTIVO' LIMIT 1");
        $anio_activo = $stmt->fetch();
        if (!$anio_activo) {
            throw new Exception('No hay año académico activo');
        }
        $id_anio = $anio_activo['id_anio'];
    }

    // Verificar permisos del docente (excepto director y secretaria)
    if ($_SESSION['tipo_usuario'] === 'docente') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as tiene_acceso
            FROM asignaciones a
            WHERE a.id_personal = ? AND a.id_seccion = ? AND a.id_anio = ? AND a.estado = 'ACTIVO'
        ");
        $stmt->execute([$_SESSION['user_id'], $id_seccion, $id_anio]);
        $acceso = $stmt->fetch();
        
        if ($acceso['tiene_acceso'] == 0) {
            throw new Exception('No tienes permisos para acceder a esta sección');
        }
    }

    // Obtener información de la sección
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            g.numero_grado,
            g.descripcion as grado_descripcion,
            a.anio
        FROM secciones s
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN anios_academicos a ON s.id_anio = a.id_anio
        WHERE s.id_seccion = ? AND s.id_anio = ?
    ");
    $stmt->execute([$id_seccion, $id_anio]);
    $seccion_info = $stmt->fetch();

    if (!$seccion_info) {
        throw new Exception('Sección no encontrada');
    }

    // Obtener estudiantes de la sección
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            m.id_matricula,
            m.fecha_matricula,
            m.estado as estado_matricula,
            CASE 
                WHEN e.fecha_nacimiento IS NOT NULL 
                THEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE())
                ELSE NULL 
            END as edad,
            -- Calcular promedio de notas
            (
                SELECT AVG(n.nota) 
                FROM notas n 
                WHERE n.id_matricula = m.id_matricula
            ) as promedio_general,
            -- Contar total de asistencias
            (
                SELECT COUNT(*) 
                FROM asistencias a 
                WHERE a.id_matricula = m.id_matricula
            ) as total_asistencias,
            -- Contar asistencias presentes
            (
                SELECT COUNT(*) 
                FROM asistencias a 
                WHERE a.id_matricula = m.id_matricula AND a.estado = 'PRESENTE'
            ) as asistencias_presentes
        FROM estudiantes e
        JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        WHERE m.id_seccion = ? AND m.id_anio = ? AND m.estado = 'ACTIVO'
        ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres
    ");
    $stmt->execute([$id_seccion, $id_anio]);
    $estudiantes = $stmt->fetchAll();

    // Procesar datos de estudiantes
    $estudiantes_procesados = [];
    foreach ($estudiantes as $estudiante) {
        $porcentaje_asistencia = 0;
        if ($estudiante['total_asistencias'] > 0) {
            $porcentaje_asistencia = ($estudiante['asistencias_presentes'] / $estudiante['total_asistencias']) * 100;
        }

        $estado_academico = getAcademicStatus($estudiante['promedio_general'] ?? 0);

        $estudiantes_procesados[] = [
            'id_estudiante' => $estudiante['id_estudiante'],
            'id_matricula' => $estudiante['id_matricula'],
            'codigo_estudiante' => $estudiante['codigo_estudiante'],
            'nombres' => $estudiante['nombres'],
            'apellido_paterno' => $estudiante['apellido_paterno'],
            'apellido_materno' => $estudiante['apellido_materno'],
            'nombre_completo' => trim($estudiante['nombres'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']),
            'dni' => $estudiante['dni'],
            'email' => $estudiante['email'],
            'telefono' => $estudiante['telefono'],
            'fecha_nacimiento' => $estudiante['fecha_nacimiento'],
            'edad' => $estudiante['edad'],
            'genero' => $estudiante['genero'],
            'direccion' => $estudiante['direccion'],
            'fecha_matricula' => $estudiante['fecha_matricula'],
            'estado_matricula' => $estudiante['estado_matricula'],
            'promedio_general' => $estudiante['promedio_general'] ? round($estudiante['promedio_general'], 2) : null,
            'estado_academico' => $estado_academico,
            'total_asistencias' => intval($estudiante['total_asistencias']),
            'asistencias_presentes' => intval($estudiante['asistencias_presentes']),
            'porcentaje_asistencia' => round($porcentaje_asistencia, 1),
            'foto_url' => $estudiante['foto_url']
        ];
    }

    // Obtener estadísticas de la sección
    $total_estudiantes = count($estudiantes_procesados);
    $promedio_seccion = 0;
    $estudiantes_aprobados = 0;
    $asistencia_promedio = 0;

    if ($total_estudiantes > 0) {
        $suma_promedios = 0;
        $suma_asistencias = 0;
        $estudiantes_con_notas = 0;

        foreach ($estudiantes_procesados as $estudiante) {
            if ($estudiante['promedio_general'] !== null) {
                $suma_promedios += $estudiante['promedio_general'];
                $estudiantes_con_notas++;
                
                if ($estudiante['promedio_general'] >= 14) {
                    $estudiantes_aprobados++;
                }
            }
            
            $suma_asistencias += $estudiante['porcentaje_asistencia'];
        }

        if ($estudiantes_con_notas > 0) {
            $promedio_seccion = $suma_promedios / $estudiantes_con_notas;
        }
        
        $asistencia_promedio = $suma_asistencias / $total_estudiantes;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'seccion_info' => [
                'id_seccion' => $seccion_info['id_seccion'],
                'letra_seccion' => $seccion_info['letra_seccion'],
                'numero_grado' => $seccion_info['numero_grado'],
                'grado_descripcion' => $seccion_info['grado_descripcion'],
                'anio' => $seccion_info['anio'],
                'capacidad_maxima' => $seccion_info['capacidad_maxima']
            ],
            'estudiantes' => $estudiantes_procesados,
            'estadisticas' => [
                'total_estudiantes' => $total_estudiantes,
                'promedio_seccion' => round($promedio_seccion, 2),
                'estudiantes_aprobados' => $estudiantes_aprobados,
                'porcentaje_aprobacion' => $total_estudiantes > 0 ? round(($estudiantes_aprobados / $total_estudiantes) * 100, 1) : 0,
                'asistencia_promedio' => round($asistencia_promedio, 1),
                'capacidad_disponible' => max(0, $seccion_info['capacidad_maxima'] - $total_estudiantes)
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en get_students_by_section.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>