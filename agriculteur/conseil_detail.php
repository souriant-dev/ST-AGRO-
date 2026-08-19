<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM demandes_conseil WHERE id = ? AND agriculteur_id = ?');
$stmt->execute([$id, $user['id']]);
$demande = $stmt->fetch();
if (!$demande) { header('Location: /agriculteur/conseils.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCSRF($_POST['csrf'] ?? null)) {
    $contenu = trim($_POST['contenu'] ?? '');
    if ($contenu !== '') {
        $stmt = $pdo->prepare('INSERT INTO messages_conseil (demande_id, expediteur_id, contenu) VALUES (?, ?, ?)');
        $stmt->execute([$id, $user['id'], $contenu]);
        if ($demande['agronome_id']) {
            creerNotification($demande['agronome_id'], 'Nouveau message', 'Nouveau message sur : ' . $demande['sujet']);
        }
    }
    header('Location: /agriculteur/conseil_detail.php?id=' . $id);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM messages_conseil WHERE demande_id = ? ORDER BY date_envoi ASC');
$stmt->execute([$id]);
$messages = $stmt->fetchAll();

$titrePage = $demande['sujet'];
$filAriane = 'Demander conseil / ' . $demande['sujet'];
$pageActive = 'conseils';
require __DIR__ . '/../includes/layout_debut.php';
$csrf = jetonCSRF();
?>
<h1><?= nettoyer($demande['sujet']) ?></h1>
<p class="sous-titre-page"><?= nettoyer($demande['description']) ?></p>

<div class="carte">
    <div class="fil-messages">
        <?php if (!$messages): ?>
            <p style="color:var(--texte-attenue); font-size:0.9rem; text-align:center;">Aucun message pour l'instant. Un agronome vous répondra bientôt.</p>
        <?php endif; ?>
        <?php foreach ($messages as $m):
            $mien = $m['expediteur_id'] == $user['id'];
        ?>
            <div class="message-bulle <?= $mien ? 'message-envoye' : 'message-recu' ?>">
                <?= nettoyer($m['contenu']) ?>
                <span class="message-heure"><?= formaterDate($m['date_envoi']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="post" style="display:flex; gap:10px; margin-top:18px;">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="text" name="contenu" placeholder="Écrivez votre message..." required style="flex:1; padding:12px 14px; border:1.5px solid var(--bordure); border-radius:8px;">
        <button type="submit" class="btn btn-primaire">Envoyer</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
