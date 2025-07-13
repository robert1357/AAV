<?php
// Verificar si el usuario está logueado
$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['tipo_usuario']);

// Configurar rutas base según la ubicación del archivo
$base_path = '';
if (strpos($_SERVER['REQUEST_URI'], '/auth/') !== false) {
    $base_path = '../';
} elseif (strpos($_SERVER['REQUEST_URI'], '/student/') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/teacher/') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/director/') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/secretaria/') !== false ||
          strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    $base_path = '../';
}

// Solo mostrar navbar si el usuario está logueado
if (!$is_logged_in) {
    return;
}

$user_name = $_SESSION['user_name'] ?? 'Usuario';
$user_type = $_SESSION['tipo_usuario'] ?? '';
$user_type_display = '';

// Mapear tipos de usuario a nombres amigables
switch ($user_type) {
    case 'estudiante':
        $user_type_display = 'Estudiante';
        break;
    case 'docente':
        $user_type_display = 'Docente';
        break;
    case 'director':
        $user_type_display = 'Director';
        break;
    case 'secretaria':
        $user_type_display = 'Secretaría';
        break;
    case 'psicologo':
        $user_type_display = 'Psicólogo(a)';
        break;
    case 'auxiliar_educacion':
        $user_type_display = 'Auxiliar de Educación';
        break;
    case 'coordinador_tutoria':
        $user_type_display = 'Coordinador de Tutoría';
        break;
    case 'jefe_laboratorio':
        $user_type_display = 'Jefe de Laboratorio';
        break;
    default:
        $user_type_display = ucfirst($user_type);
        break;
}

// Configurar menús según el tipo de usuario
$menu_items = [];

switch ($user_type) {
    case 'estudiante':
        $menu_items = [
            ['url' => $base_path . 'student/dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Inicio'],
            ['url' => $base_path . 'student/assignments.php', 'icon' => 'fas fa-tasks', 'text' => 'Mis Tareas'],
            ['url' => $base_path . 'student/grades.php', 'icon' => 'fas fa-star', 'text' => 'Calificaciones'],
            ['url' => $base_path . 'student/materials.php', 'icon' => 'fas fa-folder-open', 'text' => 'Materiales'],
            ['url' => $base_path . 'student/attendance.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Asistencia'],
            ['url' => $base_path . 'student/profile.php', 'icon' => 'fas fa-user', 'text' => 'Mi Perfil']
        ];
        break;

    case 'docente':
        $menu_items = [
            ['url' => $base_path . 'teacher/dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Inicio'],
            ['url' => $base_path . 'teacher/courses.php', 'icon' => 'fas fa-book', 'text' => 'Mis Cursos'],
            ['url' => $base_path . 'teacher/assignments.php', 'icon' => 'fas fa-tasks', 'text' => 'Tareas'],
            ['url' => $base_path . 'teacher/grade_assignments.php', 'icon' => 'fas fa-star', 'text' => 'Calificar'],
            ['url' => $base_path . 'teacher/materials.php', 'icon' => 'fas fa-folder-open', 'text' => 'Materiales'],
            ['url' => $base_path . 'teacher/attendance.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Asistencia'],
            ['url' => $base_path . 'teacher/reports.php', 'icon' => 'fas fa-chart-bar', 'text' => 'Reportes']
        ];
        break;

    case 'director':
        $menu_items = [
            ['url' => $base_path . 'director/dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Panel Principal'],
            ['url' => $base_path . 'director/student_management.php', 'icon' => 'fas fa-user-graduate', 'text' => 'Estudiantes'],
            ['url' => $base_path . 'director/teacher_management.php', 'icon' => 'fas fa-chalkboard-teacher', 'text' => 'Docentes'],
            ['url' => $base_path . 'director/course_management.php', 'icon' => 'fas fa-book', 'text' => 'Cursos'],
            ['url' => $base_path . 'director/section_management.php', 'icon' => 'fas fa-users', 'text' => 'Secciones'],
            ['url' => $base_path . 'director/reports.php', 'icon' => 'fas fa-chart-line', 'text' => 'Reportes'],
            ['url' => $base_path . 'director/settings.php', 'icon' => 'fas fa-cog', 'text' => 'Configuración']
        ];
        break;

    case 'secretaria':
        $menu_items = [
            ['url' => $base_path . 'secretaria/dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Inicio'],
            ['url' => $base_path . 'secretaria/student_enrollment.php', 'icon' => 'fas fa-user-plus', 'text' => 'Matrículas'],
            ['url' => $base_path . 'secretaria/student_search.php', 'icon' => 'fas fa-search', 'text' => 'Buscar Estudiante'],
            ['url' => $base_path . 'secretaria/certificates.php', 'icon' => 'fas fa-certificate', 'text' => 'Certificados'],
            ['url' => $base_path . 'secretaria/constancias.php', 'icon' => 'fas fa-file-alt', 'text' => 'Constancias'],
            ['url' => $base_path . 'secretaria/reports.php', 'icon' => 'fas fa-chart-bar', 'text' => 'Reportes']
        ];
        break;

    default:
        $menu_items = [
            ['url' => $base_path . 'dashboard.php', 'icon' => 'fas fa-home', 'text' => 'Inicio']
        ];
        break;
}

// Obtener la URL actual para marcar el elemento activo
$current_url = $_SERVER['REQUEST_URI'];
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container-fluid">
        <!-- Logo y nombre del sistema -->
        <a class="navbar-brand d-flex align-items-center" href="<?= $base_path ?>index.php">
            <i class="fas fa-school me-2"></i>
            <span class="fw-bold">Aula Virtual</span>
        </a>

        <!-- Toggle button para móviles -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Menú principal -->
            <ul class="navbar-nav me-auto">
                <?php foreach ($menu_items as $item): ?>
                    <?php 
                    $is_active = (strpos($current_url, basename($item['url'])) !== false);
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $is_active ? 'active' : '' ?>" href="<?= $item['url'] ?>">
                            <i class="<?= $item['icon'] ?> me-1"></i>
                            <?= $item['text'] ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Menú de usuario -->
            <ul class="navbar-nav">
                <!-- Notificaciones -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount" style="display: none;">
                            0
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" id="notificationsMenu" style="width: 300px;">
                        <li class="dropdown-header">Notificaciones</li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="px-3 py-2 text-muted text-center" id="noNotifications">
                            No tienes notificaciones nuevas
                        </li>
                    </ul>
                </li>

                <!-- Perfil de usuario -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <div class="d-flex align-items-center">
                            <div class="bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="fas fa-user text-primary"></i>
                            </div>
                            <div class="d-none d-md-block">
                                <div class="fw-medium"><?= htmlspecialchars($user_name) ?></div>
                                <small class="text-light opacity-75"><?= $user_type_display ?></small>
                            </div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <div class="fw-bold"><?= htmlspecialchars($user_name) ?></div>
                            <small class="text-muted"><?= $user_type_display ?></small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <?php if ($user_type === 'estudiante'): ?>
                            <li><a class="dropdown-item" href="<?= $base_path ?>student/profile.php">
                                <i class="fas fa-user me-2"></i>Mi Perfil
                            </a></li>
                        <?php elseif (in_array($user_type, ['docente', 'director'])): ?>
                            <li><a class="dropdown-item" href="<?= $base_path ?>teacher/profile.php">
                                <i class="fas fa-user me-2"></i>Mi Perfil
                            </a></li>
                        <?php endif; ?>
                        
                        <li><a class="dropdown-item" href="<?= $base_path ?>settings.php">
                            <i class="fas fa-cog me-2"></i>Configuración
                        </a></li>
                        
                        <?php if (in_array($user_type, ['director', 'secretaria'])): ?>
                            <li><a class="dropdown-item" href="<?= $base_path ?>help.php">
                                <i class="fas fa-question-circle me-2"></i>Ayuda
                            </a></li>
                        <?php endif; ?>
                        
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= $base_path ?>auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Breadcrumb -->
<?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
<nav aria-label="breadcrumb" class="bg-light border-bottom">
    <div class="container-fluid">
        <ol class="breadcrumb mb-0 py-2">
            <li class="breadcrumb-item">
                <a href="<?= $base_path ?>index.php" class="text-decoration-none">
                    <i class="fas fa-home"></i> Inicio
                </a>
            </li>
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index === count($breadcrumbs) - 1): ?>
                    <li class="breadcrumb-item active" aria-current="page">
                        <?= htmlspecialchars($crumb['text']) ?>
                    </li>
                <?php else: ?>
                    <li class="breadcrumb-item">
                        <a href="<?= $crumb['url'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($crumb['text']) ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </div>
</nav>
<?php endif; ?>

<style>
.navbar-brand {
    font-size: 1.1rem;
}

.nav-link {
    transition: all 0.3s ease;
    border-radius: 5px;
    margin: 0 2px;
}

.nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
    transform: translateY(-1px);
}

.nav-link.active {
    background-color: rgba(255, 255, 255, 0.2);
    font-weight: 500;
}

.dropdown-menu {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border-radius: 8px;
}

.dropdown-item {
    transition: all 0.3s ease;
    border-radius: 4px;
    margin: 2px 4px;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.badge {
    font-size: 0.6em;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    font-weight: bold;
}

@media (max-width: 768px) {
    .navbar-brand span {
        display: none;
    }
    
    .nav-link {
        margin: 2px 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cargar notificaciones
    loadNotifications();
    
    // Actualizar notificaciones cada 30 segundos
    setInterval(loadNotifications, 30000);
});

async function loadNotifications() {
    try {
        const response = await fetch('<?= $base_path ?>api/get_notifications.php');
        const data = await response.json();
        
        if (data.success) {
            updateNotificationUI(data.notifications);
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

function updateNotificationUI(notifications) {
    const notificationCount = document.getElementById('notificationCount');
    const notificationsMenu = document.getElementById('notificationsMenu');
    const noNotifications = document.getElementById('noNotifications');
    
    if (notifications && notifications.length > 0) {
        // Mostrar contador
        notificationCount.textContent = notifications.length;
        notificationCount.style.display = 'inline';
        
        // Ocultar mensaje "sin notificaciones"
        noNotifications.style.display = 'none';
        
        // Limpiar notificaciones anteriores (excepto header y divider)
        const existingNotifications = notificationsMenu.querySelectorAll('.notification-item');
        existingNotifications.forEach(item => item.remove());
        
        // Agregar nuevas notificaciones
        notifications.forEach(notification => {
            const notificationItem = document.createElement('li');
            notificationItem.className = 'notification-item';
            notificationItem.innerHTML = `
                <a class="dropdown-item py-2" href="#">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-${getNotificationIcon(notification.type)} text-primary me-2 mt-1"></i>
                        <div class="flex-grow-1">
                            <div class="fw-medium">${notification.title}</div>
                            <small class="text-muted">${notification.message}</small>
                            <div class="text-muted" style="font-size: 0.7rem;">
                                ${Utils.formatDate(notification.created_at)}
                            </div>
                        </div>
                    </div>
                </a>
            `;
            
            // Insertar antes del "no notifications" item
            notificationsMenu.insertBefore(notificationItem, noNotifications);
        });
    } else {
        // Ocultar contador
        notificationCount.style.display = 'none';
        
        // Mostrar mensaje "sin notificaciones"
        noNotifications.style.display = 'block';
        
        // Limpiar notificaciones existentes
        const existingNotifications = notificationsMenu.querySelectorAll('.notification-item');
        existingNotifications.forEach(item => item.remove());
    }
}

function getNotificationIcon(type) {
    const iconMap = {
        'tarea': 'tasks',
        'calificacion': 'star',
        'material': 'folder-open',
        'asistencia': 'calendar-check',
        'sistema': 'cog'
    };
    
    return iconMap[type] || 'bell';
}
</script>