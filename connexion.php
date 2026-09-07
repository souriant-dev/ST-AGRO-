<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
if (estConnecte()) { header('Location: ' . urlDashboard()); exit; }

$erreurs = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierCSRF($_POST['csrf'] ?? null)) {
        $erreurs[] = "Session expirée, merci de réessayer.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        $stmt = getPDO()->prepare('SELECT * FROM utilisateurs WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($motDePasse, $user['mot_de_passe'])) {
            $erreurs[] = "Adresse e-mail ou mot de passe incorrect.";
        } elseif ($user['statut'] === 'suspendu') {
            $erreurs[] = "Votre compte a été suspendu. Contactez un administrateur.";
        } else {
            connecterUtilisateur($user);
            enregistrerConnexion((int) $user['id']);
            header('Location: ' . urlDashboard($user['role']));
            exit;
        }
    }
}
$csrf = jetonCSRF();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Se connecter — ST-AGRO</title>
<link rel="stylesheet" href="assets/css/style.css?v=logo34">
</head>
<body>
<div class="page-auth">
    <div class="auth-illustration">
        <div class="logo"><img src="logo.png" alt="ST-AGRO"> ST-AGRO</div>
        <div>
            <p class="citation">« Vos parcelles vous parlent. ST-AGRO vous aide à les écouter. »</p>
            <p class="citation-auteur">— L'équipe ST-AGRO</p>
        </div>
    </div>
    <div class="auth-formulaire">
        <div class="auth-boite">
            <h1>Bon retour</h1>
            <p>Connectez-vous à votre espace ST-AGRO.</p>

            <?php afficherMessage(); ?>
            <?php foreach ($erreurs as $e): ?>
                <div class="alerte alerte-erreur"><?= nettoyer($e) ?></div>
            <?php endforeach; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="champ">
                    <label for="email">Adresse e-mail</label>
                    <input type="email" id="email" name="email" value="<?= nettoyer($email) ?>" required autofocus>
                </div>
                <div class="champ">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" required>
                </div>
                <button type="submit" class="btn btn-primaire btn-bloc btn-large">Se connecter</button>
            </form>
            <p class="auth-lien-bas">Pas encore de compte ? <a href="inscription.php">S'inscrire</a></p>
            <p class="auth-lien-bas" style="margin-top:6px;">
                <small>Démo : agriculteur@st-agro.cm · agronome@st-agro.cm · admin@st-agro.cm — mot de passe <code>Password123</code></small>
            </p>
        </div>
    </div>
</div>
</body>
</html>
