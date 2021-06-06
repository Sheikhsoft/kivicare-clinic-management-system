<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

$table_name = $wpdb->prefix . 'kc_clinic_sessions'; // do not forget about tables prefix

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    clinic_id bigint(20) UNSIGNED NOT NULL,
    doctor_id bigint(20) UNSIGNED NOT NULL,
    day varchar(191)  NULL,
    start_time time NULL,   
    end_time time NULL,
    time_slot int(5) DEFAULT 5,
    parent_id bigint(20) UNSIGNED NULL,
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $charset_collate;";

maybe_create_table($table_name,$sql);