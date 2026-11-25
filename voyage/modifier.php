<?php
// voyage/modifier.php : redirige vers voyage.php?id=...
require_once __DIR__ . '/../includes/auth.php';

$id = $_GET['id'] ?? '';
header('Location: voyage.php?id=' . urlencode((string)$id));
exit;
