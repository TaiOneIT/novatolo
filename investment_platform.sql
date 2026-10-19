-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2026 at 04:55 PM
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
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `daily_earning` decimal(15,2) NOT NULL,
  `total_profit` decimal(15,2) NOT NULL,
  `earned_so_far` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','active','completed','rejected') DEFAULT 'pending',
  `days_counted` int(11) DEFAULT 0,
  `duration_days` int(11) NOT NULL,
  `last_payout_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `return_credited_to_user` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `investments`
--

INSERT INTO `investments` (`id`, `user_id`, `plan_id`, `amount`, `daily_earning`, `total_profit`, `earned_so_far`, `status`, `days_counted`, `duration_days`, `last_payout_date`, `created_at`, `is_archived`, `return_credited_to_user`) VALUES
(18, 16, 2, 3000.00, 780.00, 900.00, 0.00, 'completed', 0, 5, '2026-09-16', '2026-09-16 19:08:04', 1, 0),
(19, 16, 6, 10000.00, 1066.67, 6000.00, 0.00, 'completed', 0, 15, '2026-09-16', '2026-09-16 19:11:50', 1, 0),
(21, 20, 1, 5000.00, 500.00, 1500.00, 0.00, 'completed', 0, 3, NULL, '2026-09-22 18:17:58', 0, 0),
(22, 21, 2, 10000.00, 571.43, 4000.00, 0.00, 'active', 0, 7, NULL, '2026-10-09 09:51:12', 1, 0),
(23, 21, 1, 5000.00, 500.00, 1500.00, 0.00, 'active', 0, 3, NULL, '2026-10-09 10:20:39', 1, 0),
(24, 21, 3, 15000.00, 750.00, 7500.00, 0.00, 'active', 0, 10, NULL, '2026-10-09 11:16:29', 0, 0),
(25, 21, 4, 20000.00, 1200.00, 12000.00, 0.00, 'active', 0, 10, NULL, '2026-10-09 11:44:02', 0, 0);

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
(1, 'Starter Plan', 30.00, 3, 5000.00, 5000.00),
(2, 'Bronze Plan', 40.00, 7, 10000.00, 10000.00),
(3, 'Silver Plan', 50.00, 10, 15000.00, 15000.00),
(4, 'Gold Plan', 60.00, 10, 20000.00, 20000.00),
(5, 'Platinum Plan', 70.00, 15, 50000.00, 50000.00),
(6, 'Starter', 60.00, 10, 100000.00, 100000.00),
(7, 'Bronze', 80.00, 15, 200000.00, 200000.00),
(8, 'Silver', 80.00, 20, 500000.00, 500000.00),
(9, 'Gold', 100.00, 30, 1000000.00, 1000000.00),
(20, 'Plan 2', 40.00, 7, 10000.00, 10000.00),
(21, 'Plan 3', 50.00, 10, 15000.00, 15000.00),
(22, 'Plan 4', 60.00, 10, 20000.00, 20000.00),
(23, 'Plan 5', 70.00, 15, 50000.00, 50000.00),
(24, 'Plan 6', 60.00, 10, 100000.00, 100000.00),
(25, 'Plan 7', 80.00, 15, 200000.00, 200000.00),
(26, 'Plan 8', 80.00, 20, 500000.00, 500000.00),
(27, 'Plan 9', 100.00, 30, 1000000.00, 1000000.00);

-- --------------------------------------------------------

--
-- Table structure for table `referral_reward_awards`
--

CREATE TABLE `referral_reward_awards` (
  `id` int(11) NOT NULL,
  `inviter_id` int(11) NOT NULL,
  `invited_user_id` int(11) NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 800.00,
  `qualifying_transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referral_reward_awards`
--

INSERT INTO `referral_reward_awards` (`id`, `inviter_id`, `invited_user_id`, `amount`, `qualifying_transaction_id`, `created_at`) VALUES
(1, 6, 9, 800.00, 5, '2026-09-16 18:19:25'),
(2, 6, 15, 800.00, 9, '2026-09-16 19:04:52'),
(3, 15, 16, 800.00, 14, '2026-09-16 19:12:13'),
(4, 6, 17, 800.00, 17, '2026-09-16 20:26:20'),
(5, 6, 18, 800.00, 19, '2026-09-16 20:47:00'),
(6, 6, 20, 800.00, 25, '2026-09-22 18:17:10'),
(7, 21, 22, 800.00, 29, '2026-10-09 10:18:54');

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sender_type` enum('user','admin') NOT NULL DEFAULT 'user',
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` enum('image','audio','none') NOT NULL DEFAULT 'none',
  `is_deleted` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_messages`
--

INSERT INTO `support_messages` (`id`, `user_id`, `sender_type`, `message`, `is_read`, `created_at`, `file_path`, `file_type`, `is_deleted`, `updated_at`) VALUES
(10, 21, 'user', 'hey🧑‍🎓', 1, '2026-10-10 15:13:06', NULL, 'none', 0, NULL),
(11, 23, 'admin', 'hy', 0, '2026-10-10 16:00:56', NULL, 'none', 0, NULL),
(12, 6, 'admin', 'see', 0, '2026-10-10 16:01:29', 'uploads/chat_6aca615988a198.05604930.png', 'image', 0, NULL),
(13, 15, 'admin', 'see', 0, '2026-10-10 16:01:29', 'uploads/chat_6aca615988a198.05604930.png', 'image', 0, NULL),
(14, 16, 'admin', 'see', 0, '2026-10-10 16:01:29', 'uploads/chat_6aca615988a198.05604930.png', 'image', 0, NULL),
(15, 17, 'admin', 'see', 0, '2026-10-10 16:01:29', 'uploads/chat_6aca615988a198.05604930.png', 'image', 0, NULL),
(16, 20, 'admin', 'see', 0, '2026-10-10 16:01:29', 'uploads/chat_6aca615988a198.05604930.png', 'image', 0, NULL),
(17, 21, 'admin', 'see', 1, '2026-10-10 16:01:29', 'uploads/chat_6aca615988a198.05604930.png', 'image', 0, '2026-10-10 16:49:39'),
(18, 22, 'admin', 'see', 0, '2026-10-10 16:01:29', 'uploads/chat_6aca615988a198.05604930.png', 'image', 0, NULL),
(19, 23, 'admin', 'see', 0, '2026-10-10 16:01:29', 'uploads/chat_6aca615988a198.05604930.png', 'image', 0, NULL),
(20, 24, 'admin', 'see', 0, '2026-10-10 16:01:29', 'uploads/chat_6aca615988a198.05604930.png', 'image', 0, NULL),
(21, 24, 'admin', 'hello our friend ..... kora investment', 0, '2026-10-10 16:07:23', NULL, 'none', 0, NULL),
(22, 24, 'admin', 'ugure umugabane muri novatolo', 0, '2026-10-10 16:07:38', NULL, 'none', 0, NULL),
(23, 24, 'admin', 'wanyandikira kuri iyi number 0785009044', 0, '2026-10-10 16:07:58', NULL, 'none', 0, NULL),
(24, 24, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 0, '2026-10-10 16:08:37', NULL, 'none', 0, NULL),
(25, 6, 'admin', 'wanyandikira kuri iyi number 0785009044', 0, '2026-10-10 16:09:11', NULL, 'none', 0, NULL),
(26, 15, 'admin', 'wanyandikira kuri iyi number 0785009044', 0, '2026-10-10 16:09:11', NULL, 'none', 0, NULL),
(27, 16, 'admin', 'wanyandikira kuri iyi number 0785009044', 0, '2026-10-10 16:09:11', NULL, 'none', 0, NULL),
(28, 17, 'admin', 'wanyandikira kuri iyi number 0785009044', 0, '2026-10-10 16:09:11', NULL, 'none', 0, NULL),
(29, 20, 'admin', 'wanyandikira kuri iyi number 0785009044', 0, '2026-10-10 16:09:11', NULL, 'none', 0, NULL),
(30, 21, 'admin', 'wanyandikira kuri iyi number 0785009044', 1, '2026-10-10 16:09:11', NULL, 'none', 0, '2026-10-10 16:49:39'),
(31, 22, 'admin', 'wanyandikira kuri iyi number 0785009044', 0, '2026-10-10 16:09:11', NULL, 'none', 0, NULL),
(32, 23, 'admin', 'wanyandikira kuri iyi number 0785009044', 0, '2026-10-10 16:09:11', NULL, 'none', 0, NULL),
(33, 24, 'admin', 'wanyandikira kuri iyi number 0785009044', 0, '2026-10-10 16:09:11', NULL, 'none', 0, NULL),
(34, 6, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 0, '2026-10-10 16:09:21', NULL, 'none', 0, NULL),
(35, 15, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 0, '2026-10-10 16:09:21', NULL, 'none', 0, NULL),
(36, 16, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 0, '2026-10-10 16:09:21', NULL, 'none', 0, NULL),
(37, 17, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 0, '2026-10-10 16:09:21', NULL, 'none', 0, NULL),
(38, 20, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 0, '2026-10-10 16:09:21', NULL, 'none', 0, NULL),
(39, 21, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 1, '2026-10-10 16:09:21', NULL, 'none', 0, '2026-10-10 16:49:39'),
(40, 22, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 0, '2026-10-10 16:09:21', NULL, 'none', 0, NULL),
(41, 23, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 0, '2026-10-10 16:09:21', NULL, 'none', 0, NULL),
(42, 24, 'admin', 'cg urebe kurri home aho uboonera kubitsa cg kubikuza urabona whatsapp support link ukandeho utwandikire kuri whatsap', 0, '2026-10-10 16:09:21', NULL, 'none', 0, NULL),
(43, 21, 'admin', '', 1, '2026-10-10 16:43:06', 'uploads/chat_audio_6aca6b1a872c80.54982892.webm', 'audio', 0, '2026-10-10 16:49:39'),
(44, 21, 'admin', 'see', 1, '2026-10-10 16:43:26', 'uploads/chat_6aca6b2e28c531.46601288.png', 'image', 0, '2026-10-10 16:49:39'),
(45, 21, 'user', '', 0, '2026-10-10 16:49:57', 'uploads/chat_audio_6aca6cb530d0e2.32136900.webm', 'audio', 0, NULL),
(46, 21, 'user', '', 0, '2026-10-10 16:50:11', 'uploads/chat_6aca6cc3f116f9.97532108.png', 'image', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `type` enum('deposit','withdraw','withdrawal') NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `recipient_name` varchar(150) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `amount`, `type`, `status`, `created_at`, `phone`, `recipient_name`, `is_archived`) VALUES
(21, 6, 2000.00, 'withdraw', 'approved', '2026-09-16 21:18:30', '0782008000', 'keza nelly', 0),
(22, 6, 500.00, 'withdraw', 'approved', '2026-09-16 23:40:49', '0782008000', 'keza nelly', 0),
(23, 15, 800.00, 'withdraw', 'rejected', '2026-09-18 07:54:36', '0784009033', 'keza  nellu', 1),
(24, 6, 700.00, 'withdraw', 'approved', '2026-09-22 18:14:26', '0782008077', 'keza  nellu', 1),
(25, 20, 5000.00, 'deposit', 'approved', '2026-09-22 18:16:38', NULL, NULL, 1),
(26, 21, 5000.00, 'deposit', 'approved', '2026-10-09 09:40:18', NULL, NULL, 1),
(27, 21, 5000.00, 'deposit', 'approved', '2026-10-09 09:51:36', NULL, NULL, 1),
(28, 21, 3000.00, 'withdraw', 'approved', '2026-10-09 09:53:28', '0782008055', 'keza  nellu', 0),
(29, 22, 5000.00, 'deposit', 'approved', '2026-10-09 09:58:37', NULL, NULL, 1),
(30, 21, 11200.00, 'deposit', 'approved', '2026-10-09 11:40:35', NULL, NULL, 0),
(31, 21, 2000.00, 'withdraw', 'rejected', '2026-10-09 11:44:14', '0782008055', 'keza  nellu', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ref_code` varchar(50) DEFAULT NULL,
  `profit_balance` decimal(12,2) DEFAULT 0.00,
  `referred_by` int(11) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `is_active_investor` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `phone`, `password`, `balance`, `is_admin`, `created_at`, `ref_code`, `profit_balance`, `referred_by`, `is_archived`, `is_active_investor`) VALUES
(4, 'NYANDWI Callixte', '0792841159', '$2y$10$zmhagMi7wepn1hkWfBGRpeLAIblxebMTd2jC0Tt73BGjpagd7wBKa', 0.00, 1, '2026-09-15 10:23:27', 'REF-F828B2', 0.00, NULL, 0, 0),
(6, 'niyonkuru', '0782008077', '$2y$10$luBB7C5ExuJuyg7rDL4/FuEqi4dOz/mDf4RYeNGHN1KkOe4Q8sAgm', 0.00, 0, '2026-09-15 18:03:02', 'REF-D26ACD', 2000.00, NULL, 0, 0),
(7, 'System Admin', '0782008066', '$2y$10$O6numZFtVhAbzadRtnhcHuOmg1wYrqZK1D1uKjDWER.cJlYWdxEQO', 0.00, 1, '2026-09-15 19:41:16', 'REF-A5D2CB03', 0.00, NULL, 0, 0),
(15, 'nm', '0782008000', '$2y$10$HuFQ2lYPrL.Scxh07jLj6uYXxf5PveHiYYUOQaoKzl0b/1vYaN0JC', 6000.00, 0, '2026-09-16 19:00:39', 'REF-1F75BA8F', 800.00, 6, 0, 0),
(16, 'goe', '0782008001', '$2y$10$qnNWoIC9KbVCjfJGjP1nBe2SEFyxrMP2Y.7mPP.n4JkMrBikLAqYO', 1000.00, 0, '2026-09-16 19:06:30', 'REF-A49E45AF', 0.00, 15, 0, 0),
(17, 'noo', '0782008011', '$2y$10$zdO1djhP4oyxFB.n.WV5OeMRFy6eL9InEM1dflNKznQ7bhN4.rY5W', 21000.00, 0, '2026-09-16 20:25:20', 'REF-043E4314', 0.00, 6, 0, 0),
(20, 'niyonzima', '0782009077', '$2y$10$WXf/xeodP8ymvhxyieTIG.9eyAjO6/HE7o7ksiqNjPN3tIc88wNWO', 1000.00, 0, '2026-09-22 18:16:17', 'REF-68F35A9C', 0.00, 6, 0, 0),
(21, 'Line', '0782008055', '$2y$10$fVaksAcfh/jRY/PXDOAEzuslh4PVJOULrR.fajUcwMumoSqThDeGy', 0.00, 0, '2026-10-09 07:58:08', 'REF-31531520', 2000.00, NULL, 0, 0),
(22, 'yeah', '0782008051', '$2y$10$CPxS0R5mxPubJEcPJL04BOrosEPFwIWyFrMklE.kHAwgwJfNG3Kp6', 5000.00, 0, '2026-10-09 09:57:29', 'REF-752094E3', 0.00, 21, 0, 0),
(23, 'eric', '0782008022', '$2y$10$dHjfJ5CMn9mtHNgL8eEg1uXG15u/pN0EB2A/OmYZ8lublzv7TOIF6', 0.00, 0, '2026-10-09 12:17:25', NULL, 0.00, NULL, 0, 0),
(24, 'american account', '0782008002', '$2y$10$uFrEPnZn9bqGxofJkxsyP.BiL5rE/.XpVqrw8z33PJ7HIu7AoQUB.', 0.00, 0, '2026-10-09 18:06:41', 'REF-6C6AA3D0', 0.00, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `welcome_bonus_awards`
--

CREATE TABLE `welcome_bonus_awards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 1000.00,
  `qualifying_transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `welcome_bonus_awards`
--

INSERT INTO `welcome_bonus_awards` (`id`, `user_id`, `amount`, `qualifying_transaction_id`, `created_at`) VALUES
(1, 6, 1000.00, 2, '2026-09-16 18:19:25'),
(2, 15, 1000.00, 9, '2026-09-16 19:03:18'),
(3, 16, 1000.00, 14, '2026-09-16 19:11:15'),
(4, 17, 1000.00, 17, '2026-09-16 20:26:02'),
(5, 18, 1000.00, 19, '2026-09-16 20:44:02'),
(6, 20, 1000.00, 25, '2026-09-22 18:17:51'),
(7, 21, 1000.00, 26, '2026-10-09 09:50:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `investments`
--
ALTER TABLE `investments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `investment_plans`
--
ALTER TABLE `investment_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_reward_awards`
--
ALTER TABLE `referral_reward_awards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invited_user_id` (`invited_user_id`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `welcome_bonus_awards`
--
ALTER TABLE `welcome_bonus_awards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `investments`
--
ALTER TABLE `investments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `investment_plans`
--
ALTER TABLE `investment_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `referral_reward_awards`
--
ALTER TABLE `referral_reward_awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `welcome_bonus_awards`
--
ALTER TABLE `welcome_bonus_awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `investments`
--
ALTER TABLE `investments`
  ADD CONSTRAINT `investments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `investments_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `investment_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
