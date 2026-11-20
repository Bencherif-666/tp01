<?php
// includes/auth.php : Gestion des sessions, authentification et contrôle d'accès par rôle

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

/**
 * Vérifie si un utilisateur est connecté
 */
function est_connecte(): bool
{
    if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
        session_start();
    }
    return !empty($_SESSION['user']['id']);
}

/**
 * Retourne les données de l'utilisateur connecté ou null
 */
function utilisateur_connecte(): ?array
{
    if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
        session_start();
    }
    return $_SESSION['user'] ?? null;
}

/**
 * Vérifie si l'utilisateur connecté a le rôle Admin
 */
function est_admin(): bool
{
    $u = utilisateur_connecte();
    return $u !== null && (($u['role'] ?? '') === 'Admin');
}

/**
 * Vérifie si l'utilisateur connecté a le rôle User
 */
function est_user(): bool
{
    $u = utilisateur_connecte();
    return $u !== null && (($u['role'] ?? '') === 'User');
}

/**
 * Vérifie si l'utilisateur connecté a le rôle Analyst
 */
function est_analyst(): bool
{
    $u = utilisateur_connecte();
    return $u !== null && (($u['role'] ?? '') === 'Analyst');
}

/**
 * Vérifie si l'utilisateur connecté a le rôle Enseignant
 */
function est_enseignant(): bool
{
    $u = utilisateur_connecte();
    return $u !== null && (($u['role'] ?? '') === 'Enseignant');
}

/**
 * Vérifie si l'utilisateur connecté a le rôle Controller
 */
function est_controller(): bool
{
    $u = utilisateur_connecte();
    return $u !== null && (($u['role'] ?? '') === 'Controller');
}

/**
 * Enregistre l'utilisateur en session lors de la connexion
 */
function connecter_utilisateur(array $user): void
{
    if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
        session_start();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user'] = [
        'id'    => (int)$user['id'],
        'email' => (string)$user['email'],
        'role'  => (string)$user['role'],
    ];
}

/**
 * Détruit la session lors de la déconnexion
 */
function deconnecter_utilisateur(): void
{
    if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Exige que l'utilisateur soit connecté (n'importe quel rôle).
 * Redirige vers login.php si non connecté.
 */
function exiger_connexion(string $base = ''): void
{
    if (!est_connecte()) {
        header('Location: ' . $base . 'login.php');
        exit;
    }
}

/**
 * Exige que l'utilisateur soit connecté et administrateur.
 * Redirige vers login.php si non connecté, ou vers bulletin_user.php si simple User.
 */
function exiger_admin(string $base = ''): void
{
    if (!est_connecte()) {
        header('Location: ' . $base . 'login.php');
        exit;
    }
    if (!est_admin()) {
        header('Location: ' . $base . 'bulletin_user.php?erreur=' . urlencode('Accès refusé. Cette page est réservée aux administrateurs.'));
        exit;
    }
}

/**
 * Protection automatique des routes au chargement d'un script web.
 */
function verifier_acces_automatique(): void
{
    if (php_sapi_name() === 'cli') {
        return;
    }

    $appRoot = realpath(__DIR__ . '/..');
    $currentScript = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (!$currentScript || strpos($currentScript, $appRoot) !== 0) {
        return;
    }

    $relPath = trim(str_replace('\\', '/', substr($currentScript, strlen($appRoot))), '/');
    $depth = substr_count($relPath, '/');
    $base = str_repeat('../', $depth);

    // Scripts publics d'authentification
    $scriptsPublics = ['login.php', 'inscription.php', 'register.php', 'logout.php'];
    if (in_array($relPath, $scriptsPublics, true)) {
        return;
    }

    // Si non connecté : redirection vers login.php
    if (!est_connecte()) {
        header('Location: ' . $base . 'login.php');
        exit;
    }

    // Si connecté en tant que User ordinaire
    if (est_user()) {
        // Le rôle User ne peut accéder qu'à bulletin_user.php
        $scriptsAutorisesUser = ['bulletin_user.php'];
        if (!in_array($relPath, $scriptsAutorisesUser, true)) {
            header('Location: ' . $base . 'bulletin_user.php?erreur=' . urlencode('Accès refusé. Cette page est réservée aux administrateurs.'));
            exit;
        }
    }

    // Analyst: accès limité aux statistiques
    if (est_analyst()) {
        $scriptsAutorisesAnalyst = [
            'statistiques.php',
            'statistiques/statistiques.php',
            'statistiques/index.php',
            'communication/inbox.php',
            'communication/mark_read.php',
        ];
        if (!in_array($relPath, $scriptsAutorisesAnalyst, true)) {
            header('Location: ' . $base . 'statistiques/statistiques.php');
            exit;
        }
    }

    // Enseignant: accès limité au formulaire et aux actions associées
    if (est_enseignant()) {
        $scriptsAutorisesEnseignant = [
            'enseignant/enseignant.php',
            'enseignant/enregistrer.php',
            'enseignant/liste.php',
            'enseignant/etudiants.php',
            'communication/inbox.php',
            'communication/mark_read.php',
        ];
        if (!in_array($relPath, $scriptsAutorisesEnseignant, true)) {
            header('Location: ' . $base . 'enseignant/enseignant.php');
            exit;
        }
    }

    // Controller: accès limité au tableau de bord des communications
    if (est_controller() && $relPath !== 'communication/controller.php') {
        header('Location: ' . $base . 'communication/controller.php');
        exit;
    }
}

// Exécuter automatiquement le contrôle d'accès
verifier_acces_automatique();
