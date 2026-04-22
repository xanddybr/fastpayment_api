-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 22, 2026 at 04:24 AM
-- Server version: 8.0.42-0ubuntu0.24.04.1
-- PHP Version: 8.5.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u967889760_fastbeta`
--

-- --------------------------------------------------------

--
-- Table structure for table `anamnesis`
--

CREATE TABLE `anamnesis` (
  `id` int UNSIGNED NOT NULL,
  `subscribed_id` int UNSIGNED NOT NULL,
  `course_reason` text COLLATE utf8mb4_unicode_ci,
  `who_recomended` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_medium` int DEFAULT NULL,
  `religion_mention` text COLLATE utf8mb4_unicode_ci,
  `is_tule_member` tinyint(1) DEFAULT '0',
  `first_time` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `anamnesis`
--

INSERT INTO `anamnesis` (`id`, `subscribed_id`, `course_reason`, `who_recomended`, `is_medium`, `religion_mention`, `is_tule_member`, `first_time`, `created_at`) VALUES
(19, 30, 'Trabalhar aceitação', 'Minha esposa', 1, 'Umbandista', 1, 1, '2026-04-13 04:50:00'),
(25, 36, 'Tenho todos', 'Jesus', 1, 'Não quero falar muito', 1, 1, '2026-04-21 06:47:36'),
(26, 37, 'Todos para continuar', 'Minha mãe Maria!', 1, 'Universalista', 1, 1, '2026-04-21 06:57:36'),
(27, 38, 'Preciso seguir em frente', 'Meu Pai', 1, 'Protestante', 1, 1, '2026-04-21 07:21:40'),
(28, 40, 'Muitos', 'Pai', 1, 'Matrizes Africanas', 0, 0, '2026-04-22 00:36:14');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) DEFAULT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `name`, `description`, `price`, `slug`) VALUES
(12, 'Reiki Nivel 2', '', 2.00, 'reiki-nivel-2'),
(14, 'Reiki Nivel 3', NULL, 3.00, 'reiki-nivel-3'),
(15, 'Reiki Nivel 1', NULL, 1.00, 'reiki-nivel-1');

-- --------------------------------------------------------

--
-- Table structure for table `events_subscribed`
--

CREATE TABLE `events_subscribed` (
  `id` int UNSIGNED NOT NULL,
  `person_id` int UNSIGNED DEFAULT NULL,
  `schedule_id` int UNSIGNED NOT NULL,
  `payment_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events_subscribed`
--

INSERT INTO `events_subscribed` (`id`, `person_id`, `schedule_id`, `payment_id`, `status`, `created_at`) VALUES
(30, 8, 32, '152597214557', 'confirmed', '2026-04-13 04:50:00'),
(36, 41, 32, '155736337632', 'confirmed', '2026-04-21 06:45:14'),
(37, 41, 27, '155736969568', 'confirmed', '2026-04-21 06:56:26'),
(38, 41, 33, '154982752969', 'confirmed', '2026-04-21 07:19:40'),
(40, 41, 27, '155740487644', 'confirmed', '2026-04-21 07:59:47');

-- --------------------------------------------------------

--
-- Table structure for table `event_types`
--

CREATE TABLE `event_types` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_types`
--

INSERT INTO `event_types` (`id`, `name`, `description`, `slug`) VALUES
(1, 'Curso', '', 'curso'),
(10, 'Atendimento', '', 'atendimento'),
(11, 'Consulta', NULL, 'consulta');

-- --------------------------------------------------------

--
-- Table structure for table `persons`
--

CREATE TABLE `persons` (
  `id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `type_person_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `persons`
--

INSERT INTO `persons` (`id`, `full_name`, `email`, `password`, `status`, `type_person_id`, `created_at`) VALUES
(8, 'Xande', 'xanddybr@gmail.com', '$2y$12$FK7PUR5nPfWiKcAReyK2M.okjaPAOb9IU9kBvX4bUGb4Sa9drmbX.', 'active', 1, '2026-02-13 16:17:54'),
(41, 'Alexandre', 'test_user_4369246050821512868@testuser.com', NULL, 'active', 2, '2026-04-21 06:47:36');

-- --------------------------------------------------------

--
-- Table structure for table `person_details`
--

CREATE TABLE `person_details` (
  `id` int UNSIGNED NOT NULL,
  `person_id` int UNSIGNED DEFAULT NULL,
  `activity_professional` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number` int DEFAULT NULL,
  `neighborhood` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `person_details`
--

INSERT INTO `person_details` (`id`, `person_id`, `activity_professional`, `phone`, `street`, `number`, `neighborhood`, `city`) VALUES
(28, 8, 'Analista de sistemas', '21986609260', 'agua branca', 1738, 'Realengo', 'Rio de Janeiro'),
(38, 41, 'Cozinheiro', '21986609260', NULL, NULL, 'Bangu', 'Rio de Janeiro');

-- --------------------------------------------------------

--
-- Table structure for table `phinxlog`
--

CREATE TABLE `phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `phinxlog`
--

INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES
(20260123190539, 'CreateInitialSchema', '2026-01-29 16:09:40', '2026-01-29 16:09:40', 0);

-- --------------------------------------------------------

--
-- Table structure for table `registered_codes`
--

CREATE TABLE `registered_codes` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('pendente','validado','expirado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registered_codes`
--

INSERT INTO `registered_codes` (`id`, `email`, `code`, `expires_at`, `status`, `created_at`) VALUES
(24, 'xanddybr@gmail.com', '039587', '2026-03-26 04:52:28', 'validado', '2026-03-26 04:47:28'),
(25, 'alessouza@live.com', '065537', '2026-04-19 04:16:57', 'validado', '2026-04-19 04:11:57'),
(26, 'alessouza@live.com', '246870', '2026-04-19 06:22:26', 'validado', '2026-04-19 06:17:26'),
(27, 'xanddybr@gmail.com', '314869', '2026-04-19 08:07:14', 'validado', '2026-04-19 08:02:14'),
(28, 'xanddybr@gmail.com', '183400', '2026-04-19 08:35:31', 'validado', '2026-04-19 08:30:31'),
(29, 'alessouza@live.com', '248368', '2026-04-19 10:05:38', 'validado', '2026-04-19 10:00:38'),
(30, 'alessouza@live.com', '465166', '2026-04-20 16:40:56', 'validado', '2026-04-20 16:35:56'),
(31, 'alessouza@live.com', '196374', '2026-04-20 17:15:19', 'validado', '2026-04-20 17:10:19'),
(32, 'alessouza@live.com', '859124', '2026-04-20 17:27:57', 'validado', '2026-04-20 17:22:57'),
(33, 'alessouza@live.com', '715473', '2026-04-20 17:35:24', 'validado', '2026-04-20 17:30:24'),
(34, 'xanddybr@gmail.com', '428555', '2026-04-20 18:33:56', 'validado', '2026-04-20 18:28:56'),
(35, 'xanddybr@gmail.com', '277162', '2026-04-20 18:51:36', 'validado', '2026-04-20 18:46:36'),
(36, 'xanddybr@gmail.com', '980574', '2026-04-20 19:05:10', 'validado', '2026-04-20 19:00:10'),
(37, 'xanddybr@gmail.com', '946131', '2026-04-20 19:07:13', 'validado', '2026-04-20 19:02:13'),
(38, 'alessouza@live.com', '912633', '2026-04-20 22:03:20', 'validado', '2026-04-20 21:58:20'),
(39, 'alessouza@live.com', '908141', '2026-04-21 01:14:18', 'validado', '2026-04-21 01:09:18'),
(40, 'alessouza@live.com', '999030', '2026-04-21 02:23:41', 'validado', '2026-04-21 02:18:41'),
(41, 'alessouza@live.com', '091888', '2026-04-21 03:38:38', 'validado', '2026-04-21 03:33:38'),
(42, 'alessouza@live.com', '392089', '2026-04-21 03:40:55', 'validado', '2026-04-21 03:35:55'),
(43, 'alessouza@live.com', '749078', '2026-04-21 04:09:01', 'expirado', '2026-04-21 04:04:01'),
(44, 'alessouza@live.com', '414494', '2026-04-21 04:29:32', 'validado', '2026-04-21 04:24:32'),
(45, 'teste_user_2904943887590020914@testeuser.com', '914759', '2026-04-21 06:23:49', 'expirado', '2026-04-21 06:18:49'),
(46, 'alessouza@live.com', '973758', '2026-04-21 20:36:31', 'validado', '2026-04-21 20:31:31'),
(47, 'alessouza@live.com', '187304', '2026-04-21 20:42:38', 'validado', '2026-04-21 20:37:38'),
(48, 'alessouza@live.com', '825265', '2026-04-21 22:02:21', 'validado', '2026-04-21 21:57:21'),
(49, 'teste_user_2904943887590020914@testeuser.com', '663760', '2026-04-21 23:59:57', 'pendente', '2026-04-21 23:54:57');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int UNSIGNED NOT NULL,
  `event_id` int UNSIGNED DEFAULT NULL,
  `event_type_id` int UNSIGNED DEFAULT NULL,
  `unit_id` int UNSIGNED DEFAULT NULL,
  `vacancies` int DEFAULT '0',
  `scheduled_at` datetime DEFAULT NULL,
  `duration_minutes` int DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `event_id`, `event_type_id`, `unit_id`, `vacancies`, `scheduled_at`, `duration_minutes`, `status`, `created_at`) VALUES
(27, 15, 1, 17, 93, '2026-04-30 23:00:00', 60, 'available', '2026-03-18 02:21:54'),
(32, 12, 1, 17, 91, '2026-04-30 13:00:00', 60, 'available', '2026-04-13 01:57:08'),
(33, 14, 1, 17, 49, '2026-04-30 20:42:00', 360, 'available', '2026-04-21 07:18:34');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int UNSIGNED NOT NULL,
  `preference_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule_id` int UNSIGNED DEFAULT NULL,
  `person_id` int UNSIGNED DEFAULT NULL,
  `payment_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `preference_id`, `external_reference`, `schedule_id`, `person_id`, `payment_id`, `payer_email`, `payment_status`, `amount`, `created_at`, `updated_at`) VALUES
(51, '3281120951-9e70ae25-f3d2-4883-a644-4b71e944ca46', 'FP-1776054597-32', 32, 8, '152597214557', 'teste_user_2904943887590020914@testeuser.com', 'approved', 1.00, '2026-04-13 04:29:57', '2026-04-19 07:29:20'),
(81, '2457271780-3a98a86f-2b9e-4726-97a2-c8bb14717dee', 'FP-1776753897-32', 32, 41, '155736337632', 'test_user_4369246050821512868@testuser.com', 'approved', 2.00, '2026-04-21 06:44:57', '2026-04-21 06:47:36'),
(82, '2457271780-f1d5b230-1fa5-4acb-b14b-b1de69d4f5a3', 'FP-1776754572-27', 27, 41, '155736969568', 'test_user_4369246050821512868@testuser.com', 'approved', 1.00, '2026-04-21 06:56:12', '2026-04-21 06:57:36'),
(83, '2457271780-9a0d21fb-fb94-4c06-8508-d4c99762201f', 'FP-1776755948-33', 33, 41, '154982752969', 'test_user_4369246050821512868@testuser.com', 'approved', 3.00, '2026-04-21 07:19:08', '2026-04-21 07:21:40'),
(85, '2457271780-05adbc1c-5968-480d-b5b4-b05623e18e0a', 'FP-1776758375-27', 27, 41, '155740487644', 'test_user_4369246050821512868@testuser.com', 'approved', 1.00, '2026-04-21 07:59:35', '2026-04-22 00:32:41');

-- --------------------------------------------------------

--
-- Table structure for table `types_person`
--

CREATE TABLE `types_person` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `types_person`
--

INSERT INTO `types_person` (`id`, `name`, `created_at`) VALUES
(1, 'Admin', '2026-01-29 13:19:24'),
(2, 'Subscribe', '2026-01-29 13:19:24');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `name`, `slug`) VALUES
(17, 'CURICICA', 'curicica'),
(18, 'ON-LINE', 'on-line');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anamnesis`
--
ALTER TABLE `anamnesis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_anamnesis_subscription` (`subscribed_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `events_subscribed`
--
ALTER TABLE `events_subscribed`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subscribed_person` (`person_id`),
  ADD KEY `fk_subscribed_schedule` (`schedule_id`);

--
-- Indexes for table `event_types`
--
ALTER TABLE `event_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `persons`
--
ALTER TABLE `persons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `type_person_id` (`type_person_id`);

--
-- Indexes for table `person_details`
--
ALTER TABLE `person_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_person` (`person_id`),
  ADD KEY `person_id` (`person_id`);

--
-- Indexes for table `phinxlog`
--
ALTER TABLE `phinxlog`
  ADD PRIMARY KEY (`version`);

--
-- Indexes for table `registered_codes`
--
ALTER TABLE `registered_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_schedules_event` (`event_id`),
  ADD KEY `fk_schedules_unit` (`unit_id`),
  ADD KEY `fk_schedules_type` (`event_type_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `external_reference` (`external_reference`),
  ADD KEY `preference_id` (`preference_id`),
  ADD KEY `fk_transactions_person` (`person_id`);

--
-- Indexes for table `types_person`
--
ALTER TABLE `types_person`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anamnesis`
--
ALTER TABLE `anamnesis`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `events_subscribed`
--
ALTER TABLE `events_subscribed`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `event_types`
--
ALTER TABLE `event_types`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `persons`
--
ALTER TABLE `persons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `person_details`
--
ALTER TABLE `person_details`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `registered_codes`
--
ALTER TABLE `registered_codes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `types_person`
--
ALTER TABLE `types_person`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `anamnesis`
--
ALTER TABLE `anamnesis`
  ADD CONSTRAINT `fk_anamnesis_subscription` FOREIGN KEY (`subscribed_id`) REFERENCES `events_subscribed` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events_subscribed`
--
ALTER TABLE `events_subscribed`
  ADD CONSTRAINT `fk_subscribed_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subscribed_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `persons`
--
ALTER TABLE `persons`
  ADD CONSTRAINT `persons_ibfk_1` FOREIGN KEY (`type_person_id`) REFERENCES `types_person` (`id`);

--
-- Constraints for table `person_details`
--
ALTER TABLE `person_details`
  ADD CONSTRAINT `person_details_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedules_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_schedules_type` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_schedules_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
