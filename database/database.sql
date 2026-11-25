-- ============================================================
-- TP01 - PAW : base de données
-- Importer ce fichier dans phpMyAdmin (onglet "Importer")
-- ============================================================
CREATE DATABASE IF NOT EXISTS tp01_paw
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE tp01_paw;

-- Table des nationalités : code X(3), libellé X(40), drapeau (nom de fichier ou URL)
CREATE TABLE IF NOT EXISTS nationalite (
  code    VARCHAR(3)   NOT NULL PRIMARY KEY,
  libelle VARCHAR(40)  NOT NULL,
  drapeau VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

-- Mise à niveau des bases déjà créées (ajout de la colonne drapeau)
ALTER TABLE nationalite
  ADD COLUMN IF NOT EXISTS drapeau VARCHAR(255) DEFAULT NULL;

-- Une dizaine de nationalités de départ
INSERT IGNORE INTO nationalite (code, libelle) VALUES
  ('DZ', 'ALGERIE'),
  ('FR', 'FRANCE'),
  ('BE', 'BELGIQUE'),
  ('TN', 'TUNISIE'),
  ('MA', 'MAROC'),
  ('DE', 'ALLEMAGNE'),
  ('IT', 'ITALIE'),
  ('ES', 'ESPAGNE'),
  ('CA', 'CANADA'),
  ('US', 'ETATS-UNIS');

-- Drapeaux par défaut pour les nationalités existantes (codes à 2 lettres)
-- Une URL est utilisée tant qu'aucun fichier local n'a été enregistré.
UPDATE nationalite
SET drapeau = CONCAT('https://flagcdn.com/w160/', LOWER(code), '.png')
WHERE (drapeau IS NULL OR drapeau = '')
  AND CHAR_LENGTH(code) = 2;

-- Table des moyens de transport
CREATE TABLE IF NOT EXISTS moyen_transport (
  libelle VARCHAR(20) NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT IGNORE INTO moyen_transport (libelle) VALUES
  ('Bus'),
  ('Avion'),
  ('Bateau'),
  ('Train');

-- Table des sports
CREATE TABLE IF NOT EXISTS sport (
  libelle VARCHAR(40) NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT IGNORE INTO sport (libelle) VALUES
  ('Football'),
  ('Basketball'),
  ('Tennis'),
  ('Natation'),
  ('Athlétisme');

-- Table des modèles de voiture
CREATE TABLE IF NOT EXISTS modele_voiture (
  libelle VARCHAR(50) NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT IGNORE INTO modele_voiture (libelle) VALUES
  ('SMV'),
  ('Fiat'),
  ('Peugeot');

-- Table des personnes
-- plateformes, applications, activites, modules : valeurs séparées par des virgules
CREATE TABLE IF NOT EXISTS personne (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  numero           VARCHAR(10)  NOT NULL,
  civilite         VARCHAR(15)  NOT NULL,
  nom_prenom       VARCHAR(80)  NOT NULL,
  adresse          VARCHAR(150) NOT NULL,
  code_postal      VARCHAR(10)  NOT NULL,
  localite         VARCHAR(60)  NOT NULL,
  pays             VARCHAR(40)  NOT NULL,
  plateformes      VARCHAR(100) DEFAULT '',
  applications     VARCHAR(255) DEFAULT '',
  transport        VARCHAR(20)  NOT NULL DEFAULT '',
  sport            VARCHAR(40)  NOT NULL DEFAULT '',
  activites        VARCHAR(100) NOT NULL DEFAULT '',
  modules          VARCHAR(100) NOT NULL DEFAULT '',
  groupe_sanguin   VARCHAR(10)  NOT NULL DEFAULT '',
  modele_voiture   VARCHAR(150) NOT NULL DEFAULT '',
  filiere          VARCHAR(30)  NOT NULL DEFAULT '',
  nationalite_code VARCHAR(3)   NOT NULL,
  photo            VARCHAR(100) DEFAULT NULL,
  -- Relation : personne.nationalite_code -> nationalite.code
  CONSTRAINT fk_personne_nationalite
    FOREIGN KEY (nationalite_code) REFERENCES nationalite(code)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Mise à niveau des bases déjà créées
ALTER TABLE personne
  ADD COLUMN IF NOT EXISTS transport VARCHAR(20) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS sport VARCHAR(40) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS activites VARCHAR(100) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS modules VARCHAR(100) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS groupe_sanguin VARCHAR(10) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS modele_voiture VARCHAR(50) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS filiere VARCHAR(30) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT NULL;

-- Table des filières
CREATE TABLE IF NOT EXISTS filiere (
  id  INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO filiere (id, nom) VALUES
  (1, 'TC'),
  (2, '2SC'),
  (3, '3ISIL'),
  (4, '3SI'),
  (5, 'M1'),
  (6, 'M2ISI'),
  (7, 'M2WIC'),
  (8, 'M2RSSI'),
  (9, '1ING'),
  (10, '2ING');

-- Table des enseignants
CREATE TABLE IF NOT EXISTS enseignant (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  numero         VARCHAR(10)  NOT NULL,
  civilite       VARCHAR(15)  NOT NULL,
  nom_prenom     VARCHAR(80)  NOT NULL,
  adresse        VARCHAR(150) NOT NULL,
  date_naissance DATE         DEFAULT NULL,
  lieu_naissance VARCHAR(60)  NOT NULL DEFAULT '',
  pays           VARCHAR(40)  NOT NULL,
  grade          VARCHAR(30)  NOT NULL,
  specialite     VARCHAR(80)  NOT NULL DEFAULT '',
  filiere        VARCHAR(50)  NOT NULL DEFAULT '',
  modules        VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

ALTER TABLE enseignant
  ADD COLUMN IF NOT EXISTS filiere VARCHAR(50) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS modules VARCHAR(255) NOT NULL DEFAULT '';

-- Table des modules
CREATE TABLE IF NOT EXISTS module (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  code_module    VARCHAR(20)  NOT NULL,
  designation    VARCHAR(100) NOT NULL,
  coefficient    INT          NOT NULL DEFAULT 1,
  volume_horaire INT          NOT NULL DEFAULT 0,
  filiere        VARCHAR(50)  NOT NULL
) ENGINE=InnoDB;

-- Table des voyages (Voyager)
CREATE TABLE IF NOT EXISTS voyager (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  etudiant_id      INT DEFAULT NULL,
  numero           VARCHAR(10)  NOT NULL,
  civilite         VARCHAR(15)  NOT NULL,
  nom_prenom       VARCHAR(80)  NOT NULL,
  filiere          VARCHAR(30)  NOT NULL DEFAULT '',
  adresse          VARCHAR(150) NOT NULL,
  code_postal      VARCHAR(10)  NOT NULL DEFAULT '',
  localite         VARCHAR(60)  NOT NULL DEFAULT '',
  pays             VARCHAR(40)  NOT NULL,
  plateformes      VARCHAR(100) DEFAULT '',
  applications     VARCHAR(255) DEFAULT '',
  transport        VARCHAR(20)  NOT NULL DEFAULT '',
  sport            VARCHAR(40)  NOT NULL DEFAULT '',
  activites        VARCHAR(100) NOT NULL DEFAULT '',
  modules          VARCHAR(100) NOT NULL DEFAULT '',
  groupe_sanguin   VARCHAR(10)  NOT NULL DEFAULT '',
  modele_voiture   VARCHAR(150) NOT NULL DEFAULT '',
  nationalite_code VARCHAR(3)   DEFAULT NULL,
  photo            VARCHAR(100) DEFAULT NULL,
  travel_methodes  VARCHAR(100) NOT NULL DEFAULT '',
  travel_with      VARCHAR(50)  NOT NULL DEFAULT '',
  description      TEXT         DEFAULT NULL,
  CONSTRAINT fk_voyager_nationalite
    FOREIGN KEY (nationalite_code) REFERENCES nationalite(code)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_voyager_etudiant
    FOREIGN KEY (etudiant_id) REFERENCES personne(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW voyage AS SELECT * FROM voyager;

-- Table des notes (Bulletin de notes)
CREATE TABLE IF NOT EXISTS note (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  etudiant_id INT NOT NULL,
  module_id   INT NOT NULL,
  note        DECIMAL(4, 2) NOT NULL,
  date_saisie TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_note_etudiant
    FOREIGN KEY (etudiant_id) REFERENCES personne(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_note_module
    FOREIGN KEY (module_id) REFERENCES module(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT uk_etudiant_module UNIQUE (etudiant_id, module_id)
) ENGINE=InnoDB;

-- Jeu de démonstration du bulletin (ajouté uniquement si les numéros/codes n'existent pas)
INSERT INTO personne
  (numero, civilite, nom_prenom, adresse, code_postal, localite, pays, filiere, nationalite_code)
SELECT demo.numero, demo.civilite, demo.nom_prenom, 'Adresse de démonstration', '00000',
     'Sidi Bel Abbès', 'Algérie', demo.filiere, 'DZ'
FROM (
  SELECT '15' AS numero, 'Monsieur' AS civilite, 'Démo Bulletin 15' AS nom_prenom, '3ISIL' AS filiere
  UNION ALL SELECT '16', 'Madame', 'Démo Bulletin 16', '3ISIL'
  UNION ALL SELECT '17', 'Monsieur', 'Démo Bulletin 17', '3ISIL'
  UNION ALL SELECT '18', 'Madame', 'Démo Bulletin 18', '2SC'
  UNION ALL SELECT '19', 'Monsieur', 'Démo Bulletin 19', '3SI'
) AS demo
WHERE NOT EXISTS (
  SELECT 1 FROM personne p WHERE p.numero = demo.numero
);

INSERT INTO module (code_module, designation, coefficient, volume_horaire, filiere)
SELECT demo.code_module, demo.designation, demo.coefficient, 45, demo.filiere
FROM (
  SELECT 'DEMO-BDD' AS code_module, 'Base de données' AS designation, 4 AS coefficient, '3ISIL' AS filiere
  UNION ALL SELECT 'DEMO-POO', 'Programmation orientée objet', 3, '3ISIL'
  UNION ALL SELECT 'DEMO-ALG', 'Algorithmique', 3, '3ISIL'
  UNION ALL SELECT 'DEMO-SYS', 'Systèmes', 3, '3ISIL'
  UNION ALL SELECT 'DEMO-WEB', 'Développement web', 4, '3ISIL'
  UNION ALL SELECT 'DEMO-MATH', 'Mathématiques', 3, '2SC'
  UNION ALL SELECT 'DEMO-RES', 'Réseaux', 4, '3SI'
) AS demo
WHERE NOT EXISTS (
  SELECT 1 FROM module m WHERE m.code_module = demo.code_module
);

INSERT IGNORE INTO note (etudiant_id, module_id, note)
SELECT p.id, m.id, demo.note
FROM (
  SELECT '15' AS numero, 'Démo Bulletin 15' AS nom_prenom, 'DEMO-BDD' AS code_module, 15.00 AS note
  UNION ALL SELECT '15', 'Démo Bulletin 15', 'DEMO-POO', 16.00
  UNION ALL SELECT '15', 'Démo Bulletin 15', 'DEMO-ALG', 15.00
  UNION ALL SELECT '15', 'Démo Bulletin 15', 'DEMO-SYS', 17.00
  UNION ALL SELECT '15', 'Démo Bulletin 15', 'DEMO-WEB', 14.00
  UNION ALL SELECT '16', 'Démo Bulletin 16', 'DEMO-BDD', 12.00
  UNION ALL SELECT '16', 'Démo Bulletin 16', 'DEMO-POO', 8.00
  UNION ALL SELECT '16', 'Démo Bulletin 16', 'DEMO-WEB', 17.00
  UNION ALL SELECT '17', 'Démo Bulletin 17', 'DEMO-BDD', 9.00
  UNION ALL SELECT '17', 'Démo Bulletin 17', 'DEMO-ALG', 14.00
  UNION ALL SELECT '18', 'Démo Bulletin 18', 'DEMO-MATH', 18.00
  UNION ALL SELECT '19', 'Démo Bulletin 19', 'DEMO-RES', 11.00
) AS demo
JOIN personne p ON p.numero = demo.numero AND p.nom_prenom = demo.nom_prenom
JOIN module m ON m.code_module = demo.code_module;
-- Seed data for PV and Statistiques
USE tp01_paw;

-- 1. Ensure filiere column values for existing students 4 to 9
UPDATE personne SET filiere = '3SI' WHERE numero = '4' AND (filiere IS NULL OR filiere = '');
UPDATE personne SET filiere = '3SI' WHERE numero = '5' AND (filiere IS NULL OR filiere = '');
UPDATE personne SET filiere = '2SC' WHERE numero = '6' AND (filiere IS NULL OR filiere = '');
UPDATE personne SET filiere = '2SC' WHERE numero = '7' AND (filiere IS NULL OR filiere = '');
UPDATE personne SET filiere = 'TC'  WHERE numero = '8' AND (filiere IS NULL OR filiere = '');
UPDATE personne SET filiere = 'TC'  WHERE numero = '9' AND (filiere IS NULL OR filiere = '');

-- 2. Insert representative students across filieres
INSERT INTO personne (numero, civilite, nom_prenom, adresse, code_postal, localite, pays, filiere, nationalite_code)
SELECT demo.numero, demo.civilite, demo.nom_prenom, demo.adresse, '22000', 'Sidi Bel Abbès', 'Algérie', demo.filiere, 'DZ'
FROM (
    SELECT '10' AS numero, 'Madame' AS civilite, 'Sarah Benali' AS nom_prenom, '12 Rue de la Paix' AS adresse, '3ISIL' AS filiere
    UNION ALL SELECT '11', 'Madame', 'Amina Khelil', '5 Bd Mohamed V', '3ISIL'
    UNION ALL SELECT '12', 'Madame', 'Yasmine Mansouri', '18 Rue Larbi Ben M''hidi', '3SI'
    UNION ALL SELECT '13', 'Monsieur', 'Mehdi Cherif', '7 Rue Didouche Mourad', '3SI'
    UNION ALL SELECT '14', 'Madame', 'Rania Haddad', '24 Av de l''Indépendance', '2SC'
    UNION ALL SELECT '15', 'Madame', 'Lyna Boukhatem', '11 Rue Emir Abdelkader', '2SC'
    UNION ALL SELECT '16', 'Monsieur', 'Bilal Belkacem', '30 Rue Pasteur', 'TC'
    UNION ALL SELECT '17', 'Monsieur', 'Sofiane Amari', '8 Rue Hassiba Ben Bouali', 'TC'
    UNION ALL SELECT '18', 'Monsieur', 'Karim Ziani', '14 Rue Colonel Lotfi', 'M1'
    UNION ALL SELECT '19', 'Madame', 'Nour El Houda', '9 Av des Martyrs', 'M1'
    UNION ALL SELECT '20', 'Monsieur', 'Anis Mebarki', '21 Rue Zighoud Youcef', 'M1'
    UNION ALL SELECT '21', 'Monsieur', 'Walid Touati', '16 Rue Khemisti', 'M2RSSI'
    UNION ALL SELECT '22', 'Madame', 'Fatima Zahra Larbi', '3 Rue de la Liberté', 'M2RSSI'
    UNION ALL SELECT '23', 'Madame', 'Meriem Rahmouni', '10 Rue Frères Bouaddou', 'M2WIC'
    UNION ALL SELECT '24', 'Monsieur', 'Rayan Boutaleb', '4 Av de la Gare', 'M2WIC'
    UNION ALL SELECT '25', 'Monsieur', 'Oussama Taleb', '19 Rue Tripoli', 'M2ISI'
    UNION ALL SELECT '26', 'Madame', 'Manel Bensaada', '7 Bd Zirout Youcef', 'M2ISI'
    UNION ALL SELECT '27', 'Monsieur', 'Adel Ghomari', '13 Rue Victor Hugo', '1ING'
    UNION ALL SELECT '28', 'Madame', 'Chaima Belhadj', '2 Rue Abane Ramdane', '1ING'
    UNION ALL SELECT '29', 'Monsieur', 'Ilyes Hamdani', '28 Rue Ibn Khaldoun', '2ING'
    UNION ALL SELECT '30', 'Madame', 'Souhila Brahimi', '15 Rue des Frères Meslem', '2ING'
) AS demo
WHERE NOT EXISTS (
    SELECT 1 FROM personne p WHERE p.numero = demo.numero
);

-- 3. Insert modules across several filieres with varied coefficients
INSERT INTO module (code_module, designation, coefficient, volume_horaire, filiere)
SELECT mdemo.code_module, mdemo.designation, mdemo.coefficient, mdemo.volume_horaire, mdemo.filiere
FROM (
    -- 3SI
    SELECT 'ALG-3SI' AS code_module, 'Algorithmique Avancée' AS designation, 3 AS coefficient, 45 AS volume_horaire, '3SI' AS filiere
    UNION ALL SELECT 'RES-3SI', 'Réseaux Informatiques', 3, 45, '3SI'
    UNION ALL SELECT 'ARCH-3SI', 'Architecture des Ordinateurs', 2, 30, '3SI'
    -- 2SC
    UNION ALL SELECT 'ANA-2SC', 'Analyse Mathématique', 4, 60, '2SC'
    UNION ALL SELECT 'ALGO-2SC', 'Structures de Données', 3, 45, '2SC'
    UNION ALL SELECT 'ELEC-2SC', 'Électronique Fondamentale', 2, 30, '2SC'
    -- TC
    UNION ALL SELECT 'MATH-TC', 'Mathématiques Générales', 4, 60, 'TC'
    UNION ALL SELECT 'INFO-TC', 'Initiation à l''Informatique', 3, 45, 'TC'
    UNION ALL SELECT 'PHYS-TC', 'Physique Mécanique', 3, 45, 'TC'
    -- M1
    UNION ALL SELECT 'GEN-M1', 'Génie Logiciel Avancé', 3, 45, 'M1'
    UNION ALL SELECT 'SEC-M1', 'Sécurité des Systèmes', 3, 45, 'M1'
    -- M2RSSI
    UNION ALL SELECT 'CRYP-RSSI', 'Cryptographie Appliquée', 4, 45, 'M2RSSI'
    UNION ALL SELECT 'AUDIT-RSSI', 'Audit et Sécurité Réseau', 3, 45, 'M2RSSI'
    -- M2WIC
    UNION ALL SELECT 'CLOUD-WIC', 'Cloud Computing & Microservices', 3, 45, 'M2WIC'
    UNION ALL SELECT 'WEB-WIC', 'Architectures Web Modernes', 3, 45, 'M2WIC'
    -- M2ISI
    UNION ALL SELECT 'BD-ISI', 'Big Data & NoSQL', 4, 45, 'M2ISI'
    UNION ALL SELECT 'DIST-ISI', 'Systèmes Distribués', 3, 45, 'M2ISI'
    -- 1ING
    UNION ALL SELECT 'RO-1ING', 'Recherche Opérationnelle', 3, 45, '1ING'
    UNION ALL SELECT 'COMP-1ING', 'Théorie des Langages & Compilation', 3, 45, '1ING'
    -- 2ING
    UNION ALL SELECT 'PROJ-2ING', 'Management de Projets IT', 4, 45, '2ING'
    UNION ALL SELECT 'EMB-2ING', 'Systèmes Embarqués & IoT', 3, 45, '2ING'
) AS mdemo
WHERE NOT EXISTS (
    SELECT 1 FROM module m WHERE m.code_module = mdemo.code_module
);

-- 4. Insert grades in note table
INSERT IGNORE INTO note (etudiant_id, module_id, note)
SELECT p.id, m.id, ndemo.note
FROM (
    -- 3ISIL : Sarah (10) et Amina (11)
    SELECT '10' AS numero, 'BDD' AS code_module, 14.50 AS note
    UNION ALL SELECT '10', 'POO', 16.00
    UNION ALL SELECT '10', 'DEV', 15.00
    UNION ALL SELECT '10', 'IHM', 17.50
    UNION ALL SELECT '11', 'BDD', 11.00
    UNION ALL SELECT '11', 'POO', 13.00
    UNION ALL SELECT '11', 'DEV', 12.50
    UNION ALL SELECT '11', 'IHM', 14.00

    -- 3SI : Clark (4), Diana (5), Yasmine (12), Mehdi (13)
    UNION ALL SELECT '4', 'ALG-3SI', 18.00
    UNION ALL SELECT '4', 'RES-3SI', 17.50
    UNION ALL SELECT '4', 'ARCH-3SI', 16.00
    UNION ALL SELECT '5', 'ALG-3SI', 16.50
    UNION ALL SELECT '5', 'RES-3SI', 19.00
    UNION ALL SELECT '5', 'ARCH-3SI', 17.00
    UNION ALL SELECT '12', 'ALG-3SI', 14.00
    UNION ALL SELECT '12', 'RES-3SI', 13.50
    UNION ALL SELECT '12', 'ARCH-3SI', 15.00
    UNION ALL SELECT '13', 'ALG-3SI', 9.50
    UNION ALL SELECT '13', 'RES-3SI', 10.50
    UNION ALL SELECT '13', 'ARCH-3SI', 11.00

    -- 2SC : Tony (6), TChalla (7), Rania (14), Lyna (15)
    UNION ALL SELECT '6', 'ANA-2SC', 19.00
    UNION ALL SELECT '6', 'ALGO-2SC', 18.50
    UNION ALL SELECT '6', 'ELEC-2SC', 17.00
    UNION ALL SELECT '7', 'ANA-2SC', 15.00
    UNION ALL SELECT '7', 'ALGO-2SC', 16.00
    UNION ALL SELECT '7', 'ELEC-2SC', 14.50
    UNION ALL SELECT '14', 'ANA-2SC', 12.00
    UNION ALL SELECT '14', 'ALGO-2SC', 13.50
    UNION ALL SELECT '14', 'ELEC-2SC', 11.00
    UNION ALL SELECT '15', 'ANA-2SC', 8.50
    UNION ALL SELECT '15', 'ALGO-2SC', 10.00
    UNION ALL SELECT '15', 'ELEC-2SC', 9.50

    -- TC : Marinette (8), Natasha (9), Bilal (16), Sofiane (17)
    UNION ALL SELECT '8', 'MATH-TC', 15.00
    UNION ALL SELECT '8', 'INFO-TC', 16.50
    UNION ALL SELECT '8', 'PHYS-TC', 14.00
    UNION ALL SELECT '9', 'MATH-TC', 17.00
    UNION ALL SELECT '9', 'INFO-TC', 18.00
    UNION ALL SELECT '9', 'PHYS-TC', 16.50
    UNION ALL SELECT '16', 'MATH-TC', 11.00
    UNION ALL SELECT '16', 'INFO-TC', 13.00
    UNION ALL SELECT '16', 'PHYS-TC', 10.50
    UNION ALL SELECT '17', 'MATH-TC', 7.50
    UNION ALL SELECT '17', 'INFO-TC', 9.00
    UNION ALL SELECT '17', 'PHYS-TC', 8.00

    -- M1 : Karim (18), Nour (19), Anis (20)
    UNION ALL SELECT '18', 'SI', 14.00
    UNION ALL SELECT '18', 'GEN-M1', 15.50
    UNION ALL SELECT '18', 'SEC-M1', 13.00
    UNION ALL SELECT '19', 'SI', 16.50
    UNION ALL SELECT '19', 'GEN-M1', 17.00
    UNION ALL SELECT '19', 'SEC-M1', 18.00
    UNION ALL SELECT '20', 'SI', 10.50
    UNION ALL SELECT '20', 'GEN-M1', 11.00
    UNION ALL SELECT '20', 'SEC-M1', 12.00

    -- M2RSSI : Walid (21), Fatima Zahra (22)
    UNION ALL SELECT '21', 'CRYP-RSSI', 15.00
    UNION ALL SELECT '21', 'AUDIT-RSSI', 14.00
    UNION ALL SELECT '22', 'CRYP-RSSI', 17.50
    UNION ALL SELECT '22', 'AUDIT-RSSI', 18.00

    -- M2WIC : Meriem (23), Rayan (24)
    UNION ALL SELECT '23', 'CLOUD-WIC', 16.00
    UNION ALL SELECT '23', 'WEB-WIC', 15.50
    UNION ALL SELECT '24', 'CLOUD-WIC', 12.50
    UNION ALL SELECT '24', 'WEB-WIC', 13.00

    -- M2ISI : Oussama (25), Manel (26)
    UNION ALL SELECT '25', 'BD-ISI', 14.00
    UNION ALL SELECT '25', 'DIST-ISI', 13.50
    UNION ALL SELECT '26', 'BD-ISI', 16.50
    UNION ALL SELECT '26', 'DIST-ISI', 17.00

    -- 1ING : Adel (27), Chaima (28)
    UNION ALL SELECT '27', 'RO-1ING', 13.00
    UNION ALL SELECT '27', 'COMP-1ING', 12.00
    UNION ALL SELECT '28', 'RO-1ING', 15.50
    UNION ALL SELECT '28', 'COMP-1ING', 16.00

    -- 2ING : Ilyes (29), Souhila (30)
    UNION ALL SELECT '29', 'PROJ-2ING', 16.00
    UNION ALL SELECT '29', 'EMB-2ING', 15.00
    UNION ALL SELECT '30', 'PROJ-2ING', 17.50
    UNION ALL SELECT '30', 'EMB-2ING', 18.00
) AS ndemo
JOIN personne p ON p.numero = ndemo.numero
JOIN module m ON m.code_module = ndemo.code_module;

-- ============================================================
-- Table des utilisateurs (Authentification & Rôles)
-- ============================================================
CREATE TABLE IF NOT EXISTS `user` (
  `id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `mdp` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'User'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `user` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'User';

-- Comptes de test par défaut
INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'admin@example.com', '$2y$10$BWZdfL9i87HntjU/NFXZ6.l0csgzfq7MjhpJGrrbSiiKxKAIvevX2', 'Admin'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'admin@example.com');

INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'user@example.com', '$2y$10$CG1YQ/vunfQFLtgZ.1J86eOrfJ85J0jwIVGDKa5e6iBy8s2nH4pdi', 'User'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'user@example.com');

INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'analyse@example.com', '$2y$10$Grdkd6VygqenRtm3APoXf.LLImRm8u4TwBFqbEjEL6bOsJLM0o3Na', 'Analyse'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'analyse@example.com');

INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'enseignant@example.com', '$2y$10$dMxmlUHvUOd9yPcYDHlz8OfpTaZR2mk5VTfGsn.trJFYyk3RJ7Lm.', 'Enseignant'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'enseignant@example.com');

INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'controller@example.com', '$2y$10$ORjTrFCPXwmWaEk/h5DQ4.bB/r4pOyXJ5kscHI.dBpra2R4hhJi7m', 'Controller'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'controller@example.com');

-- ============================================================
-- Table des messages (Admin -> Enseignants / Analyse & Contrôle)
-- ============================================================
CREATE TABLE IF NOT EXISTS `message` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expediteur_id` INT NOT NULL,
  `destinataire_id` INT NOT NULL,
  `destinataire_role` VARCHAR(50) NOT NULL DEFAULT '',
  `contenu` TEXT NOT NULL,
  `date_envoi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_expediteur FOREIGN KEY (`expediteur_id`) REFERENCES `user`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_message_destinataire FOREIGN KEY (`destinataire_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Messages de démonstration
INSERT INTO `message` (`expediteur_id`, `destinataire_id`, `destinataire_role`, `contenu`, `date_envoi`)
SELECT uAdmin.id, uDest.id, uDest.role, 'Bonjour, merci de préparer le rapport statistique des admis pour le semestre 1.', '2026-11-19 08:30:00'
FROM `user` uAdmin, `user` uDest
WHERE uAdmin.email = 'admin@example.com' AND uDest.email = 'analyse@example.com'
  AND NOT EXISTS (SELECT 1 FROM `message` WHERE `contenu` LIKE 'Bonjour, merci de préparer%');

INSERT INTO `message` (`expediteur_id`, `destinataire_id`, `destinataire_role`, `contenu`, `date_envoi`)
SELECT uAdmin.id, uDest.id, uDest.role, 'Rappel : n\'oubliez pas d\'intégrer les graphiques par filière.', '2026-11-19 08:45:00'
FROM `user` uAdmin, `user` uDest
WHERE uAdmin.email = 'admin@example.com' AND uDest.email = 'analyse@example.com'
  AND NOT EXISTS (SELECT 1 FROM `message` WHERE `contenu` LIKE 'Rappel : n\'oubliez pas%');

INSERT INTO `message` (`expediteur_id`, `destinataire_id`, `destinataire_role`, `contenu`, `date_envoi`)
SELECT uAdmin.id, uDest.id, uDest.role, 'Bonjour cher enseignant, veuillez vérifier les notes saisies pour le module BDD.', '2026-11-19 08:35:00'
FROM `user` uAdmin, `user` uDest
WHERE uAdmin.email = 'admin@example.com' AND uDest.email = 'enseignant@example.com'
  AND NOT EXISTS (SELECT 1 FROM `message` WHERE `contenu` LIKE 'Bonjour cher enseignant%');


