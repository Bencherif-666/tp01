<?php
// config/mail.php : Configuration centralisée de la messagerie (mail() et PHPMailer SMTP)

return [
    // Expéditeur par défaut
    'from_email' => 'scolarite@univ-sba.dz',
    'from_name'  => 'Scolarité — Université Djillali Liabes',
    'reply_to'   => 'scolarite@univ-sba.dz',

    // Paramètres SMTP pour PHPMailer
    'smtp' => [
        'host'       => 'smtp.gmail.com',         // Serveur SMTP (ex: smtp.gmail.com, mail.univ-sba.dz, localhost)
        'port'       => 587,                      // Port SMTP (587 TLS, 465 SSL, 25/1025 local)
        'auth'       => true,                     // Nécessite une authentification
        'username'   => 'scolarite.tp01@gmail.com', // Identifiant / email SMTP
        'password'   => '',                       // Mot de passe ou mot de passe d'application SMTP
        'encryption' => 'tls',                    // 'tls', 'ssl', ou '' (vide)
        'timeout'    => 8,                        // Délai d'expiration (secondes)
        'debug'      => 0,                        // Niveau de débogage SMTP (0 = off)
    ],

    // Répertoire d'archivage des emails envoyés (pour consultation et historique)
    'archive_dir' => __DIR__ . '/../uploads/emails',
];
