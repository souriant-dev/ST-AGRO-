<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['agriculteur']);
$user = utilisateurCourant();
$pdo = getPDO();

$messages = [
    [
        'role' => 'assistant',
        'texte' => 'Bonjour ! Je suis votre assistant agricole ST-AGRO. Posez-moi une question sur les cultures, le sol, l’irrigation, les maladies, ou le suivi de vos exploitations.',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $message = trim($_POST['message']);
    if ($message !== '') {
        $messages[] = ['role' => 'user', 'texte' => $message];
        $reponse = reponseChatGemini($message, ['profil' => 'Agriculteur']);
        $messages[] = ['role' => 'assistant', 'texte' => $reponse];
    }
}

$titrePage = 'Demander conseil à l’IA';
$filAriane = 'Demander conseil à l’IA';
$pageActive = 'assistant_ia';
require __DIR__ . '/../includes/layout_debut.php';
?>
<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:18px;">
    <div>
        <h1>Demander conseil à l’IA</h1>
        <p class="sous-titre-page">Discussion avec l’assistant IA Mistral pour obtenir des conseils agronomiques rapides.</p>
    </div>
</div>

<div class="carte" style="padding:20px; max-width:1000px;">
    <div style="display:flex; flex-direction:column; gap:14px; min-height:420px;">
        <?php foreach ($messages as $msg): ?>
            <div style="max-width:75%; padding:12px 14px; border-radius:14px; line-height:1.5; background:<?= $msg['role'] === 'assistant' ? '#eaf4ff' : '#f3f5f7' ?>; margin-left:<?= $msg['role'] === 'assistant' ? '0' : 'auto' ?>; border:1px solid var(--bordure);">
                <strong style="display:block; margin-bottom:4px; color:var(--bleu-fonce);">
                    <?= $msg['role'] === 'assistant' ? 'Assistant IA' : 'Vous' ?>
                </strong>
                <div style="white-space:pre-wrap; color:var(--texte);">
                    <?= nettoyer($msg['texte']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" style="margin-top:18px;">
        <div class="champ" style="margin-bottom:12px;">
            <label for="message">Votre question</label>
            <textarea id="message" name="message" rows="4" placeholder="Ex : Je vois des feuilles jaunes sur ma parcelle de maïs, que faire ?" required></textarea>
        </div>
        <button type="submit" class="btn btn-primaire">Envoyer</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/layout_fin.php'; ?>
