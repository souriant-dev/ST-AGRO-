<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();
ensureAffectationsAgronomesTableExists();

$stmt = $pdo->prepare("SELECT u.id, u.nom, u.prenom, e.id AS exploitation_id, e.nom AS exploitation_nom, MAX(d.date_creation) AS derniere_date
    FROM affectations_agronomes aa
    JOIN utilisateurs u ON u.id = aa.agronome_id
    JOIN exploitations e ON e.id = aa.exploitation_id AND e.agriculteur_id = ?
    LEFT JOIN demandes_conseil d ON d.agronome_id = aa.agronome_id AND d.exploitation_id = e.id AND d.agriculteur_id = e.agriculteur_id
    GROUP BY u.id, u.nom, u.prenom, e.id, e.nom
    ORDER BY derniere_date DESC, e.nom");
$stmt->execute([$user['id']]);
$agronomes = $stmt->fetchAll();

$agronomeId = (int) ($_GET['agronome_id'] ?? 0);
if ($agronomeId === 0 && !empty($agronomes)) {
    $agronomeId = (int) $agronomes[0]['id'];
}

$exploitationId = (int) ($_GET['exploitation_id'] ?? 0);
if ($exploitationId === 0 && !empty($agronomes)) {
    $exploitationId = (int) $agronomes[0]['exploitation_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'chat_agronome' && verifierCSRF($_POST['csrf'] ?? null)) {
    $agronomeId = (int) ($_POST['agronome_id'] ?? 0);
    $exploitationId = (int) ($_POST['exploitation_id'] ?? 0);
    $contenu = trim($_POST['contenu'] ?? '');

    if ($agronomeId > 0 && $exploitationId > 0 && $contenu !== '') {
        $stmt = $pdo->prepare('SELECT aa.agronome_id FROM affectations_agronomes aa JOIN exploitations e ON e.id = aa.exploitation_id WHERE aa.agronome_id = ? AND aa.exploitation_id = ? AND e.agriculteur_id = ?');
        $stmt->execute([$agronomeId, $exploitationId, $user['id']]);
        if (!$stmt->fetch()) {
            header('Location: /st-agro/agriculteur/chat.php');
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM demandes_conseil WHERE agriculteur_id = ? AND agronome_id = ? AND exploitation_id = ? ORDER BY date_creation DESC LIMIT 1');
        $stmt->execute([$user['id'], $agronomeId, $exploitationId]);
        $discussion = $stmt->fetch();

        if (!$discussion) {
            $stmt = $pdo->prepare('INSERT INTO demandes_conseil (agriculteur_id, agronome_id, exploitation_id, sujet, description, statut) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $user['id'],
                $agronomeId,
                $exploitationId,
                'Suivi exploitation',
                'Conversation directe avec l’agronome sur l’exploitation sélectionnée.',
                'en_cours',
            ]);
            $discussionId = (int) $pdo->lastInsertId();
        } else {
            $discussionId = (int) $discussion['id'];
        }

        $stmt = $pdo->prepare('INSERT INTO messages_conseil (demande_id, expediteur_id, contenu) VALUES (?, ?, ?)');
        $stmt->execute([$discussionId, $user['id'], $contenu]);

        $stmt = $pdo->prepare('UPDATE demandes_conseil SET statut = ' . $pdo->quote('en_cours') . ' WHERE id = ?');
        $stmt->execute([$discussionId]);

        creerNotification($agronomeId, 'Nouveau message', 'Vous avez reçu un message de l’agriculteur sur son exploitation.');
        header('Location: /st-agro/agriculteur/chat.php?agronome_id=' . $agronomeId . '&exploitation_id=' . $exploitationId);
        exit;
    }
}

$messagesDiscussion = [];
if ($agronomeId > 0 && $exploitationId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM demandes_conseil WHERE agriculteur_id = ? AND agronome_id = ? AND exploitation_id = ? ORDER BY date_creation DESC LIMIT 1');
    $stmt->execute([$user['id'], $agronomeId, $exploitationId]);
    $discussion = $stmt->fetch();

    if ($discussion) {
        $stmt = $pdo->prepare('SELECT * FROM messages_conseil WHERE demande_id = ? ORDER BY date_envoi ASC');
        $stmt->execute([$discussion['id']]);
        $messagesDiscussion = $stmt->fetchAll();
    }
}

$titrePage = 'Chat';
$filAriane = 'Chat';
$pageActive = 'chat';
require __DIR__ . '/../includes/layout_debut.php';
$csrf = jetonCSRF();
?>
<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:18px;">
    <div>
        <h1>Chat avec mon agronome</h1>
        <p class="sous-titre-page">Suivez la discussion avec l’agronome qui accompagne vos exploitations.</p>
    </div>
</div>

<div class="carte" style="padding:20px; max-width:1200px;">
    <?php if (!$agronomes): ?>
        <div class="etat-vide">
            <div class="icone">&#128172;</div>
            <h4>Aucun agronome associé</h4>
            <p>Vous n’avez pas encore reçu d’accompagnement par un agronome pour une exploitation.</p>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns: 260px 1fr; gap:18px;">
            <div style="border-right:1px solid var(--bordure); padding-right:12px;">
                <h3 style="margin:0 0 12px;">Mes agronomes</h3>
                <?php foreach ($agronomes as $agro): ?>
                    <a href="/st-agro/agriculteur/chat.php?agronome_id=<?= $agro['id'] ?>&exploitation_id=<?= $agro['exploitation_id'] ?>"
                       style="display:block; text-decoration:none; color:inherit; margin-bottom:12px; padding:12px 10px; border-radius:10px; background:<?= (int)$agro['id'] === $agronomeId ? '#edf5ff' : '#f8f9fb' ?>; border:1px solid var(--bordure);">
                        <strong><?= nettoyer($agro['prenom'] . ' ' . $agro['nom']) ?></strong>
                        <div style="color:var(--texte-attenue); font-size:0.8rem; margin-top:4px;">
                            <?= nettoyer($agro['exploitation_nom']) ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div>
                <?php if ($agronomeId > 0):
                    $agronomeActif = null;
                    foreach ($agronomes as $agro) {
                        if ((int)$agro['id'] === $agronomeId) {
                            $agronomeActif = $agro;
                            break;
                        }
                    }
                ?>
                    <div style="margin-bottom:12px;">
                        <h3 style="margin:0;">Conversation avec <?= $agronomeActif ? nettoyer($agronomeActif['prenom'] . ' ' . $agronomeActif['nom']) : 'l’agronome' ?></h3>
                        <small style="color:var(--texte-attenue);">
                            <?= $agronomeActif ? nettoyer($agronomeActif['exploitation_nom']) : '' ?>
                        </small>
                    </div>

                    <div class="fil-messages" style="max-height:360px; overflow:auto; margin-bottom:16px;">
                        <?php if (!$messagesDiscussion): ?>
                            <p style="color:var(--texte-attenue); text-align:center;">Aucun message pour l’instant. Démarrez la discussion.</p>
                        <?php endif; ?>
                        <?php foreach ($messagesDiscussion as $m):
                            $mien = (int) $m['expediteur_id'] === (int) $user['id'];
                        ?>
                            <div class="message-bulle <?= $mien ? 'message-envoye' : 'message-recu' ?>">
                                <?= nettoyer($m['contenu']) ?>
                                <span class="message-heure"><?= formaterDate($m['date_envoi']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form method="post" style="display:flex; gap:10px; align-items:center;">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="chat_agronome">
                        <input type="hidden" name="agronome_id" value="<?= $agronomeId ?>">
                        <input type="hidden" name="exploitation_id" value="<?= $exploitationId ?>">
                        <input type="text" name="contenu" placeholder="Écrivez votre message à l’agronome..." required style="flex:1; padding:12px 14px; border:1.5px solid var(--bordure); border-radius:8px;">
                        <button type="submit" class="btn btn-primaire">Envoyer</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
