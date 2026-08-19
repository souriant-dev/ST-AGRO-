<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCSRF($_POST['csrf'] ?? null)) {
    $sujet = trim($_POST['sujet'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $exploitationId = (int) ($_POST['exploitation_id'] ?? 0) ?: null;

    if ($sujet === '' || $description === '') {
        definirMessage('erreur', 'Le sujet et la description sont obligatoires.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO demandes_conseil (agriculteur_id, exploitation_id, sujet, description) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user['id'], $exploitationId, $sujet, $description]);
        definirMessage('succes', 'Votre demande a été envoyée aux agronomes disponibles.');
    }
    header('Location: /agriculteur/conseils.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, nom FROM exploitations WHERE agriculteur_id = ?');
$stmt->execute([$user['id']]);
$exploitations = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT d.*, u.nom AS agronome_nom, u.prenom AS agronome_prenom FROM demandes_conseil d
    LEFT JOIN utilisateurs u ON u.id = d.agronome_id
    WHERE d.agriculteur_id = ? ORDER BY d.date_creation DESC");
$stmt->execute([$user['id']]);
$demandes = $stmt->fetchAll();

$titrePage = 'Demander conseil';
$filAriane = 'Demander conseil';
$pageActive = 'conseils';
require __DIR__ . '/../includes/layout_debut.php';
$csrf = jetonCSRF();
$libStatut = ['en_attente' => ['Envoyée', 'badge-bleu'], 'en_cours' => ['En cours', 'badge-ambre'], 'repondu' => ['Répondu', 'badge-vert']];
?>
<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;">
    <div>
        <h1>Demander conseil</h1>
        <p class="sous-titre-page">Échangez directement avec un agronome de la plateforme.</p>
    </div>
    <button class="btn btn-primaire" data-ouvrir-modale="modale-conseil">+ Nouvelle demande</button>
</div>

<?php if (!$demandes): ?>
    <div class="carte">
        <div class="etat-vide">
            <div class="icone">&#128172;</div>
            <h4>Aucune demande envoyée</h4>
            <p>Posez votre première question à un agronome.</p>
        </div>
    </div>
<?php else: ?>
    <div class="carte">
        <?php foreach ($demandes as $d): [$libelle, $classe] = $libStatut[$d['statut']]; ?>
            <a href="/agriculteur/conseil_detail.php?id=<?= $d['id'] ?>" style="text-decoration:none; color:inherit;">
                <div class="capteur-item">
                    <div class="capteur-nom">
                        <span class="badge <?= $classe ?>"><?= $libelle ?></span>
                        <div>
                            <strong style="display:block; color:var(--bleu-fonce);"><?= nettoyer($d['sujet']) ?></strong>
                            <small style="color:var(--texte-attenue);">
                                <?= $d['agronome_id'] ? 'Agronome : ' . nettoyer($d['agronome_prenom'] . ' ' . $d['agronome_nom']) : 'En attente d\'un agronome' ?>
                            </small>
                        </div>
                    </div>
                    <small style="color:var(--texte-attenue);"><?= formaterDate($d['date_creation']) ?></small>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="fond-modale" id="modale-conseil">
    <div class="boite-modale">
        <div class="boite-modale-tete">
            <h3>Nouvelle demande de conseil</h3>
            <button type="button" data-fermer-modale aria-label="Fermer">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <?php if ($exploitations): ?>
            <div class="champ">
                <label for="exploitation_id">Exploitation concernée (optionnel)</label>
                <select id="exploitation_id" name="exploitation_id">
                    <option value="">— Aucune en particulier —</option>
                    <?php foreach ($exploitations as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= nettoyer($e['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="champ">
                <label for="sujet">Sujet</label>
                <input type="text" id="sujet" name="sujet" placeholder="Ex : Taches jaunes sur les feuilles" required>
            </div>
            <div class="champ">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" required placeholder="Décrivez la situation en détail..."></textarea>
            </div>
            <button type="submit" class="btn btn-primaire btn-bloc">Envoyer la demande</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
