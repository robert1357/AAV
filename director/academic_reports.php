<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'DIRECTOR') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Reportes Académicos - Director";

// Obtener años académicos disponibles
$stmt = $pdo->query("SELECT * FROM anios_academicos ORDER BY anio DESC");
$anos_academicos = $stmt->fetchAll();

// Obtener bimestres del año actual
$anio_actual = $_POST['anio'] ?? date('Y');
$stmt = $pdo->prepare("SELECT * FROM bimestres b 
                       JOIN anios_academicos a ON b.id_anio = a.id_anio 
                       WHERE a.anio = ? ORDER BY b.numero_bimestre");
$stmt->execute([$anio_actual]);
$bimestres = $stmt->fetchAll();

// Procesar solicitud de reporte
$reporte_data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_reporte'])) {
    $tipo_reporte = $_POST['tipo_reporte'];
    $anio = $_POST['anio'];
    $bimestre = $_POST['bimestre'] ?? null;
    
    switch ($tipo_reporte) {
        case 'promedio_general':
            $sql = "SELECT 
                        g.numero_grado,
                        s.letra_seccion,
                        COUNT(DISTINCT e.id_estudiante) as total_estudiantes,
                        AVG(n.nota) as promedio_general,
                        COUNT(CASE WHEN n.nota >= 14 THEN 1 END) as aprobados,
                        COUNT(CASE WHEN n.nota < 14 THEN 1 END) as desaprobados
                    FROM matriculas m
                    JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
                    JOIN secciones s ON m.id_seccion = s.id_seccion
                    JOIN grados g ON s.id_grado = g.id_grado
                    JOIN anios_academicos a ON m.id_anio = a.id_anio
                    LEFT JOIN notas n ON m.id_matricula = n.id_matricula
                    LEFT JOIN bimestres b ON n.id_bimestre = b.id_bimestre
                    WHERE a.anio = ? AND m.estado = 'ACTIVO'";
            
            $params = [$anio];
            if ($bimestre) {
                $sql .= " AND b.numero_bimestre = ?";
                $params[] = $bimestre;
            }
            
            $sql .= " GROUP BY g.numero_grado, s.letra_seccion ORDER BY g.numero_grado, s.letra_seccion";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reporte_data = $stmt->fetchAll();
            break;
            
        case 'rendimiento_cursos':
            $sql = "SELECT 
                        c.nombre as curso,
                        c.codigo,
                        AVG(n.nota) as promedio_curso,
                        COUNT(DISTINCT n.id_matricula) as total_evaluaciones,
                        COUNT(CASE WHEN n.nota >= 18 THEN 1 END) as excelente,
                        COUNT(CASE WHEN n.nota >= 14 AND n.nota < 18 THEN 1 END) as bueno,
                        COUNT(CASE WHEN n.nota >= 11 AND n.nota < 14 THEN 1 END) as regular,
                        COUNT(CASE WHEN n.nota < 11 THEN 1 END) as deficiente
                    FROM cursos c
                    LEFT JOIN notas n ON c.id_curso = n.id_curso
                    LEFT JOIN bimestres b ON n.id_bimestre = b.id_bimestre
                    LEFT JOIN anios_academicos a ON b.id_anio = a.id_anio
                    WHERE a.anio = ?";
            
            $params = [$anio];
            if ($bimestre) {
                $sql .= " AND b.numero_bimestre = ?";
                $params[] = $bimestre;
            }
            
            $sql .= " GROUP BY c.id_curso ORDER BY promedio_curso DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reporte_data = $stmt->fetchAll();
            break;
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line"></i> Reportes Académicos
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="tipo_reporte" class="form-label">Tipo de Reporte</label>
                                <select name="tipo_reporte" id="tipo_reporte" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="promedio_general">Promedio General por Grado</option>
                                    <option value="rendimiento_cursos">Rendimiento por Cursos</option>
                                    <option value="asistencia_general">Asistencia General</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="anio" class="form-label">Año Académico</label>
                                <select name="anio" id="anio" class="form-select" required>
                                    <?php foreach ($anos_academicos as $ano): ?>
                                        <option value="<?= $ano['anio'] ?>" <?= $ano['anio'] == $anio_actual ? 'selected' : '' ?>>
                                            <?= $ano['anio'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="bimestre" class="form-label">Bimestre (Opcional)</label>
                                <select name="bimestre" id="bimestre" class="form-select">
                                    <option value="">Todos los bimestres</option>
                                    <?php foreach ($bimestres as $bim): ?>
                                        <option value="<?= $bim['numero_bimestre'] ?>">
                                            <?= $bim['numero_bimestre'] ?>° Bimestre
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" name="generar_reporte" class="btn btn-primary">
                                    <i class="fas fa-chart-bar"></i> Generar Reporte
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($reporte_data)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <?php if ($_POST['tipo_reporte'] === 'promedio_general'): ?>
                                            <th>Grado</th>
                                            <th>Sección</th>
                                            <th>Total Estudiantes</th>
                                            <th>Promedio General</th>
                                            <th>Aprobados</th>
                                            <th>Desaprobados</th>
                                            <th>% Aprobación</th>
                                        <?php elseif ($_POST['tipo_reporte'] === 'rendimiento_cursos'): ?>
                                            <th>Curso</th>
                                            <th>Código</th>
                                            <th>Promedio</th>
                                            <th>Total Evaluaciones</th>
                                            <th>Excelente (18-20)</th>
                                            <th>Bueno (14-17)</th>
                                            <th>Regular (11-13)</th>
                                            <th>Deficiente (0-10)</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reporte_data as $row): ?>
                                        <tr>
                                            <?php if ($_POST['tipo_reporte'] === 'promedio_general'): ?>
                                                <td><?= $row['numero_grado'] ?>°</td>
                                                <td><?= $row['letra_seccion'] ?></td>
                                                <td><?= $row['total_estudiantes'] ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $row['promedio_general'] >= 14 ? 'success' : 'warning' ?>">
                                                        <?= number_format($row['promedio_general'], 2) ?>
                                                    </span>
                                                </td>
                                                <td class="text-success"><?= $row['aprobados'] ?></td>
                                                <td class="text-danger"><?= $row['desaprobados'] ?></td>
                                                <td>
                                                    <?php 
                                                    $porcentaje = $row['total_estudiantes'] > 0 ? 
                                                        ($row['aprobados'] / $row['total_estudiantes']) * 100 : 0;
                                                    ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $porcentaje ?>%">
                                                            <?= number_format($porcentaje, 1) ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            <?php elseif ($_POST['tipo_reporte'] === 'rendimiento_cursos'): ?>
                                                <td><?= htmlspecialchars($row['curso']) ?></td>
                                                <td><?= htmlspecialchars($row['codigo']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $row['promedio_curso'] >= 14 ? 'success' : 'warning' ?>">
                                                        <?= number_format($row['promedio_curso'], 2) ?>
                                                    </span>
                                                </td>
                                                <td><?= $row['total_evaluaciones'] ?></td>
                                                <td class="text-success"><?= $row['excelente'] ?></td>
                                                <td class="text-info"><?= $row['bueno'] ?></td>
                                                <td class="text-warning"><?= $row['regular'] ?></td>
                                                <td class="text-danger"><?= $row['deficiente'] ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button onclick="window.print()" class="btn btn-secondary">
                                <i class="fas fa-print"></i> Imprimir Reporte
                            </button>
                            <button onclick="exportToExcel()" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Exportar a Excel
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // Implementar exportación a Excel
    alert('Función de exportación en desarrollo');
}
</script>

<?php include '../includes/footer.php'; ?>