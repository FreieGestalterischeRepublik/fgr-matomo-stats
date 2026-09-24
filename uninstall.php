<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'fgr_matomo_stats' );
delete_transient( 'fgr_ms_data' );
delete_transient( 'fgr_ms_activation_error' );
