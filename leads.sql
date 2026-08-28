CREATE TABLE `leads` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `zip` CHAR(5) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `age_band` VARCHAR(30) NOT NULL,
    `parts_ab` VARCHAR(10) NOT NULL,
    `medicaid` VARCHAR(10) NOT NULL,
    `consent_contact` TINYINT(1) NOT NULL DEFAULT 0,
    `terms_ack` TINYINT(1) NOT NULL DEFAULT 0,
    `trusted` TEXT NULL,
    `consent_text_rendered` TEXT NULL,
    `submitted_at` VARCHAR(40) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_leads_zip` (`zip`),
    KEY `idx_leads_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
