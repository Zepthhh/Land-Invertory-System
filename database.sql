CREATE DATABASE IF NOT EXISTS `Land Inventory`;
USE `Land Inventory`;

DROP TABLE IF EXISTS `land_use`;
DROP TABLE IF EXISTS `lots`;
DROP TABLE IF EXISTS `barangay`;

CREATE TABLE `barangay` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `total_area_sqm` DOUBLE NOT NULL DEFAULT 0
);

CREATE TABLE `lots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lot_no` VARCHAR(100) NOT NULL,
    `survey_no` VARCHAR(100) NOT NULL,
    `barangay_id` INT NOT NULL,
    `area_sqm` DOUBLE NOT NULL DEFAULT 0,
    `status` ENUM('Unapplied', 'Applied', 'Titled', 'Conflict') NOT NULL,
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

INSERT INTO `barangay` (`name`, `total_area_sqm`) VALUES
('San Isidro', 150000.00),
('San Roque', 125000.00),
('Santa Lucia', 98000.00),
('Mabini', 110500.00);

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
