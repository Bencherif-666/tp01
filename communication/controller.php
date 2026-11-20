<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!est_controller()) {
    exiger_connexion('../');
    header('Location: ../index.php');
    exit;
}

$stmtCounts = $pdo->query("
    SELECT u.id, u.email, u.role,
           COUNT(cm.id) AS message_count,
           COALESCE(SUM(CASE WHEN cm.read_at IS NULL AND cm.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS unread_count,
           MAX(cm.sent_at) AS latest_message
    FROM `user` u
    LEFT JOIN communication_message cm ON cm.recipient_id = u.id
    WHERE u.role IN ('Analyst', 'Enseignant')
    GROUP BY u.id, u.email, u.role
    ORDER BY u.role ASC, u.email ASC
");
$recipientStats = $stmtCounts->fetchAll();
$analystStats = array_values(array_filter($recipientStats, static fn(array $row): bool => $row['role'] === 'Analyst'));
$teacherStats = array_values(array_filter($recipientStats, static fn(array $row): bool => $row['role'] === 'Enseignant'));

$messageCountsByRole = ['Analyst' => 0, 'Enseignant' => 0];
$totalMessages = 0;
foreach ($recipientStats as $recipientStat) {
    $role = $recipientStat['role'];
    $count = (int)$recipientStat['message_count'];
    $messageCountsByRole[$role] = ($messageCountsByRole[$role] ?? 0) + $count;
    $totalMessages += $count;
}

$stmtMessages = $pdo->query("
    SELECT cm.id, cm.message, cm.sent_at, cm.read_at,
           recipient.email AS recipient_email, recipient.role AS recipient_role,
           sender.email AS sender_email
    FROM communication_message cm
    JOIN `user` recipient ON recipient.id = cm.recipient_id
    JOIN `user` sender ON sender.id = cm.sender_id
    WHERE recipient.role IN ('Analyst', 'Enseignant')
    ORDER BY cm.sent_at DESC, cm.id DESC
");
$messages = $stmtMessages->fetchAll();
$analystMessages = array_values(array_filter($messages, static fn(array $row): bool => $row['recipient_role'] === 'Analyst'));
$teacherMessages = array_values(array_filter($messages, static fn(array $row): bool => $row['recipient_role'] === 'Enseignant'));

$base = '../';
$titre = 'Tableau de bord Controller';
require __DIR__ . '/../includes/header.php';
?>

<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;margin-bottom:24px;">
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--radius-lg);padding:18px;">
        <div style="color:#1d4ed8;font-size:13px;font-weight:700;">Messages reçus par les Analysts</div>
        <strong style="display:block;font-size:28px;color:#1e3a8a;"><?= h($messageCountsByRole['Analyst']) ?></strong>
    </div>
    <div style="background:#ecfeff;border:1px solid #a5f3fc;border-radius:var(--radius-lg);padding:18px;">
        <div style="color:#0e7490;font-size:13px;font-weight:700;">Messages reçus par les Enseignants</div>
        <strong style="display:block;font-size:28px;color:#164e63;"><?= h($messageCountsByRole['Enseignant']) ?></strong>
    </div>
    <div style="background:var(--bg-subtle);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:18px;">
        <div style="color:var(--text-muted);font-size:13px;font-weight:700;">Total des messages</div>
        <strong style="display:block;font-size:28px;color:var(--text-heading);"><?= h($totalMessages) ?></strong>
    </div>
</section>

<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;margin-bottom:30px;">
    <div style="min-width:0;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:18px;box-shadow:var(--shadow-sm);">
        <h3 style="margin:0 0 14px;color:var(--text-heading);">Messages par destinataire</h3>
        <div style="height:340px;"><canvas id="messagesByRecipient"></canvas></div>
    </div>
    <div style="min-width:0;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:18px;box-shadow:var(--shadow-sm);">
        <h3 style="margin:0 0 14px;color:var(--text-heading);">Répartition par catégorie</h3>
        <div style="height:340px;"><canvas id="messagesByRole"></canvas></div>
    </div>
</section>

<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:20px;margin-bottom:30px;">
    <div>
        <h3>Messages des Analysts</h3>
        <table class="liste" style="width:100%;">
            <thead>
                <tr><th>Email</th><th>Messages</th><th>Non lus</th><th>Dernier message</th></tr>
            </thead>
            <tbody>
                <?php if ($analystStats): ?>
                    <?php foreach ($analystStats as $recipientStat): ?>
                        <tr>
                            <td><?= h($recipientStat['email']) ?></td>
                            <td><strong><?= h($recipientStat['message_count']) ?></strong></td>
                            <td><?= h($recipientStat['unread_count']) ?></td>
                            <td><?= $recipientStat['latest_message'] ? h(date('d/m/Y H:i', strtotime($recipientStat['latest_message']))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Aucun compte Analyst.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="4" style="text-align:right;">Total : <?= h($messageCountsByRole['Analyst']) ?> message(s)</th></tr>
            </tfoot>
        </table>
    </div>
    <div>
        <h3>Messages des Enseignants</h3>
        <table class="liste" style="width:100%;">
            <thead>
                <tr><th>Email</th><th>Messages</th><th>Non lus</th><th>Dernier message</th></tr>
            </thead>
            <tbody>
                <?php if ($teacherStats): ?>
                    <?php foreach ($teacherStats as $recipientStat): ?>
                        <tr>
                            <td><?= h($recipientStat['email']) ?></td>
                            <td><strong><?= h($recipientStat['message_count']) ?></strong></td>
                            <td><?= h($recipientStat['unread_count']) ?></td>
                            <td><?= $recipientStat['latest_message'] ? h(date('d/m/Y H:i', strtotime($recipientStat['latest_message']))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Aucun compte Enseignant.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="4" style="text-align:right;">Total : <?= h($messageCountsByRole['Enseignant']) ?> message(s)</th></tr>
            </tfoot>
        </table>
    </div>
</section>

<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:20px;">
    <div>
        <h3>Messages adressés aux Analysts</h3>
        <table class="liste" style="width:100%;">
            <thead>
                <tr><th>Destinataire</th><th>Message</th><th>Date</th><th>Statut</th></tr>
            </thead>
            <tbody>
                <?php if ($analystMessages): ?>
                    <?php foreach ($analystMessages as $message): ?>
                        <tr>
                            <td><?= h($message['recipient_email']) ?></td>
                            <td style="white-space:pre-wrap;"><?= nl2br(h($message['message'])) ?></td>
                            <td><?= h(date('d/m/Y H:i', strtotime($message['sent_at']))) ?></td>
                            <td><?= $message['read_at'] ? 'Lu' : 'Non lu' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Aucun message pour les Analysts.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div>
        <h3>Messages adressés aux Enseignants</h3>
        <table class="liste" style="width:100%;">
            <thead>
                <tr><th>Destinataire</th><th>Message</th><th>Date</th><th>Statut</th></tr>
            </thead>
            <tbody>
                <?php if ($teacherMessages): ?>
                    <?php foreach ($teacherMessages as $message): ?>
                        <tr>
                            <td><?= h($message['recipient_email']) ?></td>
                            <td style="white-space:pre-wrap;"><?= nl2br(h($message['message'])) ?></td>
                            <td><?= h(date('d/m/Y H:i', strtotime($message['sent_at']))) ?></td>
                            <td><?= $message['read_at'] ? 'Lu' : 'Non lu' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Aucun message pour les Enseignants.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script src="<?= $base ?>js/chart.umd.min.js"></script>
<script>
if (typeof Chart === 'undefined') {
    var chartFallback = document.createElement('script');
    chartFallback.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
    chartFallback.onload = renderControllerCharts;
    document.head.appendChild(chartFallback);
} else {
    renderControllerCharts();
}

function renderControllerCharts() {
    if (typeof Chart === 'undefined') return;

    var recipients = <?= json_encode(array_map(static function (array $row): array {
        return [
            'email' => $row['email'],
            'role' => $row['role'],
            'count' => (int)$row['message_count'],
        ];
    }, $recipientStats), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var roleCounts = <?= json_encode(array_values($messageCountsByRole)) ?>;
    var palette = recipients.map(function (recipient) {
        return recipient.role === 'Analyst' ? '#3b82f6' : '#06b6d4';
    });

    new Chart(document.getElementById('messagesByRecipient'), {
        type: 'bar',
        data: {
            labels: recipients.map(function (recipient) { return recipient.email; }),
            datasets: [{
                label: 'Messages reçus',
                data: recipients.map(function (recipient) { return recipient.count; }),
                backgroundColor: palette,
                borderRadius: 5
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: false } }
        }
    });

    new Chart(document.getElementById('messagesByRole'), {
        type: 'pie',
        data: {
            labels: ['Analysts', 'Enseignants'],
            datasets: [{
                data: roleCounts,
                backgroundColor: ['#3b82f6', '#06b6d4'],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
