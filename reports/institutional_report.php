<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['cargo'], ['ADMIN', 'DIRECTOR'])) {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Reporte Institucional";
require_once '../includes/header.php';
require_once '../includes/navbar.php';
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
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="filterForm">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Período:</label>
                                <select class="form-select" id="periodo">
                                    <option value="2025">2025</option>
                                    <option value="2024">2024</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo de Reporte:</label>
                                <select class="form-select" id="tipoReporte">
                                    <option value="general">Reporte General</option>
                                    <option value="academico">Académico</option>
                                    <option value="administrativo">Administrativo</option>
                                    <option value="estadistico">Estadístico</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="button" class="btn btn-primary" onclick="generateReport()">
                                        <i class="fas fa-chart-line"></i> Generar Reporte
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reporte -->
            <div id="reportContent">
                <!-- Información institucional -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Información Institucional</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Institución:</strong></td>
                                        <td>Institución Educativa Ejemplo</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Código Modular:</strong></td>
                                        <td>123456789</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nivel:</strong></td>
                                        <td>Educación Secundaria</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Modalidad:</strong></td>
                                        <td>Presencial</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Director:</strong></td>
                                        <td>Mg. Director Ejemplo</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Ubicación:</strong></td>
                                        <td>Lima, Perú</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Teléfono:</strong></td>
                                        <td>(01) 234-5678</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td>contacto@colegio.edu.pe</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas generales -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4>450</h4>
                                <p>Estudiantes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4>35</h4>
                                <p>Docentes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4>15</h4>
                                <p>Cursos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4>18</h4>
                                <p>Secciones</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h4>25</h4>
                                <p>Personal</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-dark text-white">
                            <div class="card-body text-center">
                                <h4>15.2</h4>
                                <p>Promedio</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Distribución por grado -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Distribución de Estudiantes por Grado</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Grado</th>
                                        <th>Secciones</th>
                                        <th>Estudiantes</th>
                                        <th>Promedio</th>
                                        <th>Aprobados</th>
                                        <th>Desaprobados</th>
                                        <th>% Aprobación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>1° Grado</td>
                                        <td>4</td>
                                        <td>120</td>
                                        <td>14.8</td>
                                        <td>105</td>
                                        <td>15</td>
                                        <td>87.5%</td>
                                    </tr>
                                    <tr>
                                        <td>2° Grado</td>
                                        <td>4</td>
                                        <td>115</td>
                                        <td>15.1</td>
                                        <td>102</td>
                                        <td>13</td>
                                        <td>88.7%</td>
                                    </tr>
                                    <tr>
                                        <td>3° Grado</td>
                                        <td>3</td>
                                        <td>95</td>
                                        <td>15.5</td>
                                        <td>87</td>
                                        <td>8</td>
                                        <td>91.6%</td>
                                    </tr>
                                    <tr>
                                        <td>4° Grado</td>
                                        <td>3</td>
                                        <td>90</td>
                                        <td>15.8</td>
                                        <td>84</td>
                                        <td>6</td>
                                        <td>93.3%</td>
                                    </tr>
                                    <tr>
                                        <td>5° Grado</td>
                                        <td>2</td>
                                        <td>60</td>
                                        <td>16.2</td>
                                        <td>58</td>
                                        <td>2</td>
                                        <td>96.7%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Rendimiento por área -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Rendimiento por Área Curricular</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="areaChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Evolución Mensual de Notas</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="evolutionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal por cargo -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Distribución del Personal</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Cargo</th>
                                        <th>Cantidad</th>
                                        <th>Activos</th>
                                        <th>Inactivos</th>
                                        <th>% Activos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Director</td>
                                        <td>1</td>
                                        <td>1</td>
                                        <td>0</td>
                                        <td>100%</td>
                                    </tr>
                                    <tr>
                                        <td>Docentes</td>
                                        <td>35</td>
                                        <td>34</td>
                                        <td>1</td>
                                        <td>97.1%</td>
                                    </tr>
                                    <tr>
                                        <td>Auxiliares</td>
                                        <td>8</td>
                                        <td>8</td>
                                        <td>0</td>
                                        <td>100%</td>
                                    </tr>
                                    <tr>
                                        <td>Administrativo</td>
                                        <td>12</td>
                                        <td>11</td>
                                        <td>1</td>
                                        <td>91.7%</td>
                                    </tr>
                                    <tr>
                                        <td>Otros</td>
                                        <td>4</td>
                                        <td>4</td>
                                        <td>0</td>
                                        <td>100%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Indicadores de calidad -->
                <div class="card">
                    <div class="card-header">
                        <h5>Indicadores de Calidad Educativa</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Índice de Aprobación</h6>
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-success" style="width: 91%"></div>
                                        </div>
                                        <small>91% de estudiantes aprobados</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Asistencia Promedio</h6>
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-info" style="width: 87%"></div>
                                        </div>
                                        <small>87% de asistencia promedio</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Satisfacción</h6>
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-warning" style="width: 85%"></div>
                                        </div>
                                        <small>85% de satisfacción</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateReport() {
    const periodo = document.getElementById('periodo').value;
    const tipo = document.getElementById('tipoReporte').value;
    
    // Simular actualización de datos según el tipo de reporte
    console.log('Generando reporte:', tipo, 'para el período:', periodo);
    
    // Aquí se implementaría la lógica para generar diferentes tipos de reportes
    // Por ahora mostramos una notificación
    alert('Reporte ' + tipo + ' generado para el período ' + periodo);
}

function printReport() {
    window.print();
}

function exportToPDF() {
    alert('Función de exportación en desarrollo');
}

// Inicializar gráficos cuando se cargue la página
document.addEventListener('DOMContentLoaded', function() {
    // Aquí se inicializarían los gráficos con Chart.js
    // Por ahora solo mostramos un mensaje
    console.log('Gráficos inicializados');
});
</script>

<?php require_once '../includes/footer.php'; ?>