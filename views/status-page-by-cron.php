<?php
    $cron_logs = $cronicle->summarize_logs_by_cron();
    $data_count = count( $cron_logs );
?>
<p class="intro">
    <?php 
    $log_lifespan = cronicle_option( 'log_lifespan', CRONICLE_DEFAULT_LOG_LIFESPAN );
    ?>

    <?php if ( empty( $log_lifespan ) ): ?>
        <strong><?php _e( 'Logs are currently not being kept.', 'cronicle' ); ?></strong>
        <a href="<?php echo esc_url( menu_page_url( CRONICLE_OPTIONS_PAGE_ID, false ) ); ?>"><?php _e( 'Enable logging here.', 'cronicle' ); ?></a>
        <br>
    <?php else : ?>
    <?php printf( __( 'See WP-Cron runs for the last %s.  You can see how long each of the triggered hooks took to complete.', 'cronicle' ), CRONICLE::humanize_seconds( $log_lifespan ) );  ?>
    <?php endif; ?>
    <?php if ( $data_count == 0 ) : ?>
    <br><br>No logs yet.
    <?php endif; ?>
</p>
<?php if ( $data_count > 0 ) : ?>
<p class="toggle-all-block">
    <a href="#" class="toggle-all-link"><span class="expand">[+] <?php _e( 'Expand All', 'cronicle' ); ?></span><span class="collapse">[-] <?php _e( 'Collapse All', 'cronicle' ); ?></span></a>
</p>
<?php endif; ?>

<?php require( 'status-page-by-cron-base.php' ); ?>

