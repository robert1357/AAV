<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'ADMIN') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Gestión de Estudiantes";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Obtener estudiantes con información de matrícula
$stmt = $pdo->prepare("
    SELECT e.*, m.estado as estado_matricula, g.nombre_grado, s.letra_seccion,
           aa.anio as anio_academico
    FROM estudiantes e
    LEFT JOIN matriculas m ON e.id_estudiante = m.id_estudiante
    LEFT JOIN secciones s ON m.id_seccion = s.id_seccion
    LEFT JOIN grados g ON s.id_grado = g.id_grado
    LEFT JOIN anios_academicos aa ON m.id_anio = aa.id_anio
    ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres
");
$stmt->execute();
$estudiantes = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?php echo $page_title; ?></h2>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importStudentsModal">
                        <i class="fas fa-upload"></i> Importar Excel
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus"></i> Agregar Estudiante
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
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
                        <div class="col-md-3">
                            <label class="form-label">Filtrar por Sección:</label>
                            <select class="form-select" id="filterSeccion">
                                <option value="">Todas las secciones</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado:</label>
                            <select class="form-select" id="filterEstado">
                                <option value="">Todos los estados</option>
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                                <option value="RETIRADO">Retirado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Buscar:</label>
                            <input type="text" class="form-control" id="searchStudent" placeholder="Nombre o DNI...">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de estudiantes -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre Completo</th>
                                    <th>DNI</th>
                                    <th>Grado/Sección</th>
                                    <th>Estado</th>
                                    <th>Apoderado</th>
                                    <th>Contacto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $estudiante): ?>
                                <tr>
                                    <td><?php echo $estudiante['codigo_estudiante']; ?></td>
                                    <td><?php echo $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres']; ?></td>
                                    <td><?php echo $estudiante['dni']; ?></td>
                                    <td>
                                        <?php if ($estudiante['nombre_grado']): ?>
                                            <?php echo $estudiante['nombre_grado'] . ' - ' . $estudiante['letra_seccion']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sin matrícula</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($estudiante['estado_matricula'] == 'ACTIVO'): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php elseif ($estudiante['estado_matricula'] == 'INACTIVO'): ?>
                                            <span class="badge bg-warning">Inactivo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Retirado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $estudiante['apoderado_nombres']; ?></td>
                                    <td>
                                        <small>
                                            <?php echo $estudiante['apoderado_celular']; ?><br>
                                            <?php echo $estudiante['apoderado_email']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editStudent(<?php echo $estudiante['id_estudiante']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewStudentProfile(<?php echo $estudiante['id_estudiante']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="resetStudentPassword('<?php echo $estudiante['dni']; ?>')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="enrollStudent(<?php echo $estudiante['id_estudiante']; ?>)">
                                                <i class="fas fa-graduation-cap"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editStudent(studentId) {
    alert('Función de edición en desarrollo');
}

function viewStudentProfile(studentId) {
    alert('Función de perfil en desarrollo');
}

function resetStudentPassword(dni) {
    if (confirm('¿Resetear contraseña del estudiante?')) {
        alert('Función de reset en desarrollo');
    }
}

function enrollStudent(studentId) {
    alert('Función de matrícula en desarrollo');
}
</script>

<?php require_once '../includes/footer.php'; ?>