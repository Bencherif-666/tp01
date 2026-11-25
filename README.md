# Configuration des sessions, du style et de l’envoi des bulletins

## Fichiers ajoutés

- `includes/bulletin_mail.php` : chargement PDO et génération HTML/texte du bulletin.
- `bulletin/send_mail_native.php` et `bulletin/send_mail_phpmailer.php` : envois protégés admin/CSRF.
- `config/mail.example.php` : modèle de configuration SMTP ; `config/mail.php` est local et ignoré par Git.
- `database/migration_student_email.sql` : ajout de `personne.email` (nullable).

## Installation

Exécutez `database/migration_student_email.sql` dans la base `tp01_paw`. Renseignez ensuite les adresses depuis les formulaires Étudiants ou directement dans `personne.email`. La connexion stocke l’identifiant, l’adresse utilisée comme nom d’utilisateur et le rôle dans la session ; les pages applicatives utilisent le contrôle d’accès existant.

La feuille commune `css/style.css` est chargée par l’en-tête partagé et par la page de connexion. Le bulletin masque navigation, recherche et actions à l’impression.

### Envoi natif sous XAMPP Windows

Dans le `php.ini` réellement utilisé par Apache, sous `[mail function]`, définissez la ligne :

```ini
sendmail_path = "\"C:\xampp\mailtodisk\mailtodisk.exe\""
```

Cette configuration envoie les messages vers le dossier de sortie de XAMPP (`C:\xampp\mailoutput`) ; vérifiez que le chemin correspond à votre installation. Enregistrez `php.ini`, puis redémarrez Apache depuis le panneau XAMPP. Aucun fichier système n’est modifié par ce projet.

### PHPMailer / Gmail

Si Composer est disponible, lancez `composer require phpmailer/phpmailer` à la racine du projet. Composer n’étant pas installé dans cet environnement, les sources officielles PHPMailer 6.10.0 (`Exception.php`, `PHPMailer.php`, `SMTP.php`, avec leur licence) sont incluses sous `vendor/phpmailer/phpmailer/src/` et chargées par espace de noms, sans `PHPMailerAutoload.php`.

Copiez `config/mail.example.php` en `config/mail.php` et renseignez l’adresse Gmail et le mot de passe d’application. Pour obtenir celui-ci : activez la validation en deux étapes dans le compte Google, puis créez un mot de passe d’application dans les paramètres de sécurité Google. Utilisez ce mot de passe à 16 caractères, jamais le mot de passe Gmail habituel. Le débogage SMTP est désactivé par défaut.

## Vérification manuelle

1. Connectez-vous avec un compte Admin et vérifiez que le nom de compte et « Déconnexion » apparaissent. Testez une page protégée sans session (redirection vers la connexion), puis cliquez « Déconnexion » et vérifiez que la session est terminée.
2. Ouvrez le bulletin d’un étudiant dont `personne.email` est défini. Vérifiez l’affichage responsive et l’aperçu avant impression, qui doit exclure navigation, recherche et boutons.
3. Configurez `php.ini`, redémarrez Apache et cliquez « Envoyer Mail (sans PHPMailer) » ; vérifiez la confirmation et le fichier dans `mailoutput`.
4. Configurez `config/mail.php` et PHPMailer, cliquez « Envoyer Mail (avec PHPMailer) », puis vérifiez la confirmation et la réception. Une adresse vide ou invalide doit afficher une erreur.
