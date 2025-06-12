# Sistema de Correos entreunosyceros

![enviador-correos](https://github.com/user-attachments/assets/41a45607-59dd-4e2e-8f14-bc0439a84b8e)

Este proyecto es un sistema web para el envío masivo de correos electrónicos, pensado para centros de cosas, empresas y cualquier organización que necesite gestionar campañas de email personalizadas a partir de archivos Excel.

Esto en realidad fue un proyecto para un centro que tenía como base de datos un archivo Excel (cosas de la vida!!) ... Y se trataba de un Excel de unas 2000 líneas. Una vez cargado sin problemas, este programa fué capaz de enviar en bloques de 100 los 2000 emails sin problema.

![email-enviado](https://github.com/user-attachments/assets/57176314-2f42-4070-bddd-eb4ba21e9851)

Este programa lo he probado en un servidor local y en en servidor web sin ningún problema.

## Características principales

![documentacion-envio-correos](https://github.com/user-attachments/assets/1687c7a5-c530-4ea1-b2b5-6daf649a6475)

- **Documentación integrada**: Acceso rápido a la documentación desde la interfaz.
- **Carga flexible de archivos Excel**: Detecta automáticamente columnas como Nombre, Email, Tratamientos, etc. Solo la columna "Nombre" es obligatoria.
- **Envío masivo de correos**: Permite seleccionar destinatarios, personalizar el mensaje y adjuntar imágenes.
- **Gestión de SMTP**: Configuración sencilla para cualquier proveedor (Gmail, Outlook, Ionos, etc). Compatible con SSL/TLS.
- **Editor visual de mensajes**: Redacta mensajes con formato, listas, enlaces, imágenes y emojis.
- **Filtrado por tratamientos**: Si el Excel tiene columna de tratamientos, puedes filtrar destinatarios fácilmente.
- **Soporte para archivos grandes**: Compatible con archivos Excel de muchas columnas y miles de registros.
- **Validación y compatibilidad**: El sistema informa sobre la compatibilidad del archivo y posibles problemas.
- **Gestión de usuarios**: Acceso protegido por login. Usuario y contraseña configurables.

## ¿Para qué sirve?

- Enviar promociones, felicitaciones o avisos a clientes de forma masiva y personalizada.
- Gestionar campañas de email marketing desde un entorno privado y seguro.
- Consultar y filtrar datos de clientes desde archivos Excel sin depender de servicios externos.

## Instalación y uso

1. **Clona el repositorio**
   ```bash
   git clone https://github.com/tuusuario/sistema-correos-entreunosyceros.git
   ```
2. **Instala dependencias PHP**
   ```bash
   cd sistema-correos-entreunosyceros
   composer install
   ```
3. **Configura el servidor web**
   - Puedes usar Apache, Nginx o el servidor de desarrollo incluido:
   ```bash
   php -S localhost:8080
   ```
4. **Accede al sistema**

![login](https://github.com/user-attachments/assets/74bb1240-0c34-4b78-886c-bed16bdaea16)

   - Usuario por defecto: `entreunosyceros`
   - Contraseña por defecto: `entreunosyceros`

## Estructura del proyecto

- `index.php` — Página principal y acceso al sistema
- `views/main.php` — Interfaz principal de gestión y envío de correos
- `documentacion_flexible.html` — Documentación completa y ejemplos
- `includes/` — Funciones de autenticación y utilidades
- `assets/js/` — Scripts de la interfaz
- `img/` — Imágenes y logotipo

## Requisitos

- PHP 7.4 o superior
- Composer
- Extensiones PHP: `mbstring`, `json`, `openssl`
- Servidor web (opcional)

## Seguridad

![configuracion-envio-correos](https://github.com/user-attachments/assets/4f182c80-ec74-4055-81b8-d5376d942a2a)

- Las contraseñas de usuario están cifradas.
- El acceso está protegido por login.
- No almacena contraseñas SMTP en texto plano (usa almacenamiento seguro).

## Créditos

Creado y documentado por [entreunosyceros](https://entreunosyceros.net) 2025

---

> **Nota:** Este sistema es funcional y está verificado para uso real. Consulta la documentación incluida para detalles avanzados y resolución de problemas.
