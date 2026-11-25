<?php
// utilisateurs.php : Redirection / alias vers utilisateur/utilisateur.php
require_once __DIR__ . '/includes/auth.php';

header('Location: utilisateur/utilisateur.php');
exit;
