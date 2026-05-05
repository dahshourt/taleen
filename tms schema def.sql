SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `application_impact` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`impacts_id`  bigint UNSIGNED NOT NULL ,
`application_id`  bigint UNSIGNED NOT NULL ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `application_impact_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `application_impact_impacts_id_foreign` FOREIGN KEY (`impacts_id`) REFERENCES `deployment_impacts` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `application_impact_impacts_id_foreign` (`impacts_id`) USING BTREE ,
INDEX `application_impact_application_id_foreign` (`application_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
ALTER TABLE `applications` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `applications` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `applications` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `applications` MODIFY COLUMN `workflow_type_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `applications` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `associated_crs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `associated_crs` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `associated_crs` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `associated_crs` MODIFY COLUMN `workflow_type_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `associated_crs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `attachements_crs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `attachements_crs` MODIFY COLUMN `user_id`  bigint UNSIGNED NOT NULL AFTER `file`;
ALTER TABLE `attachements_crs` MODIFY COLUMN `cr_id`  bigint UNSIGNED NOT NULL AFTER `user_id`;
ALTER TABLE `attachements_crs` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `cr_id`;
ALTER TABLE `attachements_crs` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `attachements_crs` MODIFY COLUMN `visible`  tinyint NOT NULL DEFAULT 1 AFTER `updated_at`;
ALTER TABLE `attachements_crs` MODIFY COLUMN `description`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `visible`;
ALTER TABLE `attachements_crs` MODIFY COLUMN `attachment`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `description`;
ALTER TABLE `attachements_crs` MODIFY COLUMN `file_name`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `attachment`;
ALTER TABLE `attachements_crs` MODIFY COLUMN `size`  int NULL DEFAULT NULL AFTER `file_name`;
ALTER TABLE `attachements_crs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `cab_cr_users` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `cab_cr_users` MODIFY COLUMN `user_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `id`;
ALTER TABLE `cab_cr_users` MODIFY COLUMN `cab_cr_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `cab_cr_users` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `cab_cr_users` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `cab_cr_users` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `cab_crs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `cab_crs` MODIFY COLUMN `cr_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `id`;
ALTER TABLE `cab_crs` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `cab_crs` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `cab_crs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `categories` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `categories` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `categories` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `categories` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `change_request` DROP INDEX `change_request_rejection_reason_id_foreign`;
ALTER TABLE `change_request` DROP FOREIGN KEY `change_request_ibfk_13`;
ALTER TABLE `change_request` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `change_request` MODIFY COLUMN `developer_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `change_request` MODIFY COLUMN `tester_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `developer_id`;
ALTER TABLE `change_request` MODIFY COLUMN `designer_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `tester_id`;
ALTER TABLE `change_request` MODIFY COLUMN `requester_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `designer_id`;
ALTER TABLE `change_request` MODIFY COLUMN `design_duration`  int NULL DEFAULT NULL AFTER `requester_id`;
ALTER TABLE `change_request` MODIFY COLUMN `start_design_time`  datetime NULL DEFAULT NULL AFTER `design_duration`;
ALTER TABLE `change_request` MODIFY COLUMN `end_design_time`  datetime NULL DEFAULT NULL AFTER `start_design_time`;
ALTER TABLE `change_request` MODIFY COLUMN `develop_duration`  int NULL DEFAULT NULL AFTER `end_design_time`;
ALTER TABLE `change_request` MODIFY COLUMN `start_develop_time`  datetime NULL DEFAULT NULL AFTER `develop_duration`;
ALTER TABLE `change_request` MODIFY COLUMN `end_develop_time`  datetime NULL DEFAULT NULL AFTER `start_develop_time`;
ALTER TABLE `change_request` MODIFY COLUMN `test_duration`  int NULL DEFAULT NULL AFTER `end_develop_time`;
ALTER TABLE `change_request` MODIFY COLUMN `start_test_time`  datetime NULL DEFAULT NULL AFTER `test_duration`;
ALTER TABLE `change_request` MODIFY COLUMN `end_test_time`  datetime NULL DEFAULT NULL AFTER `start_test_time`;
ALTER TABLE `change_request` MODIFY COLUMN `depend_cr_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `end_test_time`;
ALTER TABLE `change_request` MODIFY COLUMN `requester_name`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `depend_cr_id`;
ALTER TABLE `change_request` MODIFY COLUMN `requester_email`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `requester_name`;
ALTER TABLE `change_request` MODIFY COLUMN `requester_unit`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `requester_email`;
ALTER TABLE `change_request` MODIFY COLUMN `requester_division_manager`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `requester_unit`;
ALTER TABLE `change_request` MODIFY COLUMN `requester_department`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `requester_division_manager`;
ALTER TABLE `change_request` MODIFY COLUMN `application_name`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `requester_department`;
ALTER TABLE `change_request` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `testable`;
ALTER TABLE `change_request` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `change_request` MODIFY COLUMN `category_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `change_request` MODIFY COLUMN `priority_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `category_id`;
ALTER TABLE `change_request` MODIFY COLUMN `unit_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `priority_id`;
ALTER TABLE `change_request` MODIFY COLUMN `department_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `unit_id`;
ALTER TABLE `change_request` MODIFY COLUMN `application_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `department_id`;
ALTER TABLE `change_request` MODIFY COLUMN `workflow_type_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `application_id`;
ALTER TABLE `change_request` MODIFY COLUMN `division_manager`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `workflow_type_id`;
ALTER TABLE `change_request` MODIFY COLUMN `creator_mobile_number`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `division_manager`;
ALTER TABLE `change_request` ADD COLUMN `calendar`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' AFTER `creator_mobile_number`;
ALTER TABLE `change_request` ADD COLUMN `CR_duration`  int NULL DEFAULT NULL AFTER `calendar`;
ALTER TABLE `change_request` ADD COLUMN `chnage_requester_id`  bigint NULL DEFAULT NULL AFTER `CR_duration`;
ALTER TABLE `change_request` ADD COLUMN `start_CR_time`  datetime NULL DEFAULT NULL AFTER `chnage_requester_id`;
ALTER TABLE `change_request` ADD COLUMN `end_CR_time`  datetime NULL DEFAULT NULL AFTER `start_CR_time`;
ALTER TABLE `change_request` DROP COLUMN `helpdesk_id`;
ALTER TABLE `change_request` DROP COLUMN `vendor_id`;
ALTER TABLE `change_request` DROP COLUMN `man_days`;
ALTER TABLE `change_request` DROP COLUMN `release`;
ALTER TABLE `change_request` DROP COLUMN `associated`;
ALTER TABLE `change_request` DROP COLUMN `depend_on`;
ALTER TABLE `change_request` DROP COLUMN `analysis_feedback`;
ALTER TABLE `change_request` DROP COLUMN `technical_feedback`;
ALTER TABLE `change_request` DROP COLUMN `approval`;
ALTER TABLE `change_request` DROP COLUMN `need_design`;
ALTER TABLE `change_request` DROP COLUMN `impacted_services`;
ALTER TABLE `change_request` DROP COLUMN `impact_during_deployment`;
ALTER TABLE `change_request` DROP COLUMN `release_delivery_date`;
ALTER TABLE `change_request` DROP COLUMN `release_name`;
ALTER TABLE `change_request` DROP COLUMN `release_receiving_date`;
ALTER TABLE `change_request` DROP COLUMN `need_iot_e2e_testing`;
ALTER TABLE `change_request` DROP COLUMN `te_testing_date`;
ALTER TABLE `change_request` DROP COLUMN `uat_date`;
ALTER TABLE `change_request` DROP COLUMN `cost`;
ALTER TABLE `change_request` DROP COLUMN `uat_duration`;
ALTER TABLE `change_request` DROP COLUMN `parent_id`;
ALTER TABLE `change_request` DROP COLUMN `rejection_reason_id`;
ALTER TABLE `change_request` DROP COLUMN `cr_member`;
ALTER TABLE `change_request` DROP COLUMN `need_ux_ui`;
ALTER TABLE `change_request` DROP COLUMN `cr_workload`;
ALTER TABLE `change_request` DROP COLUMN `rtm_member`;
ALTER TABLE `change_request` DROP COLUMN `cr_no`;
ALTER TABLE `change_request` DROP COLUMN `need_down_time`;
ALTER TABLE `change_request` DROP COLUMN `deployment_impact`;
ALTER TABLE `change_request` DROP COLUMN `business_feedback`;
ALTER TABLE `change_request` DROP COLUMN `sanity_spoc`;
ALTER TABLE `change_request` DROP COLUMN `test_reachability`;
ALTER TABLE `change_request` DROP COLUMN `test_with_business`;
ALTER TABLE `change_request` DROP COLUMN `cr_data`;
ALTER TABLE `change_request` DROP COLUMN `type_of_impact_during_deployment`;
ALTER TABLE `change_request` DROP COLUMN `sanity_spoc_phone_no`;
ALTER TABLE `change_request` DROP COLUMN `rtm_feedback`;
ALTER TABLE `change_request` DROP COLUMN `postpone`;
ALTER TABLE `change_request` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `change_request_custom_fields` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `change_request_custom_fields` MODIFY COLUMN `cr_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `change_request_custom_fields` MODIFY COLUMN `custom_field_id`  bigint UNSIGNED NOT NULL AFTER `cr_id`;
ALTER TABLE `change_request_custom_fields` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `custom_field_value`;
ALTER TABLE `change_request_custom_fields` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `change_request_custom_fields` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `cr_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `old_status_id`  bigint UNSIGNED NOT NULL AFTER `cr_id`;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `new_status_id`  bigint UNSIGNED NOT NULL AFTER `old_status_id`;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `user_id`  bigint UNSIGNED NOT NULL AFTER `new_status_id`;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `assignment_user_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `sla`  int NOT NULL DEFAULT 0 COMMENT 'Integer value in days' AFTER `assignment_user_id`;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `sla_dif`  int NOT NULL DEFAULT 0 COMMENT 'Integer value in days' AFTER `sla`;
ALTER TABLE `change_request_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
CREATE TABLE `change_request_technical_team` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`cr_id`  bigint UNSIGNED NOT NULL ,
`technical_team_id`  bigint UNSIGNED NOT NULL ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `change_request_technical_team_cr_id_foreign` FOREIGN KEY (`cr_id`) REFERENCES `change_request` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `change_request_technical_team_technical_team_id_foreign` FOREIGN KEY (`technical_team_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `change_request_technical_team_cr_id_foreign` (`cr_id`) USING BTREE ,
INDEX `change_request_technical_team_technical_team_id_foreign` (`technical_team_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
ALTER TABLE `custom_fields` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `custom_fields` MODIFY COLUMN `class`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `label`;
ALTER TABLE `custom_fields` MODIFY COLUMN `default_value`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `class`;
ALTER TABLE `custom_fields` MODIFY COLUMN `related_table`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `default_value`;
ALTER TABLE `custom_fields` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `custom_fields` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `custom_fields` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `form_type`  enum('1','2','3','4','5','6','7') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `group_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `form_type`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `wf_type_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `group_id`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `custom_field_id`  bigint UNSIGNED NOT NULL AFTER `wf_type_id`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `sort`  int NULL DEFAULT NULL AFTER `custom_field_id`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `validation_type_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `enable`  tinyint UNSIGNED NULL DEFAULT 1 AFTER `validation_type_id`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `status_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `enable`;
ALTER TABLE `custom_fields_groups_type` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
CREATE TABLE `defect_attachments` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`defect_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`user_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`file`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `defect_attachments_defect_id_foreign` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `defect_attachments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `defect_attachments_defect_id_foreign` (`defect_id`) USING BTREE ,
INDEX `defect_attachments_user_id_foreign` (`user_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
CREATE TABLE `defect_comments` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`defect_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`user_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`comment`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `defect_comments_defect_id_foreign` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `defect_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `defect_comments_defect_id_foreign` (`defect_id`) USING BTREE ,
INDEX `defect_comments_user_id_foreign` (`user_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
CREATE TABLE `defect_logs` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`defect_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`user_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`log_text`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `defect_logs_defect_id_foreign` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `defect_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `defect_logs_defect_id_foreign` (`defect_id`) USING BTREE ,
INDEX `defect_logs_user_id_foreign` (`user_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
CREATE TABLE `defect_statuses` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`defect_id`  bigint UNSIGNED NOT NULL ,
`previous_status_id`  bigint UNSIGNED NOT NULL ,
`new_status_id`  bigint UNSIGNED NOT NULL ,
`user_id`  bigint UNSIGNED NOT NULL ,
`active`  enum('0','1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `defect_statuses_defect_id_foreign` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `defect_statuses_new_status_id_foreign` FOREIGN KEY (`new_status_id`) REFERENCES `statuses` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `defect_statuses_previous_status_id_foreign` FOREIGN KEY (`previous_status_id`) REFERENCES `statuses` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `defect_statuses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `defect_statuses_defect_id_foreign` (`defect_id`) USING BTREE ,
INDEX `defect_statuses_previous_status_id_foreign` (`previous_status_id`) USING BTREE ,
INDEX `defect_statuses_new_status_id_foreign` (`new_status_id`) USING BTREE ,
INDEX `defect_statuses_user_id_foreign` (`user_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
CREATE TABLE `defects` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`cr_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`subject`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL ,
`group_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`status_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`created_by`  bigint UNSIGNED NULL DEFAULT NULL ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `defects_cr_id_foreign` FOREIGN KEY (`cr_id`) REFERENCES `change_request` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `defects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `defects_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `defects_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `defects_cr_id_foreign` (`cr_id`) USING BTREE ,
INDEX `defects_created_by_foreign` (`created_by`) USING BTREE ,
INDEX `defects_group_id_foreign` (`group_id`) USING BTREE ,
INDEX `defects_status_id_foreign` (`status_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
ALTER TABLE `departments` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `departments` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `departments` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `departments` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `depend_workflow_status` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `depend_workflow_status` MODIFY COLUMN `to_status_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `depend_workflow_status` MODIFY COLUMN `depend_status_id`  bigint UNSIGNED NOT NULL AFTER `to_status_id`;
ALTER TABLE `depend_workflow_status` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `depend_workflow_status` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `depend_workflow_status` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `deployment_impacts` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `deployment_impacts` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `deployment_impacts` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `deployment_impacts` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `division_managers` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `division_managers` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `division_managers` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `division_managers` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `failed_jobs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `failed_jobs` MODIFY COLUMN `failed_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `exception`;
ALTER TABLE `failed_jobs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `fields` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `fields` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `required`;
ALTER TABLE `fields` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `fields` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `group_applications` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `group_applications` MODIFY COLUMN `application_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `group_applications` MODIFY COLUMN `group_id`  bigint UNSIGNED NOT NULL AFTER `application_id`;
ALTER TABLE `group_applications` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `group_id`;
ALTER TABLE `group_applications` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `group_applications` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `group_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `group_statuses` MODIFY COLUMN `status_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `group_statuses` MODIFY COLUMN `group_id`  bigint UNSIGNED NOT NULL AFTER `status_id`;
ALTER TABLE `group_statuses` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `group_id`;
ALTER TABLE `group_statuses` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `group_statuses` MODIFY COLUMN `type`  tinyint NOT NULL DEFAULT 1 AFTER `updated_at`;
ALTER TABLE `group_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `groups` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `groups` MODIFY COLUMN `parent_id`  int NULL DEFAULT NULL AFTER `description`;
ALTER TABLE `groups` MODIFY COLUMN `head_group_name`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `parent_id`;
ALTER TABLE `groups` MODIFY COLUMN `head_group_email`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `head_group_name`;
ALTER TABLE `groups` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `groups` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `groups` MODIFY COLUMN `man_power`  int NULL DEFAULT NULL COMMENT 'Integer value in hours' AFTER `updated_at`;
ALTER TABLE `groups` ADD COLUMN `technical_team`  enum('0','1','') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' COMMENT '0:not technical,1\"technicalteam' AFTER `man_power`;
ALTER TABLE `groups` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `high_level_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `high_level_statuses` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `high_level_statuses` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `high_level_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `logs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `logs` MODIFY COLUMN `cr_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `logs` MODIFY COLUMN `user_id`  bigint UNSIGNED NOT NULL AFTER `cr_id`;
ALTER TABLE `logs` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `log_text`;
ALTER TABLE `logs` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `logs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `mail_templates` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `mail_templates` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `body`;
ALTER TABLE `mail_templates` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `mail_templates` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `migrations` MODIFY COLUMN `id`  int UNSIGNED NOT NULL FIRST ;
ALTER TABLE `migrations` MODIFY COLUMN `batch`  int NOT NULL AFTER `migration`;
ALTER TABLE `migrations` MODIFY COLUMN `id`  int UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `model_has_permissions` MODIFY COLUMN `permission_id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `model_has_permissions` MODIFY COLUMN `model_id`  bigint UNSIGNED NOT NULL AFTER `model_type`;
ALTER TABLE `model_has_roles` MODIFY COLUMN `role_id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `model_has_roles` MODIFY COLUMN `model_id`  bigint UNSIGNED NOT NULL AFTER `model_type`;
ALTER TABLE `module_rules` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `module_rules` MODIFY COLUMN `module_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `module_rules` MODIFY COLUMN `sort`  int NULL DEFAULT NULL AFTER `action_url`;
ALTER TABLE `module_rules` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `module_rules` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `module_rules` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `modules` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `modules` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `modules` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `modules` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `need_down_times` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `need_down_times` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `need_down_times` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `need_down_times` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `new_workflow` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `new_workflow` ADD COLUMN `same_time_from`  enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' AFTER `id`;
ALTER TABLE `new_workflow` ADD COLUMN `previous_status_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `same_time_from`;
ALTER TABLE `new_workflow` MODIFY COLUMN `from_status_id`  bigint UNSIGNED NOT NULL AFTER `previous_status_id`;
ALTER TABLE `new_workflow` MODIFY COLUMN `to_status_label`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `workflow_type`;
ALTER TABLE `new_workflow` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `to_status_label`;
ALTER TABLE `new_workflow` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `new_workflow` MODIFY COLUMN `type_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `new_workflow` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `new_workflow_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `new_workflow_statuses` MODIFY COLUMN `new_workflow_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `new_workflow_statuses` MODIFY COLUMN `to_status_id`  bigint UNSIGNED NOT NULL AFTER `new_workflow_id`;
ALTER TABLE `new_workflow_statuses` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `default_to_status`;
ALTER TABLE `new_workflow_statuses` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `new_workflow_statuses` ADD COLUMN `dependency_ids`  longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER `updated_at`;
ALTER TABLE `new_workflow_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `parents_crs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `parents_crs` MODIFY COLUMN `name`  int NULL DEFAULT NULL AFTER `id`;
ALTER TABLE `parents_crs` MODIFY COLUMN `application_name`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `parents_crs` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `parents_crs` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `parents_crs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `password_resets` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `token`;
ALTER TABLE `permissions` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `permissions` MODIFY COLUMN `parent_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `guard_name`;
ALTER TABLE `permissions` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `module`;
ALTER TABLE `permissions` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `permissions` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
CREATE TABLE `permissions_old` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`group_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`user_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`module_rule_id`  bigint UNSIGNED NOT NULL ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `permissions_old_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `permissions_old_ibfk_2` FOREIGN KEY (`module_rule_id`) REFERENCES `module_rules` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `permissions_old_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `permissions_module_rule_id_foreign` (`module_rule_id`) USING BTREE ,
INDEX `permissions_user_id_foreign` (`user_id`) USING BTREE ,
INDEX `permissions_group_id_foreign` (`group_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
ALTER TABLE `personal_access_tokens` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `personal_access_tokens` MODIFY COLUMN `tokenable_id`  bigint UNSIGNED NOT NULL AFTER `tokenable_type`;
ALTER TABLE `personal_access_tokens` MODIFY COLUMN `abilities`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `token`;
ALTER TABLE `personal_access_tokens` MODIFY COLUMN `last_used_at`  timestamp NULL DEFAULT NULL AFTER `abilities`;
ALTER TABLE `personal_access_tokens` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `last_used_at`;
ALTER TABLE `personal_access_tokens` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `personal_access_tokens` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `pivotusersroles` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `pivotusersroles` MODIFY COLUMN `report_to`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `pivotusersroles` MODIFY COLUMN `user_id`  bigint UNSIGNED NOT NULL AFTER `report_to`;
ALTER TABLE `pivotusersroles` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `pivotusersroles` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `pivotusersroles` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `priorities` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `priorities` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `priorities` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `priorities` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `rejection_reasons` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `rejection_reasons` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `rejection_reasons` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `rejection_reasons` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `release_logs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `release_logs` MODIFY COLUMN `release_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `release_logs` MODIFY COLUMN `user_id`  bigint UNSIGNED NOT NULL AFTER `release_id`;
ALTER TABLE `release_logs` MODIFY COLUMN `status_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `log_text`;
ALTER TABLE `release_logs` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `status_id`;
ALTER TABLE `release_logs` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `release_logs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `release_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `release_statuses` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `release_statuses` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `release_statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `releases` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `releases` MODIFY COLUMN `go_live_planned_date`  date NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `releases` MODIFY COLUMN `planned_start_iot_date`  date NULL DEFAULT NULL AFTER `go_live_planned_date`;
ALTER TABLE `releases` MODIFY COLUMN `planned_end_iot_date`  date NULL DEFAULT NULL AFTER `planned_start_iot_date`;
ALTER TABLE `releases` MODIFY COLUMN `planned_start_e2e_date`  date NULL DEFAULT NULL AFTER `planned_end_iot_date`;
ALTER TABLE `releases` MODIFY COLUMN `planned_end_e2e_date`  date NULL DEFAULT NULL AFTER `planned_start_e2e_date`;
ALTER TABLE `releases` MODIFY COLUMN `planned_start_uat_date`  date NULL DEFAULT NULL AFTER `planned_end_e2e_date`;
ALTER TABLE `releases` MODIFY COLUMN `planned_end_uat_date`  date NULL DEFAULT NULL AFTER `planned_start_uat_date`;
ALTER TABLE `releases` MODIFY COLUMN `planned_start_smoke_test_date`  date NULL DEFAULT NULL AFTER `planned_end_uat_date`;
ALTER TABLE `releases` MODIFY COLUMN `planned_end_smoke_test_date`  date NULL DEFAULT NULL AFTER `planned_start_smoke_test_date`;
ALTER TABLE `releases` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `planned_end_smoke_test_date`;
ALTER TABLE `releases` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `releases` MODIFY COLUMN `release_status`  bigint NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `releases` MODIFY COLUMN `actual_start_iot_date`  date NULL DEFAULT NULL AFTER `release_status`;
ALTER TABLE `releases` MODIFY COLUMN `actual_end_iot_date`  date NULL DEFAULT NULL AFTER `actual_start_iot_date`;
ALTER TABLE `releases` MODIFY COLUMN `actual_start_e2e_date`  date NULL DEFAULT NULL AFTER `actual_end_iot_date`;
ALTER TABLE `releases` MODIFY COLUMN `actual_end_e2e_date`  date NULL DEFAULT NULL AFTER `actual_start_e2e_date`;
ALTER TABLE `releases` MODIFY COLUMN `actual_start_uat_date`  date NULL DEFAULT NULL AFTER `actual_end_e2e_date`;
ALTER TABLE `releases` MODIFY COLUMN `actual_end_uat_date`  date NULL DEFAULT NULL AFTER `actual_start_uat_date`;
ALTER TABLE `releases` MODIFY COLUMN `actual_start_smoke_test_date`  date NULL DEFAULT NULL AFTER `actual_end_uat_date`;
ALTER TABLE `releases` MODIFY COLUMN `actual_end_smoke_test_date`  date NULL DEFAULT NULL AFTER `actual_start_smoke_test_date`;
ALTER TABLE `releases` MODIFY COLUMN `actual_closure_date`  date NULL DEFAULT NULL AFTER `actual_end_smoke_test_date`;
ALTER TABLE `releases` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
CREATE TABLE `releases_old` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`name`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL ,
`vendor_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`release_status_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`release_start`  datetime NULL DEFAULT NULL ,
`release_end`  datetime NULL DEFAULT NULL ,
`atp_start_date`  datetime NULL DEFAULT NULL ,
`atp_end_date`  datetime NULL DEFAULT NULL ,
`iot_start_date`  datetime NULL DEFAULT NULL ,
`iot_end_date`  datetime NULL DEFAULT NULL ,
`uat_start_date`  datetime NULL DEFAULT NULL ,
`uat_end_date`  datetime NULL DEFAULT NULL ,
`smoke_test_start_date`  datetime NULL DEFAULT NULL ,
`smoke_test_end_date`  datetime NULL DEFAULT NULL ,
`created_at`  datetime NULL DEFAULT NULL ,
`updated_at`  datetime NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `releases_old_ibfk_1` FOREIGN KEY (`release_status_id`) REFERENCES `release_statuses` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `releases_old_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `releases_vendor_id_foreign` (`vendor_id`) USING BTREE ,
INDEX `releases_release_status_id_foreign` (`release_status_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
ALTER TABLE `role_has_permissions` MODIFY COLUMN `permission_id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `role_has_permissions` MODIFY COLUMN `role_id`  bigint UNSIGNED NOT NULL AFTER `permission_id`;
ALTER TABLE `roles` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `roles` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `guard_name`;
ALTER TABLE `roles` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `roles` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
CREATE TABLE `roles_old` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`name`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL ,
`parent_id`  int NULL DEFAULT NULL ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`)
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
ALTER TABLE `sessions` MODIFY COLUMN `user_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `id`;
ALTER TABLE `sessions` MODIFY COLUMN `ip_address`  varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `sessions` MODIFY COLUMN `user_agent`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `ip_address`;
ALTER TABLE `sessions` MODIFY COLUMN `last_activity`  int NOT NULL AFTER `payload`;
ALTER TABLE `settings` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `settings` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `id`;
ALTER TABLE `settings` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `settings` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `stages` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `stages` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `stages` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `stages` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `status_work_flow` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `status_work_flow` MODIFY COLUMN `type`  tinyint NOT NULL DEFAULT 1 AFTER `id`;
ALTER TABLE `status_work_flow` MODIFY COLUMN `from_status_id`  bigint UNSIGNED NOT NULL AFTER `type`;
ALTER TABLE `status_work_flow` MODIFY COLUMN `to_status_id`  bigint UNSIGNED NOT NULL AFTER `from_status_id`;
ALTER TABLE `status_work_flow` MODIFY COLUMN `from_stage_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `to_status_id`;
ALTER TABLE `status_work_flow` MODIFY COLUMN `to_stage_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `from_stage_id`;
ALTER TABLE `status_work_flow` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `to_stage_id`;
ALTER TABLE `status_work_flow` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `status_work_flow` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `statuses` MODIFY COLUMN `stage_id`  bigint UNSIGNED NOT NULL AFTER `status_name`;
ALTER TABLE `statuses` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `type`;
ALTER TABLE `statuses` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `statuses` MODIFY COLUMN `high_level_status_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `statuses` MODIFY COLUMN `sla`  int NOT NULL DEFAULT 0 COMMENT 'Integer value in days' AFTER `high_level_status_id`;
ALTER TABLE `statuses` ADD COLUMN `defect`  tinyint(1) NOT NULL DEFAULT 0 AFTER `sla`;
ALTER TABLE `statuses` ADD COLUMN `view_technical_team_flag`  enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' AFTER `defect`;
ALTER TABLE `statuses` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `system_user_cabs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `system_user_cabs` MODIFY COLUMN `user_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `id`;
ALTER TABLE `system_user_cabs` MODIFY COLUMN `system_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `system_user_cabs` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `system_user_cabs` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `system_user_cabs` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
CREATE TABLE `technical_cr_teams` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`group_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`technical_cr_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`status`  enum('0','1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
`current_status_id`  bigint UNSIGNED NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `technical_cr_teams_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
CONSTRAINT `technical_cr_teams_technical_cr_id_foreign` FOREIGN KEY (`technical_cr_id`) REFERENCES `technical_crs` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `technical_cr_teams_group_id_foreign` (`group_id`) USING BTREE ,
INDEX `technical_cr_teams_technical_cr_id_foreign` (`technical_cr_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
CREATE TABLE `technical_crs` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`cr_id`  bigint UNSIGNED NULL DEFAULT NULL ,
`status`  enum('0','1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`),
CONSTRAINT `technical_crs_cr_id_foreign` FOREIGN KEY (`cr_id`) REFERENCES `change_request` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
INDEX `technical_crs_cr_id_foreign` (`cr_id`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
CREATE TABLE `technical_teams` (
`id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT ,
`name`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL ,
`active`  enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' ,
`created_at`  timestamp NULL DEFAULT NULL ,
`updated_at`  timestamp NULL DEFAULT NULL ,
PRIMARY KEY (`id`)
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
ROW_FORMAT=Dynamic
;
ALTER TABLE `units` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `units` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `units` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `units` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `user_groups` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `user_groups` MODIFY COLUMN `user_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `user_groups` MODIFY COLUMN `group_id`  bigint UNSIGNED NOT NULL AFTER `user_id`;
ALTER TABLE `user_groups` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `group_id`;
ALTER TABLE `user_groups` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `user_groups` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `users` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `users` MODIFY COLUMN `email_verified_at`  timestamp NULL DEFAULT NULL AFTER `email`;
ALTER TABLE `users` MODIFY COLUMN `password`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `email_verified_at`;
ALTER TABLE `users` MODIFY COLUMN `default_group`  int NOT NULL DEFAULT 0 AFTER `user_type`;
ALTER TABLE `users` MODIFY COLUMN `remember_token`  varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `users` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `remember_token`;
ALTER TABLE `users` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `users` MODIFY COLUMN `last_login`  timestamp NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `users` MODIFY COLUMN `role_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `last_login`;
ALTER TABLE `users` MODIFY COLUMN `unit_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `role_id`;
ALTER TABLE `users` MODIFY COLUMN `department_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `unit_id`;
ALTER TABLE `users` MODIFY COLUMN `man_power`  int NULL DEFAULT NULL COMMENT 'Integer value in hours' AFTER `flag`;
ALTER TABLE `users` MODIFY COLUMN `failed_attempts`  int NOT NULL DEFAULT 1 AFTER `man_power`;
ALTER TABLE `users` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `validation_type` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `validation_type` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `validation_type` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `validation_type` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `vendors` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `vendors` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `vendors` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `vendors` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `workflow` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `workflow` MODIFY COLUMN `from_status_id`  bigint UNSIGNED NOT NULL AFTER `id`;
ALTER TABLE `workflow` MODIFY COLUMN `to_status_id`  bigint UNSIGNED NOT NULL AFTER `from_status_name`;
ALTER TABLE `workflow` MODIFY COLUMN `to_status_label`  varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `workflow` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `to_status_label`;
ALTER TABLE `workflow` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `workflow` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `workflow_special` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `workflow_special` MODIFY COLUMN `no_need_desgin`  tinyint NOT NULL DEFAULT 0 AFTER `id`;
ALTER TABLE `workflow_special` MODIFY COLUMN `not_testable`  tinyint NOT NULL DEFAULT 0 AFTER `no_need_desgin`;
ALTER TABLE `workflow_special` MODIFY COLUMN `workflow_type_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `not_testable`;
ALTER TABLE `workflow_special` MODIFY COLUMN `from_status_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `workflow_type_id`;
ALTER TABLE `workflow_special` MODIFY COLUMN `to_workflow_id`  bigint UNSIGNED NULL DEFAULT NULL AFTER `from_status_id`;
ALTER TABLE `workflow_special` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `to_workflow_id`;
ALTER TABLE `workflow_special` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `workflow_special` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
ALTER TABLE `workflow_type` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL FIRST ;
ALTER TABLE `workflow_type` MODIFY COLUMN `parent_id`  int NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `workflow_type` MODIFY COLUMN `created_at`  timestamp NULL DEFAULT NULL AFTER `active`;
ALTER TABLE `workflow_type` MODIFY COLUMN `updated_at`  timestamp NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `workflow_type` MODIFY COLUMN `id`  bigint UNSIGNED NOT NULL AUTO_INCREMENT FIRST ;
SET FOREIGN_KEY_CHECKS=1;