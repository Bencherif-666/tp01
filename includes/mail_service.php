<?php
// includes/mail_service.php : Service complet d'envoi de bulletins par email
// Gère à la fois mail() natif et PHPMailer avec SMTP

require_once __DIR__ . '/fonctions.php';

// Chargement des classes PHPMailer
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Charge la configuration de messagerie
 */
function get_mail_config(): array
{
    static $config = null;
    if ($config === null) {
        $configFile = __DIR__ . '/../config/mail.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        } else {
            $config = [
                'from_email'  => 'scolarite@univ-sba.dz',
                'from_name'   => 'Scolarité — Université Djillali Liabes',
                'reply_to'    => 'scolarite@univ-sba.dz',
                'smtp'        => [
                    'host'       => 'smtp.gmail.com',
                    'port'       => 587,
                    'auth'       => true,
                    'username'   => 'scolarite.tp01@gmail.com',
                    'password'   => '',
                    'encryption' => 'tls',
                    'timeout'    => 8,
                    'debug'      => 0,
                ],
                'archive_dir' => __DIR__ . '/../uploads/emails',
            ];
        }
    }
    return $config;
}

/**
 * Calcule les statistiques complètes d'un ensemble de notes
 */
function calculer_stats_bulletin(array $notes): array
{
    $totalCoefficients = 0;
    $totalPoints = 0.0;
    $noteMax = null;
    $noteMin = null;
    $modulesMax = [];
    $modulesMin = [];

    if (!empty($notes)) {
        foreach ($notes as $n) {
            $coeff = (int)($n['coefficient'] ?? 1);
            $val   = (float)($n['note'] ?? 0);
            $totalCoefficients += $coeff;
            $totalPoints       += ($val * $coeff);
        }

        $valeurs = array_map(function ($item) {
            return round((float)$item['note'], 2);
        }, $notes);

        $noteMax = max($valeurs);
        $noteMin = min($valeurs);

        foreach ($notes as $n) {
            $v = round((float)$n['note'], 2);
            $modNom = $n['designation'] ?? $n['code_module'] ?? 'Module';
            if ($v === $noteMax && !in_array($modNom, $modulesMax, true)) {
                $modulesMax[] = $modNom;
            }
            if ($v === $noteMin && !in_array($modNom, $modulesMin, true)) {
                $modulesMin[] = $modNom;
            }
        }
    }

    $moyenne = $totalCoefficients > 0 ? ($totalPoints / $totalCoefficients) : 0.0;
    $admis   = $moyenne >= 10.0;

    return [
        'total_coefficients' => $totalCoefficients,
        'total_points'       => $totalPoints,
        'moyenne'            => round($moyenne, 2),
        'admis'              => $admis,
        'note_max'           => $noteMax,
        'note_min'           => $noteMin,
        'modules_max'        => $modulesMax,
        'modules_min'        => $modulesMin,
    ];
}

/**
 * Récupère ou génère l'adresse email enregistrée de l'étudiant
 */
function recuperer_email_etudiant(array $etudiant, ?PDO $pdo = null): string
{
    // 1. Champ email explicite dans l'étudiant
    if (!empty($etudiant['email']) && filter_var($etudiant['email'], FILTER_VALIDATE_EMAIL)) {
        return trim($etudiant['email']);
    }

    // 2. Recherche dans la table `user` si PDO est disponible
    if ($pdo) {
        try {
            // Vérifier si un compte user porte le nom ou prénom de l'étudiant
            $nomPrenom = trim($etudiant['nom_prenom'] ?? '');
            $parties = preg_split('/\s+/', $nomPrenom);
            $premierMot = $parties[0] ?? '';
            if ($premierMot !== '') {
                $st = $pdo->prepare('SELECT email FROM `user` WHERE email LIKE :rech LIMIT 1');
                $st->execute([':rech' => '%' . $premierMot . '%']);
                $emailUser = $st->fetchColumn();
                if ($emailUser && filter_var($emailUser, FILTER_VALIDATE_EMAIL)) {
                    return $emailUser;
                }
            }
        } catch (Throwable $e) {}
    }

    // 3. Email institutionnel par défaut basé sur le numéro ou nom
    $numero = preg_replace('/[^0-9a-zA-Z]/', '', $etudiant['numero'] ?? '0');
    $nomSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $etudiant['nom_prenom'] ?? 'etudiant')));
    $nomSlug = trim(preg_replace('/\.+/', '.', $nomSlug), '.');

    return !empty($nomSlug) ? ($nomSlug . '@univ-sba.dz') : ('etudiant' . $numero . '@univ-sba.dz');
}

/**
 * Génère le code HTML complet et responsive du bulletin officiel de notes pour l'email
 */
function generer_html_bulletin(array $etudiant, array $notes, array $stats): string
{
    $numero      = htmlspecialchars($etudiant['numero'] ?? '—', ENT_QUOTES, 'UTF-8');
    $nomPrenom   = htmlspecialchars($etudiant['nom_prenom'] ?? '—', ENT_QUOTES, 'UTF-8');
    $civilite    = htmlspecialchars($etudiant['civilite'] ?? '—', ENT_QUOTES, 'UTF-8');
    $filiere     = htmlspecialchars($etudiant['filiere'] ?? '—', ENT_QUOTES, 'UTF-8');
    $adresse     = htmlspecialchars($etudiant['adresse'] ?? '—', ENT_QUOTES, 'UTF-8');
    $nationalite = htmlspecialchars($etudiant['nationalite'] ?? $etudiant['nationalite_code'] ?? 'Algérienne', ENT_QUOTES, 'UTF-8');
    $dateJour    = date('d/m/Y à H:i');

    $moyenneFormatee = number_format($stats['moyenne'], 2, ',', ' ');
    $decisionLabel   = $stats['admis'] ? 'ADMIS (PASSED)' : 'AJOURNÉ (FAILED)';
    $decisionColor   = $stats['admis'] ? '#16a34a' : '#dc2626';
    $decisionBg      = $stats['admis'] ? '#dcfce7' : '#fee2e2';

    // Lignes de tableau des notes
    $lignesNotesHtml = '';
    foreach ($notes as $n) {
        $desig = htmlspecialchars($n['designation'] ?? '—', ENT_QUOTES, 'UTF-8');
        $code  = htmlspecialchars($n['code_module'] ?? '—', ENT_QUOTES, 'UTF-8');
        $coeff = (int)($n['coefficient'] ?? 1);
        $val   = (float)($n['note'] ?? 0);
        $pond  = $coeff * $val;
        $valFormatee = number_format($val, 2, ',', ' ');
        $pondFormatee = number_format($pond, 2, ',', ' ');
        $noteColor = $val >= 10.0 ? '#15803d' : '#b91c1c';
        $noteBadge = $val >= 10.0 ? '#dcfce7' : '#fee2e2';

        $lignesNotesHtml .= "
        <tr style='border-bottom: 1px solid #e2e8f0;'>
            <td style='padding: 12px 14px; color: #1e293b; font-weight: 600;'>{$desig}</td>
            <td style='padding: 12px 14px; color: #64748b; font-family: monospace; font-size: 13px;'>{$code}</td>
            <td style='padding: 12px 14px; text-align: center; color: #334155; font-weight: 600;'>{$coeff}</td>
            <td style='padding: 12px 14px; text-align: right;'>
                <span style='display: inline-block; padding: 4px 10px; background: {$noteBadge}; color: {$noteColor}; border-radius: 6px; font-weight: 700; font-size: 14px;'>
                    {$valFormatee} / 20
                </span>
            </td>
            <td style='padding: 12px 14px; text-align: right; color: #334155; font-weight: 700;'>{$pondFormatee}</td>
        </tr>";
    }

    $totalCoeff = $stats['total_coefficients'];
    $totalPts   = number_format($stats['total_points'], 2, ',', ' ');
    $noteMaxTxt = $stats['note_max'] !== null ? number_format($stats['note_max'], 2, ',', ' ') . ' / 20 (' . htmlspecialchars(implode(', ', $stats['modules_max']), ENT_QUOTES, 'UTF-8') . ')' : '—';
    $noteMinTxt = $stats['note_min'] !== null ? number_format($stats['note_min'], 2, ',', ' ') . ' / 20 (' . htmlspecialchars(implode(', ', $stats['modules_min']), ENT_QUOTES, 'UTF-8') . ')' : '—';

    return "
<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Bulletin de Notes Officiel</title>
</head>
<body style='margin: 0; padding: 24px 10px; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.5;'>
    <div style='max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06);'>
        
        <!-- Header Institutionnel -->
        <div style='background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 26px 30px; color: #ffffff;'>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='vertical-align: middle;'>
                        <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85; font-weight: 600;'>République Algérienne Démocratique et Populaire</div>
                        <div style='font-size: 16px; font-weight: 800; margin-top: 4px; letter-spacing: 0.5px;'>UNIVERSITÉ DJILLALI LIABES — SIDI BEL ABBÈS</div>
                        <div style='font-size: 13px; opacity: 0.92; margin-top: 2px;'>Faculté des Sciences &bull; Département d'Informatique</div>
                    </td>
                    <td style='vertical-align: middle; text-align: right; width: 80px;'>
                        <div style='background: rgba(255,255,255,0.18); border-radius: 10px; padding: 8px 12px; font-size: 22px; text-align: center;'>🎓</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Titre du Document -->
        <div style='padding: 22px 30px 10px; text-align: center; border-bottom: 2px solid #e2e8f0;'>
            <h1 style='margin: 0; font-size: 20px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px;'>
                Relevé de Notes &bull; Bulletin Officiel
            </h1>
            <p style='margin: 6px 0 0; font-size: 13px; color: #64748b;'>
                Année Universitaire 2026/2027 &bull; Édité le {$dateJour}
            </p>
        </div>

        <!-- Fiche d'Informations Étudiant -->
        <div style='padding: 24px 30px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;'>
            <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                <tr>
                    <td style='width: 50%; padding: 6px 0; vertical-align: top;'>
                        <span style='color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600; display: block;'>Numéro d'étudiant :</span>
                        <strong style='font-size: 16px; color: #1e3a8a;'>{$numero}</strong>
                    </td>
                    <td style='width: 50%; padding: 6px 0; vertical-align: top;'>
                        <span style='color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600; display: block;'>Filière :</span>
                        <strong style='display: inline-block; padding: 2px 8px; background: #e0e7ff; color: #3730a3; border-radius: 4px; font-size: 14px;'>{$filiere}</strong>
                    </td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; vertical-align: top;'>
                        <span style='color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600; display: block;'>Nom et Prénom :</span>
                        <strong style='font-size: 15px; color: #0f172a;'>{$civilite} {$nomPrenom}</strong>
                    </td>
                    <td style='padding: 8px 0; vertical-align: top;'>
                        <span style='color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600; display: block;'>Nationalité :</span>
                        <span style='color: #334155; font-weight: 600;'>{$nationalite}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan='2' style='padding: 6px 0; vertical-align: top;'>
                        <span style='color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600; display: block;'>Adresse :</span>
                        <span style='color: #475569;'>{$adresse}</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tableau des Notes -->
        <div style='padding: 24px 30px;'>
            <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                <thead>
                    <tr style='background: #1e3a8a; color: #ffffff;'>
                        <th style='padding: 12px 14px; text-align: left; font-weight: 700; border-top-left-radius: 8px;'>Module</th>
                        <th style='padding: 12px 14px; text-align: left; font-weight: 700;'>Code</th>
                        <th style='padding: 12px 14px; text-align: center; font-weight: 700;'>Coeff</th>
                        <th style='padding: 12px 14px; text-align: right; font-weight: 700;'>Note / 20</th>
                        <th style='padding: 12px 14px; text-align: right; font-weight: 700; border-top-right-radius: 8px;'>Note Pondérée</th>
                    </tr>
                </thead>
                <tbody>
                    {$lignesNotesHtml}
                </tbody>
                <tfoot>
                    <tr style='background: #f8fafc; font-weight: 700;'>
                        <td colspan='2' style='padding: 12px 14px; text-align: right; color: #475569; border-top: 2px solid #cbd5e1;'>Total Coefficients :</td>
                        <td style='padding: 12px 14px; text-align: center; color: #1e3a8a; border-top: 2px solid #cbd5e1;'>{$totalCoeff}</td>
                        <td style='padding: 12px 14px; text-align: right; color: #475569; border-top: 2px solid #cbd5e1;'>Somme Pondérée :</td>
                        <td style='padding: 12px 14px; text-align: right; color: #1e3a8a; border-top: 2px solid #cbd5e1;'>{$totalPts}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Synthèse des Résultats & Moyenne -->
        <div style='margin: 0 30px 24px; padding: 20px; background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px;'>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='vertical-align: middle;'>
                        <div style='font-size: 13px; text-transform: uppercase; font-weight: 700; color: #166534;'>Moyenne Générale</div>
                        <div style='font-size: 28px; font-weight: 800; color: #15803d; margin-top: 4px;'>{$moyenneFormatee} <span style='font-size: 16px; font-weight: 600;'>/ 20</span></div>
                    </td>
                    <td style='vertical-align: middle; text-align: right;'>
                        <div style='display: inline-block; padding: 8px 20px; background: {$decisionBg}; color: {$decisionColor}; border: 2px solid {$decisionColor}; border-radius: 8px; font-size: 16px; font-weight: 800; letter-spacing: 1px;'>
                            {$decisionLabel}
                        </div>
                    </td>
                </tr>
            </table>
            
            <div style='margin-top: 14px; padding-top: 12px; border-top: 1px dashed #bbf7d0; font-size: 13px; color: #374151;'>
                <div style='margin-bottom: 4px;'><strong>Note maximale :</strong> <span style='color: #15803d; font-weight: 700;'>{$noteMaxTxt}</span></div>
                <div><strong>Note minimale :</strong> <span style='color: #b91c1c; font-weight: 700;'>{$noteMinTxt}</span></div>
            </div>
        </div>

        <!-- Footer Académique -->
        <div style='background: #f8fafc; padding: 20px 30px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center; line-height: 1.6;'>
            <p style='margin: 0;'>
                Ce document officiel a été généré automatiquement par le <strong>Système de Gestion de Scolarité (TP01 — PAW)</strong>.<br>
                Pour toute réclamation, veuillez contacter le service de scolarité à <a href='mailto:scolarite@univ-sba.dz' style='color: #2563eb; text-decoration: underline;'>scolarite@univ-sba.dz</a>.
            </p>
            <p style='margin: 8px 0 0; font-size: 11px; color: #94a3b8;'>
                Université Djillali Liabes &bull; BP 89, 22000 Sidi Bel Abbès, Algérie
            </p>
        </div>

    </div>
</body>
</html>
    ";
}

/**
 * Génère le contenu texte alternatif (pour les clients sans HTML)
 */
function generer_texte_bulletin(array $etudiant, array $notes, array $stats): string
{
    $numero    = $etudiant['numero'] ?? '—';
    $nom       = $etudiant['nom_prenom'] ?? '—';
    $filiere   = $etudiant['filiere'] ?? '—';
    $moyenne   = number_format($stats['moyenne'], 2, ',', ' ');
    $decision  = $stats['admis'] ? 'ADMIS (PASSED)' : 'AJOURNÉ (FAILED)';

    $txt = "========================================================\n";
    $txt .= "UNIVERSITE DJILLALI LIABES - SIDI BEL ABBES\n";
    $txt .= "BULLETIN DE NOTES OFFICIEL\n";
    $txt .= "========================================================\n\n";
    $txt .= "Etudiant : {$nom} (N° {$numero})\n";
    $txt .= "Filiere  : {$filiere}\n\n";
    $txt .= "NOTES DES MODULES :\n";
    $txt .= "--------------------------------------------------------\n";
    foreach ($notes as $n) {
        $txt .= sprintf(
            "%-30s | Coeff: %2d | Note: %5.2f/20\n",
            substr($n['designation'] ?? '', 0, 30),
            (int)($n['coefficient'] ?? 1),
            (float)($n['note'] ?? 0)
        );
    }
    $txt .= "--------------------------------------------------------\n";
    $txt .= "Total Coefficients : " . $stats['total_coefficients'] . "\n";
    $txt .= "Moyenne Generale   : " . $moyenne . " / 20\n";
    $txt .= "Decision Finale    : " . $decision . "\n\n";
    $txt .= "Service Scolarite - Universite Djillali Liabes\n";

    return $txt;
}

/**
 * Archive un email envoyé pour consultation et traçabilité locale
 */
function archiver_email(
    string $to,
    string $nomEtudiant,
    string $subject,
    string $htmlBody,
    string $methode,
    bool $succes,
    ?string $erreur = null
): string {
    $config = get_mail_config();
    $dir = $config['archive_dir'] ?? (__DIR__ . '/../uploads/emails');
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $idUnique = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $fichierHtml = $dir . '/' . $idUnique . '.html';
    $fichierJson = $dir . '/' . $idUnique . '.json';

    // Sauvegarde du fichier HTML prévisualisable
    @file_put_contents($fichierHtml, $htmlBody);

    // Métadonnées
    $meta = [
        'id'           => $idUnique,
        'date'         => date('c'),
        'destinataire' => $to,
        'etudiant'     => $nomEtudiant,
        'sujet'        => $subject,
        'methode'      => $methode,
        'succes'       => $succes,
        'erreur'       => $erreur,
        'fichier_html' => basename($fichierHtml),
    ];
    @file_put_contents($fichierJson, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $idUnique;
}

/**
 * Envoie un email en utilisant la fonction native mail() de PHP
 */
function envoyer_bulletin_via_mail(
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody,
    array $config
): array {
    $fromEmail = $config['from_email'] ?? 'scolarite@univ-sba.dz';
    $fromName  = $config['from_name'] ?? 'Scolarite';
    $replyTo   = $config['reply_to'] ?? $fromEmail;

    // Encodage du sujet en UTF-8 pour les accents
    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // En-têtes MIME conformes aux standards RFC
    $boundary = '=_paw_' . md5((string)microtime(true));
    $headers  = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <{$fromEmail}>";
    $headers[] = "Reply-To: {$replyTo}";
    $headers[] = "X-Mailer: PHP/" . phpversion() . " (TP01 PAW Bulletin)";
    $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

    // Corps multipart avec alternative texte et HTML
    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= "--{$boundary}--";

    $headersString = implode("\r\n", $headers);

    // Tentative d'envoi via la fonction mail() native
    $erreurMail = null;
    $envoye = false;

    try {
        // Enregistre les erreurs éventuelles
        $envoye = @mail($to, $subjectEncoded, $body, $headersString);
        if (!$envoye) {
            $lastErr = error_get_last();
            $erreurMail = $lastErr['message'] ?? "La fonction mail() n'a pas pu remettre le message (serveur MTA local non configuré ou inactif).";
        }
    } catch (Throwable $e) {
        $erreurMail = $e->getMessage();
        $envoye = false;
    }

    // Archiver la copie dans tous les cas
    $archiveId = archiver_email(
        $to,
        $subject,
        $subject,
        $htmlBody,
        'mail()',
        $envoye,
        $erreurMail
    );

    return [
        'succes'       => $envoye,
        'methode'      => 'mail()',
        'email'        => $to,
        'archive_id'   => $archiveId,
        'erreur'       => $erreurMail,
        'message'      => $envoye
            ? "Bulletin officiel envoyé avec succès à {$to} via la fonction PHP mail()."
            : "L'email du bulletin a été préparé et archivé pour {$to}, mais mail() a signalé : " . ($erreurMail ?: "erreur d'envoi."),
    ];
}

/**
 * Envoie un email en utilisant PHPMailer et le protocole SMTP
 */
function envoyer_bulletin_via_phpmailer(
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody,
    array $config
): array {
    $mail = new PHPMailer(true);
    $erreurSmtp = null;
    $envoye = false;

    try {
        $smtp = $config['smtp'] ?? [];
        $mail->isSMTP();
        $mail->Host       = $smtp['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = !empty($smtp['auth']);
        $mail->Username   = $smtp['username'] ?? '';
        $mail->Password   = $smtp['password'] ?? '';
        $mail->SMTPSecure = !empty($smtp['encryption']) ? $smtp['encryption'] : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = !empty($smtp['port']) ? (int)$smtp['port'] : 587;
        $mail->Timeout    = !empty($smtp['timeout']) ? (int)$smtp['timeout'] : 8;
        $mail->SMTPDebug  = !empty($smtp['debug']) ? (int)$smtp['debug'] : 0;

        // Configuration Expéditeur et Destinataire
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'] ?? 'scolarite@univ-sba.dz', $config['from_name'] ?? 'Scolarité');
        if (!empty($config['reply_to'])) {
            $mail->addReplyTo($config['reply_to'], $config['from_name'] ?? 'Scolarité');
        }
        $mail->addAddress($to);

        // Contenu du message
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody;

        $envoye = $mail->send();
    } catch (PHPMailerException $e) {
        $erreurSmtp = $mail->ErrorInfo ?: $e->getMessage();
        $envoye = false;
    } catch (Throwable $e) {
        $erreurSmtp = $e->getMessage();
        $envoye = false;
    }

    // Archiver la copie du message
    $archiveId = archiver_email(
        $to,
        $subject,
        $subject,
        $htmlBody,
        'PHPMailer (SMTP)',
        $envoye,
        $erreurSmtp
    );

    return [
        'succes'       => $envoye,
        'methode'      => 'PHPMailer (SMTP)',
        'email'        => $to,
        'archive_id'   => $archiveId,
        'erreur'       => $erreurSmtp,
        'message'      => $envoye
            ? "Bulletin officiel envoyé avec succès à {$to} via PHPMailer (SMTP) !"
            : "PHPMailer a généré et préparé le bulletin officiel pour {$to}, mais le serveur SMTP a renvoyé : " . ($erreurSmtp ?: "Erreur de connexion SMTP."),
    ];
}

/**
 * Fonction maîtresse : envoie le bulletin complet d'un étudiant via la méthode demandée ('mail' ou 'phpmailer')
 */
function envoyer_bulletin_etudiant(
    array $etudiant,
    array $notes,
    string $methode = 'mail',
    ?string $emailDestinataire = null,
    ?PDO $pdo = null
): array {
    $config = get_mail_config();

    // Déterminer l'adresse de destination
    $to = trim((string)$emailDestinataire);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $to = recuperer_email_etudiant($etudiant, $pdo);
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [
            'succes'  => false,
            'methode' => $methode,
            'email'   => $to,
            'message' => "Adresse email invalide pour l'étudiant ({$to}).",
            'erreur'  => "Format email invalide",
        ];
    }

    // Calculer les statistiques du bulletin
    $stats = calculer_stats_bulletin($notes);

    // Générer le corps du message
    $htmlBody = generer_html_bulletin($etudiant, $notes, $stats);
    $textBody = generer_texte_bulletin($etudiant, $notes, $stats);
    $sujet    = "[Bulletin Officiel] Relevé de notes — " . ($etudiant['nom_prenom'] ?? 'Étudiant') . " (N° " . ($etudiant['numero'] ?? '') . ")";

    // Choix du moteur d'envoi
    if ($methode === 'phpmailer') {
        $res = envoyer_bulletin_via_phpmailer($to, $sujet, $htmlBody, $textBody, $config);
    } else {
        $res = envoyer_bulletin_via_mail($to, $sujet, $htmlBody, $textBody, $config);
    }

    // Enregistrement en session pour traçabilité immédiate
    if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
        session_start();
    }
    $_SESSION['dernier_envoi_email'] = [
        'date'        => date('d/m/Y H:i:s'),
        'etudiant'    => $etudiant['nom_prenom'] ?? '',
        'numero'      => $etudiant['numero'] ?? '',
        'email'       => $to,
        'methode'     => $res['methode'],
        'succes'      => $res['succes'],
        'archive_id'  => $res['archive_id'] ?? null,
    ];

    return $res;
}
