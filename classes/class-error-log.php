<?php
/**
 * Just a wrapper to get the errors
 */
class CRONICLE_Error_Logs {


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
}
