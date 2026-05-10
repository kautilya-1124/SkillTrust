<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

skilltrust_env_load(dirname(__DIR__));

require_once __DIR__ . '/../config/db.php';



require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();