<?php
// Affichage des erreurs (utile pendant le TP ; à retirer en production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Crée la colonne `drapeau` si elle n'existe pas encore
$colonneDrapeau = true;
try {
    if (!$pdo->query("SHOW COLUMNS FROM nationalite LIKE 'drapeau'")->fetch()) {
        $pdo->exec("ALTER TABLE nationalite ADD COLUMN drapeau VARCHAR(255) DEFAULT NULL");
    }
} catch (PDOException $e) {
    $colonneDrapeau = false;
}

// Retourne l'URL à utiliser dans <img src> (fichier local ou URL), ou null
if (!function_exists('drapeau_url')) {
    function drapeau_url(?string $drapeau, string $prefixe = '../'): ?string
    {
        $drapeau = trim((string)$drapeau);
        if ($drapeau === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $drapeau)) {
            return $drapeau;
        }
        $fichier = basename($drapeau);
        if (file_exists(__DIR__ . '/../uploads/drapeaux/' . $fichier)) {
            return $prefixe . 'uploads/drapeaux/' . rawurlencode($fichier);
        }
        return null;
    }
}

$numero = trim((string)($_GET['numero'] ?? ''));
$etudiant = null;
$notes = [];
$erreur = null;
$totalCoefficients = 0;
$totalPoints = 0.0;

// Note maximale / minimale (et les modules concernés, en cas d'égalité)
$noteMax = null;
$noteMin = null;
$modulesMax = [];
$modulesMin = [];

if ($numero !== '') {
    // Si la colonne drapeau est absente, on la remplace par NULL pour ne pas planter
    $champDrapeau = $colonneDrapeau ? 'n.drapeau' : 'NULL';

    $stmt = $pdo->prepare("
        SELECT p.id, p.numero, p.civilite, p.nom_prenom, p.filiere, p.adresse, p.photo,
               n.libelle AS nationalite, $champDrapeau AS drapeau
        FROM personne p
        LEFT JOIN nationalite n ON n.code = p.nationalite_code
        WHERE p.numero = :numero
        LIMIT 1
    ");
    $stmt->execute([':numero' => $numero]);
    $etudiant = $stmt->fetch() ?: null;

    if (!$etudiant) {
        $erreur = "Aucun étudiant trouvé pour le numéro « {$numero} ».";
    } else {
        $stmt = $pdo->prepare('
            SELECT m.code_module, m.designation, m.coefficient, n.note
            FROM note n
            JOIN module m ON m.id = n.module_id
            WHERE n.etudiant_id = :etudiant_id
            ORDER BY m.designation ASC
        ');
        $stmt->execute([':etudiant_id' => $etudiant['id']]);
        $notes = $stmt->fetchAll();

        foreach ($notes as $note) {
            $coefficient = (int)$note['coefficient'];
            $totalCoefficients += $coefficient;
            $totalPoints += (float)$note['note'] * $coefficient;
        }

        // Recherche du maximum et du minimum
        if ($notes) {
            $valeurs = array_map(function ($n) { return round((float)$n['note'], 2); }, $notes);
            $noteMax = max($valeurs);
            $noteMin = min($valeurs);

            // On ajoute TOUS les modules qui ont la même note max / min
            foreach ($notes as $note) {
                $v = round((float)$note['note'], 2);
                if ($v === $noteMax) { $modulesMax[] = $note['designation']; }
                if ($v === $noteMin) { $modulesMin[] = $note['designation']; }
            }
        }
    }
}

$moyenne = $totalCoefficients > 0 ? $totalPoints / $totalCoefficients : 0;
$admis   = $moyenne >= 10;   // PASSED si la moyenne est >= 10

$base = '../';
$titre = 'Bulletin de Notes';
require __DIR__ . '/../includes/header.php';
?>

<style>
    .bl-page {
        max-width: 960px;
        margin: 0 auto;
        padding: 20px 16px 40px;
        font-family: "Segoe UI", Arial, sans-serif;
        color: #1f2937;
    }

    /* Search bar */
    .bl-search {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 14px 18px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .bl-search label { font-weight: 600; }
    .bl-search input[type="text"] {
        flex: 1 1 180px;
        padding: 9px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 15px;
    }
    .bl-search input[type="text"]:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,.15);
    }
    .bl-search button {
        padding: 9px 20px;
        background: #2563eb;
        color: #fff;
        border: 0;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
    }
    .bl-search button:hover { background: #1d4ed8; }

    /* Error message */
    .bl-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    /* Student card */
    .bl-card {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .bl-info {
        flex: 1 1 320px;
        display: grid;
        gap: 10px;
    }
    .bl-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    .bl-row strong {
        min-width: 130px;
        color: #475569;
    }

    /* Flag next to the nationality */
    .bl-flag {
        height: 22px;
        width: auto;
        border: 1px solid #cbd5e1;
        vertical-align: middle;
        margin-left: 6px;
        display: inline-block;
    }
    .bl-nat { display: inline-flex; align-items: center; gap: 4px; }

    .bl-photo {
        flex: 0 0 auto;
        width: 150px;
        height: 180px;
        border-radius: 10px;
        border: 2px solid #e5e7eb;
        background: #f1f5f9;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .bl-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .bl-photo em { color: #94a3b8; font-size: 13px; }

    /* ===== Table: sharp edges, simple shape ===== */
    .bl-table-wrap {
        overflow-x: auto;
        background: #fff;
        border: 1px solid #64748b;
        border-radius: 0;
    }
    .bl-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 15px;
    }
    .bl-table th, .bl-table td {
        padding: 11px 14px;
        text-align: left;
        border: 1px solid #cbd5e1;
        border-radius: 0;
    }
    .bl-table thead th {
        background: #1e3a8a;
        color: #fff;
        font-weight: 600;
        white-space: nowrap;
        border-color: #1e3a8a;
    }
    .bl-table tbody tr:nth-child(even) { background: #f8fafc; }
    .bl-table tbody tr:hover { background: #eff6ff; }
    .bl-table .num { text-align: right; font-variant-numeric: tabular-nums; }

    /* Footer rows */
    .bl-table tfoot th {
        background: #fff;
        text-align: right;
        font-weight: 600;
        color: #334155;
        border-top: 2px solid #64748b;
    }
    .bl-table tfoot tr + tr th,
    .bl-table tfoot tr + tr td { border-top: 1px solid #cbd5e1; }
    .bl-table tfoot td {
        background: #fff;
        text-align: right;
        vertical-align: middle;
    }
    .bl-table tfoot tr:first-child td { border-top: 2px solid #64748b; }

    /* Light orange bubbles for the two sums */
    .bl-bubble {
        display: inline-block;
        min-width: 90px;
        padding: 6px 20px;
        background: #ffedd5;
        color: #c2410c;
        border: 1px solid #fdba74;
        border-radius: 999px;
        font-size: 16px;
        font-weight: 700;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }

    /* Average row (square, blue) */
    .bl-table tfoot tr.bl-moyenne th,
    .bl-table tfoot tr.bl-moyenne td {
        background: #dbeafe;
        color: #1e3a8a;
        font-size: 17px;
        font-weight: 700;
    }

    /* Max / Min rows */
    .bl-table tfoot tr.bl-max th,
    .bl-table tfoot tr.bl-max td { background: #f0fdf4; }
    .bl-table tfoot tr.bl-min th,
    .bl-table tfoot tr.bl-min td { background: #fef2f2; }
    .bl-extreme-note {
        font-size: 17px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .bl-max .bl-extreme-note { color: #15803d; }
    .bl-min .bl-extreme-note { color: #b91c1c; }
    .bl-extreme-mod {
        display: block;
        margin-top: 4px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
    }

    /* PASSED / FAILED mark */
    .bl-result-cell { text-align: center !important; padding: 14px !important; }
    .bl-mark {
        display: inline-block;
        min-width: 160px;
        padding: 10px 28px;
        border-radius: 8px;
        font-size: 20px;
        font-weight: 800;
        letter-spacing: 2px;
        color: #fff;
        text-align: center;
    }
    .bl-mark.passed { background: #16a34a; }
    .bl-mark.failed { background: #dc2626; }

    .bl-ok   { color: #15803d; font-weight: 600; }
    .bl-fail { color: #b91c1c; font-weight: 600; }

    @media (max-width: 600px) {
        .bl-card { justify-content: center; }
        .bl-row strong { min-width: 100%; }
    }
</style>

<div class="bl-page">

    <!-- Search form: always visible, so you can search again after an error -->
    <form method="get" action="afficher_bulletin.php" class="bl-search">
        <label for="numero-etudiant">Numéro d'étudiant :</label>
        <input type="text" name="numero" id="numero-etudiant" value="<?= h($numero) ?>" placeholder="ex: 15" required>
        <button type="submit">Rechercher</button>
    </form>

    <?php if ($erreur): ?>
        <p class="bl-error"><?= h($erreur) ?></p>

    <?php elseif ($etudiant): ?>
        <?php $srcDrapeau = drapeau_url($etudiant['drapeau'] ?? null); ?>
        <div class="bl-card">
            <section class="bl-info" aria-label="Informations de l'étudiant">
                <div class="bl-row"><strong>Numéro :</strong> <span><?= h($etudiant['numero']) ?></span></div>
                <div class="bl-row"><strong>Civilité :</strong> <span><?= h($etudiant['civilite'] ?: '—') ?></span></div>
                <div class="bl-row"><strong>Nom / Prénom :</strong> <span><?= h($etudiant['nom_prenom']) ?></span></div>
                <div class="bl-row"><strong>Filière :</strong> <span><?= h($etudiant['filiere']) ?></span></div>
                <div class="bl-row"><strong>Adresse :</strong> <span><?= h($etudiant['adresse']) ?></span></div>
                <div class="bl-row">
                    <strong>Nationalité :</strong>
                    <span class="bl-nat">
                        <?= h($etudiant['nationalite'] ?: '—') ?>
                        <?php if ($srcDrapeau): ?>
                            <img class="bl-flag" src="<?= h($srcDrapeau) ?>"
                                 alt="Drapeau <?= h($etudiant['nationalite']) ?>">
                        <?php endif; ?>
                    </span>
                </div>
            </section>

            <div id="cadre-photo" class="bl-photo">
                <?php if (!empty($etudiant['photo']) && file_exists(__DIR__ . '/../uploads/' . $etudiant['photo'])): ?>
                    <img src="../uploads/<?= h($etudiant['photo']) ?>" alt="Photo de <?= h($etudiant['nom_prenom']) ?>">
                <?php else: ?>
                    <em>Aucune photo</em>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$notes): ?>
            <p class="bl-error">Aucune note n'est enregistrée pour cet étudiant.</p>
        <?php else: ?>
            <section id="fiche-bulletin-print" class="bulletin-document">
                <header class="bulletin-document-header">
                    <h3>Bulletin de notes</h3>
                    <p><?= h($etudiant['civilite'] ?: '') ?> <?= h($etudiant['nom_prenom']) ?> — <?= h($etudiant['filiere']) ?></p>
                </header>
            <div class="bl-table-wrap">
                <table class="bl-table">
                    <thead>
                        <tr>
                            <th>N°Etudiant</th>
                            <th>Filière</th>
                            <th>Code module</th>
                            <th>Nom module</th>
                            <th class="num">Coefficient</th>
                            <th class="num">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $note): ?>
                            <tr>
                                <td><?= h($etudiant['numero']) ?></td>
                                <td><?= h($etudiant['filiere']) ?></td>
                                <td><?= h($note['code_module']) ?></td>
                                <td><?= h($note['designation']) ?></td>
                                <td class="num"><?= h($note['coefficient']) ?></td>
                                <td class="num <?= (float)$note['note'] >= 10 ? 'bl-ok' : 'bl-fail' ?>">
                                    <?= number_format((float)$note['note'], 2, ',', ' ') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">Somme coefficients</th>
                            <td colspan="2">
                                <span class="bl-bubble"><?= $totalCoefficients ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="4">Somme Coef*Notes</th>
                            <td colspan="2">
                                <span class="bl-bubble"><?= number_format($totalPoints, 2, ',', ' ') ?></span>
                            </td>
                        </tr>
                        <tr class="bl-moyenne">
                            <th colspan="4">Moyenne</th>
                            <td colspan="2"><?= number_format($moyenne, 2, ',', ' ') ?></td>
                        </tr>

                        <!-- Note maximale (tous les modules ex aequo sont listés) -->
                        <tr class="bl-max">
                            <th colspan="4">Note maximale</th>
                            <td colspan="2">
                                <span class="bl-extreme-note"><?= number_format($noteMax, 2, ',', ' ') ?></span>
                                <span class="bl-extreme-mod"><?= h(implode(', ', $modulesMax)) ?></span>
                            </td>
                        </tr>

                        <!-- Note minimale (tous les modules ex aequo sont listés) -->
                        <tr class="bl-min">
                            <th colspan="4">Note minimale</th>
                            <td colspan="2">
                                <span class="bl-extreme-note"><?= number_format($noteMin, 2, ',', ' ') ?></span>
                                <span class="bl-extreme-mod"><?= h(implode(', ', $modulesMin)) ?></span>
                            </td>
                        </tr>

                        <!-- Résultat : PASSED (vert) ou FAILED (rouge) -->
                        <tr>
                            <td colspan="6" class="bl-result-cell">
                                <?php if ($admis): ?>
                                    <span class="bl-mark passed">PASSED</span>
                                <?php else: ?>
                                    <span class="bl-mark failed">FAILED</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
