<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'JEFE_LABORATORIO') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Gestión de Equipos - Jefe de Laboratorio";

// Procesar registro de equipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_equipo'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO equipos_laboratorio (
                nombre, codigo_inventario, marca, modelo, serie, 
                categoria, ubicacion, estado, fecha_adquisicion, 
                valor_compra, proveedor, garantia_hasta, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['nombre'],
            $_POST['codigo_inventario'],
            $_POST['marca'],
            $_POST['modelo'],
            $_POST['serie'],
            $_POST['categoria'],
            $_POST['ubicacion'],
            $_POST['estado'],
            $_POST['fecha_adquisicion'],
            $_POST['valor_compra'],
            $_POST['proveedor'],
            $_POST['garantia_hasta'] ?: null,
            $_POST['observaciones']
        ]);
        
        $pdo->commit();
        $success_message = "Equipo registrado exitosamente";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al registrar equipo: " . $e->getMessage();
    }
}

// Procesar mantenimiento de equipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_mantenimiento'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO mantenimientos_equipo (
                id_equipo, tipo_mantenimiento, fecha_mantenimiento, 
                descripcion_trabajo, costo, responsable, 
                proximo_mantenimiento, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['id_equipo'],
            $_POST['tipo_mantenimiento'],
            $_POST['fecha_mantenimiento'],
            $_POST['descripcion_trabajo'],
            $_POST['costo'],
            $_POST['responsable'],
            $_POST['proximo_mantenimiento'] ?: null,
            $_POST['observaciones_mantenimiento']
        ]);
        
        // Actualizar estado del equipo si es necesario
        if ($_POST['nuevo_estado_equipo']) {
            $stmt = $pdo->prepare("UPDATE equipos_laboratorio SET estado = ? WHERE id_equipo = ?");
            $stmt->execute([$_POST['nuevo_estado_equipo'], $_POST['id_equipo']]);
        }
        
        $pdo->commit();
        $success_message = "Mantenimiento registrado exitosamente";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error al registrar mantenimiento: " . $e->getMessage();
    }
}

// Obtener equipos
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_ubicacion = $_GET['ubicacion'] ?? '';

$sql = "SELECT * FROM equipos_laboratorio WHERE 1=1";
$params = [];

if ($filtro_categoria) {
    $sql .= " AND categoria = ?";
    $params[] = $filtro_categoria;
}

if ($filtro_estado) {
    $sql .= " AND estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_ubicacion) {
    $sql .= " AND ubicacion LIKE ?";
    $params[] = '%' . $filtro_ubicacion . '%';
}

$sql .= " ORDER BY categoria, nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll();

// Obtener estadísticas
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_equipos,
        COUNT(CASE WHEN estado = 'OPERATIVO' THEN 1 END) as operativos,
        COUNT(CASE WHEN estado = 'MANTENIMIENTO' THEN 1 END) as en_mantenimiento,
        COUNT(CASE WHEN estado = 'DAÑADO' THEN 1 END) as dañados,
        COUNT(CASE WHEN estado = 'FUERA_SERVICIO' THEN 1 END) as fuera_servicio
    FROM equipos_laboratorio
");
$estadisticas = $stmt->fetch();

// Obtener equipos que requieren mantenimiento próximo
$stmt = $pdo->query("
    SELECT e.*, m.proximo_mantenimiento
    FROM equipos_laboratorio e
    LEFT JOIN mantenimientos_equipo m ON e.id_equipo = m.id_equipo
    WHERE m.proximo_mantenimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND m.proximo_mantenimiento >= CURDATE()
    ORDER BY m.proximo_mantenimiento ASC
");
$mantenimientos_proximos = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-cogs"></i> Gestión de Equipos de Laboratorio
                    </h3>
                    <div>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevoEquipoModal">
                            <i class="fas fa-plus"></i> Nuevo Equipo
                        </button>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#mantenimientoModal">
                            <i class="fas fa-tools"></i> Registrar Mantenimiento
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $success_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Estado General de Equipos</h6>
                                    <div class="row text-center">
                                        <div class="col-md-2">
                                            <h4 class="text-primary"><?= $estadisticas['total_equipos'] ?></h4>
                                            <small class="text-muted">Total Equipos</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h4 class="text-success"><?= $estadisticas['operativos'] ?></h4>
                                            <small class="text-muted">Operativos</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h4 class="text-warning"><?= $estadisticas['en_mantenimiento'] ?></h4>
                                            <small class="text-muted">En Mantenimiento</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h4 class="text-danger"><?= $estadisticas['dañados'] ?></h4>
                                            <small class="text-muted">Dañados</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h4 class="text-secondary"><?= $estadisticas['fuera_servicio'] ?></h4>
                                            <small class="text-muted">Fuera de Servicio</small>
                                        </div>
                                        <div class="col-md-2">
                                            <h4 class="text-info"><?= count($mantenimientos_proximos) ?></h4>
                                            <small class="text-muted">Mantenimiento Próximo</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="categoria" class="form-label">Categoría</label>
                                    <select name="categoria" id="categoria" class="form-select">
                                        <option value="">Todas las categorías</option>
                                        <option value="MICROSCOPIO" <?= $filtro_categoria === 'MICROSCOPIO' ? 'selected' : '' ?>>Microscopios</option>
                                        <option value="BALANZA" <?= $filtro_categoria === 'BALANZA' ? 'selected' : '' ?>>Balanzas</option>
                                        <option value="QUIMICO" <?= $filtro_categoria === 'QUIMICO' ? 'selected' : '' ?>>Equipos Químicos</option>
                                        <option value="FISICO" <?= $filtro_categoria === 'FISICO' ? 'selected' : '' ?>>Equipos Físicos</option>
                                        <option value="INFORMATICO" <?= $filtro_categoria === 'INFORMATICO' ? 'selected' : '' ?>>Equipos Informáticos</option>
                                        <option value="MEDICION" <?= $filtro_categoria === 'MEDICION' ? 'selected' : '' ?>>Instrumentos de Medición</option>
                                        <option value="OTRO" <?= $filtro_categoria === 'OTRO' ? 'selected' : '' ?>>Otros</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="estado" class="form-label">Estado</label>
                                    <select name="estado" id="estado" class="form-select">
                                        <option value="">Todos los estados</option>
                                        <option value="OPERATIVO" <?= $filtro_estado === 'OPERATIVO' ? 'selected' : '' ?>>Operativo</option>
                                        <option value="MANTENIMIENTO" <?= $filtro_estado === 'MANTENIMIENTO' ? 'selected' : '' ?>>En Mantenimiento</option>
                                        <option value="DAÑADO" <?= $filtro_estado === 'DAÑADO' ? 'selected' : '' ?>>Dañado</option>
                                        <option value="FUERA_SERVICIO" <?= $filtro_estado === 'FUERA_SERVICIO' ? 'selected' : '' ?>>Fuera de Servicio</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="ubicacion" class="form-label">Ubicación</label>
                                    <input type="text" name="ubicacion" id="ubicacion" class="form-control" 
                                           placeholder="Buscar por ubicación..." value="<?= htmlspecialchars($filtro_ubicacion) ?>">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter"></i> Filtrar
                                    </button>
                                    <a href="equipment_management.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de equipos -->
                    <?php if (!empty($equipos)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Código</th>
                                        <th>Nombre</th>
                                        <th>Marca/Modelo</th>
                                        <th>Categoría</th>
                                        <th>Ubicación</th>
                                        <th>Estado</th>
                                        <th>Último Mantenimiento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($equipos as $equipo): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($equipo['codigo_inventario']) ?></strong></td>
                                            <td><?= htmlspecialchars($equipo['nombre']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($equipo['marca']) ?>
                                                <?php if ($equipo['modelo']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($equipo['modelo']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= getCategoriaTexto($equipo['categoria']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($equipo['ubicacion']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= getEstadoBadgeClass($equipo['estado']) ?>">
                                                    <?= getEstadoTexto($equipo['estado']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $stmt_ultimo = $pdo->prepare("SELECT MAX(fecha_mantenimiento) as ultimo FROM mantenimientos_equipo WHERE id_equipo = ?");
                                                $stmt_ultimo->execute([$equipo['id_equipo']]);
                                                $ultimo_mant = $stmt_ultimo->fetchColumn();
                                                ?>
                                                <?= $ultimo_mant ? date('d/m/Y', strtotime($ultimo_mant)) : 'Nunca' ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="verDetalle(<?= $equipo['id_equipo'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning" onclick="registrarMantenimiento(<?= $equipo['id_equipo'] ?>, '<?= htmlspecialchars($equipo['nombre']) ?>')">
                                                        <i class="fas fa-tools"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="verHistorialMantenimiento(<?= $equipo['id_equipo'] ?>)">
                                                        <i class="fas fa-history"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-cogs fa-3x text-muted mb-3"></i>
                            <h5>No hay equipos registrados</h5>
                            <p class="text-muted">Comience registrando el primer equipo del laboratorio.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Equipo -->
<div class="modal fade" id="nuevoEquipoModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Nuevo Equipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre del Equipo *</label>
                                <input type="text" name="nombre" id="nombre" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="codigo_inventario" class="form-label">Código de Inventario *</label>
                                <input type="text" name="codigo_inventario" id="codigo_inventario" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="marca" class="form-label">Marca</label>
                                <input type="text" name="marca" id="marca" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="modelo" class="form-label">Modelo</label>
                                <input type="text" name="modelo" id="modelo" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="serie" class="form-label">Número de Serie</label>
                                <input type="text" name="serie" id="serie" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="categoria" class="form-label">Categoría *</label>
                                <select name="categoria" id="categoria" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="MICROSCOPIO">Microscopios</option>
                                    <option value="BALANZA">Balanzas</option>
                                    <option value="QUIMICO">Equipos Químicos</option>
                                    <option value="FISICO">Equipos Físicos</option>
                                    <option value="INFORMATICO">Equipos Informáticos</option>
                                    <option value="MEDICION">Instrumentos de Medición</option>
                                    <option value="OTRO">Otros</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="ubicacion" class="form-label">Ubicación *</label>
                                <input type="text" name="ubicacion" id="ubicacion" class="form-control" required
                                       placeholder="Ej: Lab. Química - Mesa 3">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="estado" class="form-label">Estado Inicial *</label>
                                <select name="estado" id="estado" class="form-select" required>
                                    <option value="OPERATIVO">Operativo</option>
                                    <option value="MANTENIMIENTO">En Mantenimiento</option>
                                    <option value="DAÑADO">Dañado</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="fecha_adquisicion" class="form-label">Fecha de Adquisición</label>
                                <input type="date" name="fecha_adquisicion" id="fecha_adquisicion" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="valor_compra" class="form-label">Valor de Compra</label>
                                <input type="number" name="valor_compra" id="valor_compra" class="form-control" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="garantia_hasta" class="form-label">Garantía Hasta</label>
                                <input type="date" name="garantia_hasta" id="garantia_hasta" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="proveedor" class="form-label">Proveedor</label>
                        <input type="text" name="proveedor" id="proveedor" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="registrar_equipo" class="btn btn-success">
                        <i class="fas fa-save"></i> Registrar Equipo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Mantenimiento -->
<div class="modal fade" id="mantenimientoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Mantenimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="id_equipo_mant" class="form-label">Equipo *</label>
                        <select name="id_equipo" id="id_equipo_mant" class="form-select" required>
                            <option value="">Seleccione un equipo...</option>
                            <?php foreach ($equipos as $equipo): ?>
                                <option value="<?= $equipo['id_equipo'] ?>">
                                    [<?= $equipo['codigo_inventario'] ?>] <?= htmlspecialchars($equipo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tipo_mantenimiento" class="form-label">Tipo de Mantenimiento *</label>
                                <select name="tipo_mantenimiento" id="tipo_mantenimiento" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="PREVENTIVO">Preventivo</option>
                                    <option value="CORRECTIVO">Correctivo</option>
                                    <option value="CALIBRACION">Calibración</option>
                                    <option value="LIMPIEZA">Limpieza</option>
                                    <option value="REPARACION">Reparación</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fecha_mantenimiento" class="form-label">Fecha de Mantenimiento *</label>
                                <input type="date" name="fecha_mantenimiento" id="fecha_mantenimiento" 
                                       class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion_trabajo" class="form-label">Descripción del Trabajo Realizado *</label>
                        <textarea name="descripcion_trabajo" id="descripcion_trabajo" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="costo" class="form-label">Costo</label>
                                <input type="number" name="costo" id="costo" class="form-control" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="responsable" class="form-label">Responsable</label>
                                <input type="text" name="responsable" id="responsable" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="proximo_mantenimiento" class="form-label">Próximo Mantenimiento</label>
                                <input type="date" name="proximo_mantenimiento" id="proximo_mantenimiento" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nuevo_estado_equipo" class="form-label">Nuevo Estado del Equipo</label>
                        <select name="nuevo_estado_equipo" id="nuevo_estado_equipo" class="form-select">
                            <option value="">No cambiar estado</option>
                            <option value="OPERATIVO">Operativo</option>
                            <option value="MANTENIMIENTO">En Mantenimiento</option>
                            <option value="DAÑADO">Dañado</option>
                            <option value="FUERA_SERVICIO">Fuera de Servicio</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones_mantenimiento" class="form-label">Observaciones</label>
                        <textarea name="observaciones_mantenimiento" id="observaciones_mantenimiento" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="registrar_mantenimiento" class="btn btn-warning">
                        <i class="fas fa-tools"></i> Registrar Mantenimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function verDetalle(idEquipo) {
    fetch(`../api/get_equipment_details.php?id=${idEquipo}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Vista de detalle en desarrollo');
            } else {
                alert('Error al cargar los detalles');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los detalles');
        });
}

function registrarMantenimiento(idEquipo, nombreEquipo) {
    document.getElementById('id_equipo_mant').value = idEquipo;
    const modalTitle = document.querySelector('#mantenimientoModal .modal-title');
    modalTitle.innerHTML = 'Registrar Mantenimiento - ' + nombreEquipo;
    
    new bootstrap.Modal(document.getElementById('mantenimientoModal')).show();
}

function verHistorialMantenimiento(idEquipo) {
    window.open(`../reports/equipment_maintenance.php?equipo=${idEquipo}`, '_blank');
}
</script>

<?php
// Funciones auxiliares
function getCategoriaTexto($categoria) {
    $categorias = [
        'MICROSCOPIO' => 'Microscopios',
        'BALANZA' => 'Balanzas',
        'QUIMICO' => 'Químicos',
        'FISICO' => 'Físicos',
        'INFORMATICO' => 'Informáticos',
        'MEDICION' => 'Medición',
        'OTRO' => 'Otros'
    ];
    return $categorias[$categoria] ?? $categoria;
}

function getEstadoBadgeClass($estado) {
    switch ($estado) {
        case 'OPERATIVO': return 'success';
        case 'MANTENIMIENTO': return 'warning';
        case 'DAÑADO': return 'danger';
        case 'FUERA_SERVICIO': return 'secondary';
        default: return 'secondary';
    }
}

function getEstadoTexto($estado) {
    $estados = [
        'OPERATIVO' => 'Operativo',
        'MANTENIMIENTO' => 'Mantenimiento',
        'DAÑADO' => 'Dañado',
        'FUERA_SERVICIO' => 'Fuera de Servicio'
    ];
    return $estados[$estado] ?? $estado;
}

include '../includes/footer.php'; 
?>