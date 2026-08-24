<?php
/**
 * Attend en entrée : $titrePage, $filAriane, $pageActive
 * Nécessite : includes/auth.php déjà chargé + exigerRole() déjà appelé
 */
$user = utilisateurCourant();
$role = $user['role'];
enregistrerVisite((int) $user['id'], basename($_SERVER['SCRIPT_NAME'] ?? 'page inconnue'));
$nbNotifs = compterNotificationsNonLues($user['id']);
$initiales = mb_strtoupper(mb_substr($user['prenom'], 0, 1) . mb_substr($user['nom'], 0, 1));

$liensParRole = [
    'agriculteur' => [
        ['icone' => '&#9679;', 'texte' => 'Tableau de bord', 'href' => '/st-agro/agriculteur/dashboard.php', 'cle' => 'dashboard'],
        ['icone' => '&#127806;', 'texte' => 'Mes exploitations', 'href' => '/st-agro/agriculteur/exploitations.php', 'cle' => 'exploitations'],
        ['icone' => '&#128225;', 'texte' => 'Capteurs & terrain', 'href' => '/st-agro/agriculteur/capteurs.php', 'cle' => 'capteurs'],
        ['icone' => '&#127780;', 'texte' => 'Météo', 'href' => '/st-agro/agriculteur/meteo.php', 'cle' => 'meteo'],
        ['icone' => '&#129717;', 'texte' => 'Analyse phytosanitaire', 'href' => '/st-agro/agriculteur/phytosanitaire.php', 'cle' => 'phyto'],
        ['icone' => '&#128276;', 'texte' => 'Alertes', 'href' => '/st-agro/agriculteur/alertes.php', 'cle' => 'alertes'],
        ['icone' => '&#128172;', 'texte' => 'Demander conseil à l’IA', 'href' => '/st-agro/agriculteur/assistant_ia.php', 'cle' => 'assistant_ia'],
        ['icone' => '&#128483;', 'texte' => 'Chat', 'href' => '/st-agro/agriculteur/chat.php', 'cle' => 'chat'],
        ['icone' => '&#128276;', 'texte' => 'Notifications', 'href' => '/st-agro/agriculteur/notifications.php', 'cle' => 'notifications'],
    ],
    'agronome' => [
        ['icone' => '&#9679;', 'texte' => 'Tableau de bord', 'href' => '/st-agro/agronome/dashboard.php', 'cle' => 'dashboard'],
        ['icone' => '&#127806;', 'texte' => 'Exploitations suivies', 'href' => '/st-agro/agronome/exploitations.php', 'cle' => 'exploitations'],
        ['icone' => '&#128172;', 'texte' => 'Demandes de conseil', 'href' => '/st-agro/agronome/conseils.php', 'cle' => 'conseils'],
        ['icone' => '&#129717;', 'texte' => 'Analyses phytosanitaires', 'href' => '/st-agro/agronome/phytosanitaire.php', 'cle' => 'phyto'],
        ['icone' => '&#128276;', 'texte' => 'Notifications', 'href' => '/st-agro/agronome/notifications.php', 'cle' => 'notifications'],
    ],
    'administrateur' => [
        ['icone' => '&#9679;', 'texte' => 'Tableau de bord', 'href' => '/st-agro/admin/dashboard.php', 'cle' => 'dashboard'],
        ['icone' => '&#128101;', 'texte' => 'Comptes utilisateurs', 'href' => '/st-agro/admin/utilisateurs.php', 'cle' => 'utilisateurs'],
        ['icone' => '&#127806;', 'texte' => 'Exploitations', 'href' => '/st-agro/admin/exploitations.php', 'cle' => 'exploitations'],
        ['icone' => '&#128225;', 'texte' => 'État des capteurs', 'href' => '/st-agro/admin/capteurs.php', 'cle' => 'capteurs'],
        ['icone' => '&#128202;', 'texte' => 'Statistiques globales', 'href' => '/st-agro/admin/statistiques.php', 'cle' => 'statistiques'],
    ],
];
$libRole = ['agriculteur' => 'Agriculteur', 'agronome' => 'Agronome', 'administrateur' => 'Administrateur'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= nettoyer($titrePage) ?> — ST-AGRO</title>
<link rel="stylesheet" href="/st-agro/includes/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="barre-laterale">
        <div class="logo"><span class="pastille"></span> ST-AGRO</div>
        <ul class="nav-app">
            <?php foreach ($liensParRole[$role] as $lien): ?>
            <li><a href="<?= $lien['href'] ?>" class="<?= $pageActive === $lien['cle'] ? 'actif' : '' ?>">
                <span class="icone"><?= $lien['icone'] ?></span> <?= $lien['texte'] ?>
            </a></li>
            <?php endforeach; ?>
        </ul>
        <div class="barre-laterale-bas">
            <a href="/st-agro/profil.php"><span class="icone">&#9881;</span> Mon profil</a>
            <a href="/st-agro/deconnexion.php"><span class="icone">&#10148;</span> Déconnexion</a>
        </div>
    </aside>

    <div class="zone-app">
        <header class="barre-superieure">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="menu-burger-app" aria-label="Ouvrir le menu">&#9776;</button>
                <div class="fil-ariane"><strong><?= $libRole[$role] ?></strong> / <?= nettoyer($filAriane) ?></div>
            </div>
            <div class="barre-superieure-actions">
                <a href="/st-agro/<?= $role === 'administrateur' ? 'admin' : $role ?>/notifications.php" class="cloche-notif" aria-label="Notifications">
                    &#128276;
                    <?php if ($nbNotifs > 0): ?><span class="point"><?= $nbNotifs ?></span><?php endif; ?>
                </a>
                <a href="/st-agro/profil.php" class="mini-profil">
                    <div class="avatar"><?= $initiales ?></div>
                    <div>
                        <div class="nom"><?= nettoyer($user['prenom'] . ' ' . $user['nom']) ?></div>
                        <div class="role"><?= $libRole[$role] ?></div>
                    </div>
                </a>
            </div>
        </header>
        <main class="contenu-app">
            <?php afficherMessage(); ?>
