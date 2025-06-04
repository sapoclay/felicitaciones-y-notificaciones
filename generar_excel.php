<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Cargar configuración
function loadConfig() {
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $config;
        }
    }
    return ['excel' => ['path' => __DIR__, 'filename' => 'empresas_{date}.xlsx']];
}

$config = loadConfig();
$excelConfig = $config['excel'];

// Crear nuevo documento de Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Definir encabezados
$headers = [
    'A1' => 'Código',
    'B1' => 'Nombre',
    'C1' => 'Nombre comercial',
    'D1' => 'Dirección',
    'E1' => 'CIF',
    'F1' => 'Localidad',
    'G1' => 'Provincia',
    'H1' => 'C. Postal',
    'I1' => 'País',
    'J1' => 'Teléfono',
    'K1' => 'Fax',
    'L1' => 'Email',
    'M1' => 'Fecha Alta'
];

// Aplicar encabezados y estilo
foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
    $sheet->getStyle($cell)->getFont()->setBold(true);
}

// Datos de ejemplo
$data = [
    [
        'EMP001', 'Perez Lolailo Manolo', 'TecnoChunda', 'Calle Más Mayor 123', 'B12345678',
        'Madrid', 'Madrid', '28001', 'España', '912345678', '912345679', 'gron93394@mail.com', '03-06-2024'
    ],
    [
        'EMP002', 'Trocipurcio Rostinguelrs Mario Jacinto', 'DistRápida', 'Arvenida Primogénica 45', 'A87654321',
        'Barcelona', 'Barcelona', '08001', 'España', '933456789', '933456780', 'txupamelon29393@mail.com', '03-06-1980'
    ],
    [
        'EMP003', 'Chanchurcio Grande María Antonia', 'SPermaT', 'Plaza del Sol Ecillo 7', 'X1234567Y',
        'Valencia', 'Valencia', '46001', 'España', '963789012', '963789013', 'info@andromenaguer.es', '03-06-1980'
    ],
    [
        'EMP004', 'Construcciones Martínez e Hijos', 'Pintamelón', 'Calle Juela 234', 'B98765432',
        'Sevilla', 'Sevilla', '41001', 'España', '954567890', '954567891', 'info@colifrondio.es', '03-06-1980'
    ],
    [
        'EMP005', 'Suministros Médicos López S.L.', 'MedioLópez', 'Avenida de la poca Salud 69', 'B45678901',
        'Bilbao', 'Vizcaya', '48001', 'España', '944789123', '944789124', 'contarto@massachupets.es', '03-06-1980'
    ],
    [
        'EMP006', 'Franchuncio Wend Benancio', 'MedioChancho', 'Avenida Zinganillo 345', 'F8754321',
        'Wisconsyn', 'Alpedrete', '08074', 'España', '933456789', '937777780', 'tikitoke393941@mail.com', '03-06-1970'
    ],
    
];

// Insertar datos
$row = 2;
foreach ($data as $rowData) {
    $col = 'A';
    foreach ($rowData as $value) {
        $sheet->setCellValue($col . $row, $value);
        $col++;
    }
    $row++;
}

// Autoajustar anchos de columna
foreach (range('A', 'M') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Crear el archivo
$writer = new Xlsx($spreadsheet);

// Procesar el nombre del archivo
$filename = str_replace('{date}', date('d-m-Y'), $excelConfig['filename']);
$fullPath = rtrim($excelConfig['path'], '/') . '/' . $filename;

// Intentar guardar el archivo
try {
    if (!is_dir($excelConfig['path'])) {
        throw new Exception("El directorio {$excelConfig['path']} no existe");
    }
    
    if (!is_writable($excelConfig['path'])) {
        throw new Exception("No hay permisos de escritura en {$excelConfig['path']}");
    }
    
    $writer->save($fullPath);
    echo "Archivo Excel creado correctamente en: " . $fullPath;
} catch (Exception $e) {
    echo "Error al crear el archivo Excel: " . $e->getMessage();
    exit(1);
}