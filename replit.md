# Aula Virtual - Sistema de Gestión Educativa

## Overview

Este es un sistema de aula virtual desarrollado en PHP con una arquitectura basada en roles que permite la gestión académica completa. El sistema está diseñado para instituciones educativas con diferentes niveles de acceso según el cargo del usuario. Se ha implementado con procedimientos almacenados, triggers y transacciones para asegurar la integridad de los datos y optimizar el rendimiento.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture
- **Tecnología**: HTML5, CSS3, JavaScript con jQuery
- **Framework CSS**: Bootstrap 5 para diseño responsivo
- **Arquitectura**: Estructura modular con archivos separados por roles
- **Patrón de diseño**: Separación de concernientes with includes comunes (header, footer, navbar)
- **APIs**: Endpoints dedicados para operaciones AJAX y carga dinámica de datos

### Backend Architecture
- **Lenguaje**: PHP 8.0+ con PDO para conexiones de base de datos
- **Patrón**: MVC implícito con separación de lógica por carpetas de roles
- **Base de datos**: MySQL 8.0+ con procedimientos almacenados y triggers
- **Autenticación**: Sistema basado en sesiones PHP con roles definidos por campo 'cargo'
- **Transacciones**: Uso extensivo de transacciones para operaciones críticas
- **Logging**: Sistema de logs para auditoría y debugging

### Estructura de Directorios
```
aula_virtual/
├── config/           # Configuraciones del sistema
├── includes/         # Componentes comunes reutilizables
├── assets/          # Recursos estáticos (CSS, JS, imágenes)
├── uploads/         # Archivos subidos por usuarios
├── admin/           # Panel administrativo
├── director/        # Panel directivo
├── jefe_laboratorio/ # Panel jefe de laboratorio
└── coordinador_ciencias/ # Panel coordinador de ciencias
```

## Key Components

### 1. Sistema de Autenticación y Roles
- **Problema**: Necesidad de diferentes niveles de acceso según el cargo
- **Solución**: Sistema basado en roles definidos por el campo 'cargo' en tabla 'personal'
- **Roles principales**:
  - **ADMINISTRADOR**: Acceso total al sistema
  - **DIRECTOR**: Gestión académica y administrativa
  - **JEFE_LABORATORIO**: Gestión de equipos y laboratorios
  - **COORDINADOR_CIENCIAS**: Coordinación académica específica

### 2. Gestión de Usuarios
- **Tabla 'personal'**: Gestiona todos los usuarios del sistema
- **Tabla 'estudiantes'**: Gestiona específicamente las cuentas estudiantiles
- **Funcionalidades**: Creación, edición, eliminación y restablecimiento de contraseñas

### 3. Gestión Académica
- **Tabla 'anios_academicos'**: Períodos académicos
- **Tabla 'bimestres'**: Períodos de evaluación
- **Tabla 'evaluaciones_docentes'**: Sistema de evaluación docente
- **Funcionalidades**: Reportes académicos, estadísticas institucionales

### 4. Sistema de Archivos
- **Uploads organizados por tipo**:
  - `materials/`: Materiales de curso
  - `assignments/`: Tareas subidas
  - `profile_pics/`: Fotos de perfil
- **Gestión de avatares**: Sistema de fotos de usuarios

## Data Flow

### Flujo de Autenticación
1. Usuario ingresa credenciales
2. Sistema valida contra tabla 'personal' o 'estudiantes'
3. Se establece sesión con rol basado en campo 'cargo'
4. Redirección al panel correspondiente según rol

### Flujo de Gestión de Datos
1. **Administrador**: Acceso completo a todas las tablas
2. **Director**: Acceso a datos académicos y de personal
3. **Roles específicos**: Acceso limitado a sus áreas de responsabilidad

## External Dependencies

### Frontend
- **Bootstrap**: Framework CSS para diseño responsivo
- **jQuery**: Biblioteca JavaScript para interactividad
- **Font Awesome**: Iconos (implícito en el uso de clases de iconos)

### Backend
- **PHP**: Lenguaje servidor
- **MySQL**: Base de datos relacional
- **PDO/MySQLi**: Conectores de base de datos

## Deployment Strategy

### Estructura de Configuración
- **config/database.php**: Configuración de conexión a base de datos
- **config/config.php**: Configuraciones generales del sistema
- **Separación de ambientes**: Configuraciones centralizadas para fácil despliegue

### Consideraciones de Seguridad
- Sistema de roles granular
- Validación de formularios client-side y server-side
- Gestión de sesiones PHP
- Separación de archivos subidos por tipo

### Escalabilidad
- Arquitectura modular permite agregar nuevos roles fácilmente
- Sistema de includes evita duplicación de código
- Estructura de carpetas organizada por funcionalidad

### Backup y Mantenimiento
- Funcionalidad de backup integrada en panel administrativo
- Logs de actividad del sistema
- Monitoreo de actividad de usuarios

## Technical Notes

- El sistema utiliza procedimientos almacenados (sp_resetear_password_estudiante)
- Paleta de colores verde consistente definida en variables CSS
- Diseño responsivo con breakpoints Bootstrap
- Validación de formularios con confirmación de eliminaciones
- Auto-cierre de alertas después de 5 segundos
- Toggle de sidebar para dispositivos móviles