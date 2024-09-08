<?php
    $cron_logs = $cronicle->summarize_logs_by_hooks( true );
    $data_count = count( $cron_logs );
    $max_rows = $data_count > 6  ? ceil( $data_count / 2 ) : $data_count;
    $r = 0;
    $log_lifespan = cronicle_option( 'log_lifespan', CRONICLE_DEFAULT_LOG_LIFESPAN );
    ?>
    <p class="intro">
        <?php _e( 'Recent uncompleted crons grouped by hooks. ', 'cronicle' ); ?><br>
        <?php if ( $data_count == 0 ) : ?>
        <br><br><?php _e( 'Awesome!  No uncompleted cron logs.', 'cronicle' ); ?>
        <?php else : ?>    
        <?php _e( 'Note: Sometimes crons can take longer than expected to complete.  If you got an email saying a cron did not complete, but shows completed here, it may just have completed after the email was sent.', 'cronicle' );  ?>
        <?php endif; ?>
    </p>
    
    <?php if ( !empty( $data_count ) ): ?>

    <p class="toggle-all-block">
        <a href="#" class="toggle-all-link"><span class="expand">[+] <?php _e( 'Expand All', 'cronicle' ); ?></span><span class="collapse">[-] <?php _e( 'Collapse All', 'cronicle' ); ?></span></a>
    </p>

    <?php require( 'status-page-by-hooks-base.php' ); ?>

    <?php endif; ?>
