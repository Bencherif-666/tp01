<?php
// logout.php : Déconnexion de l'utilisateur
require_once __DIR__ . '/includes/fonctions.php';

deconnecter_utilisateur();
header('Location: login.php?succes=' . urlencode('Vous avez été déconnecté avec succès.'));
exit;
