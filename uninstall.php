<?php
	/* Make sure uninstall is not called directly */
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		exit();
	}

	/* Uninstall database per blog (e.g. called multiple times when multisite is used */
	function subme_uninstall() {
                global $wpdb;

		delete_option ( 'subme_options' );

                /* Drop subme_queue table */
                $table = $wpdb->prefix . 'subme_queue';
                $wpdb->query ( "DROP TABLE $table" );

                /* Drop subme table */
                $table = $wpdb->prefix . 'subme';
                $wpdb->query ( "DROP TABLE $table" );
	}

	if ( function_exists( 'is_multisite' ) && is_multisite() ) {
		global $wpdb;

		$blogs = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog );
			subme_uninstall();
			restore_current_blog();
		}
	} else {
		subme_uninstall();
	}
?>
