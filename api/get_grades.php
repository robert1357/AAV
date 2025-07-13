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

$response = ['success' => false, 'grades' => [], 'summary' => []];

try {
    $user_type = $_SESSION['user_type'];
    $student_id = null;
    
    // Determinar ID del estudiante
    if ($user_type === 'student') {
        $student_id = $_SESSION['student_id'];
    } elseif (isset($_GET['student_id'])) {
        // Docente o admin consultando calificaciones de un estudiante específico
        $student_id = $_GET['student_id'];
    }
    
    if (!$student_id) {
        $response['error'] = 'ID de estudiante requerido';
        echo json_encode($response);
        exit;
    }
    
    $course_id = $_GET['course_id'] ?? null;
    $bimester = $_GET['bimester'] ?? null;
    $year = $_GET['year'] ?? date('Y');
    
    // Construir query base
    $where_conditions = ['c.id_estudiante = ?'];
    $params = [$student_id];
    
    if ($course_id) {
        $where_conditions[] = 'c.id_curso = ?';
        $params[] = $course_id;
    }
    
    if ($bimester) {
        $where_conditions[] = 'c.bimestre = ?';
        $params[] = $bimester;
    }
    
    $where_conditions[] = 'YEAR(c.fecha_calificacion) = ?';
    $params[] = $year;
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Obtener calificaciones
    $sql = "
        SELECT c.*, cur.nombre as nombre_curso, cur.codigo as codigo_curso,
               t.titulo as nombre_tarea, t.tipo as tipo_evaluacion,
               p.nombres as docente_nombres, p.apellido_paterno as docente_apellido,
               CASE 
                   WHEN c.calificacion >= 17 THEN 'Excelente'
                   WHEN c.calificacion >= 14 THEN 'Bueno'
                   WHEN c.calificacion >= 11 THEN 'Regular'
                   ELSE 'Deficiente'
               END as nivel_logro
        FROM calificaciones c
        JOIN cursos cur ON c.id_curso = cur.id_curso
        LEFT JOIN tareas t ON c.id_tarea = t.id_tarea
        LEFT JOIN asignaciones a ON cur.id_curso = a.id_curso
        LEFT JOIN personal p ON a.id_personal = p.id_personal
        WHERE {$where_clause}
        ORDER BY c.fecha_calificacion DESC, cur.nombre, c.bimestre
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $grades = $stmt->fetchAll();
    
    // Formatear calificaciones
    $formatted_grades = [];
    $course_averages = [];
    $bimester_totals = [];
    
    foreach ($grades as $grade) {
        $course_key = $grade['id_curso'];
        $bimester_key = $grade['bimestre'];
        
        $formatted_grade = [
            'id' => $grade['id_calificacion'],
            'course' => [
                'id' => $grade['id_curso'],
                'name' => $grade['nombre_curso'],
                'code' => $grade['codigo_curso']
            ],
            'task' => [
                'id' => $grade['id_tarea'],
                'title' => $grade['nombre_tarea'],
                'type' => $grade['tipo_evaluacion']
            ],
            'grade' => $grade['calificacion'],
            'bimester' => $grade['bimestre'],
            'date' => $grade['fecha_calificacion'],
            'date_formatted' => date('d/m/Y', strtotime($grade['fecha_calificacion'])),
            'teacher' => $grade['docente_nombres'] . ' ' . $grade['docente_apellido'],
            'achievement_level' => $grade['nivel_logro'],
            'observations' => $grade['observaciones']
        ];
        
        $formatted_grades[] = $formatted_grade;
        
        // Calcular promedios por curso
        if (!isset($course_averages[$course_key])) {
            $course_averages[$course_key] = [
                'course_name' => $grade['nombre_curso'],
                'grades' => [],
                'bimesters' => []
            ];
        }
        
        $course_averages[$course_key]['grades'][] = $grade['calificacion'];
        
        if (!isset($course_averages[$course_key]['bimesters'][$bimester_key])) {
            $course_averages[$course_key]['bimesters'][$bimester_key] = [];
        }
        $course_averages[$course_key]['bimesters'][$bimester_key][] = $grade['calificacion'];
        
        // Totales por bimestre
        if (!isset($bimester_totals[$bimester_key])) {
            $bimester_totals[$bimester_key] = [];
        }
        $bimester_totals[$bimester_key][] = $grade['calificacion'];
    }
    
    // Calcular resumen
    $summary = [
        'general_average' => 0,
        'total_courses' => count($course_averages),
        'total_grades' => count($formatted_grades),
        'course_averages' => [],
        'bimester_averages' => [],
        'best_course' => null,
        'lowest_course' => null
    ];
    
    $all_grades = [];
    $best_avg = 0;
    $lowest_avg = 20;
    
    foreach ($course_averages as $course_id => $course_data) {
        $course_avg = array_sum($course_data['grades']) / count($course_data['grades']);
        $all_grades = array_merge($all_grades, $course_data['grades']);
        
        $summary['course_averages'][] = [
            'course_id' => $course_id,
            'course_name' => $course_data['course_name'],
            'average' => round($course_avg, 2),
            'total_grades' => count($course_data['grades'])
        ];
        
        if ($course_avg > $best_avg) {
            $best_avg = $course_avg;
            $summary['best_course'] = [
                'name' => $course_data['course_name'],
                'average' => round($course_avg, 2)
            ];
        }
        
        if ($course_avg < $lowest_avg) {
            $lowest_avg = $course_avg;
            $summary['lowest_course'] = [
                'name' => $course_data['course_name'],
                'average' => round($course_avg, 2)
            ];
        }
    }
    
    foreach ($bimester_totals as $bim => $grades_bim) {
        $summary['bimester_averages'][] = [
            'bimester' => $bim,
            'average' => round(array_sum($grades_bim) / count($grades_bim), 2),
            'total_grades' => count($grades_bim)
        ];
    }
    
    if (!empty($all_grades)) {
        $summary['general_average'] = round(array_sum($all_grades) / count($all_grades), 2);
    }
    
    $response['success'] = true;
    $response['grades'] = $formatted_grades;
    $response['summary'] = $summary;
    
} catch (PDOException $e) {
    $response['error'] = 'Error al obtener calificaciones';
    error_log("Error en get_grades: " . $e->getMessage());
}

echo json_encode($response);
?>