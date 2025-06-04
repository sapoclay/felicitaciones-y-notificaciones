<?php
header('Content-Type: application/json');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

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
    
    // Verificar la estructura del archivo (insensible a mayúsculas/minúsculas)
    $requiredHeaders = [
        'A1' => 'Código',
        'B1' => 'Nombre',
        'L1' => 'Email',
        'N1' => 'Fecha Alta'
    ];

    foreach ($requiredHeaders as $cell => $expectedValue) {
        $actualValue = $worksheet->getCell($cell)->getValue();
        if (mb_strtolower(trim($actualValue)) !== mb_strtolower($expectedValue)) {
            throw new Exception("El archivo no tiene el formato correcto. Se esperaba '$expectedValue' en $cell, pero se encontró '" . $actualValue . "'.");
        }
    }

    // Copiar el archivo a la ubicación final
    $targetPath = __DIR__ . '/empresas_' . date('d-m-Y') . '.xlsx';
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('No se pudo guardar el archivo en el servidor. Verifique permisos de escritura.');
    }

    // Establecer permisos de archivo
    chmod($targetPath, 0644);

    echo json_encode([
        'success' => true,
        'message' => 'Archivo procesado correctamente'
    ]);

} catch (Exception $e) {
    error_log('Error en cargar_excel.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
