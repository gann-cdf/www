<?php

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('America/New_York');

Dotenv::create(__DIR__)->load();
