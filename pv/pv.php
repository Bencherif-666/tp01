<?php
// pv/pv.php : Procès-Verbal Global par filière
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Liste des filières requises
$filieres = ['TC', '2SC', '3ISIL', '3SI', 'M1', 'M2RSSI', 'M2WIC', 'M2ISI', '1ING', '2ING'];

$selectedFiliere = trim((string)($_GET['filiere'] ?? ''));
if ($selectedFiliere !== '' && !in_array($selectedFiliere, $filieres, true)) {
    // Si la filière n'est pas dans la liste autorisée, on la réinitialise
    $selectedFiliere = '';
}

$etudiantsPv = [];
$statsFiliere = [
    'moyenne' => null,
    'min'     => null,
    'max'     => null,
    'count'   => 0,
];

if ($selectedFiliere !== '') {
    // Récupération des étudiants de la filière sélectionnée
    $stmtEtudiants = $pdo->prepare('
        SELECT id, numero, nom_prenom, filiere
        FROM personne
        WHERE filiere = :filiere
        ORDER BY nom_prenom ASC
    ');
    $stmtEtudiants->execute([':filiere' => $selectedFiliere]);
    $listeEtudiants = $stmtEtudiants->fetchAll();

    // Pour chaque étudiant, calcul de la moyenne générale recalculée à chaque fois depuis la table note
    $moyennesValides = [];

    // Préparation de la requête pour récupérer les notes et coefficients de l'étudiant
    $stmtNotes = $pdo->prepare('
        SELECT n.note, m.coefficient
        FROM note n
        JOIN module m ON m.id = n.module_id
        WHERE n.etudiant_id = :eid
    ');

    foreach ($listeEtudiants as $etudiant) {
        $stmtNotes->execute([':eid' => $etudiant['id']]);
        $notes = $stmtNotes->fetchAll();

        $sommePoints = 0.0;
        $sommeCoeffs = 0;

        foreach ($notes as $n) {
            $coeff = (int)$n['coefficient'];
            $val   = (float)$n['note'];
            $sommePoints += ($val * $coeff);
            $sommeCoeffs += $coeff;
        }

        if ($sommeCoeffs > 0) {
            $moyenneGen = $sommePoints / $sommeCoeffs;
            $moyenneGenArrondie = round($moyenneGen, 2);
            $moyennesValides[] = $moyenneGenArrondie;
            $moyenneAffichee = number_format($moyenneGenArrondie, 2, '.', '');
        } else {
            $moyenneGen = null;
            $moyenneAffichee = '—';
        }

        $etudiantsPv[] = [
            'id'             => $etudiant['id'],
            'numero'         => $etudiant['numero'],
            'nom_prenom'     => $etudiant['nom_prenom'],
            'filiere'        => $etudiant['filiere'],
            'moyenne'        => $moyenneGen,
            'moyenne_format' => $moyenneAffichee,
            'nb_notes'       => count($notes),
        ];
    }

    // Calcul des statistiques de la filière : Moyenne, Moy.Min et Moy.Max
    if (count($moyennesValides) > 0) {
        $statsFiliere['count']   = count($moyennesValides);
        $statsFiliere['moyenne'] = round(array_sum($moyennesValides) / count($moyennesValides), 2);
        $statsFiliere['min']     = round(min($moyennesValides), 2);
        $statsFiliere['max']     = round(max($moyennesValides), 2);
    }
}

$base  = '../';
$titre = 'PV';
require __DIR__ . '/../includes/header.php';
?>

<style>
/* Styles spécifiques pour l'écran et pour l'impression du PV */
.pv-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    background: var(--bg-subtle);
    padding: 16px 20px;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
}

.pv-form-group {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.pv-form-group label {
    font-weight: 600;
    color: var(--text-heading);
    font-size: 14.5px;
}

.pv-form-group select {
    padding: 8px 14px;
    font-size: 14px;
    border: 1px solid var(--border-medium);
    border-radius: var(--radius-md);
    background: #ffffff;
    color: var(--text-heading);
    min-width: 220px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pv-form-group select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-glow);
    outline: none;
}

/* Zone imprimable du PV */
#zone-pv {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 30px;
    box-shadow: var(--shadow-sm);
    margin-top: 10px;
}

.pv-entete-officiel {
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 18px;
    border-bottom: 2px solid var(--border-light);
}

.pv-entete-officiel .pv-republique {
    font-size: 13px;
    letter-spacing: 0.08em;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
}

.pv-entete-officiel .pv-institution {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-heading);
    margin-top: 4px;
}

.pv-entete-officiel .pv-faculte {
    font-size: 13.5px;
    color: var(--text-body);
}

.pv-title-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.pv-title-banner h3 {
    margin: 0;
    font-size: 22px;
    color: var(--primary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 800;
}

.pv-badge-filiere {
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid var(--primary-light-border);
    padding: 6px 14px;
    border-radius: var(--radius-full);
    font-size: 13.5px;
    font-weight: 600;
}

/* Tableau PV GLOBAL */
table.pv-table {
    width: 100%;
    margin-bottom: 24px;
    border-collapse: separate;
    border-spacing: 0;
}

table.pv-table th {
    background: var(--bg-alt);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 12px 14px;
    border-bottom: 2px solid var(--border-medium);
}

table.pv-table td {
    padding: 11px 14px;
    font-size: 13.5px;
}

.pv-note-admis {
    color: #15803d;
    font-weight: 700;
}

.pv-note-ajourne {
    color: #b91c1c;
    font-weight: 700;
}

/* Bloc statistiques sous le tableau */
.pv-stats-container {
    background: var(--bg-subtle);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 20px 24px;
    margin-top: 20px;
}

.pv-stats-title {
    font-size: 13.5px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    margin-bottom: 14px;
}

.pv-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

.pv-stat-card {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 14px 18px;
    text-align: center;
    box-shadow: var(--shadow-sm);
}

.pv-stat-label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 6px;
    letter-spacing: 0.03em;
}

.pv-stat-value {
    display: block;
    font-size: 24px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: var(--text-heading);
}

.pv-stat-value.primary { color: var(--primary); }
.pv-stat-value.success { color: #15803d; }
.pv-stat-value.danger  { color: #b91c1c; }

/* Styles d'impression stricts pour n'imprimer que le PV */
@media print {
    @page {
        margin: 1.5cm;
        size: A4 portrait;
    }

    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-family: Arial, sans-serif !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Masquer tous les éléments extérieurs au PV */
    #page,
    #page > *:not(#zone-pv),
    #menu,
    .header-brand,
    .pv-controls,
    .btn-print,
    #pied,
    footer,
    hr,
    h2 {
        display: none !important;
    }

    #page {
        display: block !important;
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    #zone-pv {
        display: block !important;
        position: static !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }

    .pv-entete-officiel {
        border-bottom: 2px solid #000 !important;
        margin-bottom: 20px !important;
        padding-bottom: 12px !important;
    }

    .pv-title-banner h3 {
        color: #000000 !important;
        font-size: 18pt !important;
    }

    .pv-badge-filiere {
        background: none !important;
        border: 1px solid #000 !important;
        color: #000 !important;
    }

    table.pv-table {
        border: 1px solid #000 !important;
        width: 100% !important;
        border-collapse: collapse !important;
    }

    table.pv-table th,
    table.pv-table td {
        border: 1px solid #000 !important;
        padding: 8px 10px !important;
        color: #000000 !important;
    }

    table.pv-table th {
        background: #f0f0f0 !important;
    }

    .pv-stats-container {
        border: 1px solid #000 !important;
        background: #fafafa !important;
        padding: 14px !important;
        page-break-inside: avoid;
    }

    .pv-stat-card {
        border: 1px solid #666 !important;
        box-shadow: none !important;
    }

    .pv-stat-value {
        color: #000000 !important;
    }
}
</style>

<!-- Barre de contrôle : sélection de filière et impression -->
<div class="pv-controls">
    <form method="get" action="pv.php" class="pv-form-group" id="form-filiere">
        <label for="select-filiere">Filière :</label>
        <select name="filiere" id="select-filiere" onchange="this.form.submit()">
            <option value="">— Choisir une filière —</option>
            <?php foreach ($filieres as $f): ?>
                <option value="<?= h($f) ?>" <?= ($selectedFiliere === $f) ? 'selected' : '' ?>>
                    <?= h($f) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Afficher</button>
    </form>

    <?php if ($selectedFiliere !== ''): ?>
        <button type="button" class="btn-print" onclick="window.print()" style="font-weight: 600; padding: 8px 18px; font-size: 13.5px;">
            🖨️ Imprimer le PV
        </button>
    <?php endif; ?>
</div>

<?php if ($selectedFiliere === ''): ?>
    <div style="background: var(--bg-subtle); border: 1px dashed var(--border-medium); border-radius: var(--radius-lg); padding: 40px 20px; text-align: center; color: var(--text-muted); margin-top: 10px;">
        <h3 style="margin: 0 0 8px 0; color: var(--text-heading); font-size: 18px;">Procès-Verbal Global (PV)</h3>
        <p style="margin: 0; font-size: 14.5px;">Veuillez sélectionner une filière dans le menu déroulant ci-dessus pour afficher son PV Global et les statistiques associées.</p>
    </div>
<?php else: ?>
    <!-- Contenu officiel du PV (zone imprimable) -->
    <div id="zone-pv">
        <div class="pv-entete-officiel">
            <div class="pv-republique">République Algérienne Démocratique et Populaire</div>
            <div class="pv-institution">Ministère de l'Enseignement Supérieur et de la Recherche Scientifique</div>
            <div class="pv-institution">Université Djillali Liabes &bull; Sidi Bel Abbès</div>
            <div class="pv-faculte">Faculté des Sciences Exactes &bull; Département d'Informatique</div>
        </div>

        <div class="pv-title-banner">
            <h3 class="pv-title">PV GLOBAL</h3>
            <span class="pv-badge-filiere">Filière : <strong><?= h($selectedFiliere) ?></strong> &bull; Année Universitaire 2026/2027</span>
        </div>

        <?php if (!$etudiantsPv): ?>
            <p style="color: var(--text-muted); padding: 20px 0; font-style: italic; text-align: center;">
                Aucun étudiant inscrit dans la filière <?= h($selectedFiliere) ?> pour le moment.
            </p>
        <?php else: ?>
            <table class="liste pv-table" id="table-pv">
                <thead>
                    <tr>
                        <th style="width: 140px; text-align: center;">N° Etudiant</th>
                        <th>NOMS ET PRENOMS</th>
                        <th style="width: 160px; text-align: center;">MOY.GEN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($etudiantsPv as $ligne): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 600;"><?= h($ligne['numero']) ?></td>
                            <td><strong><?= h($ligne['nom_prenom']) ?></strong></td>
                            <td style="text-align: center;">
                                <?php if ($ligne['moyenne'] !== null): ?>
                                    <span class="<?= $ligne['moyenne'] >= 10 ? 'pv-note-admis' : 'pv-note-ajourne' ?>">
                                        <?= h($ligne['moyenne_format']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Statistiques de la filière requises sous le tableau : Moyenne, Moy.Min et Moy.Max -->
            <div class="pv-stats-container">
                <div class="pv-stats-title">Synthèse des résultats &bull; Filière <?= h($selectedFiliere) ?></div>
                <div class="pv-stats-grid">
                    <div class="pv-stat-card">
                        <span class="pv-stat-label">Moyenne</span>
                        <span class="pv-stat-value primary">
                            <?= ($statsFiliere['moyenne'] !== null) ? number_format($statsFiliere['moyenne'], 2, '.', '') : '—' ?>
                        </span>
                    </div>

                    <div class="pv-stat-card">
                        <span class="pv-stat-label">Moy.Min</span>
                        <span class="pv-stat-value danger">
                            <?= ($statsFiliere['min'] !== null) ? number_format($statsFiliere['min'], 2, '.', '') : '—' ?>
                        </span>
                    </div>

                    <div class="pv-stat-card">
                        <span class="pv-stat-label">Moy.Max</span>
                        <span class="pv-stat-value success">
                            <?= ($statsFiliere['max'] !== null) ? number_format($statsFiliere['max'], 2, '.', '') : '—' ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="<?= $base ?>js/script.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
