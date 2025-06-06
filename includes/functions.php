<?php
/**
 * Funciones principales de la aplicación
 */

// Establecer zona horaria correcta
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Cargar configuración desde el archivo JSON
 */
function loadConfig() {
    $configFile = __DIR__ . '/../config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $config;
        }
    }
    return ['smtp' => []];
}

/**
 * Obtener los correos de las empresas que tengan email válido
 */
function obtenerCorreosPorFecha() {
    // Primero intentar con la fecha de hoy
    $inputFileName = __DIR__ . '/../empresas_' . date('d-m-Y') . '.xlsx';
    
    // Si no existe, buscar el archivo Excel más reciente
    if (!file_exists($inputFileName)) {
        $pattern = __DIR__ . '/../empresas_*.xlsx';
        $files = glob($pattern);
        
        if (empty($files)) {
            return [];
        }
        
        // Ordenar archivos por fecha de modificación (más reciente primero)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $inputFileName = $files[0];
        // error_log("Usando archivo Excel: " . basename($inputFileName));
    }

    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestRow();
    
    $correos = [];
    
    for ($row = 2; $row <= $highestRow; ++$row) {
        $correo = $worksheet->getCell('L' . $row)->getValue();
        $nombre = $worksheet->getCell('B' . $row)->getValue();
        $fechaCell = $worksheet->getCell('N' . $row)->getValue();
        $tratamientos = $worksheet->getCell('O' . $row)->getValue();
        
        // Validar que el correo no esté vacío y tenga formato válido
        if (!empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $correos[$correo] = [
                'nombre' => $nombre,
                'fecha' => $fechaCell,
                'tratamientos' => $tratamientos
            ];
        }
    }
    
    return $correos;
}

/**
 * Obtener todos los tratamientos únicos
 */
function obtenerTratamientosUnicos($correos) {
    $tratamientos = [];
    
    foreach ($correos as $info) {
        if (!empty($info['tratamientos'])) {
            $tratamientosLinea = array_map('trim', explode(',', $info['tratamientos']));
            foreach ($tratamientosLinea as $tratamiento) {
                if (!empty($tratamiento) && !in_array($tratamiento, $tratamientos)) {
                    $tratamientos[] = $tratamiento;
                }
            }
        }
    }
    
    sort($tratamientos);
    return $tratamientos;
}

/**
 * Configurar PHPMailer con los parámetros SMTP
 */
function configurarPHPMailer($config) {
    $mail = new PHPMailer(true);
    
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = $config['smtp']['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp']['username'];
    $mail->Password = $config['smtp']['password'];
    $mail->SMTPSecure = $config['smtp']['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $config['smtp']['port'];
    
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $mail->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    
    return $mail;
}

/**
 * Personalizar mensaje con saludo
 */
function personalizarMensaje($mensaje, $nombreDestinatario) {
    $mensajePersonalizado = '';
    
    if ($nombreDestinatario) {
        // Dividir el nombre completo
        $partes = explode(' ', trim($nombreDestinatario));
        
        // Los dos primeros son apellidos, el resto es el nombre
        if (count($partes) >= 3) {
            $nombre = implode(' ', array_slice($partes, 2));
        } else {
            $nombre = end($partes); // Por si acaso no sigue el formato esperado
        }
        
        $mensajePersonalizado = "<p>Hola {$nombre},</p>\n\n";
    }
    
    return $mensajePersonalizado . $mensaje;
}

/**
 * Agregar referencias de imágenes adjuntas al mensaje
 * Ahora incluye tanto imágenes adjuntas como del editor usando el mismo sistema CID embebido
 */
function agregarReferenciasImagenes($mensaje, $imagenes) {
    if (empty($imagenes)) {
        return $mensaje;
    }
    
    $referencias = [];
    $imagenesAdjuntas = [];
    
    foreach ($imagenes as $imagen) {
        $nombreImagen = htmlspecialchars($imagen['name']);
        
        // Para imágenes adjuntas tradicionales
        if (!isset($imagen['es_del_editor']) || !$imagen['es_del_editor']) {
            // Generar CID único para imagen adjunta 
            $cid = 'img_adjunta_' . uniqid();
            $imagen['cid_adjunta'] = $cid;
            $imagenesAdjuntas[] = $imagen;
            
            // Crear imagen embebida con enlace clickeable si existe
            if (!empty($imagen['link'])) {
                $linkLimpio = htmlspecialchars($imagen['link']);
                $referencias[] = '<div style="margin: 20px 0; text-align: center;">
                    <a href="' . $linkLimpio . '" target="_blank" style="text-decoration: none; display: inline-block;">
                        <img src="cid:' . $cid . '" alt="' . $nombreImagen . '" style="max-width: 400px; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); transition: transform 0.2s;">
                    </a>
                    <br><small style="color: #666; font-style: italic;">
                        <a href="' . $linkLimpio . '" target="_blank" style="color: #2196F3; text-decoration: none;">🔗 Haz clic en la imagen o aquí para ver más</a>
                    </small>
                </div>';
            } else {
                $referencias[] = '<div style="margin: 20px 0; text-align: center;">
                    <img src="cid:' . $cid . '" alt="' . $nombreImagen . '" style="max-width: 400px; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <br><small style="color: #666; font-style: italic;">📎 ' . $nombreImagen . '</small>
                </div>';
            }
        } else {
            // Para imágenes del editor, también las tratamos como adjuntos embebidos
            // Usar el CID ya generado en procesarImagenesBase64DelMensaje
            $cid = $imagen['cid'];
            $imagen['cid_adjunta'] = $cid;
            $imagenesAdjuntas[] = $imagen;
            
            // NO mostrar las imágenes del editor en la sección de adjuntos
            // ya que ya están en el cuerpo del mensaje
            // No añadimos nada a $referencias para evitar duplicar la imagen
        }
    }
    
    // Solo agregamos la sección de adjuntos si hay referencias (imágenes tradicionales)
    if (!empty($referencias)) {
        $mensaje .= "\n\n<hr style='border: 1px solid #eee; margin: 30px 0;'>\n";
        $mensaje .= "<h3 style='color: #333; font-size: 18px; margin-bottom: 20px;'>📎 Archivos adjuntos</h3>\n";
        $mensaje .= implode("\n", $referencias);
    }
    
    // Agregar todas las imágenes a una variable global para que process.php las pueda usar
    // Esto incluye tanto imágenes tradicionales como del editor, ya que todas necesitan ser
    // embebidas con sus respectivos CIDs
    global $imagenesAdjuntasConCID;
    $imagenesAdjuntasConCID = $imagenesAdjuntas;
    
    return $mensaje;
}

/**
 * Procesar las imágenes adjuntas
 */
function procesarImagenesAdjuntas() {
    $imagenes = [];
    
    if (!empty($_FILES['imagenes']['name'][0])) {
        $total_imagenes = count($_FILES['imagenes']['name']);
        $imagen_links = $_POST['imagen_links'] ?? [];
        
        if ($total_imagenes > 5) {
            throw new Exception('Solo se permiten hasta 5 imágenes.');
        }
        
        // Límite de 2MB para compatibilidad con servidores en la nube
        $maxSizeBytes = 2 * 1024 * 1024; // 2MB
        
        for ($i = 0; $i < $total_imagenes; $i++) {
            if ($_FILES['imagenes']['error'][$i] === UPLOAD_ERR_OK) {
                $fileSize = $_FILES['imagenes']['size'][$i];
                $fileName = $_FILES['imagenes']['name'][$i];
                
                // Validar tamaño del archivo
                if ($fileSize > $maxSizeBytes) {
                    $sizeMB = round($fileSize / (1024 * 1024), 2);
                    throw new Exception("La imagen '{$fileName}' es demasiado grande ({$sizeMB}MB). El tamaño máximo permitido es 2MB. Por favor, reduce el tamaño de la imagen.");
                }
                
                $imagen = [
                    'tmp_name' => $_FILES['imagenes']['tmp_name'][$i],
                    'name' => $fileName,
                    'type' => $_FILES['imagenes']['type'][$i],
                    'link' => isset($imagen_links[$i]) ? trim($imagen_links[$i]) : ''
                ];
                
                if (strpos($imagen['type'], 'image/') === 0) {
                    $imagenes[] = $imagen;
                }
            }
        }
    }
    
    return $imagenes;
}

/**
 * Extraer y procesar imágenes base64 del contenido HTML del mensaje
 * Ahora elimina las imágenes del contenido del editor y las trata como adjuntos embebidos,
 * pero preserva la información de alineación para mostrarlas en la sección de adjuntos
 */
function procesarImagenesBase64DelMensaje($mensaje, &$imagenesExtraidas) {
    try {
        // Validar que el mensaje no sea null o vacío
        if ($mensaje === null || $mensaje === '') {
            $imagenesExtraidas = [];
            return $mensaje ?? '';
        }
        
        // Primero limpiar el HTML del editor de elementos de UI
        $mensajeLimpio = limpiarHTMLEditor($mensaje);
        
        // Verificar que la limpieza no devolvió null
        if ($mensajeLimpio === null) {
            $mensajeLimpio = $mensaje; // Usar el mensaje original como fallback
        }
        
        // Asegurar que tenemos un string válido
        $mensajeLimpio = (string) $mensajeLimpio;
        
        // Buscar todas las imágenes base64 en el contenido
        // Patrón mejorado que captura toda la etiqueta img con cualquier orden de atributos
        $patronImagen = '/<img[^>]*src=["\']data:image\/([^;]+);base64,([^"\']+)["\'][^>]*>/i';
        $imagenesExtraidas = [];
        
        // Encontrar todas las imágenes y sus contextos
        preg_match_all($patronImagen, $mensajeLimpio, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        
        // Si no hay imágenes, devolver el mensaje limpio tal como está
        if (empty($matches)) {
            return $mensajeLimpio;
        }
        
        // NUEVO ENFOQUE: Procesar con placeholders únicos para evitar interferencias
        $mensajeProcesado = $mensajeLimpio;
        $reemplazos = [];
        
        // Primero, reemplazar todas las imágenes con placeholders únicos
        foreach ($matches as $index => $match) {
            $imagenCompleta = $match[0][0]; // La etiqueta img completa
            $tipoImagen = $match[1][0]; // jpeg, png, gif, etc.
            $base64Data = $match[2][0]; // Los datos base64

            // Extraer todos los atributos de la etiqueta img original, menos el src
            $atributosImg = '';
            $tieneStyle = false;
            $width = null;
            $height = null;
            
            if (preg_match_all('/([a-zA-Z\-]+)=("[^"]*"|\'[^"]*\')/i', $imagenCompleta, $attrMatches, PREG_SET_ORDER)) {
                foreach ($attrMatches as $attr) {
                    $nombreAttr = strtolower($attr[1]);
                    if ($nombreAttr === 'src') continue; // Omitir src original
                    if ($nombreAttr === 'style') $tieneStyle = true;
                    if ($nombreAttr === 'width') {
                        $width = trim($attr[2], '"\'');
                    }
                    if ($nombreAttr === 'height') {
                        $height = trim($attr[2], '"\'');
                    }
                    $atributosImg .= ' ' . $attr[1] . '=' . $attr[2];
                }
            }
            
            // Extraer dimensiones del atributo style si existen
            if (preg_match('/style=("[^"]*"|\'[^\']*\')/i', $imagenCompleta, $styleMatch)) {
                $styleValue = trim($styleMatch[1], '"\'');
                
                // Extraer width del style (incluyendo max-width que usa el editor)
                if (preg_match('/(?:max-)?width:\s*([^;]+)/i', $styleValue, $widthMatch) && $width === null) {
                    $width = trim($widthMatch[1]);
                    // Convertir max-width a width para el email
                    if (strpos($widthMatch[0], 'max-width') !== false) {
                        $width = trim($widthMatch[1]);
                    }
                }
                
                // Extraer height del style (incluyendo max-height)
                if (preg_match('/(?:max-)?height:\s*([^;]+)/i', $styleValue, $heightMatch) && $height === null) {
                    $height = trim($heightMatch[1]);
                    // Convertir max-height a height para el email
                    if (strpos($heightMatch[0], 'max-height') !== false) {
                        $height = trim($heightMatch[1]);
                    }
                }
            }
            // Decodificar la imagen base64
            $datosImagen = base64_decode($base64Data);
            if ($datosImagen === false) {
                continue; // Saltar si no se puede decodificar
            }
            
            // Validar tamaño de la imagen (límite 2MB para compatibilidad con servidores en la nube)
            $tamañoBytes = strlen($datosImagen);
            $maxSizeBytes = 2 * 1024 * 1024; // 2MB
            
            if ($tamañoBytes > $maxSizeBytes) {
                $sizeMB = round($tamañoBytes / (1024 * 1024), 2);
                error_log("Imagen base64 demasiado grande: {$sizeMB}MB (máximo 2MB)"); // Mantener este log porque es un error
                continue; // Saltar esta imagen pero continuar con el procesamiento
            }
            
            // Crear archivo temporal
            $extension = $tipoImagen === 'jpeg' ? 'jpg' : $tipoImagen;
            $numeroImagen = count($imagenesExtraidas) + 1;
            $nombreArchivo = "imagen_editor_" . $numeroImagen . "." . $extension;
            
            // Crear archivo temporal con nombre único
            $archivoTemporal = tempnam(sys_get_temp_dir(), 'img_editor_');
            if ($archivoTemporal) {
                // Renombrar con la extensión correcta
                $archivoTemporalConExt = $archivoTemporal . '.' . $extension;
                rename($archivoTemporal, $archivoTemporalConExt);
                $archivoTemporal = $archivoTemporalConExt;
            } else {
                continue;
            }
            
            // Guardar la imagen en el archivo temporal
            if (file_put_contents($archivoTemporal, $datosImagen) !== false) {
                // Generar un CID único para esta imagen
                $cid = 'imagen_editor_' . $numeroImagen . '_' . uniqid();
                
                // Detectar alineación del contexto alrededor de la imagen
                $alineacion = detectarAlineacionImagen($mensajeLimpio, $match[0][1]);
                
                // DEBUG: Log del width y height detectados
                // error_log("DEBUG: Imagen #$numeroImagen - width: " . var_export($width, true) . ", height: " . var_export($height, true));
                // error_log("DEBUG: Atributos originales: " . $atributosImg);
                
                // Agregar a la lista de imágenes extraídas con información completa
                $imagenesExtraidas[] = [
                    'tmp_name' => $archivoTemporal,
                    'name' => $nombreArchivo,
                    'type' => 'image/' . $tipoImagen,
                    'link' => '',
                    'es_del_editor' => true,
                    'cid' => $cid,
                    'alineacion' => $alineacion,
                    'width' => $width,
                    'height' => $height
                ];
                
                // Crear placeholder único que será reemplazado después
                $placeholder = '<!-- IMAGEN_PLACEHOLDER_' . $numeroImagen . '_' . uniqid() . ' -->';
                $imgTag = '<img src="cid:' . $cid . '"' . $atributosImg . '>';
                // Forzar el tamaño SOLO si el usuario lo definió explícitamente
                if (($width !== null && $width !== '') || ($height !== null && $height !== '')) {
                    // Eliminar cualquier style y atributos de tamaño anteriores
                    $atributosImg = preg_replace('/\s*style=(\"[^\"]*\"|\'[^\']*\')/i', '', $atributosImg);
                    $atributosImg = preg_replace('/\s*width=(\"[^\"]*\"|\'[^\']*\'|\S+)/i', '', $atributosImg);
                    $atributosImg = preg_replace('/\s*height=(\"[^\"]*\"|\'[^\']*\'|\S+)/i', '', $atributosImg);
                    // Usar width y height directos (no max-width) para forzar el tamaño
                    $styleFinal = 'display:block;';
                    if ($width !== null && $width !== '') {
                        $widthValue = is_numeric($width) ? $width . 'px' : $width;
                        $atributosImg .= ' width="' . $width . '"';
                        $styleFinal .= 'width:' . $widthValue . ';';
                    }
                    if ($height !== null && $height !== '') {
                        $heightValue = is_numeric($height) ? $height . 'px' : $height;
                        $atributosImg .= ' height="' . $height . '"';
                        $styleFinal .= 'height:' . $heightValue . ';';
                    }
                    $atributosImg .= ' style="' . $styleFinal . '"';
                }
                // Generar el tag <img ...> usando los atributos finales
                $imgTag = '<img src="cid:' . $cid . '"' . $atributosImg . '>';
                // error_log("DEBUG: IMG tag final: " . $imgTag);
                // error_log("DEBUG: Alineación aplicada: " . $alineacion);
                if ($alineacion === 'center') {
                    $imgConCid = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="text-align:center;">' . $imgTag . '</td></tr></table>';
                } elseif ($alineacion === 'right') {
                    $imgConCid = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="right" style="text-align:right;">' . $imgTag . '</td></tr></table>';
                } elseif ($alineacion === 'left') {
                    $imgConCid = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="left" style="text-align:left;">' . $imgTag . '</td></tr></table>';
                } else {
                    $imgConCid = $imgTag;
                }
                $reemplazos[$placeholder] = $imgConCid;
                $mensajeProcesado = str_replace($imagenCompleta, $placeholder, $mensajeProcesado);
            }
        }
        
        // Ahora hacer todos los reemplazos finales de placeholders a imágenes CID
        foreach ($reemplazos as $placeholder => $imgCid) {
            $mensajeProcesado = str_replace($placeholder, $imgCid, $mensajeProcesado);
        }
        
        // Limpiar espacios extra pero mantener estructura HTML básica
        $mensajeProcesado = preg_replace('/\s{2,}/', ' ', $mensajeProcesado); // Múltiples espacios a uno
        $mensajeProcesado = preg_replace('/\s*\n\s*/', '\n', $mensajeProcesado); // Limpiar saltos de línea
        $mensajeProcesado = trim($mensajeProcesado);
        
        // Si el mensaje queda vacío después del procesamiento, usar un mensaje mínimo
        if (empty($mensajeProcesado) || $mensajeProcesado === '<br>' || preg_match('/^\s*<\/?(?:br|p|div)[^>]*>\s*$/i', $mensajeProcesado)) {
            $mensajeProcesado = '<p>Mensaje con imágenes adjuntas.</p>';
        }
        
        return $mensajeProcesado;
        
    } catch (Exception $e) {
        // En caso de error, devolver mensaje original
        $imagenesExtraidas = [];
        return $mensaje;
    }
}

/**
 * Detectar la alineación de una imagen basada en su contexto HTML
 */
function detectarAlineacionImagen($html, $posicionImagen) {
    // Buscar hacia atrás desde la posición de la imagen para encontrar contenedores con alineación
    $fragmentoAnterior = substr($html, 0, $posicionImagen + 500); // Ampliado para capturar más contexto
    
    // error_log("DEBUG: Analizando fragmento para alineación - longitud: " . strlen($fragmentoAnterior));
    
    // 1. Buscar primero en estilos CSS text-align
    if (preg_match_all('/text-align:\s*(left|center|right|justify)/i', $fragmentoAnterior, $matches, PREG_OFFSET_CAPTURE)) {
        // Tomar la coincidencia más cercana a la imagen (la última)
        $ultimaCoincidencia = end($matches[1]);
        if ($ultimaCoincidencia && !empty($ultimaCoincidencia[0])) {
            $alineacionDetectada = strtolower(trim($ultimaCoincidencia[0]));
            // error_log("DEBUG: Alineación detectada en CSS: " . $alineacionDetectada);
            return $alineacionDetectada;
        }
    }
    
    // 2. Buscar en atributos align
    if (preg_match_all('/align="(left|center|right|justify)"/i', $fragmentoAnterior, $matches, PREG_OFFSET_CAPTURE)) {
        $ultimaCoincidencia = end($matches[1]);
        if ($ultimaCoincidencia && !empty($ultimaCoincidencia[0])) {
            $alineacionDetectada = strtolower(trim($ultimaCoincidencia[0]));
            // error_log("DEBUG: Alineación detectada en atributo align: " . $alineacionDetectada);
            return $alineacionDetectada;
        }
    }
    
    // 3. Buscar específicamente en elementos contenedores como <div> y <p>
    if (preg_match_all('/<(?:div|p)[^>]*style="[^"]*text-align:\s*(left|center|right|justify)[^"]*"/i', $fragmentoAnterior, $matches, PREG_OFFSET_CAPTURE)) {
        // El grupo de captura correcto es el primero (índice 1)
        if (isset($matches[1]) && !empty($matches[1])) {
            $ultimaCoincidencia = end($matches[1]);
            if ($ultimaCoincidencia && !empty($ultimaCoincidencia[0])) {
                $alineacionDetectada = strtolower(trim($ultimaCoincidencia[0]));
                // error_log("DEBUG: Alineación detectada en elemento contenedor: " . $alineacionDetectada);
                return $alineacionDetectada;
            }
        }
    }

    // error_log("DEBUG: No se detectó alineación, usando 'center' por defecto");
    return 'center'; // Por defecto
}

/**
 * Actualizar progreso en sesión
 */
function actualizarProgreso($enviados, $total, $mensaje, $tipo = 'info') {
    session_start();
    $_SESSION['progreso'] = round(($enviados / $total) * 100);
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo'] = $tipo;
    session_write_close();
}

/**
 * Establecer mensaje final en sesión
 */
function establecerMensajeFinal($enviados, $total, $errores) {
    session_start();
    $_SESSION['progreso'] = 100;
    
    if ($enviados === $total) {
        $_SESSION['mensaje'] = "¡Éxito! Se han enviado todos los correos correctamente.";
        $_SESSION['tipo'] = "exito";
    } elseif ($enviados > 0) {
        $_SESSION['mensaje'] = "Se enviaron $enviados de $total correos. Errores: " . implode(", ", $errores);
        $_SESSION['tipo'] = "warning";
    } else {
        $_SESSION['mensaje'] = "Hubo errores al enviar los correos: " . implode(", ", $errores);
        $_SESSION['tipo'] = "error";
    }
    session_write_close();
}

/**
 * Filtrar correos por tratamiento específico
 */
function filtrarCorreosPorTratamiento($correos, $tratamientoBuscado) {
    $correosFiltrados = [];
    
    foreach ($correos as $email => $info) {
        if (!empty($info['tratamientos'])) {
            $tratamientosLinea = array_map('trim', explode(',', $info['tratamientos']));
            if (in_array($tratamientoBuscado, $tratamientosLinea)) {
                $correosFiltrados[$email] = $info;
            }
        }
    }
    
    return $correosFiltrados;
}

/**
 * Limpiar HTML del editor de elementos de UI no deseados
 * Remueve controles de redimensionamiento, bordes de selección, etc.
 */
function limpiarHTMLEditor($html) {
    // Si el HTML está vacío o es null, devolverlo como string vacío
    if ($html === null || $html === '') {
        return '';
    }
    
    try {
        // 1. Remover los controles de imagen de nuestro editor personalizado
        $html = preg_replace('/<div[^>]*class="[^"]*image-controls[^"]*"[^>]*>.*?<\/div>/is', '', $html);
        
        // 2. NO procesar los contenedores de imagen aquí - dejar que procesarImagenesBase64DelMensaje
        // maneje todo el contexto, incluyendo la alineación del elemento padre
        // Solo limpiar eventos de las imágenes directamente
        if ($html !== null && trim($html) !== '') {
            $html = preg_replace_callback(
                '/<img([^>]*)>/i', 
                function($matches) {
                    if (!isset($matches[1])) {
                        return $matches[0] ?? '';
                    }
                    
                    $atributos = $matches[1];
                    
                    // Verificación adicional para evitar el error de valor nulo
                    if ($atributos !== null && trim($atributos) !== '') {
                        $atributos = preg_replace('/\s+(?:onclick|ondblclick|onload|onmouseover|onmouseout)="[^"]*"/i', '', $atributos);
                        $atributos = preg_replace('/\s+(?:id|title)="[^"]*"/i', '', $atributos);
                    }
                    
                    return '<img' . ($atributos ?? '') . '>';
                }, 
                $html
            );
        }
        
        // 4. Remover elementos vacíos (máximo 3 intentos)
        for ($i = 0; $i < 3; $i++) {
            $htmlAnterior = $html;
            if ($html !== null && trim($html) !== '') {
                $html = preg_replace('/<(?:div|span)[^>]*>\s*<\/(?:div|span)>/i', '', $html);
            }
            // Salir si no hay cambios o si $html se volvió null
            if ($html === $htmlAnterior || $html === null) {
                break;
            }
        }
        
        // 5. Normalizar espacios
        if ($html !== null) {
            $html = preg_replace('/\s+/', ' ', $html);
            $html = trim($html);
        }
        
        // 6. Garantizar que nunca devolvemos null
        return $html ?? '';
        
    } catch (Exception $e) {
        // En caso de error, devolver HTML original o string vacío
        return $html ?? '';
    }
}
?>


