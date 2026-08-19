<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['administrateur']);
$user = utilisateurCourant();
$pdo = getPDO();
ensureAffectationsAgronomesTableExists();
$erreursAjout = [];
$erreursModification = [];
$utilisateurEdition = null;
$exploitationsAjoutIds = array_values(array_filter(array_map('intval', (array) ($_POST['exploitation_ids'] ?? []))));
$exploitationsModificationIds = array_values(array_filter(array_map('intval', (array) ($_POST['exploitation_modification_ids'] ?? []))));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCSRF($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'ajouter') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['agriculteur', 'agronome', 'administrateur'], true) ? $_POST['role'] : 'agriculteur';
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';

        if ($role === 'agronome' && !$exploitationsAjoutIds) {
            $erreursAjout[] = 'Sélectionnez au moins une exploitation à affecter à cet agronome.';
        }

        if ($nom === '' || $prenom === '') {
            $erreursAjout[] = 'Le nom et le prénom sont obligatoires.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreursAjout[] = 'Adresse e-mail invalide.';
        }
        if (strlen($motDePasse) < 8) {
            $erreursAjout[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($motDePasse !== $confirmation) {
            $erreursAjout[] = 'Les mots de passe ne correspondent pas.';
        }

        if (!$erreursAjout) {
            $check = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                $erreursAjout[] = 'Un compte existe déjà avec cette adresse e-mail.';
            }
        }

        if (!$erreursAjout) {
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, role, ville, statut) VALUES (?, ?, ?, ?, ?, ?, ?, "actif")');
                $stmt->execute([$nom, $prenom, $email, $telephone, $hash, $role, $ville]);
                $nouvelUtilisateurId = (int) $pdo->lastInsertId();
                if ($role === 'agronome') {
                    $stmtAffectation = $pdo->prepare('INSERT INTO affectations_agronomes (agronome_id, exploitation_id) VALUES (?, ?)');
                    foreach ($exploitationsAjoutIds as $exploitationId) {
                        $stmtAffectation->execute([$nouvelUtilisateurId, $exploitationId]);
                    }
                }
                $pdo->commit();
            } catch (PDOException $exception) {
                $pdo->rollBack();
                if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                    $erreursAjout[] = 'Cette exploitation est déjà affectée à un agronome.';
                } else {
                    throw $exception;
                }
            }
        }

        if (!$erreursAjout) {
            definirMessage('succes', 'Utilisateur ajouté avec succès.');
            header('Location: /st-agro/admin/utilisateurs.php');
            exit;
        }
    }

    if ($action === 'modifier') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['agriculteur', 'agronome', 'administrateur'], true) ? $_POST['role'] : 'agriculteur';
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';

        if ($id === (int) $user['id']) {
            $stmtRole = $pdo->prepare('SELECT role FROM utilisateurs WHERE id = ?');
            $stmtRole->execute([$id]);
            $role = (string) $stmtRole->fetchColumn();
        }
        if ($role === 'agronome' && !$exploitationsModificationIds) {
            $erreursModification[] = 'Sélectionnez au moins une exploitation à affecter à cet agronome.';
        }

        if ($id <= 0) {
            $erreursModification[] = 'Utilisateur introuvable.';
        }
        if ($nom === '' || $prenom === '') {
            $erreursModification[] = 'Le nom et le prénom sont obligatoires.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreursModification[] = 'Adresse e-mail invalide.';
        }
        if ($motDePasse !== '' && strlen($motDePasse) < 8) {
            $erreursModification[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($motDePasse !== $confirmation) {
            $erreursModification[] = 'Les mots de passe ne correspondent pas.';
        }

        if (!$erreursModification) {
            $check = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ? AND id <> ?');
            $check->execute([$email, $id]);
            if ($check->fetch()) {
                $erreursModification[] = 'Un autre compte utilise déjà cette adresse e-mail.';
            }
        }

        if (!$erreursModification) {
            $champs = 'nom = ?, prenom = ?, email = ?, telephone = ?, ville = ?, role = ?';
            $parametres = [$nom, $prenom, $email, $telephone, $ville, $role];
            if ($motDePasse !== '') {
                $champs .= ', mot_de_passe = ?';
                $parametres[] = password_hash($motDePasse, PASSWORD_DEFAULT);
            }

            $parametres[] = $id;
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE utilisateurs SET $champs WHERE id = ?")->execute($parametres);
                $pdo->prepare('DELETE FROM affectations_agronomes WHERE agronome_id = ?')->execute([$id]);
                if ($role === 'agronome') {
                    $stmtAffectation = $pdo->prepare('INSERT INTO affectations_agronomes (agronome_id, exploitation_id) VALUES (?, ?)');
                    foreach ($exploitationsModificationIds as $exploitationId) {
                        $stmtAffectation->execute([$id, $exploitationId]);
                    }
                }
                $pdo->commit();
            } catch (PDOException $exception) {
                $pdo->rollBack();
                if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                    $erreursModification[] = 'Cette exploitation est déjà affectée à un autre agronome.';
                } else {
                    throw $exception;
                }
            }
        }

        if (!$erreursModification) {
            definirMessage('succes', 'Compte utilisateur modifié avec succès.');
            header('Location: /st-agro/admin/utilisateurs.php');
            exit;
        }

        $utilisateurEdition = array_merge($_POST, ['id' => $id, 'role' => $role]);
    }

    if ($action !== 'ajouter' && $action !== 'modifier') {
        if ($id === (int) $user['id']) {
            definirMessage('erreur', 'Vous ne pouvez pas modifier votre propre statut.');
        } elseif ($action === 'suspendre') {
            $pdo->prepare("UPDATE utilisateurs SET statut = 'suspendu' WHERE id = ?")->execute([$id]);
            definirMessage('succes', 'Compte suspendu.');
        } elseif ($action === 'reactiver') {
            $pdo->prepare("UPDATE utilisateurs SET statut = 'actif' WHERE id = ?")->execute([$id]);
            definirMessage('succes', 'Compte réactivé.');
        } elseif ($action === 'supprimer') {
            $pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$id]);
            definirMessage('succes', 'Compte supprimé.');
        } elseif ($action === 'changer_role') {
            $role = in_array($_POST['role'] ?? '', ['agriculteur', 'agronome', 'administrateur'], true) ? $_POST['role'] : null;
            if ($role) {
                $pdo->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?')->execute([$role, $id]);
                definirMessage('succes', 'Rôle mis à jour.');
            }
        }
        header('Location: /st-agro/admin/utilisateurs.php');
        exit;
    }
}

$recherche = trim($_GET['q'] ?? '');
if ($recherche !== '') {
    $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? ORDER BY date_creation DESC');
    $like = '%' . $recherche . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM utilisateurs ORDER BY date_creation DESC');
}
$utilisateurs = $stmt->fetchAll();
$idEdition = (int) ($_GET['modifier'] ?? 0);
$stmtExploitations = $pdo->prepare("SELECT e.id, e.nom, u.nom AS agriculteur_nom, u.prenom AS agriculteur_prenom
    FROM exploitations e
    JOIN utilisateurs u ON u.id = e.agriculteur_id
    LEFT JOIN affectations_agronomes aa ON aa.exploitation_id = e.id
    WHERE aa.id IS NULL OR aa.agronome_id = ? ORDER BY e.nom");
$stmtExploitations->execute([$idEdition]);
$exploitationsDisponibles = $stmtExploitations->fetchAll();

if ($idEdition > 0 && $utilisateurEdition === null) {
    $stmtEdition = $pdo->prepare('SELECT u.id, u.nom, u.prenom, u.email, u.telephone, u.ville, u.role
        FROM utilisateurs u LEFT JOIN affectations_agronomes aa ON aa.agronome_id = u.id WHERE u.id = ?');
    $stmtEdition->execute([$idEdition]);
    $utilisateurEdition = $stmtEdition->fetch() ?: null;
}

if ($utilisateurEdition && ($utilisateurEdition['role'] ?? '') === 'agronome' && !$exploitationsModificationIds) {
    $stmtAffectations = $pdo->prepare('SELECT exploitation_id FROM affectations_agronomes WHERE agronome_id = ?');
    $stmtAffectations->execute([(int) $utilisateurEdition['id']]);
    $exploitationsModificationIds = array_map('intval', $stmtAffectations->fetchAll(PDO::FETCH_COLUMN));
}

$titrePage = 'Comptes utilisateurs';
$filAriane = 'Comptes utilisateurs';
$pageActive = 'utilisateurs';
require __DIR__ . '/../includes/layout_debut.php';
$csrf = jetonCSRF();
$libRole = ['agriculteur' => 'Agriculteur', 'agronome' => 'Agronome', 'administrateur' => 'Administrateur'];
?>
<h1>Comptes utilisateurs</h1>
<p class="sous-titre-page">Gérez les rôles et l'accès des utilisateurs de la plateforme.</p>

<div style="margin-bottom:22px;">
    <button type="button" class="btn btn-primaire" id="bouton_ajouter_utilisateur" aria-expanded="false" aria-controls="formulaire_ajout_utilisateur">
        Ajouter un utilisateur
    </button>

    <div class="carte" id="formulaire_ajout_utilisateur" hidden style="margin-top:16px;">
    <h2 style="margin-top:0;">Ajouter un utilisateur</h2>

    <?php if (!empty($erreursAjout)): ?>
        <div class="alerte alerte-erreur" style="margin-bottom:16px;">
            <ul style="margin:0; padding-left:18px;">
                <?php foreach ($erreursAjout as $erreur): ?>
                    <li><?= nettoyer($erreur) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; align-items:end;">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="ajouter">

        <div>
            <label for="admin_nom">Nom</label>
            <input id="admin_nom" type="text" name="nom" required style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;">
        </div>

        <div>
            <label for="admin_prenom">Prénom</label>
            <input id="admin_prenom" type="text" name="prenom" required style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;">
        </div>

        <div>
            <label for="admin_email">E-mail</label>
            <input id="admin_email" type="email" name="email" required style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;">
        </div>

        <div>
            <label for="admin_telephone">Téléphone</label>
            <input id="admin_telephone" type="tel" name="telephone" style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;">
        </div>

        <div>
            <label for="admin_ville">Ville</label>
            <input id="admin_ville" type="text" name="ville" style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;">
        </div>

        <div>
            <label for="admin_role">Rôle</label>
            <select id="admin_role" name="role" onchange="afficherChampExploitation()" style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;">
                <option value="agriculteur">Agriculteur</option>
                <option value="agronome">Agronome</option>
                <option value="administrateur">Administrateur</option>
            </select>
        </div>

        <div id="bloc_exploitation_agronome" hidden>
            <span style="display:block; margin-bottom:8px; font-weight:600;">Exploitations à affecter</span>
            <div style="display:grid; gap:8px; max-height:220px; overflow-y:auto; padding:10px; border:1px solid var(--bordure); border-radius:8px; background:#fff;">
                <?php foreach ($exploitationsDisponibles as $exploitation): ?>
                    <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer;">
                        <input type="checkbox" name="exploitation_ids[]" value="<?= (int) $exploitation['id'] ?>" <?= in_array((int) $exploitation['id'], $exploitationsAjoutIds, true) ? 'checked' : '' ?>>
                        <span><?= nettoyer($exploitation['nom'] . ' — ' . $exploitation['agriculteur_prenom'] . ' ' . $exploitation['agriculteur_nom']) ?></span>
                    </label>
                <?php endforeach; ?>
                <?php if (!$exploitationsDisponibles): ?><small style="color:var(--texte-attenue);">Aucune exploitation disponible.</small><?php endif; ?>
            </div>
        </div>

        <div>
            <label for="admin_mot_de_passe">Mot de passe</label>
            <input id="admin_mot_de_passe" type="password" name="mot_de_passe" required minlength="8" style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;">
        </div>

        <div>
            <label for="admin_confirmation">Confirmation</label>
            <input id="admin_confirmation" type="password" name="confirmation" required minlength="8" style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;">
        </div>

        <div>
            <button type="submit" class="btn btn-primaire">Ajouter</button>
        </div>
    </form>
    </div>
</div>

<?php if ($utilisateurEdition): ?>
<div class="carte" style="margin-bottom:22px;">
    <h2 style="margin-top:0;">Modifier le compte de <?= nettoyer(($utilisateurEdition['prenom'] ?? '') . ' ' . ($utilisateurEdition['nom'] ?? '')) ?></h2>

    <?php if (!empty($erreursModification)): ?>
        <div class="alerte alerte-erreur" style="margin-bottom:16px;">
            <ul style="margin:0; padding-left:18px;">
                <?php foreach ($erreursModification as $erreur): ?>
                    <li><?= nettoyer($erreur) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; align-items:end;">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="modifier">
        <input type="hidden" name="id" value="<?= (int) $utilisateurEdition['id'] ?>">

        <div><label for="mod_nom">Nom</label><input id="mod_nom" type="text" name="nom" value="<?= nettoyer($utilisateurEdition['nom'] ?? '') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;"></div>
        <div><label for="mod_prenom">Prénom</label><input id="mod_prenom" type="text" name="prenom" value="<?= nettoyer($utilisateurEdition['prenom'] ?? '') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;"></div>
        <div><label for="mod_email">E-mail</label><input id="mod_email" type="email" name="email" value="<?= nettoyer($utilisateurEdition['email'] ?? '') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;"></div>
        <div><label for="mod_telephone">Téléphone</label><input id="mod_telephone" type="tel" name="telephone" value="<?= nettoyer($utilisateurEdition['telephone'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;"></div>
        <div><label for="mod_ville">Ville</label><input id="mod_ville" type="text" name="ville" value="<?= nettoyer($utilisateurEdition['ville'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;"></div>
        <div>
            <label for="mod_role">Rôle</label>
            <select id="mod_role" name="role" onchange="afficherExploitationModification()" <?= (int) $utilisateurEdition['id'] === (int) $user['id'] ? 'disabled' : '' ?> style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;">
                <?php foreach ($libRole as $val => $lib): ?>
                    <option value="<?= $val ?>" <?= ($utilisateurEdition['role'] ?? '') === $val ? 'selected' : '' ?>><?= $lib ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ((int) $utilisateurEdition['id'] === (int) $user['id']): ?>
                <input type="hidden" name="role" value="<?= nettoyer($utilisateurEdition['role'] ?? '') ?>">
            <?php endif; ?>
        </div>
        <div id="bloc_exploitation_modification" hidden>
            <span style="display:block; margin-bottom:8px; font-weight:600;">Exploitations suivies</span>
            <div style="display:grid; gap:8px; max-height:220px; overflow-y:auto; padding:10px; border:1px solid var(--bordure); border-radius:8px; background:#fff;">
                <?php foreach ($exploitationsDisponibles as $exploitation): ?>
                    <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer;">
                        <input type="checkbox" name="exploitation_modification_ids[]" value="<?= (int) $exploitation['id'] ?>" <?= in_array((int) $exploitation['id'], $exploitationsModificationIds, true) ? 'checked' : '' ?>>
                        <span><?= nettoyer($exploitation['nom'] . ' — ' . $exploitation['agriculteur_prenom'] . ' ' . $exploitation['agriculteur_nom']) ?></span>
                    </label>
                <?php endforeach; ?>
                <?php if (!$exploitationsDisponibles): ?><small style="color:var(--texte-attenue);">Aucune exploitation disponible.</small><?php endif; ?>
            </div>
        </div>
        <div><label for="mod_mot_de_passe">Nouveau mot de passe</label><input id="mod_mot_de_passe" type="password" name="mot_de_passe" minlength="8" style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;"><small>Laisser vide pour conserver l'actuel.</small></div>
        <div><label for="mod_confirmation">Confirmation</label><input id="mod_confirmation" type="password" name="confirmation" minlength="8" style="width:100%; padding:10px 12px; border:1px solid var(--bordure); border-radius:8px;"></div>
        <div><button type="submit" class="btn btn-primaire">Enregistrer</button> <a href="/st-agro/admin/utilisateurs.php" class="btn btn-fantome">Annuler</a></div>
    </form>
</div>
<?php endif; ?>

<form method="get" style="margin-bottom:18px; max-width:420px;">
    <input type="text" name="q" placeholder="Rechercher par nom ou e-mail..." value="<?= nettoyer($recherche) ?>"
        style="width:100%; padding:12px 14px; border:1.5px solid var(--bordure); border-radius:8px; background:#fff;">
</form>

<div class="carte">
    <div class="table-wrap">
        <table class="table-app">
            <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($utilisateurs as $u): ?>
                <tr>
                    <td><?= nettoyer($u['prenom'] . ' ' . $u['nom']) ?></td>
                    <td><?= nettoyer($u['email']) ?></td>
                    <td>
                        <form method="post" style="display:inline-flex; gap:6px; align-items:center;">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="changer_role">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <select name="role" onchange="this.form.submit()" <?= $u['id']==$user['id']?'disabled':'' ?> style="padding:6px 8px; border-radius:6px; border:1px solid var(--bordure);">
                                <?php foreach ($libRole as $val => $lib): ?>
                                    <option value="<?= $val ?>" <?= $u['role']===$val?'selected':'' ?>><?= $lib ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td><?= $u['statut'] === 'actif' ? '<span class="badge badge-vert">Actif</span>' : '<span class="badge badge-rouge">Suspendu</span>' ?></td>
                    <td>
                        <a href="/st-agro/admin/utilisateurs.php?modifier=<?= (int) $u['id'] ?>" class="btn btn-fantome btn-sm">Modifier</a>
                        <?php if ($u['id'] != $user['id']): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <?php if ($u['statut'] === 'actif'): ?>
                                    <input type="hidden" name="action" value="suspendre">
                                    <button type="submit" class="btn btn-fantome btn-sm">Suspendre</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="reactiver">
                                    <button type="submit" class="btn btn-fantome btn-sm">Réactiver</button>
                                <?php endif; ?>
                            </form>
                            <form method="post" style="display:inline" onsubmit="return confirm('Supprimer définitivement ce compte ?');">
                                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-fantome btn-sm" style="color:var(--rouge-critique);">Supprimer</button>
                            </form>
                        <?php else: ?>
                            <small style="color:var(--texte-attenue);">Vous</small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
<script>
    const boutonAjouterUtilisateur = document.getElementById('bouton_ajouter_utilisateur');
    const formulaireAjoutUtilisateur = document.getElementById('formulaire_ajout_utilisateur');
    const roleUtilisateur = document.getElementById('admin_role');
    const blocExploitationAgronome = document.getElementById('bloc_exploitation_agronome');
    const roleModification = document.getElementById('mod_role');
    const blocExploitationModification = document.getElementById('bloc_exploitation_modification');

    function afficherChampExploitation() {
        blocExploitationAgronome.hidden = roleUtilisateur.value !== 'agronome';
    }

    function afficherExploitationModification() {
        if (roleModification && blocExploitationModification) {
            blocExploitationModification.hidden = roleModification.value !== 'agronome';
        }
    }

    boutonAjouterUtilisateur.addEventListener('click', () => {
        const ouvert = !formulaireAjoutUtilisateur.hidden;
        formulaireAjoutUtilisateur.hidden = ouvert;
        boutonAjouterUtilisateur.setAttribute('aria-expanded', String(!ouvert));
        boutonAjouterUtilisateur.textContent = ouvert ? 'Ajouter un utilisateur' : 'Masquer le formulaire';
    });

    afficherChampExploitation();
    afficherExploitationModification();
</script>
