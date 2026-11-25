<?php
// utilisateur/supprimer.php : Suppression sécurisée d'un utilisateur
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Réservé exclusivement aux administrateurs
exiger_admin('../');

$currentUserSession = utilisateur_connecte();
$currentUserId = (int)($currentUserSession['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: utilisateur.php?erreur=' . urlencode("La suppression doit être effectuée par le formulaire sécurisé."));
    exit;
}

$idRaw = $_POST['id'] ?? '';
$idValide = filter_var((string)$idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($idValide === false) {
    header('Location: utilisateur.php?erreur=' . urlencode("Identifiant d'utilisateur invalide."));
    exit;
}

// EMPÊCHER L'ADMINISTRATEUR DE SUPPRIMER SON PROPRE COMPTE CONNECTÉ
if ($idValide === $currentUserId) {
    header('Location: utilisateur.php?erreur=' . urlencode("Action interdite : vous ne pouvez pas supprimer votre propre compte pendant que vous êtes connecté !"));
    exit;
}

// Récupérer l'utilisateur pour le message de confirmation
$stmtUser = $pdo->prepare('SELECT email FROM `user` WHERE id = :id LIMIT 1');
$stmtUser->execute([':id' => $idValide]);
$userToDelete = $stmtUser->fetch();

if (!$userToDelete) {
    header('Location: utilisateur.php?erreur=' . urlencode("Aucun utilisateur trouvé pour l'identifiant {$idValide}."));
    exit;
}

// Suppression
$stmtDelete = $pdo->prepare('DELETE FROM `user` WHERE id = :id');
$stmtDelete->execute([':id' => $idValide]);

set_flash_succes("L'utilisateur {$userToDelete['email']} (ID #{$idValide}) a été supprimé avec succès.");
header('Location: utilisateur.php?succes=' . urlencode("L'utilisateur {$userToDelete['email']} (ID #{$idValide}) a été supprimé avec succès."));
exit;
