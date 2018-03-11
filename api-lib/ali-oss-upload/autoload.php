<?php

function classLoader($class)
{
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . DIRECTORY_SEPARATOR .'src'. DIRECTORY_SEPARATOR . $path . '.php';
    //error_log($path);
    //error_log($file);
    if (file_exists($file)) {
        require_once $file;
    }
}
spl_autoload_register('classLoader');