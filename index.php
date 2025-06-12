<?php
/**
 * Punto de entrada principal de la aplicación
 * Envío de felicitaciones y promociones por correo electrónico
 * 
 * Este archivo actúa como controlador principal que incluye
 * el procesador correspondiente para mantener la separación de responsabilidades.
 */

// Verificar autenticación antes de continuar
require_once __DIR__ . '/includes/auth.php';

if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

// Incluir funciones principales para la limpieza automática
require_once __DIR__ . '/includes/functions.php';

// Incluir el procesador principal
require_once __DIR__ . '/process.php';
