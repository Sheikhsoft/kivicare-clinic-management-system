<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

$table_name = $wpdb->prefix . 'kc_medical_problems'; // do not forget about tables prefix

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    encounter_id bigint(20)  UNSIGNED NOT NULL,
    patient_id bigint(20)  UNSIGNED NOT NULL,
    start_date date NULL, 
    end_date date NULL,
    description text  NULL,  
    problem_type varchar(191)  NOT NULL,
    outcome varchar(191)  NOT NULL,
    added_by bigint(20)  UNSIGNED NOT NULL,
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $charset_collate;";

maybe_create_table($table_name,$sql);