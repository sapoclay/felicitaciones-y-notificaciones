<?php
/**
 * Script de logout
 */

require_once 'includes/auth.php';

// Cerrar sesión
cerrarSesion();

// Redirigir al login con mensaje
header('Location: login.php');
exit;
?>
