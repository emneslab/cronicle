<?php
class CRONICLE {


    /**
     * Log the time for the start of the hook.
     */
    public static function start_log( $hook_name, $with_time = true, $result = 'in progress' ) {
        self::_log( $hook_name, 'start', $with_time, $result );
    }

    /**
     * Log the time for the end of the hook.
     */
    public static function end_log( $hook_name, $with_time = true, $result = null ) {
        self::_log( $hook_name, 'end', $with_time, $result );
    }

    /**
     * Log hook's start or end time.  if end is already set, don't log it.
     */
    private static function _log( $hook_name, $key = 'start', $with_time = true, $result = null ) {
        global $cronicle_doing_cron_key, $wpdb;
        $hook_name =  sanitize_key( $hook_name );
      
        if ( $key == 'start' ) {
            $params = array( 
                    'cron_key'  => $cronicle_doing_cron_key, 
                    'hook_name' => $hook_name,
                    'start'     => $with_time ? microtime( true ) * 10000 : 0
            );
            $format = array( '%s', '%s', '%d' );
            if ( !is_null( $result ) ) {
                $params['result'] = $result;
                $format[] = '%s';
            }
            $wpdb->insert( 
                'wp_cronicle_logs',
                $params, 
                $format
            );
        }
        else {
            // only log end time if end is null
            // if end is 0 it means we are already logged it and not keeping track of elapsed time.
            $log_id = $wpdb->get_var( 
                $wpdb->prepare( "
                    select id from wp_cronicle_logs 
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
            $params = array( 'end' => $with_time ? microtime( true ) * 10000 : 0 );
            $formats = array( '%d' );
            if ( !is_null( $result ) ) {
                $params['result'] = $result;
                $formats[] = '%s';
            }
            $wpdb->update(
                'wp_cronicle_logs',
                $params,
                array( 'id' => $log_id ),
                $formats,
                array( '%d' )
            );
        }


    }

}