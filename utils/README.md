# 🧰 Utilidades del Sistema

Este directorio contiene herramientas auxiliares para el mantenimiento y gestión del sistema de envío de correos masivos.

## 📁 Archivos Incluidos

### 🧹 Sistema de Limpieza Automática Simplificado
- **`limpiar_archivos.php`** - Script principal de limpieza automática de archivos Excel
- **`SISTEMA_SIMPLIFICADO_SIN_CRON.md`** - Documentación completa del sistema simplificado (SIN cron jobs)

### 📊 Generación de Datos
- **`generar_excel.php`** - Generador de archivos Excel de ejemplo para pruebas

---

## 🚀 Uso del Sistema Simplificado

### Limpieza Automática (SIN Cron Jobs)
```bash
# Limpieza manual (si es necesaria)
php limpiar_archivos.php 0  # Eliminar TODOS los archivos

# El sistema funciona automáticamente:
# ✅ Al cargar archivos -> elimina todos los anteriores
# ✅ Al cerrar navegador -> elimina todos los archivos
```

### Generar Archivo Excel de Prueba
```bash
php generar_excel.php
```

---

## 📋 Características del Sistema Simplificado

### ✅ Funcionalidades Activas
- **Limpieza al cargar archivos** - Elimina TODOS los archivos anteriores automáticamente
- **Limpieza al cerrar navegador** - Elimina TODOS los archivos al cerrar pestaña/ventana
- **Sin configuración externa** - Funciona sin cron jobs ni privilegios de administrador
- **Validación de seguridad** - Solo elimina archivos con patrón `clientes_DD-MM-YYYY.xlsx`

### 🛡️ Características de Seguridad
- Solo elimina archivos `clientes_DD-MM-YYYY.xlsx`
- Validación de formato con regex
- Logs de auditoría completos
- Servidor siempre limpio automáticamente

---

