<?php
// pv.php : redirection / alias vers pv/pv.php
require_once __DIR__ . '/includes/auth.php';

$filiere = isset($_GET['filiere']) ? '?filiere=' . urlencode($_GET['filiere']) : '';
header('Location: pv/pv.php' . $filiere);
exit;
