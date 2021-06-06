<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


global $wpdb;
$charset_collate = $wpdb->get_charset_collate();


$table_name = $wpdb->prefix . 'kc_bill_items'; // do not forget about tables prefix

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    bill_id bigint(20) UNSIGNED NOT NULL,
    item_id bigint(20) UNSIGNED NOT NULL,
    qty integer(6) NOT NULL,
    price integer(11) NOT NULL,   
    created_at datetime NOT NULL,    
    PRIMARY KEY  (id)
  ) $charset_collate;";

maybe_create_table($table_name,$sql);
