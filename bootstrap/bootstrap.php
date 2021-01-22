<?php

// Inicializamos el autoload
require_once __DIR__.'/../vendor/autoload.php';
// Obtenemos el IoC Container
$container = include __DIR__.'/../config/container.php';

use Psr\Log\LoggerInterface;

$logger = $container->get(LoggerInterface::class);
$logger->debug('This will be logged to the file');
$logger->error('This will be logged to the file and the email');
