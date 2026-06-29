-- 1. Nettoyage et Création de la base
SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS mirindra_funding;
CREATE DATABASE mirindra_funding CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mirindra_funding;

-- 2. Création de la table des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    user_type ENUM('teacher', 'donor', 'admin', 'student') DEFAULT 'donor',
    school_name VARCHAR(255),
    phone VARCHAR(20),
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Création de la table des projets
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('arts', 'science', 'sports', 'environment', 'culture', 'technology', 'other') NOT NULL,
    target_amount DECIMAL(10, 2) NOT NULL,
    current_amount DECIMAL(10, 2) DEFAULT 0.00,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    image_url VARCHAR(255),
    school_name VARCHAR(255),
    class_level VARCHAR(100),
    number_of_students INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Création de la table des dons
CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT,
    donor_name VARCHAR(255) NOT NULL,
    donor_email VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('mvola', 'orange_money', 'airtel_money') DEFAULT 'mvola',
    phone_number VARCHAR(20),
    message TEXT,
    is_anonymous BOOLEAN DEFAULT FALSE,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    receipt_token VARCHAR(255) UNIQUE, -- Jeton unique pour le reçu de paiement
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.5 Création de la table des commentaires
CREATE TABLE project_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES project_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.5.1 Création de la table des actualités (Updates)
CREATE TABLE IF NOT EXISTS project_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.6 Création de la table des notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.7 Création de la table des réinitialisations de mot de passe
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 4.8 Création de la table des messages (Messagerie Privée)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.9 Création de la table des connexions entre utilisateurs (Amis/Contacts)
CREATE TABLE IF NOT EXISTS user_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_connection (sender_id, receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.10 Création de la table des récompenses (Rewards)
CREATE TABLE IF NOT EXISTS project_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    min_amount DECIMAL(10, 2) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Création de la table des catégories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#3498db'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Insertion des données initiales
INSERT INTO categories (name, description, color) VALUES
('Arts & Culture', 'Projets artistiques, musicaux, théâtraux', '#e74c3c'),
('Sciences & Technologie', 'Projets scientifiques, robotique, numérique', '#3498db'),
('Sports & Santé', 'Activités sportives, bien-être', '#2ecc71'),
('Environnement', 'Écologie, développement durable', '#27ae60'),
('Voyages & Échanges', 'Sorties pédagogiques, voyages scolaires', '#f39c12'),
('Citoyenneté', 'Projets solidaires, vivre ensemble', '#9b59b6');

-- L'Administrateur LAZY (Mot de passe: mirindra123 haché)
DELETE FROM users WHERE email = 'mirindra@gmail.com';
INSERT INTO users (id, email, password, first_name, last_name, user_type, is_active) 
VALUES (1, 'mirindra@gmail.com', '$2y$10$8M3.Z6tM7G5E5B9.fC0oE.6W3B2Q4B/6O1A9.S1mY0W4V6.S8zS2m', 'LAZY', 'ADMIN', 'admin', 1);

-- Un Professeur de test (Mot de passe: password haché)
INSERT INTO users (id, email, password, first_name, last_name, user_type, school_name, is_active)
VALUES (2, 'prof@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Marie', 'Dupont', 'teacher', 'Lycée technique Mahajanga', 1);

-- Connexion de test entre l'admin et le prof pour la messagerie
INSERT INTO user_connections (sender_id, receiver_id, status) VALUES (1, 2, 'accepted');

-- Projets de test
INSERT INTO projects (user_id, title, description, category, target_amount, current_amount, start_date, end_date, status, school_name) VALUES
(2, 'Atelier Robotique 2024', 'Achat de kits Arduino pour initier les élèves au codage.', 'science', 1500000.00, 450000.00, '2024-01-01', '2024-12-31', 'active', 'Lycée technique Mahajanga'),
(2, 'Rénovation Bibliothèque', 'Achat de nouveaux livres et étagères pour le CDI.', 'culture', 3000000.00, 0.00, '2024-03-01', '2024-06-30', 'pending', 'Lycée technique Mahajanga');

-- Récompenses de test
INSERT INTO project_rewards (project_id, min_amount, title, description) VALUES
(1, 10000.00, 'Photo dédicacée', 'Une photo dédicacée de toute la classe envoyée par email.'),
(1, 50000.00, 'Nom sur le robot', 'Votre nom sera gravé sur l\'un des robots construits par les élèves.'),
(2, 20000.00, 'Marque-page personnalisé', 'Un marque-page fait main par les élèves de la classe.');

SET FOREIGN_KEY_CHECKS = 1;
