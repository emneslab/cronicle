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

require 'vendor/autoload.php';

require_once CRONICLE_DIR . '/classes/class-cronicle.php';
require_once CRONICLE_DIR . '/classes/class-error-log.php';
require_once CRONICLE_DIR . '/views/dashboard.php';
require_once CRONICLE_DIR . '/views/status-page.php';
require_once CRONICLE_DIR . '/views/options-page.php';

register_activation_hook( CRONICLE_PLUGIN, 'cronicle_activation' );
register_deactivation_hook( CRONICLE_PLUGIN, 'cronicle_deactivation' );



if ( !defined( 'DOING_CRON' ) || !DOING_CRON ) {
    add_action( 'init', 'check_cron_completion'  );
}




    /**
     * return the inner join for querying logs.
     */
function get_limits_query( $outer = false ) {
        
        return '';
   
}
 



    /**
     * Populate our list of cron events and store them to a class-wide variable.
     * From WP_Site_Health 5.2.0
     */
    function get_cron_tasks() {
        $cron_tasks = _get_cron_array();

        if ( empty( $cron_tasks ) ) {
            $crons = new WP_Error( 'no_tasks', __( 'No scheduled events exist on this site.' ) );
            return;
        }

        $crons = array();

        foreach ( $cron_tasks as $time => $cron ) {
            foreach ( $cron as $hook => $dings ) {
                foreach ( $dings as $sig => $data ) {

                    $crons[ "$hook-$sig-$time" ] = (object) array(
                        'hook'     => $hook,
                        'time'     => $time,
                        'sig'      => $sig,
                        'args'     => $data['args'],
                        'schedule' => $data['schedule'],
                        'interval' => isset( $data['interval'] ) ? $data['interval'] : null,
                    );

                }
            }
        }
        return $crons;
    }









    /**
     * Return an array for display
     */
     function check_cron_completion() {
        global $wpdb;
        $last_checked = get_option( '_cronicle_last_error_check', 0 );
        $non_error_result = array_map( function( $v ) {
            return "'" . esc_sql( $v ) . "'";
        }, ['skipped', 'completed', 'exited', 'in progress'] );
        $in_str = implode( ',', $non_error_result );
        // any logs that have no end date or result is some kind of error message
        $open_logs = $wpdb->get_results( "
            select * from wp_cronicle_logs 
            where ( 
                    ( end IS NULL ) 
                 or ( 
                      ( coalesce( result, '' ) != '' )
                  and ( result NOT IN ( " . $in_str . " ) ) 
                  )
              )
              and ( start > " . ( ( $last_checked - 300 ) * 10000 ) . " )
              and ( hook_name != 'cronicle_wp_cron' )
            ", ARRAY_A );
        if ( empty( $open_logs ) ) {
            return;
        }
        // this gets called on each page load.  So anything within the last 12 hours will get found.
        $now = time();
        $incomplete_not_error = cronicle_is_incomplete_an_error();
        $cron_logs = array_filter( $open_logs, function( $row ) use ( $now ) {
            $include = true;
            if ( empty( $row['end'] ) ) {
                if ( cronicle_is_incomplete_an_error( $row['hook_name'] ) ) {
                    $run_time = round( $row['cron_key'] );
                    $include = ( ( $run_time + 300 ) < $now );
                }
                else {
                    $include = false;
                }
            }
            return $include;
        } );
        foreach ( $cron_logs as $row ) {
            CRONICLE_Error_Logs::add_error( $row['cron_key'], $row['hook_name'] );
        }
        update_option( '_cronicle_last_error_check', time() );
    }















    /**
     * Return a timestamp in the blog's timezone give a timestamp from UTC 
     */
    function utc_to_blogtime( $timestamp ) {
        $timestamp = (int) $timestamp;
        try {
            // get datetime object from unix timestamp
            $datetime = new DateTime( "@{$timestamp}", new DateTimeZone( 'UTC' ) );
         
            // set the timezone to the site timezone
            $datetime->setTimezone( new DateTimeZone( get_timezone_string() ) );
         
            // return the unix timestamp adjusted to reflect the site's timezone
            return $timestamp + $datetime->getOffset();
         
        } catch ( Exception $e ) {
             
            // something broke
            return 0;
        }
    }

    /**
     * Returns the timezone string for a site, even if it's set to a UTC offset
     *
     * @return string valid PHP timezone string
     */
     function get_timezone_string() {
     
        // if site timezone string exists, return it
        if ( $timezone = get_option( 'timezone_string' ) )
            return $timezone;
     
        // get UTC offset, if it isn't set then return UTC
        if ( 0 === ( $utc_offset = get_option( 'gmt_offset', 0 ) ) )
            return 'UTC';
     
        // adjust UTC offset from hours to seconds
        $utc_offset *= 3600;
     
        // attempt to guess the timezone string from the UTC offset
        $timezone = timezone_name_from_abbr( '', $utc_offset );
     
        // last try, guess timezone string manually
        if ( false === $timezone ) {
     
            $is_dst = date( 'I' );
     
            foreach ( timezone_abbreviations_list() as $abbr ) {
                foreach ( $abbr as $city ) {
                    if ( $city['dst'] == $is_dst && $city['offset'] == $utc_offset )
                        return $city['timezone_id'];
                }
            }
        }
     
        // fallback to UTC
        return 'UTC';
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
        $datetime = new DateTime( 'midnight', new DateTimeZone( get_timezone_string() ) );
        $datetime->setTimezone( new DateTimeZone( 'UTC' ) );
        $time = $datetime->format( 'U' );
    } catch ( Exception $e ) {
    }

    
    
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
 * Drop the tables.
 */
function cronicle_destroy_tables() {
    global $wpdb;


    $wpdb->query( 'DROP TABLE IF EXISTS wp_cronicle_logs' );

    
    $wpdb->query( 'DROP TABLE IF EXISTS wp_cronicle_error_logs' );
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
        'wp_cronicle_logs',
        'wp_cronicle_error_logs'
    ) );
    return $tables;
}


add_filter( 'wpmu_drop_tables', 'cronicle_on_delete_blog' );
/**
 * Deactivate plugin
 */
function cronicle_deactivation()
{
    
    
}



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

 
    $sql = "CREATE TABLE wp_cronicle_logs (
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

    
    $sql = "CREATE TABLE wp_cronicle_error_logs (
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
 * Run the check and update the status.
 */
function cronicle_run( $forced = false ) {
    if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
        return;
    }

    $cached_status = get_transient( 'cronicle-wp-cron-tested' );
    if ( !$forced && $cached_status ) {
        return;
    }
    set_transient( 'cronicle-wp-cron-tested', current_time( 'mysql' ), 86400 );

    $result = cronicle_test_cron_spawn();

    if ( is_wp_error( $result ) ) {
        if ( $result->get_error_code() === 'cronicle_notice' ) {
            update_option( 'cronicle_status', '<span class="cronicle-status cronicle-notice">' . $result->get_error_message() . '</span>' );
        }
        else {
            $msg = sprintf( __( '<p>While trying to spawn a call to the WP-Cron system, the following error occurred: %s</p>', 'cronicle' ), '<br><strong>' . esc_html( $result->get_error_message() ) . '</strong>' );
            $msg .= __( '<p>This may indicate a problem with your server\'s permissions, resources, or general WordPress setup.  If you need support, please contact your website host or post to the <a href="https://wordpress.org/support/forum/how-to-and-troubleshooting/">main WordPress support forum</a>.</p>', 'cronicle' );


            update_option( 'cronicle_status', '<span class="cronicle-status cronicle-error">' . $msg . '</span>' );
        }
    }
    else {
        $time_string = cronicle_get_datestring();
        $msg = sprintf( __( '<span class="cronicle-label">WP-Cron is able to run as of:</span><span class="cronicle-value">%s</span>', 'cronicle' ), $time_string );
        update_option( 'cronicle_status', '<span class="cronicle-status cronicle-success">' . $msg . '</span>', false );
    }

    do_action( 'cronicle_run_status', $result, $forced );
}

add_action( 'init', 'cronicle_run' );

/**
 * Gets the status of WP-Cron functionality on the site by performing a test spawn.
 * Code derived from WP-Crontrol.
 *
 */
function cronicle_test_cron_spawn() {
    global $wp_version;

    // Ensure WP-Cron constants are defined if not already.
    defined( 'DISABLE_WP_CRON' ) || define( 'DISABLE_WP_CRON', false );
    defined( 'ALTERNATE_WP_CRON' ) || define( 'ALTERNATE_WP_CRON', false );

    // Check if WP-Cron is disabled.
    if ( DISABLE_WP_CRON ) {
        return new WP_Error(
            'cronicle_notice',
            sprintf(
                __( 'WP-Cron has been disabled since %s. It will not run automatically as DISABLE_WP_CRON is set to true.', 'cronicle' ),
                current_time( 'm/d/Y g:i:s a' )
            )
        );
    }

    // Check if alternate WP-Cron is active.
    if ( ALTERNATE_WP_CRON ) {
        return new WP_Error(
            'cronicle_notice',
            sprintf(
                __( 'Unable to determine WP-Cron status. ALTERNATE_WP_CRON has been active since %s.', 'cronicle' ),
                current_time( 'm/d/Y g:i:s a' )
            )
        );
    }

    $sslverify = version_compare( $wp_version, '4.0', '<' );
    $doing_wp_cron = sprintf( '%.22F', microtime( true ) );

    // Build the cron request array with filters applied.
    $cron_request = apply_filters( 'cron_request', [
        'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
        'key'  => $doing_wp_cron,
        'args' => [
            'timeout'   => 3,
            'blocking'  => true,
            'sslverify' => apply_filters( 'https_local_ssl_verify', $sslverify ),
        ],
    ]);

    // Ensure blocking is true for this request.
    $cron_request['args']['blocking'] = true;

    // Execute the remote post request.
    $result = wp_remote_post( $cron_request['url'], $cron_request['args'] );

    // Handle possible errors or unexpected HTTP response codes.
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    $response_code = wp_remote_retrieve_response_code( $result );
    if ( $response_code >= 300 ) {
        return new WP_Error( 
            'unexpected_http_response_code',
            sprintf(
                 __( 'Unexpected HTTP response code: %s', 'wp-crontrol' ), 
                intval( $response_code ) 
            )
        );
    }
}


if ( defined( 'DOING_CRON' ) && DOING_CRON ) {

    /**
     * Set the time for cronicle_last_run.
     */
    function cronicle_monitor_runs() {
        global $cronicle_doing_cron_key;
        
        $cronicle_doing_cron_key = get_transient( 'doing_cron' );

        if ( empty ( $cronicle_doing_cron_key ) ) {
            // shouldn't get here
            return;
        }

        $crons = cronicle_get_ready_cron_jobs();
        if ( empty( $crons ) ) {
            // shouldn't get here
            return;
        }
        
        if ( CRONICLE_ENABLE_MONITORING == false ) {
            return;
        }

        $gmt_time = microtime( true );
        $last_hook = '';
        $skipped_hooks = array();
        foreach ( $crons as $timestamp => $cronhooks ) {
            if ( $timestamp > $gmt_time ) {
                break;
            }

            foreach ( $cronhooks as $hook => $keys ) {
                
                if ( apply_filters( 'cronicle_skip_hook', false, $hook ) ) {
                    $skipped_hooks[] = $hook;
                    // don't log elapsed time
                    CRONICLE::start_log( $hook );
                    CRONICLE::end_log( $hook, false, 'skipped' );
                    continue;
                }

                $last_hook = $hook;
                add_action( $hook, 
                    function() use ( $hook ) {
                        CRONICLE::start_log( $hook );
                    },
                    0
                );
                add_action( $hook, 
                    function() use ( $hook ) {
                        CRONICLE::end_log( $hook, true, 'completed' );
                    },
                    PHP_INT_MAX
                );
            }
        }

        if ( !empty( $last_hook ) ) {
            CRONICLE::start_log( 'cronicle_wp_cron' );

            add_action( $last_hook, 
                function() {
                    CRONICLE::end_log( 'cronicle_wp_cron', true, 'completed' );
                },
                PHP_INT_MAX
            );
        }
        else if ( !empty( $skipped_hooks ) ) {
            // cron has only skipped hooks, so log with no elapsed time
            CRONICLE::start_log( 'cronicle_wp_cron' );
            CRONICLE::end_log( 'cronicle_wp_cron', false, 'skipped' );
        }

    }
    add_action( 'cronicle_event_start', 'cronicle_monitor_runs' );

    /**
     * Setup functions to record the last run
     */
    function cronicle_init_cron_monitor() {
        global $cronicle_doing_cron_key;
        if ( CRONICLE_ENABLE_MONITORING == false ) {
            return;
        }

        $cronicle_doing_cron_key = get_transient( 'doing_cron' );
        if ( !empty( $cronicle_doing_cron_key ) ) {
            // transient is already set, lets go!
            cronicle_monitor_runs();
        }
        else {
            $crons = cronicle_get_ready_cron_jobs();
            if ( empty( $crons ) ) {
                // shouldn't get here
                return;
            }
            // we have jobs and transient not set, need to get it later.
            wp_schedule_single_event( 1, 'cronicle_event_start' );
        }
    }
    add_action( 'init', 'cronicle_init_cron_monitor' );

    /**
     * Handle shutdown of PHP.  add to errors if hook is not being skipped.
     * NOTE: Everytime PHP ends, this function is called, not just errors.
     */
    function cronicle_shutdown_handler() {
        global $cronicle_doing_cron_key;
        $last_error = error_get_last();
        $has_error = false;
        $error_message = null;
        if ( $last_error != null && $last_error['type'] === E_ERROR ) {
            $error_message = $last_error['message'];
        }
        if ( empty( $error_message ) ) {
            $error_message = 'exited';
        }

        $hooks_in_progress = get_hooks_in_progress( $cronicle_doing_cron_key );

        $skipped_hooks = array();
        foreach ( $hooks_in_progress as $hook_name ) {
            CRONICLE::end_log( $hook_name, true, $error_message );
            // should this be an option to mark exited crons as an error?
            if ( $hook_name != 'cronicle_wp_cron' && $error_message != 'exited' ) {
                CRONICLE_Error_Logs::add_error( $cronicle_doing_cron_key, $hook_name );
            }
        }
    }
    register_shutdown_function( 'cronicle_shutdown_handler' );


    /**
     * Get all running hook names with cron key
     */
    function get_hooks_in_progress( $cron_key, $hook_names = array() ) {
        global $wpdb;
        $where_hooks = '';
        if ( !empty( $hook_names ) ) {
            $hook_names = array_map( function( $v ) {
                return "'" . esc_sql( $v ) . "'";
            }, $hook_names);
            $in_str = implode( ',', $hook_names );
            $where_hooks = " and hook_name IN ( " . $in_str . " )";
        }
        return $wpdb->get_col( 
            $wpdb->prepare( "
                select distinct hook_name 
                from wp_cronicle_logs 
                where ( cron_key = %s )
                  and ( `end` IS NULL )
                " . $where_hooks . "
            ", $cron_key
        ) );
    }



    /**
     * If wp_get_ready_cron_jobs exists (WP 5.1.0) run it.  otherwise, this is a copy of that function.
     *
     * Retrieve cron jobs ready to be run.
     *
     * Returns the results of _get_cron_array() limited to events ready to be run,
     * ie, with a timestamp in the past.
     *
     *
     * @return array Cron jobs ready to be run.
     */
    function cronicle_get_ready_cron_jobs() {
        if ( defined( 'wp_get_ready_cron_jobs' ) ) {
            return wp_get_ready_cron_jobs();
        }

        /**
         * Filter to preflight or hijack retrieving ready cron jobs.
         *
         * Returning an array will short-circuit the normal retrieval of ready
         * cron jobs, causing the function to return the filtered value instead.
         *
         * @since 5.1.0
         *
         * @param null|array $pre Array of ready cron tasks to return instead. Default null
         *                        to continue using results from _get_cron_array().
         */
        $pre = apply_filters( 'pre_get_ready_cron_jobs', null );
        if ( null !== $pre ) {
            return $pre;
        }

        $crons = _get_cron_array();

        if ( false === $crons ) {
            return array();
        }

        $gmt_time = microtime( true );
        $keys     = array_keys( $crons );
        if ( isset( $keys[0] ) && $keys[0] > $gmt_time ) {
            return array();
        }

        $results = array();
        foreach ( $crons as $timestamp => $cronhooks ) {
            if ( $timestamp > $gmt_time ) {
                break;
            }
            $results[ $timestamp ] = $cronhooks;
        }

        return $results;
    }
}



if ( defined( 'CRONICLE_DEBUG_CRON_EVENT' ) && CRONICLE_DEBUG_CRON_EVENT ) {
    
    /**
     * schedules the event on activation
     */
    function cronicle_thirty_seconds_activation() {
        if (! wp_next_scheduled ( 'cronicle_debug_event' )) {
            wp_schedule_event( time(), 'thirty_seconds', 'cronicle_debug_event' );
        }
    }
    register_activation_hook( CRONICLE_PLUGIN, 'cronicle_thirty_seconds_activation' );

    /**
     * unschedules the event on activation
     */
    function cronicle_thirty_seconds_deactivation() {
            wp_clear_scheduled_hook( 'cronicle_debug_event' );
    }
    register_deactivation_hook( CRONICLE_PLUGIN, 'cronicle_thirty_seconds_deactivation' );

    /**
     * for debug, sets up a job running every 30 seconds.
     */
    function cronicle_add_thirty_seconds( $schedules ) {
        // add a 'cronicle_add_thirty_seconds' schedule to the existing set
        $schedules['thirty_seconds'] = array(
            'interval' => 30,
            'display' => __('Once Every 30 Seconds')
        );
        return $schedules;
    }
    add_filter( 'cron_schedules', 'cronicle_add_thirty_seconds' );

    /**
     * debug job to be run
     */
    function cronicle_debug_cron() {
        // global $wpdb;
        do_action( 'wp_log_debug', 'cronicle_debug_cron', 1 );
        sleep(1);
        //$wpdb->hello();
        do_action( 'wp_log_debug', 'cronicle_debug_cron2', 'results' );
    }
    add_action( 'cronicle_debug_event', 'cronicle_debug_cron', 99 );

}