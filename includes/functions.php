<?php
/**
 * Funciones auxiliares del Sistema Aula Virtual
 */

/**
 * Validar datos de entrada de manera segura
 */
function validateInput($data, $type = 'text', $required = false) {
    $data = trim($data);
    
    if ($required && empty($data)) {
        return ['valid' => false, 'message' => 'Este campo es obligatorio'];
    }
    
    if (empty($data)) {
        return ['valid' => true, 'data' => ''];
    }
    
    switch ($type) {
        case 'email':
            if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
                return ['valid' => false, 'message' => 'Email inválido'];
            }
            break;
            
        case 'dni':
            if (!preg_match('/^[0-9]{8}$/', $data)) {
                return ['valid' => false, 'message' => 'DNI debe tener 8 dígitos'];
            }
            break;
            
        case 'phone':
            if (!preg_match('/^(\+51|51|0?)?[9][0-9]{8}$/', str_replace(' ', '', $data))) {
                return ['valid' => false, 'message' => 'Número de teléfono inválido'];
            }
            break;
            
        case 'date':
            if (!validateDate($data)) {
                return ['valid' => false, 'message' => 'Fecha inválida'];
            }
            break;
            
        case 'numeric':
            if (!is_numeric($data)) {
                return ['valid' => false, 'message' => 'Debe ser un número'];
            }
            break;
            
        case 'alpha':
            if (!preg_match('/^[a-zA-ZÀ-ÿ\s]+$/', $data)) {
                return ['valid' => false, 'message' => 'Solo se permiten letras'];
            }
            break;
    }
    
    return ['valid' => true, 'data' => htmlspecialchars($data, ENT_QUOTES, 'UTF-8')];
}

/**
 * Validar fecha
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Generar contraseña aleatoria
 */
function generateRandomPassword($length = 8) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Formatear nombres (primera letra en mayúscula)
 */
function formatName($name) {
    return ucwords(strtolower(trim($name)));
}

/**
 * Calcular edad desde fecha de nacimiento
 */
function calculateAge($birthdate) {
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    return $birth->diff($today)->y;
}

/**
 * Formatear número de teléfono peruano
 */
function formatPeruvianPhone($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean) === 9 && $clean[0] === '9') {
        return '+51 ' . substr($clean, 0, 3) . ' ' . substr($clean, 3, 3) . ' ' . substr($clean, 6);
    }
    return $phone;
}

/**
 * Verificar si un archivo es una imagen válida
 */
function isValidImage($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    $fileType = $file['type'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    return in_array($fileType, $allowedTypes) && in_array($fileExtension, $allowedExtensions);
}

/**
 * Subir archivo de manera segura
 */
function uploadFile($file, $destination, $allowedTypes = [], $maxSize = 10485760) {
    // Validar si hay errores en la subida
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo'];
    }
    
    // Validar tamaño
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'El archivo es demasiado grande'];
    }
    
    // Validar tipo si se especifica
    if (!empty($allowedTypes)) {
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
        }
    }
    
    // Crear directorio si no existe
    $directory = dirname($destination);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => basename($destination)];
    }
    
    return ['success' => false, 'message' => 'Error al mover el archivo'];
}

/**
 * Generar código único para estudiante
 */
function generateStudentCode($year = null, $pdo = null) {
    if (!$year) $year = date('Y');
    
    // Obtener último código del año
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(codigo_estudiante, -4) AS UNSIGNED)) as max_num 
                               FROM estudiantes 
                               WHERE codigo_estudiante LIKE ?");
        $stmt->execute([$year . '%']);
        $result = $stmt->fetch();
        $nextNum = ($result['max_num'] ?? 0) + 1;
    } else {
        $nextNum = 1;
    }
    
    return $year . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Log de actividad del sistema
 */
function logActivity($pdo, $userId, $userType, $action, $details = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_type, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $userType,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Enviar notificación por email (placeholder - implementar con servicio real)
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    // Aquí implementar integración con servicio de email real
    // Por ahora, solo log para desarrollo
    error_log("Email enviado a: $to, Asunto: $subject");
    return true;
}

/**
 * Generar PDF usando una librería externa (placeholder)
 */
function generatePDF($html, $filename) {
    // Implementar con librería como mPDF o DomPDF
    error_log("PDF generado: $filename");
    return true;
}

/**
 * Formatear fecha en español
 */
function formatDateSpanish($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    
    $dateObj = is_string($date) ? new DateTime($date) : $date;
    
    $months = [
        'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo',
        'April' => 'abril', 'May' => 'mayo', 'June' => 'junio',
        'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre',
        'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'
    ];
    
    $days = [
        'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
        'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado',
        'Sunday' => 'domingo'
    ];
    
    $formatted = $dateObj->format($format);
    
    if (strpos($format, 'F') !== false || strpos($format, 'l') !== false) {
        $formatted = str_replace(array_keys($months), array_values($months), $formatted);
        $formatted = str_replace(array_keys($days), array_values($days), $formatted);
    }
    
    return $formatted;
}

/**
 * Calcular promedio de notas
 */
function calculateGradeAverage($grades) {
    if (empty($grades)) return 0;
    
    $validGrades = array_filter($grades, function($grade) {
        return is_numeric($grade) && $grade >= 0 && $grade <= 20;
    });
    
    if (empty($validGrades)) return 0;
    
    return round(array_sum($validGrades) / count($validGrades), 2);
}

/**
 * Determinar estado académico según promedio
 */
function getAcademicStatus($average) {
    if ($average >= 18) return ['status' => 'EXCELENTE', 'class' => 'success'];
    if ($average >= 14) return ['status' => 'APROBADO', 'class' => 'success'];
    if ($average >= 11) return ['status' => 'EN_PROCESO', 'class' => 'warning'];
    return ['status' => 'DESAPROBADO', 'class' => 'danger'];
}

/**
 * Paginar resultados
 */
function paginate($totalItems, $itemsPerPage = 20, $currentPage = 1) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Generar breadcrumbs
 */
function generateBreadcrumbs($pages) {
    $breadcrumbs = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    foreach ($pages as $index => $page) {
        $isLast = $index === count($pages) - 1;
        
        if ($isLast) {
            $breadcrumbs .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($page['title']) . '</li>';
        } else {
            $breadcrumbs .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($page['url']) . '">' . htmlspecialchars($page['title']) . '</a></li>';
        }
    }
    
    $breadcrumbs .= '</ol></nav>';
    return $breadcrumbs;
}

/**
 * Verificar permisos de acceso
 */
function checkPermission($userRole, $requiredRole) {
    $roleHierarchy = [
        'ESTUDIANTE' => 1,
        'AUXILIAR_EDUCACION' => 2,
        'DOCENTE' => 3,
        'COORDINADOR_TUTORIA' => 4,
        'JEFE_LABORATORIO' => 4,
        'PSICOLOGO' => 4,
        'SECRETARIA' => 5,
        'DIRECTOR' => 6
    ];
    
    $userLevel = $roleHierarchy[strtoupper($userRole)] ?? 0;
    $requiredLevel = $roleHierarchy[strtoupper($requiredRole)] ?? 999;
    
    return $userLevel >= $requiredLevel;
}

/**
 * Generar token seguro
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Comprimir imagen
 */
function compressImage($source, $destination, $quality = 75) {
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, 9 - ($quality / 10));
    } elseif ($info['mime'] == 'image/gif') {
        $image = imagecreatefromgif($source);
        imagegif($image, $destination);
    }
    
    if (isset($image)) {
        imagedestroy($image);
        return true;
    }
    
    return false;
}

/**
 * Convertir bytes a formato legible
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Obtener días entre fechas
 */
function getDaysBetweenDates($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $difference = $datetime1->diff($datetime2);
    return $difference->days;
}

/**
 * Limpiar texto para URL (slug)
 */
function createSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[àáâãäå]/', 'a', $text);
    $text = preg_replace('/[èéêë]/', 'e', $text);
    $text = preg_replace('/[ìíîï]/', 'i', $text);
    $text = preg_replace('/[òóôõö]/', 'o', $text);
    $text = preg_replace('/[ùúûü]/', 'u', $text);
    $text = preg_replace('/[ñ]/', 'n', $text);
    $text = preg_replace('/[^a-z0-9\s]/', '', $text);
    $text = preg_replace('/[\s]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Validar horario de acceso
 */
function isAccessTimeAllowed($startTime = '06:00', $endTime = '23:00') {
    $currentTime = date('H:i');
    return ($currentTime >= $startTime && $currentTime <= $endTime);
}

/**
 * Obtener IP real del usuario
 */
function getRealIpAddress() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

/**
 * Escape output para prevenir XSS
 */
function escapeOutput($data) {
    if (is_array($data)) {
        return array_map('escapeOutput', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Validar y limpiar entrada de archivos
 */
function validateFileUpload($file, $allowedTypes = [], $maxSize = 10485760) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error en la subida del archivo';
        return ['valid' => false, 'errors' => $errors];
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = 'El archivo excede el tamaño máximo permitido';
    }
    
    if (!empty($allowedTypes)) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = 'Tipo de archivo no permitido';
        }
    }
    
    // Verificar que es un archivo real
    if (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'Archivo inválido';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'extension' => $extension ?? '',
        'size' => $file['size']
    ];
}

/**
 * Redireccionar con mensaje
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Mostrar mensaje flash
 */
function showFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return ['message' => $message, 'type' => $type];
    }
    
    return null;
}
?>