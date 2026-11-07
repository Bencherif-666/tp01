<?php
// nationalite/enregistrer.php : insère une nationalité (+ son drapeau) dans la table `nationalite`

// Affichage des erreurs (utile pendant le TP ; à retirer en production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fonctions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nationalite.php');
    exit;
}

/* ------------------------------------------------------------------
 * Fonctions utilitaires
 * ------------------------------------------------------------------ */

// Crée la colonne `drapeau` si elle n'existe pas encore (pas besoin de réimporter le SQL)
function assurer_colonne_drapeau(PDO $pdo): bool
{
    try {
        $existe = $pdo->query("SHOW COLUMNS FROM nationalite LIKE 'drapeau'")->fetch();
        if (!$existe) {
            $pdo->exec("ALTER TABLE nationalite ADD COLUMN drapeau VARCHAR(255) DEFAULT NULL");
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Télécharge une URL (cURL si disponible, sinon file_get_contents). Retourne le contenu ou null.
function http_get(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'TP01-PAW/1.0',
            CURLOPT_SSL_VERIFYPEER => false,   // évite les erreurs de certificat sous XAMPP
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $http === 200) ? $body : null;
    }

    $ctx  = stream_context_create([
        'http' => ['timeout' => 10, 'user_agent' => 'TP01-PAW/1.0'],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

// Cherche le drapeau d'un code pays via restcountries.com (puis flagcdn.com en secours)
function drapeau_depuis_api(string $code): ?array
{
    if (!preg_match('/^[A-Za-z]{2,3}$/', $code)) {
        return null;
    }

    $urlImage = null;

    $json = http_get('https://restcountries.com/v3.1/alpha/' . rawurlencode($code) . '?fields=cca2,flags');
    if ($json !== null) {
        $data = json_decode($json, true);
        if (is_array($data)) {
            $pays     = isset($data[0]) ? $data[0] : $data;
            $urlImage = $pays['flags']['png'] ?? null;
        }
    }

    if (!$urlImage && strlen($code) === 2) {
        $urlImage = 'https://flagcdn.com/w320/' . strtolower($code) . '.png';
    }
    if (!$urlImage) {
        return null;
    }

    $corps = http_get($urlImage);
    if ($corps === null || @getimagesizefromstring($corps) === false) {
        return null;
    }
    return ['data' => $corps, 'ext' => 'png'];
}

// Lit une image envoyée manuellement. Retourne ['data','ext'] ou null.
function drapeau_depuis_upload(array $f, array &$erreurs): ?array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $erreurs[] = "Erreur lors de l'envoi de l'image.";
        return null;
    }
    if ($f['size'] > 2 * 1024 * 1024) {
        $erreurs[] = "L'image est trop lourde (2 Mo maximum).";
        return null;
    }
    $info  = @getimagesize($f['tmp_name']);
    $types = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if ($info === false || !isset($types[$info['mime']])) {
        $erreurs[] = "Format d'image non accepté (PNG, JPG, GIF ou WEBP).";
        return null;
    }
    return ['data' => file_get_contents($f['tmp_name']), 'ext' => $types[$info['mime']]];
}

/* ------------------------------------------------------------------
 * 1) Récupération
 * ------------------------------------------------------------------ */
$code    = strtoupper(trim($_POST['code'] ?? ''));
$libelle = strtoupper(trim($_POST['libelle'] ?? ''));

/* 2) Validation */
$erreurs = [];
if ($code === '' || mb_strlen($code) > 3) {
    $erreurs[] = "Le code est obligatoire (3 caractères maximum).";
}
if ($libelle === '' || mb_strlen($libelle) > 40) {
    $erreurs[] = "La nationalité est obligatoire (40 caractères maximum).";
}

$imageManuelle = null;
if (!$erreurs && isset($_FILES['drapeau_fichier'])) {
    $imageManuelle = drapeau_depuis_upload($_FILES['drapeau_fichier'], $erreurs);
}

/* 3) Insertion (la colonne drapeau est créée automatiquement si besoin) */
$colonneOk = assurer_colonne_drapeau($pdo);

if (!$erreurs) {
    try {
        $stmt = $pdo->prepare('INSERT INTO nationalite (code, libelle) VALUES (:code, :libelle)');
        $stmt->execute([':code' => $code, ':libelle' => $libelle]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $erreurs[] = "Ce code existe déjà.";
        } else {
            $erreurs[] = "Erreur de base de données : " . $e->getMessage();
        }
    }
}

/* 4) Drapeau : image manuelle sinon API */
$drapeau       = null;
$avertissement = null;
if (!$erreurs) {
    try {
        $image = $imageManuelle ?? drapeau_depuis_api($code);

        if ($image !== null) {
            $dossier = __DIR__ . '/../uploads/drapeaux/';
            if (!is_dir($dossier)) {
                @mkdir($dossier, 0775, true);
            }
            $nomSur  = preg_replace('/[^a-z0-9]/', '', strtolower($code));
            $nomSur  = $nomSur !== '' ? $nomSur : 'drapeau';
            $fichier = $nomSur . '.' . $image['ext'];

            if (is_dir($dossier) && @file_put_contents($dossier . $fichier, $image['data']) !== false) {
                if ($colonneOk) {
                    $stmt = $pdo->prepare('UPDATE nationalite SET drapeau = :d WHERE code = :code');
                    $stmt->execute([':d' => $fichier, ':code' => $code]);
                    $drapeau = $fichier;
                } else {
                    $avertissement = "Colonne « drapeau » introuvable dans la table nationalite.";
                }
            } else {
                $avertissement = "Impossible d'écrire dans le dossier uploads/drapeaux (vérifiez les droits).";
            }
        } else {
            $avertissement = "Aucun drapeau trouvé pour ce code. Vous pouvez en envoyer un manuellement.";
        }
    } catch (Throwable $e) {
        $avertissement = "Nationalité enregistrée, mais le drapeau a échoué : " . $e->getMessage();
    }
}

$base  = '../';
$titre = 'Enregistrement nationalité';
require __DIR__ . '/../includes/header.php';

if ($erreurs) {
    foreach ($erreurs as $e) {
        echo '<p class="erreur">' . h($e) . '</p>';
    }
    echo '<p><a href="nationalite.php">Retour</a></p>';
} else {
    echo '<p class="ok">Nationalité enregistrée : ' . h($code) . ' | ' . h($libelle) . '</p>';
    if ($drapeau) {
        echo '<p><img src="../uploads/drapeaux/' . h($drapeau) . '" alt="Drapeau ' . h($libelle)
           . '" style="height:50px;border:1px solid #ccc;"></p>';
    }
    if ($avertissement) {
        echo '<p class="erreur">' . h($avertissement) . '</p>';
    }
    echo '<p><a href="nationalite.php">Ajouter une autre</a> | <a href="liste.php">Affichage Liste</a></p>';
}
require __DIR__ . '/../includes/footer.php';
