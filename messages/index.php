<?php
// messages/index.php : Redirection selon le rôle
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

exiger_connexion('../');
$u = utilisateur_connecte();
$role = $u['role'] ?? '';

if (est_admin()) {
    header('Location: envoyer.php');
} elseif (in_array($role, ['Controller', 'Controleur', 'Contrôleur'], true)) {
    header('Location: controller.php');
} elseif (in_array($role, ['Analyse', 'Analyste', 'Enseignant', 'Enseignants'], true)) {
    header('Location: recus.php');
} else {
    header('Location: ../login.php');
}
exit;
