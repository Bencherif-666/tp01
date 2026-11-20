-- ============================================================
-- TP01 - PAW : Migration Authentification (table user + seeds)
-- ============================================================
USE tp01_paw;

CREATE TABLE IF NOT EXISTS `user` (
  `id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `mdp` VARCHAR(255) NOT NULL,
  `role` VARCHAR(255) NOT NULL DEFAULT 'User'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Analyst profiles
CREATE TABLE IF NOT EXISTS analyst (
  id INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT(10) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_analyst_user
    FOREIGN KEY (user_id) REFERENCES `user`(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS communication_message (
  id INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sender_id INT(10) NOT NULL,
  recipient_id INT(10) NOT NULL,
  message TEXT NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_communication_sender
    FOREIGN KEY (sender_id) REFERENCES `user`(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_communication_recipient
    FOREIGN KEY (recipient_id) REFERENCES `user`(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_communication_recipient_read (recipient_id, read_at),
  INDEX idx_communication_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Link teacher profiles to accounts
ALTER TABLE enseignant
  ADD COLUMN IF NOT EXISTS user_id INT(10) DEFAULT NULL,
  ADD UNIQUE INDEX IF NOT EXISTS uk_enseignant_user (user_id);


-- Comptes de test :
-- 1) Admin : admin@example.com / admin123
-- 2) User  : user@example.com  / user123
INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'admin@example.com', '$2y$10$BWZdfL9i87HntjU/NFXZ6.l0csgzfq7MjhpJGrrbSiiKxKAIvevX2', 'Admin'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'admin@example.com');

INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'user@example.com', '$2y$10$CG1YQ/vunfQFLtgZ.1J86eOrfJ85J0jwIVGDKa5e6iBy8s2nH4pdi', 'User'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'user@example.com');

-- Comptes de démonstration Analyst / Enseignant (mot de passe : demo123)
INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'demo.analyst1@example.com', '$2y$10$PRtavV6mZaBUG4p8X69QT.PZTAxbyAwTKtWSLTPlsmhK2LMU64wCO', 'Analyst'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'demo.analyst1@example.com');

INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'demo.analyst2@example.com', '$2y$10$PRtavV6mZaBUG4p8X69QT.PZTAxbyAwTKtWSLTPlsmhK2LMU64wCO', 'Analyst'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'demo.analyst2@example.com');

INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'demo.enseignant1@example.com', '$2y$10$PRtavV6mZaBUG4p8X69QT.PZTAxbyAwTKtWSLTPlsmhK2LMU64wCO', 'Enseignant'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'demo.enseignant1@example.com');

INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'demo.enseignant2@example.com', '$2y$10$PRtavV6mZaBUG4p8X69QT.PZTAxbyAwTKtWSLTPlsmhK2LMU64wCO', 'Enseignant'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'demo.enseignant2@example.com');

INSERT IGNORE INTO analyst (user_id)
SELECT id FROM `user`
WHERE email IN ('demo.analyst1@example.com', 'demo.analyst2@example.com')
  AND role = 'Analyst';

INSERT INTO enseignant
  (numero, civilite, nom_prenom, adresse, date_naissance, lieu_naissance, pays, grade, specialite, filiere, modules, user_id)
SELECT demo.numero, demo.civilite, demo.nom_prenom, demo.adresse, demo.date_naissance,
       demo.lieu_naissance, demo.pays, demo.grade, demo.specialite, demo.filiere, demo.modules, u.id
FROM (
  SELECT '9001' AS numero, 'Monsieur' AS civilite, 'Enseignant Démo 1' AS nom_prenom,
         'Adresse de démonstration' AS adresse, '1980-04-12' AS date_naissance,
         'Sidi Bel Abbès' AS lieu_naissance, 'Algérie' AS pays, 'MAA' AS grade,
         'Informatique' AS specialite, '3ISIL' AS filiere, 'DEMO-BDD, DEMO-POO' AS modules,
         'demo.enseignant1@example.com' AS email
  UNION ALL
  SELECT '9002', 'Madame', 'Enseignante Démo 2', 'Adresse de démonstration',
         '1985-09-23', 'Oran', 'Algérie', 'MAB', 'Réseaux', '3SI',
         'RES-3SI, ARCH-3SI', 'demo.enseignant2@example.com'
) AS demo
JOIN `user` u ON u.email = demo.email AND u.role = 'Enseignant'
WHERE NOT EXISTS (SELECT 1 FROM enseignant e WHERE e.user_id = u.id);

INSERT INTO communication_message (sender_id, recipient_id, message, read_at)
SELECT sender.id, recipient.id, demo.message,
       CASE WHEN demo.read_status = 1 THEN CURRENT_TIMESTAMP ELSE NULL END
FROM (
  SELECT 'demo.analyst1@example.com' AS email, 'Message de démonstration 1 pour Analyst 1.' AS message, 0 AS read_status
  UNION ALL SELECT 'demo.analyst1@example.com', 'Message de démonstration 2 pour Analyst 1.', 1
  UNION ALL SELECT 'demo.analyst2@example.com', 'Message de démonstration 1 pour Analyst 2.', 0
  UNION ALL SELECT 'demo.analyst2@example.com', 'Message de démonstration 2 pour Analyst 2.', 1
  UNION ALL SELECT 'demo.enseignant1@example.com', 'Message de démonstration 1 pour Enseignant 1.', 0
  UNION ALL SELECT 'demo.enseignant1@example.com', 'Message de démonstration 2 pour Enseignant 1.', 1
  UNION ALL SELECT 'demo.enseignant2@example.com', 'Message de démonstration 1 pour Enseignant 2.', 0
  UNION ALL SELECT 'demo.enseignant2@example.com', 'Message de démonstration 2 pour Enseignant 2.', 1
) AS demo
JOIN `user` sender ON sender.email = 'admin@example.com'
JOIN `user` recipient ON recipient.email = demo.email
WHERE NOT EXISTS (
  SELECT 1
  FROM communication_message existing
  WHERE existing.sender_id = sender.id
    AND existing.recipient_id = recipient.id
    AND existing.message = demo.message
);
