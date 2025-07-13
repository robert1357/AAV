<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'ADMIN') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Gestión de Usuarios";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Obtener lista de usuarios del personal
$stmt = $pdo->prepare("
    SELECT p.*, COUNT(h.id_historial) as total_accesos,
           MAX(h.fecha_acceso) as ultimo_acceso
    FROM personal p
    LEFT JOIN historial_accesos h ON p.id_personal = h.id_personal
    GROUP BY p.id_personal
    ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres
");
$stmt->execute();
$usuarios = $stmt->fetchAll();

// Obtener estudiantes
$stmt = $pdo->prepare("
    SELECT e.*, COUNT(h.id_historial) as total_accesos,
           MAX(h.fecha_acceso) as ultimo_acceso
    FROM estudiantes e
    LEFT JOIN historial_accesos_estudiantes h ON e.id_estudiante = h.id_estudiante
    GROUP BY e.id_estudiante
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
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus"></i> Agregar Usuario
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="userTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                        Personal (<?php echo count($usuarios); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="estudiantes-tab" data-bs-toggle="tab" data-bs-target="#estudiantes" type="button" role="tab">
                        Estudiantes (<?php echo count($estudiantes); ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="userTabsContent">
                <!-- Personal Tab -->
                <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre Completo</th>
                                            <th>DNI</th>
                                            <th>Cargo</th>
                                            <th>Email</th>
                                            <th>Estado</th>
                                            <th>Último Acceso</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?php echo $usuario['id_personal']; ?></td>
                                            <td><?php echo $usuario['apellido_paterno'] . ' ' . $usuario['apellido_materno'] . ', ' . $usuario['nombres']; ?></td>
                                            <td><?php echo $usuario['dni']; ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $usuario['cargo']; ?></span>
                                            </td>
                                            <td><?php echo $usuario['email']; ?></td>
                                            <td>
                                                <?php if ($usuario['activo']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo $usuario['id_personal']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo $usuario['id_personal']; ?>)">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="toggleUserStatus(<?php echo $usuario['id_personal']; ?>, <?php echo $usuario['activo'] ? 'false' : 'true'; ?>)">
                                                        <i class="fas fa-<?php echo $usuario['activo'] ? 'ban' : 'check'; ?>"></i>
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

                <!-- Estudiantes Tab -->
                <div class="tab-pane fade" id="estudiantes" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Código</th>
                                            <th>Nombre Completo</th>
                                            <th>DNI</th>
                                            <th>Estado</th>
                                            <th>Último Acceso</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($estudiantes as $estudiante): ?>
                                        <tr>
                                            <td><?php echo $estudiante['id_estudiante']; ?></td>
                                            <td><?php echo $estudiante['codigo_estudiante']; ?></td>
                                            <td><?php echo $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres']; ?></td>
                                            <td><?php echo $estudiante['dni']; ?></td>
                                            <td>
                                                <?php if ($estudiante['cuenta_bloqueada']): ?>
                                                    <span class="badge bg-danger">Bloqueado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $estudiante['fecha_ultimo_acceso'] ? date('d/m/Y H:i', strtotime($estudiante['fecha_ultimo_acceso'])) : 'Nunca'; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editStudent(<?php echo $estudiante['id_estudiante']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="resetStudentPassword('<?php echo $estudiante['dni']; ?>')">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-<?php echo $estudiante['cuenta_bloqueada'] ? 'success' : 'danger'; ?>" onclick="toggleStudentStatus(<?php echo $estudiante['id_estudiante']; ?>, <?php echo $estudiante['cuenta_bloqueada'] ? 'false' : 'true'; ?>)">
                                                        <i class="fas fa-<?php echo $estudiante['cuenta_bloqueada'] ? 'unlock' : 'lock'; ?>"></i>
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
    </div>
</div>

<script>
function editUser(userId) {
    // Implementar edición de usuario
    alert('Función de edición en desarrollo');
}

function resetPassword(userId) {
    if (confirm('¿Está seguro de resetear la contraseña de este usuario?')) {
        // Implementar reset de contraseña
        alert('Función de reset en desarrollo');
    }
}

function toggleUserStatus(userId, newStatus) {
    if (confirm('¿Está seguro de cambiar el estado de este usuario?')) {
        // Implementar cambio de estado
        alert('Función de cambio de estado en desarrollo');
    }
}

function editStudent(studentId) {
    // Implementar edición de estudiante
    alert('Función de edición en desarrollo');
}

function resetStudentPassword(dni) {
    if (confirm('¿Está seguro de resetear la contraseña de este estudiante?')) {
        // Implementar reset de contraseña de estudiante
        alert('Función de reset en desarrollo');
    }
}

function toggleStudentStatus(studentId, newStatus) {
    if (confirm('¿Está seguro de cambiar el estado de este estudiante?')) {
        // Implementar cambio de estado
        alert('Función de cambio de estado en desarrollo');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>