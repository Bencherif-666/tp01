<?php
// bulletin_user.php : Consultation du Bulletin de Notes pour les utilisateurs
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/fonctions.php';

// Exiger que l'utilisateur soit connecté
exiger_connexion('');

$numeroRecherche = trim((string)($_GET['numero'] ?? $_GET['recherche'] ?? ''));
$etudiant = null;
$notes = [];
$erreur = null;
$messageFlashErreur = $_GET['erreur'] ?? null;
$totalCoefficients = 0;
$totalPoints = 0.0;
$moyenne = 0.0;
$admis = false;

if ($numeroRecherche !== '') {
    // Vérifier si la colonne drapeau existe dans nationalite
    $champDrapeau = 'NULL AS drapeau';
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM nationalite LIKE 'drapeau'")->fetch();
        if ($checkCol) {
            $champDrapeau = 'n.drapeau';
        }
    } catch (Exception $e) {
        $champDrapeau = 'NULL AS drapeau';
    }

    $stmt = $pdo->prepare("
        SELECT p.id, p.numero, p.civilite, p.nom_prenom, p.filiere, p.adresse,
               p.code_postal, p.localite, p.pays, p.photo,
               n.libelle AS nationalite, $champDrapeau
        FROM personne p
        LEFT JOIN nationalite n ON n.code = p.nationalite_code
        WHERE p.numero = :numero OR p.id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':numero' => $numeroRecherche,
        ':id'     => ctype_digit($numeroRecherche) ? (int)$numeroRecherche : 0,
    ]);
    $etudiant = $stmt->fetch();

    if ($etudiant) {
        $stmtNotes = $pdo->prepare('
            SELECT m.code_module, m.designation, m.coefficient, n.note, n.date_saisie
            FROM note n
            JOIN module m ON m.id = n.module_id
            WHERE n.etudiant_id = :eid
            ORDER BY m.designation ASC
        ');
        $stmtNotes->execute([':eid' => $etudiant['id']]);
        $notes = $stmtNotes->fetchAll();

        foreach ($notes as $noteItem) {
            $coeff = (int)$noteItem['coefficient'];
            $totalCoefficients += $coeff;
            $totalPoints += (float)$noteItem['note'] * $coeff;
        }

        $moyenne = $totalCoefficients > 0 ? ($totalPoints / $totalCoefficients) : 0;
        $admis = ($moyenne >= 10);
    } else {
        $erreur = "Aucun étudiant trouvé pour le numéro « " . $numeroRecherche . " ». Vérifiez le numéro de bulletin.";
    }
}

$base = '';
$titre = 'Bulletin de Notes';
require __DIR__ . '/includes/header.php';
?>

<?php if ($messageFlashErreur): ?>
    <div class="erreur" style="margin-bottom: 20px;">
        ⚠️ <?= h($messageFlashErreur) ?>
    </div>
<?php endif; ?>

<!-- Formulaire de recherche de bulletin -->
<div style="background: var(--bg-subtle); border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 24px;">
    <form method="get" action="bulletin_user.php" style="margin: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 14px;">
        <label for="numero-bulletin" style="font-weight: 700; font-size: 14.5px; color: var(--text-heading);">
            Numéro de bulletin :
        </label>
        <input type="text" name="numero" id="numero-bulletin" value="<?= h($numeroRecherche) ?>" placeholder="Entrez le numéro d'étudiant (ex: 15, 10, 4...)" style="width: 280px; padding: 10px 14px;" required autofocus>
        <button type="submit" class="btn-blue" style="margin: 0;">Rechercher</button>
        <?php if ($numeroRecherche !== ''): ?>
            <a href="bulletin_user.php" style="margin-left: 8px; font-size: 13.5px; color: var(--text-muted); text-decoration: underline;">Réinitialiser</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($erreur): ?>
    <div class="erreur" style="margin-bottom: 24px;">
        <?= h($erreur) ?>
    </div>
<?php endif; ?>

<?php if ($etudiant): ?>
    <div id="fiche-bulletin-print" style="margin-top: 20px;">
        <!-- En-tête et carte d'information étudiant -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <h3 style="margin: 0; color: var(--text-heading); font-size: 18px;">
                Relevé de notes officiel &bull; <?= h($etudiant['nom_prenom']) ?>
            </h3>
            <button type="button" onclick="window.print()" class="btn-blue" style="font-size: 13px; padding: 7px 14px;">
                🖨️ Imprimer le bulletin
            </button>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 24px; background: #ffffff; border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 22px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
            <div style="width: 140px; height: 160px; border: 1.5px solid var(--border-medium); border-radius: var(--radius-md); overflow: hidden; background: var(--bg-subtle); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <?php if (!empty($etudiant['photo']) && file_exists(__DIR__ . '/uploads/' . $etudiant['photo'])): ?>
                    <img src="uploads/<?= h($etudiant['photo']) ?>" alt="Photo de <?= h($etudiant['nom_prenom']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); font-size: 12.5px; padding: 10px;">
                        <em>Photo<br>Étudiant</em>
                    </div>
                <?php endif; ?>
            </div>

            <div style="flex: 1 1 300px; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px 20px; font-size: 14px;">
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 12px; text-transform: uppercase; font-weight: 700;">Numéro Étudiant</span>
                    <strong style="font-size: 16px; color: var(--primary);"><?= h($etudiant['numero']) ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 12px; text-transform: uppercase; font-weight: 700;">Civilité & Nom / Prénom</span>
                    <strong><?= h($etudiant['civilite']) ?> <?= h($etudiant['nom_prenom']) ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 12px; text-transform: uppercase; font-weight: 700;">Filière</span>
                    <span style="display: inline-block; background: var(--primary-light); color: var(--primary); padding: 3px 10px; border-radius: var(--radius-full); font-weight: 700; font-size: 12.5px;">
                        <?= h($etudiant['filiere'] ?: '—') ?>
                    </span>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 12px; text-transform: uppercase; font-weight: 700;">Nationalité</span>
                    <span>
                        <?= h($etudiant['nationalite'] ?: '—') ?>
                        <?php if (!empty($etudiant['drapeau'])): ?>
                            <img src="<?= h($etudiant['drapeau']) ?>" alt="Drapeau" style="height: 14px; vertical-align: middle; margin-left: 4px; border: 1px solid #cbd5e1;">
                        <?php endif; ?>
                    </span>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 12px; text-transform: uppercase; font-weight: 700;">Adresse</span>
                    <span><?= h($etudiant['adresse'] ?: '—') ?></span>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 12px; text-transform: uppercase; font-weight: 700;">Localité & Pays</span>
                    <span><?= h($etudiant['localite'] ?: '') ?> <?= h($etudiant['code_postal'] ? '(' . $etudiant['code_postal'] . ')' : '') ?> &bull; <?= h($etudiant['pays'] ?: '—') ?></span>
                </div>
            </div>
        </div>

        <!-- Tableau des notes -->
        <?php if ($notes): ?>
            <table class="liste" style="width: 100%; margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="width: 35%;">Module</th>
                        <th style="width: 15%;">Code</th>
                        <th style="width: 15%;">Coefficient</th>
                        <th style="width: 15%;">Note / 20</th>
                        <th style="width: 20%;">Note Pondérée</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes as $item):
                        $noteVal = (float)$item['note'];
                        $coeffVal = (int)$item['coefficient'];
                        $ponderee = $noteVal * $coeffVal;
                    ?>
                        <tr>
                            <td><strong><?= h($item['designation']) ?></strong></td>
                            <td><?= h($item['code_module']) ?></td>
                            <td><?= $coeffVal ?></td>
                            <td>
                                <strong style="color: <?= $noteVal >= 10 ? '#15803d' : '#b91c1c' ?>; font-size: 14.5px;">
                                    <?= number_format($noteVal, 2, ',', ' ') ?> / 20
                                </strong>
                            </td>
                            <td><?= number_format($ponderee, 2, ',', ' ') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: var(--bg-subtle); font-weight: bold; border-top: 2px solid var(--border-medium);">
                        <td colspan="2" style="text-align: right;">Total des coefficients :</td>
                        <td><?= $totalCoefficients ?></td>
                        <td style="text-align: right;">Moyenne Générale :</td>
                        <td style="font-size: 16px; color: <?= $admis ? '#15803d' : '#b91c1c' ?>;">
                            <?= number_format($moyenne, 2, ',', ' ') ?> / 20
                            <span style="margin-left: 8px; font-size: 12px; padding: 2px 8px; border-radius: var(--radius-full); background: <?= $admis ? '#dcfce7; color: #166534;' : '#fee2e2; color: #991b1b;' ?>">
                                <?= $admis ? 'ADMIS' : 'AJOURNÉ' ?>
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <p style="margin-top: 20px; color: var(--text-muted); font-style: italic;">
                Aucune note n'a été enregistrée pour le moment pour cet étudiant.
            </p>
        <?php endif; ?>
    </div>
<?php elseif ($numeroRecherche === ''): ?>
    <div style="background: #ffffff; border: 1px dashed var(--border-medium); border-radius: var(--radius-lg); padding: 40px 20px; text-align: center; color: var(--text-muted); margin-top: 20px;">
        <span style="font-size: 32px; display: block; margin-bottom: 10px;">📋</span>
        <h3 style="margin: 0 0 6px 0; color: var(--text-heading);">Consultation de votre relevé de notes</h3>
        <p style="margin: 0; font-size: 14px;">Entrez votre <strong>numéro de bulletin</strong> ci-dessus puis cliquez sur <strong>Rechercher</strong> pour consulter vos notes et votre moyenne générale.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
