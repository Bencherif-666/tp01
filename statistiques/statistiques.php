<?php
// statistiques/statistiques.php : Choix du type de statistiques puis affichage immédiat
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Moyenne minimale pour être déclaré "réussi"
$seuilReussite = 10;

// Mode choisi : '' (aucun), 'filiere' ou 'toutes'
$mode = $_GET['mode'] ?? '';
if (!in_array($mode, ['filiere', 'toutes'], true)) {
    $mode = '';
}

// Variables par défaut
$nbHommes = $nbFemmes = $nbTotal = 0;
$pourcentHommes = $pourcentFemmes = 0;
$statsFiliere = [];
$resultatsFiliere = [];
$erreurResultats = '';

// ===== Mode "Par filière" =====
if ($mode === 'filiere') {
    $stmt = $pdo->query("
        SELECT
            SUM(CASE WHEN LOWER(civilite) IN ('monsieur', 'homme', 'm.', 'm') THEN 1 ELSE 0 END) AS nb_hommes,
            SUM(CASE WHEN LOWER(civilite) IN ('madame', 'mademoiselle', 'femme', 'mme', 'mlle') THEN 1 ELSE 0 END) AS nb_femmes,
            COUNT(*) AS nb_total
        FROM personne
    ");
    $row = $stmt ? $stmt->fetch() : null;

    $nbHommes = (int)($row['nb_hommes'] ?? 0);
    $nbFemmes = (int)($row['nb_femmes'] ?? 0);
    $nbTotal  = (int)($row['nb_total'] ?? ($nbHommes + $nbFemmes));

    $pourcentHommes = $nbTotal > 0 ? round(($nbHommes / $nbTotal) * 100, 1) : 0;
    $pourcentFemmes = $nbTotal > 0 ? round(($nbFemmes / $nbTotal) * 100, 1) : 0;

    $stmtFiliere = $pdo->query("
        SELECT filiere,
               SUM(CASE WHEN LOWER(civilite) IN ('monsieur', 'homme', 'm.', 'm') THEN 1 ELSE 0 END) AS hommes,
               SUM(CASE WHEN LOWER(civilite) IN ('madame', 'mademoiselle', 'femme', 'mme', 'mlle') THEN 1 ELSE 0 END) AS femmes,
               COUNT(*) AS total
        FROM personne
        WHERE filiere IS NOT NULL AND filiere != ''
        GROUP BY filiere
        ORDER BY filiere ASC
    ");
    $statsFiliere = $stmtFiliere ? $stmtFiliere->fetchAll() : [];
}

// ===== Mode "Toute filière" : réussis / échoués par filière =====
// Moyenne de chaque étudiant = SUM(note x coefficient) / SUM(coefficient)
if ($mode === 'toutes') {
    try {
        $sql = "
            SELECT t.filiere,
                   SUM(CASE WHEN t.moyenne >= :seuil1 THEN 1 ELSE 0 END) AS reussis,
                   SUM(CASE WHEN t.moyenne <  :seuil2 THEN 1 ELSE 0 END) AS echoues
            FROM (
                SELECT p.id,
                       p.filiere,
                       SUM(n.note * m.coefficient) / NULLIF(SUM(m.coefficient), 0) AS moyenne
                FROM personne p
                LEFT JOIN note n   ON n.etudiant_id = p.id
                LEFT JOIN module m ON m.id = n.module_id
                WHERE p.filiere IS NOT NULL AND p.filiere != ''
                GROUP BY p.id, p.filiere
            ) AS t
            GROUP BY t.filiere
            ORDER BY t.filiere ASC
        ";
        $stmtRes = $pdo->prepare($sql);
        $stmtRes->execute([':seuil1' => $seuilReussite, ':seuil2' => $seuilReussite]);
        $resultatsFiliere = $stmtRes->fetchAll();
    } catch (Throwable $e) {
        $erreurResultats = "Impossible de calculer les résultats : " . $e->getMessage();
    }
}

// Données pour les graphiques "Toute filière"
$labelsFiliere = [];
$dataReussis   = [];
$dataEchoues   = [];
foreach ($resultatsFiliere as $rf) {
    $labelsFiliere[] = (string)$rf['filiere'];
    $dataReussis[]   = (int)$rf['reussis'];
    $dataEchoues[]   = (int)$rf['echoues'];
}

// Totaux généraux (toutes filières confondues)
$totalReussis = array_sum($dataReussis);
$totalEchoues = array_sum($dataEchoues);
$totalGeneral = $totalReussis + $totalEchoues;
$pctReussis   = $totalGeneral > 0 ? round(($totalReussis / $totalGeneral) * 100, 1) : 0;
$pctEchoues   = $totalGeneral > 0 ? round(($totalEchoues / $totalGeneral) * 100, 1) : 0;

// Largeur minimale du graphique : ~80px par filière (défilement horizontal si trop de filières)
$largeurMinChart = max(count($labelsFiliere) * 80, 320);

$base  = '../';
$titre = 'Statistiques';
require __DIR__ . '/../includes/header.php';
?>

<style>
/* ===== Choix ===== */
.choice-card {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 28px;
    box-shadow: var(--shadow-sm);
    max-width: 520px;
    margin: 30px auto;
}

.choice-card.compact {
    margin: 0 0 24px 0;
    max-width: none;
    padding: 18px 24px;
}

.choice-card h3 {
    margin: 0 0 18px 0;
    font-size: 18px;
    color: var(--text-heading);
}

.choice-card.compact h3 {
    font-size: 15px;
    margin-bottom: 12px;
}

.choice-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.choice-card.compact .choice-options {
    flex-direction: row;
    flex-wrap: wrap;
}

.choice-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    cursor: pointer;
    font-weight: 600;
    color: var(--text-heading);
    transition: border-color 0.15s ease, background 0.15s ease;
}

.choice-card.compact .choice-option {
    flex: 1;
    min-width: 200px;
}

.choice-option:hover {
    border-color: var(--primary);
    background: var(--primary-light);
}

.choice-option.active {
    border-color: var(--primary);
    background: var(--primary-light);
}

.choice-option input[type="radio"] {
    width: 18px;
    height: 18px;
    margin: 0;
    cursor: pointer;
}

/* ===== Cartes récapitulatives ===== */
.stats-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.metric-card {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 20px 24px;
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.metric-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.metric-icon.total   { background: var(--primary-light); color: var(--primary); }
.metric-icon.hommes  { background: #eff6ff; color: #2563eb; }
.metric-icon.femmes  { background: #fdf2f8; color: #db2777; }

.metric-info { flex: 1; }

.metric-label {
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 4px;
}

.metric-value {
    font-size: 26px;
    font-weight: 800;
    color: var(--text-heading);
    line-height: 1.1;
}

.metric-sub {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

/* ===== Graphiques ===== */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}

/* Histogramme général + diagramme circulaire côte à côte */
.general-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 24px;
    margin-top: 24px;
}

.chart-card {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-sm);
}

.chart-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-light);
}

.chart-card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--text-heading);
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-container-box {
    position: relative;
    width: 100%;
    height: 320px;
}

/* Histogramme par filière : défilement horizontal si beaucoup de filières */
.chart-scroll {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 6px;
}

.chart-scroll-inner {
    position: relative;
    height: 300px;
}

/* Histogramme général (2 barres) et son diagramme circulaire */
.chart-general-box {
    position: relative;
    width: 100%;
    height: 280px;
}

/* ===== Tableaux ===== */
.filiere-table-card {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-sm);
    margin-top: 10px;
}

.filiere-table-card h3 {
    margin: 0 0 16px 0;
    font-size: 16px;
    color: var(--text-heading);
}

.total-row td {
    background: #f8fafc;
    border-top: 2px solid #cbd5e1;
    font-weight: 800;
}

.msg-erreur {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 14px 18px;
    border-radius: var(--radius-md);
    font-weight: 600;
}
</style>

<!-- ===== Choix : les résultats s'affichent dès qu'un bouton radio est sélectionné ===== -->
<div class="choice-card <?= $mode === '' ? '' : 'compact' ?>">
    <h3>Choisissez le type de statistiques</h3>
    <form method="get" action="" id="formChoix">
        <div class="choice-options">
            <label class="choice-option <?= $mode === 'filiere' ? 'active' : '' ?>">
                <input type="radio" name="mode" value="filiere" <?= $mode === 'filiere' ? 'checked' : '' ?> onchange="this.form.submit()">
                <span>Par filière</span>
            </label>
            <label class="choice-option <?= $mode === 'toutes' ? 'active' : '' ?>">
                <input type="radio" name="mode" value="toutes" <?= $mode === 'toutes' ? 'checked' : '' ?> onchange="this.form.submit()">
                <span>Toute filière</span>
            </label>
        </div>
        <noscript><button type="submit" style="margin-top: 14px;">Afficher</button></noscript>
    </form>
</div>

<?php if ($mode === 'toutes'): ?>

    <!-- ===== Tableau : Filière / Réussis / Échoués + total général ===== -->
    <div class="filiere-table-card">
        <h3>Résultats par filière (réussis / échoués)</h3>

        <?php if ($erreurResultats): ?>
            <div class="msg-erreur"><?= h($erreurResultats) ?></div>
        <?php elseif (!$resultatsFiliere): ?>
            <p style="color: var(--text-muted); margin: 0;">Aucune donnée disponible.</p>
        <?php else: ?>
            <table class="liste" style="width: 100%; margin: 0;">
                <thead>
                    <tr>
                        <th>Filière</th>
                        <th style="text-align: center;">Étudiants réussis</th>
                        <th style="text-align: center;">Étudiants échoués</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultatsFiliere as $rf): ?>
                        <tr>
                            <td><strong><?= h($rf['filiere']) ?></strong></td>
                            <td style="text-align: center; color: #16a34a; font-weight: 700;"><?= (int)$rf['reussis'] ?></td>
                            <td style="text-align: center; color: #dc2626; font-weight: 700;"><?= (int)$rf['echoues'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>Total général</td>
                        <td style="text-align: center; color: #16a34a;"><?= (int)$totalReussis ?> (<?= $pctReussis ?>%)</td>
                        <td style="text-align: center; color: #dc2626;"><?= (int)$totalEchoues ?> (<?= $pctEchoues ?>%)</td>
                    </tr>
                </tfoot>
            </table>
            <p style="color: var(--text-muted); font-size: 12px; margin: 12px 0 0 0;">
                Réussi = moyenne pondérée (note × coefficient) supérieure ou égale à <?= (int)$seuilReussite ?>/20.
                Les étudiants sans note ne sont pas comptés.
            </p>
        <?php endif; ?>
    </div>

    <?php if (!$erreurResultats && $resultatsFiliere): ?>

        <!-- ===== Histogramme 1 : réussis / échoués pour chaque filière ===== -->
        <div class="chart-card" style="margin-top: 24px;">
            <div class="chart-card-header">
                <h3 class="chart-card-title">
                    <span>📊</span>
                    <span>Réussis / échoués par filière</span>
                </h3>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Bar Chart</span>
            </div>
            <div class="chart-scroll">
                <div class="chart-scroll-inner" style="min-width: <?= (int)$largeurMinChart ?>px;">
                    <canvas id="chartReussite"></canvas>
                </div>
            </div>
        </div>

        <!-- ===== Total général : histogramme (2 barres) + diagramme circulaire côte à côte ===== -->
        <div class="general-grid">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3 class="chart-card-title">
                        <span>📈</span>
                        <span>Total général (histogramme)</span>
                    </h3>
                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Bar Chart</span>
                </div>
                <div class="chart-general-box">
                    <canvas id="chartGeneral"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-card-header">
                    <h3 class="chart-card-title">
                        <span>🥧</span>
                        <span>Total général (diagramme circulaire)</span>
                    </h3>
                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Pie Chart</span>
                </div>
                <div class="chart-general-box">
                    <canvas id="chartGeneralPie"></canvas>
                </div>
            </div>
        </div>

        <!-- Chart.js local avec secours CDN -->
        <script src="<?= $base ?>js/chart.umd.min.js"></script>
        <script>
        if (typeof Chart === 'undefined') {
            var cdnScript = document.createElement('script');
            cdnScript.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
            document.head.appendChild(cdnScript);
        }

        // Attend que Chart.js soit disponible (local ou CDN), puis exécute la fonction
        function quandChartPret(callback, essais) {
            essais = essais || 0;
            if (typeof Chart !== 'undefined') {
                callback();
            } else if (essais < 50) {
                setTimeout(function () { quandChartPret(callback, essais + 1); }, 100);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            quandChartPret(function () {
                var labels  = <?= json_encode($labelsFiliere, JSON_UNESCAPED_UNICODE) ?>;
                var reussis = <?= json_encode($dataReussis) ?>;
                var echoues = <?= json_encode($dataEchoues) ?>;
                var totalReussis = <?= json_encode($totalReussis) ?>;
                var totalEchoues = <?= json_encode($totalEchoues) ?>;

                // 1. Histogramme par filière (2 barres par filière)
                var ctx = document.getElementById('chartReussite');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Réussis',
                                    data: reussis,
                                    backgroundColor: 'rgba(22, 163, 74, 0.85)',
                                    borderColor: '#16a34a',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                    maxBarThickness: 28
                                },
                                {
                                    label: 'Échoués',
                                    data: echoues,
                                    backgroundColor: 'rgba(220, 38, 38, 0.85)',
                                    borderColor: '#dc2626',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                    maxBarThickness: 28
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        font: { size: 12, weight: 'bold' },
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            return ' ' + context.dataset.label + ' : ' + (context.parsed.y || 0) + ' étudiant(s)';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0, font: { size: 11 } },
                                    title: { display: true, text: "Nombre d'étudiants", font: { weight: 'bold' } },
                                    grid: { color: '#f1f5f9' }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 11, weight: 'bold' } }
                                }
                            }
                        }
                    });
                }

                // 2. Histogramme général (tous réussis / tous échoués)
                var ctxGen = document.getElementById('chartGeneral');
                if (ctxGen) {
                    new Chart(ctxGen, {
                        type: 'bar',
                        data: {
                            labels: ['Tous les réussis', 'Tous les échoués'],
                            datasets: [{
                                label: "Nombre d'étudiants",
                                data: [totalReussis, totalEchoues],
                                backgroundColor: ['rgba(22, 163, 74, 0.85)', 'rgba(220, 38, 38, 0.85)'],
                                borderColor: ['#16a34a', '#dc2626'],
                                borderWidth: 1.5,
                                borderRadius: 6,
                                maxBarThickness: 90
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            var total = totalReussis + totalEchoues;
                                            var val = context.parsed.y || 0;
                                            var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                            return ' ' + val + ' étudiant(s) (' + pct + '%)';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0, font: { size: 11 } },
                                    title: { display: true, text: "Nombre d'étudiants", font: { weight: 'bold' } },
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

                // 3. Diagramme circulaire général (mêmes données que l'histogramme général)
                var ctxGenPie = document.getElementById('chartGeneralPie');
                if (ctxGenPie) {
                    new Chart(ctxGenPie, {
                        type: 'pie',
                        data: {
                            labels: ['Réussis', 'Échoués'],
                            datasets: [{
                                data: [totalReussis, totalEchoues],
                                backgroundColor: ['rgba(22, 163, 74, 0.85)', 'rgba(220, 38, 38, 0.85)'],
                                borderColor: ['#ffffff', '#ffffff'],
                                borderWidth: 2,
                                hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        font: { size: 13, weight: 'bold' },
                                        padding: 16,
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            var total = totalReussis + totalEchoues;
                                            var val = context.parsed || 0;
                                            var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                            return ' ' + context.label + ' : ' + val + ' (' + pct + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        });
        </script>
    <?php endif; ?>

<?php elseif ($mode === 'filiere'): ?>

    <!-- Cartes d'indicateurs clés -->
    <div class="stats-summary-grid">
        <div class="metric-card">
            <div class="metric-icon total">👥</div>
            <div class="metric-info">
                <div class="metric-label">Total Étudiants</div>
                <div class="metric-value"><?= $nbTotal ?></div>
                <div class="metric-sub">Effectif global enregistré</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon hommes">👨</div>
            <div class="metric-info">
                <div class="metric-label">Hommes</div>
                <div class="metric-value" style="color: #2563eb;"><?= $nbHommes ?></div>
                <div class="metric-sub"><?= $pourcentHommes ?>% de l'effectif</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon femmes">👩</div>
            <div class="metric-info">
                <div class="metric-label">Femmes</div>
                <div class="metric-value" style="color: #db2777;"><?= $nbFemmes ?></div>
                <div class="metric-sub"><?= $pourcentFemmes ?>% de l'effectif</div>
            </div>
        </div>
    </div>

    <!-- Histogramme et Diagramme circulaire -->
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-card-title">
                    <span>📊</span>
                    <span>Répartition par sexe (Histogramme)</span>
                </h3>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Bar Chart</span>
            </div>
            <div class="chart-container-box">
                <canvas id="chartHistogramme"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-card-title">
                    <span>🥧</span>
                    <span>Répartition par sexe (Diagramme circulaire)</span>
                </h3>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Pie Chart</span>
            </div>
            <div class="chart-container-box">
                <canvas id="chartPie"></canvas>
            </div>
        </div>
    </div>

    <?php if ($statsFiliere): ?>
        <div class="filiere-table-card">
            <h3>Répartition détaillée par Filière</h3>
            <table class="liste" style="width: 100%; margin: 0;">
                <thead>
                    <tr>
                        <th>Filière</th>
                        <th style="text-align: center;">Hommes (👨)</th>
                        <th style="text-align: center;">Femmes (👩)</th>
                        <th style="text-align: center;">Total Étudiants</th>
                        <th style="text-align: center;">Ratio Homme / Femme</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statsFiliere as $sf):
                        $h = (int)$sf['hommes'];
                        $f = (int)$sf['femmes'];
                        $tot = (int)$sf['total'];
                        $pctH = $tot > 0 ? round(($h / $tot) * 100) : 0;
                        $pctF = $tot > 0 ? round(($f / $tot) * 100) : 0;
                    ?>
                        <tr>
                            <td><strong><?= h($sf['filiere']) ?></strong></td>
                            <td style="text-align: center; color: #2563eb; font-weight: 600;"><?= $h ?> (<?= $pctH ?>%)</td>
                            <td style="text-align: center; color: #db2777; font-weight: 600;"><?= $f ?> (<?= $pctF ?>%)</td>
                            <td style="text-align: center; font-weight: 700;"><?= $tot ?></td>
                            <td style="text-align: center;">
                                <div style="display: flex; height: 10px; border-radius: 5px; overflow: hidden; max-width: 140px; margin: 0 auto; background: #e2e8f0;">
                                    <div style="width: <?= $pctH ?>%; background: #3b82f6;" title="Hommes: <?= $pctH ?>%"></div>
                                    <div style="width: <?= $pctF ?>%; background: #ec4899;" title="Femmes: <?= $pctF ?>%"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Chart.js local avec secours CDN -->
    <script src="<?= $base ?>js/chart.umd.min.js"></script>
    <script>
    if (typeof Chart === 'undefined') {
        var cdnScript = document.createElement('script');
        cdnScript.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
        document.head.appendChild(cdnScript);
    }

    // Attend que Chart.js soit disponible (local ou CDN), puis exécute la fonction
    function quandChartPret(callback, essais) {
        essais = essais || 0;
        if (typeof Chart !== 'undefined') {
            callback();
        } else if (essais < 50) {
            setTimeout(function () { quandChartPret(callback, essais + 1); }, 100);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        quandChartPret(function () {
            var nbHommes = <?= json_encode($nbHommes) ?>;
            var nbFemmes = <?= json_encode($nbFemmes) ?>;

            var couleurs = {
                homme: { bg: 'rgba(59, 130, 246, 0.85)', border: '#2563eb' },
                femme: { bg: 'rgba(236, 72, 153, 0.85)', border: '#db2777' }
            };

            // 1. Histogramme
            var ctxHisto = document.getElementById('chartHistogramme');
            if (ctxHisto) {
                new Chart(ctxHisto, {
                    type: 'bar',
                    data: {
                        labels: ['Homme', 'Femme'],
                        datasets: [{
                            label: "Nombre d'étudiants",
                            data: [nbHommes, nbFemmes],
                            backgroundColor: [couleurs.homme.bg, couleurs.femme.bg],
                            borderColor: [couleurs.homme.border, couleurs.femme.border],
                            borderWidth: 1.5,
                            borderRadius: 6,
                            maxBarThickness: 70
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        var total = nbHommes + nbFemmes;
                                        var val = context.parsed.y || 0;
                                        var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                        return ' ' + val + ' étudiant(s) (' + pct + '%)';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0, font: { size: 12 } },
                                title: { display: true, text: "Nombre d'étudiants", font: { weight: 'bold' } },
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

            // 2. Diagramme circulaire
            var ctxPie = document.getElementById('chartPie');
            if (ctxPie) {
                new Chart(ctxPie, {
                    type: 'pie',
                    data: {
                        labels: ['Homme', 'Femme'],
                        datasets: [{
                            data: [nbHommes, nbFemmes],
                            backgroundColor: [couleurs.homme.bg, couleurs.femme.bg],
                            borderColor: ['#ffffff', '#ffffff'],
                            borderWidth: 2,
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: { size: 13, weight: 'bold' },
                                    padding: 16,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        var total = nbHommes + nbFemmes;
                                        var val = context.parsed || 0;
                                        var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                        return ' ' + context.label + ' : ' + val + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    });
    </script>
<?php endif; ?>

<script src="<?= $base ?>js/script.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
