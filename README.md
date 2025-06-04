# Sistema de envío de correos 

Este sistema permite enviar correos electrónicos de felicitación y promociones a clientes en su fecha de alta. El sistema lee los datos desde un archivo Excel y permite enviar correos personalizados con imágenes adjuntas.

> **Nota:** el archivo generar_excel solo ha sido creado para crear un archivo xlsx de ejemplo. De forma automática los > datos se tomarán del archivo empresas_[dd-mm-YYYY].xlsx que debe situarse en el directorio del proyecto. También se   > puede seleccionar desde el selector de la configuración del programa dentro de la interfaz.
> Desde el directorio de la aplicación con: php generar_excel.php debería ejecutarse correctamente el archivo y generar > el .xlsx de forma automática.

## Requisitos del sistema

### Requisitos de servidor
- PHP 7.4 o superior
- Servidor web (Apache/Nginx)
- Extensiones PHP requeridas:
  - php-mbstring
  - php-zip
  - php-xml
  - php-gd
  - php-mysql
  - php-curl
  - php-openssl (para conexiones SMTP seguras)

### Dependencias (gestionadas por composer)
- PHPMailer/PHPMailer
- PHPOffice/PhpSpreadsheet
- Otras dependencias están listadas en `composer.json`

## Instalación

1. Clonar el repositorio o copiar los archivos al servidor web
2. Ejecutar:
```bash
composer install
```
En Windows es necesario descargar composer desde su [sitio web](https://getcomposer.org/)
3. Asegurarse de que los permisos de archivos son correctos:
```bash
chmod 755 -R /ruta/al/proyecto
chmod 777 -R /ruta/al/proyecto/archivos_temporales
chmod 666 /ruta/al/proyecto/config.json
```

## Estructura de Archivos

- `index.php` - Archivo principal de la aplicación
- `generar_excel.php` - Script para generar el archivo Excel de ejemplo
- `cargar_excel.php` - Script para procesar la carga de archivos Excel
- `update_config.php` - Script para actualizar la configuración SMTP
- `config.json` - Archivo de configuración del servidor SMTP
- `styles.css` - Estilos de la aplicación
- `empresas_[fecha].xlsx` - Archivo Excel con los datos de los clientes

## Configuración

### Configuración del servidor SMTP
La configuración SMTP se gestiona a través de una interfaz gráfica en el panel lateral de la aplicación. Los parámetros configurables son:

- Servidor SMTP (host)
- Usuario SMTP (username)
- Email del remitente (from_email)
- Contraseña (password)
- Puerto (port)
  - 465 para SSL
  - 587 para TLS
- Nombre del remitente (from_name)
- Seguridad (secure)
  - SSL
  - TLS

Ejemplo de configuración en `config.json`:
```json
{
    "smtp": {
        "host": "mail.tudominio.com",
        "username": "tu@email.com",
        "password": "tu_contraseña",
        "port": 465,
        "from_email": "tu@email.com",
        "from_name": "Tu Nombre",
        "secure": "ssl"
    }
}
```

### Estructura del Excel
El archivo Excel debe contener las siguientes columnas:
- Código (columna A)
- Nombre (columna B, formato: "Apellido1 Apellido2 Nombre")
- Email (columna L)
- Fecha Alta (columna M, formato: "dd-mm-yyyy")

## Características

1. **Panel de Configuración**
   - Interfaz gráfica para configuración SMTP
   - Selector de archivos Excel con validación
   - Guardado automático de configuración
   - Visualización de campos requeridos

2. **Selección de Destinatarios**
   - Selección múltiple de destinatarios
   - Botón "Seleccionar Todos"
   - Muestra información detallada de cada destinatario
   - Filtrado automático por fecha de alta

3. **Envío de Correos**
   - Soporte para HTML en el contenido
   - Personalización con nombre del destinatario
   - Barra de progreso en tiempo real
   - Gestión de errores y notificaciones
   - Reintento automático en caso de fallo

4. **Gestión de Imágenes**
   - Permite adjuntar hasta 5 imágenes
   - Previsualización de imágenes
   - Eliminación individual de imágenes
   - Validación de tipos de archivo
   - Comprobación de tamaño máximo

5. **Notificaciones**
   - Mensajes de éxito/error
   - Notificaciones de progreso
   - Animaciones suaves
   - Tiempo de desvanecimiento automático

## Uso

1. Configuración inicial:
   - Acceder al panel lateral (botón ☰)
   - Configurar los datos del servidor SMTP
   - Subir el archivo Excel de datos

2. Preparación del archivo Excel:
   - Usar el formato especificado
   - Guardar en formato .xlsx
   - Asegurar que las fechas estén en formato dd-mm-yyyy

3. Envío de correos:
   - Seleccionar destinatarios
   - Escribir asunto y mensaje
   - Opcionalmente adjuntar imágenes
   - Enviar y monitorear el progreso

## Seguridad
- Validación de tipos de archivo para imágenes
- Sanitización de datos de entrada
- Protección contra inyección de código
- Manejo seguro de sesiones
- Conexión SMTP segura (SSL/TLS)
- Protección contra CSRF
- Validación de correos electrónicos
- Límite de tamaño en archivos

## Solución de problemas

1. **Error de permisos**
   - Verificar que config.json tiene permisos 666
   - Verificar permisos en el directorio de subidas
   - Comprobar permisos del usuario web

2. **Errores de SMTP**
   - Verificar credenciales SMTP
   - Comprobar configuración SSL/TLS
   - Verificar puertos (465 para SSL, 587 para TLS)
   - Comprobar configuración del firewall
   - Verificar que el email del remitente coincide con el usuario SMTP

3. **Problemas con Excel**
   - Verificar formato de fecha (dd-mm-yyyy)
   - Comprobar estructura de columnas
   - Asegurar que es formato .xlsx

4. **Problemas con imágenes**
   - Verificar límites de tamaño de archivo en PHP
   - Comprobar extensiones PHP necesarias
   - Verificar permisos de escritura temporales

## Mantenimiento
- Revisar logs de errores PHP
- Monitorear espacio en disco
- Verificar permisos de archivos
- Actualizar dependencias regularmente
- Hacer copias de seguridad de config.json

