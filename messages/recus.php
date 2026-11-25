<?php
// messages/recus.php : Boîte de réception des messages (Comptes Analyse et Enseignants)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Exiger une session active
exiger_connexion('../');

$userCourant = utilisateur_connecte();
$userId = (int)($userCourant['id'] ?? 0);
$userRole = (string)($userCourant['role'] ?? '');

// Vérification du rôle autorisé (Analyse, Enseignant, ou Admin)
if (!in_array($userRole, ['Analyse', 'Analyste', 'Enseignant', 'Enseignants', 'Admin'], true)) {
    header('Location: ../login.php');
    exit;
}

// Récupération des messages reçus pour l'utilisateur connecté
// Triés chronologiquement par date_envoi ASC, id ASC : le premier arrivé de l'admin a le numéro #1
$stmtMessages = $pdo->prepare("
    SELECT m.id, m.contenu, m.date_envoi, u.email AS email_expediteur, u.role AS role_expediteur
    FROM `message` m
    JOIN `user` u ON u.id = m.expediteur_id
    WHERE m.destinataire_id = :destinataire_id
    ORDER BY m.date_envoi ASC, m.id ASC
");
$stmtMessages->execute([':destinataire_id' => $userId]);
$messages = $stmtMessages->fetchAll();
$totalMessages = count($messages);

$base = '../';
$titre = 'Messages reçus';
require __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto; padding: 10px 0 40px 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="margin: 0; font-size: 20px; font-weight: 800; color: var(--text-heading);">
                📬 Boîte des messages reçus
            </h3>
            <p style="margin: 4px 0 0 0; font-size: 14px; color: var(--text-muted);">
                Compte : <strong><?= h($userCourant['email'] ?? '') ?></strong> (<?= h($userRole) ?>)
            </p>
        </div>
        <div style="background: #eef2ff; color: #3730a3; padding: 8px 16px; border-radius: var(--radius-full); font-weight: 700; font-size: 14px; border: 1px solid #c7d2fe;">
            Total reçus : <?= $totalMessages ?> message<?= $totalMessages > 1 ? 's' : '' ?>
        </div>
    </div>

    <?php if ($totalMessages === 0): ?>
        <div style="background: #ffffff; border: 1.5px dashed var(--border-medium); border-radius: var(--radius-lg); padding: 48px 24px; text-align: center;">
            <div style="font-size: 40px; margin-bottom: 12px;">📭</div>
            <h4 style="margin: 0 0 8px 0; font-size: 17px; color: var(--text-heading); font-weight: 700;">
                Aucun message reçu pour le moment
            </h4>
            <p style="margin: 0; color: var(--text-muted); font-size: 14px;">
                Lorsque l'administrateur vous enverra un message, il apparaîtra ici avec son numéro d'ordre d'arrivée.
            </p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 18px;">
            <?php foreach ($messages as $index => $msg): ?>
                <?php
                // Numéro d'ordre chronologique (1 pour le premier message arrivé)
                $numeroOrdre = $index + 1;
                $estPremier = ($numeroOrdre === 1);
                ?>
                <div style="background: #ffffff; border: 1px solid var(--border-light); border-left: 5px solid <?= $estPremier ? '#2563eb' : '#64748b' ?>; border-radius: var(--radius-lg); padding: 20px 24px; box-shadow: var(--shadow-card); transition: transform 0.15s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; background: <?= $estPremier ? '#2563eb' : '#475569' ?>; color: #ffffff; font-weight: 800; font-size: 13px; padding: 4px 12px; border-radius: var(--radius-full); min-width: 80px;">
                                Message #<?= $numeroOrdre ?>
                            </span>
                            <?php if ($estPremier): ?>
                                <span style="font-size: 11.5px; font-weight: 700; color: #1d4ed8; background: #dbeafe; padding: 3px 9px; border-radius: var(--radius-full);">
                                    ★ Premier arrivé de l'admin
                                </span>
                            <?php else: ?>
                                <span style="font-size: 11.5px; color: var(--text-muted);">
                                    Arrivé en <?= $numeroOrdre ?><sup><?= $numeroOrdre === 2 ? 'ème' : 'ème' ?></sup> position
                                </span>
                            <?php endif; ?>
                        </div>

                        <div style="font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                            <span>🕒 <?= date('d/m/Y à H:i:s', strtotime($msg['date_envoi'])) ?></span>
                        </div>
                    </div>

                    <div style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                        Expéditeur : <strong style="color: var(--text-heading);"><?= h($msg['email_expediteur'] ?? 'Administrateur') ?></strong>
                        <span class="user-pill admin" style="font-size: 11px; padding: 2px 8px; margin-left: 6px;">👑 Admin</span>
                    </div>

                    <div style="font-size: 14.5px; color: var(--text-heading); line-height: 1.6; background: #f8fafc; padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid #e2e8f0; white-space: pre-wrap; word-break: break-word;"><?= nl2br(h($msg['contenu'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="<?= $base ?>js/script.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
