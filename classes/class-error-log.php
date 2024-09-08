<?php
/**
 * Just a wrapper to get the errors
 */
class CRONICLE_Error_Logs {

    /**
     * Return the table name.
     */


    /**
     * add logs
     */
    public static function add_error( $cron_key, $hook_name = '' ) {
        global $wpdb;

        $num = $wpdb->get_var( $wpdb->prepare( "
            select count(*) 
            from wp_cronicle_error_logs
            where ( cron_key = %s ) and ( hook_name = %s )
        ", $cron_key, $hook_name ) );
        if ( $num > 0 ) {
            return;
        }

        $wpdb->insert( 
            'wp_cronicle_error_logs',
            array( 
                'cron_key'  => $cron_key, 
                'hook_name' => $hook_name
            ), 
            array( '%s', '%s' )
        );
    }

    /**
     * Get all logs that haven't been sent unless $all = true.
     */
    public static function get_errors() {
        global $wpdb;
        $results = $wpdb->get_results( "select * from wp_cronicle_error_logs where ( sent_date IS NULL )", ARRAY_A );
        $ret = array();
        foreach ( $results as $row ) {
            if ( empty( $ret[$row['cron_key']] ) ) {
                $ret[$row['cron_key']] = array();
            }
            $ret[$row['cron_key']][] = $row['hook_name'];
        }

        return $ret;
    }

    /**
     * Mark the errors with cron_keys as sent.
     */
    public static function mark_errors_sent( $cron_keys ) {
        global $wpdb;

        if ( empty( $cron_keys ) ) {
            return;
        }
        $cron_keys = array_map( function( $v ) {
            return "'" . esc_sql( $v ) . "'";
        }, $cron_keys);
        $cron_keys = implode( ',', $cron_keys );
        $wpdb->query( $wpdb->prepare( "update wp_cronicle_error_logs SET sent_date = %s where ( cron_key IN( " . $cron_keys . " ) )", current_time( 'mysql', 1 ) ) );
    }

    /**
     * Delete all the sent errors
     */
    public static function clear_all_sent() {
        global $wpdb;
        $log_lifespan = CRONICLE::get_log_lifespan();
        $expire_time = strtotime( '-' . $log_lifespan . ' seconds' );
        $email_address = cronicle_get_email_address();
        if ( !empty( $email_address ) ) {
            $where = "( sent_date IS NOT NULL ) and ( sent_date <= FROM_UNIXTIME( " . $expire_time . " ) )";
        }
        else {
            $where = "( cron_key < $expire_time )";
        }
        $wpdb->query( "delete from wp_cronicle_error_logs where " . $where );
    }
}
