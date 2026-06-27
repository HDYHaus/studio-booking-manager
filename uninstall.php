<?php
/**
 * Uninstall handler.
 *
 * @package StudioBookingManager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Keep operational data by default. A future settings option will allow full data removal.
delete_option( 'sbm_settings' );
delete_option( 'sbm_db_version' );
