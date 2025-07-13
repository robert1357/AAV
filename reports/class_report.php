<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Reporte de Clase";
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
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Año Académico:</label>
                                <select class="form-select" id="anioAcademico">
                                    <option value="2025">2025</option>
                                    <option value="2024">2024</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Grado:</label>
                                <select class="form-select" id="grado">
                                    <option value="">Seleccionar grado</option>
                                    <option value="1">1° Grado</option>
                                    <option value="2">2° Grado</option>
                                    <option value="3">3° Grado</option>
                                    <option value="4">4° Grado</option>
                                    <option value="5">5° Grado</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sección:</label>
                                <select class="form-select" id="seccion">
                                    <option value="">Seleccionar sección</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="button" class="btn btn-primary" onclick="generateReport()">
                                        <i class="fas fa-search"></i> Generar Reporte
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reporte -->
            <div id="reportContent" style="display: none;">
                <!-- Información de la clase -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Información de la Clase</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Grado/Sección:</strong></td>
                                        <td id="reportGradoSeccion"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Año Académico:</strong></td>
                                        <td id="reportAnio"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tutor:</strong></td>
                                        <td id="reportTutor"></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Total Estudiantes:</strong></td>
                                        <td id="reportTotalEstudiantes"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Promedio General:</strong></td>
                                        <td id="reportPromedioGeneral"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Fecha Generación:</strong></td>
                                        <td><?php echo date('d/m/Y H:i'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4 id="statAprobados">0</h4>
                                <p>Aprobados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4 id="statDesaprobados">0</h4>
                                <p>Desaprobados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4 id="statAsistenciaPromedio">0%</h4>
                                <p>Asistencia Promedio</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4 id="statMejorPromedio">0</h4>
                                <p>Mejor Promedio</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de estudiantes -->
                <div class="card">
                    <div class="card-header">
                        <h5>Lista de Estudiantes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Código</th>
                                        <th>Apellidos y Nombres</th>
                                        <th>DNI</th>
                                        <th>Promedio</th>
                                        <th>Asistencia</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="studentsTableBody">
                                    <!-- Los datos se llenarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateReport() {
    const anio = document.getElementById('anioAcademico').value;
    const grado = document.getElementById('grado').value;
    const seccion = document.getElementById('seccion').value;
    
    if (!grado || !seccion) {
        alert('Por favor seleccione el grado y la sección');
        return;
    }
    
    // Simular datos del reporte
    document.getElementById('reportGradoSeccion').textContent = grado + '° - ' + seccion;
    document.getElementById('reportAnio').textContent = anio;
    document.getElementById('reportTutor').textContent = 'Profesor(a) Ejemplo';
    document.getElementById('reportTotalEstudiantes').textContent = '28';
    document.getElementById('reportPromedioGeneral').textContent = '15.2';
    
    // Estadísticas
    document.getElementById('statAprobados').textContent = '25';
    document.getElementById('statDesaprobados').textContent = '3';
    document.getElementById('statAsistenciaPromedio').textContent = '87%';
    document.getElementById('statMejorPromedio').textContent = '18.5';
    
    // Mostrar reporte
    document.getElementById('reportContent').style.display = 'block';
    
    // Llenar tabla de estudiantes (simulado)
    const tableBody = document.getElementById('studentsTableBody');
    tableBody.innerHTML = '';
    
    for (let i = 1; i <= 28; i++) {
        const row = tableBody.insertRow();
        row.innerHTML = `
            <td>${i}</td>
            <td>EST2025${String(i).padStart(3, '0')}</td>
            <td>Estudiante ${i}</td>
            <td>123456${String(i).padStart(2, '0')}</td>
            <td>${(Math.random() * 8 + 10).toFixed(1)}</td>
            <td>${(Math.random() * 20 + 80).toFixed(0)}%</td>
            <td><span class="badge bg-success">Aprobado</span></td>
        `;
    }
}

function printReport() {
    window.print();
}

function exportToExcel() {
    alert('Función de exportación en desarrollo');
}
</script>

<?php require_once '../includes/footer.php'; ?>