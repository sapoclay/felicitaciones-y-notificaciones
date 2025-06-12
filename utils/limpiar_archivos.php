<?php
/**
 * Script de limpieza de archivos Excel antiguos
 * 
 * Este script puede ejecutarse:
 * 1. Manualmente desde línea de comandos: php utils/limpiar_archivos.php
 * 2. Como tarea programada (cron job)
 * 3. Desde la interfaz web (opcional)
 * 
 * Ejemplo de uso en cron (ejecutar diariamente a las 2:00 AM):
 * 0 2 * * * /usr/bin/php /var/www/html/PRACTICAS/utils/limpiar_archivos.php
 */

// Incluir funciones principales
require_once __DIR__ . '/../includes/functions.php';

// Configuración
$mantenerDias = isset($argv[1]) ? (int)$argv[1] : 3; // Por defecto, mantener archivos de los últimos 3 días
$modoVerbose = isset($argv[2]) && $argv[2] === '--verbose';

echo "🧹 LIMPIEZA AUTOMÁTICA DE ARCHIVOS EXCEL\n";
echo "==========================================\n";
echo "Configuración:\n";
echo "- Mantener archivos de los últimos $mantenerDias días\n";
echo "- Directorio: " . __DIR__ . "/..\n";
echo "- Fecha/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// Obtener información antes de la limpieza
$infoAntes = obtenerInfoArchivosExcel();
echo "📊 ESTADO ANTES DE LA LIMPIEZA:\n";
echo "- Total de archivos: {$infoAntes['total_archivos']}\n";
echo "- Espacio ocupado: " . formatearTamaño($infoAntes['espacio_total']) . "\n\n";

if ($modoVerbose && !empty($infoAntes['archivos'])) {
    echo "📋 ARCHIVOS ENCONTRADOS:\n";
    foreach ($infoAntes['archivos'] as $archivo) {
        $estado = $archivo['dias_antiguedad'] > $mantenerDias ? "❌ SERÁ ELIMINADO" : "✅ SE MANTIENE";
        echo "- {$archivo['nombre']} ({$archivo['dias_antiguedad']} días) - {$estado}\n";
    }
    echo "\n";
}

// Ejecutar limpieza
$archivosEliminados = limpiarArchivosExcelAntiguos($mantenerDias);

// Obtener información después de la limpieza
$infoDespues = obtenerInfoArchivosExcel();

echo "🎯 RESULTADO DE LA LIMPIEZA:\n";
echo "- Archivos eliminados: $archivosEliminados\n";
echo "- Archivos restantes: {$infoDespues['total_archivos']}\n";
echo "- Espacio liberado: " . formatearTamaño($infoAntes['espacio_total'] - $infoDespues['espacio_total']) . "\n";
echo "- Espacio actual: " . formatearTamaño($infoDespues['espacio_total']) . "\n\n";

if ($archivosEliminados > 0) {
    echo "✅ Limpieza completada exitosamente.\n";
} else {
    echo "ℹ️  No se encontraron archivos antiguos para eliminar.\n";
}

echo "==========================================\n";

/**
 * Función auxiliar para formatear tamaños de archivo
 */
function formatearTamaño($bytes) {
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $exponente = 0;
    
    while ($bytes >= 1024 && $exponente < count($unidades) - 1) {
        $bytes /= 1024;
        $exponente++;
    }
    
    return round($bytes, 2) . ' ' . $unidades[$exponente];
}

// Si se ejecuta desde web (GET o POST), devolver JSON
$esWebRequest = false;

// Manejar solicitudes GET
if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
    $esWebRequest = true;
}

// Manejar solicitudes POST (desde JavaScript)
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data && isset($data['action']) && $data['action'] === 'limpiar') {
        $esWebRequest = true;
        // Usar configuración del POST si está disponible
        if (isset($data['mantener_dias'])) {
            $mantenerDias = (int)$data['mantener_dias'];
        }
    }
    
    // Manejar solicitudes FormData (desde navigator.sendBeacon)
    if (isset($_POST['action']) && $_POST['action'] === 'limpiar') {
        $esWebRequest = true;
        if (isset($_POST['mantener_dias'])) {
            $mantenerDias = (int)$_POST['mantener_dias'];
        }
    }
}

if ($esWebRequest) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'archivos_eliminados' => $archivosEliminados,
        'archivos_restantes' => $infoDespues['total_archivos'],
        'espacio_liberado' => $infoAntes['espacio_total'] - $infoDespues['espacio_total'],
        'espacio_actual' => $infoDespues['espacio_total'],
        'mensaje' => $archivosEliminados > 0 ? 
            "Limpieza completada: $archivosEliminados archivo(s) eliminado(s)" : 
            "No se encontraron archivos antiguos para eliminar"
    ]);
    exit;
}
?>
