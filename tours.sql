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
-- Tabela 5: Histórico (tour_history)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tour_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pilot_id` INT NOT NULL,
  `tour_id` INT NOT NULL,
  `leg_id` INT NOT NULL,
  `callsign` VARCHAR(20) NULL,
  `aircraft` VARCHAR(10) NULL,
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
-- Tabela 7: Rastreio ao vivo (SQL)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS tour_live_sessions (
    pilot_id INT NOT NULL,
    tour_id INT NOT NULL,
    leg_id INT NOT NULL,
    start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    state VARCHAR(20) DEFAULT 'Pre-Flight',
    arrival_checks INT DEFAULT 0 COMMENT 'Contador para confirmar pouso',
    PRIMARY KEY (pilot_id),
    UNIQUE KEY unique_flight (pilot_id, tour_id, leg_id)
);

SET FOREIGN_KEY_CHECKS = 1;