<?php

/**
 * Add a menu item to the tools menu.
 */
function cronicle_add_menu() {
    add_management_page( 'WP Cron Status', 'WP Cron Status', 'manage_options', 'cronicle_status', 'cronicle_status_page' );
}
add_action( 'admin_menu', 'cronicle_add_menu' );


/**
 * Enqueue the styls.
 */
function cronicle_admin_enqueue($hook) {
    if( stripos($hook, 'tools_page_cronicle_status' ) === FALSE)
        return;

    wp_enqueue_style( 'wp-pointer' );
    wp_enqueue_script( 'wp-pointer' );

    wp_enqueue_style( 'cronicle_style', 
        plugins_url('/assets/css/status-page.css', CRONICLE_PLUGIN ), 
        CRONICLE_VERSION 
    );

    wp_enqueue_script( 'cronicle_script', 
        plugins_url('assets/js/status-page.js', CRONICLE_PLUGIN ), 
        array( 'jquery' ),
        CRONICLE_VERSION,
        true 
    );
}
add_action( 'admin_enqueue_scripts', 'cronicle_admin_enqueue' );

/**
 * Output the status page.
 */
function cronicle_status_page() {
    $cronicle = new CRONICLE();
    $now = time();
    ?>
    <div class="wrap">
        <h2>WP Cron Status</h2>
        <div class="cronicle-status-page">
            <div class="cronicle-menu subsubsub">
                <a href="#" data-view="cron" class="current">View by WP-Cron</a>
                <span class="divider">|</span>
                <a href="#" data-view="hooks">View by Hooks</a>
                <span class="divider">|</span>
                <a href="#" data-view="cron-errors">View Uncompleted by WP-Cron</a>
                <span class="divider">|</span>
                <a href="#" data-view="hooks-errors">View Uncompleted by Hooks</a>
            </div>

            <?php

           // dump(\Emneslab\ORM\Support\Facades\DB::table('cronicle_logs')->get()->toArray());
            //dump(\Emneslab\ORM\Support\Facades\DB::table('cronicle_error_logs')->get()->toArray());




            /*
            $lock =  get_transient( 'doing_cron' );
            dump($lock);

            global $wpdb;
            $results = $wpdb->get_results("SHOW TABLES", ARRAY_N);
            dump($results);
            $results = $wpdb->get_results( "SELECT * FROM wp_cronicle_logs", ARRAY_A );
            dump($results);
            $results = $wpdb->get_results( "SELECT * FROM wp_cronicle_error_logs", ARRAY_A );
            dump($results);

            $crons = _get_cron_array();

            dump($crons);
            $schedules = wp_get_schedules();
            dump($schedules);

   



                // Get the cron array
                $crons = _get_cron_array();
            
                // Check if there are any cron events
                if ( empty( $crons ) ) {
                    echo 'No cron jobs are registered.';
                    return;
                }
            
                // Array to store all the cron hooks
                $cron_hooks = [];
            
                // Loop through each cron job
                foreach ( $crons as $timestamp => $cron ) {
            dump($timestamp);
            dump($cron);

                    foreach ( $cron as $hook => $data ) {
            dump($data);

                        if ( !in_array( $hook, $cron_hooks ) ) {
                            $cron_hooks[] = $hook;
                        }
                    }
                }
            
            dump($cron_hooks);
                



*/


            ?>


            <div class="status-view status-by-cron current">
                <?php require_once( 'status-page-by-cron.php' ); ?>
            </div>
            <div class="status-view status-by-hooks">
                <?php require_once( 'status-page-by-hooks.php' ); ?>
            </div>
            <div class="status-view status-by-cron-errors">
                <?php require_once( 'status-page-by-cron-errors.php' ); ?>
            </div>
            <div class="status-view status-by-hooks-errors">
                <?php require_once( 'status-page-by-hooks-errors.php' ); ?>
            </div>
        </div>
    </div>
<?php
}