<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT c.*, e.nom AS exploitation_nom, e.id AS exploitation_id
    FROM capteurs c JOIN exploitations e ON e.id = c.exploitation_id
    WHERE e.agriculteur_id = ? ORDER BY c.statut = 'en_panne' DESC, c.id DESC");
$stmt->execute([$user['id']]);
$capteurs = $stmt->fetchAll();

$historiqueParCapteur = [];
foreach ($capteurs as $capteur) {
    $historiqueParCapteur[$capteur['id']] = obtenirHistoriqueMesures((int) $capteur['id'], 12);
}

$libType = ['humidite_sol' => 'Humidité du sol', 'temperature' => 'Température', 'luminosite' => 'Luminosité', 'ph_sol' => 'pH du sol', 'pluviometrie' => 'Pluviométrie', 'azote_sol' => 'Azote du sol', 'phosphore_sol' => 'Phosphore du sol', 'potassium_sol' => 'Potassium du sol'];

$titrePage = 'Capteurs & terrain';
$filAriane = 'Capteurs & terrain';
$pageActive = 'capteurs';
require __DIR__ . '/../includes/layout_debut.php';
?>
<h1>Capteurs & terrain</h1>
<p class="sous-titre-page">Relevés transmis par vos capteurs IoT sur l'ensemble de vos exploitations.</p>

<?php if (!$capteurs): ?>
    <div class="carte">
        <div class="etat-vide">
            <div class="icone">&#128225;</div>
            <h4>Aucun capteur enregistré</h4>
            <p>Rendez-vous sur une exploitation pour y ajouter un capteur.</p>
            <a href="/st-agro/agriculteur/exploitations.php" class="btn btn-primaire" style="margin-top:14px;">Voir mes exploitations</a>
        </div>
    </div>
<?php else: ?>
    <div class="carte">
        <div class="table-wrap">
            <table class="table-app">
                <thead><tr><th>Code</th><th>Type</th><th>Exploitation</th><th>Dernier relevé</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($capteurs as $c):
                    $s = $pdo->prepare('SELECT * FROM releves_capteurs WHERE capteur_id = ? ORDER BY date_releve DESC LIMIT 1');
                    $s->execute([$c['id']]);
                    $r = $s->fetch();
                    $historique = $historiqueParCapteur[$c['id']] ?? [];
                ?>
                    <tr>
                        <td><?= nettoyer($c['code_capteur']) ?></td>
                        <td><?= $libType[$c['type_capteur']] ?? $c['type_capteur'] ?></td>
                        <td><a href="/st-agro/agriculteur/exploitation_detail.php?id=<?= $c['exploitation_id'] ?>"><?= nettoyer($c['exploitation_nom']) ?></a></td>
                        <td class="capteur-valeur"><?= $r ? $r['valeur'] . ' ' . $r['unite'] . ' — ' . formaterDate($r['date_releve']) : 'Aucun relevé' ?></td>
                        <td>
                            <?php if ($c['statut'] === 'actif'): ?><span class="badge badge-vert">Actif</span>
                            <?php elseif ($c['statut'] === 'en_panne'): ?><span class="badge badge-rouge">En panne</span>
                            <?php else: ?><span class="badge badge-gris">Inactif</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grille-2" style="margin-top:20px;">
        <?php foreach ($capteurs as $c):
            $historique = $historiqueParCapteur[$c['id']] ?? [];
            $dernier = end($historique);
        ?>
            <div class="carte">
                <div class="carte-titre">
                    <h3><?= nettoyer($c['code_capteur']) ?> · <?= $libType[$c['type_capteur']] ?? $c['type_capteur'] ?></h3>
                    <span class="badge badge-bleu"><?= $dernier ? $dernier['valeur'] . ' ' . ($dernier['unite'] ?? '') : 'Aucune donnée' ?></span>
                </div>
                <?= genererSvgGraphique($historique) ?>
                <div style="margin-top:10px; color:var(--texte-attenue); font-size:0.8rem;">
                    Dernière mise à jour : <?= $dernier ? formaterDate($dernier['date_mesure']) : '—' ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
