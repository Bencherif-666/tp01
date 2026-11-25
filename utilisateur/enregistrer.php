<?php
// utilisateur/enregistrer.php : Traitement de l'ajout et de la mise à jour d'un utilisateur
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Réservé exclusivement aux administrateurs
exiger_admin('../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: utilisateur.php');
    exit;
}

$idRaw = trim((string)($_POST['id'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$mdp   = (string)($_POST['mdp'] ?? '');
$role  = trim((string)($_POST['role'] ?? 'User'));

$idValide = null;
if ($idRaw !== '') {
    $idValide = filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idValide === false) {
        header('Location: utilisateur.php?erreur=' . urlencode("Identifiant utilisateur invalide."));
        exit;
    }
}

// Validation des données
if ($email === '') {
    $cible = $idValide ? "utilisateur.php?id={$idValide}&erreur=" : "utilisateur.php?erreur=";
    header('Location: ' . $cible . urlencode("L'adresse email est obligatoire."));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $cible = $idValide ? "utilisateur.php?id={$idValide}&erreur=" : "utilisateur.php?erreur=";
    header('Location: ' . $cible . urlencode("Format d'adresse email invalide."));
    exit;
}

if (!in_array($role, ['Admin', 'User', 'Analyst', 'Enseignant', 'Controller'], true)) {
    $cible = $idValide ? "utilisateur.php?id={$idValide}&erreur=" : "utilisateur.php?erreur=";
    header('Location: ' . $cible . urlencode("Le rôle sélectionné est invalide."));
    exit;
}

// Mode AJOUT (création d'un nouvel utilisateur)
if ($idValide === null) {
    if ($mdp === '') {
        header('Location: utilisateur.php?erreur=' . urlencode("Le mot de passe est obligatoire pour la création d'un compte."));
        exit;
    }

    // Vérifier l'unicité de l'email
    $stmtCheck = $pdo->prepare('SELECT id FROM `user` WHERE email = :email LIMIT 1');
    $stmtCheck->execute([':email' => $email]);
    if ($stmtCheck->fetch()) {
        header('Location: utilisateur.php?erreur=' . urlencode("Cette adresse email est déjà utilisée par un autre compte."));
        exit;
    }

    // Hachage du mot de passe
    $hashMdp = password_hash($mdp, PASSWORD_DEFAULT);

    $stmtInsert = $pdo->prepare('INSERT INTO `user` (email, mdp, role) VALUES (:email, :mdp, :role)');
    $stmtInsert->execute([
        ':email' => $email,
        ':mdp'   => $hashMdp,
        ':role'  => $role,
    ]);
    $newId = $pdo->lastInsertId();

    if ($role === 'Analyst') {
        $stmtProfile = $pdo->prepare('INSERT IGNORE INTO analyst (user_id) VALUES (:user_id)');
        $stmtProfile->execute([':user_id' => $newId]);
    }

    header('Location: utilisateur.php?succes=' . urlencode("Utilisateur {$email} (ID #{$newId}) créé avec succès."));
    exit;
}

// Mode MODIFICATION (mise à jour d'un utilisateur existant)
// 1. Vérifier que l'utilisateur existe
$stmtUser = $pdo->prepare('SELECT * FROM `user` WHERE id = :id LIMIT 1');
$stmtUser->execute([':id' => $idValide]);
$existingUser = $stmtUser->fetch();

if (!$existingUser) {
    header('Location: utilisateur.php?erreur=' . urlencode("L'utilisateur ID {$idValide} n'existe pas."));
    exit;
}

// 2. Vérifier l'unicité de l'email (à l'exclusion de l'utilisateur en cours de modification)
$stmtCheck = $pdo->prepare('SELECT id FROM `user` WHERE email = :email AND id != :id LIMIT 1');
$stmtCheck->execute([':email' => $email, ':id' => $idValide]);
if ($stmtCheck->fetch()) {
    header('Location: utilisateur.php?id=' . $idValide . '&erreur=' . urlencode("Cette adresse email est déjà attribuée à un autre compte."));
    exit;
}

// 3. Mise à jour : avec ou sans nouveau mot de passe
if ($mdp !== '') {
    // Un nouveau mot de passe a été fourni -> on le hache
    $hashMdp = password_hash($mdp, PASSWORD_DEFAULT);
    $stmtUpdate = $pdo->prepare('UPDATE `user` SET email = :email, mdp = :mdp, role = :role WHERE id = :id');
    $stmtUpdate->execute([
        ':email' => $email,
        ':mdp'   => $hashMdp,
        ':role'  => $role,
        ':id'    => $idValide,
    ]);
} else {
    // Le champ mot de passe est vide -> on conserve l'ancien mot de passe
    $stmtUpdate = $pdo->prepare('UPDATE `user` SET email = :email, role = :role WHERE id = :id');
    $stmtUpdate->execute([
        ':email' => $email,
        ':role'  => $role,
        ':id'    => $idValide,
    ]);
}

// Si l'administrateur a modifié ses propres informations, mettre à jour la session
$sessionUser = utilisateur_connecte();
if ($sessionUser && (int)$sessionUser['id'] === (int)$idValide) {
    $_SESSION['user']['email'] = $email;
    $_SESSION['user']['username'] = $email;
    $_SESSION['user']['role']  = $role;
}

header('Location: utilisateur.php?succes=' . urlencode("Utilisateur {$email} (ID #{$idValide}) mis à jour avec succès."));
exit;
