<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


global $wpdb;
$charset_collate = $wpdb->get_charset_collate();


$table_name = $wpdb->prefix . 'kc_bills'; // do not forget about tables prefix

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    encounter_id bigint(20)  UNSIGNED NOT NULL,
    appointment_id bigint(20)  UNSIGNED NULL,
    title varchar(191) NULL,
    total_amount integer(11) NOT NULL,
    discount integer(11) NOT NULL,
    actual_amount integer(11) NOT NULL,
    status bigint(1) NOT NULL,
    payment_status varchar(10) NULL,
    created_at datetime NOT NULL,    
    PRIMARY KEY  (id)
  ) $charset_collate;";

maybe_create_table($table_name,$sql);

$new_fields = [
    'payment_status' => 'varchar(10) NULL',
];

kcUpdateFields($table_name,$new_fields);
