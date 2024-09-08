<?php
    $cron_logs = $cronicle->summarize_logs_by_hooks();
    $data_count = count( $cron_logs );
    $max_rows = $data_count > 6  ? ceil( $data_count / 2 ) : $data_count;
    $r = 0;
    $log_lifespan = cronicle_option( 'log_lifespan', CRONICLE_DEFAULT_LOG_LIFESPAN );
    ?>
    <p class="intro">
        <?php if ( empty( $log_lifespan ) ): ?>
            <strong><?php _e( 'Logs are currently not being kept.', 'cronicle' ); ?></strong>
            <a href="<?php echo esc_url( menu_page_url( CRONICLE_OPTIONS_PAGE_ID, false ) ); ?>"><?php _e( 'Enable logging here.', 'cronicle' ); ?></a>
            <br>
        <?php else : ?>
        <?php _e( 'Below are the hooks that are scheduled to run or have recently run.  If hooks are not run in over a week, the log for the hooks are removed.', 'cronicle' ); ?>
        <?php endif; ?>
    </p>
    
    <?php if ( !empty( $log_lifespan ) ): ?>

    <p class="toggle-all-block">
        <a href="#" class="toggle-all-link"><span class="expand">[+] <?php _e( 'Expand All', 'cronicle' ); ?></span><span class="collapse">[-] <?php _e( 'Collapse All', 'cronicle' ); ?></span></a>
    </p>

    <?php require( 'status-page-by-hooks-base.php' ); ?>

    <?php endif; ?>


