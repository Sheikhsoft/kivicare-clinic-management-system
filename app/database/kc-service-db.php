<?php

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

$table_name = $wpdb->prefix . 'kc_services'; // do not forget about tables prefix

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    type varchar(191) NULL,
    name varchar(191)  NOT NULL,
    price integer(11) NOT NULL,
    status bigint(1) NOT NULL,    
    created_at datetime NOT NULL,    
    PRIMARY KEY  (id)
  ) $charset_collate;" ;

maybe_create_table($table_name,$sql);