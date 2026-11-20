<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

exiger_connexion('../');

$user = utilisateur_connecte();
if (!in_array($user['role'] ?? '', ['Analyst', 'Enseignant'], true)) {
    header('Location: ../index.php');
    exit;
}

$stmtMessages = $pdo->prepare("
    SELECT cm.id, cm.message, cm.sent_at, cm.read_at, sender.email AS sender_email
    FROM communication_message cm
    JOIN `user` sender ON sender.id = cm.sender_id
    WHERE cm.recipient_id = :recipient_id
    ORDER BY cm.read_at IS NOT NULL, cm.sent_at DESC, cm.id DESC
");
$stmtMessages->execute([':recipient_id' => (int)$user['id']]);
$messages = $stmtMessages->fetchAll();

$base = '../';
$titre = 'Messagerie';
require __DIR__ . '/../includes/header.php';
?>

<h2>Messages reçus</h2>
<div style="display:grid;gap:14px;">
    <?php if ($messages): ?>
        <?php foreach ($messages as $message): ?>
            <?php $pending = $message['read_at'] === null; ?>
            <article style="border:1px solid <?= $pending ? '#f59e0b' : 'var(--border-light)' ?>; border-left-width:5px; border-radius:var(--radius-lg); padding:18px; background:<?= $pending ? '#fffbeb' : '#ffffff' ?>; box-shadow:var(--shadow-sm);">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                    <strong>De : <?= h($message['sender_email']) ?></strong>
                    <?php if ($pending): ?>
                        <span class="user-pill" style="background:#fde68a;color:#92400e;border:1px solid #f59e0b;">Nouveau</span>
                    <?php else: ?>
                        <span class="petit" style="color:var(--text-muted);">Lu</span>
                    <?php endif; ?>
                </div>
                <p style="white-space:pre-wrap;margin:0 0 12px 0;"><?= nl2br(h($message['message'])) ?></p>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span class="petit" style="color:var(--text-muted);"><?= h(date('d/m/Y H:i', strtotime($message['sent_at']))) ?></span>
                    <?php if ($pending): ?>
                        <form method="post" action="mark_read.php" style="margin:0;">
                            <input type="hidden" name="id" value="<?= h($message['id']) ?>">
                            <button type="submit" class="btn-blue" style="margin:0;">Marquer comme lu</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color:var(--text-muted);">Aucun message reçu.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
