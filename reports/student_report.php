<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Reporte Individual de Estudiante";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$student_id = $_GET['student_id'] ?? null;
$student_data = null;

if ($student_id) {
    // Obtener datos del estudiante
    $stmt = $pdo->prepare("
        SELECT e.*, m.estado as estado_matricula, g.nombre_grado, s.letra_seccion,
               aa.anio as anio_academico
        FROM estudiantes e
        LEFT JOIN matriculas m ON e.id_estudiante = m.id_estudiante
        LEFT JOIN secciones s ON m.id_seccion = s.id_seccion
        LEFT JOIN grados g ON s.id_grado = g.id_grado
        LEFT JOIN anios_academicos aa ON m.id_anio = aa.id_anio
        WHERE e.id_estudiante = ?
    ");
    $stmt->execute([$student_id]);
    $student_data = $stmt->fetch();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?php echo $page_title; ?></h2>
                <div>
                    <button class="btn btn-primary" onclick="printReport()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <button class="btn btn-success" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> Exportar PDF
                    </button>
                </div>
            </div>

            <?php if (!$student_data): ?>
                <!-- Selección de estudiante -->
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Buscar Estudiante:</label>
                                <input type="text" class="form-control" id="searchStudent" placeholder="Buscar por nombre o código...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Filtrar por Grado:</label>
                                <select class="form-select" id="filterGrado">
                                    <option value="">Todos los grados</option>
                                    <option value="1">1° Grado</option>
                                    <option value="2">2° Grado</option>
                                    <option value="3">3° Grado</option>
                                    <option value="4">4° Grado</option>
                                    <option value="5">5° Grado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Reporte del estudiante -->
                <div id="reportContent">
                    <!-- Información personal -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Información Personal</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Código:</strong></td>
                                            <td><?php echo $student_data['codigo_estudiante']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Nombre Completo:</strong></td>
                                            <td><?php echo $student_data['apellido_paterno'] . ' ' . $student_data['apellido_materno'] . ', ' . $student_data['nombres']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>DNI:</strong></td>
                                            <td><?php echo $student_data['dni']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Fecha de Nacimiento:</strong></td>
                                            <td><?php echo date('d/m/Y', strtotime($student_data['fecha_nacimiento'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Grado/Sección:</strong></td>
                                            <td><?php echo $student_data['nombre_grado'] . ' - ' . $student_data['letra_seccion']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Año Académico:</strong></td>
                                            <td><?php echo $student_data['anio_academico']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Estado:</strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $student_data['estado_matricula'] == 'ACTIVO' ? 'success' : 'warning'; ?>">
                                                    <?php echo $student_data['estado_matricula']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Apoderado:</strong></td>
                                            <td><?php echo $student_data['apoderado_nombres']; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rendimiento académico -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Rendimiento Académico</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Curso</th>
                                            <th>Bimestre 1</th>
                                            <th>Bimestre 2</th>
                                            <th>Bimestre 3</th>
                                            <th>Bimestre 4</th>
                                            <th>Promedio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Datos de calificaciones se llenarían aquí -->
                                        <tr>
                                            <td>Matemáticas</td>
                                            <td>16</td>
                                            <td>15</td>
                                            <td>17</td>
                                            <td>16</td>
                                            <td><strong>16.0</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Asistencia -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Registro de Asistencia</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h4>85%</h4>
                                            <p>Asistencia Total</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h4>170</h4>
                                            <p>Días Asistidos</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body text-center">
                                            <h4>25</h4>
                                            <p>Faltas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <h4>5</h4>
                                            <p>Tardanzas</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div class="card">
                        <div class="card-header">
                            <h5>Observaciones y Comentarios</h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" rows="4" placeholder="Ingrese observaciones sobre el estudiante..."></textarea>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function printReport() {
    window.print();
}

function exportToPDF() {
    alert('Función de exportación en desarrollo');
}
</script>

<?php require_once '../includes/footer.php'; ?>