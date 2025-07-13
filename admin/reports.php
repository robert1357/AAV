<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'ADMIN') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Reportes y Estadísticas";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Obtener estadísticas generales
$stats = [];

// Total de usuarios
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM personal WHERE activo = 1");
$stmt->execute();
$stats['personal_activo'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM estudiantes WHERE cuenta_bloqueada = 0");
$stmt->execute();
$stats['estudiantes_activos'] = $stmt->fetchColumn();

// Estadísticas académicas
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cursos WHERE activo = 1");
$stmt->execute();
$stats['cursos_activos'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM matriculas WHERE estado = 'ACTIVO'");
$stmt->execute();
$stats['matriculas_activas'] = $stmt->fetchColumn();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2><?php echo $page_title; ?></h2>
        </div>
    </div>

    <!-- Estadísticas generales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Personal Activo</h5>
                    <h3><?php echo $stats['personal_activo']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Estudiantes Activos</h5>
                    <h3><?php echo $stats['estudiantes_activos']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Cursos Activos</h5>
                    <h3><?php echo $stats['cursos_activos']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Matrículas Activas</h5>
                    <h3><?php echo $stats['matriculas_activas']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Secciones de reportes -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Reportes Académicos</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="generateReport('academic_performance')">
                            <i class="fas fa-chart-bar"></i> Rendimiento Académico
                        </button>
                        <button class="btn btn-outline-primary" onclick="generateReport('attendance')">
                            <i class="fas fa-calendar-check"></i> Reporte de Asistencia
                        </button>
                        <button class="btn btn-outline-primary" onclick="generateReport('grades')">
                            <i class="fas fa-graduation-cap"></i> Reporte de Calificaciones
                        </button>
                        <button class="btn btn-outline-primary" onclick="generateReport('course_performance')">
                            <i class="fas fa-book"></i> Rendimiento por Curso
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Reportes Administrativos</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-secondary" onclick="generateReport('user_activity')">
                            <i class="fas fa-users"></i> Actividad de Usuarios
                        </button>
                        <button class="btn btn-outline-secondary" onclick="generateReport('enrollment')">
                            <i class="fas fa-user-plus"></i> Reporte de Matrículas
                        </button>
                        <button class="btn btn-outline-secondary" onclick="generateReport('teachers')">
                            <i class="fas fa-chalkboard-teacher"></i> Reporte de Docentes
                        </button>
                        <button class="btn btn-outline-secondary" onclick="generateReport('system_logs')">
                            <i class="fas fa-file-alt"></i> Logs del Sistema
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Distribución por Grado</h5>
                </div>
                <div class="card-body">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Accesos por Mes</h5>
                </div>
                <div class="card-body">
                    <canvas id="accessChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateReport(reportType) {
    alert('Generando reporte: ' + reportType);
    // Implementar generación de reportes
}

// Implementar gráficos con Chart.js
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de distribución por grado
    const gradeCtx = document.getElementById('gradeChart').getContext('2d');
    // Implementar gráfico

    // Gráfico de accesos
    const accessCtx = document.getElementById('accessChart').getContext('2d');
    // Implementar gráfico
});
</script>

<?php require_once '../includes/footer.php'; ?>