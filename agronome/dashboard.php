<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agronome']);
$user = utilisateurCourant();
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demandes_conseil WHERE statut = 'en_attente'");
$stmt->execute();
$demandesEnAttente = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM demandes_conseil WHERE agronome_id = ?');
$stmt->execute([$user['id']]);
$mesDemandesEnCours = (int) $stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM exploitations');
$totalExploitations = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM analyses_phytosanitaires WHERE traite_par_agronome_id IS NULL");
$analysesEnAttente = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT d.*, u.nom, u.prenom FROM demandes_conseil d
    JOIN utilisateurs u ON u.id = d.agriculteur_id
    WHERE d.statut = 'en_attente' ORDER BY d.date_creation ASC LIMIT 5");
$stmt->execute();
$demandesRecentes = $stmt->fetchAll();

$titrePage = 'Tableau de bord';
$filAriane = 'Tableau de bord';
$pageActive = 'dashboard';
require __DIR__ . '/../includes/layout_debut.php';
?>
<h1>Bonjour <?= nettoyer($user['prenom']) ?> &#128075;</h1>
<p class="sous-titre-page">Voici l'activité à traiter aujourd'hui.</p>

<div class="grille-stats">
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone ambre">&#128172;</span></div>
        <div class="valeur"><?= $demandesEnAttente ?></div>
        <div class="libelle">Demandes en attente</div>
    </div>
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone bleu">&#128203;</span></div>
        <div class="valeur"><?= $mesDemandesEnCours ?></div>
        <div class="libelle">Mes suivis en cours</div>
    </div>
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone vert">&#127806;</span></div>
        <div class="valeur"><?= $totalExploitations ?></div>
        <div class="libelle">Exploitations suivies</div>
    </div>
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone rouge">&#129717;</span></div>
        <div class="valeur"><?= $analysesEnAttente ?></div>
        <div class="libelle">Analyses à valider</div>
    </div>
</div>

<div class="carte">
    <div class="carte-titre">
        <h3>Demandes de conseil en attente</h3>
        <a href="/st-agro/agronome/conseils.php" class="btn btn-contour btn-sm">Voir tout</a>
    </div>
    <?php if (!$demandesRecentes): ?>
        <p style="color:var(--texte-attenue); font-size:0.9rem;">Aucune demande en attente. Excellent travail !</p>
    <?php else: ?>
        <?php foreach ($demandesRecentes as $d): ?>
            <a href="/st-agro/agronome/conseil_detail.php?id=<?= $d['id'] ?>" style="text-decoration:none; color:inherit;">
                <div class="capteur-item">
                    <div class="capteur-nom">
                        <span class="badge badge-ambre">En attente</span>
                        <div>
                            <strong style="display:block; color:var(--bleu-fonce);"><?= nettoyer($d['sujet']) ?></strong>
                            <small style="color:var(--texte-attenue);">Par <?= nettoyer($d['prenom'] . ' ' . $d['nom']) ?></small>
                        </div>
                    </div>
                    <small style="color:var(--texte-attenue);"><?= formaterDate($d['date_creation']) ?></small>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
