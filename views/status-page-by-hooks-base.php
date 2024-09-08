<div class="status-col">
    <?php 
        $dt_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        foreach ( $cron_logs as $hook_name => $hook_item ) : 
            if ( empty( $hook_item['logs'] ) ) {
                continue;
            }
            if ( $r == $max_rows ) :
                echo '</div><div class="status-col">';
            endif;
            $next_run = 'now';
            if ( $hook_item['time'] == -1 ) {
                $next_run = 'Not Scheduled';
            }
            $now = time();
            if ( $hook_item['time'] > $now ) {
                $next_run = date_i18n( 'm/d/Y h:ia', CRONICLE::utc_to_blogtime( $hook_item['time'] ) );
            }
            $logs = array_reverse( $hook_item['logs'] );
            $completions = array_column( $logs, 'completed' );
            $class_status = '';
            $title = '';
            foreach ( $logs as $log ) {
                if ( !empty( $log['in_progress'] ) ) {
                    $class_status = 'in-progress';
                    $title = __( 'One or more hooks are still running', 'cronicle' );
                    break;
                }
                $completed = $log['completed'];
                if ( empty( $class_status ) )  {
                    if ( !$completed ) {
                        $class_status = 'failed';
                        $title = __( 'The last run failed', 'cronicle' );
                        if ( $log['result'] != CRONICLE::RESULT_INCOMPLETE || cronicle_is_incomplete_an_error( $log['hook_name'] ) ) {
                            // only break if this is considered an error
                            break;
                        }
                    }
                    $class_status = 'completed';
                    $title = __( 'The latest runs succeeded', 'cronicle' );
                    continue;
                }
                if ( !$completed ) {
                    if ( $log['result'] != CRONICLE::RESULT_INCOMPLETE || cronicle_is_incomplete_an_error( $log['hook_name'] ) ) {
                        // only if this is considered an error
                        $class_status = 'warning';
                        $title = __( 'At least one of the last 5 runs failed or is still running', 'cronicle' );
                    }
                }
            }
    ?>
    <div class="hook-container <?php echo $class_status; ?>">
        <div class="hook-top">
            <span type="button" class="toggle-indicator"></span>
            <h3 class="hook-title" title="<?php echo esc_attr( $hook_name ); ?>"><?php echo esc_html( $hook_name ); ?>
                <br><span class="subheader">Next run: <?php echo $next_run; ?> <?php echo !empty( $hook_item['interval'] ) ? '('.$hook_item['interval'].')' : ''; ?></span>
            </h3>
            <span class="status-icon" title="<?php echo esc_attr( $title ); ?>"></span>
        </div>
        <div class="hook-details">
            <div class="hook-details-content">
                <div class="detail-row">
                    <?php if ( empty( $logs ) ) : ?>
                        <div class="detail-text-only">No logs yet.</div>
                    <?php else : ?>
                    <table class="last-runs">
                        <tr class="header-row">
                            <th class="last-run-label">Time Ran</th>
                            <th class="last-run-lapse">Time (ms)</th>
                            <th class="last-run-status">Result</th>
                        </tr>
                        <?php foreach ( $logs as $row ) : 
                            $elapsed = __( 'N/A', 'cronicle' );
                            if ( !empty( $row['in_progress'] ) ) {
                                $elapsed = '-';
                            }
                            else if ( $row['elapsed'] != CRONICLE::NO_VALUE ) {
                                $elapsed = number_format( floatval( $row['elapsed'] ), 1 );
                            }
                        ?>
                        <tr class="<?php echo str_replace(' ', '-', strtolower( $row['result'] ) ); ?>">
                            <td class="last-run-label"><?php echo date_i18n( $dt_format, CRONICLE::utc_to_blogtime( (int) $row['start'] ) ); ?></td>
                            <td class="last-run-lapse"><?php echo $elapsed; ?></td>
                            <td class="last-run-status">
                                <span class="<?php echo str_replace(' ', '-', strtolower( $row['result'] ) ); ?>-status-bar" title="<?php echo esc_attr( $row['message'] ); ?>">
                                    <?php echo $row['result']; ?>
                                    <?php if ( $row['result'] == CRONICLE::RESULT_FAILED ) : ?>
                                        <span class="dashicons dashicons-code-standards"></span>
                                        <div class="failed-message <?php echo ( !empty( $row['error'] ) ) ? 'has-error' : ''; ?>" style="display:none;">
                                            <?php 
                                                if ( !empty( $row['caught_error'] ) ) {
                                                    $error = __( 'An error has been caught!  Purchase the PRO version to see it here.', 'cronicle' );
                                                        $error = $row['error'];
                                                }
                                                else {
                                                    $error = __( 'No errors caught', 'cronicle' );
                                                }
                                                echo $error;
                                            ?>
                                        </div>
                                    <?php elseif ( $row['result'] == CRONICLE::RESULT_INCOMPLETE ) : ?>
                                        <span class="dashicons dashicons-editor-help"></span>
                                        <div class="incomplete-message" style="display:none;">
                                            A hook is marked incomplete when the normal WordPress and PHP process did not complete as normally.
                                        </div>
                                    <?php endif; ?>
                                </span></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                    <div class="attached-functions">
                        <h4>Attached Functions</h4>
                        <ul>
                            <?php if ( empty( $hook_item['callbacks'] ) ) : ?>
                            <li>None.  Functions could be attached at a later time or dynamically.</li>
                            <?php else : ?>
                                <?php foreach( $hook_item['callbacks'] as $callback ) : ?>
                                <li><?php echo $callback['callback']['name']; ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div> <!-- .attached-functions -->
                </div>
            </div>
        </div>
    </div> <!-- .hook-container -->
    <?php 
        $r++;
        endforeach;
    ?>
</div>