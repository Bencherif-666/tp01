<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$php = getenv('PHP_BIN') ?: '/opt/lampp/bin/php';
$dbHost = getenv('TP_BN_DB_HOST') ?: 'localhost';
$dbPort = (int)(getenv('TP_BN_DB_PORT') ?: 3306);
$dbName = getenv('TP_BN_DB_NAME') ?: '';
$dbUser = getenv('TP_BN_DB_USER') ?: 'root';
$dbPassword = getenv('TP_BN_DB_PASSWORD') ?: '';
$testCount = 0;
$failures = [];

function check_test(bool $condition, string $description): void
{
    global $testCount, $failures;
    $testCount++;
    if (!$condition) {
        $failures[] = $description;
    }
}

function pdo_for_tests(): PDO
{
    global $dbHost, $dbPort, $dbName, $dbUser, $dbPassword;
    return new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

final class TestHttpClient
{
    private string $cookie = '';

    public function request(string $url, string $method = 'GET', array $data = []): array
    {
        global $httpBase;

        $headers = ['Connection: close'];
        if ($this->cookie !== '') {
            $headers[] = 'Cookie: ' . $this->cookie;
        }
        $content = '';
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $content = http_build_query($data);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 10,
            ],
        ]);
        $body = @file_get_contents($httpBase . $url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        $location = '';

        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                $status = (int)$match[1];
            } elseif (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, strlen('Location:')));
            } elseif (stripos($header, 'Set-Cookie:') === 0
                && preg_match('/^\s*Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $match)) {
                $this->cookie = $match[1] . '=' . $match[2];
            }
        }

        return [
            'status' => $status,
            'location' => $location,
            'body' => $body === false ? '' : $body,
            'cookie' => $this->cookie,
        ];
    }
}

function create_test_accounts(PDO $pdo): array
{
    $accounts = [
        ['test.admin@example.test', 'Admin'],
        ['test.user@example.test', 'User'],
        ['test.analyst@example.test', 'Analyst'],
        ['test.teacher@example.test', 'Enseignant'],
        ['test.controller@example.test', 'Controller'],
        ['test.unknown@example.test', 'UnexpectedRole'],
    ];
    $insert = $pdo->prepare('INSERT INTO `user` (email, mdp, role) VALUES (:email, :mdp, :role)');
    foreach ($accounts as [$email, $role]) {
        $insert->execute([
            ':email' => $email,
            ':mdp' => password_hash('TestPass123!', PASSWORD_DEFAULT),
            ':role' => $role,
        ]);
    }
    return array_column($accounts, 1, 0);
}

function login_client(TestHttpClient $client, string $email, string $password = 'TestPass123!'): array
{
    $client->request('/login.php');
    return $client->request('/login.php', 'POST', ['email' => $email, 'mdp' => $password]);
}

function project_endpoints(string $root): array
{
    $endpoints = [];
    $ignored = ['.git', 'config', 'database', 'includes', 'tests', 'uploads', 'vendor'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
        $parts = explode('/', $relative);
        if (array_intersect($parts, $ignored)) {
            continue;
        }
        $endpoints[] = '/' . $relative;
    }
    sort($endpoints);
    return $endpoints;
}

function table_counts(PDO $pdo): array
{
    $tables = [
        'nationalite', 'moyen_transport', 'sport', 'modele_voiture', 'personne',
        'filiere', 'enseignant', 'module', 'voyager', 'note', 'user', 'analyst',
        'communication_message',
    ];
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    }
    return $counts;
}

$pdo = pdo_for_tests();
$accounts = create_test_accounts($pdo);
require_once $root . '/includes/fonctions.php';
require_once $root . '/includes/bulletin_mail.php';

check_test(h('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;', 'h() escapes HTML entities');

$_POST = ['values' => ['ok', 'invalid', 'also-ok']];
check_test(post_liste('values', ['ok', 'also-ok']) === ['ok', 'also-ok'], 'post_liste() applies its whitelist');
$_POST = ['values' => 'not-an-array'];
check_test(post_liste('values', ['ok']) === [], 'post_liste() rejects scalar input');
$_POST = [];

check_test(enregistrer_image([]) === [null, null], 'enregistrer_image() accepts no upload');
[$noImage, $partialUploadError] = enregistrer_image(['error' => UPLOAD_ERR_PARTIAL]);
check_test($noImage === null && $partialUploadError !== null, 'enregistrer_image() reports partial uploads');

session_name('TPBN_TEST_SESSION');
ini_set('session.use_cookies', '0');
session_start();
$sessionBeforeLogin = session_id();
connecter_utilisateur(['id' => 17, 'email' => 'session@example.test', 'role' => 'Admin']);
check_test(session_id() !== $sessionBeforeLogin, 'connecter_utilisateur() regenerates the session ID');
check_test(
    $_SESSION['user'] === [
        'id' => 17,
        'username' => 'session@example.test',
        'email' => 'session@example.test',
        'role' => 'Admin',
    ],
    'connecter_utilisateur() stores user identity and role'
);
check_test(
    est_connecte() && est_admin()
        && (utilisateur_connecte()['username'] ?? '') === 'session@example.test',
    'session accessors recognize an Admin and expose the stored identity'
);
$_SESSION['user']['role'] = 'Analyst';
check_test(est_analyst() && !est_admin(), 'role helpers distinguish Analyst from Admin');
$_SESSION['user']['role'] = 'Enseignant';
check_test(est_enseignant(), 'role helper recognizes Enseignant');
$_SESSION['user']['role'] = 'Controller';
check_test(est_controller(), 'role helper recognizes Controller');
$_SESSION['user']['role'] = 'User';
check_test(est_user(), 'role helper recognizes User');
deconnecter_utilisateur();
check_test(session_status() === PHP_SESSION_NONE && empty($_SESSION), 'deconnecter_utilisateur() clears and destroys the session');

$adminId = (int)$pdo->query("SELECT id FROM `user` WHERE email = 'test.admin@example.test'")->fetchColumn();
$userId = (int)$pdo->query("SELECT id FROM `user` WHERE email = 'test.user@example.test'")->fetchColumn();
$message = $pdo->prepare('INSERT INTO communication_message (sender_id, recipient_id, message) VALUES (:sender, :recipient, :message)');
$message->execute([':sender' => $adminId, ':recipient' => $userId, ':message' => 'test unread message']);
$unread = $pdo->prepare('SELECT COUNT(*) FROM communication_message WHERE recipient_id = :id AND read_at IS NULL');
$unread->execute([':id' => $userId]);
check_test(
    nombre_messages_nonlus($pdo, $userId) === (int)$unread->fetchColumn(),
    'nombre_messages_nonlus() matches unread message count'
);

$filierePageOutput = '';
ob_start();
require $root . '/filiere/liste.php';
$filierePageOutput = (string)ob_get_clean();
check_test(str_contains($filierePageOutput, 'Liste des filières'), 'filiere list page renders with its function definitions');
check_test(ef_point(2.0, 3.0, 1.0, 0.0) === [3.0, 3.0], 'ef_point() computes a chart coordinate');
check_test(str_contains(ef_camembert(0, 0), 'Aucune moyenne'), 'ef_camembert() handles empty data');
check_test(str_contains(ef_camembert(2, 0), '<circle'), 'ef_camembert() handles an all-passing cohort');
check_test(str_contains(ef_camembert(1, 1), '<path'), 'ef_camembert() draws mixed results');
check_test(str_contains(ef_histogramme(0, 0), 'Aucune moyenne'), 'ef_histogramme() handles empty data');
check_test(str_contains(ef_histogramme(2, 1), '<rect'), 'ef_histogramme() draws non-empty data');
check_test(drapeau_url('') === null, 'drapeau_url() handles empty values');
check_test(drapeau_url('https://example.test/flag.png') === 'https://example.test/flag.png', 'drapeau_url() preserves remote image URLs');
$studentsOutput = '';
ob_start();
afficher_etudiants_filiere($pdo, ['id' => 3, 'nom' => '3ISIL']);
$studentsOutput = (string)ob_get_clean();
check_test(str_contains($studentsOutput, 'Étudiants de la filière'), 'afficher_etudiants_filiere() renders student and result summaries');

$previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
ob_start();
require $root . '/nationalite/enregistrer.php';
ob_end_clean();
if ($previousRequestMethod === null) {
    unset($_SERVER['REQUEST_METHOD']);
} else {
    $_SERVER['REQUEST_METHOD'] = $previousRequestMethod;
}
check_test(assurer_colonne_drapeau($pdo), 'assurer_colonne_drapeau() recognizes the schema');
check_test(drapeau_depuis_api('x') === null, 'drapeau_depuis_api() rejects invalid country codes without network access');
$uploadErrors = [];
check_test(
    drapeau_depuis_upload(['error' => UPLOAD_ERR_NO_FILE], $uploadErrors) === null && $uploadErrors === [],
    'drapeau_depuis_upload() accepts an omitted flag'
);
$uploadErrors = [];
drapeau_depuis_upload(['error' => UPLOAD_ERR_PARTIAL], $uploadErrors);
check_test($uploadErrors !== [], 'drapeau_depuis_upload() reports failed uploads');
$uploadErrors = [];
drapeau_depuis_upload(['error' => UPLOAD_ERR_OK, 'size' => 2 * 1024 * 1024 + 1], $uploadErrors);
check_test($uploadErrors !== [], 'drapeau_depuis_upload() enforces its file-size limit');
$uploadErrors = [];
drapeau_depuis_upload(['error' => UPLOAD_ERR_OK, 'size' => 10, 'tmp_name' => '/missing/test-image'], $uploadErrors);
check_test($uploadErrors !== [], 'drapeau_depuis_upload() rejects non-image files');
$pngPath = tempnam(sys_get_temp_dir(), 'tpbn-test-image-');
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lfgAAAAASUVORK5CYII=', true);
$validImage = false;
if ($pngPath !== false && $pngBytes !== false && file_put_contents($pngPath, $pngBytes) !== false) {
    $uploadErrors = [];
    $uploadedFlag = drapeau_depuis_upload([
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($pngBytes),
        'tmp_name' => $pngPath,
    ], $uploadErrors);
    $validImage = ($uploadedFlag['ext'] ?? '') === 'png'
        && ($uploadedFlag['data'] ?? null) === $pngBytes
        && $uploadErrors === [];
}
if ($pngPath !== false) {
    unlink($pngPath);
}
check_test($validImage, 'drapeau_depuis_upload() reads valid PNG image data');

$student = $pdo->query(
    'SELECT p.id FROM personne p JOIN note n ON n.etudiant_id = p.id WHERE p.email IS NULL LIMIT 1'
)->fetch();
check_test((bool)$student, 'SQL fixtures provide a student with grades');
if ($student) {
    $studentId = (int)$student['id'];
    $setEmail = $pdo->prepare('UPDATE personne SET email = :email WHERE id = :id');
    $setEmail->execute([':email' => 'student@example.test', ':id' => $studentId]);
    $mailData = charger_bulletin_mail($pdo, $studentId);
    $mailContent = contenu_bulletin_mail($mailData);
    check_test($mailData['notes'] !== [], 'charger_bulletin_mail() loads grade rows');
    check_test(filter_var($mailData['etudiant']['email'], FILTER_VALIDATE_EMAIL) !== false, 'bulletin recipient comes from the student record');
    check_test(str_contains($mailContent['text'], 'Moyenne générale :'), 'plain-text bulletin contains the average');
    check_test(str_contains($mailContent['html'], 'Résultat :</strong>'), 'HTML bulletin contains the result');
    check_test(
        !str_contains($mailContent['html'], 'student@example.test'),
        'HTML bulletin omits the private recipient address'
    );
    $injectionBulletin = $mailData;
    $injectionBulletin['etudiant']['nom_prenom'] = "Élève\r\nBcc: attacker@example.test";
    $injectionSubject = contenu_bulletin_mail($injectionBulletin)['subject'];
    check_test(!str_contains($injectionSubject, "\r") && !str_contains($injectionSubject, "\n"), 'bulletin subject removes header newlines');

    $setEmail->execute([':email' => 'invalid-address', ':id' => $studentId]);
    $invalidRejected = false;
    try {
        charger_bulletin_mail($pdo, $studentId);
    } catch (BulletinMailException $e) {
        $invalidRejected = str_contains($e->getMessage(), 'invalide');
    }
    check_test($invalidRejected, 'bulletin loader rejects invalid student email');

    $setEmail->execute([':email' => null, ':id' => $studentId]);
    $missingRejected = false;
    try {
        charger_bulletin_mail($pdo, $studentId);
    } catch (BulletinMailException $e) {
        $missingRejected = str_contains($e->getMessage(), 'Aucune adresse');
    }
    check_test($missingRejected, 'bulletin loader rejects missing student email');
}
check_test(
    str_contains(contenu_bulletin_mail([
        'etudiant' => ['civilite' => '', 'nom_prenom' => 'Test', 'numero' => '1', 'filiere' => 'TC'],
        'notes' => [],
        'total_coefficients' => 0,
        'moyenne' => 0,
        'resultat' => 'AJOURNÉ',
    ])['text'], 'Résultat : AJOURNÉ'),
    'contenu_bulletin_mail() formats the plain-text result when no notes exist'
);

$serverSocket = stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketMessage);
if ($serverSocket === false) {
    throw new RuntimeException("Cannot allocate test-server port: $socketMessage ($socketError)");
}
$serverAddress = stream_socket_get_name($serverSocket, false);
fclose($serverSocket);
$serverPort = (int)substr(strrchr((string)$serverAddress, ':'), 1);
$httpBase = 'http://127.0.0.1:' . $serverPort;

$serverEnv = getenv();
$serverEnv['TP_BN_DB_HOST'] = $dbHost;
$serverEnv['TP_BN_DB_PORT'] = (string)$dbPort;
$serverEnv['TP_BN_DB_USER'] = $dbUser;
$serverEnv['TP_BN_DB_PASSWORD'] = $dbPassword;
$serverEnv['TP_BN_DB_NAME'] = $dbName;
$serverProcess = proc_open(
    [$php, '-S', '127.0.0.1:' . $serverPort, '-t', $root],
    [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'a'],
        2 => ['file', '/dev/null', 'a'],
    ],
    $serverPipes,
    $root,
    $serverEnv
);
if (!is_resource($serverProcess)) {
    throw new RuntimeException('Could not start PHP built-in test server.');
}
register_shutdown_function(static function () use (&$serverProcess): void {
    if (is_resource($serverProcess)) {
        proc_terminate($serverProcess);
        proc_close($serverProcess);
    }
});

$serverReady = false;
for ($attempt = 0; $attempt < 50; $attempt++) {
    $socket = @fsockopen('127.0.0.1', $serverPort, $socketError, $socketMessage, 0.1);
    if ($socket !== false) {
        fclose($socket);
        $serverReady = true;
        break;
    }
    usleep(100000);
}
check_test($serverReady, 'PHP built-in server starts for endpoint tests');
check_test(
    http_get($httpBase . '/login.php') !== null,
    'http_get() returns a successful local HTTP response'
);
check_test(
    http_get('http://127.0.0.1:1/not-listening') === null,
    'http_get() returns null for a failed local HTTP request'
);

$anonymous = new TestHttpClient();
$loginPage = $anonymous->request('/login.php');
check_test($loginPage['status'] === 200 && str_contains($loginPage['body'], 'Connexion'), 'login page renders');
$unauthenticated = $anonymous->request('/bulletin/bulletin.php');
check_test(
    $unauthenticated['status'] === 302 && str_contains($unauthenticated['location'], 'login.php'),
    'protected page redirects an anonymous visitor'
);

$invalidLogin = new TestHttpClient();
$invalidLogin->request('/login.php');
$invalidLoginResponse = $invalidLogin->request('/login.php', 'POST', [
    'email' => 'nobody@example.test',
    'mdp' => 'wrong',
]);
check_test($invalidLoginResponse['status'] === 200 && str_contains($invalidLoginResponse['body'], 'User Inexistant'), 'invalid login is rejected');

$publicSignup = new TestHttpClient();
$publicSignup->request('/register.php');
$publicSignupResponse = $publicSignup->request('/register.php', 'POST', [
    'email' => 'public-signup@example.test',
    'mdp' => 'SignupPass123!',
    'role' => 'Admin',
]);
$publicSignupRole = $pdo->prepare('SELECT role FROM `user` WHERE email = :email');
$publicSignupRole->execute([':email' => 'public-signup@example.test']);
check_test(
    $publicSignupResponse['status'] === 302
        && str_contains($publicSignupResponse['location'], 'bulletin_user.php')
        && $publicSignupRole->fetchColumn() === 'User',
    'public signup cannot create a privileged account'
);
$publicSignupDenied = $publicSignup->request('/index.php');
check_test(
    $publicSignupDenied['status'] === 302
        && str_contains($publicSignupDenied['location'], 'bulletin_user.php'),
    'public signup account cannot access the administrator dashboard'
);

$admin = new TestHttpClient();
$admin->request('/login.php');
$oldSessionCookie = $admin->request('/login.php')['cookie'];
$adminLogin = $admin->request('/login.php', 'POST', [
    'email' => 'test.admin@example.test',
    'mdp' => 'TestPass123!',
]);
check_test($adminLogin['status'] === 302 && str_contains($adminLogin['location'], 'index.php'), 'Admin login redirects to the dashboard');
check_test($adminLogin['cookie'] !== '' && $adminLogin['cookie'] !== $oldSessionCookie, 'successful login rotates the session cookie');
$welcome = $admin->request('/index.php');
check_test($welcome['status'] === 200 && str_contains($welcome['body'], 'Bienvenue, test.admin@example.test'), 'authenticated navigation shows the session username');
$filiereSearch = $admin->request('/filiere/liste.php?q=3ISIL');
check_test(
    $filiereSearch['status'] === 200
        && str_contains($filiereSearch['body'], 'Diagramme circulaire')
        && str_contains($filiereSearch['body'], 'Histogramme'),
    'filiere search exercises student listing and chart helpers'
);
$filiereFormSearch = $admin->request('/filiere/filiere.php?id=3');
check_test(
    $filiereFormSearch['status'] === 200
        && str_contains($filiereFormSearch['body'], 'Histogramme : réussis / échoués'),
    'filiere form exercises its duplicated chart and student-summary functions'
);
$studentBulletin = $admin->request('/bulletin/afficher_bulletin.php?numero=15');
check_test(
    $studentBulletin['status'] === 200 && str_contains($studentBulletin['body'], 'Bulletin de notes'),
    'student bulletin endpoint renders a seeded student'
);

$testSuffix = strtoupper(bin2hex(random_bytes(4)));
$testFiliereName = 'TEST-' . $testSuffix;
$filiereCreate = $admin->request('/filiere/enregistrer.php', 'POST', ['nom' => $testFiliereName]);
$filiereId = (int)$pdo->query("SELECT id FROM filiere WHERE nom = " . $pdo->quote($testFiliereName))->fetchColumn();
check_test(
    $filiereCreate['status'] === 200
        && str_contains($filiereCreate['body'], 'Filière ajoutée')
        && $filiereId > 0,
    'filiere endpoint creates a new record'
);
$filiereUpdate = $admin->request('/filiere/enregistrer.php', 'POST', [
    'action' => 'modifier',
    'id' => $filiereId,
    'nom' => $testFiliereName . '-EDIT',
]);
$filiereUpdatedName = $pdo->query("SELECT nom FROM filiere WHERE id = $filiereId")->fetchColumn();
check_test(
    $filiereUpdate['status'] === 200 && $filiereUpdatedName === $testFiliereName . '-EDIT',
    'filiere endpoint updates an existing record'
);
$filiereDelete = $admin->request('/filiere/supprimer.php', 'POST', ['id' => $filiereId]);
check_test(
    $filiereDelete['status'] === 200
        && (int)$pdo->query("SELECT COUNT(*) FROM filiere WHERE id = $filiereId")->fetchColumn() === 0,
    'filiere endpoint deletes an existing record'
);

$testModuleCode = 'T' . $testSuffix;
$moduleData = [
    'code_module' => $testModuleCode,
    'designation' => 'Test module ' . $testSuffix,
    'coefficient' => 1,
    'volume_horaire' => 30,
    'filiere' => '3ISIL',
];
$moduleCreate = $admin->request('/module/enregistrer.php', 'POST', $moduleData);
$moduleId = (int)$pdo->query("SELECT id FROM module WHERE code_module = " . $pdo->quote($testModuleCode))->fetchColumn();
check_test(
    $moduleCreate['status'] === 200
        && str_contains($moduleCreate['body'], 'Module ajouté avec succès')
        && $moduleId > 0,
    'module endpoint creates a new record'
);
$moduleUpdateData = $moduleData;
$moduleUpdateData['action'] = 'modifier';
$moduleUpdateData['id'] = $moduleId;
$moduleUpdateData['code_module'] = $testModuleCode . 'E';
$moduleUpdateData['coefficient'] = 2;
$moduleUpdate = $admin->request('/module/enregistrer.php', 'POST', $moduleUpdateData);
$updatedModule = $pdo->query("SELECT code_module, coefficient FROM module WHERE id = $moduleId")->fetch();
check_test(
    $moduleUpdate['status'] === 200
        && ($updatedModule['code_module'] ?? null) === $testModuleCode . 'E'
        && (int)$updatedModule['coefficient'] === 2,
    'module endpoint updates an existing record'
);

if ($student && $moduleId > 0) {
    $studentId = (int)$student['id'];
    $gradeData = [
        'action' => 'enregistrer',
        'etudiant_id' => $studentId,
        'module_id' => $moduleId,
        'note' => '13.5',
    ];
    $gradeCreate = $admin->request('/bulletin/enregistrer.php', 'POST', $gradeData);
    $gradeRow = $pdo->query(
        "SELECT id, note FROM note WHERE etudiant_id = $studentId AND module_id = $moduleId"
    )->fetch();
    $gradeId = (int)($gradeRow['id'] ?? 0);
    check_test(
        $gradeCreate['status'] === 200
            && str_contains($gradeCreate['body'], 'Note enregistrée avec succès')
            && $gradeId > 0
            && (float)$gradeRow['note'] === 13.5,
        'grade endpoint creates a note'
    );

    $duplicateGrade = $admin->request('/bulletin/enregistrer.php', 'POST', $gradeData);
    check_test(
        str_contains($duplicateGrade['body'], 'Une note existe déjà')
            && (int)$pdo->query(
                "SELECT COUNT(*) FROM note WHERE etudiant_id = $studentId AND module_id = $moduleId"
            )->fetchColumn() === 1,
        'grade endpoint rejects a duplicate student-module note'
    );

    $gradeUpdateData = $gradeData;
    $gradeUpdateData['action'] = 'modifier';
    $gradeUpdateData['note_id'] = $gradeId;
    $gradeUpdateData['note'] = '16.25';
    $gradeUpdate = $admin->request('/bulletin/enregistrer.php', 'POST', $gradeUpdateData);
    $updatedGrade = $pdo->query("SELECT note FROM note WHERE id = $gradeId")->fetchColumn();
    check_test(
        $gradeUpdate['status'] === 200
            && str_contains($gradeUpdate['body'], 'Note mise à jour avec succès')
            && (float)$updatedGrade === 16.25,
        'grade endpoint updates an existing note'
    );

    $gradeDelete = $admin->request('/bulletin/supprimer.php', 'POST', [
        'id' => $gradeId,
        'etudiant_id' => $studentId,
        'module_id' => $moduleId,
    ]);
    check_test(
        $gradeDelete['status'] === 200
            && (int)$pdo->query("SELECT COUNT(*) FROM note WHERE id = $gradeId")->fetchColumn() === 0,
        'grade endpoint deletes an existing note'
    );
}

$moduleDelete = $admin->request('/module/supprimer.php', 'POST', ['id' => $moduleId]);
check_test(
    $moduleDelete['status'] === 200
        && (int)$pdo->query("SELECT COUNT(*) FROM module WHERE id = $moduleId")->fetchColumn() === 0,
    'module endpoint deletes an existing record after its notes are removed'
);

$analystId = (int)$pdo->query("SELECT id FROM `user` WHERE email = 'test.analyst@example.test'")->fetchColumn();
$testMessageText = 'Integration test message ' . $testSuffix;
$messageSent = $admin->request('/communication/admin.php', 'POST', [
    'recipient_id' => $analystId,
    'message' => $testMessageText,
]);
$testMessageQuery = $pdo->prepare(
    'SELECT id FROM communication_message WHERE recipient_id = :recipient_id AND message = :message'
);
$testMessageQuery->execute([':recipient_id' => $analystId, ':message' => $testMessageText]);
$testMessageId = (int)$testMessageQuery->fetchColumn();
check_test(
    $messageSent['status'] === 302
        && str_contains($messageSent['location'], 'admin.php?succes=')
        && $testMessageId > 0,
    'communication endpoint sends a message to an eligible recipient'
);

$routes = project_endpoints($root);
$endpointFailures = [];
foreach ($routes as $route) {
    if ($route === '/logout.php') {
        continue;
    }
    $response = $admin->request($route);
    check_test($response['status'] > 0 && $response['status'] < 500, 'GET endpoint responds without server error: ' . $route);
    if ($response['status'] >= 500 || $response['status'] === 0) {
        $endpointFailures[] = $route . ' returned HTTP ' . $response['status'];
    }
}

$handlerRoutes = array_values(array_filter($routes, static function (string $route): bool {
    return in_array(basename($route), [
        'enregistrer.php', 'supprimer.php', 'upload_image.php', 'mark_read.php',
        'send_mail_native.php', 'send_mail_phpmailer.php', 'afficher_php.php',
    ], true) || $route === '/communication/admin.php';
}));
$countsBeforeInvalidPosts = table_counts($pdo);
foreach ($handlerRoutes as $route) {
    $response = $admin->request($route, 'POST', []);
    check_test($response['status'] > 0 && $response['status'] < 500, 'invalid POST is handled safely: ' . $route);
}
check_test(table_counts($pdo) === $countsBeforeInvalidPosts, 'invalid POST requests do not change fixture table counts');

$roleTests = [
    'User' => ['test.user@example.test', '/bulletin_user.php'],
    'Analyst' => ['test.analyst@example.test', '/statistiques/statistiques.php'],
    'Enseignant' => ['test.teacher@example.test', '/enseignant/enseignant.php'],
    'Controller' => ['test.controller@example.test', '/communication/controller.php'],
];
foreach ($roleTests as $role => [$email, $allowedRoute]) {
    $client = new TestHttpClient();
    $login = login_client($client, $email);
    check_test($login['status'] === 302, $role . ' account can log in');
    $allowed = $client->request($allowedRoute);
    check_test($allowed['status'] === 200, $role . ' can open its permitted page');
    $denied = $client->request('/bulletin/bulletin.php');
    check_test(
        $denied['status'] === 302 && $denied['location'] !== '/bulletin/bulletin.php',
        $role . ' cannot open the administrator bulletin page'
    );

    if ($role === 'Analyst' && $testMessageId > 0) {
        $inbox = $client->request('/communication/inbox.php');
        check_test(
            $inbox['status'] === 200 && str_contains($inbox['body'], $testMessageText),
            'eligible recipient can read the message in the inbox'
        );
        $markRead = $client->request('/communication/mark_read.php', 'POST', ['id' => $testMessageId]);
        $readAt = $pdo->query(
            "SELECT read_at FROM communication_message WHERE id = $testMessageId"
        )->fetchColumn();
        check_test(
            $markRead['status'] === 302 && $readAt !== null,
            'recipient can mark their message as read'
        );
    }

    if ($role === 'User') {
        $mailDenied = $client->request('/bulletin/send_mail_native.php', 'POST', ['etudiant_id' => 1]);
        check_test($mailDenied['status'] === 302, 'non-admin cannot invoke native mail handler');
    }
}

$unknownRole = new TestHttpClient();
login_client($unknownRole, 'test.unknown@example.test');
$unknownDenied = $unknownRole->request('/index.php');
check_test(
    $unknownDenied['status'] === 302 && str_contains($unknownDenied['location'], 'login.php'),
    'unknown account role is denied protected routes'
);

$mailStudent = $pdo->query('SELECT id FROM personne WHERE id = ' . (int)($student['id'] ?? 0))->fetch();
if ($mailStudent) {
    $csrfRejected = $admin->request('/bulletin/send_mail_native.php', 'POST', [
        'etudiant_id' => (int)$mailStudent['id'],
    ]);
    check_test(
        $csrfRejected['status'] === 302 && str_contains($csrfRejected['location'], 'bulletin.php'),
        'mail handler rejects missing CSRF tokens and redirects to the bulletin'
    );
}

$logout = $admin->request('/logout.php');
check_test($logout['status'] === 302 && str_contains($logout['location'], 'login.php'), 'logout redirects to login');
check_test(
    str_ends_with($logout['cookie'], '=') || str_ends_with($logout['cookie'], '=deleted'),
    'logout expires the session cookie'
);
$afterLogout = $admin->request('/bulletin/bulletin.php');
check_test(
    $afterLogout['status'] === 302 && str_contains($afterLogout['location'], 'login.php'),
    'logout invalidates access to protected routes'
);

proc_terminate($serverProcess);
proc_close($serverProcess);

if ($failures) {
    fwrite(STDERR, "\nFAILED (" . count($failures) . " of $testCount checks):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - $failure\n");
    }
    if ($endpointFailures) {
        fwrite(STDERR, "\nEndpoint failures:\n - " . implode("\n - ", $endpointFailures) . "\n");
    }
    exit(1);
}

echo "PASS: $testCount checks, including " . count($routes) . " PHP endpoints and " . count($handlerRoutes) . " POST handlers.\n";
