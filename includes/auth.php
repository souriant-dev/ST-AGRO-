<?php
/**
 * ST-AGRO — Authentification & contrôle d'accès par rôle
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

function estConnecte(): bool
{
    return isset($_SESSION['user_id']);
}

function utilisateurCourant(): ?array
{
    if (!estConnecte()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = getPDO()->prepare('SELECT id, nom, prenom, email, telephone, role, photo_profil, ville, statut FROM utilisateurs WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

function connecterUtilisateur(array $user): void
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_nom'] = $user['prenom'] . ' ' . $user['nom'];
}

function deconnecterUtilisateur(): void
{
    $_SESSION = [];
    session_destroy();
}

/** Redirige vers la page de connexion si l'utilisateur n'est pas authentifié */
function exigerConnexion(): void
{
    if (!estConnecte()) {
        header('Location: /st-agro/connexion.php');
        exit;
    }
}

/** Redirige si le rôle de l'utilisateur ne fait pas partie des rôles autorisés */
function exigerRole(array $rolesAutorises): void
{
    exigerConnexion();
    if (!in_array($_SESSION['user_role'], $rolesAutorises, true)) {
        header('Location: /st-agro/index.php');
        exit;
    }
}

/** Chemin relatif vers le tableau de bord adapté au rôle courant */
function urlDashboard(?string $role = null): string
{
    $role = $role ?? ($_SESSION['user_role'] ?? '');
    return match ($role) {
        'administrateur' => '/st-agro/admin/dashboard.php',
        'agronome'       => '/st-agro/agronome/dashboard.php',
        default          => '/st-agro/agriculteur/dashboard.php',
    };
}

function jetonCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifierCSRF(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
}
