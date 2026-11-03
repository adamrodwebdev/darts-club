<?php

// Contrôle de la CI, monté à la place de public/index.php dans l'image de production : réglages PHP tels
// que le serveur web (FrankenPHP) les applique vraiment. Jamais copié dans l'image (.github est exclu).
header('Content-Type: application/json');

$mustBeDisabled = ['exec', 'passthru', 'shell_exec', 'system', 'proc_open', 'popen', 'mail', 'putenv'];

echo json_encode([
    'sapi' => PHP_SAPI,
    'enabled' => array_values(array_filter($mustBeDisabled, 'function_exists')),
    'exception_ignore_args' => ini_get('zend.exception_ignore_args'),
    'register_argc_argv' => ini_get('register_argc_argv'),
    'use_strict_mode' => ini_get('session.use_strict_mode'),
    'display_errors' => ini_get('display_errors'),
], \JSON_THROW_ON_ERROR);
