<?php
// includes/fonctions.php : petites fonctions utilitaires
require_once __DIR__ . '/auth.php';

// Échappe le texte avant de l'afficher en HTML (protège contre XSS)
function h($texte): string
{
    return htmlspecialchars((string)$texte, ENT_QUOTES, 'UTF-8');
}

// Récupère une liste de cases cochées (checkbox) en gardant seulement les valeurs autorisées
function post_liste(string $nom, array $autorises): array
{
    $recu = $_POST[$nom] ?? [];
    if (!is_array($recu)) {
        return [];
    }
    return array_values(array_intersect($autorises, $recu));
}

// Gère l'upload d'une image.
// Retourne [nom_du_fichier_ou_null, message_erreur_ou_null]
// - Aucun fichier envoyé  => [null, null]
function enregistrer_image(array $fichier): array
{
    if (!isset($fichier['error']) || $fichier['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($fichier['error'] !== UPLOAD_ERR_OK) {
        return [null, "Erreur pendant l'envoi de l'image."];
    }
    // Taille maximale : 2 Mo
    if ($fichier['size'] > 2 * 1024 * 1024) {
        return [null, "Image trop grande (2 Mo maximum)."];
    }
    // On vérifie le VRAI type du fichier (pas seulement son extension)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($fichier['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime]) || getimagesize($fichier['tmp_name']) === false) {
        return [null, "Type d'image refusé (JPG, PNG ou GIF uniquement)."];
    }
    // Nom aléatoire + extension choisie par nous : impossible d'envoyer un fichier .php
    $nom = bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    $destination = __DIR__ . '/../uploads/' . $nom;
    if (!move_uploaded_file($fichier['tmp_name'], $destination)) {
        return [null, "Impossible d'enregistrer l'image (dossier uploads/ accessible en écriture ?)."];
    }
    return [$nom, null];
}

// ============================================================================
// Gestion des Sessions PHP ($_SESSION) & Messages Flash
// ============================================================================

/**
 * Assure que la session PHP est démarrée
 */
function demarrer_session(): void
{
    if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
        session_start();
    }
}

/**
 * Définit une valeur dans la session
 */
function set_session(string $cle, $valeur): void
{
    demarrer_session();
    $_SESSION[$cle] = $valeur;
}

/**
 * Récupère une valeur de session avec valeur par défaut optionnelle
 */
function get_session(string $cle, $defaut = null)
{
    demarrer_session();
    return $_SESSION[$cle] ?? $defaut;
}

/**
 * Supprime une valeur de la session
 */
function unset_session(string $cle): void
{
    demarrer_session();
    unset($_SESSION[$cle]);
}

/**
 * Enregistre un message flash de succès en session
 */
function set_flash_succes(string $message): void
{
    demarrer_session();
    $_SESSION['flash_succes'][] = $message;
}

/**
 * Enregistre un message flash d'erreur en session
 */
function set_flash_erreur(string $message): void
{
    demarrer_session();
    $_SESSION['flash_erreur'][] = $message;
}

/**
 * Enregistre un message flash d'information en session
 */
function set_flash_info(string $message): void
{
    demarrer_session();
    $_SESSION['flash_info'][] = $message;
}

/**
 * Récupère et vide les messages flash de la session
 */
function get_flash_messages(): array
{
    demarrer_session();
    $messages = [
        'succes' => $_SESSION['flash_succes'] ?? [],
        'erreur' => $_SESSION['flash_erreur'] ?? [],
        'info'   => $_SESSION['flash_info'] ?? [],
    ];
    unset($_SESSION['flash_succes'], $_SESSION['flash_erreur'], $_SESSION['flash_info']);
    return $messages;
}

/**
 * Affiche tous les messages flash en attente (session et fallback GET)
 */
function afficher_messages_flash(): void
{
    demarrer_session();
    $flashes = get_flash_messages();

    // Fallback rétrocompatible pour les paramètres GET historiques
    if (!empty($_GET['succes'])) {
        $flashes['succes'][] = (string)$_GET['succes'];
    }
    if (!empty($_GET['erreur'])) {
        $flashes['erreur'][] = (string)$_GET['erreur'];
    }

    if (!empty($flashes['succes'])) {
        foreach ($flashes['succes'] as $s) {
            echo '<div class="alert alert-success" style="margin: 16px 0; padding: 12px 18px; background: #f0fdf4; border: 1px solid #86efac; color: #166534; border-radius: 8px; font-weight: 500;">';
            echo '<span>' . $s . '</span>';
            echo '</div>';
        }
    }

    if (!empty($flashes['info'])) {
        foreach ($flashes['info'] as $inf) {
            echo '<div class="alert alert-info" style="margin: 16px 0; padding: 12px 18px; background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; border-radius: 8px; font-weight: 500;">';
            echo '<span>' . $inf . '</span>';
            echo '</div>';
        }
    }

    if (!empty($flashes['erreur'])) {
        foreach ($flashes['erreur'] as $e) {
            echo '<div class="alert alert-error" style="margin: 16px 0; padding: 12px 18px; background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 8px; font-weight: 500;">';
            echo '<span>' . $e . '</span>';
            echo '</div>';
        }
    }
}

