<?php

/**
 * Setup all the admin stuff.
 */
function cronicle_admin_init() {
    add_action('wp_dashboard_setup', 'cronicle_dashboard_widget' );
}
add_action( 'admin_init', 'cronicle_admin_init' ); 

/**
 * Add dashboard widget
 */
function cronicle_dashboard_widget() {
    $title = 'WP-Cron Status Checker';
    if ( current_user_can( 'manage_options' ) ) {
        $title .= '<span class="cronicle-title-email"><a href="' . esc_url( menu_page_url( CRONICLE_OPTIONS_PAGE_ID, false ) ) . '" style="text-decoration: none; font-weight: normal;" title="Settings"><span class="dashicons dashicons-admin-generic"></span></a></span>';   
    }
    wp_add_dashboard_widget('dashboard_cronicle_widget', $title, 'cronicle_dashboard_widget_output');
}

/**
 * Show the status and check button.
 */
function cronicle_dashboard_widget_output() { 
    $link = '<span class="spinner"></span> <a id="cronicle-force-check" href="#" class="check-status-link">' . __( 'You can also click here to check it now.', 'cronicle' ) . '</a>';
    echo '<p>' . sprintf( __( 'The ability for the WP-Cron system to run will be automatically checked once every 24 hours.  %s', 'cronicle' ), $link ) . '</p>';

    $time_in_minutes = CRONICLE::CRON_TIME_ALLOWANCE / 60;
    $minutes = sprintf( _n( '%s minute', '%s minutes', $time_in_minutes, 'cronicle' ), $time_in_minutes );
    echo '<p>' . sprintf( __( 'Whenever WP-Cron takes longer than %s to complete, it\'s assumed to have failed.  You\'ll get an email to check the WP Cron Status page once the failure is detected.', 'cronicle' ), $minutes ) . '</p>';
    echo '<div class="cronicle-status-container">' . cronicle_dashboard_get_status() . '</div>';
?>
    <a class="btn-log button" href="<?php echo admin_url( 'tools.php?page=cronicle_status' ); ?>"><?php echo __( 'View Logs', 'cronicle' ); ?></a>
<?php
}

/**
 * Return the dashboard friendly status.
 */
function cronicle_dashboard_get_status() {
    if ( $status = get_option( 'cronicle_status' ) ) {
        
        // use the site health way instead of _cronicle_hooks_status.

        $last_run = get_option( '_cronicle_last_run' );
        $cronicle = new CRONICLE();
        $info = $cronicle->quick_wp_cron_info();
        if ( !empty( $info['last'] ) ) {
            $date_format = get_option( 'date_format' );
            $time_format = get_option( 'time_format' );
            $time_string = date( $date_format . ' ' . $time_format, CRONICLE::utc_to_blogtime( (int) $info['last']['start'] ) );
            if ( $info['last']['completed'] === false ) {
                $msg = __( '<span class="cronicle-label">WP Cron failed to complete:</span><span class="cronicle-value">%s</span>' );
                $status .= '<span class="cronicle-status cronicle-error">' . sprintf( $msg, $time_string ) . '</span>';
            }
            else {
                $msg = __( '<span class="cronicle-label">Last time WP Cron succeeded:</span><span class="cronicle-value">%s</span>' );
                $status .= '<span class="cronicle-status cronicle-success">' . sprintf( $msg, $time_string ) . '</span>';
            }
        }
        return $status;
    }
    else {
        return __( 'WP-Cron Status Checker has not run yet.', 'cronicle' );
    }
}

/**
 * Enqueue the scripts
 */
function cronicle_dashboard_widget_enqueue( $hook ) {
    if( 'index.php' != $hook && 'options-general.php' != $hook ) {
        return;
    }

    wp_enqueue_style( 'cronicle-dashboard-widget', 
        plugins_url( '/assets/css/dashboard.css', CRONICLE_PLUGIN ),
        array(),
        CRONICLE_VERSION );

    wp_enqueue_script( 'cronicle-dashboard-widget', 
        plugins_url( '/assets/js/dashboard.js', CRONICLE_PLUGIN ), 
        array( 'jquery' ), 
        CRONICLE_VERSION,
        true );

    wp_localize_script( 'cronicle-dashboard-widget', 'cronicle', array(
        'ajaxurl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'cronicle-nonce' )
    ) );
}
add_action( 'admin_enqueue_scripts', 'cronicle_dashboard_widget_enqueue' );

/**
 * Force check the status.
 */
function cronicle_ajax_check() {
    if ( !check_ajax_referer('cronicle-nonce', 'nonce', false) ){
        die(); 
    }
    cronicle_run( true );
    $html = cronicle_dashboard_get_status();
    wp_send_json( array( 'html' => $html ) );
}
add_action('wp_ajax_cronicle-force-check', 'cronicle_ajax_check');
