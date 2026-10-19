-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2026 at 07:16 PM
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
-- Database: `investment_platform`
--

-- --------------------------------------------------------

--
-- Table structure for table `investment_plans`
--

CREATE TABLE `investment_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `profit_rate` decimal(5,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `investment_plans`
--

INSERT INTO `investment_plans` (`id`, `name`, `profit_rate`, `duration_days`, `min_amount`, `max_amount`) VALUES
(1, 'Tier 1: Micro Starter', 26.00, 3, 3500.00, 3500.00),
(2, 'Tier 2: Basic Growth', 30.00, 4, 5000.00, 5000.00),
(3, 'Tier 3: Standard Saver', 35.00, 5, 7500.00, 7500.00),
(4, 'Tier 4: Advanced Starter', 40.00, 6, 10000.00, 10000.00),
(5, 'Tier 5: Bronze I', 45.00, 7, 15000.00, 15000.00),
(6, 'Tier 6: Bronze II', 50.00, 8, 20000.00, 20000.00),
(7, 'Tier 7: Bronze III', 55.00, 10, 30000.00, 30000.00),
(8, 'Tier 8: Silver I', 60.00, 12, 45000.00, 45000.00),
(9, 'Tier 9: Silver II', 65.00, 14, 60000.00, 60000.00),
(10, 'Tier 10: Silver III', 70.00, 15, 80000.00, 80000.00),
(11, 'Tier 11: Gold I', 75.00, 20, 100000.00, 100000.00),
(12, 'Tier 12: Gold II', 85.00, 25, 130000.00, 130000.00),
(13, 'Tier 13: Gold III', 95.00, 30, 170000.00, 170000.00),
(14, 'Tier 14: Platinum I', 105.00, 30, 220000.00, 220000.00),
(15, 'Tier 15: Platinum II', 115.00, 35, 300000.00, 300000.00),
(16, 'Tier 16: Platinum III', 125.00, 45, 400000.00, 400000.00),
(17, 'Tier 17: Diamond I', 140.00, 45, 550000.00, 550000.00),
(18, 'Tier 18: Diamond II', 160.00, 60, 700000.00, 700000.00),
(19, 'Tier 19: Diamond III', 180.00, 75, 850000.00, 850000.00),
(20, 'Tier 20: Executive VIP', 200.00, 90, 1000000.00, 1000000.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `investment_plans`
--
ALTER TABLE `investment_plans`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `investment_plans`
--
ALTER TABLE `investment_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
