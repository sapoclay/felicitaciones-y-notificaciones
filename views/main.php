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
                        <li><strong>Fecha Alta</strong> (columna N)</li>
                        <li><strong>Tratamientos</strong> (columna 0)</li>
                    </ul>
                </small>
            </div>
            <h3>Configuración SMTP</h3>
            <div class="form-group">
                <label for="host">Servidor SMTP:</label>
                <input type="text" id="host" name="host" value="<?php echo htmlspecialchars($config['smtp']['host'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="username">Usuario SMTP:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($config['smtp']['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="from_email">Email del remitente:</label>
                <input type="email" id="from_email" name="from_email" value="<?php echo htmlspecialchars($config['smtp']['from_email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($config['smtp']['password'] ?? ''); ?>">
                    <button type="button" class="toggle-password" aria-label="Mostrar/ocultar contraseña">👁️</button>
                </div>
            </div>
            <div class="form-group">
                <label for="from_name">Nombre del remitente:</label>
                <input type="text" id="from_name" name="from_name" value="<?php echo htmlspecialchars($config['smtp']['from_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="port">Puerto:</label>
                <input type="number" id="port" name="port" value="<?php echo htmlspecialchars($config['smtp']['port'] ?? '465'); ?>" required>
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
    
    <?php if ($mensaje): ?>
        <div id="notificacion" class="mensaje <?php echo $tipo_mensaje; ?>" style="display: block;">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($correos)): ?>
        <div class="no-clients-message">
            <i class="message-icon">📂</i>
            <p>No hay clientes con dirección de correo válida en el archivo Excel</p>
        </div>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" id="emailForm">
            <!-- Filtro por tratamientos -->
            <?php if (!empty($tratamientosUnicos)): ?>
            <div class="form-group">
                <label>Filtrar por tratamientos:</label>
                <div class="tratamientos-filter">
                    <div class="checkbox-group">
                        <?php foreach ($tratamientosUnicos as $tratamiento): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="filtro_tratamientos[]" value="<?php echo htmlspecialchars($tratamiento); ?>" onchange="filtrarPorTratamientos()">
                                <span class="checkbox-text"><?php echo htmlspecialchars($tratamiento); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="filter-actions">
                        <button type="button" onclick="seleccionarTodosTratamientos()" class="btn btn-small">Todos</button>
                        <button type="button" onclick="limpiarTratamientos()" class="btn btn-small">Limpiar</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="destinatarios">Seleccionar destinatarios:</label>
                
                <select name="destinatarios[]" id="destinatarios" multiple required>
                    <?php foreach ($correos as $correo => $info): ?>
                        <option value="<?php echo htmlspecialchars($correo); ?>" 
                                data-tratamientos="<?php echo htmlspecialchars($info['tratamientos']); ?>">
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
                <div class="editor-container">
                    <div class="editor-toolbar">
                        <div class="toolbar-group">
                            <button type="button" onclick="formatText('bold')" data-tooltip="Negrita"><b>B</b></button>
                            <button type="button" onclick="formatText('italic')" data-tooltip="Cursiva"><i>I</i></button>
                            <button type="button" onclick="formatText('underline')" data-tooltip="Subrayado"><u>U</u></button>
                        </div>
                        <div class="toolbar-group">
                            <button type="button" onclick="formatText('justifyLeft')" data-tooltip="Alinear a la izquierda">⬅</button>
                            <button type="button" onclick="formatText('justifyCenter')" data-tooltip="Centrar">⬛</button>
                            <button type="button" onclick="formatText('justifyRight')" data-tooltip="Alinear a la derecha">➡</button>
                        </div>
                        <div class="toolbar-group">
                            <button type="button" onclick="formatText('insertUnorderedList')" data-tooltip="Lista con viñetas">• Lista</button>
                            <button type="button" onclick="formatText('insertOrderedList')" data-tooltip="Lista numerada">1. Lista</button>
                        </div>
                        <div class="toolbar-group">
                            <button type="button" onclick="insertLink()" data-tooltip="Enlace">🔗</button>
                            <button type="button" onclick="insertHorizontalRule()" data-tooltip="Línea">📏</button>
                            <button type="button" onclick="insertImage()" data-tooltip="Imagen (máx 2MB)">🖼️</button>
                        </div>
                        <div class="toolbar-group">
                            <select onchange="changeFontSize(this.value)" data-tooltip="Tamaño de fuente">
                                <option value="">Tamaño</option>
                                <option value="1">Pequeño</option>
                                <option value="3">Normal</option>
                                <option value="4">Grande</option>
                                <option value="5">Muy grande</option>
                            </select>
                        </div>
                        <div class="toolbar-group">
                            <input type="color" id="textColor" onchange="changeTextColor(this.value)" data-tooltip="Color del texto">
                            <button type="button" onclick="removeAllFormat()" data-tooltip="Quitar formato">⚡</button>
                        </div>
                    </div>
                    <div 
                        id="contenido_mensaje" 
                        contenteditable="true" 
                        class="editor-content"
                        placeholder="Escriba aquí su mensaje..."
                        oninput="updateHiddenTextarea()"
                        onpaste="handlePaste(event)"
                        onkeyup="updateHiddenTextarea()"
                        onblur="updateHiddenTextarea()"
                        onchange="updateHiddenTextarea()"
                    ></div>
                    <textarea name="mensaje" id="mensaje_hidden" style="display: none;" required></textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="imagenes">Adjuntar Imágenes (máximo 5):</label>
                <small class="help-text" style="display: block; margin-bottom: 8px; color: #666;">
                    Puedes agregar un enlace opcional a cada imagen. Las imágenes aparecerán incrustadas en el correo y serán clickeables si tienen enlace.
                </small>
                <div class="size-restriction-notice" style="background: #e3f2fd; border: 1px solid #2196f3; padding: 8px; border-radius: 4px; margin-bottom: 10px;">
                    <strong>📏 Restricción de tamaño:</strong> Cada imagen debe ser menor a <strong>2MB</strong> para compatibilidad con servidores en la nube.
                </div>
                <input type="file" id="selectorImagenes" accept="image/*" multiple onchange="previsualizarImagenes(event)">
                <div id="previsualizacion"></div>
                <div id="imagenesSeleccionadas"></div>
            </div>

            <div class="progress-container" id="progressContainer">
                <div class="progress-bar" id="progressBar"></div>
                <div class="progress-text" id="progressText">Enviando correos: 0%</div>
            </div>

            <input type="submit" value="Enviar" id="submitButton">
        </form>
    <?php endif; ?>

    <footer class="copyright">
        <p>&copy; <?php echo date('Y'); ?> Sistema de Envío de Correos. Creado por Javier, Michel y <a href="https://entreunosyceros.net" target="_blank" title="About entreunosyceros">entreunosyceros</a></p>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
