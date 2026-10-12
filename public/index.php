<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'application' => 'ERP AI Integration',
    'backend' => 'PHP',
    'php_version' => PHP_VERSION,
    'status' => 'running'
], JSON_PRETTY_PRINT);