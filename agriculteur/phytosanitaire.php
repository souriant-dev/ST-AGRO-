<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

$stmt = $pdo->prepare('SELECT id, nom FROM exploitations WHERE agriculteur_id = ?');
$stmt->execute([$user['id']]);
$exploitations = $stmt->fetchAll();
$exploitationPreselectionnee = (int) ($_GET['exploitation_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCSRF($_POST['csrf'] ?? null)) {
    $exploitationId = (int) ($_POST['exploitation_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id FROM exploitations WHERE id = ? AND agriculteur_id = ?');
    $stmt->execute([$exploitationId, $user['id']]);

    if (!$stmt->fetch()) {
        definirMessage('erreur', 'Exploitation invalide.');
    } else {
        $cheminImage = null;
        if (!empty($_FILES['photo']['name'])) {
            $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                definirMessage('erreur', 'Format d\'image non supporté (jpg, png, webp uniquement).');
            } else {
                $nomFichier = 'phyto_' . uniqid() . '.' . $extension;
                $dossier = __DIR__ . '/../uploads/phytosanitaire/';
                if (!is_dir($dossier)) mkdir($dossier, 0755, true);
                move_uploaded_file($_FILES['photo']['tmp_name'], $dossier . $nomFichier);
                $cheminImage = '/uploads/phytosanitaire/' . $nomFichier;
            }
        }

        if (empty($_SESSION['flash'])) {
            $resultatPlantNet = $cheminImage ? analyserImagePlantNet($dossier . $nomFichier) : ['succes' => false];
            $stmt = $pdo->prepare('INSERT INTO analyses_phytosanitaires (exploitation_id, image_path, diagnostic, niveau_risque, recommandation) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $exploitationId, $cheminImage,
                $resultatPlantNet['diagnostic'] ?? 'Analyse en attente de validation par un agronome.',
                $resultatPlantNet['niveau_risque'] ?? 'faible',
                $resultatPlantNet['recommandation'] ?? 'Un agronome examinera votre photo et complétera le diagnostic sous peu.',
            ]);
            definirMessage('succes', $resultatPlantNet['succes'] ? 'Photo analysée par Pl@ntNet. Un agronome va vérifier le résultat.' : 'Photo envoyée. L’analyse sera complétée par un agronome.');
            header('Location: /st-agro/agriculteur/phytosanitaire.php');
            exit;
        }
    }
}

$idsExploitations = array_column($exploitations, 'id');
$analyses = [];
if ($idsExploitations) {
    $in = implode(',', array_fill(0, count($idsExploitations), '?'));
    $stmt = $pdo->prepare("SELECT a.*, e.nom AS exploitation_nom FROM analyses_phytosanitaires a
        JOIN exploitations e ON e.id = a.exploitation_id WHERE a.exploitation_id IN ($in) ORDER BY a.date_analyse DESC");
    $stmt->execute($idsExploitations);
    $analyses = $stmt->fetchAll();
}

$titrePage = 'Analyse phytosanitaire';
$filAriane = 'Analyse phytosanitaire';
$pageActive = 'phyto';
require __DIR__ . '/../includes/layout_debut.php';
$csrf = jetonCSRF();
?>
<h1>Analyse phytosanitaire</h1>
<p class="sous-titre-page">Envoyez une photo de vos cultures pour un diagnostic maladies/parasites.</p>

<div class="grille-2">
    <div class="carte">
        <div class="carte-titre"><h3>Envoyer une photo</h3></div>
        <?php if (!$exploitations): ?>
            <p style="color:var(--texte-attenue); font-size:0.9rem;">Ajoutez d'abord une exploitation pour lancer une analyse.</p>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="champ">
                    <label for="exploitation_id">Exploitation concernée</label>
                    <select id="exploitation_id" name="exploitation_id" required>
                        <?php foreach ($exploitations as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= $exploitationPreselectionnee === (int)$e['id'] ? 'selected' : '' ?>><?= nettoyer($e['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ">
                    <label for="photo">Photo de la plante / feuille</label>
                    <input type="file" id="photo" name="photo" accept="image/png, image/jpeg, image/webp" required>
                    <small>Formats acceptés : JPG, PNG, WEBP.</small>
                </div>
                <button type="submit" class="btn btn-primaire btn-bloc">Envoyer pour analyse</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="carte" style="background: var(--bleu-liseret); border:none;">
        <h4 style="margin-bottom:10px;">Comment ça marche ?</h4>
        <p style="font-size:0.88rem; color:var(--bleu-fonce);">
            Votre photo est transmise au module de diagnostic. Un agronome de la plateforme
            valide et complète chaque résultat avant qu'il ne s'affiche comme définitif,
            afin de garantir des recommandations fiables.
        </p>
    </div>
</div>

<div class="carte" style="margin-top:20px;">
    <div class="carte-titre"><h3>Historique des analyses</h3></div>
    <?php if (!$analyses): ?>
        <p style="color:var(--texte-attenue); font-size:0.9rem;">Aucune analyse envoyée pour le moment.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-app">
                <thead><tr><th>Date</th><th>Exploitation</th><th>Diagnostic</th><th>Risque</th></tr></thead>
                <tbody>
                <?php foreach ($analyses as $a): ?>
                    <tr>
                        <td><?= formaterDate($a['date_analyse']) ?></td>
                        <td><?= nettoyer($a['exploitation_nom']) ?></td>
                        <td><?= nettoyer($a['diagnostic']) ?></td>
                        <td>
                            <?php $b = $a['niveau_risque'] === 'eleve' ? 'badge-rouge' : ($a['niveau_risque'] === 'modere' ? 'badge-ambre' : 'badge-vert'); ?>
                            <span class="badge <?= $b ?>"><?= ucfirst($a['niveau_risque']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
