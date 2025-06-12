<?php
// Verificar autenticación antes de continuar
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

// Función para validar los datos de entrada
function validateInput($input) {
    $errors = [];
    // Permitir guardar aunque todos los campos estén vacíos
    if (!isset($input['smtp']) || !is_array($input['smtp'])) {
        $errors[] = 'La configuración SMTP es inválida';
        return $errors;
    }
    // No validar campos requeridos, solo estructura
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
        // Limpiar mensajes de la sesión relacionados con el envío de correos ANTES de procesar el formulario
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo']);
        
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
        $fromEmail = $input['smtp']['from_email'] ?? '';
        $config = [
            'smtp' => [
                'host' => isset($input['smtp']['host']) ? htmlspecialchars($input['smtp']['host'], ENT_QUOTES, 'UTF-8') : '',
                'username' => isset($input['smtp']['username']) ? htmlspecialchars($input['smtp']['username'], ENT_QUOTES, 'UTF-8') : '',
                'password' => $input['smtp']['password'] ?? '',
                'port' => isset($input['smtp']['port']) ? intval($input['smtp']['port']) ?: 465 : 465,
                'from_email' => isset($input['smtp']['from_email']) ? htmlspecialchars($input['smtp']['from_email'], ENT_QUOTES, 'UTF-8') : '',
                'from_name' => isset($input['smtp']['from_name']) ? htmlspecialchars($input['smtp']['from_name'], ENT_QUOTES, 'UTF-8') : '',
                'secure' => isset($input['smtp']['secure']) ? htmlspecialchars($input['smtp']['secure'], ENT_QUOTES, 'UTF-8') : ''
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
