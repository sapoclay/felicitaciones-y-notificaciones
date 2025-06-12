<?php
/**
 * Sistema de autenticación basado en archivo JSON
 */

// Configuración de sesión
$session_timeout = 3600; // 1 hora en segundos
$session_name = 'correos_masivos_session';

/**
 * Cargar usuarios desde archivo JSON
 */
function cargarUsuarios() {
    $archivo_usuarios = __DIR__ . '/../usuarios.json';
    
    if (!file_exists($archivo_usuarios)) {
        error_log("ERROR: Archivo de usuarios no encontrado: " . $archivo_usuarios);
        return [];
    }
    
    $contenido = file_get_contents($archivo_usuarios);
    if ($contenido === false) {
        error_log("ERROR: No se pudo leer el archivo de usuarios");
        return [];
    }
    
    $usuarios = json_decode($contenido, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("ERROR: JSON inválido en archivo de usuarios: " . json_last_error_msg());
        return [];
    }
    
    return $usuarios;
}

/**
 * Función para verificar credenciales
 */
function verificarCredenciales($usuario, $password) {
    $usuarios = cargarUsuarios();
    
    if (empty($usuarios)) {
        error_log("ERROR: No se pudieron cargar usuarios para verificación");
        return false;
    }
    
    if (!isset($usuarios[$usuario])) {
        return false;
    }

    return password_verify($password, $usuarios[$usuario]);
}

/**
 * Función para verificar si el usuario está autenticado
 */
function estaAutenticado() {
    global $session_timeout, $session_name;
    
    if (session_status() == PHP_SESSION_NONE) {
        session_name($session_name);
        session_start();
    }
    
    if (!isset($_SESSION['usuario_autenticado']) || !isset($_SESSION['tiempo_login'])) {
        return false;
    }
    
    // Verificar timeout de sesión
    if (time() - $_SESSION['tiempo_login'] > $session_timeout) {
        cerrarSesion();
        return false;
    }
    
    // Actualizar tiempo de última actividad
    $_SESSION['tiempo_login'] = time();
    
    return true;
}

/**
 * Función para iniciar sesión
 */
function iniciarSesion($usuario) {
    global $session_name;
    
    if (session_status() == PHP_SESSION_NONE) {
        session_name($session_name);
        session_start();
    }
    
    $_SESSION['usuario_autenticado'] = $usuario;
    $_SESSION['tiempo_login'] = time();
    
    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);
}

/**
 * Función para cerrar sesión
 */
function cerrarSesion() {
    global $session_name;
    
    if (session_status() == PHP_SESSION_NONE) {
        session_name($session_name);
        session_start();
    }
    
    // Limpiar todas las variables de sesión
    $_SESSION = array();
    
    // Eliminar cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir sesión
    session_destroy();
}

/**
 * Función para obtener usuario actual
 */
function obtenerUsuarioActual() {
    return isset($_SESSION['usuario_autenticado']) ? $_SESSION['usuario_autenticado'] : null;
}

/**
 * Función para generar hash de contraseña (útil para crear nuevos usuarios)
 */
function generarHashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Función para agregar un nuevo usuario al archivo JSON
 */
function agregarUsuario($usuario, $password) {
    $usuarios = cargarUsuarios();
    $usuarios[$usuario] = password_hash($password, PASSWORD_DEFAULT);
    
    $archivo_usuarios = __DIR__ . '/../usuarios.json';
    $resultado = file_put_contents($archivo_usuarios, json_encode($usuarios, JSON_PRETTY_PRINT));
    
    if ($resultado === false) {
        error_log("ERROR: No se pudo escribir el archivo de usuarios");
        return false;
    }
    
    return true;
}

// CREDENCIALES ACTUALES:
// Usuario: ylekaraformulario, Contraseña: ylekara16
?>
