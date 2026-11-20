<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

exiger_connexion('../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inbox.php');
    exit;
}

$user = utilisateur_connecte();
$messageId = (int)($_POST['id'] ?? 0);

if ($messageId > 0) {
    $stmt = $pdo->prepare("
        UPDATE communication_message
        SET read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
        WHERE id = :id AND recipient_id = :recipient_id
    ");
    $stmt->execute([
        ':id' => $messageId,
        ':recipient_id' => (int)$user['id'],
    ]);
}

header('Location: inbox.php');
exit;
