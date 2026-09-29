<?php

use opensuny\Converter;
$config = require __DIR__.'/env.php';

define('ROOT_DIR', realpath(__DIR__.'/../'));

require ROOT_DIR.'/convert/depend/vendor/autoload.php';


(new Converter($config))->run();