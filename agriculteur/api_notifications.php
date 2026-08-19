<?php
require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'marquer_lue') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = getPDO()->prepare('UPDATE notifications SET lue = 1 WHERE id = ? AND utilisateur_id = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['ok' => true]);
    exit;
}
echo json_encode(['ok' => false]);
