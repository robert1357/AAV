<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Aula Virtual' ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= isset($page_description) ? htmlspecialchars($page_description) : 'Sistema de gestión educativa integral para estudiantes, docentes y personal administrativo' ?>">
    <meta name="keywords" content="aula virtual, educación, estudiantes, docentes, calificaciones, tareas, asistencia">
    <meta name="author" content="Sistema Aula Virtual">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= isset($base_url) ? $base_url : '../' ?>assets/images/favicon.ico">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js (solo cargar si es necesario) -->
    <?php if (isset($include_charts) && $include_charts): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    
    <!-- DataTables (solo cargar si es necesario) -->
    <?php if (isset($include_datatables) && $include_datatables): ?>
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <?php endif; ?>
    
    <!-- Estilos personalizados globales -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            margin-left: -250px;
        }

        .main-content {
            transition: all 0.3s ease;
            margin-left: 0;
        }

        .main-content.expanded {
            margin-left: 250px;
        }

        .navbar-brand {
            font-weight: 600;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .btn {
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
        }

        .form-control, .form-select {
            border-radius: 6px;
            border: 1.5px solid #e3e6f0;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .alert {
            border: none;
            border-radius: 8px;
            font-weight: 500;
        }

        .badge {
            font-weight: 500;
            border-radius: 4px;
        }

        .table {
            border-radius: 8px;
            overflow: hidden;
        }

        .table thead th {
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        /* Loading Spinner */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        /* Tooltips */
        .tooltip {
            font-size: 0.8rem;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Responsive utilities */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 1000;
                width: 250px;
                margin-left: -250px;
            }

            .sidebar.show {
                margin-left: 0;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
                display: none;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        .slide-in-up {
            animation: slideInUp 0.6s ease-out;
        }

        /* Status badges */
        .status-active { background-color: #28a745 !important; }
        .status-inactive { background-color: #6c757d !important; }
        .status-pending { background-color: #ffc107 !important; }
        .status-cancelled { background-color: #dc3545 !important; }

        /* Grade colors */
        .grade-excellent { color: #28a745; font-weight: 600; }
        .grade-good { color: #17a2b8; font-weight: 600; }
        .grade-average { color: #ffc107; font-weight: 600; }
        .grade-poor { color: #fd7e14; font-weight: 600; }
        .grade-fail { color: #dc3545; font-weight: 600; }

        /* Print styles */
        @media print {
            .sidebar, .navbar, .btn, .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
            }
        }
    </style>
    
    <!-- Estilos adicionales específicos de página -->
    <?php if (isset($additional_css)): ?>
        <?= $additional_css ?>
    <?php endif; ?>
</head>
<body>
    <!-- Loading overlay -->
    <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="background: rgba(255, 255, 255, 0.9); z-index: 9999;">
        <div class="d-flex justify-content-center align-items-center h-100">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <div class="mt-2">Cargando...</div>
            </div>
        </div>
    </div>