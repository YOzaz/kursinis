/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `analysis_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analysis_jobs` (
  `job_id` char(36) NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL,
  `total_execution_time_seconds` int(11) DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `total_texts` int(11) NOT NULL,
  `processed_texts` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `custom_prompt` text DEFAULT NULL,
  `reference_analysis_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `failed_models` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of models that failed during analysis' CHECK (json_valid(`failed_models`)),
  `retry_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of times this job has been retried',
  `last_retry_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp of last retry attempt',
  `model_status` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Status of each model (success/failed/pending)' CHECK (json_valid(`model_status`)),
  PRIMARY KEY (`job_id`),
  KEY `analysis_jobs_status_created_at_index` (`status`,`created_at`),
  KEY `analysis_jobs_reference_analysis_id_foreign` (`reference_analysis_id`),
  CONSTRAINT `analysis_jobs_reference_analysis_id_foreign` FOREIGN KEY (`reference_analysis_id`) REFERENCES `analysis_jobs` (`job_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comparison_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comparison_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` char(36) NOT NULL,
  `text_id` varchar(255) NOT NULL,
  `model_name` varchar(255) NOT NULL,
  `actual_model_name` varchar(255) DEFAULT NULL COMMENT 'The actual model name (e.g., claude-sonnet-4-20250514)',
  `true_positives` int(11) NOT NULL DEFAULT 0,
  `false_positives` int(11) NOT NULL DEFAULT 0,
  `false_negatives` int(11) NOT NULL DEFAULT 0,
  `position_accuracy` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `analysis_execution_time_ms` int(11) DEFAULT NULL,
  `precision` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `recall` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `f1_score` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comparison_metrics_job_id_model_name_index` (`job_id`,`model_name`),
  KEY `comparison_metrics_text_id_model_name_index` (`text_id`,`model_name`),
  CONSTRAINT `comparison_metrics_job_id_foreign` FOREIGN KEY (`job_id`) REFERENCES `analysis_jobs` (`job_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_analysis_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_analysis_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` varchar(255) NOT NULL,
  `text_id` varchar(255) NOT NULL,
  `model_name` varchar(255) NOT NULL,
  `actual_model_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','processing','success','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `processing_time` decimal(8,3) DEFAULT NULL COMMENT 'Processing time in seconds',
  `response_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional response info' CHECK (json_valid(`response_metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `model_analysis_logs_job_id_model_name_index` (`job_id`,`model_name`),
  KEY `model_analysis_logs_status_created_at_index` (`status`,`created_at`),
  KEY `model_analysis_logs_job_id_index` (`job_id`),
  KEY `model_analysis_logs_text_id_index` (`text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `text_analysis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `text_analysis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` char(36) NOT NULL,
  `text_id` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `expert_annotations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`expert_annotations`)),
  `claude_annotations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`claude_annotations`)),
  `claude_actual_model` varchar(255) DEFAULT NULL COMMENT 'Actual Claude model used (e.g., claude-sonnet-4-20250514)',
  `claude_execution_time_ms` int(11) DEFAULT NULL,
  `gemini_annotations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gemini_annotations`)),
  `gemini_actual_model` varchar(255) DEFAULT NULL COMMENT 'Actual Gemini model used (e.g., gemini-2.5-pro-preview-05-06)',
  `gemini_execution_time_ms` int(11) DEFAULT NULL,
  `gpt_annotations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gpt_annotations`)),
  `gpt_actual_model` varchar(255) DEFAULT NULL COMMENT 'Actual GPT model used (e.g., gpt-4o)',
  `gpt_execution_time_ms` int(11) DEFAULT NULL,
  `analysis_attempts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Track analysis attempts per model' CHECK (json_valid(`analysis_attempts`)),
  `last_updated_at` timestamp NULL DEFAULT NULL COMMENT 'Last time any model was updated',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `text_analysis_job_id_text_id_index` (`job_id`,`text_id`),
  CONSTRAINT `text_analysis_job_id_foreign` FOREIGN KEY (`job_id`) REFERENCES `analysis_jobs` (`job_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*M!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2024_01_01_000001_create_analysis_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2024_01_01_000002_create_text_analysis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2024_01_01_000003_create_comparison_metrics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_05_27_200054_add_actual_model_name_to_analysis_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_05_27_201823_add_retry_functionality_to_analysis_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_05_27_225002_add_custom_prompt_and_reference_to_analysis_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_05_27_232216_change_model_name_to_string_in_comparison_metrics',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_05_28_000732_drop_experiment_tables_and_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_05_28_015051_add_execution_time_to_analysis_tables',1);
