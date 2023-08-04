CREATE TABLE `clb_company_connection_fields` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `company_id` int(11) NOT NULL,
 `connection_id` int(11) NOT NULL,
 `is_default` tinyint(1) DEFAULT '0',
 `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `update_date` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 `nag_enabled` tinyint(4) DEFAULT NULL,
 `nag_duration` tinyint(4) DEFAULT NULL,
 `nag_regex` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `nag_regex_type` enum('MATCHES','NOTMATCHES') COLLATE utf8mb4_unicode_ci DEFAULT 'MATCHES',
 `type` enum('STANDARD','RFID_FUEL_CARD') COLLATE utf8mb4_unicode_ci DEFAULT 'STANDARD' COMMENT 'Type of verification fields',
 PRIMARY KEY (`id`),
 KEY `connection_name_index` (`name`),
 KEY `clb_company_foreign_key` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=101485 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
