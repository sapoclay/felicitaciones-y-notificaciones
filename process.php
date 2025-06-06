<?php
/**
 * Procesamiento de formularios y peticiones AJAX
 */

session_start();

require_once __DIR__ . '/includes/functions.php';

$config = loadConfig();
$correos = obtenerCorreosPorFecha();

// Verificar si es una solicitud AJAX para verificar el progreso
if (isset($_GET['check_progress'])) {
    header('Content-Type: application/json');
    $response = [
        'progress' => $_SESSION['progreso'] ?? 0,
        'message' => $_SESSION['mensaje'] ?? '',
        'tipo' => $_SESSION['tipo'] ?? 'info',
        'status' => isset($_SESSION['progreso']) && $_SESSION['progreso'] >= 100 ? 'completed' : 'in_progress'
    ];
    echo json_encode($response);
    exit;
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    $to = $_POST['destinatarios'] ?? [];
    $subject = $_POST['asunto'] ?? '';
    $message = $_POST['mensaje'] ?? '';
    
    // LOGGING TEMPORAL PARA DEBUG - INICIO
    error_log("=== DEBUG PROCESS.PHP ===");
    error_log("Message length: " . strlen($message));
    error_log("Message content: " . $message);
    error_log("Number of images in message: " . preg_match_all('/<img[^>]*>/i', $message, $matches));
    
    // Dividir por imágenes para ver contenido antes/después
    $parts = preg_split('/<img[^>]*>/i', $message);
    error_log("Parts after splitting by images: " . print_r($parts, true));
    error_log("========================");
    // LOGGING TEMPORAL PARA DEBUG - FIN
    
    // Validar campos requeridos
    if (empty($to) || empty($subject) || empty($message)) {
        $_SESSION['mensaje'] = 'Por favor, complete todos los campos requeridos.';
        $_SESSION['tipo'] = "error";
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => $_SESSION['mensaje'],
                'tipo' => $_SESSION['tipo']
            ]);
        } else {
            // Redirigir con mensaje de error usando PRG
            $mensaje_encoded = urlencode($_SESSION['mensaje']);
            $tipo_encoded = urlencode($_SESSION['tipo']);
            
            // Limpiar la sesión
            unset($_SESSION['mensaje']);
            unset($_SESSION['tipo']);
            
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=1&msg=" . $mensaje_encoded . "&tipo=" . $tipo_encoded);
        }
        exit();
    }

    $_SESSION['progreso'] = 0;
    $total_emails = count($to);
    $enviados = 0;
    $errores = [];

    try {
        // Procesar imágenes adjuntas
        $imagenes = procesarImagenesAdjuntas();
        
        // Procesar imágenes base64 del contenido del mensaje
        $imagenesDelEditor = [];
        $message = procesarImagenesBase64DelMensaje($message, $imagenesDelEditor);
        
        // Combinar ambos tipos de imágenes
        $todasLasImagenes = array_merge($imagenes, $imagenesDelEditor);
        
        // Enviar correos uno por uno
        foreach ($to as $email) {
            try {
                $mail = configurarPHPMailer($config);
                $mail->addAddress($email);
                $mail->Subject = $subject;
                
                // Personalizar mensaje
                $nombreDestinatario = $correos[$email]['nombre'] ?? '';
                $mensajePersonalizado = personalizarMensaje($message, $nombreDestinatario);
                
                // Agregar referencias de imágenes al mensaje (incluye tanto tradicionales como del editor)
                $mensajeFinal = agregarReferenciasImagenes($mensajePersonalizado, $todasLasImagenes);
                
                // Obtener las imágenes adjuntas con CID generado por agregarReferenciasImagenes
                global $imagenesAdjuntasConCID;
                if (isset($imagenesAdjuntasConCID) && is_array($imagenesAdjuntasConCID)) {
                    // Embebir todas las imágenes (adjuntas tradicionales y del editor) usando CID 
                    foreach ($imagenesAdjuntasConCID as $imagen) {
                        if (file_exists($imagen['tmp_name']) && isset($imagen['cid_adjunta'])) {
                            $mail->addEmbeddedImage($imagen['tmp_name'], $imagen['cid_adjunta'], $imagen['name']);
                        }
                    }
                }
                
                $mail->Body = $mensajeFinal;
                
                $mail->send();
                $enviados++;
                
                actualizarProgreso($enviados, $total_emails, "Enviando correo {$enviados} de {$total_emails}...");
                
            } catch (Exception $e) {
                $errores[] = "Error enviando a $email: " . $mail->ErrorInfo;
                actualizarProgreso($enviados, $total_emails, "Error al enviar a {$email}", "error");
            }
        }
        
        // Limpiar archivos temporales creados para imágenes del editor
        foreach ($imagenesDelEditor as $imagen) {
            if (isset($imagen['es_del_editor']) && $imagen['es_del_editor'] && file_exists($imagen['tmp_name'])) {
                unlink($imagen['tmp_name']);
            }
        }
        
    } catch (Exception $e) {
        $errores[] = $e->getMessage();
    }
    
    // Establecer mensaje final
    establecerMensajeFinal($enviados, $total_emails, $errores);
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'progress' => 100,
            'status' => 'completed',
            'message' => $_SESSION['mensaje'],
            'tipo' => $_SESSION['tipo']
        ]);
    } else {
        // Implementar patrón PRG (Post-Redirect-Get) para evitar reenvío con F5
        $mensaje_encoded = urlencode($_SESSION['mensaje']);
        $tipo_encoded = urlencode($_SESSION['tipo']);
        
        // Limpiar la sesión
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo']);
        unset($_SESSION['progreso']);
        
        // Redirigir con parámetros GET para mostrar el mensaje
        header("Location: " . $_SERVER['PHP_SELF'] . "?sent=1&msg=" . $mensaje_encoded . "&tipo=" . $tipo_encoded);
    }
    exit();
}

// Preparar datos para la vista
$tratamientosUnicos = obtenerTratamientosUnicos($correos);

// Obtener mensaje de la sesión o de los parámetros GET (después de PRG)
$mensaje = '';
$tipo_mensaje = '';

// Verificar si viene de una redirección PRG
if (isset($_GET['sent']) || isset($_GET['error'])) {
    if (isset($_GET['msg']) && isset($_GET['tipo'])) {
        $mensaje = urldecode($_GET['msg']);
        $tipo_mensaje = urldecode($_GET['tipo']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['mensaje'])) {
    // Fallback para compatibilidad (solo si NO es POST y NO viene de redirección)
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo']);
}

// Incluir la vista
require_once __DIR__ . '/views/main.php';
