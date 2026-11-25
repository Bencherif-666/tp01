<?php
// inscription.php : Page d'inscription (création de compte)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/fonctions.php';

// Si l'utilisateur est déjà connecté, redirection selon son rôle
if (est_connecte()) {
    $u = utilisateur_connecte();
    header('Location: ' . page_selon_role($u['role'] ?? ''));
    exit;
}

$erreur = null;
$emailSaisi = '';
$roleSaisi  = 'User';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $mdp   = (string)($_POST['mdp'] ?? '');
    $role  = trim((string)($_POST['role'] ?? 'User'));

    $emailSaisi = $email;
    $rolesValides = ['Admin', 'User', 'Analyse', 'Enseignant', 'Controller'];
    $roleSaisi  = in_array($role, $rolesValides, true) ? $role : 'User';

    if ($email === '') {
        $erreur = "Veuillez renseigner une adresse email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Format d'adresse email invalide.";
    } elseif ($mdp === '') {
        $erreur = "Le mot de passe ne peut pas être vide.";
    } elseif (!in_array($role, $rolesValides, true)) {
        $erreur = "Rôle invalide.";
    } else {
        // Vérifier l'unicité de l'adresse email
        $stmtCheck = $pdo->prepare('SELECT id FROM `user` WHERE email = :email LIMIT 1');
        $stmtCheck->execute([':email' => $email]);
        if ($stmtCheck->fetch()) {
            $erreur = "Cette adresse email est déjà enregistrée. Veuillez vous connecter.";
        } else {
            // Hachage du mot de passe
            $hashMdp = password_hash($mdp, PASSWORD_DEFAULT);

            $stmtInsert = $pdo->prepare('INSERT INTO `user` (email, mdp, role) VALUES (:email, :mdp, :role)');
            $stmtInsert->execute([
                ':email' => $email,
                ':mdp'   => $hashMdp,
                ':role'  => $role,
            ]);

            header('Location: login.php?succes=' . urlencode("Inscription réussie ! Vous pouvez maintenant vous connecter."));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription – TP01 PAW</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .auth-container {
            min-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .auth-card {
            background: #ffffff;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            width: 100%;
            max-width: 440px;
            padding: 36px 32px;
            position: relative;
        }
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563eb 0%, #4f46e5 100%);
            border-top-left-radius: var(--radius-xl);
            border-top-right-radius: var(--radius-xl);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .auth-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-heading);
            margin: 10px 0 4px 0;
            letter-spacing: -0.02em;
        }
        .auth-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            margin: 0;
        }
        .auth-form-group {
            margin-bottom: 18px;
        }
        .auth-form-group label {
            display: block;
            font-weight: 600;
            font-size: 13.5px;
            color: var(--text-heading);
            margin-bottom: 6px;
        }
        .auth-form-group input,
        .auth-form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-medium);
            border-radius: var(--radius-md);
            font-size: 14.5px;
            font-family: inherit;
            transition: all 0.18s ease;
            box-sizing: border-box;
            background-color: #ffffff;
        }
        .auth-form-group input:focus,
        .auth-form-group select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
        }
        .auth-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-blue {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #2563eb !important;
            color: #ffffff !important;
            border: 1.5px solid #1d4ed8 !important;
            border-radius: var(--radius-md) !important;
            padding: 11px 18px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            text-decoration: none !important;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25) !important;
            transition: all 0.18s ease !important;
            box-sizing: border-box;
            text-align: center;
            line-height: 1.4;
            margin: 0 !important;
        }
        .btn-blue:hover {
            background: #1d4ed8 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35) !important;
            transform: translateY(-1px);
        }
        .auth-alert-error {
            background: #fef2f2;
            border: 1.5px solid #fecaca;
            color: #b91c1c;
            padding: 12px 14px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }
        .auth-footer-link {
            text-align: center;
            margin-top: 14px;
            font-size: 13.5px;
            color: var(--text-muted);
        }
        .auth-footer-link a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
        .auth-footer-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <span class="brand-badge">ISIL &bull; Université Djillali Liabes</span>
            <h2 class="auth-title" style="justify-content: center; margin-top: 10px;">Inscription</h2>
            <p class="auth-subtitle">Créez votre compte utilisateur</p>
        </div>

        <?php if ($erreur): ?>
            <div class="auth-alert-error"><?= h($erreur) ?></div>
        <?php endif; ?>

        <form method="post" action="inscription.php" autocomplete="on">
            <div class="auth-form-group">
                <label for="email">Adresse Mail</label>
                <input type="email" name="email" id="email" value="<?= h($emailSaisi) ?>" placeholder="nom@exemple.com" required autofocus>
            </div>

            <div class="auth-form-group">
                <label for="mdp">Mots de Passe</label>
                <input type="password" name="mdp" id="mdp" placeholder="Choisir un mot de passe sécurisé" required>
            </div>

            <div class="auth-form-group">
                <label for="role">Type User</label>
                <select name="role" id="role" required>
                    <option value="User" <?= $roleSaisi === 'User' ? 'selected' : '' ?>>User</option>
                    <option value="Admin" <?= $roleSaisi === 'Admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="Analyse" <?= $roleSaisi === 'Analyse' ? 'selected' : '' ?>>Analyse</option>
                    <option value="Enseignant" <?= $roleSaisi === 'Enseignant' ? 'selected' : '' ?>>Enseignant</option>
                    <option value="Controller" <?= $roleSaisi === 'Controller' ? 'selected' : '' ?>>Controller</option>
                </select>
            </div>

            <div class="auth-actions">
                <button type="submit" class="btn-blue">Valider</button>
            </div>

            <div class="auth-footer-link">
                Déjà inscrit ? <a href="login.php">Se connecter</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
