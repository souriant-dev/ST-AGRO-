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
        return;
    }

    $pdo->exec("CREATE TABLE mesures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        capteur_id INT NOT NULL,
        exploitation_id INT NOT NULL,
        valeur DECIMAL(10,2) NOT NULL,
        unite VARCHAR(20) DEFAULT NULL,
        date_mesure DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mesures_capteur_date (capteur_id, date_mesure),
        KEY idx_mesures_exploitation_date (exploitation_id, date_mesure),
        CONSTRAINT fk_mesures_capteur FOREIGN KEY (capteur_id) REFERENCES capteurs(id) ON DELETE CASCADE,
        CONSTRAINT fk_mesures_exploitation FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("INSERT INTO mesures (capteur_id, exploitation_id, valeur, unite, date_mesure)
        SELECT r.capteur_id, c.exploitation_id, r.valeur, r.unite, r.date_releve
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

    $stmt = $pdo->prepare('SELECT valeur, unite, date_mesure FROM mesures WHERE capteur_id = ? ORDER BY date_mesure DESC LIMIT ?');
    $stmt->execute([$capteurId, $limit]);
    $mesures = $stmt->fetchAll();

    foreach ($mesures as &$m) {
        $m['valeur'] = (float) $m['valeur'];
    }
    unset($m);

    usort($mesures, static fn($a, $b) => strcmp($a['date_mesure'], $b['date_mesure']));
    return $mesures;
}

function genererSvgGraphique(array $mesures, string $couleur = '#2E86C1'): string
{
    if (!$mesures) {
        return '<p style="color:var(--texte-attenue); margin:8px 0 0;">Aucune mesure enregistrée pour ce capteur.</p>';
    }

    $largeur = 420;
    $hauteur = 160;
    $marge = 18;
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

    $minValeur = min($valeurs);
    $maxValeur = max($valeurs);
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
    if ($minTs !== null && $maxTs !== null && $etendueTs !== null) {
        $milieuTs = $minTs + ($etendueTs / 2);
        $finTs = $maxTs;
        $labels = '
            <text x="' . $marge . '" y="' . ($hauteur - 2) . '" fill="var(--texte-attenue)" font-size="10">' . date('H:i', $minTs) . '</text>
            <text x="' . ($largeur / 2 - 12) . '" y="' . ($hauteur - 2) . '" fill="var(--texte-attenue)" font-size="10">' . date('H:i', $milieuTs) . '</text>
            <text x="' . ($largeur - 36) . '" y="' . ($hauteur - 2) . '" fill="var(--texte-attenue)" font-size="10">' . date('H:i', $finTs) . '</text>';
    }

    return '<svg viewBox="0 0 ' . $largeur . ' ' . $hauteur . '" width="100%" height="160" role="img" aria-label="Graphique des mesures du capteur en fonction de l’heure" style="display:block; border-radius:10px; background:linear-gradient(180deg,#f7fbff,#edf5fb); border:1px solid var(--bordure);">
        <polyline fill="none" stroke="' . $couleur . '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="' . $linePath . '" />
        <circle cx="' . $dernierX . '" cy="' . $dernierY . '" r="4" fill="' . $couleur . '" />
        ' . $labels . '
    </svg>';
}

/**
 * Récupère la météo pour une ville via OpenWeatherMap, avec repli sur un cache local
 * (use case "consulter météo"). Si aucune clé API n'est configurée, renvoie une
 * estimation locale simulée pour ne jamais bloquer l'interface.
 */
function obtenirMeteo(string $ville): array
{
    if (defined('METEO_API_KEY') && METEO_API_KEY !== 'VOTRE_CLE_API_OPENWEATHERMAP') {
        $url = 'https://api.openweathermap.org/data/2.5/weather?q=' . urlencode($ville)
             . '&appid=' . METEO_API_KEY . '&units=metric&lang=fr';
        $contexte = stream_context_create(['http' => ['timeout' => 4]]);
        $reponse = @file_get_contents($url, false, $contexte);
        if ($reponse !== false) {
            $data = json_decode($reponse, true);
            if (isset($data['main'])) {
                $meteo = [
                    'ville'       => $ville,
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
    $stmt = getPDO()->prepare('SELECT * FROM meteo_cache WHERE ville = ? ORDER BY date_maj DESC LIMIT 1');
    $stmt->execute([$ville]);
    if ($cache = $stmt->fetch()) {
        return [
            'ville' => $ville, 'temperature' => $cache['temperature'], 'humidite' => $cache['humidite'],
            'description' => $cache['description'], 'vent' => $cache['vent'],
        ];
    }
    return ['ville' => $ville, 'temperature' => 26.0, 'humidite' => 60, 'description' => 'Ensoleillé (estimation)', 'vent' => 8.0];
}

function reponseChatGemini(string $message, ?array $contexte = null): string
{
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    if ($apiKey === '') {
        return 'La clé Gemini n’est pas configurée. Ajoutez la variable GEMINI_API_KEY ou la constante GEMINI_API_KEY dans l’environnement pour activer le chatbot.';
    }

    $prompt = "Tu es un assistant agricole et agronomique pour une application ST-AGRO. Réponds en français, de manière claire et utile pour un agriculteur. " .
        "Donne des conseils pratiques, réalistes et adaptés au contexte agricole. " .
        "Contexte utilisateur : " . ($contexte['profil'] ?? 'Agriculteur') . ".\n\nQuestion : " . $message;

    $payload = [
        'contents' => [[
            'parts' => [[
                'text' => $prompt,
            ]],
        ]],
        'generationConfig' => [
            'temperature' => 0.7,
            'topP' => 0.95,
            'maxOutputTokens' => 2048,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode < 200 || $httpCode >= 300) {
        return 'Le chatbot Gemini est momentanément indisponible. Vérifiez votre clé API GEMINI_API_KEY et réessayez.';
    }

    $data = json_decode($body, true);
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return 'Le chatbot ne renvoie pas de réponse exploitable pour le moment.';
    }

    return trim($data['candidates'][0]['content']['parts'][0]['text']);
}
