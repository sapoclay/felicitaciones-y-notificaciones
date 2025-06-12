<?php
// Verificar autenticación antes de procesar archivos
require_once 'includes/auth.php';

if (!estaAutenticado()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Sesión expirada. Por favor, inicie sesión nuevamente.',
        'redirect' => 'login.php'
    ]);
    exit;
}

header('Content-Type: application/json');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Función para limpiar archivos Excel antiguos
 */
function limpiarArchivosAntiguos($directorioBase, $mantenerDias = 1) {
    $patron = $directorioBase . '/clientes_*.xlsx';
    $archivos = glob($patron);
    $tiempoLimite = time() - ($mantenerDias * 24 * 60 * 60);
    $archivosEliminados = 0;
    
    foreach ($archivos as $archivo) {
        if (filemtime($archivo) < $tiempoLimite) {
            if (unlink($archivo)) {
                $archivosEliminados++;
            }
        }
    }
    
    return $archivosEliminados;
}

/**
 * Función para eliminar archivo Excel anterior del mismo día
 */
function eliminarArchivoAnterior($rutaArchivoNuevo) {
    if (file_exists($rutaArchivoNuevo)) {
        if (unlink($rutaArchivoNuevo)) {
            return true;
        }
    }
    return false;
}

try {
    if (!isset($_FILES['excelFile'])) {
        throw new Exception('No se ha recibido ningún archivo');
    }

    $file = $_FILES['excelFile'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo: ' . $file['error']);
    }

    // Verificar el tipo de archivo
    $mimeType = mime_content_type($file['tmp_name']);
    $validMimeTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel' // .xls
    ];

    if (!in_array($mimeType, $validMimeTypes)) {
        throw new Exception('El archivo debe ser un documento Excel (.xlsx)');
    }

    // Cargar el archivo Excel
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();
    
    /* 
     * COLUMNAS SOPORTADAS EN EL EXCEL:
     * - Código, Nombre, Nombre comercial, Dirección, C.I.F., Localidad, 
     *   Provincia, C.Postal, Pais, Teléfono, FAX, Email, Contacto, F. Alta, Tratamientos
     * 
     * COLUMNAS OBLIGATORIAS: Solo "Nombre" es obligatorio
     * COLUMNAS OPCIONALES: Todas las demás
     * NOTA ESPECIAL: La columna "Tratamientos" puede aparecer en cualquier posición
     */
    
    // Verificar que existe al menos la columna obligatoria "Nombre"
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
    
    $nombreEncontrado = false;
    $tratamientosEncontrado = false;
    $columnaTratamientos = null;
    
    // Buscar la columna "Nombre" (obligatoria) y "Tratamientos" (puede estar en cualquier posición)
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $cellValue = trim($worksheet->getCell($columnLetter . '1')->getValue());
        $cellValueLower = mb_strtolower($cellValue);
        
        // Verificar si es la columna "Nombre"
        $nombreVariants = ['nombre', 'name'];
        foreach ($nombreVariants as $variant) {
            if ($cellValueLower === $variant) {
                $nombreEncontrado = true;
                break;
            }
        }
        
        // Verificar si es la columna "Tratamientos"
        $tratamientosVariants = ['tratamientos', 'tratamiento', 'treatments', 'treatment'];
        foreach ($tratamientosVariants as $variant) {
            if ($cellValueLower === $variant) {
                $tratamientosEncontrado = true;
                $columnaTratamientos = $columnLetter;
                break;
            }
        }
    }
    
    // Solo validar que existe la columna "Nombre" (es la única obligatoria)
    if (!$nombreEncontrado) {
        throw new Exception("El archivo debe contener al menos una columna 'Nombre'. Esta es la única columna obligatoria.");
    }
    
    // Información adicional sobre las columnas encontradas
    $columnasDetectadas = [];
    if ($tratamientosEncontrado) {
        $columnasDetectadas[] = "Tratamientos encontrado en columna $columnaTratamientos";
    }

    // LIMPIEZA AUTOMÁTICA: Eliminar TODOS los archivos Excel anteriores
    // (Solo mantener el nuevo archivo que se está cargando)
    $archivosEliminados = limpiarArchivosAntiguos(__DIR__, 0); // 0 días = eliminar todos los archivos anteriores
    
    // Determinar la ruta del archivo objetivo
    $targetPath = __DIR__ . '/clientes_' . date('d-m-Y') . '.xlsx';
    
    // ELIMINAR ARCHIVO ANTERIOR: Si ya existe un archivo del mismo día, eliminarlo
    eliminarArchivoAnterior($targetPath);
    
    // Copiar el archivo a la ubicación final
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('No se pudo guardar el archivo en el servidor. Verifique permisos de escritura.');
    }

    // Establecer permisos de archivo
    chmod($targetPath, 0644);

    // Mensaje de respuesta incluyendo información de limpieza
    $mensaje = 'Archivo procesado correctamente';
    if ($archivosEliminados > 0) {
        $mensaje .= " ($archivosEliminados archivo(s) antiguo(s) eliminado(s))";
    }
    
    // Agregar información sobre columnas detectadas
    if (!empty($columnasDetectadas)) {
        $mensaje .= '. ' . implode(', ', $columnasDetectadas);
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'filename' => basename($targetPath),
        'cleanup_info' => [
            'files_deleted' => $archivosEliminados,
            'current_file' => basename($targetPath)
        ],
        'columns_detected' => [
            'nombre_found' => $nombreEncontrado,
            'tratamientos_found' => $tratamientosEncontrado,
            'tratamientos_column' => $columnaTratamientos
        ]
    ]);

} catch (Exception $e) {
    error_log('Error en cargar_excel.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
