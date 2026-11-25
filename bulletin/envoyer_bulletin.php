<?php
// bulletin/envoyer_bulletin.php : Contrôleur d'envoi du bulletin par email
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';
require_once __DIR__ . '/../includes/mail_service.php';

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'bulletin.php';
$redirectTarget = 'bulletin.php';
if (strpos($referer, 'afficher_bulletin.php') !== false) {
    $redirectTarget = 'afficher_bulletin.php';
}

// 1. Récupération des paramètres
$etudiantId = filter_var($_POST['etudiant_id'] ?? $_GET['etudiant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$numero     = trim((string)($_POST['numero'] ?? $_GET['numero'] ?? ''));
$methode    = strtolower(trim((string)($_POST['methode'] ?? $_GET['methode'] ?? 'mail')));
if (!in_array($methode, ['mail', 'phpmailer'], true)) {
    $methode = 'mail';
}
$emailSaisi = trim((string)($_POST['email_destinataire'] ?? $_GET['email_destinataire'] ?? ''));

// 2. Recherche de l'étudiant
$etudiant = null;
if ($etudiantId) {
    $st = $pdo->prepare('SELECT * FROM personne WHERE id = :id LIMIT 1');
    $st->execute([':id' => $etudiantId]);
    $etudiant = $st->fetch() ?: null;
} elseif ($numero !== '') {
    $st = $pdo->prepare('SELECT * FROM personne WHERE numero = :num LIMIT 1');
    $st->execute([':num' => $numero]);
    $etudiant = $st->fetch() ?: null;
}

if (!$etudiant) {
    set_flash_erreur("Impossible d'envoyer l'email : aucun étudiant spécifié ou introuvable.");
    header('Location: ' . $redirectTarget);
    exit;
}

// 3. Récupération des notes de l'étudiant
$stNotes = $pdo->prepare('
    SELECT m.code_module, m.designation, m.coefficient, n.note, n.date_saisie
    FROM note n
    JOIN module m ON m.id = n.module_id
    WHERE n.etudiant_id = :eid
    ORDER BY m.designation ASC
');
$stNotes->execute([':eid' => $etudiant['id']]);
$notes = $stNotes->fetchAll();

if (empty($notes)) {
    set_flash_erreur("Impossible d'envoyer le bulletin : aucune note n'est encore enregistrée pour l'étudiant {$etudiant['nom_prenom']}.");
    $urlRetour = ($redirectTarget === 'afficher_bulletin.php')
        ? "afficher_bulletin.php?numero=" . urlencode((string)$etudiant['numero'])
        : "bulletin.php?id=" . urlencode((string)$etudiant['id']);
    header('Location: ' . $urlRetour);
    exit;
}

// 4. Détermination de l'email de destination
$destinataireEmail = $emailSaisi;
if ($destinataireEmail === '' || !filter_var($destinataireEmail, FILTER_VALIDATE_EMAIL)) {
    $destinataireEmail = recuperer_email_etudiant($etudiant, $pdo);
}

// Si la colonne email de l'étudiant est vide mais qu'on a un email valide, on la met à jour dans la base
if (!empty($destinataireEmail) && empty($etudiant['email'])) {
    try {
        $stUp = $pdo->prepare('UPDATE personne SET email = :email WHERE id = :id');
        $stUp->execute([':email' => $destinataireEmail, ':id' => $etudiant['id']]);
        $etudiant['email'] = $destinataireEmail;
    } catch (Throwable $e) {}
}

// 5. Exécution de l'envoi
$resultat = envoyer_bulletin_etudiant($etudiant, $notes, $methode, $destinataireEmail, $pdo);

$nomMethode = ($methode === 'phpmailer') ? 'PHPMailer (SMTP)' : 'PHP mail()';

if ($resultat['succes']) {
    $msg = "✅ Le bulletin officiel de l'étudiant <strong>" . h($etudiant['nom_prenom']) . "</strong> (N° " . h($etudiant['numero']) . ") a été envoyé avec succès à <strong>" . h($destinataireEmail) . "</strong> via <strong>" . $nomMethode . "</strong> !";
    if (!empty($resultat['archive_id'])) {
        $msg .= " &nbsp; <a href='apercu_email.php?id=" . urlencode($resultat['archive_id']) . "' target='_blank' style='text-decoration: underline; font-weight: bold;'>[👁️ Voir l'email envoyé]</a>";
    }
    set_flash_succes($msg);
} else {
    // Si l'envoi SMTP/mail() direct a échoué (ex: pas de serveur MTA/SMTP configuré en local), le message HTML complet a tout de même été généré et archivé !
    $msg = "ℹ️ Le bulletin officiel complet a été <strong>généré pour l'étudiant " . h($etudiant['nom_prenom']) . "</strong> (destinataire : <strong>" . h($destinataireEmail) . "</strong> via <strong>" . $nomMethode . "</strong>).";
    if (!empty($resultat['erreur'])) {
        $msg .= "<br><span style='font-size: 13px; opacity: 0.9;'>Détail du moteur : " . h($resultat['erreur']) . "</span>";
    }
    if (!empty($resultat['archive_id'])) {
        $msg .= "<br><a href='apercu_email.php?id=" . urlencode($resultat['archive_id']) . "' target='_blank' style='text-decoration: underline; font-weight: bold;'>👉 Cliquez ici pour prévisualiser le bulletin HTML complet envoyé</a>";
    }
    // Si l'archive a été créée, considérer comme information utile
    set_flash_info($msg);
}

// 6. Redirection
$urlRetour = ($redirectTarget === 'afficher_bulletin.php')
    ? "afficher_bulletin.php?numero=" . urlencode((string)$etudiant['numero'])
    : "bulletin.php?id=" . urlencode((string)$etudiant['id']);

header('Location: ' . $urlRetour);
exit;
