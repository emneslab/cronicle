<?php

/**
 * Return a list of all tables used by this plugin.
 */
function cronicle_table_names() {
    return array(
        CRONICLE::table_name(),
        CRONICLE_Error_Logs::table_name()
    );
}

/**
 * Set up the sql tables.
 */
function cronicle_setup_tables() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $table_name = CRONICLE::table_name();
    $sql = "CREATE TABLE " . $table_name . " (
        id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
        cron_key varchar(255) NOT NULL,
        hook_name varchar(255) NOT NULL,
        start BIGINT(20) NOT NULL,
        end BIGINT(20) NULL,
        result TEXT NULL,
        PRIMARY KEY  (id),
        KEY cron_key (cron_key),
        KEY cron_key_hook_name (cron_key, hook_name),
        KEY `result_hook_name` (`result`(255), `hook_name`(255))
        )
        ENGINE = innodb;";
    dbDelta($sql);

    $table_name = CRONICLE_Error_Logs::table_name();
    $sql = "CREATE TABLE " . $table_name . " (
        id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
        cron_key varchar(255) NOT NULL,
        hook_name varchar(255) NULL,
        sent_date DATETIME NULL,
        PRIMARY KEY  (id),
        KEY cron_key (cron_key)
        )
        ENGINE = innodb;";
    dbDelta($sql);

    update_option( '_cronicle_version', CRONICLE_VERSION );
}

/**
 * Drop the tables.
 */
function cronicle_destroy_tables() {
    global $wpdb;

    $table_name = CRONICLE::table_name();
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $table_name );

    $table_name = CRONICLE_Error_Logs::table_name();
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $table_name );
}