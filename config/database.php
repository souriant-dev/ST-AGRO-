<?php
/**
 * ST-AGRO — Connexion à la base de données (PDO)
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'stagro');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_CHARSET', 'utf8mb4');

// Clé API météo — https://openweathermap.org (créer un compte gratuit)
define('METEO_API_KEY', '6edc0ffad9597f1502200515f2f98db7');

// Clé API Mistral Studio — privilégier une variable d'environnement en production
define('MISTRAL_API_KEY', getenv('MISTRAL_API_KEY') ?: '4W1sSkCziXVjpjcVmyzGw4oKWCuPdaub');

// Clé API Pl@ntNet — https://my.plantnet.org/
define('PLANTNET_API_KEY', getenv('PLANTNET_API_KEY') ?: '2b10HQN9MoyLyNziC5u9FOdVu');

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }
    }
    return $pdo;
}
