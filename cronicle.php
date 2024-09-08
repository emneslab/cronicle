<?php

/*
Plugin Name: WP-Cron Status Checker Pro
Plugin URI: https://webheadcoder.com/wp-cron-status-checker
Description: If WP-Cron runs important things for you, you better make sure WP-Cron always runs!
Version: 1.2.5
Update URI: https://api.freemius.com
Author: Webhead LLC
*/
define( 'CRONICLE_VERSION', '1.2.5' );
define( 'CRONICLE_PLUGIN', __FILE__ );
define( 'CRONICLE_DIR', dirname( CRONICLE_PLUGIN ) );
define( 'CRONICLE_OPTIONS_NAME', 'cronicle_options' );
define( 'CRONICLE_OPTIONS_PAGE_ID', 'cronicle-options' );
define( 'CRONICLE_DEBUG_CRON_EVENT', 1 );
define( 'CRONICLE_ENABLE_MONITORING', true );
define( 'CRONICLE_DEFAULT_LOG_LIFESPAN', 86400 );
define( 'CRONICLE_PLUGIN_WEBSITE', 'https://webheadcoder.com/wp-cron-status-checker' );
require 'vendor/autoload.php';
require_once CRONICLE_DIR . '/classes/class-cronicle.php';
require_once CRONICLE_DIR . '/classes/class-error-log.php';
require_once CRONICLE_DIR . '/inc/cron-checker.php';
require_once CRONICLE_DIR . '/views/dashboard.php';
require_once CRONICLE_DIR . '/views/status-page.php';
require_once CRONICLE_DIR . '/views/options-page.php';
register_activation_hook( CRONICLE_PLUGIN, 'cronicle_activation' );
register_deactivation_hook( CRONICLE_PLUGIN, 'cronicle_deactivation' );
add_action( 'cronicle_clean_up', array( 'CRONICLE', 'cleanup' ) );
// Use cron to send email for this because it is assumed working.
// If cron isn't working, the "Failed" email notice will be sent.
add_action( 'cronicle_email_notice_hook', array( 'CRONICLE', 'notify_user' ) );
if ( !defined( 'DOING_CRON' ) || !DOING_CRON ) {
    add_action( 'init', array( 'CRONICLE', 'check_cron_completion' ) );
}
if ( !defined( 'ABSPATH' ) ) {
    exit;
}


    
    /**
     * Activate plugin
     */
    function cronicle_activation( $network_wide )
    {
        global  $wpdb ;
        cronicle_setup_tables();
        
        if ( $network_wide && is_multisite() ) {
            // Get all blogs in the network and activate plugin on each one
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                cronicle_setup_tables();
                restore_current_blog();
            }
        } else {
            cronicle_setup_tables();
        }
        
        $time = time();
        try {
            $datetime = new DateTime( 'midnight', new DateTimeZone( CRONICLE::get_timezone_string() ) );
            $datetime->setTimezone( new DateTimeZone( 'UTC' ) );
            $time = $datetime->format( 'U' );
        } catch ( Exception $e ) {
        }
        if ( !wp_next_scheduled( 'cronicle_clean_up' ) ) {
            wp_schedule_event( $time, 'twicedaily', 'cronicle_clean_up' );
        }
        CRONICLE::schedule_email_notice_hook();
    }
    
    /**
     * Delete the tables
     */
    function cronicle_fs_uninstall_cleanup()
    {
        $delete_data_too = cronicle_option( 'delete_data_too', 0 );
        
        if ( !empty($delete_data_too) ) {
            cronicle_destroy_tables();
            delete_option( '_cronicle_version' );
        }
    
    }
    
    /**
     * Setup the 
     */
    function cronicle_add_email_frequency( $schedules )
    {
        // add to the existing set
        $email_frequency = (int) cronicle_option( 'email_frequency', 86400 );

        $schedules['cronicle_email_interval'] = array(
            'interval' => $email_frequency,
            'display'  => cronicle_email_frequencies( $email_frequency ),
        );
        return $schedules;
    }
    
    add_filter( 'cron_schedules', 'cronicle_add_email_frequency' );
    /**
     * Check to see if the db is up to date.
     */
    function cronicle_update_db_check()
    {
        if ( get_option( '_cronicle_version', 0 ) != CRONICLE_VERSION ) {
            cronicle_activation( false );
        }
    }
    
    add_action( 'plugins_loaded', 'cronicle_update_db_check' );
    /**
     * Deleting the table whenever a blog is deleted
     */
    function cronicle_on_delete_blog( $tables )
    {
        global  $wpdb ;
        $tables = array_merge( $tables, array(
            CRONICLE::table_name(),
            CRONICLE_Error_Logs::table_name()
        ) );
        return $tables;
    }

    
    add_filter( 'wpmu_drop_tables', 'cronicle_on_delete_blog' );
    /**
     * Deactivate plugin
     */
    function cronicle_deactivation()
    {
        wp_clear_scheduled_hook( 'cronicle_clean_up' );
        CRONICLE::unschedule_email_notice_hook();
    }
    
    /**
     * Returns the timestamp in the blog's time and format.
     */
    function cronicle_get_datestring( $timestamp = '' )
    {
        if ( empty($timestamp) ) {
            $timestamp = current_time( 'timestamp', true );
        }
        return get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
    }
    
    /**
     * Get option
     */
    function cronicle_option( $name, $default = false )
    {
        $options = get_option( CRONICLE_OPTIONS_NAME );
        
        if ( !empty($options) && isset( $options[$name] ) ) {
            $ret = $options[$name];
        } else {
            $ret = $default;
        }
        
        return $ret;
    }
    
    /**
     * Email the user if the results for the general WP Cron system is bad
     */
    function cronicle_notify_user( $result, $forced )
    {
        
        if ( !$forced && is_wp_error( $result ) && $result->get_error_code() != 'cronicle_notice' ) {
            $last_emailed = get_option( '_cronicle_last_emailed', 0 );
            $email_frequency = (int) cronicle_option( 'email_frequency', 86400 );
            if ( !$forced && $last_emailed > time() - $email_frequency ) {
                return;
            }
            

            
            $email_address = cronicle_get_email_address();
            
            if ( !empty($email_address) ) {
                $msg = get_option( 'cronicle_status' );
                $msg .= sprintf( __( '<p style="font-size:.9em;">This message has been sent from %s by the WP-Cron Status Checker plugin.  You can change the email address in your WordPress admin section under Settings -> WP Cron Status.  Only one email will be mailed every 24 hours.</p>', 'cronicle' ), site_url() );
                $headers = array( ' Content-Type: text/html; charset=UTF-8' );
                wp_mail(
                    $email_address,
                    get_bloginfo( 'name' ) . ' - ' . __( 'WP-Cron Cannot Run!', 'cronicle' ),
                    $msg,
                    $headers
                );
                update_option( '_cronicle_last_emailed', time() );
            }
        
        }
    
    }
    
    add_action(
        'cronicle_run_status',
        'cronicle_notify_user',
        10,
        2
    );
    /**
     * Get the email address taking account the old settings.
     */
    function cronicle_get_email_address()
    {
        $email_address = cronicle_option( 'email', -1 );
        // if not set, fall back to old setting
        if ( $email_address == -1 ) {
            
            if ( get_option( 'cronicle-email-flag' ) ) {
                $email_address = get_option( 'admin_email' );
            } else {
                $email_address = '';
            }
        
        }
        return $email_address;
    }
    
    /**
     * List of messages
     */
    function cronicle_messages()
    {
        $messages = array(
            '1' => __( 'Logs successfully cleared', 'cronicle' ),
        );
        return $messages;
    }
    
    /**
     * Return true if incomplete should be considered an error (for this hook)
     */
    function cronicle_is_incomplete_an_error( $hook_name = '' )
    {
        $incomplete_not_error = cronicle_option( 'incomplete_not_error', 0 );
        if ( !empty($incomplete_not_error) ) {
            return false;
        }
        $hooks = cronicle_option( 'incomplete_not_error_hooks', '' );
        $hook_names = explode( "\n", $hooks );
        
        if ( !empty($hook_name) && !empty($hook_names) && is_array( $hook_names ) ) {
            $hook_names = array_map( 'trim', $hook_names );
            if ( in_array( trim( $hook_name ), $hook_names ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Email frequencies
     */
    function cronicle_email_frequencies( $val = '' )
    {
        $frequencies = array(
            '300'    => __( 'Once every 5 minutes' ),
            '3600'   => __( 'Once an hour' ),
            '86400'  => __( 'Once a day' ),
            '604800' => __( 'Once a week' ),
        );
        if ( !empty($val) && !empty($frequencies[$val]) ) {
            return $frequencies[$val];
        }
        return $frequencies;
    }
    
    /**
     * Log lifespan options
     */
    function cronicle_log_lifespans( $val = '' )
    {
        $lifespans = array(
            '0'       => __( 'Do Not Keep Logs', 'cronicle' ),
            '43200'   => __( '12 hours', 'cronicle' ),
            '86400'   => __( '24 hours', 'cronicle' ),
            '604800'  => __( '1 week', 'cronicle' ),
            '2592000' => __( '1 month', 'cronicle' ),
        );
        if ( !empty($val) && !empty($lifespans[$val]) ) {
            return $lifespans[$val];
        }
        return $lifespans;
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