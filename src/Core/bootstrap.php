<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

spl_autoload_register(function ($class) {
    $base = __DIR__ . '/..';
    $folders = ['Models', 'Repositories', 'Services', 'Controllers'];
    foreach ($folders as $folder) {
        $file = $base . '/' . $folder . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
