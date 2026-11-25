<?php
// Fonctions partagées pour générer le bulletin envoyé par e-mail.

class BulletinMailException extends RuntimeException
{
}

function charger_bulletin_mail(PDO $pdo, int $etudiantId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, numero, civilite, nom_prenom, filiere, email
         FROM personne WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $etudiantId]);
    $etudiant = $stmt->fetch();
    if (!$etudiant) {
        throw new BulletinMailException('Étudiant introuvable.');
    }

    $email = trim((string)($etudiant['email'] ?? ''));
    if ($email === '') {
        throw new BulletinMailException('Aucune adresse e-mail n’est renseignée pour cet étudiant.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new BulletinMailException('L’adresse e-mail de cet étudiant est invalide.');
    }

    $stmtNotes = $pdo->prepare(
        'SELECT m.code_module, m.designation, m.coefficient, n.note
         FROM note n
         JOIN module m ON m.id = n.module_id
         WHERE n.etudiant_id = :id
         ORDER BY m.designation ASC'
    );
    $stmtNotes->execute([':id' => $etudiantId]);
    $notes = $stmtNotes->fetchAll();

    $totalCoefficients = 0;
    $totalPoints = 0.0;
    foreach ($notes as $note) {
        $coefficient = (int)$note['coefficient'];
        $totalCoefficients += $coefficient;
        $totalPoints += (float)$note['note'] * $coefficient;
    }
    $moyenne = $totalCoefficients > 0 ? $totalPoints / $totalCoefficients : 0.0;

    return [
        'etudiant' => $etudiant,
        'notes' => $notes,
        'total_coefficients' => $totalCoefficients,
        'moyenne' => $moyenne,
        'resultat' => $moyenne >= 10 ? 'ADMIS' : 'AJOURNÉ',
    ];
}

function contenu_bulletin_mail(array $bulletin): array
{
    $etudiant = $bulletin['etudiant'];
    $academicYear = ((int)date('n') >= 9)
        ? date('Y') . '-' . ((int)date('Y') + 1)
        : ((int)date('Y') - 1) . '-' . date('Y');
    $subject = preg_replace(
        '/[\r\n]+/',
        ' ',
        'Bulletin de notes - ' . $etudiant['nom_prenom'] . ' - ' . $academicYear
    );

    $html = '<!doctype html><html lang="fr"><head><meta charset="UTF-8"></head><body'
        . ' style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.5">'
        . '<h1 style="color:#1e3a8a">Bulletin de notes</h1>'
        . '<p><strong>Étudiant :</strong> ' . htmlspecialchars((string)$etudiant['civilite'], ENT_QUOTES, 'UTF-8')
        . ' ' . htmlspecialchars((string)$etudiant['nom_prenom'], ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Numéro :</strong> ' . htmlspecialchars((string)$etudiant['numero'], ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Filière :</strong> ' . htmlspecialchars((string)$etudiant['filiere'], ENT_QUOTES, 'UTF-8')
        . '<br><strong>Année universitaire :</strong> ' . htmlspecialchars($academicYear, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<table style="border-collapse:collapse;width:100%">'
        . '<thead><tr style="background:#1e3a8a;color:#fff">'
        . '<th style="padding:8px;border:1px solid #cbd5e1;text-align:left">Module</th>'
        . '<th style="padding:8px;border:1px solid #cbd5e1;text-align:left">Code</th>'
        . '<th style="padding:8px;border:1px solid #cbd5e1">Coefficient</th>'
        . '<th style="padding:8px;border:1px solid #cbd5e1">Note / 20</th>'
        . '</tr></thead><tbody>';
    $text = "Bulletin de notes\n"
        . 'Étudiant : ' . $etudiant['civilite'] . ' ' . $etudiant['nom_prenom'] . "\n"
        . 'Numéro : ' . $etudiant['numero'] . "\n"
        . 'Filière : ' . $etudiant['filiere'] . "\n"
        . 'Année universitaire : ' . $academicYear . "\n\n"
        . "Module | Code | Coefficient | Note / 20\n";

    foreach ($bulletin['notes'] as $note) {
        $designation = htmlspecialchars((string)$note['designation'], ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars((string)$note['code_module'], ENT_QUOTES, 'UTF-8');
        $coefficient = (int)$note['coefficient'];
        $grade = number_format((float)$note['note'], 2, ',', ' ');
        $html .= '<tr><td style="padding:8px;border:1px solid #cbd5e1">' . $designation . '</td>'
            . '<td style="padding:8px;border:1px solid #cbd5e1">' . $code . '</td>'
            . '<td style="padding:8px;border:1px solid #cbd5e1;text-align:center">' . $coefficient . '</td>'
            . '<td style="padding:8px;border:1px solid #cbd5e1;text-align:center">' . $grade . '</td></tr>';
        $text .= $note['designation'] . ' | ' . $note['code_module'] . ' | '
            . $coefficient . ' | ' . $grade . "\n";
    }

    $average = number_format((float)$bulletin['moyenne'], 2, ',', ' ');
    $result = htmlspecialchars((string)$bulletin['resultat'], ENT_QUOTES, 'UTF-8');
    $html .= '</tbody></table><p><strong>Total coefficients :</strong> '
        . (int)$bulletin['total_coefficients'] . '<br><strong>Moyenne générale :</strong> '
        . $average . ' / 20<br><strong>Résultat :</strong> ' . $result . '</p></body></html>';
    $text .= "\nTotal coefficients : " . (int)$bulletin['total_coefficients']
        . "\nMoyenne générale : " . $average . " / 20"
        . "\nRésultat : " . $bulletin['resultat'] . "\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
