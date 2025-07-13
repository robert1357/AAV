<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
header('Content-Type: application/json');

if (!isset($_GET['id_tarea']) || !isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DOCENTE') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $id_tarea = $_GET['id_tarea'];
    
    // Verificar que la tarea pertenece al docente
    $stmt = $pdo->prepare("
        SELECT t.*, c.nombre as curso_nombre, c.codigo as curso_codigo
        FROM tareas t
        JOIN asignaciones a ON t.id_asignacion = a.id_asignacion
        JOIN cursos c ON a.id_curso = c.id_curso
        WHERE t.id_tarea = ? AND a.id_personal = ?
    ");
    $stmt->execute([$id_tarea, $_SESSION['user_id']]);
    $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tarea) {
        echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
        exit();
    }
    
    // Obtener todas las entregas de la tarea
    $stmt = $pdo->prepare("
        SELECT 
            et.*,
            CONCAT(e.apellido_paterno, ' ', e.apellido_materno, ', ', e.nombres) as estudiante_nombre,
            e.codigo_estudiante,
            g.numero_grado,
            s.letra_seccion,
            CASE 
                WHEN et.fecha_entrega IS NULL THEN 'NO_ENTREGADO'
                WHEN et.estado = 'CALIFICADO' THEN 'CALIFICADO'
                WHEN et.fecha_entrega > ? THEN 'ENTREGADO_TARDE'
                ELSE 'ENTREGADO'
            END as estado_entrega
        FROM matriculas m
        JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
        JOIN secciones s ON m.id_seccion = s.id_seccion
        JOIN grados g ON s.id_grado = g.id_grado
        JOIN asignaciones a ON s.id_seccion = a.id_seccion
        LEFT JOIN entregas_tareas et ON m.id_matricula = et.id_matricula AND et.id_tarea = ?
        WHERE a.id_asignacion = (
            SELECT id_asignacion FROM tareas WHERE id_tarea = ?
        )
        ORDER BY g.numero_grado, s.letra_seccion, e.apellido_paterno, e.apellido_materno, e.nombres
    ");
    $stmt->execute([$tarea['fecha_limite'], $id_tarea, $id_tarea]);
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generar HTML
    $html = '
    <div class="row mb-3">
        <div class="col-md-8">
            <h5>' . htmlspecialchars($tarea['titulo']) . '</h5>
            <p class="text-muted mb-0">[' . $tarea['curso_codigo'] . '] ' . htmlspecialchars($tarea['curso_nombre']) . '</p>
            <small class="text-muted">Fecha límite: ' . date('d/m/Y H:i', strtotime($tarea['fecha_limite'])) . '</small>
        </div>
        <div class="col-md-4 text-end">
            <div class="d-flex justify-content-end gap-2">
                <span class="badge bg-primary">Máx. ' . $tarea['puntos_maximos'] . ' pts</span>
                <button class="btn btn-sm btn-outline-primary" onclick="exportarCalificaciones(' . $id_tarea . ')">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Estudiante</th>
                    <th>Código</th>
                    <th>Grado/Sección</th>
                    <th>Estado</th>
                    <th>Fecha Entrega</th>
                    <th>Calificación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($entregas as $entrega) {
        $badge_class = '';
        $estado_texto = '';
        
        switch ($entrega['estado_entrega']) {
            case 'NO_ENTREGADO':
                $badge_class = 'bg-secondary';
                $estado_texto = 'No entregado';
                break;
            case 'ENTREGADO':
                $badge_class = 'bg-warning';
                $estado_texto = 'Entregado';
                break;
            case 'ENTREGADO_TARDE':
                $badge_class = 'bg-danger';
                $estado_texto = 'Entregado tarde';
                break;
            case 'CALIFICADO':
                $badge_class = 'bg-success';
                $estado_texto = 'Calificado';
                break;
        }
        
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($entrega['estudiante_nombre']) . '</td>
                    <td>' . htmlspecialchars($entrega['codigo_estudiante']) . '</td>
                    <td>' . $entrega['numero_grado'] . '° ' . $entrega['letra_seccion'] . '</td>
                    <td><span class="badge ' . $badge_class . '">' . $estado_texto . '</span></td>
                    <td>' . ($entrega['fecha_entrega'] ? date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])) : '-') . '</td>
                    <td>';
        
        if ($entrega['calificacion'] !== null) {
            $nota_class = $entrega['calificacion'] >= ($tarea['puntos_maximos'] * 0.7) ? 'text-success' : 'text-danger';
            $html .= '<span class="' . $nota_class . ' fw-bold">' . $entrega['calificacion'] . '/' . $tarea['puntos_maximos'] . '</span>';
        } else {
            $html .= '-';
        }
        
        $html .= '</td>
                    <td>';
        
        if ($entrega['id_entrega']) {
            $html .= '
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="verEntrega(' . $entrega['id_entrega'] . ')">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="calificarEntrega(' . $entrega['id_entrega'] . ', ' . $tarea['puntos_maximos'] . ')">
                            <i class="fas fa-edit"></i>
                        </button>';
        } else {
            $html .= '<span class="text-muted">Sin entrega</span>';
        }
        
        $html .= '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
    </div>';
    
    // Estadísticas
    $total_estudiantes = count($entregas);
    $entregadas = count(array_filter($entregas, fn($e) => $e['id_entrega'] !== null));
    $calificadas = count(array_filter($entregas, fn($e) => $e['estado_entrega'] === 'CALIFICADO'));
    $pendientes = $entregadas - $calificadas;
    
    $html .= '
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="row text-center">
                <div class="col-3">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <h5 class="text-primary mb-0">' . $total_estudiantes . '</h5>
                            <small class="text-muted">Total estudiantes</small>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <h5 class="text-info mb-0">' . $entregadas . '</h5>
                            <small class="text-muted">Entregas recibidas</small>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <h5 class="text-warning mb-0">' . $pendientes . '</h5>
                            <small class="text-muted">Pendientes calificar</small>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <h5 class="text-success mb-0">' . $calificadas . '</h5>
                            <small class="text-muted">Calificadas</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'tarea' => $tarea,
        'estadisticas' => [
            'total' => $total_estudiantes,
            'entregadas' => $entregadas,
            'calificadas' => $calificadas,
            'pendientes' => $pendientes
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>