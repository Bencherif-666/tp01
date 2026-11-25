<?php
// Envoi du bulletin via PHPMailer et un serveur SMTP.
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
        throw new BulletinMailException('La configuration SMTP est absente. Consultez config/mail.example.php.');
    }
    $mailConfig = require $mailConfigPath;

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    } else {
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    }

    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = (string)($mailConfig['host'] ?? 'smtp.gmail.com');
    $mailer->SMTPAuth = true;
    $mailer->Username = (string)($mailConfig['username'] ?? '');
    $mailer->Password = (string)($mailConfig['app_password'] ?? '');
    $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = (int)($mailConfig['port'] ?? 587);
    $mailer->CharSet = 'UTF-8';
    $mailer->isHTML(true);
    if (!empty($mailConfig['debug'])) {
        $mailer->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
    }
    $mailer->setFrom(
        (string)($mailConfig['from_email'] ?? ''),
        (string)($mailConfig['from_name'] ?? '')
    );
    $mailer->addAddress((string)$bulletin['etudiant']['email']);
    $mailer->Subject = $content['subject'];
    $mailer->Body = $content['html'];
    $mailer->AltBody = $content['text'];
    $mailer->send();

    $message = 'Le bulletin a été envoyé à ' . $bulletin['etudiant']['email'] . '.';
    $type = 'success';
} catch (Throwable $e) {
    error_log('Envoi PHPMailer du bulletin : ' . $e->getMessage());
    $message = $e instanceof BulletinMailException
        ? $e->getMessage()
        : 'L’envoi SMTP a échoué. Vérifiez la configuration et les identifiants Gmail.';
}

$_SESSION['flash_mail'] = ['type' => $type, 'message' => $message];
$recherche = $numero !== '' ? $numero : (string)($etudiantId ?: '');
$redirect = 'bulletin.php' . ($recherche !== '' ? '?recherche=' . rawurlencode($recherche) : '');
header('Location: ' . $redirect);
exit;
