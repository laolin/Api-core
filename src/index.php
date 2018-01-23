<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
ini_set("display_errors", 0);
ini_set("error_log", "php_errors.log");

require_once 'dj-api-shell/api-root.php';

require_once 'system/main.php';

/* see README.md */

main( );
