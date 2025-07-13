/**
 * JavaScript principal del sistema de aula virtual
 */

// Inicialización cuando el DOM esté listo
$(document).ready(function() {
    // Inicializar tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Inicializar popovers
    $('[data-bs-toggle="popover"]').popover();
    
    // Auto-cerrar alertas después de 5 segundos
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
    
    // Confirmar eliminaciones
    $('.btn-delete').on('click', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        const message = $(this).data('confirm') || '¿Está seguro de que desea eliminar este elemento?';
        
        if (confirm(message)) {
            window.location.href = url;
        }
    });
    
    // Sidebar toggle para móviles
    $('#sidebarToggle').on('click', function() {
        $('.sidebar').toggleClass('show');
    });
    
    // Cerrar sidebar al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sidebar, #sidebarToggle').length) {
            $('.sidebar').removeClass('show');
        }
    });
    
    // Validación de formularios
    $('form').on('submit', function(e) {
        const form = this;
        
        // Validar campos requeridos
        const requiredFields = $(form).find('[required]');
        let isValid = true;
        
        requiredFields.each(function() {
            const field = $(this);
            const value = field.val().trim();
            
            if (!value) {
                isValid = false;
                field.addClass('is-invalid');
                
                // Mostrar mensaje de error
                if (!field.next('.invalid-feedback').length) {
                    field.after('<div class="invalid-feedback">Este campo es obligatorio</div>');
                }
            } else {
                field.removeClass('is-invalid');
                field.next('.invalid-feedback').remove();
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showAlert('Por favor complete todos los campos requeridos', 'error');
        }
    });
    
    // Limpiar validación al escribir
    $('input, textarea, select').on('input change', function() {
        $(this).removeClass('is-invalid');
        $(this).next('.invalid-feedback').remove();
    });
    
    // Subida de archivos con preview
    $('.file-upload').on('change', function() {
        const file = this.files[0];
        const preview = $(this).closest('.form-group').find('.file-preview');
        
        if (file) {
            const fileName = file.name;
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            
            preview.html(`
                <div class="file-info">
                    <i class="fas fa-file"></i>
                    <span class="file-name">${fileName}</span>
                    <span class="file-size">(${fileSize} MB)</span>
                </div>
            `).show();
        } else {
            preview.hide();
        }
    });
    
    // Busqueda en tiempo real
    $('.search-input').on('input', function() {
        const query = $(this).val().toLowerCase();
        const target = $(this).data('target');
        
        $(target).each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(query));
        });
    });
    
    // Cargar notificaciones
    loadNotifications();
    
    // Actualizar notificaciones cada 30 segundos
    setInterval(loadNotifications, 30000);
    
    // Marcar notificación como leída
    $(document).on('click', '.notification-item', function() {
        const notificationId = $(this).data('id');
        markNotificationAsRead(notificationId);
    });
    
    // Efectos de animación
    $('.fade-in').css('opacity', '0').animate({opacity: 1}, 600);
    
    // Smooth scroll para enlaces internos
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $(this.hash);
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });
});

// Función para mostrar alertas
function showAlert(message, type = 'info') {
    const alertClass = type === 'error' ? 'alert-danger' : `alert-${type}`;
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insertar al inicio del container
    $('.container-fluid').prepend(alertHtml);
    
    // Auto-cerrar después de 5 segundos
    setTimeout(function() {
        $('.alert').first().fadeOut();
    }, 5000);
}

// Función para cargar notificaciones
function loadNotifications() {
    $.ajax({
        url: '../api/get_notifications.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateNotificationBadge(response.count);
                updateNotificationDropdown(response.notifications);
            }
        },
        error: function() {
            console.log('Error al cargar notificaciones');
        }
    });
}

// Función para actualizar badge de notificaciones
function updateNotificationBadge(count) {
    const badge = $('.notification-badge');
    if (count > 0) {
        badge.text(count).show();
    } else {
        badge.hide();
    }
}

// Función para actualizar dropdown de notificaciones
function updateNotificationDropdown(notifications) {
    const dropdown = $('.notification-dropdown');
    dropdown.empty();
    
    if (notifications.length === 0) {
        dropdown.html('<div class="dropdown-item text-center">No hay notificaciones</div>');
        return;
    }
    
    notifications.forEach(function(notification) {
        const item = `
            <div class="dropdown-item notification-item" data-id="${notification.id}">
                <div class="notification-content">
                    <div class="notification-title">${notification.titulo}</div>
                    <div class="notification-text">${notification.mensaje}</div>
                    <div class="notification-time">${notification.fecha_creacion}</div>
                </div>
            </div>
        `;
        dropdown.append(item);
    });
}

// Función para marcar notificación como leída
function markNotificationAsRead(notificationId) {
    $.ajax({
        url: '../api/mark_notification_read.php',
        type: 'POST',
        data: {id: notificationId},
        success: function() {
            loadNotifications();
        }
    });
}

// Función para validar email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Función para validar DNI
function validateDNI(dni) {
    return /^\d{8}$/.test(dni);
}

// Función para validar teléfono
function validatePhone(phone) {
    return /^\d{9}$/.test(phone);
}

// Función para formatear fechas
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-PE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Función para formatear fecha y hora
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('es-PE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Función para copiar texto al portapapeles
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showAlert('Texto copiado al portapapeles', 'success');
    });
}

// Función para descargar archivo
function downloadFile(url, filename) {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Función para previsualizar imagen
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $(previewId).attr('src', e.target.result).show();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Función para confirmar acción
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Función para mostrar modal de carga
function showLoadingModal() {
    const modal = `
        <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-body text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-3">Procesando...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(modal);
    $('#loadingModal').modal('show');
}

// Función para ocultar modal de carga
function hideLoadingModal() {
    $('#loadingModal').modal('hide');
    $('#loadingModal').remove();
}

// Función para generar colores aleatorios para gráficos
function generateRandomColors(count) {
    const colors = [];
    for (let i = 0; i < count; i++) {
        colors.push(`hsl(${Math.floor(Math.random() * 360)}, 70%, 50%)`);
    }
    return colors;
}

// Función para exportar tabla a CSV
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = Array.from(table.querySelectorAll('tr'));
    
    const csvContent = rows.map(row => {
        const cells = Array.from(row.querySelectorAll('td, th'));
        return cells.map(cell => `"${cell.textContent.trim()}"`).join(',');
    }).join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Función para validar formulario completo
function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Función para resetear formulario
function resetForm(formId) {
    const form = document.getElementById(formId);
    form.reset();
    form.querySelectorAll('.is-invalid').forEach(element => {
        element.classList.remove('is-invalid');
    });
    form.querySelectorAll('.invalid-feedback').forEach(element => {
        element.remove();
    });
}

// Función para filtrar tabla
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    const filter = input.value.toUpperCase();
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let shouldShow = false;
        
        for (let j = 0; j < cells.length; j++) {
            if (cells[j].textContent.toUpperCase().indexOf(filter) > -1) {
                shouldShow = true;
                break;
            }
        }
        
        rows[i].style.display = shouldShow ? '' : 'none';
    }
}

// Función para ordenar tabla
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const isAscending = table.getAttribute('data-sort-order') !== 'asc';
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        if (isAscending) {
            return aValue.localeCompare(bValue);
        } else {
            return bValue.localeCompare(aValue);
        }
    });
    
    const tbody = table.querySelector('tbody');
    rows.forEach(row => tbody.appendChild(row));
    
    table.setAttribute('data-sort-order', isAscending ? 'asc' : 'desc');
}
