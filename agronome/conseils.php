<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agronome']);
$user = utilisateurCourant();
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCSRF($_POST['csrf'] ?? null) && ($_POST['action'] ?? '') === 'prendre_en_charge') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE demandes_conseil SET agronome_id = ?, statut = 'en_cours' WHERE id = ? AND agronome_id IS NULL");
    $stmt->execute([$user['id'], $id]);
    $stmt = $pdo->prepare('SELECT agriculteur_id, sujet FROM demandes_conseil WHERE id = ?');
    $stmt->execute([$id]);
    if ($d = $stmt->fetch()) {
        creerNotification($d['agriculteur_id'], 'Demande prise en charge', 'Un agronome s\'occupe de : ' . $d['sujet']);
    }
    header('Location: /st-agro/agronome/conseil_detail.php?id=' . $id);
    exit;
}

$stmt = $pdo->prepare("SELECT d.*, u.nom, u.prenom FROM demandes_conseil d
    JOIN utilisateurs u ON u.id = d.agriculteur_id
    WHERE d.agronome_id = ? OR d.agronome_id IS NULL ORDER BY (d.statut = 'en_attente') DESC, d.date_creation DESC");
$stmt->execute([$user['id']]);
$demandes = $stmt->fetchAll();

$titrePage = 'Demandes de conseil';
$filAriane = 'Demandes de conseil';
$pageActive = 'conseils';
require __DIR__ . '/../includes/layout_debut.php';
$csrf = jetonCSRF();
$libStatut = ['en_attente' => ['En attente', 'badge-ambre'], 'en_cours' => ['En cours', 'badge-bleu'], 'repondu' => ['Répondu', 'badge-vert']];
?>
<h1>Demandes de conseil</h1>
<p class="sous-titre-page">Prenez en charge les demandes des agriculteurs et répondez-leur.</p>

<?php if (!$demandes): ?>
    <div class="carte"><div class="etat-vide"><div class="icone">&#128172;</div><h4>Aucune demande</h4></div></div>
<?php else: ?>
    <div class="carte">
        <?php foreach ($demandes as $d): [$libelle, $classe] = $libStatut[$d['statut']]; ?>
            <div class="capteur-item">
                <div class="capteur-nom">
                    <span class="badge <?= $classe ?>"><?= $libelle ?></span>
                    <div>
                        <a href="/st-agro/agronome/conseil_detail.php?id=<?= $d['id'] ?>" style="color:var(--bleu-fonce); font-weight:600;"><?= nettoyer($d['sujet']) ?></a>
                        <small style="display:block; color:var(--texte-attenue);">Par <?= nettoyer($d['prenom'] . ' ' . $d['nom']) ?> — <?= formaterDate($d['date_creation']) ?></small>
                    </div>
                </div>
                <?php if (!$d['agronome_id']): ?>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="prendre_en_charge">
                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                        <button type="submit" class="btn btn-primaire btn-sm">Prendre en charge</button>
                    </form>
                <?php else: ?>
                    <a href="/st-agro/agronome/conseil_detail.php?id=<?= $d['id'] ?>" class="btn btn-contour btn-sm">Ouvrir</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
