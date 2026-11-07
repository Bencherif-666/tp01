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
