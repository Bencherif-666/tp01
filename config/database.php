<?php
// config/database.php
// Connexion unique à MySQL (PDO). Toutes les autres pages font require_once de ce fichier.
// Si besoin, modifiez UNIQUEMENT ces 4 valeurs.
require_once __DIR__ . '/../includes/auth.php';

$host   = getenv('TP_BN_DB_HOST') ?: 'localhost';
$dbname = getenv('TP_BN_DB_NAME') ?: 'tp01_paw';
$user   = getenv('TP_BN_DB_USER') ?: 'root';
$pass   = getenv('TP_BN_DB_PASSWORD');
$pass   = $pass === false ? '' : $pass;
$port   = (int)(getenv('TP_BN_DB_PORT') ?: 3306);

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // erreurs = exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // résultats en tableaux associatifs
        ]
    );
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données. Vérifiez que MySQL est démarré et que database.sql est importé.');
}
