<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
exigerConnexion();
$user = utilisateurCourant();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCSRF($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'maj_profil') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        if ($nom === '' || $prenom === '') {
            definirMessage('erreur', 'Le nom et le prénom sont obligatoires.');
        } else {
            $stmt = getPDO()->prepare('UPDATE utilisateurs SET nom = ?, prenom = ?, telephone = ?, ville = ? WHERE id = ?');
            $stmt->execute([$nom, $prenom, $telephone, $ville, $user['id']]);
            definirMessage('succes', 'Profil mis à jour.');
        }
    } elseif ($action === 'maj_mdp') {
        $actuel = $_POST['mdp_actuel'] ?? '';
        $nouveau = $_POST['mdp_nouveau'] ?? '';
        $confirmation = $_POST['mdp_confirmation'] ?? '';
        $stmt = getPDO()->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hashActuel = $stmt->fetchColumn();

        if (!password_verify($actuel, $hashActuel)) {
            definirMessage('erreur', 'Mot de passe actuel incorrect.');
        } elseif (strlen($nouveau) < 8) {
            definirMessage('erreur', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
        } elseif ($nouveau !== $confirmation) {
            definirMessage('erreur', 'Les mots de passe ne correspondent pas.');
        } else {
            $stmt = getPDO()->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?');
            $stmt->execute([password_hash($nouveau, PASSWORD_DEFAULT), $user['id']]);
            definirMessage('succes', 'Mot de passe modifié avec succès.');
        }
    }
    header('Location: /st-agro/profil.php');
    exit;
}

$titrePage = 'Mon profil';
$filAriane = 'Mon profil';
$pageActive = '';
require __DIR__ . '/includes/layout_debut.php';
$csrf = jetonCSRF();
$initiales = mb_strtoupper(mb_substr($user['prenom'], 0, 1) . mb_substr($user['nom'], 0, 1));
$libRole = ['agriculteur' => 'Agriculteur', 'agronome' => 'Agronome', 'administrateur' => 'Administrateur'];
?>
<h1>Mon profil</h1>
<p class="sous-titre-page">Gérez vos informations personnelles et votre sécurité.</p>

<div class="grille-2">
    <div class="carte">
        <div class="carte-titre"><h3>Informations personnelles</h3></div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="maj_profil">
            <div class="champ-groupe">
                <div class="champ">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" value="<?= nettoyer($user['prenom']) ?>" required>
                </div>
                <div class="champ">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" value="<?= nettoyer($user['nom']) ?>" required>
                </div>
            </div>
            <div class="champ">
                <label>Adresse e-mail</label>
                <input type="email" value="<?= nettoyer($user['email']) ?>" disabled>
                <small>L'e-mail ne peut pas être modifié.</small>
            </div>
            <div class="champ-groupe">
                <div class="champ">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" value="<?= nettoyer((string)$user['telephone']) ?>">
                </div>
                <div class="champ">
                    <label for="ville">Ville</label>
                    <input type="text" id="ville" name="ville" value="<?= nettoyer((string)$user['ville']) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primaire">Enregistrer les modifications</button>
        </form>

        <hr style="border:none; border-top:1px solid var(--bordure); margin:26px 0;">

        <div class="carte-titre"><h3>Changer de mot de passe</h3></div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="maj_mdp">
            <div class="champ">
                <label for="mdp_actuel">Mot de passe actuel</label>
                <input type="password" id="mdp_actuel" name="mdp_actuel" required>
            </div>
            <div class="champ-groupe">
                <div class="champ">
                    <label for="mdp_nouveau">Nouveau mot de passe</label>
                    <input type="password" id="mdp_nouveau" name="mdp_nouveau" required minlength="8">
                </div>
                <div class="champ">
                    <label for="mdp_confirmation">Confirmation</label>
                    <input type="password" id="mdp_confirmation" name="mdp_confirmation" required minlength="8">
                </div>
            </div>
            <button type="submit" class="btn btn-contour">Mettre à jour le mot de passe</button>
        </form>
    </div>

    <div class="carte" style="text-align:center; height:fit-content;">
        <div class="avatar" style="width:84px; height:84px; font-size:1.8rem; margin:0 auto 16px;"><?= $initiales ?></div>
        <h3><?= nettoyer($user['prenom'] . ' ' . $user['nom']) ?></h3>
        <p style="color:var(--texte-attenue); font-size:0.9rem; margin:4px 0 14px;"><?= nettoyer($user['email']) ?></p>
        <span class="badge badge-bleu"><?= $libRole[$user['role']] ?></span>
        <p style="margin-top:18px; font-size:0.85rem; color:var(--texte-attenue);">
            Membre depuis <?= isset($user['date_creation']) ? formaterDate($user['date_creation']) : '—' ?>
        </p>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_fin.php'; ?>
