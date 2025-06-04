<?php
header('Content-Type: application/json');

// Función para validar los datos de entrada
function validateInput($input) {
    $errors = [];
    
    // Verificar que existe la sección smtp
    if (!isset($input['smtp']) || !is_array($input['smtp'])) {
        $errors[] = 'La configuración SMTP es inválida';
        return $errors;
    }
    
    $smtp = $input['smtp'];
    
    // Lista de campos requeridos
    $requiredFields = [
        'host' => 'El host SMTP',
        'username' => 'El nombre de usuario',
        'password' => 'La contraseña',
        'port' => 'El puerto',
        'from_email' => 'El email del remitente',
        'from_name' => 'El nombre del remitente',
        'secure' => 'El tipo de seguridad'
    ];
    
    // Validar campos requeridos
    foreach ($requiredFields as $field => $label) {
        if (!isset($smtp[$field]) || (empty($smtp[$field]) && $smtp[$field] !== '0')) {
            $errors[] = $label . ' es requerido';
        } else if ($field === 'from_email' && !filter_var($smtp[$field], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email del remitente no tiene un formato válido';
        }
    }
    
    // Si hay campos vacíos, no seguir con el resto de validaciones
    if (!empty($errors)) {
        return $errors;
    }
    
    // Validar host
    if (!filter_var($smtp['host'], FILTER_VALIDATE_DOMAIN)) {
        $errors[] = 'El host SMTP no es válido';
    }
    
    // Validar puerto
    if (!filter_var($smtp['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
        $errors[] = 'El puerto debe ser un número entre 1 y 65535';
    }
    
    // Validar secure
    if (!in_array($smtp['secure'], ['ssl', 'tls'])) {
        $errors[] = 'El tipo de seguridad debe ser ssl o tls';
    }
    
    return $errors;
}

// Función para cargar la configuración actual
function loadCurrentConfig() {
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $config;
        }
    }
    return ['smtp' => []];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log('Iniciando actualización de configuración');
        
        // Verificar si podemos leer la entrada
        $rawInput = file_get_contents('php://input');
        if ($rawInput === false) {
            throw new Exception('No se pudieron leer los datos de entrada');
        }
        
        $input = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Datos JSON inválidos: ' . json_last_error_msg());
        }
        
        // Validar los datos de entrada
        $errors = validateInput($input);
        if (!empty($errors)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Errores de validación',
                'errors' => $errors
            ]);
            exit;
        }
        
        $configFile = __DIR__ . '/config.json';
        $backupFile = __DIR__ . '/config.backup.json';
        
        // Verificar permisos de escritura
        if (file_exists($configFile) && !is_writable($configFile)) {
            throw new Exception('No hay permisos de escritura en el archivo de configuración');
        }
        
        // Cargar la configuración actual
        $currentConfig = loadCurrentConfig();
        
        // Actualizar solo la sección SMTP
        // Si from_email está vacío, usar username
        $fromEmail = isset($input['smtp']['from_email']) && !empty($input['smtp']['from_email']) ? $input['smtp']['from_email'] : $input['smtp']['username'];
        $config = [
            'smtp' => [
                'host' => htmlspecialchars($input['smtp']['host'], ENT_QUOTES, 'UTF-8'),
                'username' => htmlspecialchars($input['smtp']['username'], ENT_QUOTES, 'UTF-8'),
                'password' => $input['smtp']['password'],
                'port' => intval($input['smtp']['port']),
                'from_email' => htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8'),
                'from_name' => htmlspecialchars($input['smtp']['from_name'], ENT_QUOTES, 'UTF-8'),
                'secure' => htmlspecialchars($input['smtp']['secure'], ENT_QUOTES, 'UTF-8')
            ]
        ];
        
        // Intentar guardar la configuración
        $jsonConfig = json_encode($config, JSON_PRETTY_PRINT);
        if ($jsonConfig === false) {
            throw new Exception('Error al codificar la configuración: ' . json_last_error_msg());
        }
        
        // Guardar directamente sin modificar permisos
        if (file_put_contents($configFile, $jsonConfig) === false) {
            throw new Exception('Error al escribir el archivo de configuración');
        }
        
        error_log('Configuración actualizada correctamente en: ' . $configFile);
        echo json_encode([
            'success' => true, 
            'message' => 'Configuración actualizada correctamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);
