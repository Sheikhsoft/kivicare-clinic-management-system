<?php

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

$table_name = $wpdb->prefix . 'kc_service_doctor_mapping'; // do not forget about tables prefix

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    service_id int(11) DEFAULT 0,
    doctor_id bigint(20) unsigned DEFAULT 0,
    clinic_id int(11) DEFAULT 0,
    charges int(11) DEFAULT 0,
    extra longtext NULL, 
    status int(1) DEFAULT 1,
    created_at datetime NOT NULL,    
    PRIMARY KEY  (id)
  ) $charset_collate;";

maybe_create_table($table_name,$sql);