<?php

class laolinDb extends medoo {
  public function __construct() {
    $optn=[
        'database_type' => 'mysql',
        'database_name' => api_g('DBNAME'),
        'server' => api_g('DBSERVER'),
        'port' => api_g('DBPORT'),
        'username' => api_g('DBUSER'),
        'password' => api_g('DBPASS'),
        'charset' => 'utf8'
      ];
    parent::__construct($optn);
  }
}
