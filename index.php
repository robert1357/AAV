<?php
session_start();

// Si el usuario ya está logueado, redirigir al dashboard correspondiente
if (isset($_SESSION['user_id']) && isset($_SESSION['tipo_usuario'])) {
    switch ($_SESSION['tipo_usuario']) {
        case 'estudiante':
            header('Location: student/dashboard.php');
            break;
        case 'docente':
            header('Location: teacher/dashboard.php');
            break;
        case 'director':
            header('Location: director/dashboard.php');
            break;
        case 'secretaria':
            header('Location: secretaria/dashboard.php');
            break;
        case 'psicologo':
            header('Location: psicologa/dashboard.php');
            break;
        case 'auxiliar_educacion':
            header('Location: auxiliar_educacion/dashboard.php');
            break;
        case 'coordinador_tutoria':
            header('Location: coordinador_tutoria/dashboard.php');
            break;
        case 'jefe_laboratorio':
            header('Location: jefe_laboratorio/dashboard.php');
            break;
        default:
            header('Location: auth/login.php');
            break;
    }
    exit();
}

$page_title = "Bienvenido al Aula Virtual";
include 'includes/header.php';
?>

<div class="min-vh-100 d-flex align-items-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-5">
                            <div class="mb-4">
                                <i class="fas fa-school fa-4x text-primary"></i>
                            </div>
                            <h1 class="h2 mb-3">Sistema Aula Virtual</h1>
                            <p class="text-muted">
                                Plataforma integral de gestión educativa para estudiantes, docentes y personal administrativo.
                            </p>
                        </div>

                        <div class="row text-center mb-4">
                            <div class="col-4">
                                <div class="p-3">
                                    <i class="fas fa-user-graduate fa-2x text-primary mb-2"></i>
                                    <h6 class="mb-0">Estudiantes</h6>
                                    <small class="text-muted">Acceso a tareas y calificaciones</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3">
                                    <i class="fas fa-chalkboard-teacher fa-2x text-success mb-2"></i>
                                    <h6 class="mb-0">Docentes</h6>
                                    <small class="text-muted">Gestión de cursos y evaluaciones</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3">
                                    <i class="fas fa-users-cog fa-2x text-info mb-2"></i>
                                    <h6 class="mb-0">Administración</h6>
                                    <small class="text-muted">Control total del sistema</small>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-3">
                            <a href="auth/login.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Iniciar Sesión
                            </a>
                            
                            <div class="text-center">
                                <small class="text-muted">
                                    ¿Necesitas ayuda? Contacta con la administración
                                </small>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row text-center">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">Funcionalidades Principales</h6>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-3">
                                        <i class="fas fa-tasks text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Gestión de Tareas</h6>
                                        <small class="text-muted">Asignación y entrega</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-3">
                                        <i class="fas fa-star text-warning"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Calificaciones</h6>
                                        <small class="text-muted">Seguimiento académico</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-3">
                                        <i class="fas fa-calendar-check text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Control de Asistencia</h6>
                                        <small class="text-muted">Registro diario</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-3">
                                        <i class="fas fa-folder-open text-info"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Materiales de Estudio</h6>
                                        <small class="text-muted">Recursos educativos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-3">
                                        <i class="fas fa-chart-line text-danger"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Reportes y Estadísticas</h6>
                                        <small class="text-muted">Análisis de rendimiento</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-3">
                                        <i class="fas fa-shield-alt text-secondary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Seguridad</h6>
                                        <small class="text-muted">Acceso controlado</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Sistema Aula Virtual v1.0 • 
                                <i class="fas fa-shield-alt text-success"></i> Seguro y confiable
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.95);
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.bg-light {
    background-color: #f8f9fa !important;
}

.rounded-circle {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.text-primary { color: #667eea !important; }
.text-success { color: #28a745 !important; }
.text-info { color: #17a2b8 !important; }
.text-warning { color: #ffc107 !important; }
.text-danger { color: #dc3545 !important; }
.text-secondary { color: #6c757d !important; }

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .card-body {
        padding: 2rem !important;
    }
    
    .fa-4x {
        font-size: 2.5rem !important;
    }
}

/* Animación de entrada */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.6s ease-out;
}

/* Efectos hover */
.d-flex:hover .rounded-circle {
    transform: scale(1.1);
    transition: transform 0.2s ease;
}
</style>

<?php include 'includes/footer.php'; ?>