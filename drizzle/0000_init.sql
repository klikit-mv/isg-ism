CREATE TABLE `activities` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`name` varchar(255) NOT NULL,
	`date` date NOT NULL,
	`details` text,
	`all_students` boolean NOT NULL DEFAULT false,
	`charge_fee` boolean NOT NULL DEFAULT false,
	`fee_amount` decimal(12,2),
	`certificate_template_id` bigint unsigned,
	`created_by` bigint unsigned,
	`legacy_id` varchar(255),
	`created_at` timestamp,
	`updated_at` timestamp,
	`deleted_at` timestamp,
	CONSTRAINT `activities_id` PRIMARY KEY(`id`),
	CONSTRAINT `activities_uuid_unique` UNIQUE(`uuid`)
);
--> statement-breakpoint
CREATE TABLE `activity_groups` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`activity_id` bigint unsigned NOT NULL,
	`group_id` bigint unsigned NOT NULL,
	CONSTRAINT `activity_groups_id` PRIMARY KEY(`id`),
	CONSTRAINT `activity_groups_unique` UNIQUE(`activity_id`,`group_id`)
);
--> statement-breakpoint
CREATE TABLE `activity_sections` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`activity_id` bigint unsigned NOT NULL,
	`section` varchar(255) NOT NULL,
	CONSTRAINT `activity_sections_id` PRIMARY KEY(`id`),
	CONSTRAINT `activity_sections_unique` UNIQUE(`activity_id`,`section`)
);
--> statement-breakpoint
CREATE TABLE `annual_fee_years` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`year` smallint unsigned NOT NULL,
	`amount` decimal(12,2) NOT NULL,
	`status` varchar(255) NOT NULL DEFAULT 'Active',
	`created_by` bigint unsigned,
	`updated_by` bigint unsigned,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `annual_fee_years_id` PRIMARY KEY(`id`),
	CONSTRAINT `annual_fee_years_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `annual_fee_years_year_unique` UNIQUE(`year`)
);
--> statement-breakpoint
CREATE TABLE `annual_fees` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`annual_fee_year_id` bigint unsigned NOT NULL,
	`student_id` bigint unsigned,
	`user_id` bigint unsigned,
	`person_type` varchar(255) NOT NULL,
	`section` varchar(255),
	`amount` decimal(12,2) NOT NULL,
	`paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`outstanding_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`status` varchar(255) NOT NULL DEFAULT 'Pending',
	`created_by` bigint unsigned,
	`legacy_id` varchar(255),
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `annual_fees_id` PRIMARY KEY(`id`),
	CONSTRAINT `annual_fees_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `annual_fees_student_unique` UNIQUE(`annual_fee_year_id`,`student_id`),
	CONSTRAINT `annual_fees_user_unique` UNIQUE(`annual_fee_year_id`,`user_id`)
);
--> statement-breakpoint
CREATE TABLE `app_sessions` (
	`id` char(64) NOT NULL,
	`user_id` bigint unsigned NOT NULL,
	`ip_address` varchar(45),
	`user_agent` varchar(255),
	`expires_at` timestamp NOT NULL,
	`created_at` timestamp,
	CONSTRAINT `app_sessions_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `attendance_records` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`activity_id` bigint unsigned NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`status` varchar(255) NOT NULL,
	`remarks` varchar(255),
	`marked_by` bigint unsigned,
	`marked_at` timestamp,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `attendance_records_id` PRIMARY KEY(`id`),
	CONSTRAINT `attendance_records_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `attendance_records_unique` UNIQUE(`activity_id`,`student_id`)
);
--> statement-breakpoint
CREATE TABLE `audit_logs` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`entity_type` varchar(255),
	`entity_id` varchar(255),
	`action` varchar(255) NOT NULL,
	`actor_user_id` bigint unsigned,
	`details` json,
	`created_at` timestamp,
	CONSTRAINT `audit_logs_id` PRIMARY KEY(`id`),
	CONSTRAINT `audit_logs_uuid_unique` UNIQUE(`uuid`)
);
--> statement-breakpoint
CREATE TABLE `badge_requests` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`request_id` varchar(255) NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`student_name` varchar(255) NOT NULL,
	`badge_id` bigint unsigned NOT NULL,
	`badge_name` varchar(255) NOT NULL,
	`status` varchar(255) NOT NULL DEFAULT 'requested',
	`certificate_number` varchar(255),
	`date_awarded` date,
	`certificate_path` varchar(255),
	`requested_by` bigint unsigned,
	`reviewed_by` bigint unsigned,
	`reviewed_at` timestamp,
	`review_note` varchar(255),
	`generated_by` bigint unsigned,
	`generated_at` timestamp,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `badge_requests_id` PRIMARY KEY(`id`),
	CONSTRAINT `badge_requests_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `badge_requests_request_id_unique` UNIQUE(`request_id`)
);
--> statement-breakpoint
CREATE TABLE `badges` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`badge_id` varchar(255) NOT NULL,
	`name` varchar(255) NOT NULL,
	`code` varchar(255) NOT NULL,
	`section` varchar(255),
	`description` text,
	`category` varchar(255) NOT NULL DEFAULT 'proficiency',
	`image_path` varchar(255),
	`certificate_template_id` bigint unsigned,
	`number_prefix` varchar(255),
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `badges_id` PRIMARY KEY(`id`),
	CONSTRAINT `badges_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `badges_badge_id_unique` UNIQUE(`badge_id`),
	CONSTRAINT `badges_code_unique` UNIQUE(`code`)
);
--> statement-breakpoint
CREATE TABLE `certificate_counters` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`counter_id` varchar(255) NOT NULL,
	`badge_id` bigint unsigned,
	`year` smallint unsigned NOT NULL,
	`last_number` int unsigned NOT NULL DEFAULT 0,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `certificate_counters_id` PRIMARY KEY(`id`),
	CONSTRAINT `certificate_counters_counter_id_unique` UNIQUE(`counter_id`)
);
--> statement-breakpoint
CREATE TABLE `certificate_templates` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`template_id` varchar(255) NOT NULL,
	`name` varchar(255) NOT NULL,
	`type` varchar(255) NOT NULL,
	`google_slide_id` varchar(255),
	`activity_id` bigint unsigned,
	`template_content` longtext,
	`active` boolean NOT NULL DEFAULT true,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `certificate_templates_id` PRIMARY KEY(`id`),
	CONSTRAINT `certificate_templates_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `certificate_templates_template_id_unique` UNIQUE(`template_id`)
);
--> statement-breakpoint
CREATE TABLE `certificates` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`cert_id` varchar(255) NOT NULL,
	`type` varchar(255) NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`student_name` varchar(255) NOT NULL,
	`title` varchar(255),
	`cert_number` varchar(255) NOT NULL,
	`id_card_no` varchar(255),
	`date_awarded` date NOT NULL,
	`path` varchar(255),
	`status` varchar(255) NOT NULL DEFAULT 'issued',
	`badge_id` bigint unsigned,
	`badge_name` varchar(255),
	`template_id` bigint unsigned,
	`activity_id` bigint unsigned,
	`badge_request_id` bigint unsigned,
	`created_by` bigint unsigned,
	`generated_by` bigint unsigned,
	`generated_at` timestamp,
	`verified_by` bigint unsigned,
	`verified_at` timestamp,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `certificates_id` PRIMARY KEY(`id`),
	CONSTRAINT `certificates_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `certificates_cert_id_unique` UNIQUE(`cert_id`),
	CONSTRAINT `certificates_cert_number_unique` UNIQUE(`cert_number`)
);
--> statement-breakpoint
CREATE TABLE `class_fees` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`activity_id` bigint unsigned NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`amount` decimal(12,2) NOT NULL,
	`paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`outstanding_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`status` varchar(255) NOT NULL DEFAULT 'Pending',
	`due_date` date,
	`voided_at` timestamp,
	`created_by` bigint unsigned,
	`legacy_id` varchar(255),
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `class_fees_id` PRIMARY KEY(`id`),
	CONSTRAINT `class_fees_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `class_fees_unique` UNIQUE(`activity_id`,`student_id`)
);
--> statement-breakpoint
CREATE TABLE `event_items` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`event_id` bigint unsigned NOT NULL,
	`name` varchar(255) NOT NULL,
	`description` text,
	`price` decimal(12,2) NOT NULL DEFAULT '0.00',
	`sizes` json,
	`size_chart` json,
	`size_guide` text,
	`stock` int unsigned,
	`max_per_registration` int unsigned NOT NULL DEFAULT 5,
	`active` boolean NOT NULL DEFAULT true,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `event_items_id` PRIMARY KEY(`id`),
	CONSTRAINT `event_items_uuid_unique` UNIQUE(`uuid`)
);
--> statement-breakpoint
CREATE TABLE `event_registration_items` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`event_registration_id` bigint unsigned NOT NULL,
	`event_item_id` bigint unsigned NOT NULL,
	`item_name` varchar(255) NOT NULL,
	`size` varchar(255),
	`quantity` int unsigned NOT NULL,
	`unit_price` decimal(12,2) NOT NULL,
	`total_amount` decimal(12,2) NOT NULL,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `event_registration_items_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `event_registrations` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`event_id` bigint unsigned NOT NULL,
	`student_id` bigint unsigned,
	`user_id` bigint unsigned,
	`registered_by` bigint unsigned,
	`status` varchar(255) NOT NULL DEFAULT 'registered',
	`payment_option` varchar(255) NOT NULL DEFAULT 'online',
	`fee_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`items_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`outstanding_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`payment_status` varchar(255) NOT NULL DEFAULT 'Pending',
	`notes` varchar(500),
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `event_registrations_id` PRIMARY KEY(`id`),
	CONSTRAINT `event_registrations_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `event_registrations_student_unique` UNIQUE(`event_id`,`student_id`),
	CONSTRAINT `event_registrations_user_unique` UNIQUE(`event_id`,`user_id`)
);
--> statement-breakpoint
CREATE TABLE `events` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`name` varchar(255) NOT NULL,
	`description` text,
	`location` varchar(255),
	`starts_at` datetime NOT NULL,
	`ends_at` datetime,
	`registration_closes_at` datetime,
	`fee` decimal(12,2) NOT NULL DEFAULT '0.00',
	`capacity` int unsigned,
	`sections` json,
	`status` varchar(255) NOT NULL DEFAULT 'draft',
	`created_by` bigint unsigned,
	`created_at` timestamp,
	`updated_at` timestamp,
	`deleted_at` timestamp,
	CONSTRAINT `events_id` PRIMARY KEY(`id`),
	CONSTRAINT `events_uuid_unique` UNIQUE(`uuid`)
);
--> statement-breakpoint
CREATE TABLE `group_assistant_leaders` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`group_id` bigint unsigned NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `group_assistant_leaders_id` PRIMARY KEY(`id`),
	CONSTRAINT `group_assistant_leaders_unique` UNIQUE(`group_id`,`student_id`)
);
--> statement-breakpoint
CREATE TABLE `group_leaders` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`group_id` bigint unsigned NOT NULL,
	`user_id` bigint unsigned NOT NULL,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `group_leaders_id` PRIMARY KEY(`id`),
	CONSTRAINT `group_leaders_unique` UNIQUE(`group_id`,`user_id`)
);
--> statement-breakpoint
CREATE TABLE `group_members` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`group_id` bigint unsigned NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `group_members_id` PRIMARY KEY(`id`),
	CONSTRAINT `group_members_unique` UNIQUE(`group_id`,`student_id`)
);
--> statement-breakpoint
CREATE TABLE `groups` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`name` varchar(255) NOT NULL,
	`type` varchar(255),
	`section` varchar(255),
	`owner_id` bigint unsigned,
	`status` varchar(255) NOT NULL DEFAULT 'Active',
	`legacy_id` varchar(255),
	`created_at` timestamp,
	`updated_at` timestamp,
	`deleted_at` timestamp,
	CONSTRAINT `groups_id` PRIMARY KEY(`id`),
	CONSTRAINT `groups_uuid_unique` UNIQUE(`uuid`)
);
--> statement-breakpoint
CREATE TABLE `leadership_records` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`patrol_or_six` varchar(255) NOT NULL,
	`troop_or_group` varchar(255) NOT NULL,
	`start_date` date NOT NULL,
	`end_date` date,
	`certificate_id` bigint unsigned,
	`created_by` bigint unsigned,
	`updated_by` bigint unsigned,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `leadership_records_id` PRIMARY KEY(`id`),
	CONSTRAINT `leadership_records_uuid_unique` UNIQUE(`uuid`)
);
--> statement-breakpoint
CREATE TABLE `notifications` (
	`id` char(36) NOT NULL,
	`type` varchar(255) NOT NULL,
	`notifiable_type` varchar(255) NOT NULL,
	`notifiable_id` bigint unsigned NOT NULL,
	`data` text NOT NULL,
	`read_at` timestamp,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `notifications_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `parent_student_links` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`parent_user_id` bigint unsigned NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`status` varchar(255) NOT NULL DEFAULT 'pending',
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `parent_student_links_id` PRIMARY KEY(`id`),
	CONSTRAINT `parent_student_links_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `parent_student_links_unique` UNIQUE(`parent_user_id`,`student_id`)
);
--> statement-breakpoint
CREATE TABLE `payment_proofs` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`payment_id` bigint unsigned NOT NULL,
	`disk` varchar(255) NOT NULL,
	`path` varchar(255) NOT NULL,
	`original_filename` varchar(255),
	`mime_type` varchar(255),
	`file_size` bigint unsigned,
	`uploaded_by` bigint unsigned,
	`uploaded_at` timestamp,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `payment_proofs_id` PRIMARY KEY(`id`),
	CONSTRAINT `payment_proofs_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `payment_proofs_payment_id_unique` UNIQUE(`payment_id`)
);
--> statement-breakpoint
CREATE TABLE `payments` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`payable_type` varchar(255) NOT NULL,
	`payable_id` bigint unsigned NOT NULL,
	`student_id` bigint unsigned,
	`amount` decimal(12,2) NOT NULL,
	`method` varchar(255) NOT NULL,
	`source` varchar(255),
	`submitted_by` bigint unsigned,
	`submitted_at` timestamp,
	`accepted_by` bigint unsigned,
	`status` varchar(255) NOT NULL,
	`verified_by` bigint unsigned,
	`verified_at` timestamp,
	`rejection_reason` varchar(255),
	`legacy_id` varchar(255),
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `payments_id` PRIMARY KEY(`id`),
	CONSTRAINT `payments_uuid_unique` UNIQUE(`uuid`)
);
--> statement-breakpoint
CREATE TABLE `purchase_items` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`purchase_id` bigint unsigned NOT NULL,
	`shop_item_id` bigint unsigned NOT NULL,
	`item_name_snapshot` varchar(255) NOT NULL,
	`quantity` int unsigned NOT NULL,
	`unit_price` decimal(12,2) NOT NULL,
	`total_amount` decimal(12,2) NOT NULL,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `purchase_items_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `purchases` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`created_by` bigint unsigned,
	`total_amount` decimal(12,2) NOT NULL,
	`paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`outstanding_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
	`payment_status` varchar(255) NOT NULL DEFAULT 'Pending',
	`purchase_status` varchar(255) NOT NULL DEFAULT 'PendingPayment',
	`stock_decremented` boolean NOT NULL DEFAULT false,
	`delivered_by` bigint unsigned,
	`delivered_at` timestamp,
	`recipient` varchar(255),
	`legacy_id` varchar(255),
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `purchases_id` PRIMARY KEY(`id`),
	CONSTRAINT `purchases_uuid_unique` UNIQUE(`uuid`)
);
--> statement-breakpoint
CREATE TABLE `rate_limits` (
	`key` varchar(191) NOT NULL,
	`hits` int NOT NULL DEFAULT 0,
	`resets_at` timestamp NOT NULL,
	CONSTRAINT `rate_limits_key` PRIMARY KEY(`key`)
);
--> statement-breakpoint
CREATE TABLE `rover_attendance_records` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`activity_id` bigint unsigned NOT NULL,
	`student_id` bigint unsigned NOT NULL,
	`status` varchar(255) NOT NULL,
	`is_required` boolean NOT NULL DEFAULT true,
	`remarks` varchar(255),
	`marked_by` bigint unsigned,
	`marked_at` timestamp,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `rover_attendance_records_id` PRIMARY KEY(`id`),
	CONSTRAINT `rover_attendance_records_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `rover_attendance_records_unique` UNIQUE(`activity_id`,`student_id`)
);
--> statement-breakpoint
CREATE TABLE `settings` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`key` varchar(255) NOT NULL,
	`value` text,
	`updated_by` bigint unsigned,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `settings_id` PRIMARY KEY(`id`),
	CONSTRAINT `settings_key_unique` UNIQUE(`key`)
);
--> statement-breakpoint
CREATE TABLE `shop_items` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`name` varchar(255) NOT NULL,
	`description` text,
	`price` decimal(12,2) NOT NULL,
	`image_path` varchar(255),
	`status` varchar(255) NOT NULL DEFAULT 'Active',
	`stock_qty` int unsigned NOT NULL DEFAULT 0,
	`created_by` bigint unsigned,
	`legacy_id` varchar(255),
	`created_at` timestamp,
	`updated_at` timestamp,
	`deleted_at` timestamp,
	CONSTRAINT `shop_items_id` PRIMARY KEY(`id`),
	CONSTRAINT `shop_items_uuid_unique` UNIQUE(`uuid`)
);
--> statement-breakpoint
CREATE TABLE `stock_movements` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`shop_item_id` bigint unsigned NOT NULL,
	`type` varchar(255) NOT NULL,
	`quantity` int unsigned NOT NULL,
	`reference_type` varchar(255),
	`reference_id` bigint unsigned,
	`actor_user_id` bigint unsigned,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `stock_movements_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `students` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`index_number` varchar(255) NOT NULL,
	`name` varchar(255) NOT NULL,
	`national_id` varchar(255) NOT NULL,
	`email` varchar(255),
	`photo_path` varchar(255),
	`gender` varchar(255),
	`permanent_address` varchar(255),
	`present_address` varchar(255),
	`date_of_birth` date,
	`parent_name` varchar(255),
	`primary_mobile` varchar(255),
	`secondary_mobile` varchar(255),
	`section` varchar(255) NOT NULL,
	`class_name` varchar(255),
	`patrol` varchar(255),
	`status` varchar(255) NOT NULL DEFAULT 'pending',
	`verified_at` timestamp,
	`verified_by` bigint unsigned,
	`legacy_id` varchar(255),
	`created_at` timestamp,
	`updated_at` timestamp,
	`deleted_at` timestamp,
	CONSTRAINT `students_id` PRIMARY KEY(`id`),
	CONSTRAINT `students_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `students_index_number_unique` UNIQUE(`index_number`),
	CONSTRAINT `students_national_id_unique` UNIQUE(`national_id`)
);
--> statement-breakpoint
CREATE TABLE `user_permissions` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`user_id` bigint unsigned NOT NULL,
	`permission` varchar(255) NOT NULL,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `user_permissions_id` PRIMARY KEY(`id`),
	CONSTRAINT `user_permissions_user_id_permission_unique` UNIQUE(`user_id`,`permission`)
);
--> statement-breakpoint
CREATE TABLE `user_roles` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`user_id` bigint unsigned NOT NULL,
	`role` varchar(255) NOT NULL,
	`created_at` timestamp,
	`updated_at` timestamp,
	CONSTRAINT `user_roles_id` PRIMARY KEY(`id`),
	CONSTRAINT `user_roles_user_id_role_unique` UNIQUE(`user_id`,`role`)
);
--> statement-breakpoint
CREATE TABLE `users` (
	`id` bigint unsigned AUTO_INCREMENT NOT NULL,
	`uuid` char(36) NOT NULL,
	`name` varchar(255) NOT NULL,
	`national_id` varchar(255) NOT NULL,
	`email` varchar(255),
	`password` varchar(255) NOT NULL,
	`status` varchar(255) NOT NULL DEFAULT 'inactive',
	`student_id` bigint unsigned,
	`verified_at` timestamp,
	`verified_by` bigint unsigned,
	`signature_path` varchar(255),
	`avatar_path` varchar(255),
	`email_notifications_enabled` boolean NOT NULL DEFAULT true,
	`telegram_notifications_enabled` boolean NOT NULL DEFAULT false,
	`telegram_chat_id` varchar(255),
	`telegram_connect_token` varchar(64),
	`telegram_connect_token_expires_at` timestamp,
	`legacy_pin_hash` varchar(255),
	`legacy_pin_salt` varchar(255),
	`last_login_at` timestamp,
	`legacy_id` varchar(255),
	`remember_token` varchar(100),
	`created_at` timestamp,
	`updated_at` timestamp,
	`deleted_at` timestamp,
	CONSTRAINT `users_id` PRIMARY KEY(`id`),
	CONSTRAINT `users_uuid_unique` UNIQUE(`uuid`),
	CONSTRAINT `users_national_id_unique` UNIQUE(`national_id`),
	CONSTRAINT `users_email_unique` UNIQUE(`email`),
	CONSTRAINT `users_student_id_unique` UNIQUE(`student_id`)
);
--> statement-breakpoint
ALTER TABLE `activities` ADD CONSTRAINT `activities_certificate_template_id_certificate_templates_id_fk` FOREIGN KEY (`certificate_template_id`) REFERENCES `certificate_templates`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `activities` ADD CONSTRAINT `activities_created_by_users_id_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `activity_groups` ADD CONSTRAINT `activity_groups_activity_id_activities_id_fk` FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `activity_groups` ADD CONSTRAINT `activity_groups_group_id_groups_id_fk` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `activity_sections` ADD CONSTRAINT `activity_sections_activity_id_activities_id_fk` FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `annual_fee_years` ADD CONSTRAINT `annual_fee_years_created_by_users_id_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `annual_fee_years` ADD CONSTRAINT `annual_fee_years_updated_by_users_id_fk` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `annual_fees` ADD CONSTRAINT `annual_fees_annual_fee_year_id_annual_fee_years_id_fk` FOREIGN KEY (`annual_fee_year_id`) REFERENCES `annual_fee_years`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `annual_fees` ADD CONSTRAINT `annual_fees_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `annual_fees` ADD CONSTRAINT `annual_fees_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `annual_fees` ADD CONSTRAINT `annual_fees_created_by_users_id_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `app_sessions` ADD CONSTRAINT `app_sessions_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `attendance_records` ADD CONSTRAINT `attendance_records_activity_id_activities_id_fk` FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `attendance_records` ADD CONSTRAINT `attendance_records_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `attendance_records` ADD CONSTRAINT `attendance_records_marked_by_users_id_fk` FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_actor_user_id_users_id_fk` FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `badge_requests` ADD CONSTRAINT `badge_requests_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `badge_requests` ADD CONSTRAINT `badge_requests_badge_id_badges_id_fk` FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `badge_requests` ADD CONSTRAINT `badge_requests_requested_by_users_id_fk` FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `badge_requests` ADD CONSTRAINT `badge_requests_reviewed_by_users_id_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `badge_requests` ADD CONSTRAINT `badge_requests_generated_by_users_id_fk` FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `badges` ADD CONSTRAINT `badges_certificate_template_id_certificate_templates_id_fk` FOREIGN KEY (`certificate_template_id`) REFERENCES `certificate_templates`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `certificate_counters` ADD CONSTRAINT `certificate_counters_badge_id_badges_id_fk` FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `certificates` ADD CONSTRAINT `certificates_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `certificates` ADD CONSTRAINT `certificates_badge_id_badges_id_fk` FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `certificates` ADD CONSTRAINT `certificates_template_id_certificate_templates_id_fk` FOREIGN KEY (`template_id`) REFERENCES `certificate_templates`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `certificates` ADD CONSTRAINT `certificates_activity_id_activities_id_fk` FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `certificates` ADD CONSTRAINT `certificates_badge_request_id_badge_requests_id_fk` FOREIGN KEY (`badge_request_id`) REFERENCES `badge_requests`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `certificates` ADD CONSTRAINT `certificates_created_by_users_id_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `certificates` ADD CONSTRAINT `certificates_generated_by_users_id_fk` FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `certificates` ADD CONSTRAINT `certificates_verified_by_users_id_fk` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `class_fees` ADD CONSTRAINT `class_fees_activity_id_activities_id_fk` FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `class_fees` ADD CONSTRAINT `class_fees_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `class_fees` ADD CONSTRAINT `class_fees_created_by_users_id_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `event_items` ADD CONSTRAINT `event_items_event_id_events_id_fk` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `event_registration_items` ADD CONSTRAINT `event_registration_items_event_item_id_event_items_id_fk` FOREIGN KEY (`event_item_id`) REFERENCES `event_items`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `event_registration_items` ADD CONSTRAINT `event_reg_items_registration_fk` FOREIGN KEY (`event_registration_id`) REFERENCES `event_registrations`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `event_registrations` ADD CONSTRAINT `event_registrations_event_id_events_id_fk` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `event_registrations` ADD CONSTRAINT `event_registrations_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `event_registrations` ADD CONSTRAINT `event_registrations_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `event_registrations` ADD CONSTRAINT `event_registrations_registered_by_users_id_fk` FOREIGN KEY (`registered_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `events` ADD CONSTRAINT `events_created_by_users_id_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `group_assistant_leaders` ADD CONSTRAINT `group_assistant_leaders_group_id_groups_id_fk` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `group_assistant_leaders` ADD CONSTRAINT `group_assistant_leaders_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `group_leaders` ADD CONSTRAINT `group_leaders_group_id_groups_id_fk` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `group_leaders` ADD CONSTRAINT `group_leaders_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `group_members` ADD CONSTRAINT `group_members_group_id_groups_id_fk` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `group_members` ADD CONSTRAINT `group_members_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `groups` ADD CONSTRAINT `groups_owner_id_users_id_fk` FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `leadership_records` ADD CONSTRAINT `leadership_records_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `leadership_records` ADD CONSTRAINT `leadership_records_certificate_id_certificates_id_fk` FOREIGN KEY (`certificate_id`) REFERENCES `certificates`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `leadership_records` ADD CONSTRAINT `leadership_records_created_by_users_id_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `leadership_records` ADD CONSTRAINT `leadership_records_updated_by_users_id_fk` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `parent_student_links` ADD CONSTRAINT `parent_student_links_parent_user_id_users_id_fk` FOREIGN KEY (`parent_user_id`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `parent_student_links` ADD CONSTRAINT `parent_student_links_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `payment_proofs` ADD CONSTRAINT `payment_proofs_payment_id_payments_id_fk` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `payment_proofs` ADD CONSTRAINT `payment_proofs_uploaded_by_users_id_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `payments` ADD CONSTRAINT `payments_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `payments` ADD CONSTRAINT `payments_submitted_by_users_id_fk` FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `payments` ADD CONSTRAINT `payments_accepted_by_users_id_fk` FOREIGN KEY (`accepted_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `payments` ADD CONSTRAINT `payments_verified_by_users_id_fk` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `purchase_items` ADD CONSTRAINT `purchase_items_purchase_id_purchases_id_fk` FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `purchase_items` ADD CONSTRAINT `purchase_items_shop_item_id_shop_items_id_fk` FOREIGN KEY (`shop_item_id`) REFERENCES `shop_items`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_created_by_users_id_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_delivered_by_users_id_fk` FOREIGN KEY (`delivered_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `rover_attendance_records` ADD CONSTRAINT `rover_attendance_records_activity_id_activities_id_fk` FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `rover_attendance_records` ADD CONSTRAINT `rover_attendance_records_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `rover_attendance_records` ADD CONSTRAINT `rover_attendance_records_marked_by_users_id_fk` FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `settings` ADD CONSTRAINT `settings_updated_by_users_id_fk` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `shop_items` ADD CONSTRAINT `shop_items_created_by_users_id_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `stock_movements` ADD CONSTRAINT `stock_movements_shop_item_id_shop_items_id_fk` FOREIGN KEY (`shop_item_id`) REFERENCES `shop_items`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `stock_movements` ADD CONSTRAINT `stock_movements_actor_user_id_users_id_fk` FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `students` ADD CONSTRAINT `students_verified_by_users_id_fk` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `user_permissions` ADD CONSTRAINT `user_permissions_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `user_roles` ADD CONSTRAINT `user_roles_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `activities_date_index` ON `activities` (`date`);--> statement-breakpoint
CREATE INDEX `annual_fees_status_index` ON `annual_fees` (`status`);--> statement-breakpoint
CREATE INDEX `app_sessions_user_id_index` ON `app_sessions` (`user_id`);--> statement-breakpoint
CREATE INDEX `app_sessions_expires_at_index` ON `app_sessions` (`expires_at`);--> statement-breakpoint
CREATE INDEX `attendance_records_status_index` ON `attendance_records` (`status`);--> statement-breakpoint
CREATE INDEX `audit_logs_action_index` ON `audit_logs` (`action`);--> statement-breakpoint
CREATE INDEX `audit_logs_created_at_index` ON `audit_logs` (`created_at`);--> statement-breakpoint
CREATE INDEX `audit_logs_entity_index` ON `audit_logs` (`entity_type`,`entity_id`);--> statement-breakpoint
CREATE INDEX `badge_requests_status_index` ON `badge_requests` (`status`);--> statement-breakpoint
CREATE INDEX `certificate_templates_type_index` ON `certificate_templates` (`type`);--> statement-breakpoint
CREATE INDEX `certificates_type_index` ON `certificates` (`type`);--> statement-breakpoint
CREATE INDEX `certificates_status_index` ON `certificates` (`status`);--> statement-breakpoint
CREATE INDEX `class_fees_status_index` ON `class_fees` (`status`);--> statement-breakpoint
CREATE INDEX `event_registrations_status_index` ON `event_registrations` (`status`);--> statement-breakpoint
CREATE INDEX `events_starts_at_index` ON `events` (`starts_at`);--> statement-breakpoint
CREATE INDEX `events_status_index` ON `events` (`status`);--> statement-breakpoint
CREATE INDEX `notifications_notifiable_index` ON `notifications` (`notifiable_type`,`notifiable_id`);--> statement-breakpoint
CREATE INDEX `parent_student_links_status_index` ON `parent_student_links` (`status`);--> statement-breakpoint
CREATE INDEX `payments_payable_index` ON `payments` (`payable_type`,`payable_id`);--> statement-breakpoint
CREATE INDEX `payments_status_index` ON `payments` (`status`);--> statement-breakpoint
CREATE INDEX `payments_submitted_at_index` ON `payments` (`submitted_at`);--> statement-breakpoint
CREATE INDEX `purchases_payment_status_index` ON `purchases` (`payment_status`);--> statement-breakpoint
CREATE INDEX `purchases_purchase_status_index` ON `purchases` (`purchase_status`);--> statement-breakpoint
CREATE INDEX `shop_items_status_index` ON `shop_items` (`status`);--> statement-breakpoint
CREATE INDEX `students_section_index` ON `students` (`section`);--> statement-breakpoint
CREATE INDEX `students_status_index` ON `students` (`status`);--> statement-breakpoint
CREATE INDEX `students_legacy_id_index` ON `students` (`legacy_id`);--> statement-breakpoint
CREATE INDEX `users_status_index` ON `users` (`status`);--> statement-breakpoint
CREATE INDEX `users_legacy_id_index` ON `users` (`legacy_id`);