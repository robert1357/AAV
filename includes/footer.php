    <!-- Toast container for notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer">
        <!-- Toasts will be inserted here dynamically -->
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage">¿Estás seguro de que deseas realizar esta acción?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmAction">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to top button -->
    <button type="button" class="btn btn-primary btn-floating btn-lg position-fixed bottom-0 end-0 m-3 d-none" id="backToTop" style="z-index: 1000;">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (solo si no se cargó antes) -->
    <?php if (!isset($include_datatables) || !$include_datatables): ?>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <?php endif; ?>
    
    <!-- Scripts personalizados globales -->
    <script>
        // Variables globales
        window.APP = {
            baseUrl: '<?= isset($base_url) ? $base_url : "../" ?>',
            currentUser: {
                id: <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>,
                type: '<?= isset($_SESSION['tipo_usuario']) ? $_SESSION['tipo_usuario'] : '' ?>',
                name: '<?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '' ?>'
            }
        };

        // Utilidades globales
        class Utils {
            // Mostrar toast notification
            static showToast(message, type = 'info', duration = 5000) {
                const toastId = 'toast_' + Date.now();
                const iconMap = {
                    'success': 'fa-check-circle',
                    'error': 'fa-exclamation-triangle',
                    'warning': 'fa-exclamation-circle',
                    'info': 'fa-info-circle'
                };
                
                const toastHtml = `
                    <div class="toast align-items-center text-bg-${type === 'error' ? 'danger' : type} border-0" 
                         role="alert" id="${toastId}" data-bs-autohide="true" data-bs-delay="${duration}">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas ${iconMap[type] || iconMap.info} me-2"></i>
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                                    data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `;
                
                document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);
                const toastElement = document.getElementById(toastId);
                const toast = new bootstrap.Toast(toastElement);
                toast.show();
                
                // Remove from DOM after hiding
                toastElement.addEventListener('hidden.bs.toast', () => {
                    toastElement.remove();
                });
            }

            // Mostrar modal de confirmación
            static showConfirmDialog(message, onConfirm, title = 'Confirmar Acción') {
                const modal = document.getElementById('confirmModal');
                const modalTitle = modal.querySelector('.modal-title');
                const confirmMessage = document.getElementById('confirmMessage');
                const confirmButton = document.getElementById('confirmAction');
                
                modalTitle.textContent = title;
                confirmMessage.textContent = message;
                
                // Remove existing event listeners
                const newConfirmButton = confirmButton.cloneNode(true);
                confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);
                
                // Add new event listener
                newConfirmButton.addEventListener('click', () => {
                    bootstrap.Modal.getInstance(modal).hide();
                    if (typeof onConfirm === 'function') {
                        onConfirm();
                    }
                });
                
                new bootstrap.Modal(modal).show();
            }

            // Formatear números
            static formatNumber(num, decimals = 0) {
                return new Intl.NumberFormat('es-PE', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                }).format(num);
            }

            // Formatear fechas
            static formatDate(dateString, format = 'dd/mm/yyyy') {
                const date = new Date(dateString);
                const options = {};
                
                if (format === 'dd/mm/yyyy') {
                    return date.toLocaleDateString('es-PE');
                } else if (format === 'dd/mm/yyyy HH:mm') {
                    return date.toLocaleString('es-PE');
                }
                
                return date.toLocaleDateString('es-PE');
            }

            // Loading overlay
            static showLoading() {
                document.getElementById('loadingOverlay').classList.remove('d-none');
            }

            static hideLoading() {
                document.getElementById('loadingOverlay').classList.add('d-none');
            }

            // AJAX helper
            static async makeRequest(url, options = {}) {
                try {
                    const defaultOptions = {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    };

                    const response = await fetch(url, { ...defaultOptions, ...options });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.indexOf('application/json') !== -1) {
                        return await response.json();
                    } else {
                        return await response.text();
                    }
                } catch (error) {
                    console.error('Request failed:', error);
                    throw error;
                }
            }

            // Debounce function
            static debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // Copy text to clipboard
            static async copyToClipboard(text) {
                try {
                    await navigator.clipboard.writeText(text);
                    this.showToast('Texto copiado al portapapeles', 'success');
                } catch (err) {
                    console.error('Failed to copy: ', err);
                    this.showToast('Error al copiar texto', 'error');
                }
            }
        }

        // DOM Ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });

            // Back to top button
            const backToTopButton = document.getElementById('backToTop');
            if (backToTopButton) {
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        backToTopButton.classList.remove('d-none');
                    } else {
                        backToTopButton.classList.add('d-none');
                    }
                });

                backToTopButton.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            // Auto-resize textareas
            const textareas = document.querySelectorAll('textarea[data-auto-resize]');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });

            // Form validation enhancement
            const forms = document.querySelectorAll('.needs-validation');
            forms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                        
                        // Focus on first invalid field
                        const firstInvalidField = form.querySelector(':invalid');
                        if (firstInvalidField) {
                            firstInvalidField.focus();
                        }
                    }
                    form.classList.add('was-validated');
                });
            });

            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.parentNode) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, parseInt(alert.dataset.autoDismiss) || 5000);
            });

            // Hide loading overlay
            Utils.hideLoading();
        });

        // Session timeout warning
        <?php if (isset($_SESSION['user_id'])): ?>
        let sessionTimeout;
        let warningTimeout;

        function resetSessionTimer() {
            clearTimeout(sessionTimeout);
            clearTimeout(warningTimeout);
            
            // Warning 2 minutes before expiry
            warningTimeout = setTimeout(() => {
                Utils.showToast('Tu sesión expirará en 2 minutos', 'warning', 10000);
            }, <?= (ini_get('session.gc_maxlifetime') - 120) * 1000 ?>);
            
            // Session expiry
            sessionTimeout = setTimeout(() => {
                Utils.showToast('Tu sesión ha expirado. Serás redirigido al login.', 'error');
                setTimeout(() => {
                    window.location.href = '<?= isset($base_url) ? $base_url : "../" ?>auth/login.php?message=session_expired';
                }, 3000);
            }, <?= ini_get('session.gc_maxlifetime') * 1000 ?>);
        }

        // Reset timer on user activity
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetSessionTimer, true);
        });

        // Initialize session timer
        resetSessionTimer();
        <?php endif; ?>

        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
            // Only show error to user in development
            <?php if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1'): ?>
            Utils.showToast('Error del sistema: ' + e.message, 'error');
            <?php endif; ?>
        });

        // Service Worker registration (if available)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= isset($base_url) ? $base_url : "../" ?>sw.js')
                .then(registration => {
                    console.log('SW registered');
                })
                .catch(error => {
                    console.log('SW registration failed');
                });
        }
    </script>

    <!-- Scripts adicionales específicos de página -->
    <?php if (isset($additional_js)): ?>
        <?= $additional_js ?>
    <?php endif; ?>

    <!-- Analytics (solo en producción) -->
    <?php if ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1'): ?>
    <!-- Google Analytics o similar aquí -->
    <?php endif; ?>

</body>
</html>