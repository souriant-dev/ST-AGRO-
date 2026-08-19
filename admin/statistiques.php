<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['administrateur']);
$pdo = getPDO();

$totalUtilisateurs = (int) $pdo->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn();
$totalExploitations = (int) $pdo->query('SELECT COUNT(*) FROM exploitations')->fetchColumn();
$totalCapteurs = (int) $pdo->query('SELECT COUNT(*) FROM capteurs')->fetchColumn();
$totalAlertes = (int) $pdo->query('SELECT COUNT(*) FROM alertes')->fetchColumn();
$totalConseils = (int) $pdo->query('SELECT COUNT(*) FROM demandes_conseil')->fetchColumn();
$totalAnalyses = (int) $pdo->query('SELECT COUNT(*) FROM analyses_phytosanitaires')->fetchColumn();

$stmt = $pdo->query('SELECT culture, COUNT(*) AS total FROM exploitations GROUP BY culture ORDER BY total DESC LIMIT 6');
$parCulture = $stmt->fetchAll();
$maxCulture = max(array_column($parCulture, 'total') ?: [1]);

$stmt = $pdo->query("SELECT statut, COUNT(*) AS total FROM demandes_conseil GROUP BY statut");
$parStatutConseil = ['en_attente' => 0, 'en_cours' => 0, 'repondu' => 0];
foreach ($stmt->fetchAll() as $r) { $parStatutConseil[$r['statut']] = (int) $r['total']; }
$maxConseil = max(array_values($parStatutConseil) ?: [1]);

$titrePage = 'Statistiques globales';
$filAriane = 'Statistiques globales';
$pageActive = 'statistiques';
require __DIR__ . '/../includes/layout_debut.php';
?>
<h1>Statistiques globales</h1>
<p class="sous-titre-page">Activité mesurée sur l'ensemble de la plateforme ST-AGRO.</p>

<div class="grille-stats">
    <div class="stat-carte"><div class="stat-tete"><span class="stat-icone bleu">&#128101;</span></div><div class="valeur"><?= $totalUtilisateurs ?></div><div class="libelle">Utilisateurs</div></div>
    <div class="stat-carte"><div class="stat-tete"><span class="stat-icone vert">&#127806;</span></div><div class="valeur"><?= $totalExploitations ?></div><div class="libelle">Exploitations</div></div>
    <div class="stat-carte"><div class="stat-tete"><span class="stat-icone bleu">&#128225;</span></div><div class="valeur"><?= $totalCapteurs ?></div><div class="libelle">Capteurs</div></div>
    <div class="stat-carte"><div class="stat-tete"><span class="stat-icone rouge">&#9888;&#65039;</span></div><div class="valeur"><?= $totalAlertes ?></div><div class="libelle">Alertes émises</div></div>
</div>

<div class="grille-2">
    <div class="carte">
        <div class="carte-titre"><h3>Répartition des cultures</h3></div>
        <?php if (!$parCulture): ?>
            <p style="color:var(--texte-attenue); font-size:0.9rem;">Aucune donnée disponible.</p>
        <?php else: ?>
            <?php foreach ($parCulture as $c): $pct = round(($c['total'] / $maxCulture) * 100); ?>
                <div style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:6px;">
                        <span><?= nettoyer($c['culture']) ?></span><strong style="font-family:var(--police-data);"><?= $c['total'] ?></strong>
                    </div>
                    <div style="background:var(--bleu-liseret); border-radius:20px; height:10px;">
                        <div style="background:var(--bleu-primaire); width:<?= $pct ?>%; height:100%; border-radius:20px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="carte">
        <div class="carte-titre"><h3>Demandes de conseil</h3></div>
        <?php
        $labels = ['en_attente' => 'En attente', 'en_cours' => 'En cours', 'repondu' => 'Répondues'];
        foreach ($labels as $cle => $lib): $pct = round(($parStatutConseil[$cle] / $maxConseil) * 100);
        ?>
            <div style="margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:6px;">
                    <span><?= $lib ?></span><strong style="font-family:var(--police-data);"><?= $parStatutConseil[$cle] ?></strong>
                </div>
                <div style="background:var(--bleu-liseret); border-radius:20px; height:10px;">
                    <div style="background:var(--vert-culture); width:<?= $pct ?>%; height:100%; border-radius:20px;"></div>
                </div>
            </div>
        <?php endforeach; ?>
        <p style="margin-top:16px; font-size:0.85rem; color:var(--texte-attenue);">
            <?= $totalAnalyses ?> analyse(s) phytosanitaire(s) enregistrée(s) au total.
        </p>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
