-- ========================================================
-- قاعدة بيانات نظام إدارة تكاليف المصنع
-- Factory Production Cost Management Database Dump
-- Compatible with MySQL / MariaDB (Hostinger)
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table structure for `migrations`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `migration` varchar not null, `batch` integer not null) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `migrations`
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_09_09_000002_create_products_table', 1),
(5, '2026_09_09_000003_create_fabrics_tables', 1),
(6, '2026_09_09_000004_create_product_cost_standards_table', 1),
(7, '2026_09_09_000005_create_daily_reports_table', 1),
(8, '2026_09_09_000006_create_daily_report_items_table', 1);

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `name` varchar not null, `email` varchar not null, `email_verified_at` datetime, `password` varchar not null, `remember_token` varchar, `created_at` datetime, `updated_at` datetime) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `users`
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'مدير النظام', 'admin@factory.com', NULL, '$2y$12$G2yzIs44r3eaL2zahu68nek/eArWG8KJeBvmWVA8mVeKKDmcvkcqe', 'CjWEBSCeP6qBu8rgXbWZ4t8GIILyuSgPpWHv534Zid93bnbmFf9CYjymoBMB', '2026-09-09 18:22:48', '2026-09-09 18:22:48');

-- --------------------------------------------------------
-- Table structure for `password_reset_tokens`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (`email` varchar not null, `token` varchar not null, `created_at` datetime, primary key (`email`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sessions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (`id` varchar not null, `user_id` integer, `ip_address` varchar, `user_agent` text, `payload` text not null, `last_activity` integer not null, primary key (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `cache`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (`key` varchar not null, `value` text not null, `expiration` integer not null, primary key (`key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `cache_locks`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (`key` varchar not null, `owner` varchar not null, `expiration` integer not null, primary key (`key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `jobs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `queue` varchar not null, `payload` text not null, `attempts` integer not null, `reserved_at` integer, `available_at` integer not null, `created_at` integer not null) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `job_batches`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (`id` varchar not null, `name` varchar not null, `total_jobs` integer not null, `pending_jobs` integer not null, `failed_jobs` integer not null, `failed_job_ids` text not null, `options` text, `cancelled_at` integer, `created_at` integer not null, `finished_at` integer, primary key (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `failed_jobs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `uuid` varchar not null, `connection` varchar not null, `queue` varchar not null, `payload` text not null, `exception` text not null, `failed_at` datetime not null default CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `products`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `order` integer not null, `code` varchar not null, `category` varchar not null, `size` varchar not null, `full_name` varchar not null, `is_active` tinyint(1) not null default '1', `created_at` datetime, `updated_at` datetime) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `products`
INSERT INTO `products` (`id`, `order`, `code`, `category`, `size`, `full_name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'A', 'بدون حواجز', '90*190', 'بدون حواجز — 90*190', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(2, 2, 'B', 'بدون حواجز', '100*200', 'بدون حواجز — 100*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(3, 3, 'C', 'بدون حواجز', '120*200', 'بدون حواجز — 120*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(4, 4, 'D', 'بدون حواجز', '140*200', 'بدون حواجز — 140*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(5, 5, 'E', 'بدون حواجز', '150*200', 'بدون حواجز — 150*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(6, 6, 'F', 'بدون حواجز', '160*200', 'بدون حواجز — 160*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(7, 7, 'G', 'بدون حواجز', '180*200', 'بدون حواجز — 180*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(8, 8, 'H', 'بدون حواجز', '200*200', 'بدون حواجز — 200*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(9, 9, 'I', 'حواجز مبرومة', '90*190', 'حواجز مبرومة — 90*190', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(10, 10, 'J', 'حواجز مبرومة', '100*200', 'حواجز مبرومة — 100*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(11, 11, 'K', 'حواجز مبرومة', '120*200', 'حواجز مبرومة — 120*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(12, 12, 'L', 'حواجز مبرومة', '140*200', 'حواجز مبرومة — 140*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(13, 13, 'M', 'حواجز مبرومة', '150*200', 'حواجز مبرومة — 150*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(14, 14, 'N', 'حواجز مبرومة', '160*200', 'حواجز مبرومة — 160*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(15, 15, 'O', 'حواجز مبرومة', '180*200', 'حواجز مبرومة — 180*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(16, 16, 'P', 'حواجز مبرومة', '200*200', 'حواجز مبرومة — 200*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(17, 17, 'Q', 'حواجز علب', '90*190', 'حواجز علب — 90*190', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(18, 18, 'R', 'حواجز علب', '100*200', 'حواجز علب — 100*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(19, 19, 'S', 'حواجز علب', '120*200', 'حواجز علب — 120*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(20, 20, 'T', 'حواجز علب', '140*200', 'حواجز علب — 140*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(21, 21, 'U', 'حواجز علب', '150*200', 'حواجز علب — 150*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(22, 22, 'V', 'حواجز علب', '160*200', 'حواجز علب — 160*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(23, 23, 'W', 'حواجز علب', '180*200', 'حواجز علب — 180*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(24, 24, 'X', 'حواجز علب', '200*200', 'حواجز علب — 200*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(25, 25, 'Y', 'حواجز مضخمة تقطيع', '90*190', 'حواجز مضخمة تقطيع — 90*190', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(26, 26, 'Z', 'حواجز مضخمة تقطيع', '100*200', 'حواجز مضخمة تقطيع — 100*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(27, 27, 'AA', 'حواجز مضخمة تقطيع', '120*200', 'حواجز مضخمة تقطيع — 120*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(28, 28, 'AB', 'حواجز مضخمة تقطيع', '140*200', 'حواجز مضخمة تقطيع — 140*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(29, 29, 'AC', 'حواجز مضخمة تقطيع', '150*200', 'حواجز مضخمة تقطيع — 150*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(30, 30, 'AD', 'حواجز مضخمة تقطيع', '160*200', 'حواجز مضخمة تقطيع — 160*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(31, 31, 'AE', 'حواجز مضخمة تقطيع', '180*200', 'حواجز مضخمة تقطيع — 180*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(32, 32, 'AF', 'حواجز مضخمة تقطيع', '200*200', 'حواجز مضخمة تقطيع — 200*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(33, 33, 'AG', 'حواجز مضخمة فرمات', '90*190', 'حواجز مضخمة فرمات — 90*190', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(34, 34, 'AH', 'حواجز مضخمة فرمات', '100*200', 'حواجز مضخمة فرمات — 100*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(35, 35, 'AI', 'حواجز مضخمة فرمات', '120*200', 'حواجز مضخمة فرمات — 120*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(36, 36, 'AJ', 'حواجز مضخمة فرمات', '140*200', 'حواجز مضخمة فرمات — 140*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(37, 37, 'AK', 'حواجز مضخمة فرمات', '150*200', 'حواجز مضخمة فرمات — 150*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(38, 38, 'AL', 'حواجز مضخمة فرمات', '160*200', 'حواجز مضخمة فرمات — 160*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(39, 39, 'AM', 'حواجز مضخمة فرمات', '180*200', 'حواجز مضخمة فرمات — 180*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(40, 40, 'AN', 'حواجز مضخمة فرمات', '200*200', 'حواجز مضخمة فرمات — 200*200', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48');

-- --------------------------------------------------------
-- Table structure for `fabric_companies`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `fabric_companies`;
CREATE TABLE `fabric_companies` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `name` varchar not null, `is_active` tinyint(1) not null default '1', `created_at` datetime, `updated_at` datetime) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `fabric_companies`
INSERT INTO `fabric_companies` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'كيف المجالس', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(2, 'ابداع السرير', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(3, 'ركن الخليج', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(4, 'النساج', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(5, 'روائع النسيج', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48');

-- --------------------------------------------------------
-- Table structure for `fabric_types`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `fabric_types`;
CREATE TABLE `fabric_types` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `name` varchar not null, `is_active` tinyint(1) not null default '1', `created_at` datetime, `updated_at` datetime) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `fabric_types`
INSERT INTO `fabric_types` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'خيش', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(2, 'مخمل ثقيل', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(3, 'مخمل', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(4, 'بوكلية', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(5, 'شانيل', 1, '2026-09-09 18:22:48', '2026-09-09 18:22:48');

-- --------------------------------------------------------
-- Table structure for `fabric_prices`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `fabric_prices`;
CREATE TABLE `fabric_prices` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `fabric_company_id` integer not null, `fabric_type_id` integer not null, `price_per_meter` numeric not null, `created_at` datetime, `updated_at` datetime, foreign key(`fabric_company_id`) references `fabric_companies`(`id`) on delete cascade, foreign key(`fabric_type_id`) references `fabric_types`(`id`) on delete cascade) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `fabric_prices`
INSERT INTO `fabric_prices` (`id`, `fabric_company_id`, `fabric_type_id`, `price_per_meter`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 7.5, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(2, 1, 2, 9, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(3, 1, 4, 15.5, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(4, 1, 5, 15.35, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(5, 2, 5, 14.88, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(6, 3, 4, 17.25, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(7, 3, 3, 8.05, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(8, 3, 5, 13.8, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(9, 4, 5, 21, '2026-09-09 18:22:48', '2026-09-09 18:22:48');

-- --------------------------------------------------------
-- Table structure for `product_cost_standards`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_cost_standards`;
CREATE TABLE `product_cost_standards` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `product_id` integer not null, `wood_plain` numeric not null default '0', `wood_hydraulic` numeric not null default '0', `leg_boxes` numeric not null default '0', `hydraulic_machines` numeric not null default '0', `wood_without_storage` numeric not null default '0', `wood_with_storage` numeric not null default '0', `fabric_meters` numeric not null default '0', `raw_material_transport` numeric not null default '0', `foam` numeric not null default '0', `paint` numeric not null default '0', `nails` numeric not null default '0', `hinges` numeric not null default '0', `packaging` numeric not null default '0', `carpentry_wages` numeric not null default '0', `upholstery_wages` numeric not null default '0', `packaging_wages` numeric not null default '0', `administrative_wages` numeric not null default '0', `advertising` numeric not null default '0', `shipping` numeric not null default '0', `miscellaneous` numeric not null default '0', `profit_margin` numeric not null default '0', `created_at` datetime, `updated_at` datetime, foreign key(`product_id`) references `products`(`id`) on delete cascade) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `product_cost_standards`
INSERT INTO `product_cost_standards` (`id`, `product_id`, `wood_plain`, `wood_hydraulic`, `leg_boxes`, `hydraulic_machines`, `wood_without_storage`, `wood_with_storage`, `fabric_meters`, `raw_material_transport`, `foam`, `paint`, `nails`, `hinges`, `packaging`, `carpentry_wages`, `upholstery_wages`, `packaging_wages`, `administrative_wages`, `advertising`, `shipping`, `miscellaneous`, `profit_margin`, `created_at`, `updated_at`) VALUES
(1, 1, 110.4, 290.95, 4, 115, 114.4, 409.95, 9.5, 0, 19, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(2, 2, 110.4, 290.95, 4, 115, 114.4, 409.95, 11.5, 0, 19, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(3, 3, 144.9, 290.95, 6, 115, 150.9, 411.95, 13.5, 0, 31.26, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(4, 4, 182.85, 290.95, 12, 230, 194.85, 532.95, 14.5, 0, 56.4, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(5, 5, 182.85, 290.95, 12, 230, 194.85, 532.95, 14.5, 0, 56.4, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(6, 6, 182.85, 451.95, 12, 230, 194.85, 693.95, 14.5, 0, 56.4, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(7, 7, 182.85, 451.95, 12, 230, 194.85, 693.95, 15, 0, 56.4, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(8, 8, 217.35, 451.95, 12, 230, 229.35, 693.95, 15, 0, 56.4, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(9, 9, 144.9, 359.95, 4, 115, 148.9, 478.95, 8.5, 0, 81.98, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(10, 10, 144.9, 359.95, 4, 115, 148.9, 478.95, 10, 0, 81.98, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(11, 11, 144.9, 359.95, 6, 115, 150.9, 480.95, 10, 0, 103.44, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(12, 12, 251.85, 359.95, 12, 230, 263.85, 601.95, 10, 0, 106.5, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(13, 13, 251.85, 359.95, 12, 230, 263.85, 601.95, 10, 0, 106.5, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(14, 14, 251.85, 520.95, 12, 230, 263.85, 762.95, 10, 0, 106.5, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(15, 15, 251.85, 520.95, 12, 230, 263.85, 762.95, 11, 0, 106.5, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(16, 16, 286.53, 520.95, 12, 230, 298.53, 762.95, 11, 0, 106.5, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(17, 17, 193.2, 397.9, 4, 115, 197.2, 516.9, 9, 0, 48.58, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(18, 18, 193.2, 397.9, 4, 115, 197.2, 516.9, 10, 0, 48.58, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(19, 19, 262.2, 397.9, 6, 115, 268.2, 518.9, 10, 0, 70.04, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(20, 20, 338.1, 397.9, 12, 230, 350.1, 639.9, 12.5, 0, 73.1, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(21, 21, 338.1, 397.9, 12, 230, 350.1, 639.9, 12.5, 0, 73.1, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(22, 22, 338.1, 558.9, 12, 230, 350.1, 800.9, 13.5, 0, 73.1, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(23, 23, 338.1, 558.9, 12, 230, 350.1, 800.9, 14, 0, 81.45, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(24, 24, 407.1, 558.9, 12, 230, 419.1, 800.9, 15, 0, 89.8, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(25, 25, 251.85, 458.85, 4, 115, 255.85, 577.85, 12, 0, 83.4, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(26, 26, 251.85, 458.85, 4, 115, 255.85, 577.85, 12, 0, 83.4, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(27, 27, 289.8, 458.85, 6, 115, 295.8, 579.85, 13, 0, 141.66, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(28, 28, 396.75, 458.85, 12, 230, 408.75, 700.85, 14, 0, 188.88, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(29, 29, 396.75, 458.85, 12, 230, 408.75, 700.85, 14, 0, 188.88, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(30, 30, 396.75, 619.85, 12, 230, 408.75, 861.85, 15, 0, 188.88, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(31, 31, 396.75, 619.85, 12, 230, 408.75, 861.85, 15, 0, 210.96, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(32, 32, 396.75, 619.85, 12, 230, 408.75, 861.85, 16, 0, 210.96, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(33, 33, 247.25, 393.3, 4, 115, 251.25, 512.3, 13, 0, 83.84, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(34, 34, 247.25, 393.3, 4, 115, 251.25, 512.3, 13.5, 0, 83.84, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(35, 35, 247.25, 393.3, 6, 115, 253.25, 514.3, 14, 0, 83.84, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(36, 36, 285.2, 592.25, 12, 230, 297.2, 834.25, 16, 0, 109.39, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(37, 37, 285.2, 592.25, 12, 230, 297.2, 834.25, 16, 0, 109.39, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(38, 38, 285.2, 592.25, 12, 230, 297.2, 834.25, 16.5, 0, 109.39, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(39, 39, 354.2, 592.25, 12, 230, 366.2, 834.25, 18, 0, 128.82, 0, 30, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48'),
(40, 40, 446.2, 602.6, 12, 230, 458.2, 844.6, 18, 0, 128.82, 0, 30, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:22:48', '2026-09-09 18:22:48');

-- --------------------------------------------------------
-- Table structure for `daily_reports`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `daily_reports`;
CREATE TABLE `daily_reports` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `production_date` date not null, `total_quantity` integer not null default '0', `total_cost` numeric not null default '0', `total_transport` numeric not null default '0', `total_wood` numeric not null default '0', `total_foam` numeric not null default '0', `total_fabric` numeric not null default '0', `total_paint` numeric not null default '0', `total_nails` numeric not null default '0', `total_hinges` numeric not null default '0', `total_packaging` numeric not null default '0', `total_carpentry_wages` numeric not null default '0', `total_upholstery_wages` numeric not null default '0', `total_packaging_wages` numeric not null default '0', `total_administrative_wages` numeric not null default '0', `total_advertising` numeric not null default '0', `total_shipping` numeric not null default '0', `total_miscellaneous` numeric not null default '0', `total_profit_margin` numeric not null default '0', `notes` text, `created_by` integer, `created_at` datetime, `updated_at` datetime, `deleted_at` datetime, foreign key(`created_by`) references `users`(`id`) on delete set null) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `daily_reports`
INSERT INTO `daily_reports` (`id`, `production_date`, `total_quantity`, `total_cost`, `total_transport`, `total_wood`, `total_foam`, `total_fabric`, `total_paint`, `total_nails`, `total_hinges`, `total_packaging`, `total_carpentry_wages`, `total_upholstery_wages`, `total_packaging_wages`, `total_administrative_wages`, `total_advertising`, `total_shipping`, `total_miscellaneous`, `total_profit_margin`, `notes`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '2026-09-09', 6, 3379.24, 0, 2467.7, 163.04, 658.5, 0, 60, 0, 30, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 1, '2026-09-09 18:32:53', '2026-09-09 18:32:53', NULL);

-- --------------------------------------------------------
-- Table structure for `daily_report_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `daily_report_items`;
CREATE TABLE `daily_report_items` (`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `daily_report_id` integer not null, `product_id` integer not null, `quantity` integer not null default '0', `storage_type` varchar not null default 'بدون تخزين', `fabric_company_id` integer, `fabric_type_id` integer, `unit_raw_material_transport` numeric not null default '0', `unit_wood_cost` numeric not null default '0', `unit_foam_cost` numeric not null default '0', `unit_fabric_meters` numeric not null default '0', `unit_fabric_price_per_meter` numeric not null default '0', `unit_fabric_cost` numeric not null default '0', `unit_paint_cost` numeric not null default '0', `unit_nails_cost` numeric not null default '0', `unit_hinges_cost` numeric not null default '0', `unit_packaging_cost` numeric not null default '0', `unit_carpentry_wages` numeric not null default '0', `unit_upholstery_wages` numeric not null default '0', `unit_packaging_wages` numeric not null default '0', `unit_administrative_wages` numeric not null default '0', `unit_advertising` numeric not null default '0', `unit_shipping` numeric not null default '0', `unit_miscellaneous` numeric not null default '0', `unit_profit_margin` numeric not null default '0', `unit_total_cost` numeric not null default '0', `total_transport` numeric not null default '0', `total_wood` numeric not null default '0', `total_foam` numeric not null default '0', `total_fabric` numeric not null default '0', `total_paint` numeric not null default '0', `total_nails` numeric not null default '0', `total_hinges` numeric not null default '0', `total_packaging` numeric not null default '0', `total_carpentry_wages` numeric not null default '0', `total_upholstery_wages` numeric not null default '0', `total_packaging_wages` numeric not null default '0', `total_administrative_wages` numeric not null default '0', `total_advertising` numeric not null default '0', `total_shipping` numeric not null default '0', `total_miscellaneous` numeric not null default '0', `total_profit_margin` numeric not null default '0', `line_total_cost` numeric not null default '0', `created_at` datetime, `updated_at` datetime, foreign key(`daily_report_id`) references `daily_reports`(`id`) on delete cascade, foreign key(`product_id`) references `products`(`id`), foreign key(`fabric_company_id`) references `fabric_companies`(`id`) on delete set null, foreign key(`fabric_type_id`) references `fabric_types`(`id`) on delete set null) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `daily_report_items`
INSERT INTO `daily_report_items` (`id`, `daily_report_id`, `product_id`, `quantity`, `storage_type`, `fabric_company_id`, `fabric_type_id`, `unit_raw_material_transport`, `unit_wood_cost`, `unit_foam_cost`, `unit_fabric_meters`, `unit_fabric_price_per_meter`, `unit_fabric_cost`, `unit_paint_cost`, `unit_nails_cost`, `unit_hinges_cost`, `unit_packaging_cost`, `unit_carpentry_wages`, `unit_upholstery_wages`, `unit_packaging_wages`, `unit_administrative_wages`, `unit_advertising`, `unit_shipping`, `unit_miscellaneous`, `unit_profit_margin`, `unit_total_cost`, `total_transport`, `total_wood`, `total_foam`, `total_fabric`, `total_paint`, `total_nails`, `total_hinges`, `total_packaging`, `total_carpentry_wages`, `total_upholstery_wages`, `total_packaging_wages`, `total_administrative_wages`, `total_advertising`, `total_shipping`, `total_miscellaneous`, `total_profit_margin`, `line_total_cost`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 0, 'بدون تخزين', NULL, NULL, 0, 114.4, 19, 9.5, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 148.4, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(2, 1, 2, 2, 'بتخزين', 1, 1, 0, 409.95, 19, 11.5, 7.5, 86.25, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 530.2, 0, 819.9, 38, 172.5, 0, 20, 0, 10, 0, 0, 0, 0, 0, 0, 0, 0, 1060.4, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(3, 1, 3, 4, 'بتخزين', 1, 2, 0, 411.95, 31.26, 13.5, 9, 121.5, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 579.71, 0, 1647.8, 125.04, 486, 0, 40, 0, 20, 0, 0, 0, 0, 0, 0, 0, 0, 2318.84, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(4, 1, 4, 0, 'بدون تخزين', NULL, NULL, 0, 194.85, 56.4, 14.5, 0, 0, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 276.25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(5, 1, 5, 0, 'بدون تخزين', NULL, NULL, 0, 194.85, 56.4, 14.5, 0, 0, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, 277.25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(6, 1, 6, 0, 'بدون تخزين', NULL, NULL, 0, 194.85, 56.4, 14.5, 0, 0, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, 278.25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(7, 1, 7, 0, 'بدون تخزين', NULL, NULL, 0, 194.85, 56.4, 15, 0, 0, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 284.25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(8, 1, 8, 0, 'بدون تخزين', NULL, NULL, 0, 229.35, 56.4, 15, 0, 0, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 318.75, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(9, 1, 9, 0, 'بدون تخزين', NULL, NULL, 0, 148.9, 81.98, 8.5, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 245.88, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(10, 1, 10, 0, 'بدون تخزين', NULL, NULL, 0, 148.9, 81.98, 10, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 245.88, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(11, 1, 11, 0, 'بدون تخزين', NULL, NULL, 0, 150.9, 103.44, 10, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 269.34, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(12, 1, 12, 0, 'بدون تخزين', NULL, NULL, 0, 263.85, 106.5, 10, 0, 0, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 395.35, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(13, 1, 13, 0, 'بدون تخزين', NULL, NULL, 0, 263.85, 106.5, 10, 0, 0, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, 396.35, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(14, 1, 14, 0, 'بدون تخزين', NULL, NULL, 0, 263.85, 106.5, 10, 0, 0, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, 397.35, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(15, 1, 15, 0, 'بدون تخزين', NULL, NULL, 0, 263.85, 106.5, 11, 0, 0, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 403.35, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(16, 1, 16, 0, 'بدون تخزين', NULL, NULL, 0, 298.53, 106.5, 11, 0, 0, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 438.03, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(17, 1, 17, 0, 'بدون تخزين', NULL, NULL, 0, 197.2, 48.58, 9, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 260.78, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(18, 1, 18, 0, 'بدون تخزين', NULL, NULL, 0, 197.2, 48.58, 10, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 260.78, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(19, 1, 19, 0, 'بدون تخزين', NULL, NULL, 0, 268.2, 70.04, 10, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 353.24, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(20, 1, 20, 0, 'بدون تخزين', NULL, NULL, 0, 350.1, 73.1, 12.5, 0, 0, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 448.2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(21, 1, 21, 0, 'بدون تخزين', NULL, NULL, 0, 350.1, 73.1, 12.5, 0, 0, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, 449.2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(22, 1, 22, 0, 'بدون تخزين', NULL, NULL, 0, 350.1, 73.1, 13.5, 0, 0, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, 450.2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(23, 1, 23, 0, 'بدون تخزين', NULL, NULL, 0, 350.1, 81.45, 14, 0, 0, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 464.55, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(24, 1, 24, 0, 'بدون تخزين', NULL, NULL, 0, 419.1, 89.8, 15, 0, 0, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 541.9, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(25, 1, 25, 0, 'بدون تخزين', NULL, NULL, 0, 255.85, 83.4, 12, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 354.25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(26, 1, 26, 0, 'بدون تخزين', NULL, NULL, 0, 255.85, 83.4, 12, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 354.25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(27, 1, 27, 0, 'بدون تخزين', NULL, NULL, 0, 295.8, 141.66, 13, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 452.46, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(28, 1, 28, 0, 'بدون تخزين', NULL, NULL, 0, 408.75, 188.88, 14, 0, 0, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 622.63, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(29, 1, 29, 0, 'بدون تخزين', NULL, NULL, 0, 408.75, 188.88, 14, 0, 0, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, 623.63, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(30, 1, 30, 0, 'بدون تخزين', NULL, NULL, 0, 408.75, 188.88, 15, 0, 0, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, 624.63, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(31, 1, 31, 0, 'بدون تخزين', NULL, NULL, 0, 408.75, 210.96, 15, 0, 0, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 652.71, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(32, 1, 32, 0, 'بدون تخزين', NULL, NULL, 0, 408.75, 210.96, 16, 0, 0, 0, 25, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 652.71, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(33, 1, 33, 0, 'بدون تخزين', NULL, NULL, 0, 251.25, 83.84, 13, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 350.09, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(34, 1, 34, 0, 'بدون تخزين', NULL, NULL, 0, 251.25, 83.84, 13.5, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 350.09, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(35, 1, 35, 0, 'بدون تخزين', NULL, NULL, 0, 253.25, 83.84, 14, 0, 0, 0, 10, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 352.09, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(36, 1, 36, 0, 'بدون تخزين', NULL, NULL, 0, 297.2, 109.39, 16, 0, 0, 0, 20, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 431.59, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(37, 1, 37, 0, 'بدون تخزين', NULL, NULL, 0, 297.2, 109.39, 16, 0, 0, 0, 20, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, 432.59, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(38, 1, 38, 0, 'بدون تخزين', NULL, NULL, 0, 297.2, 109.39, 16.5, 0, 0, 0, 20, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, 433.59, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(39, 1, 39, 0, 'بدون تخزين', NULL, NULL, 0, 366.2, 128.82, 18, 0, 0, 0, 30, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 533.02, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53'),
(40, 1, 40, 0, 'بدون تخزين', NULL, NULL, 0, 458.2, 128.82, 18, 0, 0, 0, 30, 0, 8, 0, 0, 0, 0, 0, 0, 0, 0, 625.02, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-09-09 18:32:53', '2026-09-09 18:32:53');

SET FOREIGN_KEY_CHECKS = 1;
