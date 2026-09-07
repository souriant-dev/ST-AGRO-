<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

$stmt = $pdo->prepare('SELECT id, nom, culture, type_sol, superficie, ville, latitude, longitude, adresse_ip, irrigation_active, statut FROM exploitations WHERE agriculteur_id = ? ORDER BY nom');
$stmt->execute([$user['id']]);
$exploitations = $stmt->fetchAll();
$exploitationId = (int) ($_POST['exploitation_id'] ?? $_GET['exploitation_id'] ?? 0);
$exploitationSelectionnee = null;
foreach ($exploitations as $exploitation) {
    if ((int) $exploitation['id'] === $exploitationId) {
        $exploitationSelectionnee = $exploitation;
        break;
    }
}

$messages = [
    [
        'role' => 'assistant',
        'texte' => 'Bonjour ! Je suis votre assistant agricole ST-AGRO. Posez-moi une question sur les cultures, le sol, l’irrigation, les maladies, ou le suivi de vos exploitations.',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $message = trim($_POST['message']);
    if ($message !== '') {
        $messages[] = ['role' => 'user', 'texte' => $message];
        $donnees = "Aucune exploitation n'a été sélectionnée. Donne des conseils généraux et précise que des données d'exploitation sont nécessaires pour un diagnostic précis.\n";
        if ($exploitationSelectionnee) {
            $donnees = "Données de l'exploitation sélectionnée :\n" .
                '- Nom : ' . $exploitationSelectionnee['nom'] . "\n" .
                '- Culture : ' . $exploitationSelectionnee['culture'] . "\n" .
                '- Type de sol : ' . ($exploitationSelectionnee['type_sol'] ?: 'non renseigné') . "\n" .
                '- Superficie : ' . ($exploitationSelectionnee['superficie'] !== null ? $exploitationSelectionnee['superficie'] . ' ha' : 'non renseignée') . "\n" .
                '- Localisation : ' . ($exploitationSelectionnee['ville'] ?: 'non renseignée') . "\n" .
                '- Irrigation : ' . ((int) $exploitationSelectionnee['irrigation_active'] ? 'active' : 'inactive') . "\n" .
                '- Statut : ' . $exploitationSelectionnee['statut'] . "\n";

            $meteo = obtenirMeteoParLocalisation(
                $exploitationSelectionnee['latitude'] !== null ? (float) $exploitationSelectionnee['latitude'] : null,
                $exploitationSelectionnee['longitude'] !== null ? (float) $exploitationSelectionnee['longitude'] : null,
                $exploitationSelectionnee['ville']
            );
            $donnees .= "\nMétéo actuelle :\n" .
                '- Température : ' . $meteo['temperature'] . " °C\n" .
                '- Humidité : ' . $meteo['humidite'] . " %\n" .
                '- Conditions : ' . $meteo['description'] . "\n" .
                '- Vent : ' . $meteo['vent'] . " km/h\n";

            $stmt = $pdo->prepare('SELECT m.temperature, m.humidite_air, m.humidite_sol, m.luminosite, m.niveau_eau, m.azote, m.phosphore, m.potassium, m.date_mesure FROM mesures m WHERE m.exploitation_id = ? ORDER BY m.date_mesure DESC LIMIT 5');
            $stmt->execute([$exploitationId]);
            $mesures = $stmt->fetchAll();
            $donnees .= "\nDernières mesures des capteurs :\n";
            if (!$mesures) {
                $donnees .= '- Aucune mesure disponible.\n';
            } else {
                foreach ($mesures as $mesure) {
                    $valeurs = [];
                    foreach (['temperature' => 'température °C', 'humidite_air' => 'humidité air %', 'humidite_sol' => 'humidité sol %', 'luminosite' => 'luminosité lux', 'niveau_eau' => 'niveau eau %', 'azote' => 'azote mg/kg', 'phosphore' => 'phosphore mg/kg', 'potassium' => 'potassium mg/kg'] as $champ => $libelle) {
                        if ($mesure[$champ] !== null) {
                            $valeurs[] = $libelle . '=' . $mesure[$champ];
                        }
                    }
                    $donnees .= '- ' . $mesure['date_mesure'] . ' : ' . implode(', ', $valeurs) . "\n";
                }
            }
        }
        $reponse = reponseChatGemini($message, ['profil' => 'Agriculteur', 'donnees' => $donnees]);
        $messages[] = ['role' => 'assistant', 'texte' => $reponse];
    }
}

$titrePage = 'Demander conseil à l’IA';
$filAriane = 'Demander conseil à l’IA';
$pageActive = 'assistant_ia';
require __DIR__ . '/../includes/layout_debut.php';
?>
<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:18px;">
    <div>
        <h1>Demander conseil à l’IA</h1>
        <p class="sous-titre-page">Discussion avec l’assistant IA GroqCloud pour obtenir des conseils agronomiques rapides.</p>
    </div>
</div>

<div class="carte" style="padding:20px; max-width:1000px;">
    <div style="display:flex; flex-direction:column; gap:14px; min-height:420px;">
        <?php foreach ($messages as $msg): ?>
            <div style="max-width:75%; padding:12px 14px; border-radius:14px; line-height:1.5; background:<?= $msg['role'] === 'assistant' ? '#eaf4ff' : '#f3f5f7' ?>; margin-left:<?= $msg['role'] === 'assistant' ? '0' : 'auto' ?>; border:1px solid var(--bordure);">
                <strong style="display:block; margin-bottom:4px; color:var(--bleu-fonce);">
                    <?= $msg['role'] === 'assistant' ? 'Assistant IA' : 'Vous' ?>
                </strong>
                <div style="white-space:pre-wrap; color:var(--texte);">
                    <?= nettoyer($msg['texte']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" style="margin-top:18px;">
        <div class="champ" style="margin-bottom:12px;">
            <label for="exploitation_id">Exploitation à analyser</label>
            <select id="exploitation_id" name="exploitation_id">
                <option value="0">Conseil général</option>
                <?php foreach ($exploitations as $exploitation): ?>
                    <option value="<?= (int) $exploitation['id'] ?>" <?= $exploitationId === (int) $exploitation['id'] ? 'selected' : '' ?>><?= nettoyer($exploitation['nom']) ?> — <?= nettoyer($exploitation['culture']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ" style="margin-bottom:12px;">
            <label for="message">Votre question</label>
            <textarea id="message" name="message" rows="4" placeholder="Ex : Je vois des feuilles jaunes sur ma parcelle de maïs, que faire ?" required></textarea>
        </div>
        <button type="submit" class="btn btn-primaire">Envoyer</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
