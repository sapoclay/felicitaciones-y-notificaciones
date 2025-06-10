/**
 * JavaScript para la funcionalidad del cliente
 */

// Variables globales
let currentTooltip = null;
let imagenes = [];

/**
 * Funciones para el editor de texto enriquecido
 */
function formatText(command, value = null) {
    document.execCommand(command, false, value);
    document.getElementById('contenido_mensaje').focus();
    updateHiddenTextarea();
    setTimeout(updateButtonStates, 10);
}

function changeFontSize(size) {
    if (size) {
        formatText('fontSize', size);
    }
    event.target.selectedIndex = 0;
    updateButtonStates();
}

function changeTextColor(color) {
    formatText('foreColor', color);
    updateButtonStates();
}

function removeAllFormat() {
    formatText('removeFormat');
    document.querySelectorAll('.editor-toolbar button.active').forEach(button => {
        button.classList.remove('active');
    });
    updateButtonStates();
}

function insertLink() {
    const selection = window.getSelection();
    const selectedText = selection.toString();
    
    let url, text;
    
    if (selectedText) {
        url = prompt('Ingrese la URL del enlace:');
        if (!url) return;
        text = selectedText;
    } else {
        url = prompt('Ingrese la URL del enlace:');
        if (!url) return;
        text = prompt('Ingrese el texto del enlace:', url);
        if (!text) text = url;
    }
    
    if (!url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('mailto:')) {
        url = 'https://' + url;
    }
    
    if (selectedText) {
        document.execCommand('createLink', false, url);
    } else {
        const linkHtml = `<a href="${url}" target="_blank">${text}</a>`;
        document.execCommand('insertHTML', false, linkHtml);
    }
    
    updateHiddenTextarea();
    document.getElementById('contenido_mensaje').focus();
    updateButtonStates();
}

function insertHorizontalRule() {
    document.execCommand('insertHTML', false, '<hr style="margin: 16px 0; border: none; border-top: 2px solid #e0e0e0;">');
    updateHiddenTextarea();
    document.getElementById('contenido_mensaje').focus();
    updateButtonStates();
}

function insertImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.style.display = 'none';
    
    input.onchange = function(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        if (!file.type.startsWith('image/')) {
            alert('Por favor, seleccione un archivo de imagen válido.');
            return;
        }
        
        if (file.size > 2 * 1024 * 1024) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            alert(`⚠️ La imagen "${file.name}" es demasiado grande (${sizeMB}MB).\n\nTamaño máximo permitido: 2MB\n\nPor favor, reduce el tamaño de la imagen o elige otra.`);
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const tempImg = new Image();
            tempImg.onload = function() {
                let width = this.width;
                let height = this.height;
                const maxWidth = 600;
                const maxHeight = 400;
                
                if (width > maxWidth) {
                    height = (height * maxWidth) / width;
                    width = maxWidth;
                }
                if (height > maxHeight) {
                    width = (width * maxHeight) / height;
                    height = maxHeight;
                }
                
                // Detectar la alineación actual del cursor/selección
                const selection = window.getSelection();
                const range = selection.getRangeAt(0);
                let currentAlignment = 'left'; // Por defecto
                
                // Buscar en el elemento padre más cercano la alineación CSS
                let element = range.commonAncestorContainer;
                if (element.nodeType === Node.TEXT_NODE) {
                    element = element.parentElement;
                }
                
                // Método mejorado: verificar múltiples formas de alineación
                while (element && element !== document.getElementById('contenido_mensaje')) {
                    const computedStyle = window.getComputedStyle(element);
                    const textAlign = computedStyle.textAlign;
                    
                    // Verificar tanto computed style como atributos inline
                    if (textAlign && textAlign !== 'start' && textAlign !== 'end' && textAlign !== 'initial') {
                        currentAlignment = textAlign;
                        break;
                    }
                    
                    // También verificar el atributo align (usado por algunos navegadores)
                    const alignAttr = element.getAttribute('align');
                    if (alignAttr && ['left', 'center', 'right', 'justify'].includes(alignAttr.toLowerCase())) {
                        currentAlignment = alignAttr.toLowerCase();
                        break;
                    }
                    
                    // Verificar estilo inline
                    const style = element.getAttribute('style');
                    if (style) {
                        const match = style.match(/text-align:\s*(left|center|right|justify)/i);
                        if (match) {
                            currentAlignment = match[1].toLowerCase();
                            break;
                        }
                    }
                    
                    element = element.parentElement;
                }
                
                // Si no se encontró alineación específica, verificar el contenedor del editor
                if (currentAlignment === 'left') {
                    const editorStyle = window.getComputedStyle(document.getElementById('contenido_mensaje'));
                    if (editorStyle.textAlign && editorStyle.textAlign !== 'start' && editorStyle.textAlign !== 'end' && editorStyle.textAlign !== 'initial') {
                        currentAlignment = editorStyle.textAlign;
                    }
                }
                
                // Forzar la alineación en el contenedor para asegurar compatibilidad
                let alignmentStyle = currentAlignment;
                if (currentAlignment === 'center') {
                    alignmentStyle = 'center';
                } else if (currentAlignment === 'right') {
                    alignmentStyle = 'right';
                } else {
                    alignmentStyle = 'left';
                }
                
                const imgId = 'img_' + Date.now();
                const imgHtml = `
                    <div class="image-container" style="position: relative; display: block; margin: 10px 0; text-align: ${alignmentStyle};">
                        <img id="${imgId}" src="${e.target.result}" alt="Imagen insertada" 
                             style="width: ${width}px; height: auto; display: inline-block; border-radius: 4px; cursor: pointer;" 
                             onclick="showImageControls('${imgId}')" 
                             ondblclick="resizeImage(this)"
                             title="Clic para mostrar controles, doble clic para redimensionar">
                        <div id="controls_${imgId}" class="image-controls" style="display: none; position: absolute; top: -35px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.8); border-radius: 4px; padding: 4px; z-index: 1000; white-space: nowrap;">
                            <button type="button" onclick="quickResize('${imgId}', 200)" style="background: #2196F3; color: white; border: none; padding: 4px 8px; margin: 0 2px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="Pequeño (200px)">S</button>
                            <button type="button" onclick="quickResize('${imgId}', 400)" style="background: #2196F3; color: white; border: none; padding: 4px 8px; margin: 0 2px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="Mediano (400px)">M</button>
                            <button type="button" onclick="quickResize('${imgId}', 600)" style="background: #2196F3; color: white; border: none; padding: 4px 8px; margin: 0 2px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="Grande (600px)">L</button>
                            <button type="button" onclick="customResize('${imgId}')" style="background: #FF9800; color: white; border: none; padding: 4px 8px; margin: 0 2px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="Tamaño personalizado">...</button>
                            <button type="button" onclick="alignImage('${imgId}', 'left')" style="background: #4CAF50; color: white; border: none; padding: 4px 8px; margin: 0 2px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="Alinear izquierda">⬅</button>
                            <button type="button" onclick="alignImage('${imgId}', 'center')" style="background: #4CAF50; color: white; border: none; padding: 4px 8px; margin: 0 2px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="Centrar">⬛</button>
                            <button type="button" onclick="alignImage('${imgId}', 'right')" style="background: #4CAF50; color: white; border: none; padding: 4px 8px; margin: 0 2px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="Alinear derecha">➡</button>
                            <button type="button" onclick="hideImageControls('${imgId}')" style="background: #f44336; color: white; border: none; padding: 4px 8px; margin: 0 2px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="Cerrar controles">×</button>
                        </div>
                    </div>
                `;
                document.execCommand('insertHTML', false, imgHtml);
                updateHiddenTextarea();
                document.getElementById('contenido_mensaje').focus();
            };
            tempImg.src = e.target.result;
        };
        reader.readAsDataURL(file);
        document.body.removeChild(input);
    };
    
    document.body.appendChild(input);
    input.click();
}

/**
 * Funciones para controles de imagen
 */
function showImageControls(imgId) {
    document.querySelectorAll('.image-controls').forEach(control => {
        control.style.display = 'none';
    });
    
    const controls = document.getElementById('controls_' + imgId);
    if (controls) {
        controls.style.display = 'block';
    }
}

function hideImageControls(imgId) {
    const controls = document.getElementById('controls_' + imgId);
    if (controls) {
        controls.style.display = 'none';
    }
}

function quickResize(imgId, width) {
    const img = document.getElementById(imgId);
    if (img) {
        // Usar width directo en lugar de max-width para compatibilidad con email
        img.style.width = width + 'px';
        img.style.height = 'auto';
        // Remover max-width si existe
        img.style.maxWidth = '';
        img.style.maxHeight = '';
        
        updateHiddenTextarea();
        mostrarNotificacion(`Imagen redimensionada a ${width}px de ancho`, 'exito');
        hideImageControls(imgId);
    }
}

function alignImage(imgId, alignment) {
    const img = document.getElementById(imgId);
    if (img) {
        const container = img.closest('.image-container');
        if (container) {
            container.style.textAlign = alignment;
            updateHiddenTextarea();
            mostrarNotificacion(`Imagen alineada a la ${alignment === 'left' ? 'izquierda' : alignment === 'right' ? 'derecha' : 'centro'}`, 'exito');
            hideImageControls(imgId);
        }
    }
}

function customResize(imgId) {
    const img = document.getElementById(imgId);
    if (!img) return;
    
    const currentWidth = img.offsetWidth;
    const newWidth = prompt('Ingrese el ancho deseado en píxeles:', currentWidth);
    
    if (newWidth && !isNaN(newWidth) && parseInt(newWidth) > 0) {
        // Usar width directo en lugar de max-width para compatibilidad con email
        img.style.width = parseInt(newWidth) + 'px';
        img.style.height = 'auto';
        // Remover max-width si existe
        img.style.maxWidth = '';
        img.style.maxHeight = '';
        
        updateHiddenTextarea();
        mostrarNotificacion(`Imagen redimensionada a ${parseInt(newWidth)}px de ancho`, 'exito');
        hideImageControls(imgId);
    }
}

function resizeImage(img) {
    const currentWidth = img.offsetWidth;
    const sizes = [
        { label: 'Pequeño (200px)', width: 200 },
        { label: 'Mediano (400px)', width: 400 },
        { label: 'Grande (600px)', width: 600 },
        { label: 'Extra Grande (800px)', width: 800 },
        { label: 'Tamaño personalizado...', width: 'custom' }
    ];
    
    let options = sizes.map((size, index) => `${index + 1}. ${size.label}`).join('\n');
    options += '\n0. Cancelar';
    
    const choice = prompt(`Seleccione el nuevo tamaño para la imagen:\n\n${options}\n\nTamaño actual: ${currentWidth}px`);
    
    if (choice === null || choice === '0') return;
    
    const selectedIndex = parseInt(choice) - 1;
    if (selectedIndex >= 0 && selectedIndex < sizes.length) {
        let newWidth = sizes[selectedIndex].width;
        
        if (newWidth === 'custom') {
            newWidth = prompt('Ingrese el ancho deseado en píxeles:', currentWidth);
            if (!newWidth || isNaN(newWidth)) return;
            newWidth = parseInt(newWidth);
        }
        
        // Usar width directo en lugar de max-width para compatibilidad con email
        img.style.width = newWidth + 'px';
        img.style.height = 'auto';
        // Remover max-width si existe
        img.style.maxWidth = '';
        img.style.maxHeight = '';
        
        updateHiddenTextarea();
        mostrarNotificacion(`Imagen redimensionada a ${newWidth}px de ancho`, 'exito');
    }
}

/**
 * Funciones auxiliares del editor
 */
function updateHiddenTextarea() {
    const editorContent = document.getElementById('contenido_mensaje');
    const hiddenTextarea = document.getElementById('mensaje_hidden');
    const htmlContent = editorContent.innerHTML;
    
    hiddenTextarea.value = htmlContent;
}

function updateButtonStates() {
    const editor = document.getElementById('contenido_mensaje');
    const selection = window.getSelection();
    
    if (!editor || (!editor.contains(document.activeElement) && !editor.contains(selection.anchorNode))) {
        return;
    }

    const buttonCommands = {
        'bold': 'button[onclick*="formatText(\'bold\')"]',
        'italic': 'button[onclick*="formatText(\'italic\')"]',
        'underline': 'button[onclick*="formatText(\'underline\')"]',
        'justifyLeft': 'button[onclick*="formatText(\'justifyLeft\')"]',
        'justifyCenter': 'button[onclick*="formatText(\'justifyCenter\')"]',
        'justifyRight': 'button[onclick*="formatText(\'justifyRight\')"]',
        'insertUnorderedList': 'button[onclick*="formatText(\'insertUnorderedList\')"]',
        'insertOrderedList': 'button[onclick*="formatText(\'insertOrderedList\')"]'
    };

    Object.entries(buttonCommands).forEach(([command, selector]) => {
        const button = document.querySelector(selector);
        if (button) {
            try {
                const isActive = document.queryCommandState(command);
                if (isActive) {
                    button.classList.add('active');
                } else {
                    button.classList.remove('active');
                }
            } catch (e) {
                button.classList.remove('active');
            }
        }
    });
}

function handlePaste(event) {
    event.preventDefault();
    const text = (event.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
    updateHiddenTextarea();
}

/**
 * Funciones para el manejo de archivos Excel
 */
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        const selectedFileElement = document.getElementById('selectedFileName');
        selectedFileElement.textContent = file.name;
        selectedFileElement.classList.add('active');
        
        const formData = new FormData();
        formData.append('excelFile', file);
        
        mostrarNotificacion('Cargando datos del archivo...', 'info');
        
        fetch('cargar_excel.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion('Datos cargados correctamente. El archivo usado es clientes_' + new Date().toLocaleDateString('es-ES').replace(/\//g, '-') + '.xlsx', 'exito');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
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

/**
 * Funciones para notificaciones
 */
function mostrarNotificacion(mensaje, tipo, duracion = 4000) {
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
    }, duracion);
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

/**
 * Funciones para manejo de imágenes adjuntas
 */
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
        
        // Campo oculto para el enlace de la imagen
        const linkInput = document.createElement('input');
        linkInput.type = 'hidden';
        linkInput.name = `imagen_links[]`;
        linkInput.value = imagen.link || '';
        
        contenedor.appendChild(input);
        contenedor.appendChild(linkInput);
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

    // Validar tamaño de archivos (2MB máximo)
    const maxSizeBytes = 2 * 1024 * 1024; // 2MB en bytes
    let hasOversizedFiles = false;
    
    Array.from(files).forEach(file => {
        if (file.size > maxSizeBytes) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            alert(`⚠️ El archivo "${file.name}" es demasiado grande (${sizeMB}MB).\n\nTamaño máximo permitido: 2MB\n\nPor favor, reduce el tamaño de la imagen o elige otra.`);
            hasOversizedFiles = true;
        }
    });
    
    if (hasOversizedFiles) {
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
            
            // Campo para el enlace de la imagen
            const linkContainer = document.createElement('div');
            linkContainer.className = 'link-container';
            
            const linkLabel = document.createElement('label');
            linkLabel.textContent = 'Enlace (opcional):';
            linkLabel.className = 'link-label';
            
            const linkInput = document.createElement('input');
            linkInput.type = 'url';
            linkInput.placeholder = 'https://ejemplo.com';
            linkInput.className = 'link-input';
            
            const deleteBtn = document.createElement('button');
            deleteBtn.innerHTML = '×';
            deleteBtn.className = 'delete-image';
            
            fetch(e.target.result)
                .then(res => res.blob())
                .then(blob => {
                    const imagen = {
                        blob: blob,
                        name: file.name,
                        type: file.type,
                        link: ''
                    };
                    const imageIndex = imagenes.length;
                    imagenes.push(imagen);
                    
                    // Actualizar el enlace cuando el usuario escribe
                    linkInput.oninput = function() {
                        imagenes[imageIndex].link = this.value;
                        actualizarImagenesOcultas();
                    };
                    
                    deleteBtn.onclick = function(e) {
                        e.preventDefault();
                        imagenes.splice(imageIndex, 1);
                        imgContainer.remove();
                        actualizarImagenesOcultas();
                    };
                    
                    actualizarImagenesOcultas();
                });
            
            linkContainer.appendChild(linkLabel);
            linkContainer.appendChild(linkInput);
            
            imgContainer.appendChild(img);
            imgContainer.appendChild(linkContainer);
            imgContainer.appendChild(deleteBtn);
            previsualizacion.appendChild(imgContainer);
        };
        reader.readAsDataURL(file);
    });
    
    event.target.value = '';
}

/**
 * Funciones para filtrado y selección
 */
function filtrarPorTratamientos() {
    const checkboxes = document.querySelectorAll('input[name="filtro_tratamientos[]"]:checked');
    const selectedTratamientos = Array.from(checkboxes).map(cb => cb.value);
    const selectElement = document.getElementById('destinatarios');
    const options = selectElement.querySelectorAll('option');
    
    options.forEach(option => {
        const tratamientosOption = option.dataset.tratamientos || '';
        const tratamientosArray = tratamientosOption.split(',').map(t => t.trim());
        
        if (selectedTratamientos.length === 0) {
            option.style.display = '';
        } else {
            const tieneAlgunTratamiento = selectedTratamientos.some(tratamiento => 
                tratamientosArray.includes(tratamiento)
            );
            option.style.display = tieneAlgunTratamiento ? '' : 'none';
            
            if (!tieneAlgunTratamiento) {
                option.selected = false;
            }
        }
    });
    
    actualizarBotonSeleccionarTodos();
}

function seleccionarTodosTratamientos() {
    const checkboxes = document.querySelectorAll('input[name="filtro_tratamientos[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    filtrarPorTratamientos();
}

function limpiarTratamientos() {
    const checkboxes = document.querySelectorAll('input[name="filtro_tratamientos[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    filtrarPorTratamientos();
}

function actualizarBotonSeleccionarTodos() {
    const selectElement = document.getElementById('destinatarios');
    const visibleOptions = Array.from(selectElement.options).filter(option => 
        option.style.display !== 'none'
    );
    const selectedVisibleOptions = visibleOptions.filter(option => option.selected);
    const selectAllBtn = document.getElementById('selectAll');
    
    if (visibleOptions.length === 0) {
        selectAllBtn.textContent = 'Seleccionar Todos';
        selectAllBtn.disabled = true;
    } else {
        selectAllBtn.disabled = false;
        selectAllBtn.textContent = selectedVisibleOptions.length === visibleOptions.length ? 
            'Deseleccionar Todos' : 'Seleccionar Todos';
    }
}

function limpiarFormularioCompleto() {
    document.getElementById('emailForm').reset();
    
    const selectElement = document.getElementById('destinatarios');
    Array.from(selectElement.options).forEach(option => {
        option.selected = false;
    });
    
    document.getElementById('asunto').value = '';
    
    const editorContent = document.getElementById('contenido_mensaje');
    editorContent.innerHTML = '';
    document.getElementById('mensaje_hidden').value = '';
    
    document.getElementById('textColor').value = '#000000';
    
    const fontSizeSelect = document.querySelector('select[onchange="changeFontSize(this.value)"]');
    if (fontSizeSelect) {
        fontSizeSelect.value = '';
    }
    
    document.getElementById('selectorImagenes').value = '';
    document.getElementById('previsualizacion').innerHTML = '';
    imagenes = [];
    
    const checkboxes = document.querySelectorAll('input[name="filtro_tratamientos[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    filtrarPorTratamientos();
    actualizarBotonSeleccionarTodos();
    document.getElementById('destinatarios').focus();
}

/**
 * Sistema de tooltips
 */
function createTooltip(text, x, y) {
    if (currentTooltip) {
        currentTooltip.remove();
    }

    currentTooltip = document.createElement('div');
    currentTooltip.className = 'tooltip-dynamic';
    currentTooltip.textContent = text;
    
    currentTooltip.style.left = (x + 10) + 'px';
    currentTooltip.style.top = (y - 35) + 'px';
    
    document.body.appendChild(currentTooltip);
    
    const rect = currentTooltip.getBoundingClientRect();
    const windowWidth = window.innerWidth;
    const windowHeight = window.innerHeight;
    
    if (rect.right > windowWidth) {
        currentTooltip.style.left = (x - rect.width - 10) + 'px';
    }
    
    if (rect.top < 0) {
        currentTooltip.style.top = (y + 25) + 'px';
    }
    
    setTimeout(() => {
        currentTooltip.classList.add('show');
    }, 10);
}

function removeTooltip() {
    if (currentTooltip) {
        currentTooltip.classList.remove('show');
        setTimeout(() => {
            if (currentTooltip) {
                currentTooltip.remove();
                currentTooltip = null;
            }
        }, 200);
    }
}

/**
 * Inicialización cuando el DOM está listo
 */
document.addEventListener('DOMContentLoaded', function() {
    // Limpiar URL después de mostrar mensaje de éxito/error para evitar reenvío con F5
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('sent') || urlParams.has('error') || urlParams.has('msg')) {
        // Verificar si hay un mensaje visible
        const notificacion = document.getElementById('notificacion');
        if (notificacion) {
            // Esperar un poco para que el usuario vea el mensaje, luego limpiar la URL
            setTimeout(function() {
                // Usar replaceState para limpiar la URL sin recargar la página
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({}, '', newUrl);
            }, 3000); // Esperar 3 segundos para que el usuario vea el mensaje
        }
    }
    
    // Inicializar editor
    const editor = document.getElementById('contenido_mensaje');
    if (editor) {
        editor.addEventListener('blur', function() {
            if (this.innerHTML.trim() === '') {
                this.innerHTML = '';
            }
        });
        
        editor.addEventListener('focus', function() {
            if (this.innerHTML.trim() === '') {
                this.innerHTML = '';
            }
        });
        
        editor.addEventListener('click', function(e) {
            if (!e.target.closest('.image-controls') && !e.target.closest('img')) {
                document.querySelectorAll('.image-controls').forEach(control => {
                    control.style.display = 'none';
                });
            }
            setTimeout(updateButtonStates, 10);
        });
        
        editor.addEventListener('keyup', updateButtonStates);
        editor.addEventListener('mouseup', updateButtonStates);
        editor.addEventListener('selectionchange', updateButtonStates);
        
        updateHiddenTextarea();
        setTimeout(updateButtonStates, 100);
    }
    
    // Eventos para selección de todos los destinatarios
    const selectAllBtn = document.getElementById('selectAll');
    const selectElement = document.getElementById('destinatarios');
    let allSelected = false;

    if (selectAllBtn && selectElement) {
        selectAllBtn.addEventListener('click', function() {
            const visibleOptions = Array.from(selectElement.options).filter(option => 
                option.style.display !== 'none'
            );
            
            allSelected = !allSelected;
            visibleOptions.forEach(option => {
                option.selected = allSelected;
            });
            
            selectElement.focus();
            selectElement.blur();
            selectAllBtn.textContent = allSelected ? 'Deseleccionar Todos' : 'Seleccionar Todos';
        });
    }

    // Inicializar configuración SMTP
    initializeConfigForm();
    
    // Inicializar sidebar
    initializeSidebar();
    
    // Inicializar tooltips
    initializeTooltips();
    
    // Inicializar formulario de email
    initializeEmailForm();
    
    // Mostrar notificación inicial si existe
    const notificacion = document.getElementById('notificacion');
    if (notificacion) {
        notificacion.style.opacity = '1';
        setTimeout(ocultarMensaje, 4000);
    }
    
    actualizarBotonSeleccionarTodos();
});

/**
 * Funciones de inicialización
 */
function initializeConfigForm() {
    // Toggle de contraseña
    const togglePassword = document.querySelector('.toggle-password');
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type');
            
            if (type === 'password') {
                passwordInput.setAttribute('type', 'text');
                this.textContent = '🔒';
                this.setAttribute('aria-label', 'Ocultar contraseña');
            } else {
                passwordInput.setAttribute('type', 'password');
                this.textContent = '👁️';
                this.setAttribute('aria-label', 'Mostrar contraseña');
            }
        });
    }

    // Formulario de configuración
    const configForm = document.getElementById('configForm');
    if (configForm) {
        configForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Guardando...';
            
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
                    document.getElementById('sidebar').classList.remove('active');
                    document.getElementById('sidebarBackdrop').classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                    mostrarNotificacion('Configuración actualizada correctamente', 'exito');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
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
                submitButton.disabled = false;
                submitButton.textContent = 'Guardar configuración';
            });
        });
    }
}

function initializeSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebarBackdrop.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        });
    }
    
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarBackdrop.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        });
    }
    
    // Cerrar sidebar con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.remove('active');
            sidebarBackdrop.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        }
    });
}

function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const text = this.getAttribute('data-tooltip');
            if (text) {
                createTooltip(text, e.pageX, e.pageY);
            }
        });

        element.addEventListener('mousemove', function(e) {
            if (currentTooltip) {
                const x = e.pageX;
                const y = e.pageY;
                
                currentTooltip.style.left = (x + 10) + 'px';
                currentTooltip.style.top = (y - 35) + 'px';
                
                const rect = currentTooltip.getBoundingClientRect();
                const windowWidth = window.innerWidth;
                const windowHeight = window.innerHeight;
                
                if (rect.right > windowWidth) {
                    currentTooltip.style.left = (x - rect.width - 10) + 'px';
                }
                
                if (rect.top < 0) {
                    currentTooltip.style.top = (y + 25) + 'px';
                }
            }
        });

        element.addEventListener('mouseleave', function() {
            removeTooltip();
        });
    });
}

function initializeEmailForm() {
    const emailForm = document.getElementById('emailForm');
    if (emailForm) {
        emailForm.onsubmit = function(e) {
            e.preventDefault();
            
            // DEBUGGING: Verificar contenido antes del envío
            updateHiddenTextarea(); // Asegurar que esté actualizado
            
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
            .then((response) => response.json())
            .then((data) => {
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
                                limpiarFormularioCompleto();
                                
                                // Actualizar URL para evitar reenvío si se recarga la página (F5)
                                // esto complementa el patrón PRG del servidor
                                const cleanUrl = window.location.pathname;
                                window.history.replaceState({}, document.title, cleanUrl);
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
    }
}

/**
 * Funciones para el selector de iconos
 */

// Colección de iconos organizados por categorías
const iconCollection = {
    emotions: [
        '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇',
        '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚',
        '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🥳',
        '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖',
        '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯',
        '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔',
        '🤭', '🤫', '🤥', '😶', '😐', '😑', '😬', '🙄', '😯', '😦',
        '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴',
        '🤢', '🤮', '🤧', '😷', '🤒', '🤕', '🤑', '🤠'
    ],
    objects: [
        '📱', '💻', '🖥️', '⌨️', '🖱️', '🖨️', '📷', '📹', '🎥', '📞',
        '☎️', '📠', '📺', '📻', '🎵', '🎶', '🎤', '🎧', '📢', '📣',
        '📯', '🔔', '🔕', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🎻',
        '🎮', '🕹️', '🎯', '🎲', '🎰', '🎳', '⚽', '🏀', '🏈', '⚾',
        '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒',
        '🏑', '🥍', '🏏', '🪃', '🥅', '⛳', '🪁', '🏹', '🎣', '🤿',
        '🥊', '🥋', '🎽', '🛹', '🛷', '⛸️', '🥌', '🎿', '⛷️', '🏂'
    ],
    symbols: [
        '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔',
        '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️',
        '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐',
        '⭐', '🌟', '💫', '⚡', '💥', '💢', '💨', '💦', '💤', '💨',
        '🔥', '🌈', '☀️', '🌤️', '⛅', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️',
        '❄️', '☃️', '⛄', '🌬️', '💨', '🌪️', '🌫️', '🌊', '💧', '☔',
        '✨', '⭐', '🌟', '💫', '✅', '❌', '❎', '✔️', '☑️', '❓',
        '❔', '❕', '❗', '‼️', '⁉️', '💯', '🔀', '🔁', '🔂', '▶️',
        '⏩', '⏭️', '⏯️', '◀️', '⏪', '⏮️', '🔼', '⏫', '🔽', '⏬'
    ],
    nature: [
        '🌱', '🌿', '🍀', '🌾', '🌵', '🌲', '🌳', '🌴', '🌊', '🌍',
        '🌎', '🌏', '🌑', '🌒', '🌓', '🌔', '🌕', '🌖', '🌗', '🌘',
        '🌙', '🌚', '🌛', '🌜', '🌝', '☀️', '🌞', '⭐', '🌟', '💫',
        '🌠', '☁️', '⛅', '⛈️', '🌤️', '🌦️', '🌧️', '🌩️', '🌨️', '❄️',
        '☃️', '⛄', '🌬️', '💨', '🌪️', '🌫️', '🌈', '☔', '💧', '💦',
        '🌊', '🔥', '✨', '🌺', '🌸', '🌼', '🌻', '🌷', '🌹', '🥀',
        '🌮', '🌯', '🌰', '🌱', '🌲', '🌳', '🌴', '🌵', '🌶️', '🌽',
        '🍄', '🍅', '🍆', '🍇', '🍈', '🍉', '🍊', '🍋', '🍌', '🍍'
    ]
};

let currentIconCategory = 'all';
let allIcons = [];
let savedRange = null; // Variable para guardar la posición del cursor

// Función para inicializar la colección completa de iconos
function initializeIconCollection() {
    allIcons = [];
    Object.values(iconCollection).forEach(categoryIcons => {
        allIcons = allIcons.concat(categoryIcons);
    });
}

// Función para abrir el selector de iconos
function openIconPicker() {
    console.log('🚀 Abriendo selector de iconos...');
    
    const editor = document.getElementById('contenido_mensaje');
    const modal = document.getElementById('iconPickerModal');
    
    // Verificar que los elementos existen
    if (!editor) {
        console.error('❌ No se encontró el editor con ID: contenido_mensaje');
        alert('Error: No se encontró el editor de texto');
        return;
    }
    
    if (!modal) {
        console.error('❌ No se encontró el modal con ID: iconPickerModal');
        alert('Error: No se encontró el modal de iconos');
        return;
    }
    
    console.log('✅ Elementos encontrados correctamente');
    
    // Guardar la posición actual del cursor
    if (editor.contains(document.activeElement) || editor === document.activeElement) {
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
            savedRange = selection.getRangeAt(0).cloneRange();
            console.log('✅ Posición del cursor guardada');
        }
    } else {
        // Si el editor no tiene foco, enfocarlo y colocar el cursor al final
        editor.focus();
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(editor);
        range.collapse(false); // Colocar al final
        selection.removeAllRanges();
        selection.addRange(range);
        savedRange = range.cloneRange();
        console.log('✅ Editor enfocado y cursor colocado al final');
    }
    
    if (allIcons.length === 0) {
        console.log('📦 Inicializando colección de iconos...');
        initializeIconCollection();
        console.log(`✅ Colección inicializada con ${allIcons.length} iconos`);
    }
    
    console.log('🎨 Mostrando modal...');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Mostrar todos los iconos por defecto
    showIconCategory('all');
    
    // Enfocar el campo de búsqueda
    setTimeout(() => {
        const searchInput = document.getElementById('iconSearch');
        if (searchInput) {
            searchInput.focus();
            console.log('🔍 Campo de búsqueda enfocado');
        } else {
            console.error('❌ No se encontró el campo de búsqueda');
        }
    }, 100);
    
    console.log('🎉 Selector de iconos abierto exitosamente');
}

// Función para cerrar el selector de iconos
function closeIconPicker() {
    const modal = document.getElementById('iconPickerModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    
    // Limpiar búsqueda
    document.getElementById('iconSearch').value = '';
    
    // Volver a mostrar todos los iconos
    showIconCategory('all');
    
    // Restaurar el foco al editor si había una posición guardada
    if (savedRange) {
        const editor = document.getElementById('contenido_mensaje');
        editor.focus();
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(savedRange);
        savedRange = null; // Limpiar la referencia
    }
}

// Función para mostrar iconos por categoría
function showIconCategory(category) {
    console.log(`📂 Mostrando categoría: ${category}`);
    
    currentIconCategory = category;
    
    // Actualizar botones de categoría
    document.querySelectorAll('.icon-category').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const activeButton = document.querySelector(`[onclick="showIconCategory('${category}')"]`);
    if (activeButton) {
        activeButton.classList.add('active');
        console.log(`✅ Botón de categoría '${category}' activado`);
    } else {
        console.error(`❌ No se encontró el botón para la categoría '${category}'`);
    }
    
    // Obtener iconos de la categoría
    let iconsToShow = [];
    if (category === 'all') {
        iconsToShow = allIcons;
    } else {
        iconsToShow = iconCollection[category] || [];
    }
    
    console.log(`📊 Iconos a mostrar: ${iconsToShow.length}`);
    
    // Renderizar iconos
    renderIcons(iconsToShow);
}

// Función para renderizar iconos en la cuadrícula
function renderIcons(icons) {
    console.log(`🎨 Renderizando ${icons.length} iconos...`);
    
    const iconGrid = document.getElementById('iconGrid');
    if (!iconGrid) {
        console.error('❌ No se encontró el elemento iconGrid');
        return;
    }
    
    iconGrid.innerHTML = '';
    
    if (icons.length === 0) {
        iconGrid.innerHTML = '<p style="text-align: center; color: #666; grid-column: 1/-1;">No hay iconos para mostrar</p>';
        console.log('⚠️ No hay iconos para renderizar');
        return;
    }
    
    icons.forEach((icon, index) => {
        const iconElement = document.createElement('div');
        iconElement.className = 'icon-item';
        iconElement.textContent = icon;
        iconElement.title = `Insertar icono: ${icon}`;
        iconElement.onclick = () => {
            console.log(`🎯 Click en icono: ${icon} (índice: ${index})`);
            insertIcon(icon);
        };
        iconGrid.appendChild(iconElement);
    });
    
    console.log(`✅ ${icons.length} iconos renderizados correctamente`);
}

// Función para insertar un icono en el editor
function insertIcon(icon) {
    console.log(`📝 Insertando icono: ${icon}`);
    
    const editor = document.getElementById('contenido_mensaje');
    if (!editor) {
        console.error('❌ No se encontró el editor para insertar el icono');
        return;
    }
    
    // Restaurar la posición del cursor guardada
    if (savedRange) {
        console.log('🎯 Restaurando posición del cursor guardada');
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(savedRange);
        
        // Insertar el icono en la posición restaurada
        document.execCommand('insertText', false, icon);
        console.log('✅ Icono insertado en posición guardada');
        
        // Limpiar la referencia guardada
        savedRange = null;
    } else {
        console.log('🎯 Usando fallback: insertando al final del editor');
        // Fallback: enfocar el editor e insertar al final
        editor.focus();
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(editor);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
        
        document.execCommand('insertText', false, icon);
        console.log('✅ Icono insertado al final del editor');
    }
    
    // Actualizar el textarea oculto
    updateHiddenTextarea();
    
    // Cerrar el selector de iconos
    closeIconPicker();
    
    // Mostrar notificación
    mostrarNotificacion(`Icono ${icon} insertado correctamente`, 'exito', 2000);
    
    // Mantener el foco en el editor
    editor.focus();
    
    console.log('🎉 Proceso de inserción completado exitosamente');
}

// Función para filtrar iconos por búsqueda
function filterIcons() {
    const searchTerm = document.getElementById('iconSearch').value.toLowerCase();
    
    if (searchTerm === '') {
        // Si no hay término de búsqueda, mostrar la categoría actual
        showIconCategory(currentIconCategory);
        return;
    }
    
    // Obtener iconos de la categoría actual o todos si es 'all'
    let iconsToFilter = [];
    if (currentIconCategory === 'all') {
        iconsToFilter = allIcons;
    } else {
        iconsToFilter = iconCollection[currentIconCategory] || [];
    }
    
    // Por simplicidad, mostrar todos los iconos de la categoría
    // (la búsqueda por texto es compleja con emojis)
    renderIcons(iconsToFilter);
}

// Función de debug para probar el selector de iconos
window.debugIconPicker = function() {
    console.log('🔧 INICIANDO DEBUG DEL SELECTOR DE ICONOS');
    console.log('==========================================');
    
    // Verificar elementos
    const elements = {
        modal: document.getElementById('iconPickerModal'),
        iconGrid: document.getElementById('iconGrid'),
        iconSearch: document.getElementById('iconSearch'),
        editor: document.getElementById('contenido_mensaje'),
        iconButton: document.querySelector('button[onclick="openIconPicker()"]')
    };
    
    console.log('📋 Estado de los elementos:');
    Object.entries(elements).forEach(([name, element]) => {
        console.log(`  ${name}:`, element ? '✅ OK' : '❌ NO ENCONTRADO');
    });
    
    // Verificar variables globales
    console.log('📊 Variables globales:');
    console.log(`  allIcons: ${allIcons ? allIcons.length : 'No definido'} iconos`);
    console.log(`  iconCollection: ${iconCollection ? Object.keys(iconCollection).length : 'No definido'} categorías`);
    console.log(`  currentIconCategory: ${currentIconCategory || 'No definido'}`);
    console.log(`  savedRange: ${savedRange ? 'Guardado' : 'No guardado'}`);
    
    // Probar abrir el modal
    console.log('🧪 Probando abrir el selector...');
    try {
        openIconPicker();
        console.log('✅ Selector abierto exitosamente');
    } catch (error) {
        console.error('❌ Error al abrir el selector:', error);
    }
    
    console.log('==========================================');
};

// Inicializar la colección de iconos cuando se cargue la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando sistema de iconos...');
    
    // Verificar elementos críticos
    const modal = document.getElementById('iconPickerModal');
    const iconGrid = document.getElementById('iconGrid');
    const iconButton = document.querySelector('button[onclick="openIconPicker()"]');
    
    console.log('🔍 Verificando elementos del DOM...');
    console.log('Modal:', modal ? '✅ Encontrado' : '❌ No encontrado');
    console.log('IconGrid:', iconGrid ? '✅ Encontrado' : '❌ No encontrado');
    console.log('Botón de iconos:', iconButton ? '✅ Encontrado' : '❌ No encontrado');
    
    if (!modal || !iconGrid || !iconButton) {
        console.error('❌ Faltan elementos críticos para el selector de iconos');
    } else {
        console.log('✅ Todos los elementos del selector de iconos encontrados');
    }
    
    // Inicializar colección de iconos
    initializeIconCollection();
    console.log(`📦 Colección de iconos inicializada: ${allIcons.length} iconos totales`);
    
    // Verificar categorías
    Object.keys(iconCollection).forEach(category => {
        console.log(`📂 Categoría '${category}': ${iconCollection[category].length} iconos`);
    });
    
    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('iconPickerModal');
            if (modal && modal.classList.contains('active')) {
                console.log('⌨️ Cerrando modal con tecla ESC');
                closeIconPicker();
            }
        }
    });
    
    // Cerrar modal haciendo clic fuera del contenido
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                console.log('🖱️ Cerrando modal con clic fuera');
                closeIconPicker();
            }
        });
    }
    
    console.log('🎉 Sistema de iconos inicializado correctamente');
});

