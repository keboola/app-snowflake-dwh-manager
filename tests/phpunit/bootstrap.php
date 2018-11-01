<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../../vendor/autoload.php';

$dotenv = new Dotenv();
$dotenvPath = __DIR__ . '/../../.env';

if (file_exists($dotenvPath) && is_readable($dotenvPath)) {
    $dotenv->load($dotenvPath);
}
