<?php
session_start();

function loadConfig() {
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $config;
        }
    }
    return ['smtp' => []];
}

$config = loadConfig();

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Función para obtener los correos de las empresas del mismo día y mes
function obtenerCorreosPorFecha() {
    $inputFileName = 'empresas_' . date('d-m-Y') . '.xlsx';
    
    if (!file_exists($inputFileName)) {
        return [];
    }

    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestRow();
    
    $correos = [];
    $diaActual = date('d');
    $mesActual = date('m');
    
    for ($row = 2; $row <= $highestRow; ++$row) {
        $fechaCell = $worksheet->getCell('M' . $row)->getValue();
        $partesFecha = explode('-', $fechaCell);
        if (count($partesFecha) === 3) {
            $timestamp = strtotime($partesFecha[0] . '-' . $partesFecha[1] . '-' . $partesFecha[2]);
            
            if (date('d', $timestamp) == $diaActual && date('m', $timestamp) == $mesActual) {
                $correo = $worksheet->getCell('L' . $row)->getValue();
                $nombre = $worksheet->getCell('B' . $row)->getValue();
                $correos[$correo] = [
                    'nombre' => $nombre,
                    'fecha' => $fechaCell
                ];
            }
        }
    }
    
    return $correos;
}

$mensaje = '';
$correos = obtenerCorreosPorFecha();
$resultados = [];

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
            header("Location: " . $_SERVER['PHP_SELF']);
        }
        exit();
    }

    $_SESSION['progreso'] = 0;
    $total_emails = count($to);
    $enviados = 0;
    $errores = [];

    // Verificar las imágenes
    $imagenes = [];
    if (!empty($_FILES['imagenes']['name'][0])) {
        $total_imagenes = count($_FILES['imagenes']['name']);
        if ($total_imagenes > 5) {
            $mensaje = 'Error: Solo se permiten hasta 5 imágenes.';
        } else {
            for ($i = 0; $i < $total_imagenes; $i++) {
                if ($_FILES['imagenes']['error'][$i] === UPLOAD_ERR_OK) {
                    $imagen = [
                        'tmp_name' => $_FILES['imagenes']['tmp_name'][$i],
                        'name' => $_FILES['imagenes']['name'][$i],
                        'type' => $_FILES['imagenes']['type'][$i]
                    ];
                    if (strpos($imagen['type'], 'image/') === 0) {
                        $imagenes[] = $imagen;
                    }
                }
            }
        }
    }

    // Enviar correos uno por uno
    foreach ($to as $email) {
        try {
            $mail = new PHPMailer(true);
            
            $mail->SMTPDebug = 2; // Activar debug
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP DEBUG: $str");
            };
            
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
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            
            // Preparar el mensaje con saludo personalizado
            $nombreDestinatario = $correos[$email]['nombre'] ?? '';
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
            
            $mail->Body = $mensajePersonalizado . $message;
            
            foreach ($imagenes as $imagen) {
                $mail->addAttachment(
                    $imagen['tmp_name'],
                    $imagen['name'],
                    'base64',
                    $imagen['type']
                );
            }
            
            $mail->send();
            $enviados++;
            $resultados[$email] = true;
            
            session_start();
            $_SESSION['progreso'] = round(($enviados / $total_emails) * 100);
            $_SESSION['mensaje'] = "Enviando correo {$enviados} de {$total_emails}...";
            $_SESSION['tipo'] = "info";
            session_write_close();
            
        } catch (Exception $e) {
            $errores[] = "Error enviando a $email: " . $mail->ErrorInfo;
            $resultados[$email] = false;
            session_start();
            $_SESSION['progreso'] = round(($enviados / $total_emails) * 100);
            $_SESSION['mensaje'] = "Error al enviar a {$email}";
            $_SESSION['tipo'] = "error";
            session_write_close();
        }
    }
    
    session_start();
    $_SESSION['progreso'] = 100;
    
    if ($enviados === $total_emails) {
        $_SESSION['mensaje'] = "¡Éxito! Se han enviado todos los correos correctamente.";
        $_SESSION['tipo'] = "exito";
    } elseif ($enviados > 0) {
        $_SESSION['mensaje'] = "Se enviaron $enviados de $total_emails correos. Errores: " . implode(", ", $errores);
        $_SESSION['tipo'] = "warning";
    } else {
        $_SESSION['mensaje'] = "Hubo errores al enviar los correos: " . implode(", ", $errores);
        $_SESSION['tipo'] = "error";
    }
    session_write_close();
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'progress' => 100,
            'status' => 'completed',
            'message' => $_SESSION['mensaje'],
            'tipo' => $_SESSION['tipo']
        ]);
    } else {
        header("Location: " . $_SERVER['PHP_SELF']);
    }
    exit();
}


// Obtener mensaje de la sesión si existe
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envío felicitaciones y promociones</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0iIzIxOTZGMyIgZD0iTTIwIDRINEMyLjkgNCAyIDQuOSAyIDY2di0xMmMwIDEuMS45IDIuOSAyIDJoMTZjMS4xIDAgMi0uOSAyLTItMlY6YzAtMS4xLS45LTItMi0yek0yMCA4bC04IDUtOC01VjZsOCA1IDgtNXYyeiIvPjwvc3ZnPg==" type="image/svg+xml">
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle">☰</button>
    
    <div class="sidebar" id="sidebar">
        <h2>Configuración</h2>
        <form class="config-form" id="configForm">
            <div class="file-selector-container">
                <label for="excelFile">Seleccionar archivo de datos:</label>
                <div class="file-input-wrapper">
                    <input type="file" id="excelFile" accept=".xlsx" onchange="handleFileSelect(event)">
                    <div class="file-input-button">Seleccionar archivo Excel</div>
                </div>
                <div class="selected-file" id="selectedFileName"></div>
                <small class="help-text">
                    El archivo Excel debe contener las siguientes columnas:<br>
                    <ul class="column-list">
                        <li><strong>Código</strong> (columna A)</li>
                        <li><strong>Nombre</strong> (columna B)</li>
                        <li><strong>Email</strong> (columna L)</li>
                        <li><strong>Fecha Alta</strong> (columna M)</li>
                    </ul>
                </small>
            </div>
            <h3>Configuración SMTP</h3>
            <div class="form-group">
                <label for="host">Servidor SMTP:</label>
                <input type="text" id="host" name="host" value="<?php echo htmlspecialchars($config['smtp']['host'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="username">Usuario SMTP:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($config['smtp']['username'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="from_email">Email del remitente:</label>
                <input type="email" id="from_email" name="from_email" value="<?php echo htmlspecialchars($config['smtp']['from_email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($config['smtp']['password'] ?? ''); ?>" required>
                    <button type="button" class="toggle-password" aria-label="Mostrar/ocultar contraseña">👁️</button>
                </div>
            </div>
            <div class="form-group">
                <label for="port">Puerto:</label>
                <input type="number" id="port" name="port" value="<?php echo htmlspecialchars($config['smtp']['port'] ?? '465'); ?>" required>
            </div>
            <div class="form-group">
                <label for="from_name">Nombre del remitente:</label>
                <input type="text" id="from_name" name="from_name" value="<?php echo htmlspecialchars($config['smtp']['from_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="secure">Seguridad:</label>
                <select id="secure" name="secure" required>
                    <option value="ssl" <?php echo ($config['smtp']['secure'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo ($config['smtp']['secure'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                </select>
            </div>


            <button type="submit" class="btn">Guardar configuración</button>
        </form>
    </div>
    
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    
    <h1>Envío felicitaciones y promociones</h1>
    
    <script>
        // Función para manejar la selección de archivo Excel
        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                const selectedFileElement = document.getElementById('selectedFileName');
                selectedFileElement.textContent = file.name;
                selectedFileElement.classList.add('active');
                
                const formData = new FormData();
                formData.append('excelFile', file);
                
                // Mostrar indicador de carga
                mostrarNotificacion('Cargando datos del archivo...', 'info');
                
                fetch('cargar_excel.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarNotificacion('Datos cargados correctamente', 'exito');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        mostrarNotificacion('Error al cargar el archivo: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarNotificacion('Error al procesar el archivo', 'error');
                });
            }
        }

        // Funcionalidad para mostrar/ocultar contraseña
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type');
            
            if (type === 'password') {
                passwordInput.setAttribute('type', 'text');
                this.textContent = '🔒'; // Ojo cerrado cuando la contraseña es visible
                this.setAttribute('aria-label', 'Ocultar contraseña');
            } else {
                passwordInput.setAttribute('type', 'password');
                this.textContent = '👁️'; // Ojo abierto cuando la contraseña está oculta
                this.setAttribute('aria-label', 'Mostrar contraseña');
            }
        });

        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarBackdrop').classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        });
        
        document.getElementById('sidebarBackdrop').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarBackdrop').classList.remove('active');
            document.body.classList.remove('sidebar-open');
        });
        
        // Cerrar el sidebar con la tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('sidebarBackdrop').classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }
        });
        
        document.getElementById('configForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Deshabilitar el botón de envío mientras se procesa
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Guardando...';
            
            // Recoger los datos del formulario
            const formData = {
                smtp: {
                    host: document.getElementById('host').value.trim(),
                    username: document.getElementById('username').value.trim(),
                    password: document.getElementById('password').value,
                    port: parseInt(document.getElementById('port').value, 10),
                    from_email: document.getElementById('from_email').value.trim(),
                    from_name: document.getElementById('from_name').value.trim(),
                    secure: document.getElementById('secure').value
                }
            };
            
            // Validar que todos los campos tienen valor
            for (const [key, value] of Object.entries(formData)) {
                if (!value && value !== 0) {
                    mostrarNotificacion(`El campo ${key} es requerido`, 'error');
                    submitButton.disabled = false;
                    submitButton.textContent = 'Guardar configuración';
                    return;
                }
            }
            
            fetch('update_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarNotificacion('Configuración actualizada correctamente', 'exito');
                    // Recargar la página después de 1 segundo
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Mostrar errores de validación si existen
                    if (data.errors && Array.isArray(data.errors)) {
                        const errorMessage = data.errors.join('\n');
                        mostrarNotificacion(errorMessage, 'error');
                    } else {
                        mostrarNotificacion('Error al actualizar la configuración: ' + (data.message || 'Error desconocido'), 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error al guardar la configuración: ' + error.message, 'error');
            })
            .finally(() => {
                // Restaurar el botón de envío
                submitButton.disabled = false;
                submitButton.textContent = 'Guardar configuración';
            });
        });
    </script>
    
    <?php if ($mensaje): ?>
        <div id="notificacion" class="mensaje <?php echo $tipo_mensaje; ?>" style="display: block;">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($correos)): ?>
        <p>No hay clientes registrados el día <?php echo date('d'); ?> del mes <?php echo date('m'); ?> en ningún año.</p>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" id="emailForm">
            <div class="form-group">
                <label for="destinatarios">Seleccionar destinatarios:</label>
                
                <select name="destinatarios[]" id="destinatarios" multiple required>
                    <?php foreach ($correos as $correo => $info): ?>
                        <option value="<?php echo htmlspecialchars($correo); ?>">
                            <?php echo htmlspecialchars($info['nombre'] . ' (' . $correo . ') - Alta: ' . $info['fecha']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="selectAll" class="btn">Seleccionar Todos</button>
            </div>

            <div class="form-group">
                <label for="asunto">Asunto:</label>
                <input type="text" name="asunto" id="asunto" required style="width: 100%; padding: 8px;">
            </div>

            <div class="form-group">
                <label for="contenido_mensaje">Mensaje:</label>
                <textarea name="mensaje" id="contenido_mensaje" rows="10" required></textarea>
            </div>

            <div class="form-group">
                <label for="imagenes">Adjuntar Imágenes (máximo 5):</label>
                <input type="file" id="selectorImagenes" accept="image/*" onchange="previsualizarImagenes(event)">
                <div id="previsualizacion"></div>
                <div id="imagenesSeleccionadas"></div>
            </div>

            <div class="progress-container" id="progressContainer">
                <div class="progress-bar" id="progressBar"></div>
                <div class="progress-text" id="progressText">Enviando correos: 0%</div>
            </div>

            <input type="submit" value="Enviar" id="submitButton">
        </form>

        <script>
            function mostrarNotificacion(mensaje, tipo) {
                let contenedor = document.getElementById('notificacion');
                
                if (!contenedor) {
                    contenedor = document.createElement('div');
                    contenedor.id = 'notificacion';
                    document.querySelector('h1').after(contenedor);
                }
                
                contenedor.className = `mensaje ${tipo}`;
                contenedor.textContent = mensaje;
                contenedor.style.display = 'block';
                contenedor.style.opacity = '1';

                setTimeout(() => {
                    contenedor.style.opacity = '0';
                    setTimeout(() => {
                        contenedor.style.display = 'none';
                    }, 500);
                }, 4000);
            }

            function ocultarMensaje() {
                const notificacion = document.getElementById('notificacion');
                if (notificacion) {
                    notificacion.style.opacity = '0';
                    setTimeout(() => {
                        notificacion.style.display = 'none';
                    }, 500);
                }
            }

            const notificacion = document.getElementById('notificacion');
            if (notificacion) {
                notificacion.style.opacity = '1';
                setTimeout(ocultarMensaje, 4000);
            }

            let imagenes = [];

            function actualizarImagenesOcultas() {
                const contenedor = document.getElementById('imagenesSeleccionadas');
                contenedor.innerHTML = '';
                
                imagenes.forEach((imagen, index) => {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.name = `imagenes[]`;
                    input.style.display = 'none';
                    input.id = `imagen_${index}`;
                    
                    const file = new File([imagen.blob], imagen.name, {
                        type: imagen.type
                    });
                    
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    input.files = dataTransfer.files;
                    
                    contenedor.appendChild(input);
                });
            }

            function previsualizarImagenes(event) {
                const previsualizacion = document.getElementById('previsualizacion');
                const files = event.target.files;

                if (imagenes.length + files.length > 5) {
                    alert('El total de imágenes no puede superar 5');
                    event.target.value = '';
                    return;
                }

                Array.from(files).forEach(file => {
                    if (!file.type.startsWith('image/')) {
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imgContainer = document.createElement('div');
                        imgContainer.className = 'imagen-container';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'imagen-preview';
                        
                        const deleteBtn = document.createElement('button');
                        deleteBtn.innerHTML = '×';
                        deleteBtn.className = 'delete-image';
                        
                        fetch(e.target.result)
                            .then(res => res.blob())
                            .then(blob => {
                                const imagen = {
                                    blob: blob,
                                    name: file.name,
                                    type: file.type
                                };
                                const imageIndex = imagenes.length;
                                imagenes.push(imagen);
                                
                                deleteBtn.onclick = function(e) {
                                    e.preventDefault();
                                    imagenes.splice(imageIndex, 1);
                                    imgContainer.remove();
                                    actualizarImagenesOcultas();
                                };
                                
                                actualizarImagenesOcultas();
                            });
                        
                        imgContainer.appendChild(img);
                        imgContainer.appendChild(deleteBtn);
                        previsualizacion.appendChild(imgContainer);
                    };
                    reader.readAsDataURL(file);
                });
                
                event.target.value = '';
            }

            document.getElementById('emailForm').onsubmit = function(e) {
                e.preventDefault();
                
                const progressContainer = document.getElementById('progressContainer');
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');
                const submitButton = document.getElementById('submitButton');
                
                progressContainer.style.display = 'block';
                submitButton.disabled = true;
                progressBar.style.width = '0%';
                progressText.textContent = 'Iniciando envío...';

                const formData = new FormData(this);

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        mostrarNotificacion(data.error, 'error');
                        progressContainer.style.display = 'none';
                        submitButton.disabled = false;
                        return;
                    }
                    
                    checkProgress();
                })
                .catch(error => {
                    console.error('Error:', error);
                    progressText.textContent = 'Error al enviar los correos';
                    submitButton.disabled = false;
                });

                function checkProgress() {
                    fetch(window.location.href + '?check_progress=1')
                        .then(response => response.json())
                        .then(data => {
                            const progress = Math.min(data.progress || 0, 100);
                            progressBar.style.width = progress + '%';
                            progressText.textContent = `Enviando correos: ${Math.round(progress)}%`;
                            
                            if (data.message && data.status === 'completed') {
                                progressBar.style.width = '100%';
                                progressText.textContent = 'Enviando correos: 100%';
                                mostrarNotificacion(data.message, data.tipo);
                                setTimeout(() => {
                                    progressContainer.style.display = 'none';
                                    submitButton.disabled = false;
                                    document.getElementById('emailForm').reset();
                                    document.getElementById('previsualizacion').innerHTML = '';
                                    imagenes = [];
                                }, 2000);
                            } else if (progress < 100) {
                                setTimeout(checkProgress, 100);
                            }
                        })
                        .catch(error => {
                            console.error('Error verificando progreso:', error);
                            progressText.textContent = 'Error al verificar el progreso';
                        });
                }
            };

            document.addEventListener('DOMContentLoaded', function() {
                const selectAllBtn = document.getElementById('selectAll');
                const selectElement = document.getElementById('destinatarios');
                let allSelected = false;

                selectAllBtn.addEventListener('click', function() {
                    allSelected = !allSelected;
                    const options = selectElement.options;
                    for (let i = 0; i < options.length; i++) {
                        options[i].selected = allSelected;
                    }
                    selectAllBtn.textContent = allSelected ? 'Deseleccionar Todos' : 'Seleccionar Todos';
                });
            });
        </script>
    <?php endif; ?>
</body>
</html>
