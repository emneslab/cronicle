<?php

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

    if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
        return new WP_Error( 'cronicle_notice', sprintf( __( 'The DISABLE_WP_CRON constant is set to true as of %s. WP-Cron is disabled and will not run on it\'s own.', 'cronicle' ), current_time( 'm/d/Y g:i:s a' ) ) );
    }

    if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
        return new WP_Error( 'cronicle_notice', sprintf( __( 'The ALTERNATE_WP_CRON constant is set to true as of %s.  This plugin cannot determine the status of your WP-Cron system.', 'cronicle' ), current_time( 'm/d/Y g:i:s a' ) ) );
    }

    $sslverify     = version_compare( $wp_version, 4.0, '<' );
    $doing_wp_cron = sprintf( '%.22F', microtime( true ) );

    $cron_request = apply_filters( 'cron_request', array(
        'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
        'key'  => $doing_wp_cron,
        'args' => array(
            'timeout'   => 3,
            'blocking'  => true,
            'sslverify' => apply_filters( 'https_local_ssl_verify', $sslverify ),
        ),
    ) );

    $cron_request['args']['blocking'] = true;
    $result = wp_remote_post( $cron_request['url'], $cron_request['args'] );

    if ( is_wp_error( $result ) ) {
        return $result;
    } else if ( wp_remote_retrieve_response_code( $result ) >= 300 ) {
        return new WP_Error( 'unexpected_http_response_code', sprintf(
            __( 'Unexpected HTTP response code: %s', 'wp-crontrol' ),
            intval( wp_remote_retrieve_response_code( $result ) )
        ) );
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
                    CRONICLE::end_log( $hook, false, CRONICLE::RESULT_SKIPPED );
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
                        CRONICLE::end_log( $hook, true, CRONICLE::RESULT_COMPLETED );
                    },
                    PHP_INT_MAX
                );
            }
        }

        if ( !empty( $last_hook ) ) {
            CRONICLE::start_log( 'cronicle_wp_cron' );

            add_action( $last_hook, 
                function() {
                    CRONICLE::end_log( 'cronicle_wp_cron', true, CRONICLE::RESULT_COMPLETED );
                },
                PHP_INT_MAX
            );
        }
        else if ( !empty( $skipped_hooks ) ) {
            // cron has only skipped hooks, so log with no elapsed time
            CRONICLE::start_log( 'cronicle_wp_cron' );
            CRONICLE::end_log( 'cronicle_wp_cron', false, CRONICLE::RESULT_SKIPPED );
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
            $error_message = CRONICLE::RESULT_EXITED;
        }

        $hooks_in_progress = CRONICLE::get_hooks_in_progress( $cronicle_doing_cron_key );

        $skipped_hooks = array();
        foreach ( $hooks_in_progress as $hook_name ) {
            CRONICLE::end_log( $hook_name, true, $error_message );
            // should this be an option to mark exited crons as an error?
            if ( $hook_name != 'cronicle_wp_cron' && $error_message != CRONICLE::RESULT_EXITED ) {
                CRONICLE_Error_Logs::add_error( $cronicle_doing_cron_key, $hook_name );
            }
        }
    }
    register_shutdown_function( 'cronicle_shutdown_handler' );


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
        //$wpdb->hello();
        do_action( 'wp_log_debug', 'cronicle_debug_cron2', 'results' );
    }
    add_action( 'cronicle_debug_event', 'cronicle_debug_cron', 99 );

}