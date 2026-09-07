<?php
// Autoriser l'accès depuis n'importe quel appareil du réseau local (votre Arduino)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../includes/functions.php';

// 1. CONFIGURATION DE LA BASE DE DONNÉES
$host     = "localhost";
$username = "root";          // Identifiant par défaut sur XAMPP / Wamp
$password = "password";              // Mot de passe par défaut (vide sur XAMPP, souvent "root" sur MAMP)
$dbname   = "stagro"; // Nom de votre base de données

// Connexion à MySQL via PDO
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Configurer PDO pour lever des exceptions en cas d'erreur SQL
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Échec de la connexion à la base de données: " . $e->getMessage()
    ]);
    exit();
}

// 2. RÉCUPÉRATION DU PAQUET JSON ENVOYÉ PAR L'ARDUINO
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);
if (!is_array($data)) {
    $data = $_POST;
}

// Vérifier que la requête est bien un POST et que le JSON n'est pas vide
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($data)) {
    
    $capteurId = filter_var($data['capteur_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $codeCapteur = trim((string) ($data['code_capteur'] ?? ''));
    $adresseIpFournie = trim((string) ($data['adresse_ip'] ?? ''));
    $adresseIp = $adresseIpFournie !== '' ? $adresseIpFournie : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $exploitationId = filter_var($data['exploitation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    $mesures = [
        'temperature' => isset($data['temperature']) ? (float) $data['temperature'] : null,
        'humidite_air' => isset($data['humidite_air']) ? (float) $data['humidite_air'] : null,
        'humidite_sol' => isset($data['humidite_sol']) ? (float) $data['humidite_sol'] : null,
        'ph' => isset($data['ph']) ? (float) $data['ph'] : null,
        'luminosite' => isset($data['luminosite']) ? (float) $data['luminosite'] : null,
        'niveau_eau' => isset($data['niveau_eau']) ? (float) $data['niveau_eau'] : null,
        'azote' => isset($data['azote']) ? (float) $data['azote'] : null,
        'phosphore' => isset($data['phosphore']) ? (float) $data['phosphore'] : null,
        'potassium' => isset($data['potassium']) ? (float) $data['potassium'] : null,
    ];

    if (!array_filter($mesures, static fn($valeur) => $valeur !== null)) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Aucune mesure valide n'a été fournie."]);
        exit();
    }

    try {
        // Le capteur peut être identifié par son id, son code ou l'IP enregistrée sur l'exploitation.
        if ($capteurId) {
            $stmt = $conn->prepare('SELECT id, exploitation_id FROM capteurs WHERE id = ? AND statut = \'actif\'');
            $stmt->execute([$capteurId]);
        } elseif ($codeCapteur !== '') {
            $stmt = $conn->prepare('SELECT id, exploitation_id FROM capteurs WHERE code_capteur = ? AND statut = \'actif\'');
            $stmt->execute([$codeCapteur]);
        } elseif ($exploitationId) {
            $stmt = $conn->prepare('SELECT id, exploitation_id FROM capteurs WHERE exploitation_id = ? AND statut = \'actif\' ORDER BY id LIMIT 1');
            $stmt->execute([$exploitationId]);
        } elseif (filter_var($adresseIp, FILTER_VALIDATE_IP)) {
            $stmt = $conn->prepare('SELECT c.id, c.exploitation_id FROM capteurs c JOIN exploitations e ON e.id = c.exploitation_id WHERE e.adresse_ip = ? AND c.statut = \'actif\' ORDER BY c.id LIMIT 1');
            $stmt->execute([$adresseIp]);
        } else {
            $stmt = $conn->query('SELECT id, exploitation_id FROM capteurs WHERE statut = \'actif\' ORDER BY id');
            $capteursActifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $capteur = count($capteursActifs) === 1 ? $capteursActifs[0] : false;
            $stmt = null;
        }

        $capteur = isset($capteur) ? $capteur : ($stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false);

        if (!$capteur && !$capteurId && $codeCapteur === '' && filter_var($adresseIpFournie, FILTER_VALIDATE_IP)) {
            $stmt = $conn->prepare('SELECT id FROM exploitations WHERE adresse_ip = ? LIMIT 1');
            $stmt->execute([$adresseIpFournie]);
            $exploitation = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($exploitation) {
                $codeAutomatique = 'IP-' . str_replace(['.', ':'], '-', $adresseIpFournie);
                $stmt = $conn->prepare('SELECT id, exploitation_id FROM capteurs WHERE code_capteur = ? LIMIT 1');
                $stmt->execute([$codeAutomatique]);
                $capteur = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$capteur) {
                    $stmt = $conn->prepare('INSERT INTO capteurs (exploitation_id, code_capteur, adresse_ip, date_installation) VALUES (?, ?, ?, CURDATE())');
                    $stmt->execute([$exploitation['id'], $codeAutomatique, $adresseIpFournie]);
                    $capteur = ['id' => $conn->lastInsertId(), 'exploitation_id' => $exploitation['id']];
                }
            }
        }

        if (!$capteur) {
            http_response_code(422);
            echo json_encode(["status" => "error", "message" => "Capteur introuvable ou non identifiable. Envoyez capteur_id, code_capteur, exploitation_id ou configurez l'adresse IP de l'exploitation."]);
            exit();
        }

        $sql = "INSERT INTO mesures (capteur_id, exploitation_id, temperature, humidite_air, humidite_sol, ph, luminosite, niveau_eau, azote, phosphore, potassium, date_mesure)
            VALUES (:capteur_id, :exploitation_id, :temperature, :humidite_air, :humidite_sol, :ph, :luminosite, :niveau_eau, :azote, :phosphore, :potassium, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':capteur_id' => $capteur['id'],
            ':exploitation_id' => $capteur['exploitation_id'],
            ':temperature' => $mesures['temperature'],
            ':humidite_air' => $mesures['humidite_air'],
            ':humidite_sol' => $mesures['humidite_sol'],
            ':ph' => $mesures['ph'],
            ':luminosite' => $mesures['luminosite'],
            ':niveau_eau' => $mesures['niveau_eau'],
            ':azote' => $mesures['azote'],
            ':phosphore' => $mesures['phosphore'],
            ':potassium' => $mesures['potassium'],
        ]);

        $irrigationAuto = activerIrrigationSiSolSec($conn, (int) $capteur['exploitation_id'], $mesures['humidite_sol']);
        $irrigationAutoDesactivee = desactiverIrrigationSiSolHumide($conn, (int) $capteur['exploitation_id'], $mesures['humidite_sol']);
        $alerteAuto = creerAlerteConditionsCritiques(
            $conn,
            (int) $capteur['exploitation_id'],
            $mesures['temperature'],
            $mesures['humidite_air'],
            $mesures['humidite_sol']
        );
        
        // Réponse HTTP 201 (Créé) renvoyée à l'Arduino en cas de succès
        http_response_code(201);
        echo json_encode([
            "status" => "success", 
            "message" => "Les mesures ont été enregistrées avec succès.",
            "irrigation_auto_activee" => $irrigationAuto,
            "irrigation_auto_desactivee" => $irrigationAutoDesactivee,
            "alerte_auto_creee" => $alerteAuto
        ]);

    } catch(PDOException $e) {
        // En cas d'erreur de structure SQL (table manquante, mauvaise colonne, etc.)
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "Impossible d'insérer les données: " . $e->getMessage()
        ]);
    }

} else {
    // Si l'URL est ouverte directement depuis un navigateur (requête GET au lieu de POST)
    http_response_code(405);
    echo json_encode([
        "status" => "error", 
        "message" => "Méthode non autorisée ou données JSON manquantes. L'Arduino doit envoyer une requête POST."
    ]);
}
?>
