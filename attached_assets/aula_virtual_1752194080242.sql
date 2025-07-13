-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-07-2025 a las 02:21:29
-- Versión del servidor: 10.4.32-MariaDB-log
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `aula_virtual`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_asignar_tutor` (IN `p_id_personal` INT, IN `p_grado` INT, IN `p_seccion` CHAR(1), IN `p_anio` INT)   BEGIN
    DECLARE v_id_seccion INT;
    DECLARE v_id_anio INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Obtener IDs necesarios
    SELECT s.id_seccion INTO v_id_seccion
    FROM secciones s
    JOIN grados g ON s.id_grado = g.id_grado
    WHERE g.numero_grado = p_grado AND s.letra_seccion = p_seccion;
    
    SELECT id_anio INTO v_id_anio
    FROM anios_academicos
    WHERE anio = p_anio;
    
    -- Insertar asignación de tutoría
    INSERT INTO asignacion_tutoria (id_personal, id_seccion, id_anio, fecha_asignacion)
    VALUES (p_id_personal, v_id_seccion, v_id_anio, CURDATE());
    
    COMMIT;
    
    SELECT 'Tutor asignado exitosamente' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cambiar_password_estudiante` (IN `p_dni` VARCHAR(8), IN `p_password_actual` VARCHAR(255), IN `p_password_nueva` VARCHAR(255))   BEGIN
    DECLARE v_id_estudiante INT;
    DECLARE v_password_hash_actual VARCHAR(255);
    DECLARE v_password_hash_nueva VARCHAR(255);
    DECLARE v_cuenta_bloqueada BOOLEAN;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Verificar que el estudiante existe y obtener datos
    SELECT id_estudiante, password_hash, cuenta_bloqueada 
    INTO v_id_estudiante, v_password_hash_actual, v_cuenta_bloqueada
    FROM estudiantes
    WHERE dni = p_dni;
    
    -- Verificar que la cuenta no esté bloqueada
    IF v_cuenta_bloqueada = TRUE THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cuenta bloqueada. Contacte al administrador.';
    END IF;
    
    -- Verificar contraseña actual
    IF v_password_hash_actual != fn_hash_password(p_password_actual) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Contraseña actual incorrecta';
    END IF;
    
    -- Hashear nueva contraseña
    SET v_password_hash_nueva = fn_hash_password(p_password_nueva);
    
    -- Actualizar contraseña
    UPDATE estudiantes 
    SET password_hash = v_password_hash_nueva,
        password_temporal = FALSE,
        primer_acceso = FALSE,
        intentos_fallidos = 0,
        updated_at = CURRENT_TIMESTAMP
    WHERE id_estudiante = v_id_estudiante;
    
    COMMIT;
    
    SELECT 'Contraseña cambiada exitosamente' as mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_establecer_password_inicial` (IN `p_id_estudiante` INT)   BEGIN
    DECLARE v_dni VARCHAR(8);
    DECLARE v_apellido_paterno VARCHAR(50);
    DECLARE v_password_temporal VARCHAR(20);
    DECLARE v_password_hash VARCHAR(255);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Obtener datos del estudiante
    SELECT dni, apellido_paterno INTO v_dni, v_apellido_paterno
    FROM estudiantes
    WHERE id_estudiante = p_id_estudiante;
    
    -- Generar contraseña temporal
    SET v_password_temporal = fn_generar_password_temporal(v_dni, v_apellido_paterno);
    
    -- Hashear la contraseña
    SET v_password_hash = fn_hash_password(v_password_temporal);
    
    -- Actualizar estudiante con contraseña hasheada
    UPDATE estudiantes 
    SET password_hash = v_password_hash,
        password_temporal = TRUE,
        primer_acceso = TRUE,
        intentos_fallidos = 0,
        cuenta_bloqueada = FALSE
    WHERE id_estudiante = p_id_estudiante;
    
    COMMIT;
    
    -- Devolver la contraseña temporal para comunicar al estudiante
    SELECT v_password_temporal as password_temporal, 
           'Contraseña temporal establecida' as mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_matricular_estudiante` (IN `p_codigo_estudiante` VARCHAR(20), IN `p_apellido_paterno` VARCHAR(50), IN `p_apellido_materno` VARCHAR(50), IN `p_nombres` VARCHAR(100), IN `p_sexo` ENUM('MASCULINO','FEMENINO'), IN `p_fecha_nacimiento` DATE, IN `p_apoderado_nombres` VARCHAR(100), IN `p_apoderado_parentesco` VARCHAR(20), IN `p_apoderado_celular` VARCHAR(15), IN `p_apoderado_email` VARCHAR(100), IN `p_grado` INT, IN `p_seccion` CHAR(1), IN `p_anio` INT)   BEGIN
    DECLARE v_id_estudiante INT;
    DECLARE v_id_seccion INT;
    DECLARE v_id_anio INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Insertar o actualizar estudiante
    INSERT INTO estudiantes (
        codigo_estudiante, apellido_paterno, apellido_materno, nombres, sexo,
        fecha_nacimiento, apoderado_nombres, apoderado_parentesco,
        apoderado_celular, apoderado_email
    ) VALUES (
        p_codigo_estudiante, p_apellido_paterno, p_apellido_materno, p_nombres, p_sexo,
        p_fecha_nacimiento, p_apoderado_nombres, p_apoderado_parentesco,
        p_apoderado_celular, p_apoderado_email
    ) ON DUPLICATE KEY UPDATE
        apellido_paterno = VALUES(apellido_paterno),
        apellido_materno = VALUES(apellido_materno),
        nombres = VALUES(nombres),
        apoderado_nombres = VALUES(apoderado_nombres),
        apoderado_parentesco = VALUES(apoderado_parentesco),
        apoderado_celular = VALUES(apoderado_celular),
        apoderado_email = VALUES(apoderado_email);
    
    SET v_id_estudiante = LAST_INSERT_ID();
    
    -- Obtener IDs necesarios
    SELECT id_seccion INTO v_id_seccion
    FROM secciones s
    JOIN grados g ON s.id_grado = g.id_grado
    WHERE g.numero_grado = p_grado AND s.letra_seccion = p_seccion;
    
    SELECT id_anio INTO v_id_anio
    FROM anios_academicos
    WHERE anio = p_anio;
    
    -- Insertar matrícula
    INSERT INTO matriculas (id_estudiante, id_seccion, id_anio, fecha_matricula)
    VALUES (v_id_estudiante, v_id_seccion, v_id_anio, CURDATE());
    
    COMMIT;
    
    SELECT 'Estudiante matriculado exitosamente' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_atencion_psicologica` (IN `p_id_psicologo` INT, IN `p_codigo_estudiante` VARCHAR(20), IN `p_fecha_atencion` DATE, IN `p_motivo` TEXT, IN `p_observaciones` TEXT, IN `p_derivado_por` INT, IN `p_tipo_atencion` ENUM('INDIVIDUAL','GRUPAL','FAMILIAR','PREVENTIVA'))   BEGIN
    DECLARE v_id_estudiante INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Obtener ID del estudiante
    SELECT id_estudiante INTO v_id_estudiante
    FROM estudiantes
    WHERE codigo_estudiante = p_codigo_estudiante;
    
    -- Insertar atención psicológica
    INSERT INTO atencion_psicologica (
        id_psicologo, id_estudiante, fecha_atencion, motivo, 
        observaciones, derivado_por, tipo_atencion
    ) VALUES (
        p_id_psicologo, v_id_estudiante, p_fecha_atencion, p_motivo,
        p_observaciones, p_derivado_por, p_tipo_atencion
    );
    
    COMMIT;
    
    SELECT 'Atención psicológica registrada exitosamente' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_nota` (IN `p_codigo_estudiante` VARCHAR(20), IN `p_codigo_curso` VARCHAR(10), IN `p_bimestre` INT, IN `p_anio` INT, IN `p_nota` DECIMAL(4,2), IN `p_observaciones` TEXT, IN `p_registrado_por` INT)   BEGIN
    DECLARE v_id_matricula INT;
    DECLARE v_id_curso INT;
    DECLARE v_id_bimestre INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Obtener IDs necesarios
    SELECT m.id_matricula INTO v_id_matricula
    FROM matriculas m
    JOIN estudiantes e ON m.id_estudiante = e.id_estudiante
    JOIN anios_academicos a ON m.id_anio = a.id_anio
    WHERE e.codigo_estudiante = p_codigo_estudiante
    AND a.anio = p_anio
    AND m.estado = 'ACTIVO';
    
    SELECT id_curso INTO v_id_curso
    FROM cursos
    WHERE codigo = p_codigo_curso;
    
    SELECT id_bimestre INTO v_id_bimestre
    FROM bimestres b
    JOIN anios_academicos a ON b.id_anio = a.id_anio
    WHERE a.anio = p_anio AND b.numero_bimestre = p_bimestre;
    
    -- Insertar o actualizar nota
    INSERT INTO notas (id_matricula, id_curso, id_bimestre, nota, observaciones, fecha_registro, registrado_por)
    VALUES (v_id_matricula, v_id_curso, v_id_bimestre, p_nota, p_observaciones, CURDATE(), p_registrado_por)
    ON DUPLICATE KEY UPDATE
        nota = VALUES(nota),
        observaciones = VALUES(observaciones),
        fecha_registro = VALUES(fecha_registro),
        registrado_por = VALUES(registrado_por);
    
    COMMIT;
    
    SELECT 'Nota registrada exitosamente' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_resetear_password_estudiante` (IN `p_dni` VARCHAR(8), IN `p_admin_id` INT)   BEGIN
    DECLARE v_id_estudiante INT;
    DECLARE v_apellido_paterno VARCHAR(50);
    DECLARE v_password_temporal VARCHAR(20);
    DECLARE v_password_hash VARCHAR(255);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Obtener datos del estudiante
    SELECT id_estudiante, apellido_paterno INTO v_id_estudiante, v_apellido_paterno
    FROM estudiantes
    WHERE dni = p_dni;
    
    IF v_id_estudiante IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Estudiante no encontrado';
    END IF;
    
    -- Generar nueva contraseña temporal
    SET v_password_temporal = fn_generar_password_temporal(p_dni, v_apellido_paterno);
    SET v_password_hash = fn_hash_password(v_password_temporal);
    
    -- Resetear contraseña y desbloquear cuenta
    UPDATE estudiantes 
    SET password_hash = v_password_hash,
        password_temporal = TRUE,
        primer_acceso = TRUE,
        cuenta_bloqueada = FALSE,
        intentos_fallidos = 0,
        fecha_bloqueo = NULL,
        token_recuperacion = NULL,
        token_expiracion = NULL
    WHERE id_estudiante = v_id_estudiante;
    
    -- Registrar el reseteo en historial
    INSERT INTO historial_accesos_estudiantes (id_estudiante, resultado, motivo)
    VALUES (v_id_estudiante, 'EXITOSO', CONCAT('Contraseña reseteada por admin ID: ', p_admin_id));
    
    COMMIT;
    
    SELECT v_password_temporal as nueva_password_temporal,
           'Contraseña reseteada exitosamente' as mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_validar_login_estudiante` (IN `p_dni` VARCHAR(8), IN `p_password` VARCHAR(255), IN `p_ip_address` VARCHAR(45), IN `p_user_agent` TEXT)   BEGIN
    DECLARE v_id_estudiante INT;
    DECLARE v_password_hash VARCHAR(255);
    DECLARE v_cuenta_bloqueada BOOLEAN;
    DECLARE v_intentos_fallidos TINYINT;
    DECLARE v_password_temporal BOOLEAN;
    DECLARE v_primer_acceso BOOLEAN;
    DECLARE v_resultado VARCHAR(10);
    DECLARE v_motivo VARCHAR(100);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Obtener datos del estudiante
    SELECT id_estudiante, password_hash, cuenta_bloqueada, intentos_fallidos, 
           password_temporal, primer_acceso
    INTO v_id_estudiante, v_password_hash, v_cuenta_bloqueada, v_intentos_fallidos,
         v_password_temporal, v_primer_acceso
    FROM estudiantes
    WHERE dni = p_dni;
    
    -- Verificar si el estudiante existe
    IF v_id_estudiante IS NULL THEN
        SET v_resultado = 'FALLIDO';
        SET v_motivo = 'DNI no encontrado';
        -- Registrar intento fallido sin ID de estudiante
        INSERT INTO historial_accesos_estudiantes (id_estudiante, ip_address, user_agent, resultado, motivo)
        VALUES (0, p_ip_address, p_user_agent, v_resultado, v_motivo);
        
        SELECT 'ERROR' as status, 'DNI no encontrado' as mensaje;
        
    ELSEIF v_cuenta_bloqueada = TRUE THEN
        SET v_resultado = 'BLOQUEADO';
        SET v_motivo = 'Cuenta bloqueada';
        
        INSERT INTO historial_accesos_estudiantes (id_estudiante, ip_address, user_agent, resultado, motivo)
        VALUES (v_id_estudiante, p_ip_address, p_user_agent, v_resultado, v_motivo);
        
        SELECT 'BLOQUEADO' as status, 'Cuenta bloqueada. Contacte al administrador.' as mensaje;
        
    ELSEIF v_password_hash != fn_hash_password(p_password) THEN
        -- Contraseña incorrecta
        SET v_intentos_fallidos = v_intentos_fallidos + 1;
        SET v_resultado = 'FALLIDO';
        SET v_motivo = 'Contraseña incorrecta';
        
        -- Bloquear cuenta si supera 3 intentos
        IF v_intentos_fallidos >= 3 THEN
            UPDATE estudiantes 
            SET cuenta_bloqueada = TRUE,
                fecha_bloqueo = CURRENT_TIMESTAMP,
                intentos_fallidos = v_intentos_fallidos
            WHERE id_estudiante = v_id_estudiante;
            
            SET v_motivo = 'Cuenta bloqueada por exceso de intentos';
        ELSE
            UPDATE estudiantes 
            SET intentos_fallidos = v_intentos_fallidos
            WHERE id_estudiante = v_id_estudiante;
        END IF;
        
        INSERT INTO historial_accesos_estudiantes (id_estudiante, ip_address, user_agent, resultado, motivo)
        VALUES (v_id_estudiante, p_ip_address, p_user_agent, v_resultado, v_motivo);
        
        SELECT 'ERROR' as status, 
               CONCAT('Contraseña incorrecta. Intentos restantes: ', (3 - v_intentos_fallidos)) as mensaje;
        
    ELSE
        -- Login exitoso
        SET v_resultado = 'EXITOSO';
        SET v_motivo = 'Acceso autorizado';
        
        UPDATE estudiantes 
        SET fecha_ultimo_acceso = CURRENT_TIMESTAMP,
            intentos_fallidos = 0
        WHERE id_estudiante = v_id_estudiante;
        
        INSERT INTO historial_accesos_estudiantes (id_estudiante, ip_address, user_agent, resultado, motivo)
        VALUES (v_id_estudiante, p_ip_address, p_user_agent, v_resultado, v_motivo);
        
        SELECT 'SUCCESS' as status,
               'Acceso autorizado' as mensaje,
               v_id_estudiante as id_estudiante,
               v_password_temporal as password_temporal,
               v_primer_acceso as primer_acceso;
    END IF;
    
    COMMIT;
END$$

--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_calcular_promedio` (`p_id_matricula` INT, `p_id_bimestre` INT) RETURNS DECIMAL(4,2) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_promedio DECIMAL(4,2);
    
    SELECT AVG(nota) INTO v_promedio
    FROM notas
    WHERE id_matricula = p_id_matricula
    AND id_bimestre = p_id_bimestre;
    
    RETURN IFNULL(v_promedio, 0.00);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_generar_password_temporal` (`p_dni` VARCHAR(8), `p_apellido_paterno` VARCHAR(50)) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_password VARCHAR(20);
    DECLARE v_primeras_letras VARCHAR(3);
    
    -- Obtener las primeras 3 letras del apellido paterno en minúsculas
    SET v_primeras_letras = LOWER(LEFT(p_apellido_paterno, 3));
    
    -- Generar contraseña: DNI + primeras 3 letras del apellido
    SET v_password = CONCAT(p_dni, v_primeras_letras);
    
    RETURN v_password;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_hash_password` (`p_password` VARCHAR(255)) RETURNS VARCHAR(255) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    -- En producción, usar una función de hash segura como bcrypt
    -- Aquí usamos SHA2 como ejemplo (NO usar en producción real)
    RETURN SHA2(CONCAT(p_password, 'salt_secreto_2025'), 256);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `anios_academicos`
--

CREATE TABLE `anios_academicos` (
  `id_anio` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `anios_academicos`
--

INSERT INTO `anios_academicos` (`id_anio`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`, `created_at`, `updated_at`) VALUES
(1, 2025, '2025-03-01', '2025-12-20', 'ACTIVO', '2025-07-11 00:15:51', '2025-07-11 00:15:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas_academicas`
--

CREATE TABLE `areas_academicas` (
  `id_area` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_coordinacion` int(11) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `areas_academicas`
--

INSERT INTO `areas_academicas` (`id_area`, `nombre`, `descripcion`, `id_coordinacion`, `estado`, `created_at`) VALUES
(1, 'Ciencias Exactas', 'Área de matemáticas y ciencias exactas', 2, 'ACTIVO', '2025-07-11 00:17:45'),
(2, 'Ciencias Naturales', 'Área de ciencias naturales y experimentales', 2, 'ACTIVO', '2025-07-11 00:17:45'),
(3, 'Comunicación', 'Área de comunicación y lenguaje', 3, 'ACTIVO', '2025-07-11 00:17:45'),
(4, 'Ciencias Sociales', 'Área de ciencias sociales y cívicas', 3, 'ACTIVO', '2025-07-11 00:17:45'),
(5, 'Educación Física', 'Área de educación física y deportes', 4, 'ACTIVO', '2025-07-11 00:17:45'),
(6, 'Educación Artística', 'Área de arte y cultura', 4, 'ACTIVO', '2025-07-11 00:17:45'),
(7, 'Educación Religiosa', 'Área de educación religiosa', 4, 'ACTIVO', '2025-07-11 00:17:45'),
(8, 'Educación Técnica', 'Área de educación para el trabajo', 4, 'ACTIVO', '2025-07-11 00:17:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones`
--

CREATE TABLE `asignaciones` (
  `id_asignacion` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_seccion` int(11) NOT NULL,
  `id_anio` int(11) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones_secretaria`
--

CREATE TABLE `asignaciones_secretaria` (
  `id_asignacion_sec` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `area_asignada` enum('DIRECCION','ACADEMICA','ADMINISTRATIVA','GENERAL') NOT NULL,
  `id_anio` int(11) NOT NULL,
  `responsabilidades` text DEFAULT NULL,
  `fecha_asignacion` date NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignacion_tutoria`
--

CREATE TABLE `asignacion_tutoria` (
  `id_tutoria` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `id_seccion` int(11) NOT NULL,
  `id_anio` int(11) NOT NULL,
  `fecha_asignacion` date NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `asignacion_tutoria`
--
DELIMITER $$
CREATE TRIGGER `tr_validar_tutor` BEFORE INSERT ON `asignacion_tutoria` FOR EACH ROW BEGIN
    DECLARE v_cargo VARCHAR(50);
    
    SELECT cargo INTO v_cargo
    FROM personal
    WHERE id_personal = NEW.id_personal;
    
    IF v_cargo != 'DOCENTE' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo los docentes pueden ser asignados como tutores';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

CREATE TABLE `asistencias` (
  `id_asistencia` int(11) NOT NULL,
  `id_matricula` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('PRESENTE','AUSENTE','TARDANZA','JUSTIFICADO') NOT NULL,
  `observaciones` text DEFAULT NULL,
  `registrado_por` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `atencion_psicologica`
--

CREATE TABLE `atencion_psicologica` (
  `id_atencion` int(11) NOT NULL,
  `id_psicologo` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `fecha_atencion` date NOT NULL,
  `motivo` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `derivado_por` int(11) DEFAULT NULL,
  `tipo_atencion` enum('INDIVIDUAL','GRUPAL','FAMILIAR','PREVENTIVA') NOT NULL,
  `estado` enum('PROGRAMADA','REALIZADA','CANCELADA') DEFAULT 'PROGRAMADA',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `atencion_psicologica`
--
DELIMITER $$
CREATE TRIGGER `tr_validar_psicologo` BEFORE INSERT ON `atencion_psicologica` FOR EACH ROW BEGIN
    DECLARE v_cargo VARCHAR(50);
    
    SELECT cargo INTO v_cargo
    FROM personal
    WHERE id_personal = NEW.id_psicologo;
    
    IF v_cargo != 'PSICOLOGO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo el personal con cargo de psicólogo puede registrar atenciones';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auxiliares_educacion`
--

CREATE TABLE `auxiliares_educacion` (
  `id_auxiliar_edu` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `id_anio` int(11) NOT NULL,
  `turno` enum('MAÑANA','TARDE','AMBOS') NOT NULL,
  `responsabilidades` text DEFAULT NULL,
  `fecha_asignacion` date NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auxiliares_laboratorio`
--

CREATE TABLE `auxiliares_laboratorio` (
  `id_auxiliar_lab` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `id_laboratorio` int(11) NOT NULL,
  `id_anio` int(11) NOT NULL,
  `fecha_asignacion` date NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bimestres`
--

CREATE TABLE `bimestres` (
  `id_bimestre` int(11) NOT NULL,
  `id_anio` int(11) NOT NULL,
  `numero_bimestre` tinyint(4) NOT NULL CHECK (`numero_bimestre` in (1,2)),
  `nombre` varchar(20) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` enum('ACTIVO','INACTIVO','FINALIZADO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bimestres`
--

INSERT INTO `bimestres` (`id_bimestre`, `id_anio`, `numero_bimestre`, `nombre`, `fecha_inicio`, `fecha_fin`, `estado`, `created_at`) VALUES
(1, 1, 1, 'PRIMER BIMESTRE', '2025-03-01', '2025-07-31', 'ACTIVO', '2025-07-11 00:15:51'),
(2, 1, 2, 'SEGUNDO BIMESTRE', '2025-08-01', '2025-12-20', 'ACTIVO', '2025-07-11 00:15:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `coordinaciones`
--

CREATE TABLE `coordinaciones` (
  `id_coordinacion` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_coordinador` int(11) DEFAULT NULL,
  `tipo_coordinacion` enum('TUTORIA','CIENCIAS','LETRAS','ACADEMICA','DISCIPLINA') NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `coordinaciones`
--

INSERT INTO `coordinaciones` (`id_coordinacion`, `nombre`, `descripcion`, `id_coordinador`, `tipo_coordinacion`, `estado`, `created_at`, `updated_at`) VALUES
(1, 'Coordinación de Tutoría', 'Coordinación del área de tutoría y orientación educativa', NULL, 'TUTORIA', 'ACTIVO', '2025-07-11 00:17:42', '2025-07-11 00:17:42'),
(2, 'Coordinación de Ciencias', 'Coordinación del área de ciencias (matemáticas, ciencias naturales)', NULL, 'CIENCIAS', 'ACTIVO', '2025-07-11 00:17:42', '2025-07-11 00:17:42'),
(3, 'Coordinación de Letras', 'Coordinación del área de letras (comunicación, ciencias sociales)', NULL, 'LETRAS', 'ACTIVO', '2025-07-11 00:17:42', '2025-07-11 00:17:42'),
(4, 'Coordinación Académica', 'Coordinación general académica', NULL, 'ACADEMICA', 'ACTIVO', '2025-07-11 00:17:42', '2025-07-11 00:17:42'),
(5, 'Coordinación de Disciplina', 'Coordinación de disciplina y convivencia escolar', NULL, 'DISCIPLINA', 'ACTIVO', '2025-07-11 00:17:42', '2025-07-11 00:17:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id_curso`, `nombre`, `codigo`, `estado`, `created_at`) VALUES
(1, 'Matemáticas', 'MAT', 'ACTIVO', '2025-07-11 00:15:51'),
(2, 'Comunicación', 'COM', 'ACTIVO', '2025-07-11 00:15:51'),
(3, 'Ciencia y Tecnología', 'CYT', 'ACTIVO', '2025-07-11 00:15:51'),
(4, 'Ciencias Sociales', 'CCSS', 'ACTIVO', '2025-07-11 00:15:51'),
(5, 'Religión', 'REL', 'ACTIVO', '2025-07-11 00:15:51'),
(6, 'Educación Física', 'EF', 'ACTIVO', '2025-07-11 00:15:51'),
(7, 'Educación por el Trabajo', 'EPT', 'ACTIVO', '2025-07-11 00:15:51'),
(8, 'Inglés', 'ING', 'ACTIVO', '2025-07-11 00:15:51'),
(9, 'Arte y Cultura', 'AYC', 'ACTIVO', '2025-07-11 00:15:51'),
(10, 'DPCC', 'DPCC', 'ACTIVO', '2025-07-11 00:15:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos_areas`
--

CREATE TABLE `cursos_areas` (
  `id_curso_area` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cursos_areas`
--

INSERT INTO `cursos_areas` (`id_curso_area`, `id_curso`, `id_area`, `created_at`) VALUES
(1, 1, 1, '2025-07-11 00:18:01'),
(2, 2, 3, '2025-07-11 00:18:01'),
(3, 3, 2, '2025-07-11 00:18:01'),
(4, 4, 4, '2025-07-11 00:18:01'),
(5, 5, 7, '2025-07-11 00:18:01'),
(6, 6, 5, '2025-07-11 00:18:01'),
(7, 7, 8, '2025-07-11 00:18:01'),
(8, 8, 3, '2025-07-11 00:18:01'),
(9, 9, 6, '2025-07-11 00:18:01'),
(10, 10, 4, '2025-07-11 00:18:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id_estudiante` int(11) NOT NULL,
  `codigo_estudiante` varchar(20) NOT NULL,
  `dni` varchar(8) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_temporal` tinyint(1) DEFAULT 1,
  `primer_acceso` tinyint(1) DEFAULT 1,
  `fecha_ultimo_acceso` timestamp NULL DEFAULT NULL,
  `intentos_fallidos` tinyint(4) DEFAULT 0,
  `cuenta_bloqueada` tinyint(1) DEFAULT 0,
  `fecha_bloqueo` timestamp NULL DEFAULT NULL,
  `token_recuperacion` varchar(100) DEFAULT NULL,
  `token_expiracion` timestamp NULL DEFAULT NULL,
  `apellido_paterno` varchar(50) NOT NULL,
  `apellido_materno` varchar(50) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `sexo` enum('MASCULINO','FEMENINO') NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `estado_matricula` enum('ACTIVO','INACTIVO','TRASLADADO','RETIRADO') DEFAULT 'ACTIVO',
  `apoderado_nombres` varchar(100) NOT NULL,
  `apoderado_parentesco` varchar(20) NOT NULL,
  `apoderado_celular` varchar(15) DEFAULT NULL,
  `apoderado_email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `estudiantes`
--
DELIMITER $$
CREATE TRIGGER `tr_generar_codigo_estudiante` BEFORE INSERT ON `estudiantes` FOR EACH ROW BEGIN
    IF NEW.codigo_estudiante IS NULL OR NEW.codigo_estudiante = '' THEN
        SET NEW.codigo_estudiante = CONCAT('EST', LPAD(LAST_INSERT_ID() + 1, 8, '0'));
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_password_inicial_estudiante` AFTER INSERT ON `estudiantes` FOR EACH ROW BEGIN
    DECLARE v_password_temporal VARCHAR(20);
    DECLARE v_password_hash VARCHAR(255);
    
    -- Generar contraseña temporal
    SET v_password_temporal = fn_generar_password_temporal(NEW.dni, NEW.apellido_paterno);
    
    -- Hashear contraseña
    SET v_password_hash = fn_hash_password(v_password_temporal);
    
    -- Actualizar con contraseña hasheada
    UPDATE estudiantes 
    SET password_hash = v_password_hash,
        password_temporal = TRUE,
        primer_acceso = TRUE
    WHERE id_estudiante = NEW.id_estudiante;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_validar_edad_estudiante` BEFORE INSERT ON `estudiantes` FOR EACH ROW BEGIN
    DECLARE edad INT;
    SET edad = TIMESTAMPDIFF(YEAR, NEW.fecha_nacimiento, CURDATE());
    
    IF edad < 10 OR edad > 20 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La edad del estudiante debe estar entre 10 y 20 años';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grados`
--

CREATE TABLE `grados` (
  `id_grado` int(11) NOT NULL,
  `numero_grado` tinyint(4) NOT NULL CHECK (`numero_grado` between 1 and 5),
  `nombre` varchar(20) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `grados`
--

INSERT INTO `grados` (`id_grado`, `numero_grado`, `nombre`, `estado`, `created_at`) VALUES
(1, 1, 'PRIMERO', 'ACTIVO', '2025-07-11 00:15:51'),
(2, 2, 'SEGUNDO', 'ACTIVO', '2025-07-11 00:15:51'),
(3, 3, 'TERCERO', 'ACTIVO', '2025-07-11 00:15:51'),
(4, 4, 'CUARTO', 'ACTIVO', '2025-07-11 00:15:51'),
(5, 5, 'QUINTO', 'ACTIVO', '2025-07-11 00:15:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_accesos_estudiantes`
--

CREATE TABLE `historial_accesos_estudiantes` (
  `id_acceso` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `fecha_acceso` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `resultado` enum('EXITOSO','FALLIDO','BLOQUEADO') NOT NULL,
  `motivo` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `laboratorios`
--

CREATE TABLE `laboratorios` (
  `id_laboratorio` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `capacidad` int(11) NOT NULL,
  `id_jefe_laboratorio` int(11) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO','MANTENIMIENTO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `laboratorios`
--

INSERT INTO `laboratorios` (`id_laboratorio`, `nombre`, `descripcion`, `capacidad`, `id_jefe_laboratorio`, `estado`, `created_at`, `updated_at`) VALUES
(1, 'Laboratorio de Ciencias', 'Laboratorio principal para experimentos de ciencias', 30, NULL, 'ACTIVO', '2025-07-11 00:18:03', '2025-07-11 00:18:03'),
(2, 'Laboratorio de Cómputo', 'Laboratorio de informática y tecnología', 25, NULL, 'ACTIVO', '2025-07-11 00:18:03', '2025-07-11 00:18:03'),
(3, 'Laboratorio de Idiomas', 'Laboratorio para práctica de idiomas', 20, NULL, 'ACTIVO', '2025-07-11 00:18:03', '2025-07-11 00:18:03');

--
-- Disparadores `laboratorios`
--
DELIMITER $$
CREATE TRIGGER `tr_validar_jefe_laboratorio` BEFORE INSERT ON `laboratorios` FOR EACH ROW BEGIN
    DECLARE v_cargo VARCHAR(50);
    
    IF NEW.id_jefe_laboratorio IS NOT NULL THEN
        SELECT cargo INTO v_cargo
        FROM personal
        WHERE id_personal = NEW.id_jefe_laboratorio;
        
        IF v_cargo != 'JEFE_LABORATORIO' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo personal con cargo de jefe de laboratorio puede dirigir un laboratorio';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_notas`
--

CREATE TABLE `log_notas` (
  `id_log` int(11) NOT NULL,
  `id_nota` int(11) DEFAULT NULL,
  `nota_anterior` decimal(4,2) DEFAULT NULL,
  `nota_nueva` decimal(4,2) DEFAULT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `fecha_cambio` timestamp NOT NULL DEFAULT current_timestamp(),
  `accion` enum('INSERT','UPDATE','DELETE') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `matriculas`
--

CREATE TABLE `matriculas` (
  `id_matricula` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_seccion` int(11) NOT NULL,
  `id_anio` int(11) NOT NULL,
  `fecha_matricula` date NOT NULL,
  `estado` enum('ACTIVO','INACTIVO','TRASLADADO','RETIRADO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `matriculas`
--
DELIMITER $$
CREATE TRIGGER `tr_validar_matricula_activa` BEFORE INSERT ON `matriculas` FOR EACH ROW BEGIN
    DECLARE count_activas INT;
    
    SELECT COUNT(*) INTO count_activas
    FROM matriculas
    WHERE id_estudiante = NEW.id_estudiante
    AND id_anio = NEW.id_anio
    AND estado = 'ACTIVO';
    
    IF count_activas > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El estudiante ya tiene una matrícula activa para este año';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notas`
--

CREATE TABLE `notas` (
  `id_nota` int(11) NOT NULL,
  `id_matricula` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_bimestre` int(11) NOT NULL,
  `nota` decimal(4,2) DEFAULT NULL CHECK (`nota` >= 0 and `nota` <= 20),
  `observaciones` text DEFAULT NULL,
  `fecha_registro` date NOT NULL,
  `registrado_por` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `notas`
--
DELIMITER $$
CREATE TRIGGER `tr_log_notas_update` AFTER UPDATE ON `notas` FOR EACH ROW BEGIN
    INSERT INTO log_notas (id_nota, nota_anterior, nota_nueva, usuario, accion)
    VALUES (NEW.id_nota, OLD.nota, NEW.nota, USER(), 'UPDATE');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_validar_nota_insert` BEFORE INSERT ON `notas` FOR EACH ROW BEGIN
    IF NEW.nota < 0 OR NEW.nota > 20 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La nota debe estar entre 0 y 20';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_validar_nota_update` BEFORE UPDATE ON `notas` FOR EACH ROW BEGIN
    IF NEW.nota < 0 OR NEW.nota > 20 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La nota debe estar entre 0 y 20';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal`
--

CREATE TABLE `personal` (
  `id_personal` int(11) NOT NULL,
  `dni` varchar(8) NOT NULL,
  `apellidos_nombres` varchar(150) NOT NULL,
  `cargo` enum('DOCENTE','DIRECTOR','SUBDIRECTOR','COORDINADOR_TUTORIA','COORDINADOR_CIENCIAS','COORDINADOR_LETRAS','JEFE_LABORATORIO','AUXILIAR_EDUCACION','AUXILIAR_LABORATORIO','SECRETARIA','SECRETARIA_DIRECCION','SECRETARIA_ACADEMICA','PSICOLOGO','PERSONAL_ADMINISTRATIVO','PERSONAL_SERVICIO','COORDINADOR_ACADEMICO','COORDINADOR_DISCIPLINA') NOT NULL,
  `email` varchar(100) NOT NULL,
  `celular` varchar(15) DEFAULT NULL,
  `condicion_laboral` enum('NOMBRADO','CONTRATADO','DESIGNADO') NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secciones`
--

CREATE TABLE `secciones` (
  `id_seccion` int(11) NOT NULL,
  `id_grado` int(11) NOT NULL,
  `letra_seccion` char(1) NOT NULL CHECK (`letra_seccion` in ('A','B','C')),
  `nombre` varchar(20) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `secciones`
--

INSERT INTO `secciones` (`id_seccion`, `id_grado`, `letra_seccion`, `nombre`, `estado`, `created_at`) VALUES
(1, 1, 'A', 'PRIMERO A', 'ACTIVO', '2025-07-11 00:15:51'),
(2, 1, 'B', 'PRIMERO B', 'ACTIVO', '2025-07-11 00:15:51'),
(3, 1, 'C', 'PRIMERO C', 'ACTIVO', '2025-07-11 00:15:51'),
(4, 2, 'A', 'SEGUNDO A', 'ACTIVO', '2025-07-11 00:15:51'),
(5, 2, 'B', 'SEGUNDO B', 'ACTIVO', '2025-07-11 00:15:51'),
(6, 2, 'C', 'SEGUNDO C', 'ACTIVO', '2025-07-11 00:15:51'),
(7, 3, 'A', 'TERCERO A', 'ACTIVO', '2025-07-11 00:15:51'),
(8, 3, 'B', 'TERCERO B', 'ACTIVO', '2025-07-11 00:15:51'),
(9, 3, 'C', 'TERCERO C', 'ACTIVO', '2025-07-11 00:15:51'),
(10, 4, 'A', 'CUARTO A', 'ACTIVO', '2025-07-11 00:15:51'),
(11, 4, 'B', 'CUARTO B', 'ACTIVO', '2025-07-11 00:15:51'),
(12, 4, 'C', 'CUARTO C', 'ACTIVO', '2025-07-11 00:15:51'),
(13, 5, 'A', 'QUINTO A', 'ACTIVO', '2025-07-11 00:15:51'),
(14, 5, 'B', 'QUINTO B', 'ACTIVO', '2025-07-11 00:15:51'),
(15, 5, 'C', 'QUINTO C', 'ACTIVO', '2025-07-11 00:15:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguimiento_psicologico`
--

CREATE TABLE `seguimiento_psicologico` (
  `id_seguimiento` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_psicologo` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `problema_identificado` text NOT NULL,
  `objetivos` text NOT NULL,
  `estrategias` text DEFAULT NULL,
  `estado` enum('ACTIVO','CERRADO','SUSPENDIDO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_accesos_recientes`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_accesos_recientes` (
`fecha_acceso` timestamp
,`dni` varchar(8)
,`estudiante` varchar(203)
,`ip_address` varchar(45)
,`resultado` enum('EXITOSO','FALLIDO','BLOQUEADO')
,`motivo` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_atenciones_psicologicas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_atenciones_psicologicas` (
`psicologo` varchar(150)
,`estudiante` varchar(203)
,`fecha_atencion` date
,`motivo` text
,`tipo_atencion` enum('INDIVIDUAL','GRUPAL','FAMILIAR','PREVENTIVA')
,`estado` enum('PROGRAMADA','REALIZADA','CANCELADA')
,`derivado_por` varchar(150)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_estructura_organizacional`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_estructura_organizacional` (
`coordinacion` varchar(50)
,`tipo_coordinacion` enum('TUTORIA','CIENCIAS','LETRAS','ACADEMICA','DISCIPLINA')
,`coordinador` varchar(150)
,`email_coordinador` varchar(100)
,`area` varchar(30)
,`cursos_area` mediumtext
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_estudiantes_actuales`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_estudiantes_actuales` (
`codigo_estudiante` varchar(20)
,`nombre_completo` varchar(203)
,`sexo` enum('MASCULINO','FEMENINO')
,`fecha_nacimiento` date
,`grado` varchar(20)
,`seccion` char(1)
,`anio` int(11)
,`apoderado_nombres` varchar(100)
,`apoderado_celular` varchar(15)
,`apoderado_email` varchar(100)
,`estado_matricula` enum('ACTIVO','INACTIVO','TRASLADADO','RETIRADO')
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_estudiantes_autenticacion`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_estudiantes_autenticacion` (
`codigo_estudiante` varchar(20)
,`dni` varchar(8)
,`nombre_completo` varchar(203)
,`password_temporal` tinyint(1)
,`primer_acceso` tinyint(1)
,`fecha_ultimo_acceso` timestamp
,`intentos_fallidos` tinyint(4)
,`cuenta_bloqueada` tinyint(1)
,`fecha_bloqueo` timestamp
,`grado` varchar(20)
,`seccion` char(1)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_notas_detalle`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_notas_detalle` (
`codigo_estudiante` varchar(20)
,`estudiante` varchar(203)
,`grado` varchar(20)
,`seccion` char(1)
,`curso` varchar(50)
,`bimestre` varchar(20)
,`nota` decimal(4,2)
,`observaciones` text
,`fecha_registro` date
,`registrado_por` varchar(150)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_personal_por_cargo`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_personal_por_cargo` (
`cargo` enum('DOCENTE','DIRECTOR','SUBDIRECTOR','COORDINADOR_TUTORIA','COORDINADOR_CIENCIAS','COORDINADOR_LETRAS','JEFE_LABORATORIO','AUXILIAR_EDUCACION','AUXILIAR_LABORATORIO','SECRETARIA','SECRETARIA_DIRECCION','SECRETARIA_ACADEMICA','PSICOLOGO','PERSONAL_ADMINISTRATIVO','PERSONAL_SERVICIO','COORDINADOR_ACADEMICO','COORDINADOR_DISCIPLINA')
,`cantidad` bigint(21)
,`personal_detalle` mediumtext
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_tutorias_asignadas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_tutorias_asignadas` (
`tutor` varchar(150)
,`grado` varchar(20)
,`seccion` char(1)
,`anio` int(11)
,`fecha_asignacion` date
,`total_estudiantes` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_accesos_recientes`
--
DROP TABLE IF EXISTS `v_accesos_recientes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_accesos_recientes`  AS SELECT `h`.`fecha_acceso` AS `fecha_acceso`, `e`.`dni` AS `dni`, concat(`e`.`apellido_paterno`,' ',`e`.`apellido_materno`,', ',`e`.`nombres`) AS `estudiante`, `h`.`ip_address` AS `ip_address`, `h`.`resultado` AS `resultado`, `h`.`motivo` AS `motivo` FROM (`historial_accesos_estudiantes` `h` left join `estudiantes` `e` on(`h`.`id_estudiante` = `e`.`id_estudiante`)) WHERE `h`.`fecha_acceso` >= current_timestamp() - interval 30 day ORDER BY `h`.`fecha_acceso` DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_atenciones_psicologicas`
--
DROP TABLE IF EXISTS `v_atenciones_psicologicas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_atenciones_psicologicas`  AS SELECT `p`.`apellidos_nombres` AS `psicologo`, concat(`e`.`apellido_paterno`,' ',`e`.`apellido_materno`,', ',`e`.`nombres`) AS `estudiante`, `ap`.`fecha_atencion` AS `fecha_atencion`, `ap`.`motivo` AS `motivo`, `ap`.`tipo_atencion` AS `tipo_atencion`, `ap`.`estado` AS `estado`, `der`.`apellidos_nombres` AS `derivado_por` FROM (((`atencion_psicologica` `ap` join `personal` `p` on(`ap`.`id_psicologo` = `p`.`id_personal`)) join `estudiantes` `e` on(`ap`.`id_estudiante` = `e`.`id_estudiante`)) left join `personal` `der` on(`ap`.`derivado_por` = `der`.`id_personal`)) ORDER BY `ap`.`fecha_atencion` DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estructura_organizacional`
--
DROP TABLE IF EXISTS `v_estructura_organizacional`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_estructura_organizacional`  AS SELECT `c`.`nombre` AS `coordinacion`, `c`.`tipo_coordinacion` AS `tipo_coordinacion`, `p`.`apellidos_nombres` AS `coordinador`, `p`.`email` AS `email_coordinador`, `a`.`nombre` AS `area`, group_concat(`cur`.`nombre` separator ', ') AS `cursos_area` FROM ((((`coordinaciones` `c` left join `personal` `p` on(`c`.`id_coordinador` = `p`.`id_personal`)) left join `areas_academicas` `a` on(`c`.`id_coordinacion` = `a`.`id_coordinacion`)) left join `cursos_areas` `ca` on(`a`.`id_area` = `ca`.`id_area`)) left join `cursos` `cur` on(`ca`.`id_curso` = `cur`.`id_curso`)) WHERE `c`.`estado` = 'ACTIVO' GROUP BY `c`.`id_coordinacion`, `a`.`id_area` ORDER BY `c`.`tipo_coordinacion` ASC, `a`.`nombre` ASC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estudiantes_actuales`
--
DROP TABLE IF EXISTS `v_estudiantes_actuales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_estudiantes_actuales`  AS SELECT `e`.`codigo_estudiante` AS `codigo_estudiante`, concat(`e`.`apellido_paterno`,' ',`e`.`apellido_materno`,', ',`e`.`nombres`) AS `nombre_completo`, `e`.`sexo` AS `sexo`, `e`.`fecha_nacimiento` AS `fecha_nacimiento`, `g`.`nombre` AS `grado`, `s`.`letra_seccion` AS `seccion`, `a`.`anio` AS `anio`, `e`.`apoderado_nombres` AS `apoderado_nombres`, `e`.`apoderado_celular` AS `apoderado_celular`, `e`.`apoderado_email` AS `apoderado_email`, `m`.`estado` AS `estado_matricula` FROM ((((`estudiantes` `e` join `matriculas` `m` on(`e`.`id_estudiante` = `m`.`id_estudiante`)) join `secciones` `s` on(`m`.`id_seccion` = `s`.`id_seccion`)) join `grados` `g` on(`s`.`id_grado` = `g`.`id_grado`)) join `anios_academicos` `a` on(`m`.`id_anio` = `a`.`id_anio`)) WHERE `m`.`estado` = 'ACTIVO' AND `a`.`estado` = 'ACTIVO' ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estudiantes_autenticacion`
--
DROP TABLE IF EXISTS `v_estudiantes_autenticacion`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_estudiantes_autenticacion`  AS SELECT `e`.`codigo_estudiante` AS `codigo_estudiante`, `e`.`dni` AS `dni`, concat(`e`.`apellido_paterno`,' ',`e`.`apellido_materno`,', ',`e`.`nombres`) AS `nombre_completo`, `e`.`password_temporal` AS `password_temporal`, `e`.`primer_acceso` AS `primer_acceso`, `e`.`fecha_ultimo_acceso` AS `fecha_ultimo_acceso`, `e`.`intentos_fallidos` AS `intentos_fallidos`, `e`.`cuenta_bloqueada` AS `cuenta_bloqueada`, `e`.`fecha_bloqueo` AS `fecha_bloqueo`, `g`.`nombre` AS `grado`, `s`.`letra_seccion` AS `seccion` FROM ((((`estudiantes` `e` join `matriculas` `m` on(`e`.`id_estudiante` = `m`.`id_estudiante`)) join `secciones` `s` on(`m`.`id_seccion` = `s`.`id_seccion`)) join `grados` `g` on(`s`.`id_grado` = `g`.`id_grado`)) join `anios_academicos` `a` on(`m`.`id_anio` = `a`.`id_anio`)) WHERE `m`.`estado` = 'ACTIVO' AND `a`.`estado` = 'ACTIVO' ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_notas_detalle`
--
DROP TABLE IF EXISTS `v_notas_detalle`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_notas_detalle`  AS SELECT `e`.`codigo_estudiante` AS `codigo_estudiante`, concat(`e`.`apellido_paterno`,' ',`e`.`apellido_materno`,', ',`e`.`nombres`) AS `estudiante`, `g`.`nombre` AS `grado`, `s`.`letra_seccion` AS `seccion`, `c`.`nombre` AS `curso`, `b`.`nombre` AS `bimestre`, `n`.`nota` AS `nota`, `n`.`observaciones` AS `observaciones`, `n`.`fecha_registro` AS `fecha_registro`, `p`.`apellidos_nombres` AS `registrado_por` FROM (((((((`notas` `n` join `matriculas` `m` on(`n`.`id_matricula` = `m`.`id_matricula`)) join `estudiantes` `e` on(`m`.`id_estudiante` = `e`.`id_estudiante`)) join `secciones` `s` on(`m`.`id_seccion` = `s`.`id_seccion`)) join `grados` `g` on(`s`.`id_grado` = `g`.`id_grado`)) join `cursos` `c` on(`n`.`id_curso` = `c`.`id_curso`)) join `bimestres` `b` on(`n`.`id_bimestre` = `b`.`id_bimestre`)) join `personal` `p` on(`n`.`registrado_por` = `p`.`id_personal`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_personal_por_cargo`
--
DROP TABLE IF EXISTS `v_personal_por_cargo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_personal_por_cargo`  AS SELECT `personal`.`cargo` AS `cargo`, count(0) AS `cantidad`, group_concat(concat(`personal`.`apellidos_nombres`,' (',`personal`.`email`,')') separator '; ') AS `personal_detalle` FROM `personal` WHERE `personal`.`estado` = 'ACTIVO' GROUP BY `personal`.`cargo` ORDER BY `personal`.`cargo` ASC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_tutorias_asignadas`
--
DROP TABLE IF EXISTS `v_tutorias_asignadas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_tutorias_asignadas`  AS SELECT `p`.`apellidos_nombres` AS `tutor`, `g`.`nombre` AS `grado`, `s`.`letra_seccion` AS `seccion`, `a`.`anio` AS `anio`, `t`.`fecha_asignacion` AS `fecha_asignacion`, count(`m`.`id_matricula`) AS `total_estudiantes` FROM (((((`asignacion_tutoria` `t` join `personal` `p` on(`t`.`id_personal` = `p`.`id_personal`)) join `secciones` `s` on(`t`.`id_seccion` = `s`.`id_seccion`)) join `grados` `g` on(`s`.`id_grado` = `g`.`id_grado`)) join `anios_academicos` `a` on(`t`.`id_anio` = `a`.`id_anio`)) left join `matriculas` `m` on(`s`.`id_seccion` = `m`.`id_seccion` and `a`.`id_anio` = `m`.`id_anio` and `m`.`estado` = 'ACTIVO')) WHERE `t`.`estado` = 'ACTIVO' GROUP BY `t`.`id_tutoria` ORDER BY `a`.`anio` DESC, `g`.`numero_grado` ASC, `s`.`letra_seccion` ASC ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `anios_academicos`
--
ALTER TABLE `anios_academicos`
  ADD PRIMARY KEY (`id_anio`),
  ADD UNIQUE KEY `anio` (`anio`);

--
-- Indices de la tabla `areas_academicas`
--
ALTER TABLE `areas_academicas`
  ADD PRIMARY KEY (`id_area`),
  ADD UNIQUE KEY `unique_area` (`nombre`),
  ADD KEY `id_coordinacion` (`id_coordinacion`);

--
-- Indices de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD PRIMARY KEY (`id_asignacion`),
  ADD UNIQUE KEY `unique_asignacion` (`id_personal`,`id_curso`,`id_seccion`,`id_anio`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `id_seccion` (`id_seccion`),
  ADD KEY `id_anio` (`id_anio`);

--
-- Indices de la tabla `asignaciones_secretaria`
--
ALTER TABLE `asignaciones_secretaria`
  ADD PRIMARY KEY (`id_asignacion_sec`),
  ADD UNIQUE KEY `unique_secretaria_area_anio` (`id_personal`,`area_asignada`,`id_anio`),
  ADD KEY `id_anio` (`id_anio`);

--
-- Indices de la tabla `asignacion_tutoria`
--
ALTER TABLE `asignacion_tutoria`
  ADD PRIMARY KEY (`id_tutoria`),
  ADD UNIQUE KEY `unique_tutor_seccion_anio` (`id_personal`,`id_seccion`,`id_anio`),
  ADD KEY `id_seccion` (`id_seccion`),
  ADD KEY `idx_tutoria_anio` (`id_anio`);

--
-- Indices de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD PRIMARY KEY (`id_asistencia`),
  ADD UNIQUE KEY `unique_asistencia` (`id_matricula`,`id_curso`,`fecha`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `registrado_por` (`registrado_por`),
  ADD KEY `idx_asistencias_fecha` (`fecha`);

--
-- Indices de la tabla `atencion_psicologica`
--
ALTER TABLE `atencion_psicologica`
  ADD PRIMARY KEY (`id_atencion`),
  ADD KEY `id_psicologo` (`id_psicologo`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `derivado_por` (`derivado_por`),
  ADD KEY `idx_atencion_psico_fecha` (`fecha_atencion`);

--
-- Indices de la tabla `auxiliares_educacion`
--
ALTER TABLE `auxiliares_educacion`
  ADD PRIMARY KEY (`id_auxiliar_edu`),
  ADD UNIQUE KEY `unique_auxiliar_edu_anio` (`id_personal`,`id_anio`),
  ADD KEY `id_anio` (`id_anio`);

--
-- Indices de la tabla `auxiliares_laboratorio`
--
ALTER TABLE `auxiliares_laboratorio`
  ADD PRIMARY KEY (`id_auxiliar_lab`),
  ADD UNIQUE KEY `unique_auxiliar_lab_anio` (`id_personal`,`id_laboratorio`,`id_anio`),
  ADD KEY `id_laboratorio` (`id_laboratorio`),
  ADD KEY `id_anio` (`id_anio`);

--
-- Indices de la tabla `bimestres`
--
ALTER TABLE `bimestres`
  ADD PRIMARY KEY (`id_bimestre`),
  ADD UNIQUE KEY `unique_bimestre_anio` (`id_anio`,`numero_bimestre`);

--
-- Indices de la tabla `coordinaciones`
--
ALTER TABLE `coordinaciones`
  ADD PRIMARY KEY (`id_coordinacion`),
  ADD UNIQUE KEY `unique_tipo_coordinacion` (`tipo_coordinacion`),
  ADD KEY `id_coordinador` (`id_coordinador`),
  ADD KEY `idx_coordinaciones_tipo` (`tipo_coordinacion`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `cursos_areas`
--
ALTER TABLE `cursos_areas`
  ADD PRIMARY KEY (`id_curso_area`),
  ADD UNIQUE KEY `unique_curso_area` (`id_curso`,`id_area`),
  ADD KEY `id_area` (`id_area`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD UNIQUE KEY `codigo_estudiante` (`codigo_estudiante`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD KEY `idx_estudiantes_codigo` (`codigo_estudiante`),
  ADD KEY `idx_estudiantes_nombres` (`apellido_paterno`,`apellido_materno`,`nombres`),
  ADD KEY `idx_estudiantes_dni` (`dni`),
  ADD KEY `idx_estudiantes_password` (`dni`,`password_hash`),
  ADD KEY `idx_estudiantes_token` (`token_recuperacion`);

--
-- Indices de la tabla `grados`
--
ALTER TABLE `grados`
  ADD PRIMARY KEY (`id_grado`),
  ADD UNIQUE KEY `unique_grado` (`numero_grado`);

--
-- Indices de la tabla `historial_accesos_estudiantes`
--
ALTER TABLE `historial_accesos_estudiantes`
  ADD PRIMARY KEY (`id_acceso`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `idx_historial_fecha` (`fecha_acceso`);

--
-- Indices de la tabla `laboratorios`
--
ALTER TABLE `laboratorios`
  ADD PRIMARY KEY (`id_laboratorio`),
  ADD UNIQUE KEY `unique_laboratorio` (`nombre`),
  ADD KEY `id_jefe_laboratorio` (`id_jefe_laboratorio`);

--
-- Indices de la tabla `log_notas`
--
ALTER TABLE `log_notas`
  ADD PRIMARY KEY (`id_log`);

--
-- Indices de la tabla `matriculas`
--
ALTER TABLE `matriculas`
  ADD PRIMARY KEY (`id_matricula`),
  ADD UNIQUE KEY `unique_estudiante_anio` (`id_estudiante`,`id_anio`),
  ADD KEY `id_seccion` (`id_seccion`),
  ADD KEY `idx_matriculas_anio` (`id_anio`);

--
-- Indices de la tabla `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id_nota`),
  ADD UNIQUE KEY `unique_nota` (`id_matricula`,`id_curso`,`id_bimestre`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `id_bimestre` (`id_bimestre`),
  ADD KEY `registrado_por` (`registrado_por`),
  ADD KEY `idx_notas_matricula_curso` (`id_matricula`,`id_curso`);

--
-- Indices de la tabla `personal`
--
ALTER TABLE `personal`
  ADD PRIMARY KEY (`id_personal`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_personal_dni` (`dni`),
  ADD KEY `idx_personal_email` (`email`),
  ADD KEY `idx_personal_cargo` (`cargo`);

--
-- Indices de la tabla `secciones`
--
ALTER TABLE `secciones`
  ADD PRIMARY KEY (`id_seccion`),
  ADD UNIQUE KEY `unique_grado_seccion` (`id_grado`,`letra_seccion`);

--
-- Indices de la tabla `seguimiento_psicologico`
--
ALTER TABLE `seguimiento_psicologico`
  ADD PRIMARY KEY (`id_seguimiento`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `id_psicologo` (`id_psicologo`),
  ADD KEY `idx_seguimiento_psico_estado` (`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `anios_academicos`
--
ALTER TABLE `anios_academicos`
  MODIFY `id_anio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `areas_academicas`
--
ALTER TABLE `areas_academicas`
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  MODIFY `id_asignacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asignaciones_secretaria`
--
ALTER TABLE `asignaciones_secretaria`
  MODIFY `id_asignacion_sec` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asignacion_tutoria`
--
ALTER TABLE `asignacion_tutoria`
  MODIFY `id_tutoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  MODIFY `id_asistencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `atencion_psicologica`
--
ALTER TABLE `atencion_psicologica`
  MODIFY `id_atencion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `auxiliares_educacion`
--
ALTER TABLE `auxiliares_educacion`
  MODIFY `id_auxiliar_edu` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `auxiliares_laboratorio`
--
ALTER TABLE `auxiliares_laboratorio`
  MODIFY `id_auxiliar_lab` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `bimestres`
--
ALTER TABLE `bimestres`
  MODIFY `id_bimestre` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `coordinaciones`
--
ALTER TABLE `coordinaciones`
  MODIFY `id_coordinacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `cursos_areas`
--
ALTER TABLE `cursos_areas`
  MODIFY `id_curso_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grados`
--
ALTER TABLE `grados`
  MODIFY `id_grado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial_accesos_estudiantes`
--
ALTER TABLE `historial_accesos_estudiantes`
  MODIFY `id_acceso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `laboratorios`
--
ALTER TABLE `laboratorios`
  MODIFY `id_laboratorio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `log_notas`
--
ALTER TABLE `log_notas`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `matriculas`
--
ALTER TABLE `matriculas`
  MODIFY `id_matricula` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notas`
--
ALTER TABLE `notas`
  MODIFY `id_nota` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal`
--
ALTER TABLE `personal`
  MODIFY `id_personal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `secciones`
--
ALTER TABLE `secciones`
  MODIFY `id_seccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `seguimiento_psicologico`
--
ALTER TABLE `seguimiento_psicologico`
  MODIFY `id_seguimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `areas_academicas`
--
ALTER TABLE `areas_academicas`
  ADD CONSTRAINT `areas_academicas_ibfk_1` FOREIGN KEY (`id_coordinacion`) REFERENCES `coordinaciones` (`id_coordinacion`);

--
-- Filtros para la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD CONSTRAINT `asignaciones_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_ibfk_3` FOREIGN KEY (`id_seccion`) REFERENCES `secciones` (`id_seccion`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_ibfk_4` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id_anio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `asignaciones_secretaria`
--
ALTER TABLE `asignaciones_secretaria`
  ADD CONSTRAINT `asignaciones_secretaria_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_secretaria_ibfk_2` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id_anio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `asignacion_tutoria`
--
ALTER TABLE `asignacion_tutoria`
  ADD CONSTRAINT `asignacion_tutoria_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignacion_tutoria_ibfk_2` FOREIGN KEY (`id_seccion`) REFERENCES `secciones` (`id_seccion`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignacion_tutoria_ibfk_3` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id_anio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD CONSTRAINT `asistencias_ibfk_1` FOREIGN KEY (`id_matricula`) REFERENCES `matriculas` (`id_matricula`) ON DELETE CASCADE,
  ADD CONSTRAINT `asistencias_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `asistencias_ibfk_3` FOREIGN KEY (`registrado_por`) REFERENCES `personal` (`id_personal`);

--
-- Filtros para la tabla `atencion_psicologica`
--
ALTER TABLE `atencion_psicologica`
  ADD CONSTRAINT `atencion_psicologica_ibfk_1` FOREIGN KEY (`id_psicologo`) REFERENCES `personal` (`id_personal`),
  ADD CONSTRAINT `atencion_psicologica_ibfk_2` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE,
  ADD CONSTRAINT `atencion_psicologica_ibfk_3` FOREIGN KEY (`derivado_por`) REFERENCES `personal` (`id_personal`);

--
-- Filtros para la tabla `auxiliares_educacion`
--
ALTER TABLE `auxiliares_educacion`
  ADD CONSTRAINT `auxiliares_educacion_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE,
  ADD CONSTRAINT `auxiliares_educacion_ibfk_2` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id_anio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `auxiliares_laboratorio`
--
ALTER TABLE `auxiliares_laboratorio`
  ADD CONSTRAINT `auxiliares_laboratorio_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE,
  ADD CONSTRAINT `auxiliares_laboratorio_ibfk_2` FOREIGN KEY (`id_laboratorio`) REFERENCES `laboratorios` (`id_laboratorio`) ON DELETE CASCADE,
  ADD CONSTRAINT `auxiliares_laboratorio_ibfk_3` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id_anio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bimestres`
--
ALTER TABLE `bimestres`
  ADD CONSTRAINT `bimestres_ibfk_1` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id_anio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `coordinaciones`
--
ALTER TABLE `coordinaciones`
  ADD CONSTRAINT `coordinaciones_ibfk_1` FOREIGN KEY (`id_coordinador`) REFERENCES `personal` (`id_personal`);

--
-- Filtros para la tabla `cursos_areas`
--
ALTER TABLE `cursos_areas`
  ADD CONSTRAINT `cursos_areas_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `cursos_areas_ibfk_2` FOREIGN KEY (`id_area`) REFERENCES `areas_academicas` (`id_area`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historial_accesos_estudiantes`
--
ALTER TABLE `historial_accesos_estudiantes`
  ADD CONSTRAINT `historial_accesos_estudiantes_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE;

--
-- Filtros para la tabla `laboratorios`
--
ALTER TABLE `laboratorios`
  ADD CONSTRAINT `laboratorios_ibfk_1` FOREIGN KEY (`id_jefe_laboratorio`) REFERENCES `personal` (`id_personal`);

--
-- Filtros para la tabla `matriculas`
--
ALTER TABLE `matriculas`
  ADD CONSTRAINT `matriculas_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE,
  ADD CONSTRAINT `matriculas_ibfk_2` FOREIGN KEY (`id_seccion`) REFERENCES `secciones` (`id_seccion`) ON DELETE CASCADE,
  ADD CONSTRAINT `matriculas_ibfk_3` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id_anio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notas`
--
ALTER TABLE `notas`
  ADD CONSTRAINT `notas_ibfk_1` FOREIGN KEY (`id_matricula`) REFERENCES `matriculas` (`id_matricula`) ON DELETE CASCADE,
  ADD CONSTRAINT `notas_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `notas_ibfk_3` FOREIGN KEY (`id_bimestre`) REFERENCES `bimestres` (`id_bimestre`) ON DELETE CASCADE,
  ADD CONSTRAINT `notas_ibfk_4` FOREIGN KEY (`registrado_por`) REFERENCES `personal` (`id_personal`);

--
-- Filtros para la tabla `secciones`
--
ALTER TABLE `secciones`
  ADD CONSTRAINT `secciones_ibfk_1` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id_grado`) ON DELETE CASCADE;

--
-- Filtros para la tabla `seguimiento_psicologico`
--
ALTER TABLE `seguimiento_psicologico`
  ADD CONSTRAINT `seguimiento_psicologico_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE,
  ADD CONSTRAINT `seguimiento_psicologico_ibfk_2` FOREIGN KEY (`id_psicologo`) REFERENCES `personal` (`id_personal`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
