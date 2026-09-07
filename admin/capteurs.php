<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['administrateur']);
$pdo = getPDO();
ensureMesuresTableExists();

$capteursEtat = $pdo->query("SELECT c.id, c.code_capteur, c.adresse_ip, c.statut AS statut_capteur,
        e.nom AS exploitation_nom, u.nom AS agriculteur_nom, u.prenom AS agriculteur_prenom,
        r.derniere_mesure_releve, m.derniere_mesure_historique
    FROM capteurs c
    JOIN exploitations e ON e.id = c.exploitation_id
    JOIN utilisateurs u ON u.id = e.agriculteur_id
    LEFT JOIN (
        SELECT capteur_id, MAX(date_releve) AS derniere_mesure_releve
        FROM releves_capteurs GROUP BY capteur_id
    ) r ON r.capteur_id = c.id
    LEFT JOIN (
        SELECT capteur_id, MAX(date_mesure) AS derniere_mesure_historique
        FROM mesures GROUP BY capteur_id
    ) m ON m.capteur_id = c.id
    ORDER BY e.nom, c.code_capteur")->fetchAll();

$capteursProbleme = 0;
foreach ($capteursEtat as &$capteur) {
    $dates = array_filter([$capteur['derniere_mesure_releve'], $capteur['derniere_mesure_historique']]);
    $capteur['derniere_mesure'] = $dates ? max($dates) : null;
    $capteur['en_probleme'] = !$capteur['derniere_mesure'] || (time() - strtotime($capteur['derniere_mesure'])) > 1800;
    if ($capteur['en_probleme']) {
        $capteursProbleme++;
    }
}
unset($capteur);

$titrePage = 'État des capteurs';
$filAriane = 'État des capteurs';
$pageActive = 'capteurs';
require __DIR__ . '/../includes/layout_debut.php';
?>
<h1>État des capteurs</h1>
<p class="sous-titre-page">Surveillez les envois de mesure de tous les capteurs de la plateforme.</p>

<div class="grille-stats">
    <div class="stat-carte"><div class="stat-tete"><span class="stat-icone bleu">&#128225;</span></div><div class="valeur"><?= count($capteursEtat) ?></div><div class="libelle">Capteurs enregistrés</div></div>
    <div class="stat-carte"><div class="stat-tete"><span class="stat-icone <?= $capteursProbleme ? 'rouge' : 'vert' ?>">&#9888;&#65039;</span></div><div class="valeur"><?= $capteursProbleme ?></div><div class="libelle">Capteurs à vérifier</div></div>
    <div class="stat-carte"><div class="stat-tete"><span class="stat-icone vert">&#10003;</span></div><div class="valeur"><?= count($capteursEtat) - $capteursProbleme ?></div><div class="libelle">Capteurs fonctionnels</div></div>
</div>

<div class="carte">
    <div class="carte-titre"><h3>Suivi des capteurs</h3></div>
    <p style="color:var(--texte-attenue); font-size:0.85rem;">Un capteur est à vérifier s’il n’a envoyé aucune mesure depuis plus de 30 minutes.</p>
    <div class="table-wrap">
        <table class="table-app">
            <thead><tr><th>Capteur / IP</th><th>Exploitation</th><th>Agriculteur</th><th>Dernière mesure</th><th>État</th></tr></thead>
            <tbody>
            <?php foreach ($capteursEtat as $capteur): ?>
                <tr>
                    <td><?= nettoyer($capteur['code_capteur']) ?><br><small>IP : <?= nettoyer((string) $capteur['adresse_ip']) ?></small></td>
                    <td><?= nettoyer($capteur['exploitation_nom']) ?></td>
                    <td><?= nettoyer($capteur['agriculteur_prenom'] . ' ' . $capteur['agriculteur_nom']) ?></td>
                    <td><?= $capteur['derniere_mesure'] ? nettoyer(date('d/m/Y H:i', strtotime($capteur['derniere_mesure']))) : 'Aucune mesure' ?></td>
                    <td><?= $capteur['en_probleme'] ? '<span class="badge badge-rouge">À vérifier</span>' : '<span class="badge badge-vert">Fonctionnel</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$capteursEtat): ?><tr><td colspan="5">Aucun capteur enregistré.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
