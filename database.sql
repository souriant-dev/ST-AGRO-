-- ============================================================
-- ST-AGRO — Schéma de base de données
-- ============================================================
CREATE DATABASE IF NOT EXISTS stagro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stagro;

-- ------------------------------------------------------------
-- Utilisateurs (classe abstraite Utilisateur -> rôle)
-- ------------------------------------------------------------
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(80) NOT NULL,
    prenom VARCHAR(80) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telephone VARCHAR(30) DEFAULT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('administrateur','agriculteur','agronome') NOT NULL DEFAULT 'agriculteur',
    photo_profil VARCHAR(255) DEFAULT NULL,
    ville VARCHAR(120) DEFAULT NULL,
    statut ENUM('actif','suspendu') NOT NULL DEFAULT 'actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Exploitations agricoles (gérées par un agriculteur)
-- ------------------------------------------------------------
CREATE TABLE exploitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agriculteur_id INT NOT NULL,
    nom VARCHAR(150) NOT NULL,
    culture VARCHAR(120) NOT NULL,
    superficie DECIMAL(10,2) DEFAULT NULL COMMENT 'en hectares',
    ville VARCHAR(120) DEFAULT NULL,
    latitude DECIMAL(10,6) DEFAULT NULL,
    longitude DECIMAL(10,6) DEFAULT NULL,
    date_plantation DATE DEFAULT NULL,
    statut ENUM('en_cours','recoltee','en_alerte') NOT NULL DEFAULT 'en_cours',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agriculteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Affectation administrative des exploitations à un agronome
CREATE TABLE affectations_agronomes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agronome_id INT NOT NULL,
    exploitation_id INT NOT NULL UNIQUE,
    date_affectation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agronome_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Capteurs IoT liés à une exploitation
-- ------------------------------------------------------------
CREATE TABLE capteurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exploitation_id INT NOT NULL,
    code_capteur VARCHAR(50) NOT NULL UNIQUE,
    type_capteur ENUM('humidite_sol','temperature','luminosite','ph_sol','pluviometrie','azote_sol','phosphore_sol','potassium_sol') NOT NULL,
    statut ENUM('actif','inactif','en_panne') NOT NULL DEFAULT 'actif',
    date_installation DATE DEFAULT NULL,
    FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Relevés transmis par les capteurs (use case "Transmettre paramètre / Mesurer paramètres du terrain")
CREATE TABLE releves_capteurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    capteur_id INT NOT NULL,
    valeur DECIMAL(10,2) NOT NULL,
    unite VARCHAR(20) DEFAULT NULL,
    date_releve DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (capteur_id) REFERENCES capteurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Mesures historiques remontées depuis les capteurs
-- ------------------------------------------------------------
CREATE TABLE mesures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    capteur_id INT NOT NULL,
    exploitation_id INT NOT NULL,
    valeur DECIMAL(10,2) NOT NULL,
    unite VARCHAR(20) DEFAULT NULL,
    date_mesure DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mesures_capteur_date (capteur_id, date_mesure),
    KEY idx_mesures_exploitation_date (exploitation_id, date_mesure),
    FOREIGN KEY (capteur_id) REFERENCES capteurs(id) ON DELETE CASCADE,
    FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Analyses phytosanitaires (diagnostic via IA / agronome)
-- ------------------------------------------------------------
CREATE TABLE analyses_phytosanitaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exploitation_id INT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    diagnostic TEXT DEFAULT NULL,
    niveau_risque ENUM('faible','modere','eleve') DEFAULT 'faible',
    recommandation TEXT DEFAULT NULL,
    traite_par_agronome_id INT DEFAULT NULL,
    date_analyse DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE CASCADE,
    FOREIGN KEY (traite_par_agronome_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Prédictions IA (rendement, risques...)
-- ------------------------------------------------------------
CREATE TABLE predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exploitation_id INT NOT NULL,
    type_prediction VARCHAR(100) NOT NULL,
    resultat TEXT NOT NULL,
    fiabilite DECIMAL(5,2) DEFAULT NULL COMMENT 'pourcentage',
    date_prediction DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Alertes (générées automatiquement ou par un agronome)
-- ------------------------------------------------------------
CREATE TABLE alertes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exploitation_id INT NOT NULL,
    titre VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    niveau ENUM('info','warning','critique') NOT NULL DEFAULT 'info',
    envoyee TINYINT(1) NOT NULL DEFAULT 0,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Notifications (par utilisateur)
-- ------------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    titre VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    lue TINYINT(1) NOT NULL DEFAULT 0,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Demandes de conseil (Agriculteur -> Agronome) + Chat
-- ------------------------------------------------------------
CREATE TABLE demandes_conseil (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agriculteur_id INT NOT NULL,
    agronome_id INT DEFAULT NULL,
    exploitation_id INT DEFAULT NULL,
    sujet VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    statut ENUM('en_attente','en_cours','repondu') NOT NULL DEFAULT 'en_attente',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agriculteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (agronome_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY (exploitation_id) REFERENCES exploitations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE messages_conseil (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demande_id INT NOT NULL,
    expediteur_id INT NOT NULL,
    contenu TEXT NOT NULL,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (demande_id) REFERENCES demandes_conseil(id) ON DELETE CASCADE,
    FOREIGN KEY (expediteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Historique météo consulté (cache local des appels API)
-- ------------------------------------------------------------
CREATE TABLE meteo_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ville VARCHAR(120) NOT NULL,
    temperature DECIMAL(5,2) DEFAULT NULL,
    humidite DECIMAL(5,2) DEFAULT NULL,
    description VARCHAR(150) DEFAULT NULL,
    vent DECIMAL(5,2) DEFAULT NULL,
    date_maj DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Données de démonstration
-- ------------------------------------------------------------
-- Mot de passe pour tous les comptes de démo : "Password123"
INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, role, ville) VALUES
('Tongue', 'Chanel', 'admin@st-agro.cm', '699000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrateur', 'Yaoundé'),
('Mballa', 'Jean', 'agriculteur@st-agro.cm', '677111111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agriculteur', 'Bafoussam'),
('Nkeng', 'Sarah', 'agronome@st-agro.cm', '655222222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agronome', 'Yaoundé');

INSERT INTO exploitations (agriculteur_id, nom, culture, superficie, ville, date_plantation, statut) VALUES
(2, 'Champ de Nkolbisson', 'Maïs', 2.5, 'Yaoundé', '2026-03-01', 'en_cours'),
(2, 'Parcelle du Nord', 'Cacao', 4.0, 'Bafoussam', '2025-11-15', 'en_alerte');

INSERT INTO capteurs (exploitation_id, code_capteur, type_capteur, statut, date_installation) VALUES
(1, 'CAP-001', 'humidite_sol', 'actif', '2026-03-05'),
(1, 'CAP-002', 'temperature', 'actif', '2026-03-05'),
(1, 'CAP-004', 'ph_sol', 'actif', '2026-03-06'),
(1, 'CAP-005', 'azote_sol', 'actif', '2026-03-07'),
(1, 'CAP-006', 'phosphore_sol', 'actif', '2026-03-07'),
(1, 'CAP-007', 'potassium_sol', 'actif', '2026-03-07'),
(2, 'CAP-003', 'humidite_sol', 'actif', '2025-11-20');

INSERT INTO releves_capteurs (capteur_id, valeur, unite) VALUES
(1, 42.5, '%'), (2, 27.3, '°C'), (3, 55.0, '%');

INSERT INTO mesures (capteur_id, exploitation_id, valeur, unite, date_mesure) VALUES
(1, 1, 38.4, '%', '2026-03-10 08:00:00'),
(1, 1, 40.1, '%', '2026-03-10 10:00:00'),
(1, 1, 42.5, '%', '2026-03-10 12:00:00'),
(2, 1, 24.6, '°C', '2026-03-10 08:00:00'),
(2, 1, 26.2, '°C', '2026-03-10 10:00:00'),
(2, 1, 27.3, '°C', '2026-03-10 12:00:00'),
(3, 2, 50.2, '%', '2026-03-10 09:00:00'),
(3, 2, 55.0, '%', '2026-03-10 12:00:00');

INSERT INTO alertes (exploitation_id, titre, message, niveau) VALUES
(2, 'Humidité critique', 'Le taux d\'humidité du sol est descendu sous le seuil recommandé.', 'critique');
