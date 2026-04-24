/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `audit_logs_user_id_index` (`user_id`),
  KEY `audit_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cells`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cells` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rack_id` bigint unsigned NOT NULL,
  `dominant_category_id` bigint unsigned DEFAULT NULL,
  `zone_category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` tinyint unsigned NOT NULL,
  `column` tinyint unsigned NOT NULL,
  `capacity_max` int unsigned NOT NULL,
  `capacity_used` int unsigned NOT NULL DEFAULT '0',
  `status` enum('available','partial','full','blocked','reserved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `qr_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cells_rack_id_level_column_unique` (`rack_id`,`level`,`column`),
  UNIQUE KEY `cells_qr_code_unique` (`qr_code`),
  KEY `cells_dominant_category_id_foreign` (`dominant_category_id`),
  CONSTRAINT `cells_dominant_category_id_foreign` FOREIGN KEY (`dominant_category_id`) REFERENCES `item_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cells_rack_id_foreign` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deadstock_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deadstock_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `cell_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `days_no_movement` int unsigned NOT NULL DEFAULT '0',
  `last_movement_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','acknowledged','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deadstock_notifications_cell_id_foreign` (`cell_id`),
  KEY `deadstock_notifications_warehouse_id_status_index` (`warehouse_id`,`status`),
  KEY `deadstock_notifications_item_id_cell_id_index` (`item_id`,`cell_id`),
  CONSTRAINT `deadstock_notifications_cell_id_foreign` FOREIGN KEY (`cell_id`) REFERENCES `cells` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deadstock_notifications_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deadstock_notifications_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ga_recommendation_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ga_recommendation_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ga_recommendation_id` bigint unsigned NOT NULL,
  `inbound_order_item_id` bigint unsigned NOT NULL,
  `cell_id` bigint unsigned NOT NULL,
  `quantity` int unsigned NOT NULL,
  `gene_fitness` decimal(8,4) DEFAULT NULL,
  `fc_cap_score` decimal(8,4) DEFAULT NULL,
  `fc_cat_score` decimal(8,4) DEFAULT NULL,
  `fc_aff_score` decimal(8,4) DEFAULT NULL,
  `fc_split_score` decimal(8,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ga_recommendation_details_inbound_order_item_id_foreign` (`inbound_order_item_id`),
  KEY `ga_recommendation_details_cell_id_foreign` (`cell_id`),
  KEY `ga_recommendation_details_ga_recommendation_id_cell_id_index` (`ga_recommendation_id`,`cell_id`),
  CONSTRAINT `ga_recommendation_details_cell_id_foreign` FOREIGN KEY (`cell_id`) REFERENCES `cells` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ga_recommendation_details_ga_recommendation_id_foreign` FOREIGN KEY (`ga_recommendation_id`) REFERENCES `ga_recommendations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ga_recommendation_details_inbound_order_item_id_foreign` FOREIGN KEY (`inbound_order_item_id`) REFERENCES `inbound_details` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ga_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ga_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inbound_order_id` bigint unsigned NOT NULL,
  `generated_by` bigint unsigned DEFAULT NULL,
  `chromosome_json` json NOT NULL,
  `fitness_score` decimal(8,4) DEFAULT NULL,
  `generations_run` int unsigned NOT NULL DEFAULT '0',
  `execution_time_ms` int unsigned DEFAULT NULL,
  `parameters_json` json DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','accepted','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ga_recommendations_inbound_order_id_foreign` (`inbound_order_id`),
  KEY `ga_recommendations_generated_by_foreign` (`generated_by`),
  CONSTRAINT `ga_recommendations_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ga_recommendations_inbound_order_id_foreign` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inbound_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inbound_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inbound_order_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `lpn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lpn_timestamp` datetime DEFAULT NULL,
  `quantity_ordered` int unsigned NOT NULL,
  `quantity_received` int unsigned NOT NULL DEFAULT '0',
  `status` enum('pending','recommended','put_away','partial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inbound_details_inbound_order_id_foreign` (`inbound_order_id`),
  KEY `inbound_details_item_id_foreign` (`item_id`),
  CONSTRAINT `inbound_details_inbound_order_id_foreign` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inbound_details_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inbound_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inbound_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `received_by` bigint unsigned DEFAULT NULL,
  `do_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_bukti_manual` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `erp_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_doc_spk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_header` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `do_date` date NOT NULL,
  `received_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `status` enum('draft','processing','recommended','put_away','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inbound_transactions_do_number_unique` (`do_number`),
  KEY `inbound_transactions_warehouse_id_foreign` (`warehouse_id`),
  KEY `inbound_transactions_supplier_id_foreign` (`supplier_id`),
  KEY `inbound_transactions_received_by_foreign` (`received_by`),
  CONSTRAINT `inbound_transactions_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inbound_transactions_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inbound_transactions_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `item_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_categories_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `erp_item_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_size` enum('small','medium','large','extra_large') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deadstock_threshold_days` smallint unsigned NOT NULL DEFAULT '90' COMMENT 'Hari tanpa pergerakan sebelum dianggap deadstock',
  `barcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_stock` int unsigned NOT NULL DEFAULT '0',
  `max_stock` int unsigned NOT NULL DEFAULT '0',
  `reorder_point` int unsigned NOT NULL DEFAULT '0',
  `movement_type` enum('fast_moving','slow_moving','non_moving') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'slow_moving',
  `weight_kg` decimal(8,3) DEFAULT NULL,
  `volume_m3` decimal(8,4) DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `items_sku_unique` (`sku`),
  UNIQUE KEY `items_barcode_unique` (`barcode`),
  KEY `items_category_id_foreign` (`category_id`),
  KEY `items_unit_id_foreign` (`unit_id`),
  CONSTRAINT `items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `items_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `put_away_confirmations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `put_away_confirmations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inbound_order_item_id` bigint unsigned NOT NULL,
  `cell_id` bigint unsigned NOT NULL,
  `ga_recommendation_detail_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `quantity_stored` int unsigned NOT NULL,
  `follow_recommendation` tinyint(1) NOT NULL DEFAULT '1',
  `confirmed_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `put_away_confirmations_cell_id_foreign` (`cell_id`),
  KEY `put_away_confirmations_ga_recommendation_detail_id_foreign` (`ga_recommendation_detail_id`),
  KEY `put_away_confirmations_user_id_foreign` (`user_id`),
  KEY `put_away_confirmations_inbound_order_item_id_cell_id_index` (`inbound_order_item_id`,`cell_id`),
  CONSTRAINT `put_away_confirmations_cell_id_foreign` FOREIGN KEY (`cell_id`) REFERENCES `cells` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `put_away_confirmations_ga_recommendation_detail_id_foreign` FOREIGN KEY (`ga_recommendation_detail_id`) REFERENCES `ga_recommendation_details` (`id`) ON DELETE SET NULL,
  CONSTRAINT `put_away_confirmations_inbound_order_item_id_foreign` FOREIGN KEY (`inbound_order_item_id`) REFERENCES `inbound_details` (`id`) ON DELETE CASCADE,
  CONSTRAINT `put_away_confirmations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `put_away_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `put_away_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inbound_order_id` bigint unsigned NOT NULL,
  `inbound_order_item_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `cell_id` bigint unsigned NOT NULL,
  `confirmed_by` bigint unsigned DEFAULT NULL,
  `fitness_score` decimal(10,6) DEFAULT NULL,
  `generation` int DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `chromosome_index` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','confirmed','rejected','override') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `override_cell_id` bigint unsigned DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `put_away_recommendations_inbound_order_id_foreign` (`inbound_order_id`),
  KEY `put_away_recommendations_inbound_order_item_id_foreign` (`inbound_order_item_id`),
  KEY `put_away_recommendations_item_id_foreign` (`item_id`),
  KEY `put_away_recommendations_cell_id_foreign` (`cell_id`),
  KEY `put_away_recommendations_confirmed_by_foreign` (`confirmed_by`),
  KEY `put_away_recommendations_override_cell_id_foreign` (`override_cell_id`),
  CONSTRAINT `put_away_recommendations_cell_id_foreign` FOREIGN KEY (`cell_id`) REFERENCES `cells` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `put_away_recommendations_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `put_away_recommendations_inbound_order_id_foreign` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `put_away_recommendations_inbound_order_item_id_foreign` FOREIGN KEY (`inbound_order_item_id`) REFERENCES `inbound_details` (`id`) ON DELETE CASCADE,
  CONSTRAINT `put_away_recommendations_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `put_away_recommendations_override_cell_id_foreign` FOREIGN KEY (`override_cell_id`) REFERENCES `cells` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `racks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `racks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zone_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `dominant_category_id` bigint unsigned DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rack_number` smallint unsigned DEFAULT NULL,
  `total_levels` tinyint unsigned NOT NULL DEFAULT '4',
  `total_columns` tinyint unsigned NOT NULL DEFAULT '3',
  `pos_x` double(8,2) NOT NULL DEFAULT '0.00',
  `pos_y` double(8,2) NOT NULL DEFAULT '0.00',
  `width_3d` double(8,2) NOT NULL DEFAULT '2.00',
  `height_3d` double(8,2) NOT NULL DEFAULT '3.00',
  `depth_3d` double(8,2) NOT NULL DEFAULT '1.00',
  `pos_z` double(8,2) NOT NULL DEFAULT '0.00',
  `rotation_y` double(8,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `racks_zone_id_code_unique` (`zone_id`,`code`),
  KEY `racks_dominant_category_id_foreign` (`dominant_category_id`),
  KEY `racks_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `racks_dominant_category_id_foreign` FOREIGN KEY (`dominant_category_id`) REFERENCES `item_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `racks_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `racks_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permission` (
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permission_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permission_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permission_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `from_cell_id` bigint unsigned DEFAULT NULL,
  `to_cell_id` bigint unsigned DEFAULT NULL,
  `performed_by` bigint unsigned DEFAULT NULL,
  `lpn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `movement_type` enum('inbound','outbound','transfer','adjustment','return_inbound','return_outbound') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `moved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_from_cell_id_foreign` (`from_cell_id`),
  KEY `stock_movements_to_cell_id_foreign` (`to_cell_id`),
  KEY `stock_movements_performed_by_foreign` (`performed_by`),
  KEY `stock_movements_item_id_created_at_index` (`item_id`,`created_at`),
  KEY `stock_movements_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `stock_movements_warehouse_id_index` (`warehouse_id`),
  CONSTRAINT `stock_movements_from_cell_id_foreign` FOREIGN KEY (`from_cell_id`) REFERENCES `cells` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_movements_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_to_cell_id_foreign` FOREIGN KEY (`to_cell_id`) REFERENCES `cells` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `cell_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `inbound_order_item_id` bigint unsigned DEFAULT NULL,
  `lpn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `inbound_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `last_moved_at` timestamp NULL DEFAULT NULL,
  `status` enum('available','reserved','quarantine','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_records_cell_id_foreign` (`cell_id`),
  KEY `stock_records_inbound_order_item_id_foreign` (`inbound_order_item_id`),
  KEY `stock_records_item_id_cell_id_index` (`item_id`,`cell_id`),
  KEY `stock_records_inbound_date_index` (`inbound_date`),
  KEY `stock_records_warehouse_id_index` (`warehouse_id`),
  CONSTRAINT `stock_records_cell_id_foreign` FOREIGN KEY (`cell_id`) REFERENCES `cells` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_records_inbound_order_item_id_foreign` FOREIGN KEY (`inbound_order_item_id`) REFERENCES `inbound_details` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_records_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_records_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `erp_vendor_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role_id` bigint unsigned DEFAULT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `employee_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_employee_id_unique` (`employee_id`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `pic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouses_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` bigint unsigned NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pos_x` double(8,2) NOT NULL DEFAULT '0.00',
  `pos_z` double(8,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zones_warehouse_id_code_unique` (`warehouse_id`,`code`),
  CONSTRAINT `zones_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` VALUES (2,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` VALUES (3,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` VALUES (4,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` VALUES (5,'2026_02_27_000001_create_roles_table',1);
INSERT INTO `migrations` VALUES (6,'2026_02_27_000002_create_permissions_table',1);
INSERT INTO `migrations` VALUES (7,'2026_02_27_000003_create_role_permission_table',1);
INSERT INTO `migrations` VALUES (8,'2026_02_27_000004_add_role_id_to_users_table',1);
INSERT INTO `migrations` VALUES (9,'2026_02_27_000005_create_item_categories_table',1);
INSERT INTO `migrations` VALUES (10,'2026_02_27_000006_create_units_table',1);
INSERT INTO `migrations` VALUES (11,'2026_02_27_000007_create_suppliers_table',1);
INSERT INTO `migrations` VALUES (12,'2026_02_27_000008_create_warehouses_table',1);
INSERT INTO `migrations` VALUES (13,'2026_02_27_000009_create_zones_table',1);
INSERT INTO `migrations` VALUES (14,'2026_02_27_000010_create_racks_table',1);
INSERT INTO `migrations` VALUES (15,'2026_02_27_000011_create_cells_table',1);
INSERT INTO `migrations` VALUES (16,'2026_02_27_000012_create_items_table',1);
INSERT INTO `migrations` VALUES (17,'2026_02_27_000013_create_inbound_orders_table',1);
INSERT INTO `migrations` VALUES (18,'2026_02_27_000014_create_inbound_order_items_table',1);
INSERT INTO `migrations` VALUES (19,'2026_02_27_000015_create_put_away_recommendations_table',1);
INSERT INTO `migrations` VALUES (20,'2026_02_27_000016_create_stocks_table',1);
INSERT INTO `migrations` VALUES (21,'2026_02_27_000017_create_stock_movements_table',1);
INSERT INTO `migrations` VALUES (22,'2026_03_15_154739_add_dominant_category_to_racks_table',1);
INSERT INTO `migrations` VALUES (23,'2026_03_23_000001_create_audit_logs_table',1);
INSERT INTO `migrations` VALUES (24,'2026_04_14_000001_add_warehouse_id_to_users_table',1);
INSERT INTO `migrations` VALUES (25,'2026_04_14_000002_add_3d_columns_to_racks_table',1);
INSERT INTO `migrations` VALUES (26,'2026_04_14_000003_add_columns_to_cells_table',1);
INSERT INTO `migrations` VALUES (27,'2026_04_14_000004_add_item_size_to_items_table',1);
INSERT INTO `migrations` VALUES (28,'2026_04_14_000005_add_columns_to_inbound_orders_table',1);
INSERT INTO `migrations` VALUES (29,'2026_04_14_000006_add_lpn_timestamp_to_inbound_order_items_table',1);
INSERT INTO `migrations` VALUES (30,'2026_04_14_000007_create_ga_recommendations_table',1);
INSERT INTO `migrations` VALUES (31,'2026_04_14_000008_create_ga_recommendation_details_table',1);
INSERT INTO `migrations` VALUES (32,'2026_04_14_000009_create_put_away_confirmations_table',1);
INSERT INTO `migrations` VALUES (33,'2026_04_14_000010_add_warehouse_id_to_stocks_table',1);
INSERT INTO `migrations` VALUES (34,'2026_04_14_000011_add_columns_to_stock_movements_table',1);
INSERT INTO `migrations` VALUES (35,'2026_04_14_000012_create_deadstock_notifications_table',1);
