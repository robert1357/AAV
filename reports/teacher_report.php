<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Reporte de Docente";
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

            <!-- Selección de docente -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="teacherForm">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Seleccionar Docente:</label>
                                <select class="form-select" id="teacherId" onchange="loadTeacherData()">
                                    <option value="">Seleccionar docente</option>
                                    <option value="1">Prof. Juan Pérez García</option>
                                    <option value="2">Prof. María López Sánchez</option>
                                    <option value="3">Prof. Carlos Ramírez Torres</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Período:</label>
                                <select class="form-select" id="periodo">
                                    <option value="2025">2025</option>
                                    <option value="2024">2024</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Bimestre:</label>
                                <select class="form-select" id="bimestre">
                                    <option value="">Todos</option>
                                    <option value="1">Bimestre 1</option>
                                    <option value="2">Bimestre 2</option>
                                    <option value="3">Bimestre 3</option>
                                    <option value="4">Bimestre 4</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reporte del docente -->
            <div id="teacherReport" style="display: none;">
                <!-- Información del docente -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Información del Docente</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Nombre Completo:</strong></td>
                                        <td id="teacherName"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>DNI:</strong></td>
                                        <td id="teacherDni"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Especialidad:</strong></td>
                                        <td id="teacherEspecialidad"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td id="teacherEmail"></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Teléfono:</strong></td>
                                        <td id="teacherPhone"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Fecha Ingreso:</strong></td>
                                        <td id="teacherStartDate"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Estado:</strong></td>
                                        <td id="teacherStatus"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Carga Horaria:</strong></td>
                                        <td id="teacherHours"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4 id="statCursos">0</h4>
                                <p>Cursos Asignados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4 id="statEstudiantes">0</h4>
                                <p>Total Estudiantes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4 id="statPromedioGeneral">0</h4>
                                <p>Promedio General</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4 id="statAsistenciaClases">0%</h4>
                                <p>Asistencia a Clases</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cursos asignados -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Cursos Asignados</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th>Grado/Sección</th>
                                        <th>Estudiantes</th>
                                        <th>Horas Semanales</th>
                                        <th>Promedio</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="coursesTableBody">
                                    <!-- Los datos se llenarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Rendimiento por curso -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Rendimiento por Curso</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="card">
                    <div class="card-header">
                        <h5>Observaciones y Evaluación</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Fortalezas:</h6>
                                <ul id="fortalezas">
                                    <!-- Se llenarán dinámicamente -->
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Áreas de Mejora:</h6>
                                <ul id="areasMejora">
                                    <!-- Se llenarán dinámicamente -->
                                </ul>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6>Comentarios Adicionales:</h6>
                            <textarea class="form-control" id="comentarios" rows="4" placeholder="Ingrese comentarios sobre el desempeño del docente..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadTeacherData() {
    const teacherId = document.getElementById('teacherId').value;
    
    if (!teacherId) {
        document.getElementById('teacherReport').style.display = 'none';
        return;
    }
    
    // Simular datos del docente
    const teacherData = {
        1: {
            name: 'Prof. Juan Pérez García',
            dni: '12345678',
            especialidad: 'Matemáticas',
            email: 'juan.perez@colegio.edu',
            phone: '987654321',
            startDate: '15/02/2020',
            status: 'Activo',
            hours: '30 horas',
            courses: 4,
            students: 120,
            average: 15.2,
            attendance: 95
        },
        2: {
            name: 'Prof. María López Sánchez',
            dni: '87654321',
            especialidad: 'Comunicación',
            email: 'maria.lopez@colegio.edu',
            phone: '912345678',
            startDate: '01/03/2019',
            status: 'Activo',
            hours: '25 horas',
            courses: 3,
            students: 90,
            average: 16.1,
            attendance: 98
        }
    };
    
    const data = teacherData[teacherId];
    if (data) {
        // Llenar información del docente
        document.getElementById('teacherName').textContent = data.name;
        document.getElementById('teacherDni').textContent = data.dni;
        document.getElementById('teacherEspecialidad').textContent = data.especialidad;
        document.getElementById('teacherEmail').textContent = data.email;
        document.getElementById('teacherPhone').textContent = data.phone;
        document.getElementById('teacherStartDate').textContent = data.startDate;
        document.getElementById('teacherStatus').innerHTML = '<span class="badge bg-success">' + data.status + '</span>';
        document.getElementById('teacherHours').textContent = data.hours;
        
        // Estadísticas
        document.getElementById('statCursos').textContent = data.courses;
        document.getElementById('statEstudiantes').textContent = data.students;
        document.getElementById('statPromedioGeneral').textContent = data.average;
        document.getElementById('statAsistenciaClases').textContent = data.attendance + '%';
        
        // Llenar tabla de cursos
        const coursesBody = document.getElementById('coursesTableBody');
        coursesBody.innerHTML = '';
        
        const sampleCourses = [
            ['Matemáticas', '1° A', '30', '6', '15.2', 'Activo'],
            ['Álgebra', '2° B', '28', '4', '14.8', 'Activo'],
            ['Geometría', '3° C', '32', '5', '16.1', 'Activo']
        ];
        
        sampleCourses.forEach(course => {
            const row = coursesBody.insertRow();
            course.forEach(cell => {
                const td = row.insertCell();
                td.textContent = cell;
            });
        });
        
        // Mostrar reporte
        document.getElementById('teacherReport').style.display = 'block';
    }
}

function printReport() {
    window.print();
}

function exportToPDF() {
    alert('Función de exportación en desarrollo');
}
</script>

<?php require_once '../includes/footer.php'; ?>