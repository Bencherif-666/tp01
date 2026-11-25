<?php
// messages/envoyer.php : Page d'envoi de messages réservée aux Administrateurs
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Réservé exclusivement aux administrateurs
exiger_admin('../');

$erreur = null;
$succes = $_GET['succes'] ?? null;
$destinataireIdChoisi = (int)($_POST['destinataire_id'] ?? 0);
$texteMessage = (string)($_POST['message'] ?? '');

// Traitement de l'envoi de message (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $texteMessage = trim($texteMessage);

    if ($destinataireIdChoisi <= 0) {
        $erreur = "Veuillez choisir un compte destinataire (Enseignant ou Analyse).";
    } elseif ($texteMessage === '') {
        $erreur = "Le texte du message ne peut pas être vide.";
    } else {
        // Vérifier que le destinataire existe et a le rôle Enseignant ou Analyse
        $stmtDest = $pdo->prepare('SELECT id, email, role FROM `user` WHERE id = :id LIMIT 1');
        $stmtDest->execute([':id' => $destinataireIdChoisi]);
        $destinataire = $stmtDest->fetch();

        if (!$destinataire || !in_array($destinataire['role'], ['Enseignant', 'Enseignants', 'Analyse', 'Analyste'], true)) {
            $erreur = "Le destinataire sélectionné n'est pas un compte Enseignant ou Analyse valide.";
        } else {
            $adminUser = utilisateur_connecte();
            $adminId = (int)($adminUser['id'] ?? 0);

            $stmtInsert = $pdo->prepare('
                INSERT INTO `message` (expediteur_id, destinataire_id, destinataire_role, contenu, date_envoi)
                VALUES (:expediteur_id, :destinataire_id, :destinataire_role, :contenu, NOW())
            ');
            $stmtInsert->execute([
                ':expediteur_id'     => $adminId,
                ':destinataire_id'   => (int)$destinataire['id'],
                ':destinataire_role' => (string)$destinataire['role'],
                ':contenu'           => $texteMessage,
            ]);

            header('Location: envoyer.php?succes=' . urlencode("Message envoyé avec succès à {$destinataire['email']} ({$destinataire['role']}) !"));
            exit;
        }
    }
}

// Récupération de tous les comptes Enseignants et Analyse pour le menu déroulant
$stmtComptes = $pdo->query("
    SELECT id, email, role
    FROM `user`
    WHERE role IN ('Enseignant', 'Enseignants', 'Analyse', 'Analyste')
    ORDER BY FIELD(role, 'Enseignant', 'Enseignants', 'Analyse', 'Analyste'), email ASC
");
$tousComptes = $stmtComptes ? $stmtComptes->fetchAll() : [];

$comptesEnseignants = array_filter($tousComptes, fn($c) => in_array($c['role'], ['Enseignant', 'Enseignants'], true));
$comptesAnalyse     = array_filter($tousComptes, fn($c) => in_array($c['role'], ['Analyse', 'Analyste'], true));

// Récupération des derniers messages envoyés par l'administrateur
$stmtHistorique = $pdo->query("
    SELECT m.id, m.contenu, m.destinataire_role, m.date_envoi, u.email AS destinataire_email
    FROM `message` m
    LEFT JOIN `user` u ON u.id = m.destinataire_id
    ORDER BY m.date_envoi DESC, m.id DESC
    LIMIT 20
");
$historique = $stmtHistorique ? $stmtHistorique->fetchAll() : [];

$base = '../';
$titre = 'Envoyer des messages';
require __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 960px; margin: 0 auto; padding: 10px 0 40px 0;">
    <div style="margin-bottom: 24px;">
        <p style="font-size: 15px; color: var(--text-muted); margin: 4px 0 0 0;">
            Envoyez directement des messages aux comptes enseignants ou d'analyse.
        </p>
    </div>

    <?php if ($succes): ?>
        <div style="background: #f0fdf4; border: 1.5px solid #bbf7d0; color: #15803d; padding: 14px 18px; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 24px;">
            ✓ <?= h($succes) ?>
        </div>
    <?php endif; ?>

    <?php if ($erreur): ?>
        <div style="background: #fef2f2; border: 1.5px solid #fecaca; color: #b91c1c; padding: 14px 18px; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 24px;">
            ⚠ <?= h($erreur) ?>
        </div>
    <?php endif; ?>

    <!-- Formulaire d'envoi de messages -->
    <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 28px 30px; box-shadow: var(--shadow-card); margin-bottom: 36px;">
        <form method="post" action="envoyer.php" autocomplete="off">
            <!-- 1. Dropdown des comptes Enseignants et Analyse -->
            <div style="margin-bottom: 20px;">
                <label for="destinataire_id" style="display: block; font-weight: 700; font-size: 14.5px; color: var(--text-heading); margin-bottom: 8px;">
                    Compte Destinataire : <span style="color: #dc2626;">*</span>
                </label>
                <select name="destinataire_id" id="destinataire_id" required style="width: 100%; max-width: 520px; padding: 11px 14px; border: 1.5px solid var(--border-medium); border-radius: var(--radius-md); font-size: 14.5px; background: #ffffff; box-sizing: border-box;">
                    <option value="">-- Sélectionnez un compte destinataire --</option>

                    <optgroup label="👨‍🏫 Comptes Enseignants (<?= count($comptesEnseignants) ?>)">
                        <?php foreach ($comptesEnseignants as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $destinataireIdChoisi === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['email']) ?> — [Enseignant]
                            </option>
                        <?php endforeach; ?>
                    </optgroup>

                    <optgroup label="📊 Comptes Analyse (<?= count($comptesAnalyse) ?>)">
                        <?php foreach ($comptesAnalyse as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $destinataireIdChoisi === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['email']) ?> — [Analyse]
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 6px;">
                    Ce menu liste exclusivement tous les comptes Enseignants et Analyse enregistrés.
                </div>
            </div>

            <!-- 2. Grande zone de texte sous le menu déroulant -->
            <div style="margin-bottom: 20px;">
                <label for="message" style="display: block; font-weight: 700; font-size: 14.5px; color: var(--text-heading); margin-bottom: 8px;">
                    Message : <span style="color: #dc2626;">*</span>
                </label>
                <textarea
                    name="message"
                    id="message"
                    rows="8"
                    placeholder="Saisissez ici le contenu du message à destination du compte sélectionné..."
                    required
                    style="width: 100%; padding: 14px 16px; border: 1.5px solid var(--border-medium); border-radius: var(--radius-md); font-family: inherit; font-size: 14.5px; line-height: 1.6; resize: vertical; box-sizing: border-box;"
                ><?= h($texteMessage) ?></textarea>
            </div>

            <!-- 3. Bouton Envoyer sous la zone de texte -->
            <div>
                <button type="submit" class="btn-blue" style="padding: 12px 28px; font-size: 15px; font-weight: 700; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 8px;">
                    ✉ Envoyer le message
                </button>
            </div>
        </form>
    </div>

    <!-- Historique des messages envoyés -->
    <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 24px 26px; box-shadow: var(--shadow-card);">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 700; color: var(--text-heading); display: flex; align-items: center; gap: 8px;">
            📋 Messages récemment envoyés par l'Admin (<?= count($historique) ?>)
        </h3>

        <?php if (empty($historique)): ?>
            <div style="padding: 24px; text-align: center; color: var(--text-muted); font-style: italic; background: #f8fafc; border-radius: var(--radius-md);">
                Aucun message n'a encore été envoyé.
            </div>
        <?php else: ?>
            <table class="table-liste" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 60px;">N°</th>
                        <th style="width: 180px;">Destinataire</th>
                        <th style="width: 120px;">Type</th>
                        <th style="width: 170px;">Date d'envoi</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $idx => $m): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text-muted);">
                                #<?= (int)$m['id'] ?>
                            </td>
                            <td>
                                <strong><?= h($m['destinataire_email'] ?? 'Compte supprimé') ?></strong>
                            </td>
                            <td>
                                <?php
                                $roleBadge = in_array($m['destinataire_role'], ['Analyse', 'Analyste'], true) ? 'analyse' : 'enseignant';
                                $roleIcon  = in_array($m['destinataire_role'], ['Analyse', 'Analyste'], true) ? '📊' : '👨‍🏫';
                                ?>
                                <span class="user-pill <?= $roleBadge ?>" style="font-size: 11.5px; padding: 3px 10px;">
                                    <?= $roleIcon . ' ' . h($m['destinataire_role']) ?>
                                </span>
                            </td>
                            <td style="font-size: 13px; color: var(--text-muted);">
                                <?= date('d/m/Y à H:i', strtotime($m['date_envoi'])) ?>
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

<script src="<?= $base ?>js/script.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
