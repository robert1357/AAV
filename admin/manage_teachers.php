<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'ADMIN') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Gestión de Docentes";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Obtener docentes con información de asignaciones
$stmt = $pdo->prepare("
    SELECT p.*, COUNT(a.id_asignacion) as total_asignaciones,
           COUNT(DISTINCT a.id_curso) as total_cursos
    FROM personal p
    LEFT JOIN asignaciones a ON p.id_personal = a.id_personal
    WHERE p.cargo = 'DOCENTE' OR p.cargo = 'DOCENTE_DAIP'
    GROUP BY p.id_personal
    ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres
");
$stmt->execute();
$docentes = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?php echo $page_title; ?></h2>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="fas fa-plus"></i> Agregar Docente
                    </button>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Docentes</h5>
                            <h3><?php echo count($docentes); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Activos</h5>
                            <h3><?php echo count(array_filter($docentes, function($d) { return $d['activo']; })); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">Con Asignaciones</h5>
                            <h3><?php echo count(array_filter($docentes, function($d) { return $d['total_asignaciones'] > 0; })); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">DAIP</h5>
                            <h3><?php echo count(array_filter($docentes, function($d) { return $d['cargo'] == 'DOCENTE_DAIP'; })); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de docentes -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Completo</th>
                                    <th>DNI</th>
                                    <th>Especialidad</th>
                                    <th>Tipo</th>
                                    <th>Cursos</th>
                                    <th>Asignaciones</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($docentes as $docente): ?>
                                <tr>
                                    <td><?php echo $docente['id_personal']; ?></td>
                                    <td><?php echo $docente['apellido_paterno'] . ' ' . $docente['apellido_materno'] . ', ' . $docente['nombres']; ?></td>
                                    <td><?php echo $docente['dni']; ?></td>
                                    <td><?php echo $docente['especialidad'] ?: 'No especificada'; ?></td>
                                    <td>
                                        <?php if ($docente['cargo'] == 'DOCENTE_DAIP'): ?>
                                            <span class="badge bg-info">DAIP</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Docente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $docente['total_cursos']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?php echo $docente['total_asignaciones']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($docente['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editTeacher(<?php echo $docente['id_personal']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewTeacherProfile(<?php echo $docente['id_personal']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="manageAssignments(<?php echo $docente['id_personal']; ?>)">
                                                <i class="fas fa-tasks"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewSchedule(<?php echo $docente['id_personal']; ?>)">
                                                <i class="fas fa-calendar"></i>
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
function editTeacher(teacherId) {
    alert('Función de edición en desarrollo');
}

function viewTeacherProfile(teacherId) {
    alert('Función de perfil en desarrollo');
}

function manageAssignments(teacherId) {
    alert('Función de asignaciones en desarrollo');
}

function viewSchedule(teacherId) {
    alert('Función de horarios en desarrollo');
}
</script>

<?php require_once '../includes/footer.php'; ?>