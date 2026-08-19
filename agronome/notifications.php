<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agronome']);
$user = utilisateurCourant();
$pdo = getPDO();

$stmt = $pdo->prepare('UPDATE notifications SET lue = 1 WHERE utilisateur_id = ?');
$stmt->execute([$user['id']]);

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY date_creation DESC LIMIT 40');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$titrePage = 'Notifications';
$filAriane = 'Notifications';
$pageActive = 'notifications';
require __DIR__ . '/../includes/layout_debut.php';
?>
<h1>Notifications</h1>
<p class="sous-titre-page">Vos échanges et validations récentes.</p>

<?php if (!$notifications): ?>
    <div class="carte"><div class="etat-vide"><div class="icone">&#128276;</div><h4>Rien de nouveau</h4></div></div>
<?php else: ?>
    <div class="carte">
        <?php foreach ($notifications as $n): ?>
            <div class="capteur-item">
                <div class="capteur-nom">
                    <span class="pastille-etat <?= $n['lue'] ? 'inactif' : 'actif' ?>"></span>
                    <div>
                        <strong style="display:block; color:var(--bleu-fonce);"><?= nettoyer($n['titre']) ?></strong>
                        <small style="color:var(--texte-attenue);"><?= nettoyer($n['message']) ?></small>
                    </div>
                </div>
                <small style="color:var(--texte-attenue);"><?= formaterDate($n['date_creation']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
