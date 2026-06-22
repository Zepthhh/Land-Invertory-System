CREATE DATABASE IF NOT EXISTS `Land Inventory`;
USE `Land Inventory`;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Admin', 'Editor', 'Viewer') NOT NULL DEFAULT 'Viewer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `username` VARCHAR(50) NULL,
    `action` VARCHAR(255) NOT NULL,
    `details` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`username`, `password`, `role`) VALUES
('admin', '$2y$10$R4tSxdT9nI7dKBMOgtqQKeTix0GqnTIiugJPMUAX8OanncAUxd.IC', 'Admin'),
('editor', '$2y$10$3T97RjOIQF4t7yOuiBEGZ.VehJC1NlmGzHX6Z4PLd0DxtLopogCXS', 'Editor'),
('viewer', '$2y$10$lHtw4WjaP6xl6f4EUXKmkuH/GnCm9Fvu0qWKgbqVkJRccs0PQEN7.', 'Viewer');

DROP TABLE IF EXISTS `land_use`;
DROP TABLE IF EXISTS `lots`;
DROP TABLE IF EXISTS `barangay`;

CREATE TABLE `municipality` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL
);

CREATE TABLE `barangay` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `municipality_id` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(150) NOT NULL,
    `total_area_sqm` DOUBLE NOT NULL DEFAULT 0,
    CONSTRAINT `fk_barangay_municipality`
        FOREIGN KEY (`municipality_id`) REFERENCES `municipality` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `lots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lot_no` VARCHAR(100) NOT NULL,
    `survey_no` VARCHAR(100) NULL,
    `barangay_id` INT NOT NULL,
    `area_sqm` DOUBLE NOT NULL DEFAULT 0,
    `status` ENUM('Unapplied', 'Applied', 'Titled', 'Conflict') NOT NULL DEFAULT 'Unapplied',
    `survey_claimant` VARCHAR(255) NULL,
    `tax_declarant` VARCHAR(255) NULL,
    `current_claimant` VARCHAR(255) NULL,
    `claimant_sex` VARCHAR(20) NULL,
    `current_address` VARCHAR(255) NULL,
    `representative` VARCHAR(255) NULL,
    `representative_address` VARCHAR(255) NULL,
    `supporting_docs` VARCHAR(255) NULL,
    `subdivision` VARCHAR(50) NULL,
    `approved_survey_plan` VARCHAR(255) NULL,
    `land_case` VARCHAR(50) NULL,
    `titling_interest` VARCHAR(255) NULL,
    `mode_of_acquisition` VARCHAR(255) NULL,
    `dominant_use` VARCHAR(100) NULL,
    `remarks` TEXT NULL,
    `source_sheet` VARCHAR(100) NULL,
    `case_reference` VARCHAR(100) NULL,
    `sheet_row` INT NULL,
    CONSTRAINT `fk_lots_barangay`
        FOREIGN KEY (`barangay_id`) REFERENCES `barangay` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `land_use` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `barangay_id` INT NOT NULL,
    `type` ENUM('Road', 'Alley', 'Irrigation', 'Canal', 'Church', 'School', 'School Site', 'Plaza') NOT NULL,
    `area_sqm` DOUBLE NOT NULL DEFAULT 0,
    CONSTRAINT `fk_land_use_barangay`
        FOREIGN KEY (`barangay_id`) REFERENCES `barangay` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO `municipality` (`name`) VALUES
('Digos'), ('Bansalan'), ('Matanao'), ('Padada'), ('Hagonoy'), ('Magsaysay'), ('Sta. Cruz');

INSERT INTO `barangay` (`municipality_id`, `name`, `total_area_sqm`) VALUES
(1, 'San Isidro', 150000.00),
(1, 'San Roque', 125000.00),
(2, 'Santa Lucia', 98000.00),
(3, 'Mabini', 110500.00);

INSERT INTO `lots` (`lot_no`, `survey_no`, `barangay_id`, `area_sqm`, `status`) VALUES
('Lot-001', 'SRV-1001', 1, 12000.00, 'Unapplied'),
('Lot-002', 'SRV-1001', 1, 8500.00, 'Applied'),
('Lot-003', 'SRV-1002', 1, 6300.00, 'Titled'),
('Lot-004', 'SRV-2001', 2, 9600.00, 'Conflict'),
('Lot-005', 'SRV-2001', 2, 11200.00, 'Applied'),
('Lot-006', 'SRV-2002', 2, 7250.00, 'Unapplied'),
('Lot-007', 'SRV-3001', 3, 5300.00, 'Titled'),
('Lot-008', 'SRV-3002', 3, 4900.00, 'Applied'),
('Lot-009', 'SRV-4001', 4, 10100.00, 'Conflict'),
('Lot-010', 'SRV-4002', 4, 8800.00, 'Unapplied');

INSERT INTO `land_use` (`barangay_id`, `type`, `area_sqm`) VALUES
(1, 'Road', 5000.00),
(1, 'Church', 1200.00),
(1, 'School', 3500.00),
(2, 'Road', 4200.00),
(2, 'Canal', 1800.00),
(3, 'Alley', 950.00),
(3, 'Plaza', 2100.00),
(4, 'Irrigation', 2750.00),
(4, 'Road', 3100.00);
