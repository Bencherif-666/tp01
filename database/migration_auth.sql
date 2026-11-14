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

-- Comptes de test :
-- 1) Admin : admin@example.com / admin123
-- 2) User  : user@example.com  / user123
INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'admin@example.com', '$2y$10$BWZdfL9i87HntjU/NFXZ6.l0csgzfq7MjhpJGrrbSiiKxKAIvevX2', 'Admin'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'admin@example.com');

INSERT INTO `user` (`email`, `mdp`, `role`)
SELECT 'user@example.com', '$2y$10$CG1YQ/vunfQFLtgZ.1J86eOrfJ85J0jwIVGDKa5e6iBy8s2nH4pdi', 'User'
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `email` = 'user@example.com');
