<?php

function getDatabase()
{
    $host = getenv('DB_HOST');
    $name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    $port = getenv('DB_PORT') ?: '3306';

    foreach (['DB_HOST' => $host, 'DB_NAME' => $name, 'DB_USER' => $user, 'DB_PASSWORD' => $password] as $key => $value) {
        if ($value === false || $value === '') {
            throw new RuntimeException('Missing required database environment variable: ' . $key);
        }
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $caPath = getenv('DB_CA_CERT');
    if ($caPath === false || $caPath === '') {
        $bundledCaPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'ca.pem';
        if (is_file($bundledCaPath) && is_readable($bundledCaPath)) {
            $caPath = $bundledCaPath;
        }
    }
    if ($caPath !== false && $caPath !== '') {
        if (!is_file($caPath) || !is_readable($caPath)) {
            throw new RuntimeException('DB_CA_CERT must point to a readable CA certificate.');
        }
        if (class_exists('Pdo\\Mysql')) {
            $sslCaOption = constant('Pdo\\Mysql::ATTR_SSL_CA');
            $verifyServerOption = constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT');
        } elseif (defined('PDO::MYSQL_ATTR_SSL_CA') && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $sslCaOption = constant('PDO::MYSQL_ATTR_SSL_CA');
            $verifyServerOption = constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT');
        } else {
            throw new RuntimeException('The PDO MySQL driver is required for database TLS.');
        }
        $options[$sslCaOption] = $caPath;
        $options[$verifyServerOption] = true;
    } elseif (!in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('Set DB_CA_CERT to a readable CA certificate for remote MySQL connections.');
    }

    return new PDO($dsn, $user, $password, $options);
}