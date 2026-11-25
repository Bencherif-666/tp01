<?php
// includes/header.php
// Avant l'inclusion, la page doit définir : $base (chemin vers la racine) et $titre

$titresEtudiants = [
    'Formulaire Personne', 'Formulaire Étudiant', 'Liste des personnes',
    'Modifier une personne', 'Enregistrement personne', 'Insérer Image',
    'Affichage PHP', 'Suppression d’une personne'
];

$titresEnseignants = [
    'Gestion des enseignants', 'Formulaire Enseignant', 'Liste des enseignants',
    'Modifier un enseignant', 'Enregistrement enseignant', 'Suppression d’un enseignant',
    'Liste Des Etudiants', 'Liste des étudiants par enseignant'
];

$titresModules = [
    'Gestion des modules', 'Formulaire Module', 'Liste des modules',
    'Modifier un module', 'Enregistrement module', 'Suppression d’un module'
];

$titresVoyages = [
    'Formulaire Voyage', 'Voyage', 'Gestion des voyages', 'Liste des voyages',
    'Modifier un voyage', 'Enregistrement voyage', 'Affichage PHP Voyage', 'Suppression d’un voyage',
    'Détails du voyage'
];

$titresBulletin = [
    'Bulletin de notes', 'Bulletin de Notes', 'Formulaire Bulletin de Notes',
    'Liste des notes', 'Enregistrement note', 'Suppression d’une note',
    'Modifier une note'
];

$titresPv = [
    'PV', 'PV Global', 'Procès-Verbal (PV)'
];

$titresStatistiques = [
    'Statistiques', 'Statistiques des étudiants'
];

$titresTables = [
    'Sous-menu Tables',
    'Gestion des nationalités', 'Liste des nationalités', 'Enregistrement nationalité',
    'Gestion des sports', 'Liste des sports', 'Enregistrement du sport',
    'Gestion des filières', 'Formulaire Filière', 'Liste des filières', 'Enregistrement filière', 'Suppression d’une filière',
    'Gestion des moyens de transport', 'Liste des moyens de transport', 'Enregistrement transport',
    'Gestion des modèles de voiture', 'Liste des modèles de voiture', 'Enregistrement modèle de voiture'
];

$titresUtilisateurs = [
    'Gestion des utilisateurs', 'Formulaire Utilisateur', 'Liste des utilisateurs',
    'Modifier un utilisateur', 'Enregistrement utilisateur', 'Suppression d’un utilisateur'
];

$isAccueilActive      = ($titre === 'Accueil');
$isEtudiantsActive    = in_array($titre, $titresEtudiants, true);
$isEnseignantsActive  = in_array($titre, $titresEnseignants, true);
$isModulesActive      = in_array($titre, $titresModules, true);
$isBulletinActive     = in_array($titre, $titresBulletin, true);
$isPvActive           = in_array($titre, $titresPv, true);
$isStatistiquesActive = in_array($titre, $titresStatistiques, true);
$isVoyagesActive      = in_array($titre, $titresVoyages, true);
$isTablesActive       = in_array($titre, $titresTables, true);
$isUtilisateursActive = in_array($titre, $titresUtilisateurs, true);

$userSession = function_exists('utilisateur_connecte') ? utilisateur_connecte() : null;
$roleSession = $userSession['role'] ?? null;
$usernameSession = $userSession['username'] ?? $userSession['email'] ?? 'Utilisateur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TP01 - <?= h($titre) ?></title>
    <link rel="stylesheet" href="<?= $base ?>css/style.css">
</head>
<body>
<div id="page" class="<?= $titre === 'Bulletin de notes' ? 'page-bulletin' : '' ?>">
    <div class="header-brand">
        <h1>TP01 – PAW</h1>
        <span class="brand-badge">ISIL &bull; Université Djillali Liabes</span>
    </div>
    <div id="menu">
        <?php if ($roleSession === 'User'): ?>
            <a href="<?= $base ?>bulletin_user.php" class="active">Bulletin de Notes</a>
            <div style="margin-left: auto; display: inline-flex; align-items: center; gap: 8px;">
                <span class="user-pill user" title="Connecté en tant que <?= h($userSession['email'] ?? '') ?>">
                    👤 Bienvenue, <?= h($usernameSession) ?> ! (User)
                </span>
                <a href="<?= $base ?>logout.php" class="btn-logout" title="Se déconnecter">Déconnexion</a>
            </div>
        <?php elseif ($roleSession === 'Analyst'): ?>
            <a href="<?= $base ?>statistiques/statistiques.php" class="active">Statistiques</a>
            <?php $nbMessagesNonlus = function_exists('nombre_messages_nonlus') ? nombre_messages_nonlus($pdo, (int)$userSession['id']) : 0; ?>
            <a href="<?= $base ?>communication/inbox.php" style="<?= $nbMessagesNonlus > 0 ? 'background:#fff7ed;color:#c2410c;border:1px solid #fdba74;' : '' ?>">
                Messagerie <?= $nbMessagesNonlus > 0 ? '<span style="background:#ea580c;color:#fff;border-radius:999px;padding:1px 7px;font-size:11px;font-weight:800;">' . h($nbMessagesNonlus) . '</span>' : '' ?>
            </a>
            <div style="margin-left: auto; display: inline-flex; align-items: center; gap: 8px;">
                <span class="user-pill user" title="Connecté en tant que <?= h($userSession['email'] ?? '') ?>">
                    📊 Bienvenue, <?= h($usernameSession) ?> ! (Analyst)
                </span>
                <a href="<?= $base ?>logout.php" class="btn-logout" title="Se déconnecter">Déconnexion</a>
            </div>
        <?php elseif ($roleSession === 'Enseignant'): ?>
            <a href="<?= $base ?>enseignant/enseignant.php" class="<?= $isEnseignantsActive ? 'active' : '' ?>">Mon profil enseignant</a>
            <a href="<?= $base ?>enseignant/liste.php">Liste des enseignants</a>
            <?php $nbMessagesNonlus = function_exists('nombre_messages_nonlus') ? nombre_messages_nonlus($pdo, (int)$userSession['id']) : 0; ?>
            <a href="<?= $base ?>communication/inbox.php" style="<?= $nbMessagesNonlus > 0 ? 'background:#fff7ed;color:#c2410c;border:1px solid #fdba74;' : '' ?>">
                Messagerie <?= $nbMessagesNonlus > 0 ? '<span style="background:#ea580c;color:#fff;border-radius:999px;padding:1px 7px;font-size:11px;font-weight:800;">' . h($nbMessagesNonlus) . '</span>' : '' ?>
            </a>
            <div style="margin-left: auto; display: inline-flex; align-items: center; gap: 8px;">
                <span class="user-pill user" title="Connecté en tant que <?= h($userSession['email'] ?? '') ?>">
                    🎓 Bienvenue, <?= h($usernameSession) ?> ! (Enseignant)
                </span>
                <a href="<?= $base ?>logout.php" class="btn-logout" title="Se déconnecter">Déconnexion</a>
            </div>
        <?php elseif ($roleSession === 'Controller'): ?>
            <a href="<?= $base ?>communication/controller.php" class="<?= $titre === 'Tableau de bord Controller' ? 'active' : '' ?>">Communications</a>
            <div style="margin-left: auto; display: inline-flex; align-items: center; gap: 8px;">
                <span class="user-pill user" title="Connecté en tant que <?= h($userSession['email'] ?? '') ?>">
                    📨 Bienvenue, <?= h($usernameSession) ?> ! (Controller)
                </span>
                <a href="<?= $base ?>logout.php" class="btn-logout" title="Se déconnecter">Déconnexion</a>
            </div>
        <?php else: ?>
            <a href="<?= $base ?>index.php" class="<?= $isAccueilActive ? 'active' : '' ?>">Accueil</a>
            <a href="<?= $base ?>personne/formulaire.php" class="<?= $isEtudiantsActive ? 'active' : '' ?>">Étudiants</a>
            <a href="<?= $base ?>enseignant/enseignant.php" class="<?= $isEnseignantsActive ? 'active' : '' ?>">Enseignants</a>
            <a href="<?= $base ?>module/module.php" class="<?= $isModulesActive ? 'active' : '' ?>">Modules</a>
            <a href="<?= $base ?>bulletin/bulletin.php" class="<?= $isBulletinActive ? 'active' : '' ?>">Bulletin de Notes</a>
            <a href="<?= $base ?>pv/pv.php" class="<?= $isPvActive ? 'active' : '' ?>">PV</a>
            <a href="<?= $base ?>statistiques/statistiques.php" class="<?= $isStatistiquesActive ? 'active' : '' ?>">Statistiques</a>
            <a href="<?= $base ?>communication/admin.php" class="<?= ($titre === 'Messages administrateur') ? 'active' : '' ?>">Messagerie</a>
            <div class="nav-dropdown <?= ($isTablesActive || $isVoyagesActive) ? 'active' : '' ?>">
                <a href="<?= $base ?>tables.php" class="nav-dropdown-btn <?= ($isTablesActive || $isVoyagesActive) ? 'active' : '' ?>" id="btn-tables-menu" aria-haspopup="true" aria-expanded="false">Tables ▾</a>
                <div class="nav-dropdown-content" id="submenu-tables">
                    <a href="<?= $base ?>nationalite/nationalite.php" class="<?= in_array($titre, ['Gestion des nationalités', 'Liste des nationalités', 'Enregistrement nationalité'], true) ? 'active' : '' ?>">Nationalité</a>
                    <a href="<?= $base ?>sport/sport.php" class="<?= in_array($titre, ['Gestion des sports', 'Liste des sports', 'Enregistrement du sport'], true) ? 'active' : '' ?>">Sports</a>
                    <a href="<?= $base ?>filiere/filiere.php" class="<?= in_array($titre, ['Gestion des filières', 'Formulaire Filière', 'Liste des filières', 'Enregistrement filière', 'Suppression d’une filière'], true) ? 'active' : '' ?>">Filières</a>
                    <a href="<?= $base ?>voyage/voyage.php" class="<?= $isVoyagesActive ? 'active' : '' ?>">Voyage</a>
                </div>
            </div>
            <a href="<?= $base ?>utilisateur/utilisateur.php" class="<?= $isUtilisateursActive ? 'active' : '' ?>">Utilisateurs</a>
            <?php if ($userSession): ?>
                <div style="margin-left: auto; display: inline-flex; align-items: center; gap: 8px;">
                    <span class="user-pill admin" title="Connecté en tant que <?= h($userSession['email'] ?? '') ?>">
                        👑 Bienvenue, <?= h($usernameSession) ?> ! (Admin)
                    </span>
                    <a href="<?= $base ?>logout.php" class="btn-logout" title="Se déconnecter">Déconnexion</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <hr>
    <h2><?= h($titre) ?></h2>