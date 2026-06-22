-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 22, 2026 at 04:50 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `land inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `action`, `details`, `created_at`) VALUES
(1, 1, 'admin', 'Login', 'User admin logged in successfully.', '2026-05-26 12:21:29');

-- --------------------------------------------------------

--
-- Table structure for table `barangay`
--

CREATE TABLE `barangay` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `total_area_sqm` double NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangay`
--

INSERT INTO `barangay` (`id`, `name`, `total_area_sqm`) VALUES
(1, 'San Isidro', 150000),
(2, 'San Roque', 125000),
(3, 'Santa Lucia', 98000),
(4, 'Mabini', 110500);

-- --------------------------------------------------------

--
-- Table structure for table `land_use`
--

CREATE TABLE `land_use` (
  `id` int(11) NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `type` enum('Road','Alley','Irrigation','Canal','Church','School','School Site','Plaza') NOT NULL,
  `area_sqm` double NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `land_use`
--

INSERT INTO `land_use` (`id`, `barangay_id`, `type`, `area_sqm`) VALUES
(1, 1, 'Road', 5000),
(2, 1, 'Church', 1200),
(3, 1, 'School', 3500),
(4, 2, 'Road', 4200),
(5, 2, 'Canal', 1800),
(6, 3, 'Alley', 950),
(7, 3, 'Plaza', 2100),
(8, 4, 'Irrigation', 2750),
(9, 4, 'Road', 3100);

-- --------------------------------------------------------

--
-- Table structure for table `lots`
--

CREATE TABLE `lots` (
  `id` int(11) NOT NULL,
  `lot_no` varchar(100) NOT NULL,
  `survey_no` varchar(100) DEFAULT NULL,
  `barangay_id` int(11) NOT NULL,
  `area_sqm` double NOT NULL DEFAULT 0,
  `status` enum('Unapplied','Applied','Titled','Conflict') NOT NULL DEFAULT 'Unapplied',
  `survey_claimant` varchar(255) DEFAULT NULL,
  `tax_declarant` varchar(255) DEFAULT NULL,
  `current_claimant` varchar(255) DEFAULT NULL,
  `claimant_sex` varchar(20) DEFAULT NULL,
  `current_address` varchar(255) DEFAULT NULL,
  `representative` varchar(255) DEFAULT NULL,
  `representative_address` varchar(255) DEFAULT NULL,
  `supporting_docs` varchar(255) DEFAULT NULL,
  `subdivision` varchar(50) DEFAULT NULL,
  `approved_survey_plan` varchar(255) DEFAULT NULL,
  `land_case` varchar(50) DEFAULT NULL,
  `titling_interest` varchar(255) DEFAULT NULL,
  `mode_of_acquisition` varchar(255) DEFAULT NULL,
  `dominant_use` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `source_sheet` varchar(100) DEFAULT NULL,
  `case_reference` varchar(100) DEFAULT NULL,
  `sheet_row` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lots`
--

INSERT INTO `lots` (`id`, `lot_no`, `survey_no`, `barangay_id`, `area_sqm`, `status`, `survey_claimant`, `tax_declarant`, `current_claimant`, `claimant_sex`, `current_address`, `representative`, `representative_address`, `supporting_docs`, `subdivision`, `approved_survey_plan`, `land_case`, `titling_interest`, `mode_of_acquisition`, `dominant_use`, `remarks`, `source_sheet`, `case_reference`, `sheet_row`) VALUES
(1, 'Lot-001', 'SRV-1001', 1, 12000, 'Unapplied', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Lot-002', 'SRV-1001', 1, 8500, 'Applied', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Lot-003', 'SRV-1002', 1, 6300, 'Titled', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Lot-004', 'SRV-2001', 2, 9600, 'Conflict', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Lot-005', 'SRV-2001', 2, 11200, 'Applied', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'Lot-006', 'SRV-2002', 2, 7250, 'Unapplied', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Lot-007', 'SRV-3001', 3, 5300, 'Titled', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'Lot-008', 'SRV-3002', 3, 4900, 'Applied', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'Lot-009', 'SRV-4001', 4, 10100, 'Conflict', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'Lot-010', 'SRV-4002', 4, 8800, 'Unapplied', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Editor','Viewer') NOT NULL DEFAULT 'Viewer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '$2y$10$R4tSxdT9nI7dKBMOgtqQKeTix0GqnTIiugJPMUAX8OanncAUxd.IC', 'Admin'),
(2, 'editor', '$2y$10$3T97RjOIQF4t7yOuiBEGZ.VehJC1NlmGzHX6Z4PLd0DxtLopogCXS', 'Editor'),
(3, 'viewer', '$2y$10$lHtw4WjaP6xl6f4EUXKmkuH/GnCm9Fvu0qWKgbqVkJRccs0PQEN7.', 'Viewer');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `barangay`
--
ALTER TABLE `barangay`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `land_use`
--
ALTER TABLE `land_use`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_land_use_barangay` (`barangay_id`);

--
-- Indexes for table `lots`
--
ALTER TABLE `lots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lots_barangay` (`barangay_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `barangay`
--
ALTER TABLE `barangay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `land_use`
--
ALTER TABLE `land_use`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `lots`
--
ALTER TABLE `lots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `land_use`
--
ALTER TABLE `land_use`
  ADD CONSTRAINT `fk_land_use_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangay` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lots`
--
ALTER TABLE `lots`
  ADD CONSTRAINT `fk_lots_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangay` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
