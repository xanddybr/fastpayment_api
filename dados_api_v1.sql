-- MySQL dump 10.13  Distrib 8.0.42, for Linux (x86_64)
--
-- Host: localhost    Database: u967889760_fastpayment
-- ------------------------------------------------------
-- Server version	8.0.42-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `anamnesis`
--

LOCK TABLES `anamnesis` WRITE;
/*!40000 ALTER TABLE `anamnesis` DISABLE KEYS */;
INSERT INTO `anamnesis` (`id`, `subscribed_id`, `course_reason`, `expectations`, `who_recomend`, `is_medium`, `religion`, `religion_mention`, `is_tule_member`, `obs_motived`, `first_time`, `created_at`) VALUES (1,1,'gosto deste curso','conseguir melhorar como pessoa','uma amiga do tule',1,1,'TESE tenda espirita santo expedito',0,'preciso crescer espiritualmente',1,'2026-02-13 03:46:40'),(2,2,NULL,NULL,NULL,0,0,NULL,0,NULL,1,'2026-02-13 16:28:36'),(3,3,NULL,NULL,NULL,0,0,NULL,0,NULL,1,'2026-02-13 16:35:58'),(4,5,NULL,NULL,NULL,0,0,NULL,0,NULL,1,'2026-02-13 16:51:11'),(6,8,NULL,NULL,NULL,0,0,NULL,0,NULL,1,'2026-02-19 01:32:11');
/*!40000 ALTER TABLE `anamnesis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `event_types`
--

LOCK TABLES `event_types` WRITE;
/*!40000 ALTER TABLE `event_types` DISABLE KEYS */;
INSERT INTO `event_types` (`id`, `name`, `slug`) VALUES (1,'Curso','curso'),(6,'Presencial','presencial'),(9,'Palestra','palestra');
/*!40000 ALTER TABLE `event_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT INTO `events` (`id`, `name`, `price`, `slug`) VALUES (2,'Tarot Basico',0.83,'yoga'),(6,'Jogo de Buzios',0.15,'jogo-de-buzios'),(8,'Riki Nivel 1',0.89,'riki-2');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `events_subscribed`
--

LOCK TABLES `events_subscribed` WRITE;
/*!40000 ALTER TABLE `events_subscribed` DISABLE KEYS */;
INSERT INTO `events_subscribed` (`id`, `person_id`, `schedule_id`, `payment_id`, `status`, `created_at`) VALUES (1,2,10,1234567890,'confirmed','2026-02-13 03:46:40'),(2,8,18,123456789,'confirmed','2026-02-13 16:28:36'),(3,8,18,999888777,'confirmed','2026-02-13 16:35:58'),(5,1,18,NULL,'confirmed','2026-02-13 16:51:11'),(8,7,18,NULL,'confirmed','2026-02-19 01:32:11');
/*!40000 ALTER TABLE `events_subscribed` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `history_logs`
--

LOCK TABLES `history_logs` WRITE;
/*!40000 ALTER TABLE `history_logs` DISABLE KEYS */;
INSERT INTO `history_logs` (`id`, `transaction_id`, `action`, `details`, `created_at`) VALUES (1,1,'Pagamento Confirmado','O sistema recebeu a confirmação do Mercado Pago.','2026-01-29 13:19:25');
/*!40000 ALTER TABLE `history_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` (`id`, `amount`, `status`, `payer_email`, `external_reference`, `approved_at`, `created_at`) VALUES (1234567890,250.00,'approved','cliente@teste.com','REG-001','2026-01-29 13:19:25','2026-01-29 13:19:25');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `person_details`
--

LOCK TABLES `person_details` WRITE;
/*!40000 ALTER TABLE `person_details` DISABLE KEYS */;
INSERT INTO `person_details` (`id`, `person_id`, `activity_professional`, `phone`, `street`, `number`, `neighborhood`, `city`) VALUES (6,7,'Dev','2193945728',NULL,NULL,NULL,'RJ'),(7,8,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `person_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `persons`
--

LOCK TABLES `persons` WRITE;
/*!40000 ALTER TABLE `persons` DISABLE KEYS */;
INSERT INTO `persons` (`id`, `full_name`, `email`, `password`, `status`, `type_person_id`, `created_at`) VALUES (1,'Administrador do Sistema','admin@gmail.com','$2y$12$PkenEiMEvSS4NNy8ed.F3OlBF11j4zxtGT.sy9.D1o5tlRQ9S5GyK','active',1,'2026-01-29 13:19:25'),(2,'Aline Santana','aline@gmail.com','$2y$12$isEBehAToycdZfLmX3iGN.UAtq/tnjwxCbM47dh0x9j8.It0wH4kq','active',2,'2026-01-29 13:19:25'),(7,'Natalia','nat2026@gmail.com','$2y$12$9oWeujvEDDZq9IOVoG3mX.2DuJKYZWo8zVB0LNcyyKXWPr6//Cia.','active',1,'2026-02-12 19:47:09'),(8,'Alexandre','xanddybr@gmail.com','$2y$12$FK7PUR5nPfWiKcAReyK2M.okjaPAOb9IU9kBvX4bUGb4Sa9drmbX.','active',1,'2026-02-13 16:17:54');
/*!40000 ALTER TABLE `persons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `phinxlog`
--

LOCK TABLES `phinxlog` WRITE;
/*!40000 ALTER TABLE `phinxlog` DISABLE KEYS */;
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES (20260123190539,'CreateInitialSchema','2026-01-29 16:09:40','2026-01-29 16:09:40',0);
/*!40000 ALTER TABLE `phinxlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `registered_codes`
--

LOCK TABLES `registered_codes` WRITE;
/*!40000 ALTER TABLE `registered_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `registered_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
INSERT INTO `schedules` (`id`, `event_id`, `event_type_id`, `unit_id`, `vacancies`, `scheduled_at`, `status`, `created_at`) VALUES (10,2,1,6,9,'2026-02-20 03:23:00','available','2026-03-03 04:25:01'),(15,6,6,8,37,'2026-02-25 13:27:00','available','2026-02-28 13:15:13'),(18,8,1,9,4,'2026-02-20 10:00:00','available','2026-02-13 16:03:23');
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` (`id`, `schedule_id`, `person_id`, `payer_email`, `payment_status`, `amount`, `created_at`, `updated_at`) VALUES (2,18,8,NULL,'approved',0.00,'2026-02-13 16:28:36','2026-02-13 16:28:36'),(3,18,8,NULL,'approved',0.00,'2026-02-13 16:35:58','2026-02-13 16:35:58'),(5,18,1,NULL,'approved',2.00,'2026-02-13 16:51:11','2026-02-13 16:51:11'),(8,18,7,NULL,'approved',0.89,'2026-02-19 01:32:11','2026-02-19 01:32:11');
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `types_person`
--

LOCK TABLES `types_person` WRITE;
/*!40000 ALTER TABLE `types_person` DISABLE KEYS */;
INSERT INTO `types_person` (`id`, `name`, `created_at`) VALUES (1,'Admin','2026-01-29 13:19:24'),(2,'Subscribe','2026-01-29 13:19:24');
/*!40000 ALTER TABLE `types_person` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `units`
--

LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` (`id`, `name`, `slug`) VALUES (6,'Barra','barra'),(8,'Pechincha','pechincha'),(9,'On-line','on-line');
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-20 19:34:10
