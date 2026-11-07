<?php
// nationalite/liste.php : affiche toutes les nationalités lues dans MySQL (avec drapeau)

// Affichage des erreurs (utile pendant le TP ; à retirer en production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Crée la colonne `drapeau` si elle n'existe pas encore
try {
    $existe = $pdo->query("SHOW COLUMNS FROM nationalite LIKE 'drapeau'")->fetch();
    if (!$existe) {
        $pdo->exec("ALTER TABLE nationalite ADD COLUMN drapeau VARCHAR(255) DEFAULT NULL");
    }
} catch (PDOException $e) {
    // on continue : la requête ci-dessous gère le cas
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

try {
    $stmt = $pdo->prepare('SELECT code, libelle, drapeau FROM nationalite ORDER BY libelle');
    $stmt->execute();
} catch (PDOException $e) {
    // Secours : sans la colonne drapeau
    $stmt = $pdo->prepare('SELECT code, libelle, NULL AS drapeau FROM nationalite ORDER BY libelle');
    $stmt->execute();
}
$nationalites = $stmt->fetchAll();

$base  = '../';
$titre = 'Liste des nationalités';
require __DIR__ . '/../includes/header.php';
?>
<?php if (!$nationalites): ?>
    <p>Aucune nationalité enregistrée.</p>
<?php else: ?>
    <table class="liste">
        <tr><th>Drapeau</th><th>Code</th><th>Nationalité</th></tr>
        <?php foreach ($nationalites as $n): ?>
            <?php $src = drapeau_url($n['drapeau'] ?? null); ?>
            <tr>
                <td>
                    <?php if ($src): ?>
                        <img src="<?= h($src) ?>" alt="Drapeau <?= h($n['libelle']) ?>"
                             style="height:24px;border:1px solid #ccc;vertical-align:middle;">
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?= h($n['code']) ?></td>
                <td><?= h($n['libelle']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
<p><a href="nationalite.php">Ajouter une nationalité</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
