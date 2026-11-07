<?php
// statistiques/statistiques.php : Statistiques des étudiants (Histogramme et Diagramme circulaire par sexe)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Récupération des données d'étudiants par sexe depuis la base de données
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

// Statistiques complémentaires par filière pour enrichir la vue
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

$base  = '../';
$titre = 'Statistiques';
require __DIR__ . '/../includes/header.php';
?>

<style>
/* Grille des cartes récapitulatives */
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

.metric-icon.total {
    background: var(--primary-light);
    color: var(--primary);
}

.metric-icon.hommes {
    background: #eff6ff;
    color: #2563eb;
}

.metric-icon.femmes {
    background: #fdf2f8;
    color: #db2777;
}

.metric-info {
    flex: 1;
}

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

/* Grille des 2 graphiques */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
    gap: 24px;
    margin-bottom: 30px;
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

/* Section détail par filière */
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
</style>

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

<!-- Grille des 2 graphiques (Histogramme et Diagramme Circulaire) -->
<div class="charts-grid">
    <!-- 1. Histogramme (Bar chart) -->
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

    <!-- 2. Diagramme circulaire (Pie chart) -->
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
    <!-- Détail de la répartition par filière -->
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

<!-- Chargement de Chart.js local avec secours CDN -->
<script src="<?= $base ?>js/chart.umd.min.js"></script>
<script>
if (typeof Chart === 'undefined') {
    var cdnScript = document.createElement('script');
    cdnScript.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
    document.head.appendChild(cdnScript);
}

// Initialisation des graphiques lorsque Chart.js est prêt
document.addEventListener('DOMContentLoaded', function () {
    var nbHommes = <?= json_encode($nbHommes) ?>;
    var nbFemmes = <?= json_encode($nbFemmes) ?>;

    var couleurs = {
        homme: {
            bg: 'rgba(59, 130, 246, 0.85)',
            border: '#2563eb',
            hover: 'rgba(37, 99, 235, 1)'
        },
        femme: {
            bg: 'rgba(236, 72, 153, 0.85)',
            border: '#db2777',
            hover: 'rgba(219, 39, 119, 1)'
        }
    };

    // 1. Histogramme (Bar Chart)
    var ctxHisto = document.getElementById('chartHistogramme');
    if (ctxHisto && typeof Chart !== 'undefined') {
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
                    legend: {
                        display: false
                    },
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
                        ticks: {
                            precision: 0,
                            font: {
                                size: 12
                            }
                        },
                        title: {
                            display: true,
                            text: "Nombre d'étudiants",
                            font: {
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 13,
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Diagramme circulaire (Pie Chart)
    var ctxPie = document.getElementById('chartPie');
    if (ctxPie && typeof Chart !== 'undefined') {
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
                            font: {
                                size: 13,
                                weight: 'bold'
                            },
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
</script>

<script src="<?= $base ?>js/script.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
