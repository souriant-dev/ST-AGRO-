<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agronome']);
$user = utilisateurCourant();
$pdo = getPDO();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT d.*, u.nom, u.prenom FROM demandes_conseil d JOIN utilisateurs u ON u.id = d.agriculteur_id WHERE d.id = ?');
$stmt->execute([$id]);
$demande = $stmt->fetch();
if (!$demande) { header('Location: /st-agro/agronome/conseils.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCSRF($_POST['csrf'] ?? null)) {
    $contenu = trim($_POST['contenu'] ?? '');
    if ($contenu !== '') {
        $stmt = $pdo->prepare('INSERT INTO messages_conseil (demande_id, expediteur_id, contenu) VALUES (?, ?, ?)');
        $stmt->execute([$id, $user['id'], $contenu]);
        $stmt = $pdo->prepare("UPDATE demandes_conseil SET statut = 'repondu', agronome_id = COALESCE(agronome_id, ?) WHERE id = ?");
        $stmt->execute([$user['id'], $id]);
        creerNotification($demande['agriculteur_id'], 'Réponse de votre agronome', 'Nouvelle réponse sur : ' . $demande['sujet']);
    }
    header('Location: /st-agro/agronome/conseil_detail.php?id=' . $id);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM messages_conseil WHERE demande_id = ? ORDER BY date_envoi ASC');
$stmt->execute([$id]);
$messages = $stmt->fetchAll();

$titrePage = $demande['sujet'];
$filAriane = 'Demandes de conseil / ' . $demande['sujet'];
$pageActive = 'conseils';
require __DIR__ . '/../includes/layout_debut.php';
$csrf = jetonCSRF();
?>
<h1><?= nettoyer($demande['sujet']) ?></h1>
<p class="sous-titre-page">Demande de <?= nettoyer($demande['prenom'] . ' ' . $demande['nom']) ?> — <?= nettoyer($demande['description']) ?></p>

<div class="carte">
    <div class="fil-messages">
        <?php if (!$messages): ?>
            <p style="color:var(--texte-attenue); font-size:0.9rem; text-align:center;">Répondez à cette demande pour démarrer la conversation.</p>
        <?php endif; ?>
        <?php foreach ($messages as $m): $mien = $m['expediteur_id'] == $user['id']; ?>
            <div class="message-bulle <?= $mien ? 'message-envoye' : 'message-recu' ?>">
                <?= nettoyer($m['contenu']) ?>
                <span class="message-heure"><?= formaterDate($m['date_envoi']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="post" style="display:flex; gap:10px; margin-top:18px;">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="text" name="contenu" placeholder="Rédigez votre réponse..." required style="flex:1; padding:12px 14px; border:1.5px solid var(--bordure); border-radius:8px;">
        <button type="submit" class="btn btn-primaire">Répondre</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
