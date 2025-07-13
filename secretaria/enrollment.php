<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'SECRETARIA') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Gestión de Matrículas - Secretaría";

// Obtener datos necesarios
$stmt = $pdo->query("SELECT * FROM grados ORDER BY numero_grado");
$grados = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM anios_academicos WHERE estado = 'ACTIVO' ORDER BY anio DESC");
$anos_academicos = $stmt->fetchAll();

// Procesar matrícula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['matricular_estudiante'])) {
    try {
        // Usar el procedimiento almacenado sp_matricular_estudiante
        $stmt = $pdo->prepare("CALL sp_matricular_estudiante(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['codigo_estudiante'],
            $_POST['apellido_paterno'],
            $_POST['apellido_materno'],
            $_POST['nombres'],
            $_POST['sexo'],
            $_POST['fecha_nacimiento'],
            $_POST['apoderado_nombres'],
            $_POST['apoderado_parentesco'],
            $_POST['apoderado_celular'],
            $_POST['apoderado_email'],
            $_POST['grado'],
            $_POST['seccion'],
            $_POST['anio']
        ]);
        
        $result = $stmt->fetch();
        $success_message = $result['mensaje'];
        
        // Generar contraseña inicial para el estudiante
        $stmt = $pdo->prepare("SELECT id_estudiante FROM estudiantes WHERE codigo_estudiante = ?");
        $stmt->execute([$_POST['codigo_estudiante']]);
        $estudiante = $stmt->fetch();
        
        if ($estudiante) {
            $stmt = $pdo->prepare("CALL sp_establecer_password_inicial(?)");
            $stmt->execute([$estudiante['id_estudiante']]);
            $password_result = $stmt->fetch();
            $password_temporal = $password_result['password_temporal'];
        }
        
    } catch (Exception $e) {
        $error_message = "Error al matricular estudiante: " . $e->getMessage();
    }
}

// Buscar estudiantes matriculados
$estudiantes_matriculados = [];
$filtro_aplicado = false;

if (isset($_GET['buscar']) || isset($_POST['buscar_estudiantes'])) {
    $filtro_aplicado = true;
    $anio_busqueda = $_GET['anio'] ?? $_POST['anio'] ?? date('Y');
    $grado_busqueda = $_GET['grado'] ?? $_POST['grado'] ?? '';
    $nombre_busqueda = $_GET['nombre'] ?? $_POST['nombre'] ?? '';
    
    $sql = "SELECT 
                e.codigo_estudiante,
                e.dni,
                CONCAT(e.apellido_paterno, ' ', e.apellido_materno, ', ', e.nombres) as nombre_completo,
                e.sexo,
                e.fecha_nacimiento,
                g.numero_grado,
                s.letra_seccion,
                m.fecha_matricula,
                m.estado,
                e.apoderado_nombres,
                e.apoderado_celular
            FROM estudiantes e
            JOIN matriculas m ON e.id_estudiante = m.id_estudiante
            JOIN secciones s ON m.id_seccion = s.id_seccion
            JOIN grados g ON s.id_grado = g.id_grado
            JOIN anios_academicos a ON m.id_anio = a.id_anio
            WHERE a.anio = ?";
    
    $params = [$anio_busqueda];
    
    if (!empty($grado_busqueda)) {
        $sql .= " AND g.numero_grado = ?";
        $params[] = $grado_busqueda;
    }
    
    if (!empty($nombre_busqueda)) {
        $sql .= " AND (e.nombres LIKE ? OR e.apellido_paterno LIKE ? OR e.apellido_materno LIKE ? OR e.codigo_estudiante LIKE ?)";
        $busqueda_param = '%' . $nombre_busqueda . '%';
        $params = array_merge($params, [$busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param]);
    }
    
    $sql .= " ORDER BY g.numero_grado, s.letra_seccion, e.apellido_paterno";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $estudiantes_matriculados = $stmt->fetchAll();
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-user-plus"></i> Gestión de Matrículas
                    </h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaMatriculaModal">
                        <i class="fas fa-plus"></i> Nueva Matrícula
                    </button>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <h6>¡Matrícula Exitosa!</h6>
                            <?= $success_message ?>
                            <?php if (isset($password_temporal)): ?>
                                <hr>
                                <strong>Contraseña temporal generada:</strong> <?= $password_temporal ?>
                                <br><small class="text-muted">Proporcione esta contraseña al estudiante para su primer acceso.</small>
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario de búsqueda -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-search"></i> Buscar Estudiantes Matriculados
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="anio" class="form-label">Año Académico</label>
                                    <select name="anio" id="anio" class="form-select">
                                        <?php foreach ($anos_academicos as $ano): ?>
                                            <option value="<?= $ano['anio'] ?>" <?= (isset($_GET['anio']) && $_GET['anio'] == $ano['anio']) || (!isset($_GET['anio']) && $ano['anio'] == date('Y')) ? 'selected' : '' ?>>
                                                <?= $ano['anio'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="grado" class="form-label">Grado</label>
                                    <select name="grado" id="grado" class="form-select">
                                        <option value="">Todos</option>
                                        <?php foreach ($grados as $grado): ?>
                                            <option value="<?= $grado['numero_grado'] ?>" <?= (isset($_GET['grado']) && $_GET['grado'] == $grado['numero_grado']) ? 'selected' : '' ?>>
                                                <?= $grado['numero_grado'] ?>°
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="nombre" class="form-label">Nombre o Código</label>
                                    <input type="text" name="nombre" id="nombre" class="form-control" 
                                           placeholder="Buscar por nombre o código..." value="<?= $_GET['nombre'] ?? '' ?>">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" name="buscar" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                    <a href="enrollment.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resultados de búsqueda -->
                    <?php if ($filtro_aplicado): ?>
                        <?php if (!empty($estudiantes_matriculados)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Código</th>
                                            <th>DNI</th>
                                            <th>Nombre Completo</th>
                                            <th>Sexo</th>
                                            <th>Grado</th>
                                            <th>Sección</th>
                                            <th>Fecha Matrícula</th>
                                            <th>Estado</th>
                                            <th>Apoderado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($estudiantes_matriculados as $estudiante): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($estudiante['codigo_estudiante']) ?></td>
                                                <td><?= htmlspecialchars($estudiante['dni']) ?></td>
                                                <td><?= htmlspecialchars($estudiante['nombre_completo']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $estudiante['sexo'] === 'MASCULINO' ? 'primary' : 'pink' ?>">
                                                        <?= $estudiante['sexo'] === 'MASCULINO' ? 'M' : 'F' ?>
                                                    </span>
                                                </td>
                                                <td><?= $estudiante['numero_grado'] ?>°</td>
                                                <td><?= $estudiante['letra_seccion'] ?></td>
                                                <td><?= date('d/m/Y', strtotime($estudiante['fecha_matricula'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $estudiante['estado'] === 'ACTIVO' ? 'success' : 'warning' ?>">
                                                        <?= $estudiante['estado'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?= htmlspecialchars($estudiante['apoderado_nombres']) ?><br>
                                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($estudiante['apoderado_celular']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalle('<?= $estudiante['codigo_estudiante'] ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editarMatricula('<?= $estudiante['codigo_estudiante'] ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <p class="text-muted">Total de estudiantes encontrados: <strong><?= count($estudiantes_matriculados) ?></strong></p>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5>No se encontraron estudiantes</h5>
                                <p class="text-muted">No hay estudiantes matriculados que coincidan con los criterios de búsqueda.</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>Sistema de Gestión de Matrículas</h5>
                            <p class="text-muted">Utilice el formulario de búsqueda para ver estudiantes matriculados o cree una nueva matrícula.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Matrícula -->
<div class="modal fade" id="nuevaMatriculaModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Matrícula de Estudiante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Datos del Estudiante -->
                        <div class="col-md-6">
                            <h6 class="mb-3 text-primary">Datos del Estudiante</h6>
                            
                            <div class="mb-3">
                                <label for="codigo_estudiante" class="form-label">Código de Estudiante *</label>
                                <input type="text" name="codigo_estudiante" id="codigo_estudiante" class="form-control" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="apellido_paterno" class="form-label">Apellido Paterno *</label>
                                        <input type="text" name="apellido_paterno" id="apellido_paterno" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="apellido_materno" class="form-label">Apellido Materno *</label>
                                        <input type="text" name="apellido_materno" id="apellido_materno" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="nombres" class="form-label">Nombres *</label>
                                <input type="text" name="nombres" id="nombres" class="form-control" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sexo" class="form-label">Sexo *</label>
                                        <select name="sexo" id="sexo" class="form-select" required>
                                            <option value="">Seleccione...</option>
                                            <option value="MASCULINO">Masculino</option>
                                            <option value="FEMENINO">Femenino</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                                        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Datos del Apoderado -->
                        <div class="col-md-6">
                            <h6 class="mb-3 text-success">Datos del Apoderado</h6>
                            
                            <div class="mb-3">
                                <label for="apoderado_nombres" class="form-label">Nombres del Apoderado *</label>
                                <input type="text" name="apoderado_nombres" id="apoderado_nombres" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="apoderado_parentesco" class="form-label">Parentesco *</label>
                                <select name="apoderado_parentesco" id="apoderado_parentesco" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="PADRE">Padre</option>
                                    <option value="MADRE">Madre</option>
                                    <option value="ABUELO">Abuelo</option>
                                    <option value="ABUELA">Abuela</option>
                                    <option value="TIO">Tío</option>
                                    <option value="TIA">Tía</option>
                                    <option value="TUTOR">Tutor Legal</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="apoderado_celular" class="form-label">Celular *</label>
                                <input type="tel" name="apoderado_celular" id="apoderado_celular" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="apoderado_email" class="form-label">Email</label>
                                <input type="email" name="apoderado_email" id="apoderado_email" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Datos de Matrícula -->
                    <h6 class="mb-3 text-warning">Datos de Matrícula</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="grado_matricula" class="form-label">Grado *</label>
                                <select name="grado" id="grado_matricula" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($grados as $grado): ?>
                                        <option value="<?= $grado['numero_grado'] ?>">
                                            <?= $grado['numero_grado'] ?>° - <?= $grado['descripcion'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="seccion" class="form-label">Sección *</label>
                                <select name="seccion" id="seccion" class="form-select" required>
                                    <option value="">Seleccione grado primero</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="anio_matricula" class="form-label">Año Académico *</label>
                                <select name="anio" id="anio_matricula" class="form-select" required>
                                    <?php foreach ($anos_academicos as $ano): ?>
                                        <option value="<?= $ano['anio'] ?>" <?= $ano['anio'] == date('Y') ? 'selected' : '' ?>>
                                            <?= $ano['anio'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="matricular_estudiante" class="btn btn-primary">
                        <i class="fas fa-save"></i> Matricular Estudiante
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Cargar secciones basadas en el grado seleccionado
document.getElementById('grado_matricula').addEventListener('change', function() {
    const grado = this.value;
    const seccionSelect = document.getElementById('seccion');
    
    // Limpiar opciones
    seccionSelect.innerHTML = '<option value="">Cargando...</option>';
    
    if (grado) {
        fetch(`../api/get_sections.php?grado=${grado}`)
            .then(response => response.json())
            .then(data => {
                seccionSelect.innerHTML = '<option value="">Seleccione sección...</option>';
                data.forEach(seccion => {
                    seccionSelect.innerHTML += `<option value="${seccion.letra_seccion}">${seccion.letra_seccion}</option>`;
                });
            })
            .catch(error => {
                seccionSelect.innerHTML = '<option value="">Error al cargar</option>';
                console.error('Error:', error);
            });
    } else {
        seccionSelect.innerHTML = '<option value="">Seleccione grado primero</option>';
    }
});

function verDetalle(codigoEstudiante) {
    // Implementar vista de detalle
    alert('Vista de detalle en desarrollo para: ' + codigoEstudiante);
}

function editarMatricula(codigoEstudiante) {
    // Implementar edición de matrícula
    alert('Edición de matrícula en desarrollo para: ' + codigoEstudiante);
}
</script>

<?php include '../includes/footer.php'; ?>