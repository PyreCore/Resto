-- ============================================
--  Restaurant - LeDélise
--  Base : restaurant / Tables : commandes, commandes_items,
--         commandes_archivees, commandes_archivees_items, plats, tables,
--         parametres
--  Connexion : new PDO('mysql:host=localhost;dbname=restaurant', 'root', '')
--  Une commande = une ligne dans « commandes » + une ligne par plat
--  dans « commandes_items » (structure normalisée).
--  La table « parametres » stocke les couleurs du site (thème modifiable
--  depuis admin/parametres.php).
-- ============================================

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS restaurant
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE restaurant;

-- Table des commandes (une ligne = UNE commande d'un client)
CREATE TABLE IF NOT EXISTS commandes (
    id            INT          NOT NULL AUTO_INCREMENT,
    type_commande VARCHAR(20)  NOT NULL DEFAULT 'sur_place',
    numero_table  INT          DEFAULT NULL,
    nom_client    VARCHAR(100) DEFAULT NULL,
    telephone     VARCHAR(20)  DEFAULT NULL,
    date          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut        VARCHAR(20)  NOT NULL DEFAULT 'en_cours',
    total         INT          NOT NULL,
    PRIMARY KEY (id),
    KEY idx_statut (statut)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des plats commandés (une ligne = un plat d'une commande)
CREATE TABLE IF NOT EXISTS commandes_items (
    id          INT          NOT NULL AUTO_INCREMENT,
    commande_id INT          NOT NULL,
    menu        VARCHAR(100) NOT NULL,
    prix        INT          NOT NULL,
    quantite    INT          NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_commande (commande_id),
    KEY idx_menu (menu)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des commandes validées et archivées
-- id = identifiant interne (auto-incrément) ; commande_origine = numéro de la commande d'origine
CREATE TABLE IF NOT EXISTS commandes_archivees (
    id               INT          NOT NULL AUTO_INCREMENT,
    commande_origine INT          DEFAULT NULL,
    type_commande    VARCHAR(20)  NOT NULL DEFAULT 'sur_place',
    numero_table     INT          DEFAULT NULL,
    nom_client       VARCHAR(100) DEFAULT NULL,
    telephone        VARCHAR(20)  DEFAULT NULL,
    date             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_validation  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total            INT          NOT NULL,
    PRIMARY KEY (id),
    KEY idx_origine (commande_origine),
    KEY idx_date_validation (date_validation)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des plats des commandes archivées
CREATE TABLE IF NOT EXISTS commandes_archivees_items (
    id         INT          NOT NULL AUTO_INCREMENT,
    archive_id INT          NOT NULL,
    menu       VARCHAR(100) NOT NULL,
    prix       INT          NOT NULL,
    quantite   INT          NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_archive (archive_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des plats du menu (gérée par l'administration)
CREATE TABLE IF NOT EXISTS plats (
    id        INT          NOT NULL AUTO_INCREMENT,
    categorie VARCHAR(50)  NOT NULL,
    nom       VARCHAR(100) NOT NULL,
    prix      INT          NOT NULL,
    PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des tables de la salle (gérée par l'administration)
CREATE TABLE IF NOT EXISTS tables (
    id     INT NOT NULL AUTO_INCREMENT,
    numero INT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY numero (numero)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des paramètres du site (couleurs du thème, modifiables depuis
-- admin/parametres.php). Une ligne = une clé de paramètre.
CREATE TABLE IF NOT EXISTS parametres (
    id     INT          NOT NULL AUTO_INCREMENT,
    cle    VARCHAR(50)  NOT NULL,
    valeur VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY cle (cle)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plats par défaut (modifiables depuis l'administration)
INSERT INTO plats (categorie, nom, prix) VALUES
    ('Entrées', 'Salade de choux', 500),
    ('Entrées', 'Salade de maïs', 500),
    ('Entrées', 'Salade d''avocats', 700),
    ('Plats de résistance', 'Riz poulet', 1500),
    ('Plats de résistance', 'Riz viande', 1500),
    ('Plats de résistance', 'Riz poisson', 2000),
    ('Desserts', 'Yaourt', 300),
    ('Desserts', 'Jus de fruits', 500),
    ('Desserts', 'Gâteau au four', 500);

-- Tables par défaut (n° 1 à 10, ajoutables/retirables depuis l'administration)
INSERT IGNORE INTO tables (numero) VALUES
    (1), (2), (3), (4), (5),
    (6), (7), (8), (9), (10);

-- Couleurs par défaut du thème « Brun chaleureux » (INSERT IGNORE : ne
-- réinitialise pas les couleurs déjà personnalisées)
INSERT IGNORE INTO parametres (cle, valeur) VALUES
    ('couleur_fond',         '#2B1D12'),
    ('couleur_principale',   '#A54A12'),
    ('couleur_accent',       '#C05621'),
    ('couleur_accent_fonce', '#7C3E12'),
    ('couleur_accent_clair', '#E0A56B');
