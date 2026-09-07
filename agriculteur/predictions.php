<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCSRF($_POST['csrf'] ?? null) && ($_POST['action'] ?? '') === 'generer_prediction') {
    $exploitationId = (int) ($_POST['exploitation_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id FROM exploitations WHERE id = ? AND agriculteur_id = ?');
    $stmt->execute([$exploitationId, $user['id']]);
    if (!$stmt->fetch()) {
        definirMessage('erreur', 'Exploitation invalide.');
    } else {
        $prediction = predireRendementAvecPython($pdo, $exploitationId);
        if (!$prediction['succes']) {
            definirMessage('erreur', $prediction['message']);
        } else {
            $stmt = $pdo->prepare('INSERT INTO predictions (exploitation_id, type_prediction, resultat, fiabilite) VALUES (?, ?, ?, ?)');
            $stmt->execute([$exploitationId, 'Rendement agricole', 'Rendement prédit : ' . $prediction['rendement'] . ' t/ha', 85]);
            definirMessage('succes', 'Prédiction générée pour ' . $prediction['nom'] . '.');
        }
    }
    header('Location: /st-agro/agriculteur/predictions.php');
    exit;
}

$stmt = $pdo->prepare('SELECT p.*, e.nom AS exploitation_nom, e.culture FROM predictions p
    JOIN exploitations e ON e.id = p.exploitation_id
    WHERE e.agriculteur_id = ? ORDER BY p.date_prediction DESC, p.id DESC');
$stmt->execute([$user['id']]);
$predictions = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT id, nom, culture FROM exploitations WHERE agriculteur_id = ? ORDER BY nom');
$stmt->execute([$user['id']]);
$exploitations = $stmt->fetchAll();
$csrf = jetonCSRF();

$titrePage = 'Prédictions de rendement';
$filAriane = 'Consulter prédictions';
$pageActive = 'predictions';
require __DIR__ . '/../includes/layout_debut.php';
?>
<h1>Prédictions de rendement</h1>
<p class="sous-titre-page">Consultez les prévisions générées pour chacune de vos exploitations.</p>

<div class="carte" style="margin-bottom:20px;">
    <div class="carte-titre"><h3>Générer une prédiction</h3></div>
    <?php if (!$exploitations): ?>
        <p>Aucune exploitation enregistrée.</p>
    <?php else: ?>
        <form method="post" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="generer_prediction">
            <div class="champ" style="margin:0; min-width:260px;">
                <label for="exploitation_id">Exploitation</label>
                <select id="exploitation_id" name="exploitation_id" required>
                    <?php foreach ($exploitations as $exploitation): ?>
                        <option value="<?= (int) $exploitation['id'] ?>"><?= nettoyer($exploitation['nom']) ?> — <?= nettoyer($exploitation['culture']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primaire" type="submit">Prédire le rendement</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$predictions): ?>
    <div class="carte">
        <div class="etat-vide">
            <div class="icone">&#128200;</div>
            <h4>Aucune prédiction disponible</h4>
            <p>Utilisez le formulaire ci-dessus pour générer une prédiction.</p>
        </div>
    </div>
<?php else: ?>
    <div class="carte">
        <div class="table-wrap">
            <table class="table-app">
                <thead>
                    <tr><th>Exploitation</th><th>Culture</th><th>Type</th><th>Prédiction</th><th>Fiabilité</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($predictions as $prediction): ?>
                    <tr>
                        <td><?= nettoyer($prediction['exploitation_nom']) ?></td>
                        <td><?= nettoyer($prediction['culture']) ?></td>
                        <td><?= nettoyer($prediction['type_prediction']) ?></td>
                        <td style="white-space:pre-wrap; max-width:420px;"><?= nettoyer($prediction['resultat']) ?></td>
                        <td><?= $prediction['fiabilite'] !== null ? number_format((float) $prediction['fiabilite'], 1) . ' %' : '—' ?></td>
                        <td><?= formaterDate($prediction['date_prediction']) ?></td>
                        <td><form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="generer_prediction"><input type="hidden" name="exploitation_id" value="<?= (int) $prediction['exploitation_id'] ?>"><button class="btn btn-fantome btn-sm" type="submit">Actualiser</button></form></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>