SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- Tabela 1: Medalhas (tour_badges)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_badges` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `image_url` VARCHAR(255) NULL,
  `condition_type` VARCHAR(50) NULL COMMENT 'Ex: tour_complete',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela 2: Tours (tour_tours)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_tours` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` LONGTEXT NULL,
  `difficulty` ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Easy',
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `banner_url` VARCHAR(255) NULL,
  `rules_json` JSON NULL,
  `scenery_link` VARCHAR(255) NULL,
  `badge_id` INT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_tour_tours_badges`
    FOREIGN KEY (`badge_id`)
    REFERENCES `tour_badges` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela 3: Rotas (tour_legs)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_legs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tour_id` INT NOT NULL,
  `leg_order` INT NOT NULL,
  `dep_icao` CHAR(4) NOT NULL,
  `arr_icao` CHAR(4) NOT NULL,
  `route_string` TEXT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_tour_legs_tour` (`tour_id` ASC),
  CONSTRAINT `fk_tour_legs_tour`
    FOREIGN KEY (`tour_id`)
    REFERENCES `tour_tours` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela 4: Progresso (tour_progress)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_progress` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pilot_id` INT NOT NULL,
  `tour_id` INT NOT NULL,
  `current_leg_id` INT NOT NULL,
  `status` ENUM('Not Started', 'In Progress', 'Completed') DEFAULT 'Not Started',
  `completed_at` DATETIME NULL,
  `last_update` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_progress_pilot` (`pilot_id` ASC),
  INDEX `idx_progress_tour` (`tour_id` ASC),
  CONSTRAINT `fk_progress_tour`
    FOREIGN KEY (`tour_id`)
    REFERENCES `tour_tours` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_progress_leg`
    FOREIGN KEY (`current_leg_id`)
    REFERENCES `tour_legs` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela 5: Histórico (tour_history) - ATUALIZADA
-- Adicionadas colunas duration_minutes, dep_icao, arr_icao
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pilot_id` INT NOT NULL,
  `tour_id` INT NOT NULL,
  `leg_id` INT NOT NULL,
  `callsign` VARCHAR(20) NULL,
  `aircraft` VARCHAR(10) NULL,
  `dep_icao` CHAR(4) NOT NULL COMMENT 'Salvo para performance do logbook',
  `arr_icao` CHAR(4) NOT NULL COMMENT 'Salvo para performance do logbook',
  `duration_minutes` INT DEFAULT 0 COMMENT 'Essencial para o Ranking',
  `network` VARCHAR(20) DEFAULT 'IVAO',
  `landing_rate` INT NULL,
  `date_flown` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_history_pilot` (`pilot_id` ASC),
  CONSTRAINT `fk_history_tour`
    FOREIGN KEY (`tour_id`)
    REFERENCES `tour_tours` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_history_leg`
    FOREIGN KEY (`leg_id`)
    REFERENCES `tour_legs` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela 6: Medalhas do Piloto (tour_pilot_badges)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_pilot_badges` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pilot_id` INT NOT NULL,
  `badge_id` INT NOT NULL,
  `awarded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pilot_badges_pilot` (`pilot_id` ASC),
  CONSTRAINT `fk_pilot_badges_badge`
    FOREIGN KEY (`badge_id`)
    REFERENCES `tour_badges` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela 7: Rastreio ao vivo (tour_live_sessions)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_live_sessions` (
    `pilot_id` INT NOT NULL,
    `tour_id` INT NOT NULL,
    `leg_id` INT NOT NULL,
    `start_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_seen` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `state` VARCHAR(20) DEFAULT 'Pre-Flight',
    `arrival_checks` INT DEFAULT 0,
    PRIMARY KEY (`pilot_id`),
    UNIQUE KEY `unique_flight` (`pilot_id`, `tour_id`, `leg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela 8: Patentes / Rankings (tour_ranks) - NOVA
-- Essencial para o RankSystem.php e admin/manage_ranks.php
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_ranks` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `rank_title` VARCHAR(50) NOT NULL,
    `min_hours` INT NOT NULL,
    `stripes` INT DEFAULT 1,
    `has_star` TINYINT(1) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserção de dados padrão para Rankings
INSERT INTO `tour_ranks` (`rank_title`, `min_hours`, `stripes`, `has_star`) VALUES 
('Aluno Piloto', 0, 1, 0),
('Segundo Oficial', 10, 2, 0),
('Primeiro Oficial', 30, 2, 0),
('Comandante', 80, 3, 0),
('Comandante Sênior', 200, 4, 0),
('Comandante Master', 500, 4, 1),
('Lenda da Kafly', 1000, 4, 1);

-- -----------------------------------------------------
-- Tabela 9: Dados Persistentes do Piloto (tour_pilots) - NOVA
-- Armazena a última localização para o Dashboard
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_pilots` (
    `pilot_id` INT NOT NULL,
    `current_location` CHAR(4) DEFAULT 'SBGL',
    `total_hours` INT DEFAULT 0,
    `last_flight` DATETIME NULL,
    PRIMARY KEY (`pilot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;