<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

$stmt = $pdo->prepare('SELECT * FROM exploitations WHERE agriculteur_id = ? ORDER BY date_creation DESC');
$stmt->execute([$user['id']]);
$exploitations = $stmt->fetchAll();
$idsExploitations = array_column($exploitations, 'id');

$nbEnAlerte = count(array_filter($exploitations, fn($e) => $e['statut'] === 'en_alerte'));
$superficieTotale = array_sum(array_column($exploitations, 'superficie'));

$capteursActifs = 0;
$dernieresAlertes = [];
if ($idsExploitations) {
    $in = implode(',', array_fill(0, count($idsExploitations), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM capteurs WHERE exploitation_id IN ($in) AND statut = 'actif'");
    $stmt->execute($idsExploitations);
    $capteursActifs = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT a.*, e.nom AS exploitation_nom FROM alertes a
        JOIN exploitations e ON e.id = a.exploitation_id
        WHERE a.exploitation_id IN ($in) ORDER BY a.date_creation DESC LIMIT 5");
    $stmt->execute($idsExploitations);
    $dernieresAlertes = $stmt->fetchAll();
}

$meteo = obtenirMeteo($user['ville'] ?: 'Yaoundé');

$titrePage = 'Tableau de bord';
$filAriane = 'Tableau de bord';
$pageActive = 'dashboard';
require __DIR__ . '/../includes/layout_debut.php';
?>
<h1>Bonjour <?= nettoyer($user['prenom']) ?> &#128075;</h1>
<p class="sous-titre-page">Voici l'état de vos exploitations aujourd'hui.</p>

<div class="grille-stats">
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone bleu">&#127806;</span></div>
        <div class="valeur"><?= count($exploitations) ?></div>
        <div class="libelle">Exploitations</div>
    </div>
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone vert">&#128207;</span></div>
        <div class="valeur"><?= number_format((float)$superficieTotale, 1) ?> ha</div>
        <div class="libelle">Superficie totale</div>
    </div>
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone bleu">&#128225;</span></div>
        <div class="valeur"><?= $capteursActifs ?></div>
        <div class="libelle">Capteurs actifs</div>
    </div>
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone <?= $nbEnAlerte ? 'rouge' : 'vert' ?>">&#9888;&#65039;</span></div>
        <div class="valeur"><?= $nbEnAlerte ?></div>
        <div class="libelle">Exploitations en alerte</div>
    </div>
</div>

<div class="grille-2">
    <div class="carte">
        <div class="carte-titre">
            <h3>Mes exploitations</h3>
            <a href="/st-agro/agriculteur/exploitations.php" class="btn btn-primaire btn-sm">+ Ajouter</a>
        </div>
        <?php if (!$exploitations): ?>
            <div class="etat-vide">
                <div class="icone">&#127806;</div>
                <h4>Aucune exploitation pour l'instant</h4>
                <p>Ajoutez votre première parcelle pour commencer le suivi.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-app">
                    <thead><tr><th>Nom</th><th>Culture</th><th>Superficie</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($exploitations, 0, 6) as $e): ?>
                        <tr>
                            <td><a href="/st-agro/agriculteur/exploitation_detail.php?id=<?= $e['id'] ?>"><?= nettoyer($e['nom']) ?></a></td>
                            <td><?= nettoyer($e['culture']) ?></td>
                            <td><?= $e['superficie'] ? number_format((float)$e['superficie'], 1) . ' ha' : '—' ?></td>
                            <td>
                                <?php if ($e['statut'] === 'en_alerte'): ?><span class="badge badge-rouge">En alerte</span>
                                <?php elseif ($e['statut'] === 'recoltee'): ?><span class="badge badge-gris">Récoltée</span>
                                <?php else: ?><span class="badge badge-vert">En cours</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="meteo-widget" style="margin-bottom:20px;">
            <div class="ville">&#127780; <?= nettoyer($meteo['ville']) ?></div>
            <div class="desc"><?= nettoyer($meteo['description']) ?></div>
            <div class="temp"><?= $meteo['temperature'] ?>°C</div>
            <div class="meteo-details">
                <div>Humidité<strong><?= $meteo['humidite'] ?>%</strong></div>
                <div>Vent<strong><?= $meteo['vent'] ?> km/h</strong></div>
            </div>
        </div>

        <div class="carte">
            <div class="carte-titre"><h3>Dernières alertes</h3></div>
            <?php if (!$dernieresAlertes): ?>
                <p style="color:var(--texte-attenue); font-size:0.9rem;">Aucune alerte récente. Tout va bien !</p>
            <?php else: ?>
                <?php foreach ($dernieresAlertes as $a): ?>
                    <div class="capteur-item">
                        <div class="capteur-nom">
                            <span class="pastille-etat <?= $a['niveau'] === 'critique' ? 'panne' : 'actif' ?>"></span>
                            <?= nettoyer($a['titre']) ?> — <small style="color:var(--texte-attenue);"><?= nettoyer($a['exploitation_nom']) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
