<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'ADMIN') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Gestión de Cursos";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Obtener cursos con información adicional
$stmt = $pdo->prepare("
    SELECT c.*, g.numero_grado, g.nombre_grado,
           COUNT(DISTINCT a.id_asignacion) as total_asignaciones,
           COUNT(DISTINCT m.id_matricula) as total_estudiantes
    FROM cursos c
    LEFT JOIN grados g ON c.id_grado = g.id_grado
    LEFT JOIN asignaciones a ON c.id_curso = a.id_curso
    LEFT JOIN matriculas m ON a.id_seccion = m.id_seccion
    GROUP BY c.id_curso
    ORDER BY g.numero_grado, c.nombre
");
$stmt->execute();
$cursos = $stmt->fetchAll();

// Obtener grados para el formulario
$stmt = $pdo->prepare("SELECT * FROM grados ORDER BY numero_grado");
$stmt->execute();
$grados = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?php echo $page_title; ?></h2>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="fas fa-plus"></i> Agregar Curso
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
                                <?php foreach ($grados as $grado): ?>
                                <option value="<?php echo $grado['id_grado']; ?>"><?php echo $grado['nombre_grado']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filtrar por Área:</label>
                            <select class="form-select" id="filterArea">
                                <option value="">Todas las áreas</option>
                                <option value="CIENCIAS">Ciencias</option>
                                <option value="LETRAS">Letras</option>
                                <option value="MATEMATICAS">Matemáticas</option>
                                <option value="COMUNICACION">Comunicación</option>
                                <option value="EDUCACION_FISICA">Educación Física</option>
                                <option value="ARTE">Arte</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Buscar:</label>
                            <input type="text" class="form-control" id="searchCourse" placeholder="Buscar curso...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button class="btn btn-outline-secondary" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de cursos -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="coursesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Grado</th>
                                    <th>Área</th>
                                    <th>Horas</th>
                                    <th>Docentes</th>
                                    <th>Estudiantes</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cursos as $curso): ?>
                                <tr>
                                    <td><?php echo $curso['id_curso']; ?></td>
                                    <td><?php echo $curso['codigo']; ?></td>
                                    <td><?php echo $curso['nombre']; ?></td>
                                    <td><?php echo $curso['nombre_grado']; ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $curso['area']; ?></span>
                                    </td>
                                    <td><?php echo $curso['horas_semanales']; ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $curso['total_asignaciones']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $curso['total_estudiantes']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($curso['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editCourse(<?php echo $curso['id_curso']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewCourseDetails(<?php echo $curso['id_curso']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="manageCourseTeachers(<?php echo $curso['id_curso']; ?>)">
                                                <i class="fas fa-users"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-<?php echo $curso['activo'] ? 'danger' : 'success'; ?>" onclick="toggleCourseStatus(<?php echo $curso['id_curso']; ?>, <?php echo $curso['activo'] ? 'false' : 'true'; ?>)">
                                                <i class="fas fa-<?php echo $curso['activo'] ? 'ban' : 'check'; ?>"></i>
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

<!-- Modal para agregar curso -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Nuevo Curso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addCourseForm">
                    <div class="mb-3">
                        <label for="codigo" class="form-label">Código *</label>
                        <input type="text" class="form-control" id="codigo" required>
                    </div>
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="id_grado" class="form-label">Grado *</label>
                        <select class="form-select" id="id_grado" required>
                            <option value="">Seleccionar grado</option>
                            <?php foreach ($grados as $grado): ?>
                            <option value="<?php echo $grado['id_grado']; ?>"><?php echo $grado['nombre_grado']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="area" class="form-label">Área *</label>
                        <select class="form-select" id="area" required>
                            <option value="">Seleccionar área</option>
                            <option value="CIENCIAS">Ciencias</option>
                            <option value="LETRAS">Letras</option>
                            <option value="MATEMATICAS">Matemáticas</option>
                            <option value="COMUNICACION">Comunicación</option>
                            <option value="EDUCACION_FISICA">Educación Física</option>
                            <option value="ARTE">Arte</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="horas_semanales" class="form-label">Horas Semanales *</label>
                        <input type="number" class="form-control" id="horas_semanales" min="1" max="10" required>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveCourse()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
function editCourse(courseId) {
    // Implementar edición de curso
    alert('Función de edición en desarrollo');
}

function viewCourseDetails(courseId) {
    // Implementar vista de detalles del curso
    alert('Función de detalles en desarrollo');
}

function manageCourseTeachers(courseId) {
    // Implementar gestión de docentes del curso
    alert('Función de gestión de docentes en desarrollo');
}

function toggleCourseStatus(courseId, newStatus) {
    if (confirm('¿Está seguro de cambiar el estado de este curso?')) {
        // Implementar cambio de estado
        alert('Función de cambio de estado en desarrollo');
    }
}

function saveCourse() {
    // Implementar guardado de curso
    alert('Función de guardado en desarrollo');
}

function clearFilters() {
    document.getElementById('filterGrado').value = '';
    document.getElementById('filterArea').value = '';
    document.getElementById('searchCourse').value = '';
    // Implementar filtrado
}

// Implementar filtrado en tiempo real
document.getElementById('searchCourse').addEventListener('input', function() {
    // Implementar búsqueda
});

document.getElementById('filterGrado').addEventListener('change', function() {
    // Implementar filtrado por grado
});

document.getElementById('filterArea').addEventListener('change', function() {
    // Implementar filtrado por área
});
</script>

<?php require_once '../includes/footer.php'; ?>