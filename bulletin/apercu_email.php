<?php
// bulletin/apercu_email.php : Prévisualisation d'un email de bulletin envoyé
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';
require_once __DIR__ . '/../includes/mail_service.php';

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

$idArchive = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_GET['id'] ?? ''));
$config = get_mail_config();
$dir = $config['archive_dir'] ?? (__DIR__ . '/../uploads/emails');

$fichierHtml = $dir . '/' . $idArchive . '.html';
$fichierJson = $dir . '/' . $idArchive . '.json';

if ($idArchive !== '' && file_exists($fichierHtml)) {
    // Si demandé en mode iframe ou raw, on renvoie directement le HTML
    if (isset($_GET['raw'])) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($fichierHtml);
        exit;
    }

    $meta = file_exists($fichierJson) ? json_decode(file_get_contents($fichierJson), true) : [];

    $base = '../';
    $titre = 'Aperçu de l’email du Bulletin';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <h2 style="margin: 0; color: var(--text-heading);">📧 Prévisualisation du Bulletin Envoyé</h2>
            <p style="margin: 4px 0 0; color: var(--text-muted); font-size: 13.5px;">
                Destinataire : <strong><?= h($meta['destinataire'] ?? '—') ?></strong> &bull;
                Méthode : <span class="badge"><?= h($meta['methode'] ?? '—') ?></span> &bull;
                Date : <?= h($meta['date'] ?? '—') ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="apercu_email.php?id=<?= h($idArchive) ?>&raw=1" target="_blank" class="btn" style="text-decoration: none; padding: 8px 14px; background: #e0e7ff; color: #4338ca; border-radius: 6px; font-weight: 600;">Ouvrir en plein écran ↗</a>
            <a href="javascript:history.back()" class="btn" style="text-decoration: none; padding: 8px 14px; background: var(--bg-alt); color: var(--text-body); border-radius: 6px;">&larr; Retour</a>
        </div>
    </div>

    <!-- Iframe d'affichage de l'email -->
    <div style="border: 2px solid var(--border-medium); border-radius: var(--radius-lg); overflow: hidden; background: #ffffff; box-shadow: var(--shadow-md);">
        <iframe src="apercu_email.php?id=<?= h($idArchive) ?>&raw=1" style="width: 100%; height: 780px; border: none; display: block;"></iframe>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// Si aucun ID spécifié, lister les derniers emails envoyés
$fichiersJson = glob($dir . '/*.json');
usort($fichiersJson, function ($a, $b) {
    return filemtime($b) - filemtime($a);
});

$base = '../';
$titre = 'Historique des Emails de Bulletins';
require __DIR__ . '/../includes/header.php';
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="margin: 0;">📨 Historique des Bulletins Envoyés par Email</h2>
        <p style="margin: 4px 0 0; color: var(--text-muted);">Tous les bulletins expédiés via PHP <code>mail()</code> ou <code>PHPMailer</code></p>
    </div>
    <a href="bulletin.php" style="text-decoration: underline;">&larr; Retour au Bulletin</a>
</div>

<?php if (empty($fichiersJson)): ?>
    <p class="ok">Aucun email n'a encore été envoyé ou archivé.</p>
<?php else: ?>
    <table class="liste" style="width: 100%;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Étudiant</th>
                <th>Destinataire</th>
                <th>Moteur</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($fichiersJson, 0, 30) as $fj):
                $m = json_decode(file_get_contents($fj), true);
                if (!$m) continue;
            ?>
                <tr>
                    <td><?= h(date('d/m/Y H:i', strtotime($m['date'] ?? 'now'))) ?></td>
                    <td><strong><?= h($m['etudiant'] ?? '—') ?></strong></td>
                    <td><code><?= h($m['destinataire'] ?? '—') ?></code></td>
                    <td><span style="font-weight: 600;"><?= h($m['methode'] ?? '—') ?></span></td>
                    <td>
                        <?php if (!empty($m['succes'])): ?>
                            <span style="color: #15803d; font-weight: 700;">✅ Délivré</span>
                        <?php else: ?>
                            <span style="color: #0284c7; font-weight: 700;">📄 Généré / Archivé</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="apercu_email.php?id=<?= h($m['id']) ?>" style="font-weight: 600; text-decoration: underline;">👁️ Prévisualiser</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
