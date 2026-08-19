<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
if (estConnecte()) { header('Location: ' . urlDashboard()); exit; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ST-AGRO — L'agriculture pilotée par la donnée</title>
<meta name="description" content="ST-AGRO connecte capteurs, météo et agronomes pour aider les agriculteurs à décider plus vite.">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="entete-publique">
    <div class="conteneur">
        <div class="logo"><span class="pastille"></span> ST-AGRO</div>
        <nav class="nav-publique">
            <a href="#fonctionnalites">Fonctionnalités</a>
            <a href="#roles">Pour qui ?</a>
            <a href="#comment">Comment ça marche</a>
            <div class="nav-actions">
                <a href="connexion.php" class="btn btn-contour btn-sm">Se connecter</a>
                <a href="inscription.php" class="btn btn-primaire btn-sm">S'inscrire</a>
            </div>
        </nav>
        <button class="burger" aria-label="Ouvrir le menu">&#9776;</button>
    </div>
</header>

<section class="hero">
    <div class="conteneur">
        <div>
            <span class="hero-eyebrow">&#127806; Plateforme agro-intelligente</span>
            <h1>Faites parler <span>chaque parcelle</span> avant qu'elle ne parle d'elle-même.</h1>
            <p class="lead">ST-AGRO relie vos capteurs de terrain, la météo, le diagnostic phytosanitaire par IA et vos agronomes de confiance dans un seul tableau de bord.</p>
            <div class="hero-actions">
                <a href="inscription.php" class="btn btn-primaire btn-large">Créer un compte gratuit</a>
                <a href="#comment" class="btn btn-contour btn-large">Voir comment ça marche</a>
            </div>
        </div>
        <div class="gauge-visuel">
            <div class="gauge-anneau">
                <div class="gauge-centre">
                    <div class="valeur">78%</div>
                    <div class="libelle">Indice de santé — Parcelle Nord</div>
                </div>
            </div>
            <div class="gauge-flottant f1">&#128167; Humidité 42%</div>
            <div class="gauge-flottant f2">&#127777;&#65039; 27.3°C</div>
        </div>
    </div>
</section>

<section class="section" id="fonctionnalites">
    <div class="conteneur">
        <div class="section-titre">
            <span class="eyebrow">Fonctionnalités</span>
            <h2>Tout ce qu'il faut pour piloter une exploitation</h2>
            <p>Des données de terrain à la recommandation concrète, sans changer d'outil.</p>
        </div>
        <div class="grille-fonctions">
            <div class="fonction-carte">
                <div class="fonction-icone">&#127780;</div>
                <h4>Météo en temps réel</h4>
                <p>Prévisions localisées par exploitation pour anticiper irrigation et traitements.</p>
            </div>
            <div class="fonction-carte">
                <div class="fonction-icone">&#128225;</div>
                <h4>Capteurs IoT</h4>
                <p>Humidité du sol, température, pluviométrie : vos capteurs transmettent en continu.</p>
            </div>
            <div class="fonction-carte">
                <div class="fonction-icone">&#129717;</div>
                <h4>Diagnostic phytosanitaire</h4>
                <p>Analyse assistée par IA des maladies et parasites, validée par un agronome.</p>
            </div>
            <div class="fonction-carte">
                <div class="fonction-icone">&#128202;</div>
                <h4>Prédictions de rendement</h4>
                <p>Des modèles s'appuient sur l'historique de vos parcelles pour anticiper la récolte.</p>
            </div>
            <div class="fonction-carte">
                <div class="fonction-icone">&#128276;</div>
                <h4>Alertes automatiques</h4>
                <p>Notifications dès qu'un seuil critique est franchi sur une exploitation.</p>
            </div>
            <div class="fonction-carte">
                <div class="fonction-icone">&#128172;</div>
                <h4>Conseil d'agronome</h4>
                <p>Posez une question, échangez directement avec un agronome de la plateforme.</p>
            </div>
        </div>
    </div>
</section>

<section class="section roles-bande" id="roles">
    <div class="conteneur">
        <div class="section-titre">
            <span class="eyebrow">Pour qui ?</span>
            <h2>Un espace pensé pour chaque rôle</h2>
            <p>Chaque utilisateur retrouve exactement les outils dont il a besoin.</p>
        </div>
        <div class="grille-roles">
            <div class="role-carte">
                <span class="badge badge-bleu">Agriculteur</span>
                <h4>Gérez vos exploitations</h4>
                <p>Suivez vos capteurs, consultez la météo, recevez des alertes et demandez conseil à un agronome.</p>
            </div>
            <div class="role-carte">
                <span class="badge badge-bleu">Agronome</span>
                <h4>Accompagnez les agriculteurs</h4>
                <p>Consultez les données des exploitations suivies, répondez aux demandes de conseil et validez les diagnostics.</p>
            </div>
            <div class="role-carte">
                <span class="badge badge-bleu">Administrateur</span>
                <h4>Pilotez la plateforme</h4>
                <p>Gérez les comptes utilisateurs et consultez les statistiques globales d'usage.</p>
            </div>
        </div>
    </div>
</section>

<section class="section" id="comment">
    <div class="conteneur">
        <div class="section-titre">
            <span class="eyebrow">Comment ça marche</span>
            <h2>Trois étapes pour démarrer</h2>
        </div>
        <div class="grille-fonctions">
            <div class="fonction-carte">
                <div class="fonction-icone">1</div>
                <h4>Créez votre compte</h4>
                <p>Inscrivez-vous en tant qu'agriculteur ou agronome en quelques secondes.</p>
            </div>
            <div class="fonction-carte">
                <div class="fonction-icone">2</div>
                <h4>Ajoutez une exploitation</h4>
                <p>Renseignez votre parcelle, sa culture et, si disponible, vos capteurs.</p>
            </div>
            <div class="fonction-carte">
                <div class="fonction-icone">3</div>
                <h4>Suivez et décidez</h4>
                <p>Tableau de bord, alertes et conseils d'agronomes vous aident à agir au bon moment.</p>
            </div>
        </div>
    </div>
</section>

<footer class="pied-page">
    <div class="conteneur">
        <div class="logo"><span class="pastille"></span> ST-AGRO</div>
        <div class="pied-liens">
            <a href="#fonctionnalites">Fonctionnalités</a>
            <a href="connexion.php">Se connecter</a>
            <a href="inscription.php">S'inscrire</a>
        </div>
        <span>&copy; <?= date('Y') ?> ST-AGRO</span>
    </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
