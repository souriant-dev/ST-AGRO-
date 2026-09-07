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

// Clé API GroqCloud — définir GROQ_API_KEY dans l'environnement
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');

// Clé API Gemini pour le diagnostic visuel des maladies et insectes
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// Clé API Plant.id v3 pour l'évaluation sanitaire des plantes
define('PLANT_ID_API_KEY', getenv('PLANT_ID_API_KEY') ?: '9OHLvTnz6w8z9wzobbEXkOYuabRJHRL1AYGuyAy0E9mZ3y5Wnr');

// Clé API Pl@ntNet — https://my.plantnet.org/
//define('PLANTNET_API_KEY', getenv('PLANTNET_API_KEY') ?: '2b10HQN9MoyLyNziC5u9FOdVu');

// Clé API Geoapify — https://www.geoapify.com/
define('GEOAPIFY_API_KEY', getenv('GEOAPIFY_API_KEY') ?: 'b3246354b4e841fa9fdb97c3ca0c9e14');

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
