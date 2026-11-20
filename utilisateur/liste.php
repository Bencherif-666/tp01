<?php
// utilisateur/liste.php : Liste complète des comptes utilisateurs (Admin)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Réservé exclusivement aux administrateurs
exiger_admin('../');

$currentUserSession = utilisateur_connecte();
$currentUserId = (int)($currentUserSession['id'] ?? 0);

$recherche = trim((string)($_GET['recherche'] ?? ''));
if ($recherche !== '') {
    $stmtUsers = $pdo->prepare('
        SELECT id, email, role
        FROM `user`
        WHERE email LIKE :rech OR role LIKE :rech OR id = :idRech
        ORDER BY id ASC
    ');
    $stmtUsers->execute([
        ':rech'   => '%' . $recherche . '%',
        ':idRech' => ctype_digit($recherche) ? (int)$recherche : 0,
    ]);
} else {
    $stmtUsers = $pdo->query('SELECT id, email, role FROM `user` ORDER BY id ASC');
}
$listeUtilisateurs = $stmtUsers->fetchAll();

$messageSucces = $_GET['succes'] ?? null;
$messageErreur = $_GET['erreur'] ?? null;

$base  = '../';
$titre = 'Liste des utilisateurs';
require __DIR__ . '/../includes/header.php';
?>

<?php if ($messageSucces): ?>
    <div class="ok" style="margin-bottom: 20px;">
        <?= h($messageSucces) ?>
    </div>
<?php endif; ?>

<?php if ($messageErreur): ?>
    <div class="erreur" style="margin-bottom: 20px;">
        <?= h($messageErreur) ?>
    </div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
    <form method="get" action="liste.php" class="form-recherche" style="margin: 0; padding: 10px 16px;">
        <label for="recherche-user"><strong>Rechercher :</strong></label>
        <input type="text" name="recherche" id="recherche-user" value="<?= h($recherche) ?>" placeholder="Email ou rôle..." style="width: 240px;">
        <button type="submit" style="margin: 0;">Filtrer</button>
        <?php if ($recherche !== ''): ?>
            <a href="liste.php" style="font-size: 13px; color: var(--text-muted); text-decoration: underline;">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <div style="display: flex; gap: 8px;">
        <a href="utilisateur.php" class="btn-blue" style="font-size: 13.5px; padding: 9px 18px;">
            ➕ Ajouter un utilisateur
        </a>
    </div>
</div>

<table class="liste" style="width: 100%;">
    <thead>
        <tr>
            <th style="width: 10%;">ID</th>
            <th style="width: 45%;">Adresse Mail</th>
            <th style="width: 20%;">Rôle</th>
            <th style="width: 25%;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($listeUtilisateurs): ?>
            <?php foreach ($listeUtilisateurs as $u):
                $estCompteCourant = ((int)$u['id'] === $currentUserId);
            ?>
                <tr>
                    <td><strong>#<?= h($u['id']) ?></strong></td>
                    <td>
                        <strong style="color: var(--text-heading);"><?= h($u['email']) ?></strong>
                        <?php if ($estCompteCourant): ?>
                            <span style="display: inline-block; margin-left: 8px; font-size: 11px; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: var(--radius-full); font-weight: 700;">
                                Vous
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="user-pill <?= $u['role'] === 'Admin' ? 'admin' : 'user' ?>" style="font-size: 11.5px; padding: 3px 10px;">
                            <?php
                            $roleLabelsListe = [
                                'Admin' => '👑 Admin',
                                'User' => '👤 User',
                                'Analyst' => '📊 Analyst',
                                'Enseignant' => '🎓 Enseignant',
                                'Controller' => '📨 Controller',
                            ];
                            echo h($roleLabelsListe[$u['role']] ?? $u['role']);
                            ?>
                        </span>
                    </td>
                    <td>
                        <a href="utilisateur.php?id=<?= h($u['id']) ?>" style="font-weight: 600; text-decoration: underline; margin-right: 12px;">
                            Modifier
                        </a>
                        <?php if ($estCompteCourant): ?>
                            <span class="petit" style="color: var(--text-muted); font-style: italic;">
                                (Suppression interdite)
                            </span>
                        <?php else: ?>
                            <form method="post" action="supprimer.php" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement le compte <?= h($u['email']) ?> (ID <?= h($u['id']) ?>) ?');">
                                <input type="hidden" name="id" value="<?= h($u['id']) ?>">
                                <button type="submit" style="background: none; border: none; color: #dc2626; padding: 0; cursor: pointer; text-decoration: underline; font-weight: 600; font-size: inherit; margin: 0;">
                                    Supprimer
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">
                    Aucun utilisateur trouvé.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
