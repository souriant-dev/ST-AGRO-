<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['administrateur']);
$user = utilisateurCourant();
$pdo = getPDO();

$stmt = $pdo->query("SELECT role, COUNT(*) AS total FROM utilisateurs GROUP BY role");
$parRole = ['agriculteur' => 0, 'agronome' => 0, 'administrateur' => 0];
foreach ($stmt->fetchAll() as $r) { $parRole[$r['role']] = (int) $r['total']; }

$totalExploitations = (int) $pdo->query('SELECT COUNT(*) FROM exploitations')->fetchColumn();
$totalCapteurs = (int) $pdo->query('SELECT COUNT(*) FROM capteurs')->fetchColumn();
$totalAlertes = (int) $pdo->query("SELECT COUNT(*) FROM alertes WHERE niveau = 'critique'")->fetchColumn();

$stmt = $pdo->query('SELECT nom, prenom, email, role, date_creation FROM utilisateurs ORDER BY date_creation DESC LIMIT 6');
$derniersInscrits = $stmt->fetchAll();

$titrePage = 'Tableau de bord';
$filAriane = 'Tableau de bord';
$pageActive = 'dashboard';
require __DIR__ . '/../includes/layout_debut.php';
$libRole = ['agriculteur' => 'Agriculteur', 'agronome' => 'Agronome', 'administrateur' => 'Administrateur'];
?>
<h1>Vue d'ensemble</h1>
<p class="sous-titre-page">Activité globale de la plateforme ST-AGRO.</p>

<div class="grille-stats">
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone bleu">&#128101;</span></div>
        <div class="valeur"><?= array_sum($parRole) ?></div>
        <div class="libelle">Utilisateurs inscrits</div>
    </div>
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone vert">&#127806;</span></div>
        <div class="valeur"><?= $totalExploitations ?></div>
        <div class="libelle">Exploitations</div>
    </div>
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone bleu">&#128225;</span></div>
        <div class="valeur"><?= $totalCapteurs ?></div>
        <div class="libelle">Capteurs déployés</div>
    </div>
    <div class="stat-carte">
        <div class="stat-tete"><span class="stat-icone rouge">&#9888;&#65039;</span></div>
        <div class="valeur"><?= $totalAlertes ?></div>
        <div class="libelle">Alertes critiques</div>
    </div>
</div>

<div class="grille-2">
    <div class="carte">
        <div class="carte-titre">
            <h3>Derniers inscrits</h3>
            <a href="/st-agro/admin/utilisateurs.php" class="btn btn-contour btn-sm">Gérer les comptes</a>
        </div>
        <div class="table-wrap">
            <table class="table-app">
                <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Inscrit le</th></tr></thead>
                <tbody>
                <?php foreach ($derniersInscrits as $u): ?>
                    <tr>
                        <td><?= nettoyer($u['prenom'] . ' ' . $u['nom']) ?></td>
                        <td><?= nettoyer($u['email']) ?></td>
                        <td><span class="badge badge-bleu"><?= $libRole[$u['role']] ?></span></td>
                        <td><?= formaterDate($u['date_creation']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="carte">
        <div class="carte-titre"><h3>Répartition des comptes</h3></div>
        <div class="capteur-item"><div class="capteur-nom">Agriculteurs</div><div class="capteur-valeur"><?= $parRole['agriculteur'] ?></div></div>
        <div class="capteur-item"><div class="capteur-nom">Agronomes</div><div class="capteur-valeur"><?= $parRole['agronome'] ?></div></div>
        <div class="capteur-item"><div class="capteur-nom">Administrateurs</div><div class="capteur-valeur"><?= $parRole['administrateur'] ?></div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
