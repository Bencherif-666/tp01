<?php
// Envoi du bulletin via la fonction native mail().
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';
require_once __DIR__ . '/../includes/bulletin_mail.php';

exiger_admin('../');

$etudiantId = filter_var($_POST['etudiant_id'] ?? null, FILTER_VALIDATE_INT);
$numero = '';
$message = 'Une erreur est survenue pendant l’envoi du bulletin.';
$type = 'error';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new BulletinMailException('Cette action doit être envoyée par formulaire.');
    }
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['csrf_mail'] ?? ''), $token)) {
        throw new BulletinMailException('La vérification de sécurité a échoué. Rechargez le bulletin et réessayez.');
    }
    if (!$etudiantId || $etudiantId < 1) {
        throw new BulletinMailException('Étudiant invalide.');
    }

    $bulletin = charger_bulletin_mail($pdo, $etudiantId);
    $numero = (string)$bulletin['etudiant']['numero'];
    $content = contenu_bulletin_mail($bulletin);
    $mailConfigPath = __DIR__ . '/../config/mail.php';
    if (!is_file($mailConfigPath)) {
        throw new BulletinMailException('La configuration d’e-mail est absente. Consultez config/mail.example.php.');
    }
    $mailConfig = require $mailConfigPath;
    $fromEmail = trim((string)($mailConfig['from_email'] ?? ''));
    $fromName = trim((string)($mailConfig['from_name'] ?? ''));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new BulletinMailException('L’adresse d’expéditeur doit être configurée dans config/mail.php.');
    }

    $encodedSubject = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($content['subject'], 'UTF-8')
        : '=?UTF-8?B?' . base64_encode($content['subject']) . '?=';
    $safeFromName = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $fromName)) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $safeFromName . ' <' . $fromEmail . '>',
    ];

    set_error_handler(static function (int $severity, string $error): never {
        throw new ErrorException($error, 0, $severity);
    });
    try {
        $sent = mail(
            (string)$bulletin['etudiant']['email'],
            $encodedSubject,
            $content['html'],
            implode("\r\n", $headers)
        );
    } finally {
        restore_error_handler();
    }
    if (!$sent) {
        throw new BulletinMailException('Le serveur local n’a pas accepté le message. Vérifiez la configuration mail de PHP.');
    }
    $message = 'Le bulletin a été transmis à la file d’envoi pour ' . $bulletin['etudiant']['email'] . '.';
    $type = 'success';
} catch (Throwable $e) {
    error_log('Envoi mail natif du bulletin : ' . $e->getMessage());
    $message = $e instanceof BulletinMailException
        ? $e->getMessage()
        : 'L’envoi du bulletin a échoué. Vérifiez la configuration du serveur mail.';
}

$_SESSION['flash_mail'] = ['type' => $type, 'message' => $message];
$recherche = $numero !== '' ? $numero : (string)($etudiantId ?: '');
$redirect = 'bulletin.php' . ($recherche !== '' ? '?recherche=' . rawurlencode($recherche) : '');
header('Location: ' . $redirect);
exit;
