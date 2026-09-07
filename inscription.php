<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
if (estConnecte()) { header('Location: ' . urlDashboard()); exit; }

$erreurs = [];
$valeurs = ['nom' => '', 'prenom' => '', 'email' => '', 'telephone' => '', 'ville' => '', 'role' => 'agriculteur'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierCSRF($_POST['csrf'] ?? null)) {
        $erreurs[] = "Session expirée, merci de réessayer.";
    } else {
        $valeurs['nom'] = trim($_POST['nom'] ?? '');
        $valeurs['prenom'] = trim($_POST['prenom'] ?? '');
        $valeurs['email'] = trim($_POST['email'] ?? '');
        $valeurs['telephone'] = trim($_POST['telephone'] ?? '');
        $valeurs['ville'] = trim($_POST['ville'] ?? '');
        $valeurs['role'] = in_array($_POST['role'] ?? '', ['agriculteur', 'agronome', 'administrateur'], true) ? $_POST['role'] : 'agriculteur';
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';

        if ($valeurs['nom'] === '' || $valeurs['prenom'] === '') $erreurs[] = "Le nom et le prénom sont obligatoires.";
        if (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) $erreurs[] = "Adresse e-mail invalide.";
        if (strlen($motDePasse) < 8) $erreurs[] = "Le mot de passe doit contenir au moins 8 caractères.";
        if ($motDePasse !== $confirmation) $erreurs[] = "Les mots de passe ne correspondent pas.";

        if (!$erreurs) {
            $stmt = getPDO()->prepare('SELECT id FROM utilisateurs WHERE email = ?');
            $stmt->execute([$valeurs['email']]);
            if ($stmt->fetch()) {
                $erreurs[] = "Un compte existe déjà avec cette adresse e-mail.";
            }
        }

        if (!$erreurs) {
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $stmt = getPDO()->prepare('INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, role, ville) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$valeurs['nom'], $valeurs['prenom'], $valeurs['email'], $valeurs['telephone'], $hash, $valeurs['role'], $valeurs['ville']]);
            definirMessage('succes', 'Compte créé avec succès. Vous pouvez vous connecter.');
            header('Location: connexion.php');
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
<title>Créer un compte — ST-AGRO</title>
<link rel="stylesheet" href="assets/css/style.css?v=logo34">
</head>
<body>
<div class="page-auth">
    <div class="auth-illustration">
        <div class="logo"><img src="logo.png" alt="ST-AGRO"> ST-AGRO</div>
        <div>
            <p class="citation">« Une décision agricole prise à temps vaut mieux qu'une récolte sauvée in extremis. »</p>
            <p class="citation-auteur">— L'équipe ST-AGRO</p>
        </div>
    </div>
    <div class="auth-formulaire">
        <div class="auth-boite">
            <h1>Créer votre compte</h1>
            <p>Rejoignez ST-AGRO en tant qu'agriculteur ou agronome.</p>

            <?php foreach ($erreurs as $e): ?>
                <div class="alerte alerte-erreur"><?= nettoyer($e) ?></div>
            <?php endforeach; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="champ-groupe">
                    <div class="champ">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" value="<?= nettoyer($valeurs['prenom']) ?>" required>
                    </div>
                    <div class="champ">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" value="<?= nettoyer($valeurs['nom']) ?>" required>
                    </div>
                </div>
                <div class="champ">
                    <label for="email">Adresse e-mail</label>
                    <input type="email" id="email" name="email" value="<?= nettoyer($valeurs['email']) ?>" required>
                </div>
                <div class="champ-groupe">
                    <div class="champ">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" value="<?= nettoyer($valeurs['telephone']) ?>">
                    </div>
                    <div class="champ">
                        <label for="ville">Ville</label>
                        <input type="text" id="ville" name="ville" value="<?= nettoyer($valeurs['ville']) ?>">
                    </div>
                </div>
                <div class="champ">
                    <label for="role">Je suis</label>
                    <select id="role" name="role">
                        <option value="agriculteur" <?= $valeurs['role'] === 'agriculteur' ? 'selected' : '' ?>>Agriculteur</option>
                        <option value="agronome" <?= $valeurs['role'] === 'agronome' ? 'selected' : '' ?>>Agronome</option>
                        <option value="administrateur" <?= $valeurs['role'] === 'administrateur' ? 'selected' : '' ?>>Administrateur</option>
                    </select>
                </div>
                <div class="champ-groupe">
                    <div class="champ">
                        <label for="mot_de_passe">Mot de passe</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="8">
                        <small>8 caractères minimum</small>
                    </div>
                    <div class="champ">
                        <label for="confirmation">Confirmation</label>
                        <input type="password" id="confirmation" name="confirmation" required minlength="8">
                    </div>
                </div>
                <button type="submit" class="btn btn-primaire btn-bloc btn-large">Créer mon compte</button>
            </form>
            <p class="auth-lien-bas">Déjà un compte ? <a href="connexion.php">Se connecter</a></p>
        </div>
    </div>
</div>
</body>
</html>
