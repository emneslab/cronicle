<?php
/*********************************
 * Options page
 *********************************/

// don't load directly
if ( !defined('ABSPATH') )
    die('-1');

/**
 *  Add menu page
 */
function cronicle_options_add_page() {
    $cronicle_hook = add_options_page( 'WP Cron Status Checker Settings', // Page title
                      'WP Cron Status', // Label in sub-menu
                      'manage_options', // capability
                      CRONICLE_OPTIONS_PAGE_ID, // page identifier 
                      'cronicle_options_do_page' ); // call back function name
                      
    add_action( "admin_enqueue_scripts-" . $cronicle_hook, 'cronicle_admin_scripts' );
}
add_action('admin_menu', 'cronicle_options_add_page');

/**
 * Init plugin options to white list our options
 */
function cronicle_options_init() {
    register_setting( 'cronicle_options_options', CRONICLE_OPTIONS_NAME, 'cronicle_options_validate' );
}
add_action('admin_init', 'cronicle_options_init' );

/**
 * Draw the menu page itself
 */
function cronicle_options_do_page() {
    if ( isset( $_GET['cronicle_message'] ) ) {
        $messages = cronicle_messages();
        $msg_idx = wp_unslash( $_GET['cronicle_message'] );
        echo sprintf( '<div id="message" class="updated notice is-dismissible"><p>%s</p></div>', esc_html( $messages[$msg_idx]) );
    }
    
    $free_only = false;
    $free_label =  ' ' . __( '(PRO only)', 'cronicle' );
    $class_pro_only = $free_only ? 'cronicle-pro-only' : '';
?>
    <style>
    .cronicle-options-page ul {
        list-style: disc;
        margin-left: 20px;
        padding-left: 20px;
    }
    .cronicle-email-flag-list {
        margin-top: 2px;
    }
    .cronicle-options-page input[type="text"] {
        width: 320px;
        max-width: 100%;
    }
    .cronicle-hint {
        font-size: 13px;
        color: #555d66;
        font-style: italic;
    }
    .cronicle-options-page textarea  {
        width: 320px;
        height: 100px;
        max-width: 100%;
    }
    .cronicle-options-page textarea.not-active  {      
        background:#e0e0e0;
    }
    .cronicle-pro-only {
        color: #919aa5;
    }
    </style>
    <div class="wrap cronicle-options-page">
            <div class="cronicle-header">
                <div class="cronicle-description">
                <h2>WP Cron Status Checker Settings</h2>
                </div>
            </div>
            <div class="clear"></div>
            <hr>
            <form method="post" action="options.php">
                <?php settings_fields( 'cronicle_options_options' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Email Address', 'cronicle' ); ?></th>
                        <td>
                            <fieldset>
                                <p>
                                    <input type="text" name="<?php echo CRONICLE_OPTIONS_NAME;?>[email]" value="<?php echo cronicle_get_email_address(); ?>" data-default-value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
                                </p>
                                <p>
                                    Get an email if
                                    <ul class="cronicle-email-flag-list">
                                        <li>WordPress cannot run WP-Cron due to a general server, permissions, or setup issue.</li>
                                        <li>A WP-Cron hook does not complete due to an error specific to that hook.</li>
                                    </ul>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr class="email-types-row">
                        <th scope="row"><?php _e( 'Incomplete Status', 'cronicle' ); ?></th>
                        <td>
                            <p><?php _e( 'On occasion a plugin doesn\'t go through the entire PHP process.  Sometimes this is on purpose and sometimes it\'s because of timeout or other errors.', 'cronicle' ); ?></p>
                            <fieldset>
                                <p>
                                    <?php
                                        $incomplete_not_error = cronicle_option( 'incomplete_not_error', 0 );
                                    ?>
                                    <label for="status-incomplete"><input id="status-incomplete" type="checkbox" name="<?php echo CRONICLE_OPTIONS_NAME;?>[incomplete_not_error]" value="1" <?php checked( true, $incomplete_not_error == 1 ); ?>> <?php _e( 'Consider incomplete statuses to not be an error for all hooks', 'cronicle' ); ?></label>
                                    <br>
                                </p>
                            </fieldset>
                            - OR -
                            <br>
                            <p class="<?php echo $class_pro_only; ?>">
                                <?php _e( 'List any hooks that are being marked incomplete, but should not be considered errors.', 'cronicle' ); ?><br><span class="cronicle-hint <?php echo $class_pro_only; ?>"><?php _e( 'One hook name per line', 'cronicle' ); ?></span>
                            </p>
                            <textarea <?php echo $free_only ? 'disabled="disabled"' : '' ?> name="<?php echo CRONICLE_OPTIONS_NAME;?>[incomplete_not_error_hooks]"><?php echo esc_textarea( cronicle_option( 'incomplete_not_error_hooks', '' ) ) ?></textarea>
                            
                        </td>
                    </tr>
                    <tr class="email-freq-row">
                        <th scope="row"><?php _e( 'Email Frequency', 'cronicle' ); ?></th>
                        <td>
                            <fieldset>
                                <p>
                                    <?php 
                                        $email_frequency = cronicle_option( 'email_frequency', false ); 
                                        $email_options = cronicle_email_frequencies();
                                    ?>
                                    <select name="<?php echo CRONICLE_OPTIONS_NAME;?>[email_frequency]">
                                    <?php foreach ( $email_options as $val => $label ) : 
                                        $disabled = false;
                                        if ( $free_only ) {
                                            if ( $val < 86400 ) {
                                                $val = 86400;
                                                $label .= $free_label;
                                                $disabled = true;
                                            }
                                        }
                                    ?>
                                        <option value="<?php echo (int) $val?>" <?php selected( $val, $email_frequency ) ?> <?php disabled( $disabled, true ); ?>><?php echo $label ?></option>
                                    <?php endforeach; ?>
                                    </select>
                                    <br>
                                    <span class="cronicle-hint"><?php _e( 'The minimum amount of time between emails.', 'cronicle' ); ?></span>
                                    
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr class="keep-logs-row">
                        <th scope="row"><?php _e( 'Keep logs', 'cronicle' ); ?></th>
                        <td>
                            <fieldset>
                                <p>
                                    <?php 
                                        $log_lifespan = cronicle_option( 'log_lifespan', CRONICLE_DEFAULT_LOG_LIFESPAN ); 
                                        $lifespan_options = cronicle_log_lifespans();
                                    ?>
                                    <select name="<?php echo CRONICLE_OPTIONS_NAME;?>[log_lifespan]">
                                    <?php foreach ( $lifespan_options as $val => $label ) : 
                                        $disabled = false;
                                        if ( $free_only ) {
                                            if ( $val > CRONICLE_DEFAULT_LOG_LIFESPAN ) {
                                                $val = CRONICLE_DEFAULT_LOG_LIFESPAN - 1;
                                                $label .= $free_label;
                                                $disabled = true;
                                            }
                                        }
                                    ?>
                                        <option value="<?php echo (int) $val; ?>" <?php selected( $val, $log_lifespan ) ?> <?php disabled( $disabled, true ); ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                    </select>
                                    <br>
                                    <span class="cronicle-hint"><?php _e( 'The amount of time to keep the logs.', 'cronicle' ); ?></span>
                                    
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr class="keep-logs-row">
                        <th scope="row"><?php _e( 'Cleanup', 'cronicle' ); ?></th>
                        <td>
                            <fieldset>
                                <p>
                                    <?php 
                                        $delete_data_too = cronicle_option( 'delete_data_too', 0 ); 
                                    ?>
                                    <label for="delete-data-too"><input id="delete-data-too" type="checkbox" name="<?php echo CRONICLE_OPTIONS_NAME;?>[delete_data_too]" value="1" <?php checked( true, $delete_data_too == 1 ); ?>> <?php _e( 'Delete all tables created from this plugin when this plugin is deleted.', 'cronicle' ); ?></label>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                </table>
                <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save All') ?>" />
                </p>
        </form>
        <br>
        <hr>
        <br>
        <form name="clear-form" method="post" action="">
            <?php wp_nonce_field( 'cronicle_clear_logs' ); ?>
            <input type="hidden" name="cronicle_clear_logs" value="1">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Clear all logs', 'cronicle' ); ?></th>
                        <td>
                            <fieldset>
                                <p>
                                    <?php _e( 'Start from a clean slate and remove all logging from this plugin.', 'cronicle' ); ?>
                                </p>
                                <p>
                                    <input type="submit" class="button" value="Clear Logs">
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                </table>

        </form>
    </div>
    <script type="text/javascript">
        (function($) {
            $('form[name="clear-form"]').on('submit', function(e){
                if (!confirm('Once logs are cleared, there is no way to retrieve them unless you have a backup of your database.')) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        })(jQuery);
    </script>
    <?php 
}

    /**
     * Clear all the logs
     */
     function clear_logs() {
        global $wpdb;
        $wpdb->query( "delete from wp_cronicle_logs" );
        $wpdb->query( "delete from wp_cronicle_error_logs" );
    }




/**
 * Sanitize and validate input. Accepts an array, return a sanitized array.
 */
function cronicle_options_validate($input) {
    global $wp_settings_errors;

    // clear logs when no logs should be kept
    if ( isset( $input['log_lifespan'] ) && $input['log_lifespan'] == 0 ) {
        clear_logs();
    }

    if ( !isset( $input['incomplete_not_error'] ) ) {
        $input['incomplete_not_error'] = 0;
    }

    if ( !isset( $input['delete_data_too'] ) ) {
        $input['delete_data_too'] = 0;
    }

    add_action( 'updated_option', 'cronicle_options_updated', 10, 3 );
    add_action( 'added_option', 'cronicle_options_updated', 10, 2 );

    return $input;
}


/**
 * Enqueue Scripts
 */
function cronicle_admin_scripts() {
    do_action ('cronicle_admin_scripts');
}

/**
 * Enqueue scripts for the admin side.
 */
function cronicle_enqueue_scripts($hook) {
    if( 'settings_page_cronicle-options' != $hook )
        return;
    /*
    wp_enqueue_style( 'cronicle-options',
        plugins_url( '/css/options.css', CRONICLE_PLUGIN ),
        array( ),
        CRONICLE_VERSION );
    */
}
add_action( 'admin_enqueue_scripts', 'cronicle_enqueue_scripts' );

/**
 * clear the logs
 */
function cronicle_maybe_clear_logs() {
    global $pagenow;
    if ( $pagenow == 'options-general.php' ) {
        if ( !empty( $_POST['cronicle_clear_logs'] ) && current_user_can( 'manage_options' ) ) {
            check_admin_referer( 'cronicle_clear_logs' );
            $args = array(
                'cronicle_message' => '1'
            );
            clear_logs();
            wp_safe_redirect( add_query_arg( $args, menu_page_url( CRONICLE_OPTIONS_PAGE_ID, false ) ) );
        }
    }
}
add_action( 'admin_init', 'cronicle_maybe_clear_logs' );


/**
 * Filter list of removable query args. 
 */
function cronicle_removable_query_args( $args ) {
    return array_merge( $args, array(
        'cronicle_message'
    ) );
}
add_filter( 'removable_query_args', 'cronicle_removable_query_args' );

