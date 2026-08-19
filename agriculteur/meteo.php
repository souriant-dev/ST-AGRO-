<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

$stmt = $pdo->prepare('SELECT id, nom, ville FROM exploitations WHERE agriculteur_id = ?');
$stmt->execute([$user['id']]);
$exploitations = $stmt->fetchAll();

$titrePage = 'Météo';
$filAriane = 'Météo';
$pageActive = 'meteo';
require __DIR__ . '/../includes/layout_debut.php';
?>
<h1>Météo par exploitation</h1>
<p class="sous-titre-page">Conditions actuelles pour chacune de vos parcelles.</p>

<?php if (!$exploitations): ?>
    <div class="carte">
        <div class="etat-vide">
            <div class="icone">&#127780;</div>
            <h4>Aucune exploitation à afficher</h4>
            <p>Ajoutez une exploitation avec une ville pour consulter sa météo.</p>
        </div>
    </div>
<?php else: ?>
    <div class="grille-3">
        <?php foreach ($exploitations as $e):
            $ville = $e['ville'] ?: ($user['ville'] ?: 'Yaoundé');
            $meteo = obtenirMeteo($ville);
        ?>
            <div class="meteo-widget">
                <div class="ville"><?= nettoyer($e['nom']) ?></div>
                <div class="desc">&#127780; <?= nettoyer($ville) ?> · <?= nettoyer($meteo['description']) ?></div>
                <div class="temp"><?= $meteo['temperature'] ?>°C</div>
                <div class="meteo-details">
                    <div>Humidité<strong><?= $meteo['humidite'] ?>%</strong></div>
                    <div>Vent<strong><?= $meteo['vent'] ?> km/h</strong></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p style="margin-top:18px; color:var(--texte-attenue); font-size:0.85rem;">
        Données fournies par l'API météorologique OpenWeatherMap. Configurez votre clé dans <code>config/database.php</code> pour des données en temps réel.
    </p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
