<?php
// messages/controller.php : Tableau de bord de contrôle des messages
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Réservé exclusivement aux comptes Controller et Admin
exiger_connexion('../');
$userCourant = utilisateur_connecte();
$roleCourant = (string)($userCourant['role'] ?? '');

if (!in_array($roleCourant, ['Controller', 'Controleur', 'Contrôleur', 'Admin'], true)) {
    header('Location: ../login.php');
    exit;
}

// 1. Nombre total de messages
$stmtTotal = $pdo->query("SELECT COUNT(*) AS total FROM `message`");
$totalMessages = (int)($stmtTotal ? $stmtTotal->fetchColumn() : 0);

// 2. Messages envoyés aux comptes Analyse
$stmtAnalyse = $pdo->query("
    SELECT COUNT(*)
    FROM `message` m
    JOIN `user` u ON u.id = m.destinataire_id
    WHERE u.role IN ('Analyse', 'Analyste') OR m.destinataire_role IN ('Analyse', 'Analyste')
");
$nbMessagesAnalyse = (int)($stmtAnalyse ? $stmtAnalyse->fetchColumn() : 0);

// 3. Messages envoyés aux comptes Enseignants
$stmtEnseignant = $pdo->query("
    SELECT COUNT(*)
    FROM `message` m
    JOIN `user` u ON u.id = m.destinataire_id
    WHERE u.role IN ('Enseignant', 'Enseignants') OR m.destinataire_role IN ('Enseignant', 'Enseignants')
");
$nbMessagesEnseignant = (int)($stmtEnseignant ? $stmtEnseignant->fetchColumn() : 0);

// Pourcentages
$pctAnalyse    = $totalMessages > 0 ? round(($nbMessagesAnalyse / $totalMessages) * 100, 1) : 0;
$pctEnseignant = $totalMessages > 0 ? round(($nbMessagesEnseignant / $totalMessages) * 100, 1) : 0;

// 4. Nombre de messages par compte (qui a reçu combien)
$stmtParCompte = $pdo->query("
    SELECT
        u.id,
        u.email,
        u.role,
        COUNT(m.id) AS nb_recus,
        MAX(m.date_envoi) AS dernier_envoi
    FROM `user` u
    LEFT JOIN `message` m ON m.destinataire_id = u.id
    WHERE u.role IN ('Analyse', 'Analyste', 'Enseignant', 'Enseignants')
    GROUP BY u.id, u.email, u.role
    ORDER BY nb_recus DESC, u.role ASC, u.email ASC
");
$comptesMessages = $stmtParCompte ? $stmtParCompte->fetchAll() : [];

// Préparation des données JSON pour les graphiques Chart.js
$labelsComptes = [];
$dataComptes   = [];
$colorsComptes = [];

foreach ($comptesMessages as $c) {
    $labelsComptes[] = (string)$c['email'];
    $dataComptes[]   = (int)$c['nb_recus'];
    $isAnalyse = in_array($c['role'], ['Analyse', 'Analyste'], true);
    $colorsComptes[] = $isAnalyse ? 'rgba(126, 34, 206, 0.85)' : 'rgba(16, 185, 129, 0.85)';
}

// 5. Liste détaillée des messages enregistrés
$stmtTousMessages = $pdo->query("
    SELECT
        m.id,
        m.contenu,
        m.date_envoi,
        m.destinataire_role,
        uDest.email AS destinataire_email,
        uExp.email  AS expediteur_email
    FROM `message` m
    LEFT JOIN `user` uDest ON uDest.id = m.destinataire_id
    LEFT JOIN `user` uExp  ON uExp.id  = m.expediteur_id
    ORDER BY m.date_envoi DESC, m.id DESC
");
$tousMessages = $stmtTousMessages ? $stmtTousMessages->fetchAll() : [];

$base = '../';
$titre = 'Contrôleur Messages';
require __DIR__ . '/../includes/header.php';
?>

<style>
.controller-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.controller-card {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 22px 24px;
    box-shadow: var(--shadow-card);
    position: relative;
    overflow: hidden;
}
.controller-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}
.controller-card.total::before { background: #2563eb; }
.controller-card.analyse::before { background: #7e22ce; }
.controller-card.enseignant::before { background: #059669; }

.kpi-title {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
}
.kpi-value {
    font-size: 32px;
    font-weight: 800;
    color: var(--text-heading);
    line-height: 1.1;
    margin-bottom: 6px;
}
.kpi-sub {
    font-size: 13px;
    color: var(--text-muted);
}

.controller-charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}
@media (max-width: 860px) {
    .controller-charts-grid {
        grid-template-columns: 1fr;
    }
}
.chart-box {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 22px 24px;
    box-shadow: var(--shadow-card);
}
.chart-box-header {
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.chart-box-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--text-heading);
}
.chart-canvas-wrapper {
    position: relative;
    height: 300px;
    width: 100%;
}
</style>

<div style="max-width: 1050px; margin: 0 auto; padding: 10px 0 40px 0;">
    <div style="margin-bottom: 24px;">
        <h3 style="margin: 0; font-size: 22px; font-weight: 800; color: var(--text-heading);">
            🛡️ Tableau de bord Contrôleur
        </h3>
        <p style="margin: 4px 0 0 0; font-size: 14px; color: var(--text-muted);">
            Surveillance et audit des messages envoyés par l'administration vers les comptes Analyse et Enseignants.
        </p>
    </div>

    <!-- 1. Cartes Indicateurs Clés (KPIs) -->
    <div class="controller-kpi-grid">
        <div class="controller-card total">
            <div class="kpi-title">Total des messages</div>
            <div class="kpi-value" style="color: #2563eb;"><?= $totalMessages ?></div>
            <div class="kpi-sub">Total de tous les messages envoyés</div>
        </div>

        <div class="controller-card analyse">
            <div class="kpi-title">Messages aux comptes Analyse</div>
            <div class="kpi-value" style="color: #7e22ce;"><?= $nbMessagesAnalyse ?></div>
            <div class="kpi-sub"><?= $pctAnalyse ?>% du volume global des messages</div>
        </div>

        <div class="controller-card enseignant">
            <div class="kpi-title">Messages aux Enseignants</div>
            <div class="kpi-value" style="color: #059669;"><?= $nbMessagesEnseignant ?></div>
            <div class="kpi-sub"><?= $pctEnseignant ?>% du volume global des messages</div>
        </div>
    </div>

    <!-- 2. Graphiques sous les indicateurs : Pie Chart + Histogramme -->
    <div class="controller-charts-grid">
        <!-- Diagramme circulaire (Pie Chart) -->
        <div class="chart-box">
            <div class="chart-box-header">
                <h4 class="chart-box-title">🥧 Répartition par Type de Compte (Pie Chart)</h4>
            </div>
            <div class="chart-canvas-wrapper">
                <canvas id="chartPieMessages"></canvas>
            </div>
        </div>

        <!-- Histogramme (Bar Chart) -->
        <div class="chart-box">
            <div class="chart-box-header">
                <h4 class="chart-box-title">📊 Comparatif Analyse vs Enseignants (Histogramme)</h4>
            </div>
            <div class="chart-canvas-wrapper">
                <canvas id="chartHistoMessages"></canvas>
            </div>
        </div>
    </div>

    <!-- 3. Histogramme des messages par compte individuel -->
    <div class="chart-box" style="margin-bottom: 32px;">
        <div class="chart-box-header">
            <h4 class="chart-box-title">📈 Nombre de messages reçus par compte (Histogramme individuel)</h4>
        </div>
        <div class="chart-canvas-wrapper" style="height: 280px;">
            <canvas id="chartComptesHisto"></canvas>
        </div>
    </div>

    <!-- 4. Tableau détaillé : Nombre de messages par compte (qui a reçu combien) -->
    <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 24px 26px; box-shadow: var(--shadow-card); margin-bottom: 32px;">
        <h4 style="margin: 0 0 16px 0; font-size: 17px; font-weight: 700; color: var(--text-heading); display: flex; align-items: center; gap: 8px;">
            👥 Volume de messages par compte (Qui a reçu combien)
        </h4>

        <?php if (empty($comptesMessages)): ?>
            <div style="padding: 24px; text-align: center; color: var(--text-muted); font-style: italic;">
                Aucun compte Enseignant ou Analyse n'est encore enregistré.
            </div>
        <?php else: ?>
            <table class="table-liste" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 60px;">N°</th>
                        <th style="width: 140px;">Type de Compte</th>
                        <th>Adresse Email</th>
                        <th style="width: 170px; text-align: center;">Messages Reçus</th>
                        <th style="width: 180px;">Dernier Message Reçu</th>
                        <th style="width: 140px;">Part du Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comptesMessages as $idx => $cm): ?>
                        <?php
                        $roleBadge = in_array($cm['role'], ['Analyse', 'Analyste'], true) ? 'analyse' : 'enseignant';
                        $roleIcon  = in_array($cm['role'], ['Analyse', 'Analyste'], true) ? '📊' : '👨‍🏫';
                        $pctCompte = $totalMessages > 0 ? round(($cm['nb_recus'] / $totalMessages) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-muted);">
                                <?= $idx + 1 ?>
                            </td>
                            <td>
                                <span class="user-pill <?= $roleBadge ?>" style="font-size: 11.5px; padding: 3px 10px;">
                                    <?= $roleIcon . ' ' . h($cm['role']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= h($cm['email']) ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <span style="display: inline-block; padding: 4px 14px; border-radius: var(--radius-full); font-weight: 800; font-size: 13.5px; background: <?= (int)$cm['nb_recus'] > 0 ? '#e0f2fe' : '#f1f5f9' ?>; color: <?= (int)$cm['nb_recus'] > 0 ? '#0369a1' : '#64748b' ?>;">
                                    <?= (int)$cm['nb_recus'] ?> message<?= (int)$cm['nb_recus'] > 1 ? 's' : '' ?>
                                </span>
                            </td>
                            <td style="font-size: 13px; color: var(--text-muted);">
                                <?= $cm['dernier_envoi'] ? date('d/m/Y à H:i', strtotime($cm['dernier_envoi'])) : '<span style="font-style: italic; color: #94a3b8;">Aucun message</span>' ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?= $pctCompte ?>%; height: 100%; background: <?= $roleBadge === 'analyse' ? '#7e22ce' : '#059669' ?>;"></div>
                                    </div>
                                    <span style="font-size: 12px; font-weight: 700; color: var(--text-muted); min-width: 38px; text-align: right;"><?= $pctCompte ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- 5. Journal complet de tous les messages échangés -->
    <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 24px 26px; box-shadow: var(--shadow-card);">
        <h4 style="margin: 0 0 16px 0; font-size: 17px; font-weight: 700; color: var(--text-heading); display: flex; align-items: center; gap: 8px;">
            📑 Journal d'audit de tous les messages (<?= count($tousMessages) ?>)
        </h4>

        <?php if (empty($tousMessages)): ?>
            <div style="padding: 24px; text-align: center; color: var(--text-muted); font-style: italic;">
                Aucun message dans le système.
            </div>
        <?php else: ?>
            <table class="table-liste" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th style="width: 160px;">Date & Heure</th>
                        <th style="width: 160px;">Expéditeur</th>
                        <th style="width: 180px;">Destinataire</th>
                        <th style="width: 110px;">Type</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tousMessages as $m): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-muted);">
                                #<?= (int)$m['id'] ?>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-muted);">
                                <?= date('d/m/Y à H:i:s', strtotime($m['date_envoi'])) ?>
                            </td>
                            <td style="font-size: 13px;">
                                👑 <?= h($m['expediteur_email'] ?? 'Admin') ?>
                            </td>
                            <td>
                                <strong><?= h($m['destinataire_email'] ?? 'Compte supprimé') ?></strong>
                            </td>
                            <td>
                                <?php
                                $roleBadge = in_array($m['destinataire_role'], ['Analyse', 'Analyste'], true) ? 'analyse' : 'enseignant';
                                ?>
                                <span class="user-pill <?= $roleBadge ?>" style="font-size: 11px; padding: 2px 8px;">
                                    <?= h($m['destinataire_role']) ?>
                                </span>
                            </td>
                            <td style="font-size: 13.5px; line-height: 1.4;">
                                <?= nl2br(h($m['contenu'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Chargement de Chart.js pour les graphiques -->
<script src="<?= $base ?>js/chart.umd.min.js"></script>
<script>
if (typeof Chart === 'undefined') {
    var cdn = document.createElement('script');
    cdn.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
    document.head.appendChild(cdn);
}

document.addEventListener('DOMContentLoaded', function () {
    function initCharts() {
        if (typeof Chart === 'undefined') {
            setTimeout(initCharts, 100);
            return;
        }

        var nbAnalyse = <?= (int)$nbMessagesAnalyse ?>;
        var nbEnseignant = <?= (int)$nbMessagesEnseignant ?>;
        var total = <?= (int)$totalMessages ?>;

        // 1. Pie Chart : Analyse vs Enseignants
        var ctxPie = document.getElementById('chartPieMessages');
        if (ctxPie) {
            new Chart(ctxPie, {
                type: 'pie',
                data: {
                    labels: ['Comptes Analyse', 'Comptes Enseignants'],
                    datasets: [{
                        data: [nbAnalyse, nbEnseignant],
                        backgroundColor: ['#7e22ce', '#059669'],
                        borderColor: ['#ffffff', '#ffffff'],
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { size: 13, weight: 'bold' }, padding: 16 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var val = context.parsed || 0;
                                    var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                    return ' ' + context.label + ' : ' + val + ' message(s) (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        // 2. Histogramme : Analyse vs Enseignants
        var ctxHisto = document.getElementById('chartHistoMessages');
        if (ctxHisto) {
            new Chart(ctxHisto, {
                type: 'bar',
                data: {
                    labels: ['Comptes Analyse', 'Comptes Enseignants'],
                    datasets: [{
                        label: 'Nombre de messages',
                        data: [nbAnalyse, nbEnseignant],
                        backgroundColor: ['rgba(126, 34, 206, 0.85)', 'rgba(5, 150, 105, 0.85)'],
                        borderColor: ['#7e22ce', '#059669'],
                        borderWidth: 1.5,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.parsed.y + ' message(s)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, font: { size: 12 } },
                            title: { display: true, text: 'Nombre de messages', font: { weight: 'bold' } },
                            grid: { color: '#f1f5f9' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 13, weight: 'bold' } }
                        }
                    }
                }
            });
        }

        // 3. Histogramme par compte individuel
        var ctxComptes = document.getElementById('chartComptesHisto');
        if (ctxComptes) {
            var labelsComptes = <?= json_encode($labelsComptes) ?>;
            var dataComptes   = <?= json_encode($dataComptes) ?>;
            var colorsComptes = <?= json_encode($colorsComptes) ?>;

            new Chart(ctxComptes, {
                type: 'bar',
                data: {
                    labels: labelsComptes,
                    datasets: [{
                        label: 'Messages reçus',
                        data: dataComptes,
                        backgroundColor: colorsComptes,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' Reçus : ' + context.parsed.y + ' message(s)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, font: { size: 12 } },
                            title: { display: true, text: 'Messages reçus', font: { weight: 'bold' } },
                            grid: { color: '#f1f5f9' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 12 },
                                maxRotation: 45,
                                minRotation: 0
                            }
                        }
                    }
                }
            });
        }
    }

    initCharts();
});
</script>

<script src="<?= $base ?>js/script.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
