<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

exiger_admin('../');

$erreur = null;
$succes = $_GET['succes'] ?? null;
$recipientSaisi = (int)($_GET['recipient'] ?? 0);

$stmtRecipients = $pdo->query("
    SELECT id, email, role
    FROM `user`
    WHERE role IN ('Analyst', 'Enseignant')
    ORDER BY role ASC, email ASC
");
$recipients = $stmtRecipients ? $stmtRecipients->fetchAll() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientId = (int)($_POST['recipient_id'] ?? 0);
    $messageText = trim((string)($_POST['message'] ?? ''));
    $recipientSaisi = $recipientId;

    $stmtRecipient = $pdo->prepare("
        SELECT id
        FROM `user`
        WHERE id = :id AND role IN ('Analyst', 'Enseignant')
        LIMIT 1
    ");
    $stmtRecipient->execute([':id' => $recipientId]);

    if (!$stmtRecipient->fetch()) {
        $erreur = 'Sélectionnez un Analyst ou un Enseignant valide.';
    } elseif ($messageText === '') {
        $erreur = 'Le texte du message ne peut pas être vide.';
    } elseif (mb_strlen($messageText) > 5000) {
        $erreur = 'Le message est limité à 5000 caractères.';
    } else {
        $sender = utilisateur_connecte();
        $stmtSend = $pdo->prepare("
            INSERT INTO communication_message (sender_id, recipient_id, message)
            VALUES (:sender_id, :recipient_id, :message)
        ");
        $stmtSend->execute([
            ':sender_id' => (int)$sender['id'],
            ':recipient_id' => $recipientId,
            ':message' => $messageText,
        ]);

        header('Location: admin.php?succes=' . urlencode('Message envoyé avec succès.'));
        exit;
    }
}

$stmtSent = $pdo->query("
    SELECT cm.id, cm.message, cm.sent_at, cm.read_at, u.email AS recipient_email, u.role AS recipient_role
    FROM communication_message cm
    JOIN `user` u ON u.id = cm.recipient_id
    ORDER BY cm.sent_at DESC, cm.id DESC
");
$sentMessages = $stmtSent ? $stmtSent->fetchAll() : [];

$base = '../';
$titre = 'Messages administrateur';
require __DIR__ . '/../includes/header.php';
?>

<?php if ($succes): ?>
    <p class="ok"><?= h($succes) ?></p>
<?php endif; ?>
<?php if ($erreur): ?>
    <p class="erreur"><?= h($erreur) ?></p>
<?php endif; ?>

<form method="post" action="admin.php" style="max-width: 800px;">
    <table class="formulaire">
        <tr>
            <td><label for="recipient_id">Destinataire :</label></td>
            <td>
                <select name="recipient_id" id="recipient_id" required style="min-width: 320px;">
                    <option value="">— Sélectionner un email —</option>
                    <?php foreach ($recipients as $recipient): ?>
                        <option value="<?= h($recipient['id']) ?>" <?= $recipientSaisi === (int)$recipient['id'] ? 'selected' : '' ?>>
                            <?= h($recipient['email']) ?> (<?= h($recipient['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="message">Message :</label></td>
            <td>
                <textarea name="message" id="message" maxlength="5000" rows="8" style="width: 100%; max-width: 720px;" required><?= h($_POST['message'] ?? '') ?></textarea>
            </td>
        </tr>
    </table>
    <button type="submit" class="btn-blue">Envoyer</button>
</form>

<h2>Messages envoyés</h2>
<table class="liste">
    <thead>
        <tr>
            <th>Destinataire</th>
            <th>Rôle</th>
            <th>Message</th>
            <th>Envoyé le</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($sentMessages): ?>
            <?php foreach ($sentMessages as $sent): ?>
                <tr>
                    <td><?= h($sent['recipient_email']) ?></td>
                    <td><?= h($sent['recipient_role']) ?></td>
                    <td><?= nl2br(h($sent['message'])) ?></td>
                    <td><?= h(date('d/m/Y H:i', strtotime($sent['sent_at']))) ?></td>
                    <td>
                        <?php if ($sent['read_at']): ?>
                            <span class="user-pill" style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;">Lu</span>
                        <?php else: ?>
                            <span class="user-pill" style="background:#fffbeb;color:#92400e;border:1px solid #fde68a;">En attente</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Aucun message envoyé.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
