<?php

// Establecer zona horaria correcta
date_default_timezone_set('Europe/Madrid');

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Cargar configuración
function loadConfig() {
    $configFile = __DIR__ . '/../config.json';
    $defaultConfig = [
        'excel' => [
            'path' => __DIR__ . '/..',
            'filename' => 'empresas_{date}.xlsx'
        ]
    ];

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Fusionar con los valores predeterminados
            if (!isset($config['excel'])) {
                $config['excel'] = $defaultConfig['excel'];
            } else {
                $config['excel'] = array_merge($defaultConfig['excel'], $config['excel']);
            }
            return $config;
        }
    }
    return $defaultConfig;
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
    'M1' => 'Contacto',
    'N1' => 'Fecha Alta',
    'O1' => 'Tratamientos'
];

// Aplicar encabezados y estilo
foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
    $sheet->getStyle($cell)->getFont()->setBold(true);
}

// Datos de ejemplo
$data = [
    [
        'EMP003', 'Barreiro Algomás Javier', 'Cangas (y alrededores)', 'Plaza del Sol Ecillo 7', 'X1234567Y',
        'Valencia', 'Valencia', '46001', 'España', '963789012', '963789013', 'jronhaquejronha@mail.com', '684551555', '06-06-1980', 'Peluquería'
    ],
    [
        'EMP001', 'Pruebando Ando María José', 'TecnoChunda', 'Calle Más Mayor 123', 'B12345678',
        'Madrid', 'Madrid', '28001', 'España', '912345678', '912345679', 'sudaquetesuda@mail.com', '684551555','06-06-2024', 'Peluquería, Estética, Masajes'
    ],
    [
        'EMP002', 'Trocipurcio Rostinguelrs Mario Jacinto', 'DistRápida', 'Arvenida Primogénica 45', 'A87654321',
        'Barcelona', 'Barcelona', '08001', 'España', '933456789', '933456780', 'chanchunai@mail.com','684551555', '06-06-1980', 'Manicura, Estética'
    ],
    [
        'EMP004', 'Construcciones Martínez e Hijos', 'Pintamelón', 'Calle Juela 234', 'B98765432',
        'Sevilla', 'Sevilla', '41001', 'España', '954567890', '954567891', 'info@colifrondio.es', '684551555', '06-06-1980', 'Masajes, Peluquería'
    ],
    [
        'EMP005', 'Suministros Médicos López S.L.', 'MedioLópez', 'Avenida de la poca Salud 69', 'B45678901',
        'Bilbao', 'Vizcaya', '48001', 'España', '944789123', '944789124', 'contarto@massachupets.es', '684551555', '03-06-1980', 'Pedicura, Peluquería, Estética'
    ],
    [
        'EMP006', 'Franchuncio Wend Benancio', 'MedioChancho', 'Avenida Zinganillo 345', 'F8754321',
        'Wisconsyn', 'Alpedrete', '08074', 'España', '933456789', '937777780', 'tikitoke393941@mail.com', '684551555', '03-06-1970', 'Estética'
    ],
    [
        'EMP007', 'Nada Nada NADA', 'DistRápida', 'Arvenida Primogénica 45', 'A87654321',
        'Barcelona', 'Barcelona', '08001', 'España', '933456789', '933456780', '','684551555', '05-06-1980', 'Peluquería, Estética, Masajes'
    ],
    [
        'EMP008', 'Awakanome Edeumerito Mondongo', 'Mondon-Guillo', 'Camiño Corto 5', 'A87874321',
        'Castilla', 'Alcafran', '08301', 'España', '9334533289', '934456780', 'mondongo@duro.com','684551500', '05-06-1980', 'Drenaje, Estética, Masajes'
    ]
    
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
foreach (range('A', 'O') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Crear el archivo
$writer = new Xlsx($spreadsheet);

// Procesar el nombre del archivo
$filename = str_replace('{date}', date('d-m-Y'), $excelConfig['filename']);
$fullPath = rtrim($excelConfig['path'], '/') . '/' . $filename;

// Intentar guardar el archivo
try {
    // Verificar y crear el directorio si es necesario
    if (!file_exists($excelConfig['path'])) {
        if (!@mkdir($excelConfig['path'], 0777, true)) {
            throw new Exception("No se pudo crear el directorio {$excelConfig['path']}");
        }
    }
    
    // Asegurarse de que tenemos permisos de escritura
    if (!is_writable($excelConfig['path'])) {
        if (!@chmod($excelConfig['path'], 0777)) {
            throw new Exception("No hay permisos de escritura en {$excelConfig['path']}");
        }
    }
    
    $writer->save($fullPath);
    echo "Archivo Excel creado correctamente en: " . $fullPath;
} catch (Exception $e) {
    echo "Error al crear el archivo Excel: " . $e->getMessage();
    exit(1);
}