CREATE TABLE `clb_driver_connection_values` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `driver_id` int(11) NOT NULL,
    `company_id` int(11) NOT NULL,
    `connection_id` int(11) NOT NULL,
    `company_connection_fields_id` int(11) NOT NULL,
    `connection_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL, -- NOTE: Changed to nullable
    `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_date` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `stop_nagging` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Stop nagging flag if a driver has updated the connection field',
    `last_nag_date` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniqueConnectionValue` (`company_connection_fields_id`,`driver_id`,`company_id`,`connection_id`),
    KEY `connection_value_index` (`connection_value`),
    KEY `company_foreign_key` (`company_id`),
    KEY `driver_foreign_key` (`driver_id`),
    KEY `clb_company_connection_fields_foreign_key` (`company_connection_fields_id`)
) ENGINE=InnoDB AUTO_INCREMENT=556465 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
