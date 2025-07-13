<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
header('Content-Type: application/json');

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Datos insuficientes']);
    exit();
}

try {
    $id_tarea = $_GET['id'];
    
    // Obtener detalles de la tarea
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            c.nombre as curso_nombre,
            c.codigo as curso_codigo,
            CONCAT(p.nombres, ' ', p.apellido_paterno) as docente_nombre
        FROM tareas t
        JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
        JOIN cursos c ON a.id_curso = c.id_curso
        JOIN personal p ON a.id_personal = p.id_personal
        WHERE t.id_tarea = ?
    ");
    $stmt->execute([$id_tarea]);
    $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tarea) {
        echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
        exit();
    }
    
    // Para estudiantes, obtener su entrega si existe
    $entrega = null;
    if ($_SESSION['tipo_usuario'] === 'estudiante') {
        $stmt = $pdo->prepare("
            SELECT m.id_matricula FROM matriculas m
            JOIN anios_academicos a ON m.id_anio = a.id_anio
            WHERE m.id_estudiante = ? AND a.estado = 'ACTIVO' AND m.estado = 'ACTIVO'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $matricula = $stmt->fetch();
        
        if ($matricula) {
            $stmt = $pdo->prepare("
                SELECT * FROM entregas_tareas 
                WHERE id_tarea = ? AND id_matricula = ?
            ");
            $stmt->execute([$id_tarea, $matricula['id_matricula']]);
            $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    // Generar HTML para mostrar
    $dias_restantes = floor((strtotime($tarea['fecha_limite']) - time()) / (60 * 60 * 24));
    $estado_fecha = $dias_restantes < 0 ? 'vencida' : ($dias_restantes <= 2 ? 'proximavencer' : 'normal');
    
    $html = '
    <div class="row">
        <div class="col-md-8">
            <h5>' . htmlspecialchars($tarea['titulo']) . '</h5>
            <p class="text-muted mb-2">
                <i class="fas fa-book"></i> [' . $tarea['curso_codigo'] . '] ' . htmlspecialchars($tarea['curso_nombre']) . '<br>
                <i class="fas fa-user"></i> ' . htmlspecialchars($tarea['docente_nombre']) . '
            </p>
            <div class="alert alert-light">
                <p class="mb-0">' . nl2br(htmlspecialchars($tarea['descripcion'])) . '</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title">Información</h6>
                    <p class="mb-2">
                        <strong>Fecha límite:</strong><br>
                        <span class="text-' . ($estado_fecha === 'vencida' ? 'danger' : ($estado_fecha === 'proximavencer' ? 'warning' : 'success')) . '">
                            ' . date('d/m/Y H:i', strtotime($tarea['fecha_limite'])) . '
                        </span>';
    
    if ($dias_restantes >= 0) {
        $html .= '<br><small class="text-muted">' . ($dias_restantes == 0 ? 'Vence hoy' : 'Faltan ' . $dias_restantes . ' días') . '</small>';
    } else {
        $html .= '<br><small class="text-danger">Vencida hace ' . abs($dias_restantes) . ' días</small>';
    }
    
    $html .= '
                    </p>
                    <p class="mb-2">
                        <strong>Puntos máximos:</strong><br>
                        <span class="badge bg-primary fs-6">' . $tarea['puntos_maximos'] . ' puntos</span>
                    </p>
                    <p class="mb-0">
                        <strong>Tipo:</strong> ' . ucfirst($tarea['tipo']) . '
                    </p>
                </div>
            </div>
        </div>
    </div>';
    
    if ($tarea['instrucciones']) {
        $html .= '
        <div class="mt-3">
            <h6>Instrucciones:</h6>
            <div class="alert alert-info">
                ' . nl2br(htmlspecialchars($tarea['instrucciones'])) . '
            </div>
        </div>';
    }
    
    if ($entrega) {
        $html .= '
        <div class="mt-3">
            <h6>Tu Entrega Actual:</h6>
            <div class="alert alert-success">
                <p><strong>Entregado el:</strong> ' . date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])) . '</p>';
        
        if ($entrega['calificacion']) {
            $html .= '<p><strong>Calificación:</strong> <span class="badge bg-' . ($entrega['calificacion'] >= 14 ? 'success' : 'danger') . ' fs-6">' . $entrega['calificacion'] . '/20</span></p>';
        }
        
        if ($entrega['observaciones_docente']) {
            $html .= '<p><strong>Observaciones del docente:</strong><br>' . nl2br(htmlspecialchars($entrega['observaciones_docente'])) . '</p>';
        }
        
        if ($entrega['archivo_adjunto']) {
            $html .= '<p><strong>Archivo:</strong> <a href="../uploads/assignments/' . $entrega['archivo_adjunto'] . '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> Descargar</a></p>';
        }
        
        $html .= '<p><strong>Contenido:</strong><br>' . nl2br(htmlspecialchars($entrega['contenido_respuesta'])) . '</p>';
        $html .= '</div>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'tarea' => $tarea,
        'entrega' => $entrega
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>