# Session, style, and report card email configuration

## Tests

The dependency-free test suite checks PHP syntax, shared helpers, session/authentication behavior, role access, and every public PHP endpoint. It creates a uniquely named temporary MySQL database, imports the project schema and seed data, starts PHP’s built-in server, then drops only that temporary database.

Start MySQL, then run:

```sh
./tests/run.sh
```

By default the runner uses `/opt/lampp/bin/php`, `/opt/lampp/bin/mysql`, and the project’s XAMPP defaults (`localhost:3306`, `root`, empty password). Override `PHP_BIN`, `MYSQL_BIN`, `TP_BN_DB_HOST`, `TP_BN_DB_PORT`, `TP_BN_DB_USER`, and `TP_BN_DB_PASSWORD` for another local setup. It never connects to or modifies the configured `tp01_paw` database.

## Added files

- `includes/bulletin_mail.php`: PDO loading and report card HTML/text generation.
- `bulletin/send_mail_native.php` and `bulletin/send_mail_phpmailer.php`: Admin/CSRF-protected sending.
- `config/mail.example.php`: SMTP configuration template; `config/mail.php` is local and ignored by Git.
- `database/migration_student_email.sql`: adds nullable `personne.email`.

## Installation

Run `database/migration_student_email.sql` against the `tp01_paw` database. Then enter email addresses through the Student forms or directly in `personne.email`. Login stores the identifier, username email address, and role in the session; application pages use the existing access control.

The shared `css/style.css` stylesheet is loaded by the shared header and the login page. Report cards hide navigation, search, and actions when printed.

### Native sending on XAMPP for Windows

In the `php.ini` file actually used by Apache, under `[mail function]`, set:

```ini
sendmail_path = "\"C:\xampp\mailtodisk\mailtodisk.exe\""
```

This configuration sends messages to XAMPP's output folder (`C:\xampp\mailoutput`). Verify that the path matches your installation. Save `php.ini`, then restart Apache from the XAMPP control panel. This project does not modify any system files.

### PHPMailer/Gmail

If Composer is available, run `composer require phpmailer/phpmailer` from the project root. Since Composer is not installed in this environment, the official PHPMailer 6.10.0 sources (`Exception.php`, `PHPMailer.php`, `SMTP.php`, and their license) are included under `vendor/phpmailer/phpmailer/src/` and loaded by namespace, without `PHPMailerAutoload.php`.

Copy `config/mail.example.php` to `config/mail.php` and enter the Gmail address and app password. To obtain an app password, enable two-step verification on the Google account, then create an app password in the Google security settings. Use this 16-character password, never the regular Gmail password. SMTP debugging is disabled by default.

## Manual verification

1. Log in with an Admin account and verify that the account name and “Déconnexion” appear. Open a protected page without a session (it should redirect to login), then click “Déconnexion” and verify that the session has ended.
2. Open the report card for a student whose `personne.email` is set. Verify the responsive display and print preview, which should exclude navigation, search, and buttons.
3. Configure `php.ini`, restart Apache, and click “Envoyer Mail (sans PHPMailer)”; verify the confirmation and the file in `mailoutput`.
4. Configure `config/mail.php` and PHPMailer, click “Envoyer Mail (avec PHPMailer)”, and verify the confirmation and receipt. An empty or invalid address should display an error.
