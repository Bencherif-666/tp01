-- Adresse de contact utilisée pour l'envoi du bulletin.
USE tp01_paw;

ALTER TABLE personne
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL;
