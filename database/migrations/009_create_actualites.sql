CREATE TABLE actualites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    date_publication DATETIME NOT NULL,
    image VARCHAR(255) NULL,
    extrait TEXT NULL,
    contenu MEDIUMTEXT NULL,
    published TINYINT(1) NOT NULL DEFAULT 0,
    seo_title VARCHAR(255) NULL,
    seo_description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_date (published, date_publication)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
