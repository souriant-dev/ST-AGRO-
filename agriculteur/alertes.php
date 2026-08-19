<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT a.*, e.nom AS exploitation_nom FROM alertes a
    JOIN exploitations e ON e.id = a.exploitation_id
    WHERE e.agriculteur_id = ? ORDER BY a.date_creation DESC");
$stmt->execute([$user['id']]);
$alertes = $stmt->fetchAll();

$titrePage = 'Alertes';
$filAriane = 'Alertes';
$pageActive = 'alertes';
require __DIR__ . '/../includes/layout_debut.php';
?>
<h1>Alertes</h1>
<p class="sous-titre-page">Événements critiques détectés sur vos exploitations.</p>

<?php if (!$alertes): ?>
    <div class="carte">
        <div class="etat-vide">
            <div class="icone">&#9989;</div>
            <h4>Aucune alerte active</h4>
            <p>Vos exploitations sont dans les paramètres normaux.</p>
        </div>
    </div>
<?php else: ?>
    <div class="carte">
        <?php foreach ($alertes as $a): ?>
            <div class="capteur-item">
                <div class="capteur-nom">
                    <?php $b = $a['niveau'] === 'critique' ? 'badge-rouge' : ($a['niveau'] === 'warning' ? 'badge-ambre' : 'badge-bleu'); ?>
                    <span class="badge <?= $b ?>"><?= ucfirst($a['niveau']) ?></span>
                    <div>
                        <strong style="display:block; color:var(--bleu-fonce);"><?= nettoyer($a['titre']) ?></strong>
                        <small style="color:var(--texte-attenue);"><?= nettoyer($a['message']) ?> — <?= nettoyer($a['exploitation_nom']) ?></small>
                    </div>
                </div>
                <small style="color:var(--texte-attenue);"><?= formaterDate($a['date_creation']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
