# 📧 Sistema de envío de correos masivos

Sistema profesional para envío masivo de correos electrónicos con imágenes HTML incrustadas, desarrollado en PHP con arquitectura MVC.

## 🎯 Características principales

### ✨ Funcionalidades base
- **Editor WYSIWYG avanzado**: Editor visual TinyMCE con formato de texto e inserción de imágenes
- **Carga automática de Excel**: Procesa archivos con formato `empresas_DD-MM-YYYY.xlsx`
- **SMTP múltiple**: Compatible con Gmail, Outlook y servidores personalizados
- **Progreso en tiempo real**: Seguimiento AJAX del proceso de envío
- **Filtros por tratamiento**: Segmentación de envíos por tipos de servicio

### 🚀 Mejoras implementadas (Junio 2025)
- **✅ Alineación perfecta de imágenes**: Las imágenes del editor respetan la alineación configurada usando tablas HTML para máxima compatibilidad con todos los clientes de correo electrónico
- **✅ Imágenes embebidas sin duplicados**: Eliminación de adjuntos tradicionales redundantes, manteniendo solo imágenes embebidas con CID únicos
- **✅ Enlaces clickeables funcionales**: Las imágenes adjuntas incluyen enlaces completamente funcionales y clickeables
- **✅ Prevención de reenvíos (PRG)**: Implementación del patrón Post-Redirect-Get para evitar el reenvío accidental de formularios al recargar la página
- **✅ Corrección PHP 8+**: Resolución de advertencias de deprecación relacionadas con valores nulos en expresiones regulares
- **🆕 Restricciones de tamaño optimizadas**: Límite máximo de 2MB por imagen para compatibilidad con servidores en la nube, con validaciones tanto en cliente como servidor
- **🆕 Sistema unificado de imágenes**: Las imágenes del editor ahora se comportan exactamente igual que las adjuntas (embebidas con CID), manteniendo la alineación configurada
- **✅ Corrección crítica completada**: Resuelto completamente el problema de contenido perdido cuando el mensaje contenía imágenes del editor. La nueva implementación con sistema de placeholders únicos garantiza la conservación total del contenido (ver `CORRECCION_CUERPO_VACIO.md`)
- **✅ Redimensionado fiel de imágenes**: Las imágenes del editor mantienen el tamaño (width/height) definido por el usuario en el email final, además de la alineación
- **🪲 Bugfix**: Corregido un error donde el redimensionado se perdía si la imagen tenía alineación personalizada (right/left/center)

## 🛠️ Tecnologías

- **Backend**: PHP 7.4+ / PHP 8+, PHPMailer 6.x, PhpSpreadsheet
- **Frontend**: HTML5, CSS3, JavaScript ES6, TinyMCE 6
- **Arquitectura**: MVC (Model-View-Controller)
- **Compatibilidad**: Todos los clientes de correo electrónico (Outlook, Gmail, Apple Mail, etc.)

## 📁 Estructura

```
├── index.php              # Punto de entrada
├── process.php            # Controlador principal
├── config.json            # Configuración SMTP
├── includes/functions.php # Lógica de negocio
├── views/main.php         # Interfaz usuario
├── assets/js/main.js      # JavaScript frontend
└── utils/generar_excel.php # Generador Excel de ejemplo
```

## 📊 Formato Excel Requerido

Archivo: `empresas_DD-MM-YYYY.xlsx` con 15 columnas (A-O):

| Col | Campo | Requerido | Ejemplo |
|-----|-------|-----------|---------|
| A   | Código | ✅ | EMP001 |
| B   | Nombre | ✅ | Juan Pérez García |
| C   | Nombre Comercial | - | Mi Empresa S.L. |
| D   | Dirección | - | Calle Mayor 123 |
| E   | CIF | - | B12345678 |
| F   | Localidad | - | Madrid |
| G   | Provincia | - | Madrid |
| H   | C. Postal | - | 28001 |
| I   | País | - | España |
| J   | Teléfono | - | 912345678 |
| K   | Fax | - | 912345679 |
| L   | **Email** | ✅ | juan@empresa.com |
| M   | Contacto | - | 684551555 |
| N   | **Fecha Alta** | ✅ | 05-06-2025 |
| O   | **Tratamientos** | ✅ | Estética, Peluquería |

## ⚙️ Instalación

1. **Instalar dependencias**
```bash
git clone https://github.com/sapoclay/felicitaciones-y-notificaciones.git
cd felicitaciones-y-promociones
composer install
```
Si usas windows, composer se debe [descargar desde su página web](https://getcomposer.org/)

2. **Generar Excel ejemplo** (opcional)
```bash
php utils/generar_excel.php
```
Si es que no tienes el archivo excel sobre el que trabajar

3. **Configurar SMTP** - Configuración del archivo `config.json`. Esto se puede configurar desde la interfaz del programa:
```json
{
    "smtp": {
        "host": "smtp.gmail.com",
        "username": "tu@email.com", 
        "password": "tu_password",
        "port": 587,
        "secure": "tls",
        "from_email": "tu@email.com",
        "from_name": "Tu Nombre"
    }
}
```

## 🚀 Uso

1. Abrir `index.php` en navegador
2. Configurar SMTP (panel lateral ☰)
3. Cargar Excel o generar automáticamente
4. Escribir mensaje con editor
5. Adjuntar imágenes con enlaces
6. Filtrar destinatarios por tratamiento
7. Enviar y monitorear progreso

## 🖼️ Funcionalidad imágenes

- **Base64**: Imágenes incrustadas directamente en HTML
- **Enlaces**: URLs opcionales por imagen 
- **Responsive**: Se adapta automáticamente
- **Compatibilidad**: Funciona en todos los clientes de correo (al menos en todos lo que he probado)
- **🆕 Restricciones de tamaño**: Máximo 2MB por imagen para compatibilidad con servidores en la nube
- **🆕 Validación dual**: Control de tamaño tanto en JavaScript (cliente) como PHP (servidor)
- **🆕 Sistema unificado**: Las imágenes del editor se procesan como adjuntos embebidos igual que las tradicionales, preservando la alineación
- **🆕 Redimensionado fiel**: El tamaño de la imagen (ancho/alto) definido en el editor se respeta en el email final, incluso si la imagen está alineada a la derecha o izquierda

### Limitaciones de Tamaño
El sistema ahora incluye restricciones de 2MB por imagen para garantizar compatibilidad con servicios de hosting en la nube:
- **Validación del cliente**: JavaScript previene la carga de archivos grandes antes del procesamiento
- **Validación del servidor**: PHP verifica el tamaño tanto de archivos adjuntos como imágenes del editor
- **Mensajes informativos**: La interfaz informa claramente sobre las restricciones de tamaño

## 🔧 Configuración SMTP

**Gmail** (requiere contraseña de aplicación. Hay que habilitar en la configuración de la cuenta de Gmail que se pueda utilizar esa cuenta con cuentas de terceros o no seguras ....o algo así):
```json
{"host": "smtp.gmail.com", "port": 587, "secure": "tls"}
```

**Outlook**:
```json
{"host": "smtp-mail.outlook.com", "port": 587, "secure": "tls"}
```

**Servidor personalizado**:
```json
{"host": "mail.tudominio.com", "port": 465, "secure": "ssl"}
```

## 🔍 Solución problemas

- **Excel no carga**: Verificar nombre `empresas_DD-MM-YYYY.xlsx` y 15 columnas
- **Error SMTP**: Comprobar credenciales y puerto (587/TLS o 465/SSL)
- **🆕 Imágenes demasiado grandes**: El sistema ahora rechaza imágenes mayores a 2MB automáticamente
- **🆕 Configuración del servidor**: Ya no es necesario modificar `upload_max_filesize` o `post_max_size` en PHP - el sistema maneja las restricciones internamente

### Migración desde versiones anteriores
Si actualizas desde una versión anterior que dependía de configuraciones PHP modificadas:
1. Las nuevas validaciones son automáticas y no requieren cambios de configuración
2. El sistema mantendrá compatibilidad con configuraciones existentes como respaldo
3. Se recomienda probar el envío con imágenes de diferentes tamaños para verificar el funcionamiento

## ✨ Desarrolladores ✨ 

**Javier** - Backend (modelo) | **Michel** - Frontend (vista) | **[entreunosyceros](https://entreunosyceros.net)** - Arquitectura y desarrollo (modelo, vista, controlador)