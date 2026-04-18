-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 18, 2026 at 04:42 AM
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
  `course_reason` text,
  `expectations` text,
  `who_recomend` text,
  `is_medium` int DEFAULT NULL,
  `religion` tinyint(1) DEFAULT '0',
  `religion_mention` text,
  `is_tule_member` tinyint(1) DEFAULT '0',
  `obs_motived` text,
  `first_time` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `anamnesis`
--

INSERT INTO `anamnesis` (`id`, `subscribed_id`, `course_reason`, `expectations`, `who_recomend`, `is_medium`, `religion`, `religion_mention`, `is_tule_member`, `obs_motived`, `first_time`, `created_at`) VALUES
(16, 22, 'Precisando mudar', 'tenho a expectativa de ser um bom curso', 'Meu irmao', 1, 1, 'Espirita', 1, 'Busca pela evolução', 1, '2026-04-05 06:45:57'),
(17, 28, 'Modificar comportamentos', 'tenho a expectativa de ser um bom curso', 'Minha namorada', 0, 1, 'Kinbandeiro', 0, 'Só isso!', 1, '2026-04-06 20:12:08'),
(18, 29, 'Precisando dar mais atenção a espiritualidade', 'tenho a expectativa de ser um bom curso', 'Meu amigo', 1, 1, 'Universalista', 1, 'Minha esposa', 1, '2026-04-13 04:36:20'),
(19, 30, 'TrabalharAceitação', 'tenho a expectativa de ser um bom curso', 'Minha esposa', 1, 1, 'Umbandista', 0, 'Meu amigo me indicou', 1, '2026-04-13 04:50:00');

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
(12, 'Reiki Nivel 2', '', 1.00, 'reiki-nivel-2'),
(14, 'Reiki Nivel 3', NULL, 25.00, 'reiki-nivel-3'),
(15, 'Reiki Nivel 1', NULL, 50.00, 'reiki-nivel-1');

-- --------------------------------------------------------

--
-- Table structure for table `events_subscribed`
--

CREATE TABLE `events_subscribed` (
  `id` int UNSIGNED NOT NULL,
  `person_id` int UNSIGNED NOT NULL,
  `schedule_id` int UNSIGNED NOT NULL,
  `payment_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `events_subscribed`
--

INSERT INTO `events_subscribed` (`id`, `person_id`, `schedule_id`, `payment_id`, `status`, `created_at`) VALUES
(22, 26, 27, '152597214529', 'confirmed', '2026-04-05 06:45:57'),
(28, 26, 27, '152597214531', 'confirmed', '2026-04-06 20:12:08'),
(29, 26, 32, '152597214538', 'confirmed', '2026-04-13 04:36:20'),
(30, 26, 27, '152597214557', 'confirmed', '2026-04-13 04:50:00');

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
-- Table structure for table `history_logs`
--

CREATE TABLE `history_logs` (
  `id` int UNSIGNED NOT NULL,
  `transaction_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint UNSIGNED NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(8, 'Alexandre', 'xanddybr@gmail.com', '$2y$12$FK7PUR5nPfWiKcAReyK2M.okjaPAOb9IU9kBvX4bUGb4Sa9drmbX.', 'active', 1, '2026-02-13 16:17:54'),
(26, 'Usuario 2', 't2026@gmail.com', '$2y$12$FK7PUR5nPfWiKcAReyK2M.okjaPAOb9IU9kBvX4bUGb4Sa9drmbX.', 'active', 2, '2026-04-05 06:45:57');

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
(20, 8, 'Analista', '21986609260', NULL, NULL, 'Jacarépagua', 'Rio de Janeiro'),
(23, 26, 'Refrigerador', '21889898559', 'rua petropolis', 123, 'Bangu', 'Rio de Janeiro'),
(28, 8, 'Analista de sistemas', '21986609260', NULL, NULL, 'Realengo', 'Rio de Janeiro'),
(29, 26, 'Tecnico de TI', '21986606260', NULL, NULL, 'Realengo', 'Rio de Janeiro'),
(30, 26, 'Padeiro', '21986609260', NULL, NULL, 'Bangu', 'RJ');

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
(24, 'xanddybr@gmail.com', '039587', '2026-03-26 04:52:28', 'validado', '2026-03-26 04:47:28');

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
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `event_id`, `event_type_id`, `unit_id`, `vacancies`, `scheduled_at`, `duration_minutes`, `status`, `created_at`) VALUES
(27, 15, 1, 17, 97, '2026-11-30 23:00:00', 60, 'available', '2026-03-18 02:21:54'),
(32, 12, 1, 17, 97, '2026-04-30 13:00:00', 60, 'available', '2026-04-13 01:57:08');

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
  `payment_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `preference_id`, `external_reference`, `schedule_id`, `person_id`, `payment_id`, `payer_email`, `payment_status`, `amount`, `created_at`, `updated_at`) VALUES
(47, '3281120951-e950878a-7f7e-4c57-9692-c636b14c99a7', 'FP-1775371446-27', 27, 26, '152597214529', 'teste_user_2904943887590020914@testeuser.com', 'approved', 50.00, '2026-04-05 06:44:06', '2026-04-12 08:37:37'),
(49, '3281120951-cd6227cd-4370-4426-8776-a8c1f9ac537e', 'FP-1775490543-27', 27, 26, '152597214531', 'teste_user_2904943887590020914@testeuser.com', 'approved', 50.00, '2026-04-06 15:49:03', '2026-04-12 08:12:22'),
(51, '3281120951-9e70ae25-f3d2-4883-a644-4b71e944ca46', 'FP-1776054597-32', 32, 26, '152597214557', 'teste_user_2904943887590020914@testeuser.com', 'approved', 1.00, '2026-04-13 04:29:57', '2026-04-13 04:54:05'),
(52, '3281120951-13732ff0-3e0b-42a3-a376-9a4ac4e82d13', 'FP-1776055606-27', 27, 26, '152597214538', 'teste_user_2904943887590020914@testeuser.com', 'approved', 50.00, '2026-04-13 04:46:46', '2026-04-13 04:54:10');

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
-- Indexes for table `history_logs`
--
ALTER TABLE `history_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
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
  ADD KEY `preference_id` (`preference_id`);

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
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `events_subscribed`
--
ALTER TABLE `events_subscribed`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `event_types`
--
ALTER TABLE `event_types`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `history_logs`
--
ALTER TABLE `history_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `persons`
--
ALTER TABLE `persons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `person_details`
--
ALTER TABLE `person_details`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `registered_codes`
--
ALTER TABLE `registered_codes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

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
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
