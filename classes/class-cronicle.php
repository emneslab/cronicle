<?php
class CRONICLE {

    public $crons;

    private $raw_logs;

    const CRON_TIME_ALLOWANCE = 300; //5 minutes;
    const EMAIL_TIME_PERIOD_FREQUENCY = 86400;
    const NO_VALUE = '-';
    const MICROTIME_TO_INT_MULTIPLE = 10000;

    const RESULT_SKIPPED = 'skipped';
    const RESULT_COMPLETED = 'completed';
    const RESULT_EXITED = 'exited';
    const RESULT_IN_PROGRESS = 'in progress';
    const RESULT_FAILED = 'failed';
    const RESULT_INCOMPLETE = 'incomplete'; // derived, not stored.

    /**
     * Return the table name.
     */
    public static function table_name() {
        global $table_prefix;
        return $table_prefix . 'cronicle_logs';
    }

    /**
     * Log the time for the start of the hook.
     */
    public static function start_log( $hook_name, $with_time = true, $result = CRONICLE::RESULT_IN_PROGRESS ) {
        self::_log( $hook_name, 'start', $with_time, $result );
    }

    /**
     * Log the time for the end of the hook.
     */
    public static function end_log( $hook_name, $with_time = true, $result = null ) {
        self::_log( $hook_name, 'end', $with_time, $result );
    }

    /**
     * Clear all the logs
     */
    public static function clear_logs() {
        global $wpdb;
        $wpdb->query( "delete from " . CRONICLE::table_name() );
        $wpdb->query( "delete from " . CRONICLE_Error_Logs::table_name() );
    }

    /**
     * Get all running hook names with cron key
     */
    public static function get_hooks_in_progress( $cron_key, $hook_names = array() ) {
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
                from " . CRONICLE::table_name() . " 
                where ( cron_key = %s )
                  and ( `end` IS NULL )
                " . $where_hooks . "
            ", $cron_key
        ) );
    }

    /**
     * Log hook's start or end time.  if end is already set, don't log it.
     */
    private static function _log( $hook_name, $key = 'start', $with_time = true, $result = null ) {
        global $cronicle_doing_cron_key, $wpdb;
        $hook_name =  sanitize_key( $hook_name );
        $log_lifespan = self::get_log_lifespan();
        if ( $key == 'start' ) {
            $params = array( 
                    'cron_key'  => $cronicle_doing_cron_key, 
                    'hook_name' => $hook_name,
                    'start'     => $with_time ? microtime( true ) * CRONICLE::MICROTIME_TO_INT_MULTIPLE : 0
            );
            $format = array( '%s', '%s', '%d' );
            if ( !is_null( $result ) ) {
                $params['result'] = $result;
                $format[] = '%s';
            }
            $wpdb->insert( 
                CRONICLE::table_name(),
                $params, 
                $format
            );
        }
        else {
            // only log end time if end is null
            // if end is 0 it means we are already logged it and not keeping track of elapsed time.
            $log_id = $wpdb->get_var( 
                $wpdb->prepare( "
                    select id from " . CRONICLE::table_name() . " 
                    where ( cron_key = %s ) 
                      and ( hook_name = %s )
                      and ( `end` is null )
                    order by `start` ASC
                    limit 1
                ", 
                $cronicle_doing_cron_key, 
                $hook_name 
            ) );
            if ( empty( $log_id ) ) {
                return;
            }
            $params = array( 'end' => $with_time ? microtime( true ) * CRONICLE::MICROTIME_TO_INT_MULTIPLE : 0 );
            $formats = array( '%d' );
            if ( !is_null( $result ) ) {
                $params['result'] = $result;
                $formats[] = '%s';
            }
            $wpdb->update(
                CRONICLE::table_name(),
                $params,
                array( 'id' => $log_id ),
                $formats,
                array( '%d' )
            );
        }

        if ( $key !== 'start' && $log_lifespan === 0 ) {
            // ending, and also don't keep the log
            $wpdb->delete( CRONICLE::table_name(), array( 'id' => $log_id ) );
        }
    }

    /**
     * return the inner join for querying logs.
     */
    private static function get_limits_query( $outer = false ) {
        
            return '';
       
    }

    /**
     * Return log lifespan setting taking account free vs premium and other things.
     */
    public static function get_log_lifespan() {
        $log_lifespan = cronicle_option( 'log_lifespan', CRONICLE_DEFAULT_LOG_LIFESPAN );
        if ( CRONICLE_ENABLE_MONITORING === false ) {
            $log_lifespan = 0;
        }

        return $log_lifespan;
    }

    /**
     * Return a set of logs for a hook
     */
    private function _get_logs( $hook_name, $errors_only = false ) {
        $raw_logs = $this->get_raw_logs( $errors_only );

        $ret = array();
        foreach ( $raw_logs as $row ) {
            if ( $row['hook_name'] != $hook_name ) {
                continue;
            }
            $ret[$row['cron_key']] = $row;
        }
        ksort( $ret );

        return $ret;
    }

    /**
     * Return an array for display
     */
    public function summarize_logs_by_hooks( $errors_only = false ) {
        global $wp_filter;

        $ret = array();
        $crons = self::get_cron_tasks();
        if ( empty( $crons ) ) {
            return $ret;
        }

        $logs = array();
        $raw_logs = $this->get_raw_logs( $errors_only );   
        if ( !empty( $raw_logs ) ) {
            foreach ( $raw_logs as $row ) {
                if ( empty( $logs[$row['hook_name']] ) ) {
                    $logs[$row['hook_name']] = array();
                }
                $logs[$row['hook_name']][$row['cron_key']] = $row;
            }
        }

        $hooks = array();
        foreach ( $crons as $cron ) {
            $hook_name = sanitize_key( $cron->hook );
            if ( $errors_only && empty( $logs[$hook_name] ) ) {
                continue;
            }
            $callbacks = self::get_callbacks( $cron->hook );
            $hooks[$hook_name] = array(
                'time'      => $cron->time,
                'interval'  => self::humanize_interval( $cron->interval ),
                'callbacks' => $callbacks,
                'logs'      => array()
            );
            if ( !empty( $logs[$hook_name] ) ) {
                $line_items = array();
                foreach ( $logs[$hook_name] as $row ) {
                    $line_items[] = $this->summarize_log( $row );
                }
                if ( $errors_only ) {
                    $line_items = array_filter( $line_items, function( $row ) {
                        return $row['completed'] === false;
                    } );
                }
                $hooks[$hook_name]['logs'] = $line_items;
            }
        }

        $hook_names_in_cron = array_keys( $hooks );
        // get all not in crons
        foreach ( $logs as $hook_name => $rows ) {
            $hook_name = sanitize_key( $hook_name );
            if ( in_array( $hook_name, $hook_names_in_cron ) || $hook_name == 'cronicle_wp_cron' ) {
                continue;
            }
            if ( $errors_only && empty( $logs[$hook_name] ) ) {
                continue;
            }

            $functions = array();
            if ( !empty ( $wp_filter[$hook_name] ) ) {
                $filter = $wp_filter[$hook_name];
                if ( !empty( $filter->callbacks ) ) {
                    foreach( $filter->callbacks as $callback ) {
                        $obj = current( $callback );
                        $created_callback = array(
                            'callback' => array(
                                'callback' => self::populate_callback( $obj )
                            )
                        );
                        $functions = array_merge( $functions, $created_callback);
                    }
                }
            }

            $line_items = array();
            foreach ( $rows as $log ) {
                if ( empty( $log['start'] ) ) {
                    continue;
                }
                $line_items[] = $this->summarize_log( $log );
            }
            if ( $errors_only ) {
                $line_items = array_filter( $line_items, function( $row ) {
                    return $row['completed'] === false;
                } );
            }
            $hooks[$hook_name] = array(
                'logs'       => $line_items,
                'time'       => -1,
                'callbacks'  => $functions,
                'interval'   => ''
            );
        }

        if ( $errors_only ) {
            $hooks = array_filter( $hooks, function( $row ) {
                return ( false !== array_search( false, array_column( $row['logs'], 'completed' ) ) );
            } );
        }

        return $hooks;
    }

    /**
     * Return an array for display
     */
    public function summarize_logs_by_cron( $errors_only = false ) {
        global $wpdb, $wp_filter;

        $ret = array();
        $line_items = array();

        $logged_crons = $this->_get_logs( 'cronicle_wp_cron', $errors_only );

        foreach( $logged_crons as $cron_key => $logged_cron ) {
            $ret[$cron_key] = array( 
                'summary' => $this->summarize_log( $logged_cron ),
                'hooks'   => array()
            );
        }

        $results = array();
        $raw_logs = $this->get_raw_logs( $errors_only );
        if ( !empty( $raw_logs ) ) {
            foreach ( $raw_logs as $row ) {
                if ( empty( $results[$row['hook_name']] ) ) {
                    $results[$row['hook_name']] = array();
                }
                $results[$row['hook_name']][$row['cron_key']] = $row;
            }
        }

        foreach ( $results as $hook_name => $logged_hook ) {
            $hook_name = sanitize_key( $hook_name );
            if ( $hook_name == 'cronicle_wp_cron' ) {
                continue;
            }
            foreach ( $logged_hook as $cron_key => $log ) {
                if ( isset( $ret[$cron_key] ) ) {
                    $ret[$cron_key]['hooks'][] = $this->summarize_log( $log, $hook_name );
                }
            }
        }

        $hooks = array();
        foreach( $ret as $cron_key => $data ) {
            $hooks = $data['hooks'];
            usort( $hooks, function ( $item1, $item2 ) {
                if ( $item1['start'] == $item2['start'] ) return 0;
                return $item1['start'] < $item2['start'] ? -1 : 1;
            });   
            $ret[$cron_key]['hooks'] = $hooks;
        }
        krsort( $ret );

        return $ret;
    }

    /**
     * Return a summary of one log given the raw data as an array.
     */
    private function summarize_log( $log, $hook_name = '' ) {
        $elapsed = CRONICLE::NO_VALUE;
        $completed = false;
        $in_progress = false;
        $message = '';
        $result = '';
        $log_start = $log_end = 0;
        if ( !empty( $log['start'] ) && !empty( $log['end'] ) ) {
            $completed = true;
            $result = self::RESULT_COMPLETED;
            $log_start = $log['start'] / CRONICLE::MICROTIME_TO_INT_MULTIPLE;
            $log_end = $log['end'] / CRONICLE::MICROTIME_TO_INT_MULTIPLE;
            $ms = ( $log_end - $log_start ) * 1000;
            $elapsed =  round( $ms, 1 );
            if ( !is_null( $log['result'] ) ) {
                if ( CRONICLE::is_result_error( $log['result'] ) ) {
                    $completed = false;
                    $result = self::RESULT_FAILED;
                    $message = self::results_info( $result );
                }
                else {
                    $result = $log['result'];
                    $message = self::results_info( $result );
                }
            }
        }
        else if ( !empty( $log['start'] ) ) {
            $log_start = $log['start'] / CRONICLE::MICROTIME_TO_INT_MULTIPLE;    
            $result = self::RESULT_FAILED; // for v1.1 we have no 'result'.
            if ( !is_null( $log['end'] ) && $log['end'] == 0 ) {
                // skipped
                $in_progress = false;
                $completed = true;
            }
            else {
                // might be still running
                $in_progress = ( time() - $log_start ) <= CRONICLE::CRON_TIME_ALLOWANCE;
            }
            if ( !is_null( $log['result'] ) ) {
                $result = $log['result'];
            }
            if ( !$in_progress && $result == CRONICLE::RESULT_IN_PROGRESS ) {
                // Did not complete.
                $result = CRONICLE::RESULT_INCOMPLETE;
            }
        }
        $result_error_param = !is_null( $log['result'] ) ? $log['result'] : $result;
        $ret = array(
            'start'        => $log_start,
            'elapsed'      => $elapsed,
            'completed'    => $completed,
            'hook_name'    => $hook_name,
            'in_progress'  => $in_progress,
            'result'       => $result,
            'message'      => $message,
            'caught_error' => CRONICLE::is_result_error( $result_error_param )
        );
            if ( CRONICLE::is_result_error( $result_error_param ) ) {
                $ret['error'] = $log['result'];
        }
        return $ret;
    }

    /** 
     * Return an adjusted summary of wp cron.
     */
    public function quick_wp_cron_info() {
        $crons = _get_cron_array();
        $times = array_keys( $crons );
        $next_run = min( $times );
        // $cron_logs = get_option( '_cronicle_hooks_cronicle_wp_cron' );
        $cron_logs = $this->_get_logs( 'cronicle_wp_cron' );
        $log = array();
        if ( !empty( $cron_logs ) ) {
            $log = $this->summarize_log( array_pop( $cron_logs ) );
        }
        return array( 
            'last' => $log,
            'time' => $next_run
        );
    }

    /**
     * Populate our list of cron events and store them to a class-wide variable.
     * From WP_Site_Health 5.2.0
     */
    public static function get_cron_tasks() {
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
     * Delete all cron logs that haven't run in more than a week.
     */
    public static function cleanup() {
        global $wpdb;
        $log_lifespan = self::get_log_lifespan();
        $expire_time = strtotime( '-' . $log_lifespan . ' seconds' );
        $join = self::get_limits_query( true );
        $where = " or ( ( l.cron_key is null ) and err.cron_key is null )";

            $join = '';
            $where = '';

        $wpdb->query( "
            delete t.* from " . CRONICLE::table_name() . " as t
            " . $join . "
            left outer join ( select distinct cron_key from " . CRONICLE_Error_Logs::table_name() . " ) as err
              on ( err.cron_key = t.cron_key )
            where ( ( t.cron_key < $expire_time ) and ( err.cron_key is null ) )
            " . $where . "
        " );

            if ( intval( $log_lifespan ) >= 604800 ) {
                $time_last_week = strtotime( '-1 week' );
                // get old crons
                $results = $wpdb->get_results( "
                    select last_run.hook_name 
                    from (
                        select hook_name, max( cron_key ) as max_cron_key
                        from " . CRONICLE::table_name() . "
                        group by hook_name
                    ) as last_run
                    where ( last_run.max_cron_key < $time_last_week ) ", ARRAY_A);

                if ( empty( $results ) ) {
                    return;
                }

                $crons = self::get_cron_tasks();
                $hooks = array_column( $crons, 'hook' );
                $to_delete = array();
                foreach ( $results as $row ) {
                    if ( in_array( $row['hook_name'], $hooks ) ) {
                        continue;
                    }
                    $to_delete[] = sanitize_key( $row['hook_name'] );
                }
                if ( !empty( $to_delete ) ) {
                    $wpdb->query( "delete from " . CRONICLE::table_name() . " where hook_name IN ('" . implode("', '", $to_delete ) . "')" );
                }
            }
        
        CRONICLE_Error_Logs::clear_all_sent();
    }

    /**
     * Return an array for display
     */
    public static function check_cron_completion() {
        global $wpdb;
        $last_checked = get_option( '_cronicle_last_error_check', 0 );
        $non_error_result = array_map( function( $v ) {
            return "'" . esc_sql( $v ) . "'";
        }, self::non_error_results() );
        $in_str = implode( ',', $non_error_result );
        // any logs that have no end date or result is some kind of error message
        $open_logs = $wpdb->get_results( "
            select * from " . CRONICLE::table_name() . " 
            where ( 
                    ( end IS NULL ) 
                 or ( 
                      ( coalesce( result, '' ) != '' )
                  and ( result NOT IN ( " . $in_str . " ) ) 
                  )
              )
              and ( start > " . ( ( $last_checked - CRONICLE::CRON_TIME_ALLOWANCE ) * CRONICLE::MICROTIME_TO_INT_MULTIPLE ) . " )
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
                    $include = ( ( $run_time + CRONICLE::CRON_TIME_ALLOWANCE ) < $now );
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
     * Email the user if the results for the general WP Cron system is bad
     */
    public static function notify_user() {
        $errors = CRONICLE_Error_Logs::get_errors();
        if ( empty( $errors ) ) {
            return;
        }

        $email_address = cronicle_get_email_address();
        if ( empty( $email_address ) ) {
            return;
        }
        $time_in_minutes = self::CRON_TIME_ALLOWANCE / 60;
        $minutes = sprintf( _n( '%s minute', '%s minutes', $time_in_minutes, 'cronicle' ), $time_in_minutes );

        $msg = '<p>';
        $msg .= __( 'WP-Cron started but failed to complete.  Normally a failure here or there is not something to be concerned about.  You may want to look into the failures if they happen fairly consistently.' );
        $msg .= '</p><p>';
        $msg .= sprintf( __( 'The reason for a failed cron could be one of many factors.  It could indicate a coding error due to a plugin or conflict of plugins, it could indicate your server ran out of resources, or it could just mean the event did not finish within %s' ), $minutes );
        $msg .= '</p>';

        $msg .= '<p>' . sprintf( __( 'The following hooks failed to complete.  This can also be seen in the Tools -> WP Cron Status page on %s' ), site_url() ) . '</p>';
        $msg .= '<ul>';
        foreach ( $errors as $cron_key => $hook_names ) {
            $time = round( $cron_key );
            $msg .= '<li>' . date_i18n( 'm/d/Y h:ia', CRONICLE::utc_to_blogtime( $time ) );
            $msg .= '<ul>';
            foreach ( $hook_names as $hook_name ) {
                if ( $hook_name == 'cronicle_wp_cron' ) {
                    continue;
                }
                $msg .= '<li>' . esc_html( $hook_name ) . '</li>';
            }
            $msg .= '</ul></li>';
        }
        $msg .= '</ul>';

        $msg .= sprintf( __( '<p>This message has been sent from %s by the WP-Cron Status Checker plugin.  You can change the email address in your WordPress admin section under Settings -> WP Cron Status.</p>', 'cronicle' ), site_url() );
        $headers = array(' Content-Type: text/html; charset=UTF-8' );

        wp_mail( $email_address, 
            get_bloginfo( 'name' ) . ' - ' . __( 'WP-Cron Failed to Complete!', 'cronicle' ),
            $msg,
            $headers );
        CRONICLE_Error_Logs::mark_errors_sent( array_keys( $errors ) );
    }

    /**
     * Return a timestamp in the blog's timezone give a timestamp from UTC 
     */
    public static function utc_to_blogtime( $timestamp ) {
        $timestamp = (int) $timestamp;
        try {
            // get datetime object from unix timestamp
            $datetime = new DateTime( "@{$timestamp}", new DateTimeZone( 'UTC' ) );
         
            // set the timezone to the site timezone
            $datetime->setTimezone( new DateTimeZone( self::get_timezone_string() ) );
         
            // return the unix timestamp adjusted to reflect the site's timezone
            return $timestamp + $datetime->getOffset();
         
        } catch ( Exception $e ) {
             
            // something broke
            return 0;
        }
    }
    public static function blogtime_to_utc( $datetime_string = 'now', $type = 'mysql' ) {
        try {
            // get datetime object from site timezone
            $datetime = new DateTime( $datetime_string, new DateTimeZone( prptb_get_timezone_string() ) );
            $datetime->setTimezone( new DateTimeZone( 'UTC' ) );
            if ( $type == 'mysql' ) {
                return $datetime->format( 'Y-m-d H:i:s' );
            }
            else if ( $type == 'timestamp' ) {
                return $datetime->format( 'U' );
            }
            return $datetime;
         
        } catch ( Exception $e ) {
             
            // you'll get an exception most commonly when the date/time string passed isn't a valid date/time
            return 0;
        }
    }

    /**
     * Returns the timezone string for a site, even if it's set to a UTC offset
     *
     * @return string valid PHP timezone string
     */
    public static function get_timezone_string() {
     
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
     * From a number in seconds return a human readable string in minutes, hours, days.
     */
    public static function humanize_interval( $interval ) {
        if ( empty( $interval ) ) {
            return 'Once';
        }
        if ( $interval < 60 ) {
            return sprintf( _n( 'Every %s', 'Every %s', $interval, 'cronicle' ), self::humanize_seconds( $interval ) );
        }
        else if ( $interval < 3600 ) {
            return sprintf( _n( 'Every %s', 'Every %s', $interval / 60, 'cronicle' ), self::humanize_seconds( $interval ) );
        }
        else if ( $interval < 86400 ) {
            return sprintf( _n( '%s', 'Every %s', $interval / 60 / 60, 'cronicle' ), self::humanize_seconds( $interval ) );
        }
        else {
            return sprintf( _n( 'Once Daily', 'Every %s', $interval / 60 / 60 / 24, 'cronicle' ), self::humanize_seconds( $interval ) );
        }
    }

    /**
     * From a number in seconds return a human readable string in minutes, hours, days.
     */
    public static function humanize_seconds( $number ) {
        if ( empty( $number ) ) {
            return '';
        }
        if ( $number < 60 ) {
            return sprintf( _n( '%s second', '%s seconds', $number, 'cronicle' ), $number );
        }
        else if ( $number < 3600 ) {
            $number = $number / 60;
            return sprintf( _n( '%s minute', '%s minutes', $number, 'cronicle' ), $number );
        }
        else if ( $number < 86400 ) {
            $number = $number / 60 / 60;
            return sprintf( _n( 'hour', '%s hours', $number, 'cronicle' ), $number );
        }
        else {
            $number = $number / 60 / 60 / 24;
            return sprintf( _n( 'day', '%s days', $number, 'cronicle' ), $number );
        }
    }

    /**
     * Return the raw data for all hooks saved.
     */
    private function get_raw_logs( $errors_only = false ) {
        global $wpdb;
        if ( $errors_only ) {
            return $this->get_error_logs();
        }
        if ( isset( $this->raw_logs ) ) {
            return $this->raw_logs;
        }
        $log_lifespan = self::get_log_lifespan();
        // cron key is not microtime
        $expire_time = strtotime( '-' . $log_lifespan . ' seconds' );
        $results = $wpdb->get_results( "
            select t.* 
            from " . CRONICLE::table_name() . " as t
            " . self::get_limits_query() . "
            where ( t.cron_key > $expire_time )
        ", ARRAY_A );
        $this->raw_logs = $results;
        return $this->raw_logs;
    }

    /**
     * Return the raw data for all hooks that errored
     */
    private function get_error_logs() {
        global $wpdb;
        return $wpdb->get_results( "
            select t.* 
            from " . CRONICLE::table_name() . " as t
            inner join ( select distinct cron_key from " . CRONICLE_Error_Logs::table_name() . " ) as err
              on ( err.cron_key = t.cron_key )
        ", ARRAY_A );
    }
    
    /**
     * From WP Crontrol
     * Return a formatted version of the callbacks.
     */
    private static function get_callbacks( $name ) {
        global $wp_filter;

        $actions = array();

        if ( isset( $wp_filter[ $name ] ) ) {

            # http://core.trac.wordpress.org/ticket/17817
            $action = $wp_filter[ $name ];

            foreach ( $action as $priority => $callbacks ) {
                foreach ( $callbacks as $callback ) {
                    $callback = self::populate_callback( $callback );

                    $actions[] = array(
                        'priority'  => $priority,
                        'callback'  => $callback,
                    );
                }
            }
        }

        return $actions;
    }

    /**
     * From WP Crontrol
     * Retrun a formatted version of the callbacks.
     */
    private static function populate_callback( array $callback ) {

        // If Query Monitor is installed, use its rich callback analysis:
        if ( method_exists( 'QM_Util', 'populate_callback' ) ) {
            return QM_Util::populate_callback( $callback );
        }

        if ( is_string( $callback['function'] ) && ( false !== strpos( $callback['function'], '::' ) ) ) {
            $callback['function'] = explode( '::', $callback['function'] );
        }

        if ( is_array( $callback['function'] ) ) {
            if ( is_object( $callback['function'][0] ) ) {
                $class  = get_class( $callback['function'][0] );
                $access = '->';
            } else {
                $class  = $callback['function'][0];
                $access = '::';
            }

            $callback['name'] = $class . $access . $callback['function'][1] . '()';
        } elseif ( is_object( $callback['function'] ) ) {
            if ( is_a( $callback['function'], 'Closure' ) ) {
                $callback['name'] = 'Closure';
            } else {
                $class = get_class( $callback['function'] );
                $callback['name'] = $class . '->__invoke()';
            }
        } else {
            $callback['name'] = $callback['function'] . '()';
        }

        return $callback;

    }

    /**
     * Schedule the email notice event.
     */
    public static function schedule_email_notice_hook() {
        if (! wp_next_scheduled ( 'cronicle_email_notice_hook' )) {
            wp_schedule_event( time(), 'cronicle_email_interval', 'cronicle_email_notice_hook' );
        }
    }

    /**
     * Unschedule the email notice event.
     */
    public static function unschedule_email_notice_hook() {
        wp_clear_scheduled_hook( 'cronicle_email_notice_hook' );
    }

    /**
     * Return true if the result is an error and not our status.
     */
    public static function is_result_error( $result ) {
        return $result != self::RESULT_FAILED && !in_array( $result, self::non_error_results() );
    }

    /**
     * Return non error results
     */
    public static function non_error_results() {
        return array(
            self::RESULT_SKIPPED,
            self::RESULT_COMPLETED,
            self::RESULT_EXITED,
            self::RESULT_IN_PROGRESS
        );
    }

    /**
     * Return info on what a status means.
     */
    public static function results_info( $result ) {
        $info = '';
        switch ( $result ) {
            case self::RESULT_SKIPPED:
                $info = __( 'This hook was skipped because of a setting or added filter.', 'cronicle' );
                break;
            case self::RESULT_COMPLETED:
                $info = __( 'This hook completed successfully.', 'cronicle' );
                break;
            case self::RESULT_EXITED:
                $info = __( 'This hook completed without errors.  Most likely this hook was designed to exit early and not go through the full WordPress process.', 'cronicle' );
                break;
            case self::RESULT_IN_PROGRESS:
                $info = __( 'This hook may still be currently running.', 'cronicle' );
                break;
            case self::RESULT_FAILED:
                $info = __( 'This hook failed to complete.', 'cronicle' );
                break;
            
            default:
                # code...
                break;
        }
        return $info;
    }

}