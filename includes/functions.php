<?php
/**
 * ST-AGRO — Fonctions utilitaires
 */

function nettoyer(string $valeur): string
{
    return htmlspecialchars(trim($valeur), ENT_QUOTES, 'UTF-8');
}

function definirMessage(string $type, string $texte): void
{
    $_SESSION['flash'] = ['type' => $type, 'texte' => $texte];
}

function commanderIrrigation(?string $adresseIp, bool $activer): bool
{
    $adresseIp = trim((string) $adresseIp);
    if (!filter_var($adresseIp, FILTER_VALIDATE_IP) || !function_exists('curl_init')) {
        return false;
    }

    $hote = filter_var($adresseIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $adresseIp . ']' : $adresseIp;
    $url = 'http://' . $hote . '/POMPE=' . ($activer ? 'ON' : 'OFF');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FAILONERROR => false,
    ]);
    curl_exec($ch);
    $erreur = curl_errno($ch);
    $codeHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $erreur === 0 && $codeHttp >= 200 && $codeHttp < 300;
}

function activerIrrigationSiSolSec(PDO $pdo, int $exploitationId, ?float $humiditeSol): bool
{
    if ($humiditeSol === null || $humiditeSol >= 15) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT adresse_ip, irrigation_active FROM exploitations WHERE id = ?');
    $stmt->execute([$exploitationId]);
    $exploitation = $stmt->fetch();
    if (!$exploitation || (int) $exploitation['irrigation_active'] === 1) {
        return false;
    }

    if (!commanderIrrigation($exploitation['adresse_ip'], true)) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE exploitations SET irrigation_active = 1 WHERE id = ?');
    $stmt->execute([$exploitationId]);
    return true;
}

function desactiverIrrigationSiSolHumide(PDO $pdo, int $exploitationId, ?float $humiditeSol): bool
{
    if ($humiditeSol === null || $humiditeSol <= 70) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT adresse_ip, irrigation_active FROM exploitations WHERE id = ?');
    $stmt->execute([$exploitationId]);
    $exploitation = $stmt->fetch();
    if (!$exploitation || (int) $exploitation['irrigation_active'] === 0) {
        return false;
    }

    if (!commanderIrrigation($exploitation['adresse_ip'], false)) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE exploitations SET irrigation_active = 0 WHERE id = ?');
    $stmt->execute([$exploitationId]);
    return true;
}

function creerAlerteConditionsCritiques(PDO $pdo, int $exploitationId, ?float $temperature, ?float $humiditeAir, ?float $humiditeSol): bool
{
    if ($temperature === null || $humiditeAir === null || $humiditeSol === null
        || $temperature >= 10 || $humiditeAir >= 40 || $humiditeSol >= 15) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT nom, agriculteur_id FROM exploitations WHERE id = ?');
    $stmt->execute([$exploitationId]);
    $exploitation = $stmt->fetch();
    if (!$exploitation) {
        return false;
    }

    $titre = 'Conditions critiques détectées';
    $stmt = $pdo->prepare('SELECT id FROM alertes WHERE exploitation_id = ? AND titre = ? AND date_creation >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1');
    $stmt->execute([$exploitationId, $titre]);
    if ($stmt->fetch()) {
        return false;
    }

    $message = sprintf(
        'Température : %.1f °C, humidité de l’air : %.1f %%, humidité du sol : %.1f %%.',
        $temperature,
        $humiditeAir,
        $humiditeSol
    );
    $stmt = $pdo->prepare('INSERT INTO alertes (exploitation_id, titre, message, niveau, envoyee) VALUES (?, ?, ?, \'critique\', 1)');
    $stmt->execute([$exploitationId, $titre, $message]);
    creerNotification((int) $exploitation['agriculteur_id'], $titre, $exploitation['nom'] . ' : ' . $message);
    return true;
}

function afficherMessage(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        $classe = $f['type'] === 'succes' ? 'alerte-succes' : ($f['type'] === 'erreur' ? 'alerte-erreur' : 'alerte-info');
        echo '<div class="alerte ' . $classe . '">' . nettoyer($f['texte']) . '</div>';
        unset($_SESSION['flash']);
    }
}

function creerNotification(int $utilisateurId, string $titre, string $message): void
{
    $stmt = getPDO()->prepare('INSERT INTO notifications (utilisateur_id, titre, message) VALUES (?, ?, ?)');
    $stmt->execute([$utilisateurId, $titre, $message]);
}

function ensureHistoriqueTablesExists(): void
{
    $pdo = getPDO();
    $pdo->exec("CREATE TABLE IF NOT EXISTS historique_connexions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        utilisateur_id INT NOT NULL,
        date_connexion DATETIME DEFAULT CURRENT_TIMESTAMP,
        adresse_ip VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        KEY idx_connexions_utilisateur_date (utilisateur_id, date_connexion)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS historique_visites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        utilisateur_id INT DEFAULT NULL,
        page VARCHAR(255) NOT NULL,
        url VARCHAR(500) DEFAULT NULL,
        date_visite DATETIME DEFAULT CURRENT_TIMESTAMP,
        adresse_ip VARCHAR(45) DEFAULT NULL,
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
        KEY idx_visites_date (date_visite),
        KEY idx_visites_utilisateur_date (utilisateur_id, date_visite)
    ) ENGINE=InnoDB");
}

function enregistrerConnexion(int $utilisateurId): void
{
    ensureHistoriqueTablesExists();
    $stmt = getPDO()->prepare('INSERT INTO historique_connexions (utilisateur_id, adresse_ip, user_agent) VALUES (?, ?, ?)');
    $stmt->execute([$utilisateurId, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
}

function enregistrerVisite(int $utilisateurId, string $page): void
{
    ensureHistoriqueTablesExists();
    $stmt = getPDO()->prepare('INSERT INTO historique_visites (utilisateur_id, page, url, adresse_ip) VALUES (?, ?, ?, ?)');
    $stmt->execute([$utilisateurId, $page, substr($_SERVER['REQUEST_URI'] ?? '', 0, 500), $_SERVER['REMOTE_ADDR'] ?? null]);
}

function compterNotificationsNonLues(int $utilisateurId): int
{
    $stmt = getPDO()->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND lue = 0');
    $stmt->execute([$utilisateurId]);
    return (int) $stmt->fetchColumn();
}

function formaterDate(string $date): string
{
    $mois = ['01' => 'jan', '02' => 'fév', '03' => 'mar', '04' => 'avr', '05' => 'mai', '06' => 'juin',
             '07' => 'juil', '08' => 'août', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'déc'];
    $ts = strtotime($date);
    return date('d', $ts) . ' ' . $mois[date('m', $ts)] . ' ' . date('Y', $ts);
}

function ensureMesuresTableExists(): void
{
    $pdo = getPDO();
    $tableExiste = $pdo->query("SHOW TABLES LIKE 'mesures'")->fetch();
    if ($tableExiste) {
        $phExiste = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mesures' AND COLUMN_NAME = 'ph'")->fetchColumn();
        if (!$phExiste) {
            $pdo->exec('ALTER TABLE mesures ADD COLUMN ph DECIMAL(10,2) DEFAULT NULL AFTER humidite_sol');
        }
        return;
    }

    $pdo->exec("CREATE TABLE mesures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        capteur_id INT NOT NULL,
        exploitation_id INT NOT NULL,
        temperature DECIMAL(10,2) DEFAULT NULL,
        humidite_air DECIMAL(10,2) DEFAULT NULL,
        humidite_sol DECIMAL(10,2) DEFAULT NULL,
        ph DECIMAL(10,2) DEFAULT NULL,
        luminosite DECIMAL(10,2) DEFAULT NULL,
        niveau_eau DECIMAL(10,2) DEFAULT NULL,
        azote DECIMAL(10,2) DEFAULT NULL,
        phosphore DECIMAL(10,2) DEFAULT NULL,
        potassium DECIMAL(10,2) DEFAULT NULL,
        date_mesure DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mesures_capteur_date (capteur_id, date_mesure),
        KEY idx_mesures_exploitation_date (exploitation_id, date_mesure),
        CONSTRAINT fk_mesures_capteur FOREIGN KEY (capteur_id) REFERENCES capteurs(id) ON DELETE CASCADE,
        CONSTRAINT fk_mesures_exploitation FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("INSERT INTO mesures (capteur_id, exploitation_id, temperature, date_mesure)
        SELECT r.capteur_id, c.exploitation_id, r.valeur, r.date_releve
        FROM releves_capteurs r
        JOIN capteurs c ON c.id = r.capteur_id");
}

function ensureAffectationsAgronomesTableExists(): void
{
    $pdo = getPDO();
    $tableExiste = $pdo->query("SHOW TABLES LIKE 'affectations_agronomes'")->fetch();
    if ($tableExiste) {
        return;
    }

    $pdo->exec("CREATE TABLE affectations_agronomes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agronome_id INT NOT NULL,
        exploitation_id INT NOT NULL UNIQUE,
        date_affectation DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agronome_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
}

function obtenirHistoriqueMesures(int $capteurId, int $limit = 12): array
{
    $pdo = getPDO();
    ensureMesuresTableExists();

    $stmt = $pdo->prepare('SELECT temperature, humidite_air, humidite_sol, ph, luminosite, niveau_eau, azote, phosphore, potassium, date_mesure FROM mesures WHERE capteur_id = ? ORDER BY date_mesure DESC LIMIT ?');
    $stmt->execute([$capteurId, $limit]);
    $mesures = $stmt->fetchAll();

    foreach ($mesures as &$m) {
        $m['valeur'] = 0.0;
        $m['unite'] = null;
        foreach (['temperature' => '°C', 'humidite_air' => '%', 'humidite_sol' => '%', 'ph' => 'pH', 'luminosite' => 'lux', 'niveau_eau' => '%', 'azote' => 'mg/kg', 'phosphore' => 'mg/kg', 'potassium' => 'mg/kg'] as $parametre => $unite) {
            if ($m[$parametre] !== null) {
                $m['valeur'] = (float) $m[$parametre];
                $m['unite'] = $unite;
                break;
            }
        }
    }
    unset($m);

    usort($mesures, static fn($a, $b) => strcmp($a['date_mesure'], $b['date_mesure']));
    return $mesures;
}

function obtenirHistoriquesParametres(int $capteurId, int $limit = 20): array
{
    $pdo = getPDO();
    ensureMesuresTableExists();
    $parametres = [
        'temperature' => ['libelle' => 'Température', 'unite' => '°C', 'couleur' => '#E67E22'],
        'humidite_air' => ['libelle' => 'Humidité de l’air', 'unite' => '%', 'couleur' => '#2E86C1'],
        'humidite_sol' => ['libelle' => 'Humidité du sol', 'unite' => '%', 'couleur' => '#8E6E53'],
        'ph' => ['libelle' => 'pH du sol', 'unite' => 'pH', 'couleur' => '#9B59B6'],
        'luminosite' => ['libelle' => 'Luminosité', 'unite' => 'lux', 'couleur' => '#F1C40F'],
        'niveau_eau' => ['libelle' => 'Niveau d’eau', 'unite' => '%', 'couleur' => '#16A085'],
        'azote' => ['libelle' => 'Azote', 'unite' => 'mg/kg', 'couleur' => '#27AE60'],
        'phosphore' => ['libelle' => 'Phosphore', 'unite' => 'mg/kg', 'couleur' => '#8E44AD'],
        'potassium' => ['libelle' => 'Potassium', 'unite' => 'mg/kg', 'couleur' => '#C0392B'],
    ];
    $stmt = $pdo->prepare('SELECT temperature, humidite_air, humidite_sol, ph, luminosite, niveau_eau, azote, phosphore, potassium, date_mesure FROM mesures WHERE capteur_id = ? ORDER BY date_mesure DESC LIMIT ?');
    $stmt->execute([$capteurId, $limit]);
    $lignes = array_reverse($stmt->fetchAll());
    $series = [];
    foreach ($parametres as $parametre => $configuration) {
        $points = [];
        foreach ($lignes as $ligne) {
            if ($ligne[$parametre] !== null) {
                $points[] = ['valeur' => (float) $ligne[$parametre], 'unite' => $configuration['unite'], 'date_mesure' => $ligne['date_mesure']];
            }
        }
        $series[$parametre] = ['libelle' => $configuration['libelle'], 'unite' => $configuration['unite'], 'couleur' => $configuration['couleur'], 'mesures' => $points];
    }
    return $series;
}

function obtenirDerniereMesureExploitation(int $exploitationId): ?array
{
    $stmt = getPDO()->prepare('SELECT m.temperature, m.humidite_air, m.humidite_sol, m.ph, m.luminosite, m.niveau_eau, m.azote, m.phosphore, m.potassium, m.date_mesure, c.code_capteur, c.adresse_ip
        FROM mesures m
        JOIN capteurs c ON c.id = m.capteur_id AND c.exploitation_id = m.exploitation_id
        JOIN exploitations e ON e.id = m.exploitation_id
                WHERE m.exploitation_id = ?
                    AND ((c.adresse_ip IS NOT NULL AND c.adresse_ip <> \'\' AND c.adresse_ip = e.adresse_ip)
                        OR ((c.adresse_ip IS NULL OR c.adresse_ip = \'\') AND (e.adresse_ip IS NULL OR e.adresse_ip = \'\')))
        ORDER BY m.date_mesure DESC, m.id DESC LIMIT 1');
    $stmt->execute([$exploitationId]);
    $mesure = $stmt->fetch();
    return $mesure ?: null;
}

function genererSvgGraphique(array $mesures, string $couleur = '#2E86C1'): string
{
    if (!$mesures) {
        return '<p style="color:var(--texte-attenue); margin:8px 0 0;">Aucune mesure enregistrée pour ce capteur.</p>';
    }

    $largeur = 460;
    $hauteur = 320;
    $marge = 30;
    $coords = [];
    $timestamps = [];
    $valeurs = [];

    foreach ($mesures as $mesure) {
        $valeurs[] = (float) ($mesure['valeur'] ?? 0);
        $ts = isset($mesure['date_mesure']) ? strtotime((string) $mesure['date_mesure']) : null;
        if ($ts === false || $ts === null) {
            $ts = isset($mesure['date_releve']) ? strtotime((string) $mesure['date_releve']) : null;
        }
        $timestamps[] = $ts;
    }

    $minValeur = min(0, min($valeurs));
    $maxValeur = max(0, max($valeurs));
    $etendueValeur = $maxValeur - $minValeur;
    $etendueValeur = $etendueValeur > 0 ? $etendueValeur : 1;

    $tsValides = array_filter($timestamps, static fn($ts) => $ts !== null && $ts !== false);
    $minTs = $tsValides ? min($tsValides) : null;
    $maxTs = $tsValides ? max($tsValides) : null;
    $etendueTs = ($minTs !== null && $maxTs !== null && $maxTs > $minTs) ? ($maxTs - $minTs) : null;

    foreach ($mesures as $index => $mesure) {
        $valeur = (float) ($mesure['valeur'] ?? 0);
        $ts = isset($mesure['date_mesure']) ? strtotime((string) $mesure['date_mesure']) : null;
        if ($ts === false || $ts === null) {
            $ts = isset($mesure['date_releve']) ? strtotime((string) $mesure['date_releve']) : null;
        }

        $y = $hauteur - $marge - (($valeur - $minValeur) / $etendueValeur) * ($hauteur - ($marge * 2));

        if ($minTs !== null && $maxTs !== null && $etendueTs !== null && $ts !== null) {
            $temps = max(0, $ts - $minTs);
            $x = $marge + ($temps / $etendueTs) * ($largeur - ($marge * 2));
        } else {
            $x = $marge + ($index * ($largeur - ($marge * 2)) / max(1, count($mesures) - 1));
        }

        $coords[] = $x . ',' . $y;
    }

    $linePath = implode(' ', $coords);
    $dernierPoint = $coords[count($coords) - 1];
    [$dernierX, $dernierY] = array_map('floatval', explode(',', $dernierPoint));

    $labels = '';
    $graduations = '<line x1="' . $marge . '" y1="' . $marge . '" x2="' . $marge . '" y2="' . ($hauteur - $marge) . '" stroke="var(--texte-attenue)" stroke-width="1.5" />
        <line x1="' . $marge . '" y1="' . ($hauteur - $marge) . '" x2="' . ($largeur - $marge) . '" y2="' . ($hauteur - $marge) . '" stroke="var(--texte-attenue)" stroke-width="1.5" />';
    foreach ([0, 0.125, 0.25, 0.375, 0.5, 0.625, 0.75, 0.875, 1] as $position) {
        $y = $hauteur - $marge - ($position * ($hauteur - ($marge * 2)));
        $valeur = $minValeur + ($position * $etendueValeur);
        $graduations .= '<line x1="' . $marge . '" y1="' . $y . '" x2="' . ($largeur - $marge) . '" y2="' . $y . '" stroke="var(--bordure)" stroke-width="1" stroke-dasharray="3 3" />
            <line x1="' . ($marge - 4) . '" y1="' . $y . '" x2="' . $marge . '" y2="' . $y . '" stroke="var(--texte-attenue)" stroke-width="1" />
            <text x="' . ($marge - 6) . '" y="' . ($y + 3) . '" text-anchor="end" fill="var(--texte-attenue)" font-size="9">' . round($valeur, 1) . '</text>';
    }
    if ($minTs !== null && $maxTs !== null && $etendueTs !== null) {
        $positionsTemps = [0, 0.0625, 0.125, 0.1875, 0.25, 0.3125, 0.375, 0.4375, 0.5, 0.5625, 0.625, 0.6875, 0.75, 0.8125, 0.875, 0.9375, 1];
        foreach ($positionsTemps as $position) {
            $timestamp = $minTs + ($etendueTs * $position);
            $heure = date('H:i', (int) $timestamp);
            $x = $marge + ($position * ($largeur - ($marge * 2)));
            $ancrage = $position === 0 ? 'start' : ($position === 1 ? 'end' : 'middle');
            $labels .= '<line x1="' . $x . '" y1="' . $marge . '" x2="' . $x . '" y2="' . ($hauteur - $marge) . '" stroke="var(--bordure)" stroke-width="1" stroke-dasharray="3 3" />
                <line x1="' . $x . '" y1="' . ($hauteur - $marge) . '" x2="' . $x . '" y2="' . ($hauteur - $marge + 4) . '" stroke="var(--texte-attenue)" stroke-width="1" />
                <text x="' . $x . '" y="' . ($hauteur - 10) . '" text-anchor="' . $ancrage . '" fill="var(--texte-attenue)" font-size="8" transform="rotate(-35 ' . $x . ' ' . ($hauteur - 10) . ')">' . $heure . '</text>';
        }
    }

    return '<svg viewBox="0 0 ' . $largeur . ' ' . $hauteur . '" width="100%" height="320" role="img" aria-label="Graphique des mesures du capteur en fonction de l’heure" style="display:block; border-radius:10px; background:linear-gradient(180deg,#f7fbff,#edf5fb); border:1px solid var(--bordure);">
        ' . $graduations . '
        <polyline fill="none" stroke="' . $couleur . '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="' . $linePath . '" />
        <circle cx="' . $dernierX . '" cy="' . $dernierY . '" r="4" fill="' . $couleur . '" />
        ' . $labels . '
    </svg>';
}

/**
 * Récupère la météo pour une ville ou un point géographique via OpenWeatherMap.
 * Si des coordonnées sont fournies, elles sont prioritaires pour refléter la vraie
 * localisation de l’exploitation.
 */
function obtenirMeteoParLocalisation(?float $latitude = null, ?float $longitude = null, ?string $ville = null): array
{
    $ville = trim((string) ($ville ?? ''));
    $villeDefaut = $ville !== '' ? $ville : 'Yaoundé';

    if (defined('METEO_API_KEY') && METEO_API_KEY !== 'VOTRE_CLE_API_OPENWEATHERMAP') {
        $url = 'https://api.openweathermap.org/data/2.5/weather';
        $parametres = [
            'appid=' . METEO_API_KEY,
            'units=metric',
            'lang=fr',
        ];

        if ($latitude !== null && $longitude !== null && is_numeric($latitude) && is_numeric($longitude)) {
            $parametres[] = 'lat=' . urlencode((string) $latitude);
            $parametres[] = 'lon=' . urlencode((string) $longitude);
        } else {
            $parametres[] = 'q=' . urlencode($villeDefaut);
        }

        $url .= '?' . implode('&', $parametres);
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ];
        $certificatCa = __DIR__ . '/../config/cacert.pem';
        if (is_file($certificatCa)) {
            $options[CURLOPT_CAINFO] = $certificatCa;
        }
        curl_setopt_array($ch, $options);
        $reponse = curl_exec($ch);
        curl_close($ch);
        if ($reponse !== false) {
            $data = json_decode($reponse, true);
            if (isset($data['main'])) {
                $villeRetour = $ville !== '' ? $ville : ($data['name'] ?? $villeDefaut);
                $meteo = [
                    'ville'       => $villeRetour,
                    'temperature' => round($data['main']['temp'], 1),
                    'humidite'    => $data['main']['humidity'],
                    'description' => ucfirst($data['weather'][0]['description'] ?? ''),
                    'vent'        => round(($data['wind']['speed'] ?? 0) * 3.6, 1),
                ];
                $stmt = getPDO()->prepare('INSERT INTO meteo_cache (ville, temperature, humidite, description, vent) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$meteo['ville'], $meteo['temperature'], $meteo['humidite'], $meteo['description'], $meteo['vent']]);
                return $meteo;
            }
        }
    }

    // Repli : dernière valeur en cache, sinon estimation
    $cacheVille = $ville !== '' ? $ville : $villeDefaut;
    $stmt = getPDO()->prepare('SELECT * FROM meteo_cache WHERE ville = ? ORDER BY date_maj DESC LIMIT 1');
    $stmt->execute([$cacheVille]);
    if ($cache = $stmt->fetch()) {
        return [
            'ville' => $cacheVille, 'temperature' => $cache['temperature'], 'humidite' => $cache['humidite'],
            'description' => $cache['description'], 'vent' => $cache['vent'],
        ];
    }
    return ['ville' => $cacheVille, 'temperature' => 26.0, 'humidite' => 60, 'description' => 'Ensoleillé (estimation)', 'vent' => 8.0];
}

function obtenirMeteo(string $ville): array
{
    return obtenirMeteoParLocalisation(null, null, $ville);
}

function predireRendementAvecPython(PDO $pdo, int $exploitationId): array
{
    $stmt = $pdo->prepare('SELECT e.nom, e.culture, e.type_sol, e.latitude, e.longitude, e.ville,
        m.temperature, m.humidite_air, m.humidite_sol, m.ph, m.luminosite, m.azote, m.phosphore, m.potassium
        FROM exploitations e
        LEFT JOIN mesures m ON m.exploitation_id = e.id
        AND m.id = (SELECT m2.id FROM mesures m2 WHERE m2.exploitation_id = e.id ORDER BY m2.date_mesure DESC, m2.id DESC LIMIT 1)
        WHERE e.id = ?');
    $stmt->execute([$exploitationId]);
    $exploitation = $stmt->fetch();
    if (!$exploitation) {
        return ['succes' => false, 'message' => 'Exploitation introuvable.'];
    }

    $meteo = obtenirMeteoParLocalisation(
        $exploitation['latitude'] !== null ? (float) $exploitation['latitude'] : null,
        $exploitation['longitude'] !== null ? (float) $exploitation['longitude'] : null,
        $exploitation['ville']
    );
    $parametres = [
        'plante' => $exploitation['culture'] ?: 'Mais',
        'Azote' => (float) ($exploitation['azote'] ?? 0.15),
        'Phosphore' => (float) ($exploitation['phosphore'] ?? 15),
        'Potassium' => (float) ($exploitation['potassium'] ?? 85),
        'temperature' => (float) ($exploitation['temperature'] ?? $meteo['temperature']),
        'ph' => (float) ($exploitation['ph'] ?? 5.8),
        'humidite_air' => (float) ($exploitation['humidite_air'] ?? $meteo['humidite']),
        'humidite_sol' => (float) ($exploitation['humidite_sol'] ?? 50),
        'luminosite' => (float) ($exploitation['luminosite'] ?? 45000),
        'type_sol' => ucfirst($exploitation['type_sol'] ?: 'sableux'),
    ];
    $python = __DIR__ . '/../ia_prediction/venv/Scripts/python.exe';
    if (!is_file($python)) {
        $python = 'python';
    }
    $script = __DIR__ . '/../ia_prediction/ia_prediction.py';
    $donneesEncodees = base64_encode((string) json_encode($parametres, JSON_UNESCAPED_UNICODE));
    $commande = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' --base64 ' . escapeshellarg($donneesEncodees) . ' 2>&1';
    $sortie = shell_exec($commande);
    $sortie = trim((string) $sortie);
    $resultat = null;
    if (preg_match('/\{\s*"rendement"\s*:\s*[-+0-9.eE]+\s*\}/', $sortie, $correspondance)) {
        $resultat = json_decode($correspondance[0], true);
    }
    if (!isset($resultat['rendement'])) {
        $erreur = preg_replace('/\s+/', ' ', $sortie);
        return ['succes' => false, 'message' => $erreur !== '' ? 'Erreur du modèle Python : ' . substr($erreur, -400) : 'Le modèle Python n’a produit aucune sortie.'];
    }
    $rendement = round((float) $resultat['rendement'], 2);
    return ['succes' => true, 'rendement' => $rendement, 'parametres' => $parametres, 'nom' => $exploitation['nom']];
}

function reponseChatGemini(string $message, ?array $contexte = null): string
{
    $apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : (getenv('GROQ_API_KEY') ?: '');
    if ($apiKey === '') {
        return 'La clé GroqCloud n’est pas configurée. Ajoutez la variable GROQ_API_KEY dans l’environnement pour activer le chatbot.';
    }

    $prompt = "Tu es un assistant agricole et agronomique pour une application ST-AGRO. Réponds en français, de manière claire et utile pour un agriculteur. " .
        "Donne des conseils pratiques, réalistes et adaptés au contexte agricole. " .
        "Contexte utilisateur : " . ($contexte['profil'] ?? 'Agriculteur') . ".\n" .
        ($contexte['donnees'] ?? '') . "\n\nQuestion : " . $message;

    $payload = [
        'model' => 'openai/gpt-oss-20b',
        'messages' => [
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.7,
        'max_tokens' => 2048,
    ];

    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $ch = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
    ];
    $certificatCa = __DIR__ . '/../config/cacert.pem';
    if (is_file($certificatCa)) {
        $options[CURLOPT_CAINFO] = $certificatCa;
    }
    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false || $httpCode < 200 || $httpCode >= 300) {
        if ($curlError !== '') {
            return 'Le chatbot GroqCloud est indisponible : ' . $curlError;
        }
        if ($httpCode === 429) {
            return 'Le chatbot GroqCloud a atteint sa limite de requêtes. Vérifiez le quota de votre compte GroqCloud, puis réessayez.';
        }
        $erreurApi = json_decode((string) $body, true);
        $messageErreur = $erreurApi['message'] ?? ($erreurApi['error']['message'] ?? 'réponse HTTP ' . $httpCode);
        return 'Le chatbot GroqCloud est indisponible : ' . $messageErreur;
    }

    $data = json_decode($body, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        return 'GroqCloud ne renvoie pas de réponse exploitable pour le moment.';
    }

    return trim($data['choices'][0]['message']['content']);
}

function analyserImagePlantNet(string $cheminImage): array
{
    $apiKey = getenv('PLANTNET_API_KEY') ?: (defined('PLANTNET_API_KEY') ? PLANTNET_API_KEY : '');
    if ($apiKey === '' || $apiKey === 'VOTRE_CLE_API_PLANTNET') {
        return ['succes' => false, 'message' => 'Clé API Pl@ntNet non configurée.'];
    }
    if (!is_file($cheminImage) || !function_exists('curl_init')) {
        return ['succes' => false, 'message' => 'Le fichier image ou le module cURL est indisponible.'];
    }

    $url = 'https://my-api.plantnet.org/v2/identify/all?api-key=' . urlencode($apiKey);
    $ch = curl_init($url);
    $options = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_POSTFIELDS => [
            'images' => new CURLFile($cheminImage),
            'organs' => 'leaf',
        ],
    ];
    $certificatCa = __DIR__ . '/../config/cacert.pem';
    if (is_file($certificatCa)) {
        $options[CURLOPT_CAINFO] = $certificatCa;
    }
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode < 200 || $httpCode >= 300) {
        return ['succes' => false, 'message' => 'Pl@ntNet n’a pas pu analyser cette image.'];
    }

    $data = json_decode($body, true);
    $meilleurResultat = $data['results'][0] ?? null;
    if (!$meilleurResultat || empty($meilleurResultat['species']['scientificNameWithoutAuthor'])) {
        return ['succes' => false, 'message' => 'Aucune plante identifiable n’a été trouvée.'];
    }

    $espece = $meilleurResultat['species'];
    $nomScientifique = $espece['scientificNameWithoutAuthor'];
    $nomsCommuns = $espece['commonNames'] ?? [];
    $nomCommun = $nomsCommuns[0] ?? 'Nom commun non disponible';
    $score = round(((float) ($meilleurResultat['score'] ?? 0)) * 100, 1);
    $famille = $espece['family']['scientificNameWithoutAuthor'] ?? 'Famille non disponible';

    return [
        'succes' => true,
        'diagnostic' => "Plante identifiée : $nomCommun ($nomScientifique). Famille : $famille. Confiance : $score %.",
        'niveau_risque' => 'faible',
        'recommandation' => 'Vérifiez ce résultat avec un agronome, car l’identification dépend de la qualité et du cadrage de la photo.',
    ];
}

function analyserImagePhytosanitaire(string $cheminImage): array
{
    $plantIdResultat = analyserImagePlantId($cheminImage);
    if ($plantIdResultat['succes']) {
        return $plantIdResultat;
    }

    return $plantIdResultat;
/*
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');
    if ($apiKey === '' || !is_file($cheminImage) || !function_exists('curl_init')) {
        return ['succes' => false, 'message' => 'Gemini non configuré.'];
    }

    $mime = mime_content_type($cheminImage) ?: 'image/jpeg';
    $image = base64_encode((string) file_get_contents($cheminImage));
    $prompt = 'Analyse cette photo de plante pour un diagnostic phytosanitaire. Réponds uniquement avec un JSON valide contenant exactement les clés: statut, nom, niveau_risque, recommandation. '
        . 'statut doit être une seule valeur parmi: saine, maladie, insecte, indetermine. '
        . 'Si une maladie ou un insecte est visible, indique son nom dans nom. Si ce n’est pas identifiable, utilise indetermine et explique les limites dans recommandation. '
        . 'niveau_risque doit être faible, modere ou eleve. Réponds en français et ne prétends pas être certain si la photo ne permet pas un diagnostic fiable.';
    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mime, 'data' => $image]],
            ],
        ]],
        'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 600, 'responseMimeType' => 'application/json'],
    ];
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . urlencode($apiKey);
    $ch = curl_init($url);
    $options = [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR)];
    $certificatCa = __DIR__ . '/../config/cacert.pem';
    if (is_file($certificatCa)) $options[CURLOPT_CAINFO] = $certificatCa;
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $httpCode < 200 || $httpCode >= 300) {
        return analyserImagePlantNet($cheminImage);
    }
    $data = json_decode($body, true);
    $texte = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $diagnostic = json_decode(trim($texte), true);
    if (!is_array($diagnostic) || !in_array($diagnostic['statut'] ?? '', ['saine', 'maladie', 'insecte', 'indetermine'], true)) {
        return analyserImagePlantNet($cheminImage);
    }
    $libelle = ['saine' => 'Plante saine', 'maladie' => 'Maladie détectée', 'insecte' => 'Attaque d’insecte détectée', 'indetermine' => 'Diagnostic indéterminé'][$diagnostic['statut']];
    return [
        'succes' => true,
        'diagnostic' => $libelle . ' : ' . ($diagnostic['nom'] ?? 'non identifié') . '.',
        'niveau_risque' => in_array($diagnostic['niveau_risque'] ?? '', ['faible', 'modere', 'eleve'], true) ? $diagnostic['niveau_risque'] : 'faible',
        'recommandation' => $diagnostic['recommandation'] ?? 'Demandez une vérification par un agronome.',
    ];
*/
}

function analyserImagePlantId(string $cheminImage): array
{
    $apiKey = defined('PLANT_ID_API_KEY') ? PLANT_ID_API_KEY : (getenv('PLANT_ID_API_KEY') ?: '');
    if ($apiKey === '' || !is_file($cheminImage) || !function_exists('curl_init')) {
        return ['succes' => false, 'message' => 'Plant.id non configuré.'];
    }

    $image = base64_encode((string) file_get_contents($cheminImage));
    $payload = [
        'images' => [$image],
        'similar_images' => true,
    ];
    $url = 'https://api.plant.id/v3/health_assessment?details=common_names,description,treatment&language=fr';
    $ch = curl_init($url);
    $options = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Api-Key: ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
    ];
    $certificatCa = __DIR__ . '/../config/cacert.pem';
    if (is_file($certificatCa)) {
        $options[CURLOPT_CAINFO] = $certificatCa;
    }
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode < 200 || $httpCode >= 300) {
        $retour = json_decode((string) $body, true);
        return ['succes' => false, 'message' => 'Plant.id a refusé l’analyse (HTTP ' . $httpCode . '). ' . ($retour['message'] ?? 'Vérifiez la clé PLANT_ID_API_KEY et le quota.')];
    }

    $data = json_decode($body, true);
    $resultat = $data['result'] ?? [];
    $suggestionsMaladies = $resultat['disease']['suggestions'] ?? [];
    $meilleureMaladie = $suggestionsMaladies[0] ?? null;
    $planteSaine = $resultat['is_healthy']['binary'] ?? null;
    if ($planteSaine === true) {
        return [
            'succes' => true,
            'diagnostic' => 'Plante saine : aucune maladie visible détectée.',
            'niveau_risque' => 'faible',
            'recommandation' => 'Continuez la surveillance régulière de la plante et vérifiez l’évolution des feuilles.',
        ];
    }
    if (!$meilleureMaladie || empty($meilleureMaladie['name'])) {
        return ['succes' => true, 'diagnostic' => 'Aucune maladie identifiable sur cette image.', 'niveau_risque' => 'faible', 'recommandation' => 'La plante ne présente pas de maladie clairement détectable. Continuez la surveillance et envoyez une photo plus rapprochée en cas de doute.'];
    }

    $nom = $meilleureMaladie['name'];
    $probabilite = round(((float) ($meilleureMaladie['probability'] ?? 0)) * 100, 1);
    $details = $meilleureMaladie['details'] ?? [];
    $description = $details['description'] ?? '';
    $traitement = $details['treatment'] ?? [];
    $recommandation = '';
    if (is_array($traitement)) {
        foreach ($traitement as $groupe) {
            if (is_array($groupe)) {
                $recommandation .= implode(' ', array_filter(array_map('strval', $groupe))) . ' ';
            }
        }
    }
    $recommandation = trim($recommandation);
    return [
        'succes' => true,
        'diagnostic' => 'Maladie détectée : ' . $nom . ' (confiance : ' . $probabilite . '%).' . ($description !== '' ? ' ' . $description : ''),
        'niveau_risque' => $probabilite >= 70 ? 'eleve' : ($probabilite >= 40 ? 'modere' : 'faible'),
        'recommandation' => $recommandation !== '' ? $recommandation : 'Faites vérifier ce résultat par un agronome avant tout traitement.',
    ];
}
