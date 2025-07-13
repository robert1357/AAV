# Aula Virtual - Sistema de Gestión Educativa

## Descripción
Sistema web completo para la gestión educativa de instituciones educativas, desarrollado en PHP con MySQL.

## Características
- Sistema de autenticación por roles
- Gestión de estudiantes, docentes y personal
- Registro de calificaciones y asistencia
- Subida de materiales educativos
- Reportes académicos y administrativos
- Panel de control para diferentes tipos de usuario

## Roles del Sistema
- **Administrador**: Gestión completa del sistema
- **Director**: Supervisión académica y administrativa
- **Jefe de Laboratorio**: Gestión de equipos y laboratorios
- **Coordinadores**: Gestión por áreas académicas
- **Docentes**: Gestión de cursos y calificaciones
- **Estudiantes**: Acceso a materiales y calificaciones
- **Personal de Apoyo**: Funciones específicas según cargo

## Instalación
1. Configurar servidor web (Apache/Nginx)
2. Crear base de datos MySQL
3. Importar estructura desde `database/schema.sql`
4. Configurar conexión en `config/database.php`
5. Establecer permisos de escritura en directorios de uploads

## Configuración
Editar archivos de configuración:
- `config/database.php`: Configuración de base de datos
- `config/config.php`: Configuraciones generales

## Estructura de Directorios
```
aula_virtual/
├── admin/          # Panel administrativo
├── auth/           # Autenticación
├── assets/         # Recursos estáticos
├── config/         # Configuraciones
├── includes/       # Archivos compartidos
├── uploads/        # Archivos subidos
├── api/            # APIs REST
├── reports/        # Reportes del sistema
└── [roles]/        # Directorios por rol
```

## Tecnologías
- PHP 8.0+
- MySQL 8.0+
- Bootstrap 5
- jQuery 3.6
- HTML5/CSS3

## Licencia
Proyecto educativo - Uso académico

## Soporte
Para soporte técnico, contactar al administrador del sistema.