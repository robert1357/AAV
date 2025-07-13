<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['grado']) && !isset($_GET['grado_id'])) {
    echo json_encode([]);
    exit();
}

try {
    if (isset($_GET['grado_id'])) {
        // Buscar por ID de grado
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                COUNT(m.id_matricula) as total_estudiantes
            FROM secciones s
            LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion 
                AND m.estado = 'ACTIVO'
            WHERE s.id_grado = ?
            GROUP BY s.id_seccion
            ORDER BY s.letra_seccion
        ");
        $stmt->execute([$_GET['grado_id']]);
    } else {
        // Buscar por número de grado
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                COUNT(m.id_matricula) as total_estudiantes
            FROM secciones s
            JOIN grados g ON s.id_grado = g.id_grado
            LEFT JOIN matriculas m ON s.id_seccion = m.id_seccion 
                AND m.estado = 'ACTIVO'
            WHERE g.numero_grado = ?
            GROUP BY s.id_seccion
            ORDER BY s.letra_seccion
        ");
        $stmt->execute([$_GET['grado']]);
    }
    
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($secciones);
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>