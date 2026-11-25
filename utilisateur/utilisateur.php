<?php
// utilisateur/utilisateur.php : Gestion des utilisateurs (CRUD complet - Admin)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Réservé exclusivement aux administrateurs
exiger_admin('../');

$currentUserSession = utilisateur_connecte();
$currentUserId = (int)($currentUserSession['id'] ?? 0);

// Messages flash
$messageSucces = $_GET['succes'] ?? null;
$messageErreur = $_GET['erreur'] ?? null;

// Mode modification : charger les données de l'utilisateur
$idParam = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$userEdite = null;

if ($idParam) {
    $stmtFind = $pdo->prepare('SELECT id, email, role FROM `user` WHERE id = :id LIMIT 1');
    $stmtFind->execute([':id' => $idParam]);
    $userEdite = $stmtFind->fetch();
    if (!$userEdite) {
        $messageErreur = "Aucun utilisateur trouvé avec l'identifiant {$idParam}.";
    }
}

// Recherche dans la liste des utilisateurs
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

$base  = '../';
$titre = 'Gestion des utilisateurs';
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

<div style="display:flex;justify-content:flex-end;margin-bottom:18px;">
    <a href="../communication/admin.php" style="display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:var(--radius-md);background:var(--primary);color:#fff;font-weight:600;text-decoration:none;box-shadow:0 2px 6px var(--primary-glow);">
        📨 Envoyer un message
    </a>
</div>

<div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 30px; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid var(--border-light); padding-bottom: 12px;">
        <h3 style="margin: 0; color: var(--text-heading); font-size: 17px;">
            <?= $userEdite ? '✏️ Modifier l’utilisateur #' . h($userEdite['id']) : '➕ Ajouter un nouvel utilisateur' ?>
        </h3>
        <?php if ($userEdite): ?>
            <a href="utilisateur.php" style="font-size: 13.5px; font-weight: 600; color: var(--primary);">+ Ajouter un autre utilisateur</a>
        <?php endif; ?>
    </div>

    <form method="post" action="enregistrer.php" autocomplete="off">
        <input type="hidden" name="id" value="<?= h($userEdite['id'] ?? '') ?>">

        <table class="formulaire">
            <tr>
                <td><label for="email">Adresse Mail :</label></td>
                <td>
                    <input type="email" name="email" id="email" size="40" maxlength="255" value="<?= h($userEdite['email'] ?? '') ?>" placeholder="ex: utilisateur@exemple.com" required style="width: 320px;">
                </td>
            </tr>
            <tr>
                <td><label for="mdp">Mot de passe :</label></td>
                <td>
                    <input type="password" name="mdp" id="mdp" size="40" placeholder="<?= $userEdite ? 'Laisser vide pour conserver l’actuel' : 'Mot de passe requis' ?>" <?= $userEdite ? '' : 'required' ?> style="width: 320px;">
                    <?php if ($userEdite): ?>
                        <span class="petit" style="color: var(--text-muted); display: block; margin-top: 4px;">
                            (Si vous laissez ce champ vide, l'ancien mot de passe sera conservé)
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><label for="role">Rôle :</label></td>
                <td>
                    <select name="role" id="role" required style="width: 200px;">
                        <option value="User" <?= (($userEdite['role'] ?? 'User') === 'User') ? 'selected' : '' ?>>User</option>
                        <option value="Admin" <?= (($userEdite['role'] ?? '') === 'Admin') ? 'selected' : '' ?>>Admin</option>
                        <option value="Analyst" <?= (($userEdite['role'] ?? '') === 'Analyst') ? 'selected' : '' ?>>Analyst</option>
                        <option value="Enseignant" <?= (($userEdite['role'] ?? '') === 'Enseignant') ? 'selected' : '' ?>>Enseignant</option>
                        <option value="Controller" <?= (($userEdite['role'] ?? '') === 'Controller') ? 'selected' : '' ?>>Controller</option>
                    </select>
                </td>
            </tr>
        </table>

        <div style="margin-top: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="submit" name="action" value="enregistrer" class="btn-blue" style="margin: 0;">
                <?= $userEdite ? 'Mettre à jour' : 'Enregistrer' ?>
            </button>
            <?php if ($userEdite): ?>
                <a href="utilisateur.php" style="padding: 9px 16px; border: 1.5px solid var(--border-medium); border-radius: var(--radius-md); font-weight: 600; font-size: 13.5px; color: var(--text-heading); text-decoration: none; background: #ffffff;">
                    Annuler
                </a>
            <?php else: ?>
                <button type="reset" style="margin: 0;">Réinitialiser</button>
            <?php endif; ?>
            <button type="submit" formaction="liste.php" formmethod="get" formnovalidate style="margin: 0;">Afficher Liste</button>
        </div>
    </form>
</div>

<!-- Section Liste des utilisateurs -->
<div style="margin-top: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <h3 style="margin: 0; color: var(--text-heading); font-size: 18px;">
            👥 Liste des comptes utilisateurs (<?= count($listeUtilisateurs) ?>)
        </h3>

        <form method="get" action="utilisateur.php" style="margin: 0; display: inline-flex; gap: 8px;">
            <input type="text" name="recherche" value="<?= h($recherche) ?>" placeholder="Rechercher email ou rôle..." style="padding: 7px 12px; font-size: 13px; width: 220px;">
            <button type="submit" style="padding: 7px 14px; font-size: 13px; margin: 0;">Filtrer</button>
            <?php if ($recherche !== ''): ?>
                <a href="utilisateur.php" style="align-self: center; font-size: 12.5px; color: var(--text-muted); text-decoration: underline;">Effacer</a>
            <?php endif; ?>
        </form>
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
                    <tr <?= ($userEdite && (int)$userEdite['id'] === (int)$u['id']) ? 'style="background: #f0fdf4;"' : '' ?>>
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
                                $roleLabels = [
                                    'Admin' => '👑 Admin',
                                    'User' => '👤 User',
                                    'Analyst' => '📊 Analyst',
                                    'Enseignant' => '🎓 Enseignant',
                                    'Controller' => '📨 Controller',
                                ];
                                echo h($roleLabels[$u['role']] ?? $u['role']);
                                ?>
                            </span>
                        </td>
                        <td>
                            <a href="utilisateur.php?id=<?= h($u['id']) ?>" style="font-weight: 600; text-decoration: underline; margin-right: 10px;">
                                Modifier
                            </a>
                            <?php if ($estCompteCourant): ?>
                                <span class="petit" style="color: var(--text-muted); font-style: italic;" title="Vous ne pouvez pas supprimer votre propre compte connecté">
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
                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">
                        Aucun utilisateur trouvé.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
