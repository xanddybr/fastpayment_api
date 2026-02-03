-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 21/01/2026 às 20:25
-- Versão do servidor: 8.0.43
-- Versão do PHP: 8.3.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u967889760_fastpayment`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `events`
--

CREATE TABLE `events` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `events`
--

INSERT INTO `events` (`id`, `name`, `price`) VALUES
(1, 'Modulo 01', 0.50),
(2, 'Modulo 02', 0.70),
(3, 'Modulo 03', 0.10);

-- --------------------------------------------------------

--
-- Estrutura para tabela `event_types`
--

CREATE TABLE `event_types` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `event_types`
--

INSERT INTO `event_types` (`id`, `name`) VALUES
(1, 'Curso'),
(2, 'Consulta'),
(3, 'Congresso');

-- --------------------------------------------------------

--
-- Estrutura para tabela `history_logs`
--

CREATE TABLE `history_logs` (
  `id` int NOT NULL,
  `transaction_id` int DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `payments`
--

CREATE TABLE `payments` (
  `id` bigint NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) NOT NULL,
  `payer_email` varchar(100) NOT NULL,
  `external_reference` varchar(100) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `persons`
--

CREATE TABLE `persons` (
  `id` int NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `type_person_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `persons`
--

INSERT INTO `persons` (`id`, `full_name`, `email`, `password`, `status`, `type_person_id`, `created_at`) VALUES
(3, 'Administrador Local', 'xanddybr@gmail.com', '$2y$12$WF.wHRAlxhmKFjqRGbgzPOjdAwfxb3Ak8EmWjv5bYpkyTTWZH0tIW', 'active', 1, '2026-01-07 01:46:44');

-- --------------------------------------------------------

--
-- Estrutura para tabela `person_details`
--

CREATE TABLE `person_details` (
  `id` int NOT NULL,
  `person_id` int NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activity_professional` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `obs_motived` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_first_time` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `registered_codes`
--

CREATE TABLE `registered_codes` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `validation_method` enum('email','whatsapp') DEFAULT 'email',
  `code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) NOT NULL DEFAULT 'pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `registered_codes`
--

INSERT INTO `registered_codes` (`id`, `email`, `phone`, `validation_method`, `code`, `expires_at`, `created_at`, `status`) VALUES
(45, 'alessouza@live.com', '+5521986609260', 'email', '350920', '2026-01-21 19:15:50', '2026-01-21 19:10:50', 'substituido'),
(46, 'alessouza@live.com', '+5521986609260', 'email', '104633', '2026-01-21 19:16:40', '2026-01-21 19:11:40', 'validated'),
(47, 'alessouza@live.com', '+5521986609260', 'email', '810310', '2026-01-21 19:22:11', '2026-01-21 19:17:11', 'validated'),
(48, 'alessouza@live.com', '+5521986609260', 'email', '662206', '2026-01-21 19:24:12', '2026-01-21 19:19:12', 'validated'),
(49, 'alessouza@live.com', '+5521986609260', 'email', '938731', '2026-01-21 19:33:38', '2026-01-21 19:28:38', 'validated'),
(50, 'alessouza@live.com', '+5521986609260', 'email', '693540', '2026-01-21 19:38:18', '2026-01-21 19:33:18', 'validated'),
(51, 'alessouza@live.com', '+5521986609260', 'email', '918125', '2026-01-21 19:46:00', '2026-01-21 19:41:00', 'validated'),
(52, 'alessouza@live.com', '+5521986609260', 'email', '832471', '2026-01-21 20:08:08', '2026-01-21 20:03:08', 'validated'),
(53, 'alessouza@live.com', '+5521986609260', 'email', '596118', '2026-01-21 20:13:44', '2026-01-21 20:08:44', 'validated'),
(54, 'alessouza@live.com', '+5521986609260', 'email', '967710', '2026-01-21 20:16:17', '2026-01-21 20:11:17', 'validated'),
(55, 'alessouza@live.com', '+5521986609260', 'email', '643439', '2026-01-21 20:27:11', '2026-01-21 20:22:11', 'validated');

-- --------------------------------------------------------

--
-- Estrutura para tabela `registrations`
--

CREATE TABLE `registrations` (
  `id` int NOT NULL,
  `person_id` int NOT NULL,
  `schedule_id` int NOT NULL,
  `payment_id` bigint DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `schedules`
--

CREATE TABLE `schedules` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `event_type_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `vacancies` int DEFAULT '0',
  `scheduled_at` datetime NOT NULL,
  `status` varchar(20) DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `schedules`
--

INSERT INTO `schedules` (`id`, `event_id`, `event_type_id`, `unit_id`, `vacancies`, `scheduled_at`, `status`, `created_at`) VALUES
(1, 1, 1, 1, 16, '2026-01-20 12:00:00', 'unavailable', '2026-01-07 01:50:42'),
(3, 3, 1, 1, 48, '2026-02-15 19:00:00', 'available', '2026-01-07 05:11:44'),
(4, 1, 1, 1, 50, '2025-01-01 19:00:00', 'unavailable', '2026-01-07 05:12:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transactions`
--

CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `schedule_id` int NOT NULL,
  `person_id` int DEFAULT NULL,
  `payer_email` varchar(255) DEFAULT NULL,
  `external_reference` varchar(255) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT 'pending',
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `types_person`
--

CREATE TABLE `types_person` (
  `id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `types_person`
--

INSERT INTO `types_person` (`id`, `name`, `created_at`) VALUES
(1, 'admin', '2026-01-05 04:13:28'),
(2, 'subscribed', '2026-01-21 15:14:04');

-- --------------------------------------------------------

--
-- Estrutura para tabela `units`
--

CREATE TABLE `units` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `units`
--

INSERT INTO `units` (`id`, `name`) VALUES
(1, 'Barra'),
(2, 'Presencial'),
(3, 'On-line'),
(4, 'Barra'),
(5, 'Pechincha');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `event_types`
--
ALTER TABLE `event_types`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `history_logs`
--
ALTER TABLE `history_logs`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `persons`
--
ALTER TABLE `persons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_person_type` (`type_person_id`);

--
-- Índices de tabela `person_details`
--
ALTER TABLE `person_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_details_person` (`person_id`);

--
-- Índices de tabela `registered_codes`
--
ALTER TABLE `registered_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `code` (`code`);

--
-- Índices de tabela `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reg_person` (`person_id`),
  ADD KEY `fk_reg_schedule` (`schedule_id`),
  ADD KEY `fk_reg_payment` (`payment_id`);

--
-- Índices de tabela `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sch_event` (`event_id`),
  ADD KEY `fk_sch_type` (`event_type_id`),
  ADD KEY `fk_sch_unit` (`unit_id`);

--
-- Índices de tabela `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Índices de tabela `types_person`
--
ALTER TABLE `types_person`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `events`
--
ALTER TABLE `events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `event_types`
--
ALTER TABLE `event_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `history_logs`
--
ALTER TABLE `history_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `persons`
--
ALTER TABLE `persons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `person_details`
--
ALTER TABLE `person_details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `registered_codes`
--
ALTER TABLE `registered_codes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de tabela `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de tabela `types_person`
--
ALTER TABLE `types_person`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `units`
--
ALTER TABLE `units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `persons`
--
ALTER TABLE `persons`
  ADD CONSTRAINT `fk_person_type` FOREIGN KEY (`type_person_id`) REFERENCES `types_person` (`id`);

--
-- Restrições para tabelas `person_details`
--
ALTER TABLE `person_details`
  ADD CONSTRAINT `fk_details_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_reg_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  ADD CONSTRAINT `fk_reg_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`),
  ADD CONSTRAINT `fk_reg_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`);

--
-- Restrições para tabelas `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_sch_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `fk_sch_type` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`),
  ADD CONSTRAINT `fk_sch_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);

--
-- Restrições para tabelas `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
