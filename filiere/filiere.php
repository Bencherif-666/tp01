<?php
// filiere/filiere.php : formulaire de gestion des filières (ID et nom)
// + étudiants de la filière recherchée (tableau, camembert, histogramme, compteurs)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

/* ================== FONCTIONS (intégrées, aucun fichier en plus) ================== */

if (!function_exists('drapeau_url')) {
    function drapeau_url(?string $drapeau, string $prefixe = '../'): ?string
    {
        $drapeau = trim((string)$drapeau);
        if ($drapeau === '') { return null; }
        if (preg_match('#^https?://#i', $drapeau)) { return $drapeau; }
        $fichier = basename($drapeau);
        if (file_exists(__DIR__ . '/../uploads/drapeaux/' . $fichier)) {
            return $prefixe . 'uploads/drapeaux/' . rawurlencode($fichier);
        }
        return null;
    }
}

if (!function_exists('ef_point')) {
    function ef_point(float $cx, float $cy, float $r, float $angle): array
    {
        return [$cx + $r * cos($angle), $cy + $r * sin($angle)];
    }
}

if (!function_exists('ef_camembert')) {
    function ef_camembert(int $reussis, int $echoues): string
    {
        $total = $reussis + $echoues;
        if ($total === 0) { return '<p>Aucune moyenne disponible pour le diagramme.</p>'; }
        $cx = 110; $cy = 110; $r = 90;
        $vert = '#16a34a'; $rouge = '#dc2626';
        $svg = '<svg viewBox="0 0 220 220" width="220" height="220" role="img" aria-label="Diagramme circulaire réussis / échoués">';
        if ($echoues === 0) {
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $vert . '"/>';
        } elseif ($reussis === 0) {
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $rouge . '"/>';
        } else {
            $debut  = -M_PI / 2;
            $angle  = 2 * M_PI * $reussis / $total;
            $milieu = $debut + $angle;
            [$x1, $y1] = ef_point($cx, $cy, $r, $debut);
            [$x2, $y2] = ef_point($cx, $cy, $r, $milieu);
            $grand1 = $angle > M_PI ? 1 : 0;
            $grand2 = (2 * M_PI - $angle) > M_PI ? 1 : 0;
            $svg .= sprintf('<path d="M%1$.2f %2$.2f L%3$.2f %4$.2f A%5$d %5$d 0 %6$d 1 %7$.2f %8$.2f Z" fill="%9$s"/>',
                $cx, $cy, $x1, $y1, $r, $grand1, $x2, $y2, $vert);
            $svg .= sprintf('<path d="M%1$.2f %2$.2f L%3$.2f %4$.2f A%5$d %5$d 0 %6$d 1 %7$.2f %8$.2f Z" fill="%9$s"/>',
                $cx, $cy, $x2, $y2, $r, $grand2, $x1, $y1, $rouge);
        }
        return $svg . '</svg>';
    }
}

// Histogramme à DEUX barres : nombre de réussis (vert) et d'échoués (rouge)
if (!function_exists('ef_histogramme')) {
    function ef_histogramme(int $reussis, int $echoues): string
    {
        if ($reussis + $echoues === 0) {
            return '<p>Aucune moyenne disponible pour l\'histogramme.</p>';
        }
        $max        = max($reussis, $echoues);
        $gauche     = 50;
        $basY       = 220;
        $hauteurMax = 170;
        $largeurBarre = 80;

        $svg = '<svg viewBox="0 0 320 262" width="100%" style="max-width:320px" role="img" aria-label="Histogramme réussis / échoués">';

        // axes
        $svg .= '<line x1="' . $gauche . '" y1="' . $basY . '" x2="310" y2="' . $basY . '" stroke="#64748b"/>';
        $svg .= '<line x1="' . $gauche . '" y1="' . ($basY - $hauteurMax - 10) . '" x2="' . $gauche . '" y2="' . $basY . '" stroke="#64748b"/>';

        // graduations : 0 et maximum
        $svg .= '<text x="' . ($gauche - 6) . '" y="' . ($basY + 4) . '" font-size="11" text-anchor="end" fill="#475569">0</text>';
        $svg .= '<text x="' . ($gauche - 6) . '" y="' . ($basY - $hauteurMax + 4) . '" font-size="11" text-anchor="end" fill="#475569">' . $max . '</text>';
        $svg .= '<line x1="' . $gauche . '" y1="' . ($basY - $hauteurMax) . '" x2="310" y2="' . ($basY - $hauteurMax) . '" stroke="#e2e8f0" stroke-dasharray="4 3"/>';

        $barres = [
            ['nb' => $reussis, 'x' => 80,  'couleur' => '#16a34a', 'texte' => 'PASSED'],
            ['nb' => $echoues, 'x' => 190, 'couleur' => '#dc2626', 'texte' => 'FAILED'],
        ];
        foreach ($barres as $b) {
            $hauteur = ($b['nb'] / $max) * $hauteurMax;
            $y = $basY - $hauteur;
            if ($b['nb'] > 0) {
                $svg .= sprintf('<rect x="%d" y="%.1f" width="%d" height="%.1f" fill="%s"/>',
                    $b['x'], $y, $largeurBarre, $hauteur, $b['couleur']);
            }
            $svg .= sprintf('<text x="%.1f" y="%.1f" font-size="16" font-weight="700" text-anchor="middle" fill="#1f2937">%d</text>',
                $b['x'] + $largeurBarre / 2, $y - 6, $b['nb']);
            $svg .= sprintf('<text x="%.1f" y="%d" font-size="13" font-weight="600" text-anchor="middle" fill="#475569">%s</text>',
                $b['x'] + $largeurBarre / 2, $basY + 18, $b['texte']);
        }
        $svg .= '<text x="12" y="120" font-size="11" text-anchor="middle" fill="#475569" transform="rotate(-90 12 120)">Nombre d\'étudiants</text>';
        return $svg . '</svg>';
    }
}

if (!function_exists('afficher_etudiants_filiere')) {
    function afficher_etudiants_filiere(PDO $pdo, array $filiere): void
    {
        static $styleAffiche = false;

        $colonneDrapeau = true;
        try {
            if (!$pdo->query("SHOW COLUMNS FROM nationalite LIKE 'drapeau'")->fetch()) {
                $pdo->exec("ALTER TABLE nationalite ADD COLUMN drapeau VARCHAR(255) DEFAULT NULL");
            }
        } catch (PDOException $e) {
            $colonneDrapeau = false;
        }
        $champDrapeau = $colonneDrapeau ? 'n.drapeau' : 'NULL';

        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.numero, p.nom_prenom,
                       n.libelle AS nationalite, $champDrapeau AS drapeau,
                       COUNT(nt.id) AS nb_notes,
                       SUM(nt.note * m.coefficient) / NULLIF(SUM(m.coefficient), 0) AS moyenne
                FROM personne p
                LEFT JOIN nationalite n ON n.code = p.nationalite_code
                LEFT JOIN note nt ON nt.etudiant_id = p.id
                LEFT JOIN module m ON m.id = nt.module_id
                WHERE p.filiere = :nom
                GROUP BY p.id, p.numero, p.nom_prenom, n.libelle, $champDrapeau
                ORDER BY p.nom_prenom
            ");
            $stmt->execute([':nom' => $filiere['nom']]);
            $etudiants = $stmt->fetchAll();
        } catch (PDOException $e) {
            echo '<p class="erreur">Erreur lors du chargement des étudiants : ' . h($e->getMessage()) . '</p>';
            return;
        }

        $reussis = 0; $echoues = 0; $sansNotes = 0;
        foreach ($etudiants as $e) {
            if ($e['moyenne'] === null) { $sansNotes++; continue; }
            if ((float)$e['moyenne'] >= 10) { $reussis++; } else { $echoues++; }
        }

        if (!$styleAffiche) {
            $styleAffiche = true;
            ?>
            <style>
                .ef-bloc { margin: 24px 0; }
                .ef-flag { height: 20px; border: 1px solid #cbd5e1; vertical-align: middle; margin-left: 6px; }
                .ef-ok   { color: #15803d; font-weight: 700; }
                .ef-fail { color: #b91c1c; font-weight: 700; }
                .ef-badge { display: inline-block; min-width: 80px; padding: 4px 12px; border-radius: 6px; color: #fff; font-weight: 700; font-size: 13px; letter-spacing: 1px; text-align: center; }
                .ef-badge-ok { background: #16a34a; }
                .ef-badge-ko { background: #dc2626; }
                .ef-compteurs { display: flex; flex-wrap: wrap; gap: 12px; margin: 18px 0; }
                .ef-compteur { padding: 12px 22px; border-radius: 8px; color: #fff; font-weight: 700; font-size: 16px; text-align: center; }
                .ef-compteur span { display: block; font-size: 28px; }
                .ef-c-ok { background: #16a34a; }
                .ef-c-ko { background: #dc2626; }
                .ef-c-no { background: #64748b; }
                .ef-c-tot { background: #1e3a8a; }
                .ef-graphiques { display: flex; flex-wrap: wrap; gap: 32px; align-items: flex-start; margin-top: 10px; }
                .ef-graphiques h4 { margin: 0 0 8px; }
                .ef-legende { margin-top: 8px; font-size: 14px; }
                .ef-legende i { display: inline-block; width: 12px; height: 12px; margin-right: 6px; vertical-align: middle; }
            </style>
            <?php
        }

        echo '<div class="ef-bloc">';
        echo '<h3>Étudiants de la filière « ' . h($filiere['nom']) . ' » (ID ' . h($filiere['id']) . ')</h3>';

        if (!$etudiants) {
            echo '<p>Aucun étudiant dans cette filière.</p></div>';
            return;
        }

        echo '<table class="liste"><tr><th>ID</th><th>N° étudiant</th><th>Nom / Prénom</th><th>Nationalité</th><th>Moyenne</th><th>Résultat</th></tr>';
        foreach ($etudiants as $e) {
            $src = drapeau_url($e['drapeau'] ?? null);
            echo '<tr>';
            echo '<td>' . h($e['id']) . '</td>';
            echo '<td>' . h($e['numero']) . '</td>';
            echo '<td>' . h($e['nom_prenom']) . '</td>';
            echo '<td>' . h($e['nationalite'] ?: '—');
            if ($src) {
                echo ' <img class="ef-flag" src="' . h($src) . '" alt="Drapeau ' . h($e['nationalite']) . '">';
            }
            echo '</td>';
            if ($e['moyenne'] === null) {
                echo '<td>—</td>';
                echo '<td>Sans notes</td>';
            } else {
                $m = (float)$e['moyenne'];
                echo '<td class="' . ($m >= 10 ? 'ef-ok' : 'ef-fail') . '">' . number_format($m, 2, ',', ' ') . '</td>';
                if ($m >= 10) {
                    echo '<td><span class="ef-badge ef-badge-ok">PASSED</span></td>';
                } else {
                    echo '<td><span class="ef-badge ef-badge-ko">FAILED</span></td>';
                }
            }
            echo '</tr>';
        }
        echo '</table>';

        echo '<div class="ef-compteurs">';
        echo '<div class="ef-compteur ef-c-ok"><span>' . $reussis . '</span>Réussis (PASSED)</div>';
        echo '<div class="ef-compteur ef-c-ko"><span>' . $echoues . '</span>Échoués (FAILED)</div>';
        if ($sansNotes > 0) {
            echo '<div class="ef-compteur ef-c-no"><span>' . $sansNotes . '</span>Sans notes</div>';
        }
        echo '<div class="ef-compteur ef-c-tot"><span>' . count($etudiants) . '</span>Total étudiants</div>';
        echo '</div>';

        $totalAvecMoyenne = $reussis + $echoues;
        echo '<div class="ef-graphiques">';
        echo '<div><h4>Réussite / Échec</h4>' . ef_camembert($reussis, $echoues);
        if ($totalAvecMoyenne > 0) {
            echo '<div class="ef-legende">'
               . '<div><i style="background:#16a34a"></i>Réussis : ' . $reussis . ' (' . round($reussis * 100 / $totalAvecMoyenne, 1) . ' %)</div>'
               . '<div><i style="background:#dc2626"></i>Échoués : ' . $echoues . ' (' . round($echoues * 100 / $totalAvecMoyenne, 1) . ' %)</div>'
               . '</div>';
        }
        echo '</div>';
        echo '<div><h4>Histogramme : réussis / échoués</h4>' . ef_histogramme($reussis, $echoues) . '</div>';
        echo '</div>';
        echo '</div>';
    }
}

/* ================== PAGE ================== */

$idRecherche = trim((string)($_GET['id'] ?? ''));
$filiere = null;
$messageRecherche = null;

if ($idRecherche !== '') {
    $idValide = filter_var($idRecherche, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idValide !== false) {
        $stmt = $pdo->prepare('SELECT * FROM filiere WHERE id = :id');
        $stmt->execute([':id' => $idValide]);
        $filiere = $stmt->fetch() ?: null;
        if ($filiere) {
            $messageRecherche = "Filière trouvée (ID {$idValide}) : vous pouvez la modifier ou la supprimer.";
        } else {
            $messageRecherche = "Aucune filière avec l'ID {$idValide}. Remplissez le nom pour la créer avec cet ID.";
        }
    }
}

$idCourant = $filiere['id'] ?? $idRecherche;
$nomCourant = $filiere['nom'] ?? '';

$base = '../';
$titre = 'Gestion des filières';
require __DIR__ . '/../includes/header.php';
?>

<form method="get" action="filiere.php" class="form-recherche">
    <label for="recherche-id">Rechercher par ID :</label>
    <input type="number" name="id" id="recherche-id" min="1" value="<?= h($idRecherche) ?>" placeholder="ex: 1">
    <button type="submit">Rechercher</button>
    <?php if ($idRecherche !== ''): ?>
        <a href="filiere.php" style="margin-left: 10px;">Réinitialiser</a>
    <?php endif; ?>
</form>

<?php if ($messageRecherche): ?>
    <p class="<?= $filiere ? 'ok' : '' ?>"><?= h($messageRecherche) ?></p>
<?php endif; ?>

<form id="form-filiere" method="post" action="enregistrer.php">
    <table class="formulaire">
        <tr>
            <td>ID :</td>
            <td>
                <input type="number" name="id" id="filiere-id" min="1" size="10" placeholder="Auto ou spécifier" value="<?= h($idCourant) ?>">
                <span class="petit" style="color: var(--text-muted); font-size: 12px; margin-left: 8px;">(Laisser vide pour auto-incrémentation lors de l'ajout)</span>
            </td>
        </tr>
        <tr>
            <td>Nom de la filière :</td>
            <td>
                <input type="text" name="nom" id="filiere-nom" maxlength="50" size="30" placeholder="ex: 3ISIL" value="<?= h($nomCourant) ?>" required>
            </td>
        </tr>
    </table>

    <button type="submit" name="action" value="ajouter">Ajouter</button>
    <button type="submit" name="action" value="modifier" formaction="enregistrer.php?action=modifier">Modifier</button>
    <button type="submit" name="action" value="supprimer" formaction="supprimer.php" formnovalidate onclick="return confirm('Supprimer cette filière ?');">Supprimer</button>
    <button type="submit" formaction="liste.php" formmethod="get" formnovalidate>Affichage Liste</button>
    <button type="reset">Réinitialiser</button>
</form>

<?php
if ($filiere) {
    afficher_etudiants_filiere($pdo, $filiere);
}
?>

<script src="<?= $base ?>js/script.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
