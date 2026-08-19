<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agronome']);
$user = utilisateurCourant();
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCSRF($_POST['csrf'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);
    $diagnostic = trim($_POST['diagnostic'] ?? '');
    $niveau = in_array($_POST['niveau_risque'] ?? '', ['faible', 'modere', 'eleve'], true) ? $_POST['niveau_risque'] : 'faible';
    $recommandation = trim($_POST['recommandation'] ?? '');

    if ($diagnostic !== '') {
        $stmt = $pdo->prepare('UPDATE analyses_phytosanitaires SET diagnostic=?, niveau_risque=?, recommandation=?, traite_par_agronome_id=? WHERE id=?');
        $stmt->execute([$diagnostic, $niveau, $recommandation, $user['id'], $id]);

        $stmt = $pdo->prepare('SELECT e.agriculteur_id, e.nom FROM analyses_phytosanitaires a JOIN exploitations e ON e.id = a.exploitation_id WHERE a.id = ?');
        $stmt->execute([$id]);
        if ($info = $stmt->fetch()) {
            creerNotification($info['agriculteur_id'], 'Analyse phytosanitaire disponible', 'Le diagnostic pour ' . $info['nom'] . ' est prêt.');
        }
        definirMessage('succes', 'Diagnostic enregistré et transmis à l\'agriculteur.');
    }
    header('Location: /agronome/phytosanitaire.php');
    exit;
}

$stmt = $pdo->query("SELECT a.*, e.nom AS exploitation_nom, u.nom, u.prenom FROM analyses_phytosanitaires a
    JOIN exploitations e ON e.id = a.exploitation_id
    JOIN utilisateurs u ON u.id = e.agriculteur_id
    ORDER BY (a.traite_par_agronome_id IS NULL) DESC, a.date_analyse DESC");
$analyses = $stmt->fetchAll();

$titrePage = 'Analyses phytosanitaires';
$filAriane = 'Analyses phytosanitaires';
$pageActive = 'phyto';
require __DIR__ . '/../includes/layout_debut.php';
$csrf = jetonCSRF();
?>
<h1>Analyses phytosanitaires</h1>
<p class="sous-titre-page">Validez et complétez les diagnostics envoyés par les agriculteurs.</p>

<?php if (!$analyses): ?>
    <div class="carte"><div class="etat-vide"><div class="icone">&#129717;</div><h4>Aucune analyse à traiter</h4></div></div>
<?php else: ?>
    <?php foreach ($analyses as $a): ?>
        <div class="carte" style="margin-bottom:16px;">
            <div class="carte-titre">
                <h3><?= nettoyer($a['exploitation_nom']) ?> <small style="color:var(--texte-attenue); font-weight:400;">— <?= nettoyer($a['prenom'] . ' ' . $a['nom']) ?></small></h3>
                <?= $a['traite_par_agronome_id'] ? '<span class="badge badge-vert">Traitée</span>' : '<span class="badge badge-ambre">À valider</span>' ?>
            </div>
            <?php if ($a['image_path']): ?>
                <img src="<?= nettoyer($a['image_path']) ?>" alt="Photo envoyée" style="max-width:220px; border-radius:8px; margin-bottom:14px;">
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <div class="champ">
                    <label>Diagnostic</label>
                    <textarea name="diagnostic" rows="2" required><?= nettoyer($a['diagnostic']) ?></textarea>
                </div>
                <div class="champ-groupe">
                    <div class="champ">
                        <label>Niveau de risque</label>
                        <select name="niveau_risque">
                            <option value="faible" <?= $a['niveau_risque']==='faible'?'selected':'' ?>>Faible</option>
                            <option value="modere" <?= $a['niveau_risque']==='modere'?'selected':'' ?>>Modéré</option>
                            <option value="eleve" <?= $a['niveau_risque']==='eleve'?'selected':'' ?>>Élevé</option>
                        </select>
                    </div>
                    <div class="champ">
                        <label>Recommandation</label>
                        <input type="text" name="recommandation" value="<?= nettoyer((string)$a['recommandation']) ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primaire">Enregistrer & transmettre à l'agriculteur</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
