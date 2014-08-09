<?php

/*
 * Plugin Name: SubMe
 * Plugin URI:
 * Description: SubMe notifies subscribers by email when an new post has been published.
 * Version: 1.1
 * Author: Dennis Pellikaan
 * Author URI: https://supongo.org
 * Licence: GPL3
 * Text Domain: SubMe
*/

/* Copyright 2014 Dennis Pellikaan
 *
 * Partially based on the Subscribe2 plugin by 
 * Copyright (C) 2006-14 Matthew Robinson
 *
 * SubMe is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SubMe is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SubMe. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

define( 'SMPATH', trailingslashit( dirname( __FILE__ ) ) );

if ( version_compare( $GLOBALS['wp_version'], '3.9', '<' ) ) {
	$msg = sprintf( 'This version of SubMe requires WordPress 3.9 or greater. Please update %1$s.' ,
		'<a href="http://codex.wordpress.org/Updating_WordPress">Wordpress</a>', 
		'<a href="http://wordpress.org/extend/plugins/subme/download/">SubMe</a>' );
        exit( $msg );
}

/* Incude the appropiate functions to check for network wide installs */
if ( ! function_exists ('is_plugin_active_for_network' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
}

/* Do not allow for network plugin activation, but only per site */
if ( is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
	deactivate_plugins( plugin_basename( __FILE__ ) );
	exit( __( 'SubMe cannot be activated as a network plugin. Please activate it at on a site level.', 'subme' ) );
}

/* Include the appropiate functions to get user information */
if ( ! function_exists( 'get_user_by' ) ) {
	require_once( ABSPATH . '/wp-includes/pluggable.php' );
}

class subme {
	function init() {
		/* Localization hook */
		add_action( 'init', array ( &$this, 'load_localization' ) );

		/* Install the tables if needed */
		$this->install();

		/* Load javascript */
		add_action( 'admin_enqueue_scripts', array ( &$this, 'load_js' ) );

		/* Add menu to wordpress */
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );

		/* Load saved options */
		$this->sm_options = get_option( 'subme_options' );

		/* Load default options. Functions that have not been loaded previously will be set. None will be overridden. */
		include( SMPATH . 'include/options.php' );

		/* Check if all options are set correctly. If not, then stop running this plugin. 
		 * This protects the user from running an inproperly configured plugin
		 */
		$ret = $this->check_options();
		if ( 'OK' !== $ret['status'] ) {
			/* Stop the plugin if something if wrong with one or more options */
			return;
		}

		/* Make sure cron function is called when enabled */
		if( $this->sm_options['cron_enabled'] ) {
			add_action( 'call_subme_cron', array( &$this, 'subme_cron' ) );
		}

		/* Add link to Meta */
		if ( $this->sm_options['show_meta'] ) {
			 add_action( 'wp_meta', array( &$this, 'add_meta' ), 0 );
		}

		if ( $this->sm_options['enable_widget'] ) {
			add_action( 'widgets_init', array( &$this, 'add_widget' ) );
		}

		/* Add shortcode */
		add_shortcode( 'SubMe', array( &$this, 'sm_shortcode' ) );

		/* Trigger when new messages are posted */
		add_action ( 'transition_post_status', array( &$this, 'new_post' ), 10, 3 );

		/* Check if a subme action (subscribe/unsubscribe) is required. 
		 * This is triggered when a user confirms using the link sent by email */
		if ( isset( $_GET['subme'] ) ) {
			$this->confirm( $_GET['subme'] );
		}
	}

	/* Localization */
	function load_localization() {
		load_plugin_textdomain( 'subme', false, basename( dirname( __FILE__ ) ) . '/languages' );		
	}

	/* Hook the menu */
	function admin_menu() {
		/* Check users's permissions */
		$current_user = wp_get_current_user();
		if ( ! is_super_admin() &&
		        $current_user->ID != $this->sm_options['delegate_email_settings_to'] &&
		        $current_user->ID != $this->sm_options['delegate_templates_to'] &&
		        $current_user->ID != $this->sm_options['delegate_appearance_to'] &&
			$current_user->ID != $this->sm_options['delegate_subscribers_to'] &&
			$current_user->ID != $this->sm_options['delegate_queue_to'] ) {

			/* Do not create a menu for the current user */
			return;
		}

		/* Main menu hook */
		add_menu_page( 'SubMe', 'SubMe', NULL, 'sm',  NULL, NULL );

		if ( is_super_admin() || 
			$current_user->ID == $this->sm_options['delegate_email_settings_to'] ||
			$current_user->ID == $this->sm_options['delegate_templates_to'] ||
			$current_user->ID == $this->sm_options['delegate_appearance_to'] ) {

			add_submenu_page( 'sm', __( 'Settings', 'subme' ), __( 'Settings', 'subme' ), 'read', 'sm_settings', array( &$this, 'settings' ), NULL );
		}

		if ( is_super_admin() || $current_user->ID == $this->sm_options['delegate_subscribers_to'] ) {
			add_submenu_page( 'sm', __( 'Subscribers', 'subme' ), __( 'Subscribers', 'subme' ), 'read', 'sm_subscribers', array( &$this, 'subscribers' ), NULL );
		}

		if ( is_super_admin() || $current_user->ID == $this->sm_options['delegate_queue_to'] ) {
			add_submenu_page( 'sm', __( 'Queue', 'subme' ), __( 'Queue', 'subme' ), 'read', 'sm_queue', array( &$this, 'queue' ), NULL );
		}
	}

	/* Load javascript */
	function load_js() {
		wp_enqueue_script( 'subme_js', plugins_url( 'subme/js/subme.js', dirname( __FILE__ ) ) );
	}

	/* Display admin error */
	function display_admin_error( $msg ) {
		?>

		<div class="error">
			<p>
				<strong><?php echo esc_html( $msg ); ?></strong>
			</p>
		</div>

		<?php
	}

	/* Show subscribe link in Meta */
	function add_meta() {
		$url = wp_get_shortlink( $this->sm_options['subme_page'] );
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( __( 'Subscribe', 'subme' ) ) . '</a></li>';
	}

	/* Add widget */
	function add_widget() {
		register_widget( 'subme_widget' );
	}

	/* Call subscribers form */
	function subscribers() {
		require_once( SMPATH . '/include/subscribers.php' );
	}

	/* Call settings form */
	function settings() {
		require_once( SMPATH . '/include/settings.php' );
	}

	/* Call queue form */
	function queue() {
		require_once( SMPATH . '/include/queue.php' );
	}

	/* Replace [SubMe] shortcode on page with the SubMe form */
	function sm_shortcode() {
		$form = '';
		$msg = '';
	
		if ( isset( $_GET['status'] ) ) {
			if ( 'subscribed' === $_GET['status'] ) {
				$msg = __( 'Thank you for confirming your subscription', 'subme' );	
			} else if ( 'unsubscribed' === $_GET['status'] ) {
				$msg = __( 'You have successfully unsubscribed', 'subme' );
			} else {
				$msg = __( 'Something unexpected has happend.', 'subme' );
			}

			$form .= '<p>';
			$form .= esc_html( $msg );
			$form .= '</p>';
		} else {
			/* Process the form when submitted */	
			if ( ( isset( $_POST['subscribe'] ) || isset( $_POST['unsubscribe'] ) ) && isset( $_POST['source'] ) && 'subme_page' === $_POST['source'] ) {
				/* Check nonce */
				if ( ! isset( $_POST['subme_page_nonce'] ) || ! wp_verify_nonce( $_POST['subme_page_nonce'], 'subme_page' ) ) {
					return;
				}

				if ( ! isset( $_POST['email'] ) ) {
					return;
				}

				if ( ! $this->is_valid_email( strtolower( $_POST['email'] ) ) ) {
					$msg = __( 'Sorry, but this does not seem like a valid email address.', 'subme' );
				} else {
					if ( isset( $_POST['subscribe'] ) ) {
						$msg = $this->subscribe( $_POST['email'] );
					} else {
						$msg = $this->unsubscribe( $_POST['email'] );
					}
				}
			}

			$form .= '<form method="post">';
				$form .= wp_nonce_field( 'subme_page', 'subme_page_nonce', true, false );
				$form .= '<input type="hidden" name="source" value="subme_page" />';
				$form .= '<p>';
				$form .= __( 'Your email address:', 'subme' ) . '<br />';	
				$form .= '<input type="text" name="email" /><br /><br />';
				$form .= '<input type="submit" name="subscribe" value="' . __( 'Subscribe', 'subme' ) . '" />';
				$form .= ' ';
				$form .= '<input type="submit" name="unsubscribe" value="' . __( 'Unsubscribe', 'subme' ) . '" />';
				$form .= '</p>';
			$form .= '</form>';
			if ( strlen( $msg ) > 0 ) {
				$form .= '<p>';
				$form .= esc_html( $msg );
				$form .= '</p>';
			}
		}

		return $form;
	}

	/* Cron automatically called by Wordpress */
	function subme_cron() {
		if ( DEFINED( 'SM_CRON_ACTIVE' ) ) return;
		define( 'SM_CRON_ACTIVE', true );

		global $wpdb;

		$table = $wpdb->prefix . "subme_queue";
		$query = $wpdb->prepare( "SELECT * FROM $table LIMIT %d", absint( $this->sm_options['emails_per_burst'] ) );
		$results = $wpdb->get_results( $query );
		if ( $results ) {
			foreach ( $results as $result ) {
				/* Send email */
				$this->mail( $result->recipient, $result->sender, $result->name, $result->subject, $result->message );

				/* Remove from queue */
				$query = $wpdb->prepare( "DELETE FROM $table WHERE id=%d", $result->id );
				$wpdb->query( $query );
			}

			if ( ! wp_next_scheduled('call_subme_cron' ) ) {
				/* When triggered, Wordpress will call the action hook call_subme_cron, which in turn will call the function subme_cron */
				wp_schedule_single_event( time() + ( absint( $this->sm_options['cron_minutes'] ) * 60 ), 'call_subme_cron' );
			}
		}
	}

	/* Install the tables for the active blog if needed. Called from the init function. */
	function install() {
		global $wpdb;
		global $my_subme;

		/* Load the maybe_create_table function if not previously loaded */
		if ( ! function_exists( 'maybe_create_table' ) ) {
			require_once( ABSPATH . 'wp-admin/install-helper.php' );
		}

		/* Create SubMe table if it doesn't exists */
		$table = $wpdb->prefix . "subme";
		$query = "CREATE TABLE " . $table . " (
			`id` int(11) NOT NULL auto_increment,
			`email` varchar(64) NOT NULL DEFAULT '',
			`active` tinyint(1) NOT NULL DEFAULT '0',
			`timestamp` int(11) NOT NULL DEFAULT '0',
			`ip` varchar(64) NOT NULL DEFAULT '',
			`conf_hash` varchar(128) DEFAULT NULL,
			`conf_timestamp` int(11) DEFAULT NULL,
			`conf_ip` varchar(64) DEFAULT NULL,
			UNIQUE KEY (`email`),
			PRIMARY KEY (`id`)
			)";
		maybe_create_table( $table, $query );

		/* Create SubMe queue table if it doesn't exists */
		$table = $wpdb->prefix . "subme_queue";
		$query = "CREATE TABLE " . $table . " (
			`id` int(11) NOT NULL auto_increment,
			`recipient` varchar(255) NOT NULL DEFAULT '',
			`sender` varchar(255) NOT NULL DEFAULT '',
			`name` varchar(255) NOT NULL DEFAULT '',
			`subject` varchar(255) NOT NULL DEFAULT '',
			`message` varchar(1024) NOT NULL DEFAULT '',
			PRIMARY KEY (`id`)
			)";
		maybe_create_table( $table, $query );
	}

	/* Checks if all options are correctly set. When something is wrong the plugin will not function */
	function check_options() {
		global $wpdb;

		$ret['status'] = 'OK';
		$ret['msgs'] = array();

		/* Get admin ID */
		if ( ! ( $admin = get_user_by( 'email', get_option( 'admin_email' ) ) ) ) {
			$ret['status'] = 'NO';
			array_push( $ret['msgs'], __( 'Failed to get the site admin ID.', 'subme' ) );

			return $ret;
		}
		$admin_id = absint( $admin->ID );

		/* Check if subme is configured to point to a page with subme shortcode tag */
		if ( ! isset( $this->sm_options['subme_page'] ) || '' == $this->sm_options['subme_page'] ) {
			$ret['status'] = 'NO';
			array_push( $ret['msgs'], __( 'There is no default SubMe page configured.', 'subme' ) );
		} 

		/* Check if the page still exists and it is not deleted */
		if ( 'publish' !== get_post_status( absint( $this->sm_options['subme_page'] ) ) && '' != $this->sm_options['subme_page'] ) {
			$ret['status'] = 'NO';
			array_push( $ret['msgs'], __( 'The default SubMe page that is configured does not exists anymore.', 'subme' ) );
		}

		/* Check if delegated users exists. Replace with admin id if it doesn't exists */
		if ( ! get_userdata( $this->sm_options['delegate_email_settings_to'] ) ) {
			$this->sm_options['delegate_email_settings_to'] = $admin_id;	
		}
		if ( ! get_userdata( $this->sm_options['delegate_templates_to'] ) ) {
			$this->sm_options['delegate_templates_to'] = $admin_id;
		}
		if ( ! get_userdata ( $this->sm_options['delegate_appearance_to'] ) ) {
			$this->sm_options['delegate_appearance_to'] = $admin_id;
		}
		if ( ! get_userdata ( $this->sm_options['delegate_subscribers_to'] ) ) {
			$this->sm_options['delegate_subscribers_to'] = $admin_id;
		}
		if ( ! get_userdata ( $this->sm_options['delegate_queue_to'] ) ) {
			$this->sm_options['delegate_queue_to'] = $admin_id;
		}
		if ( ! get_userdata ( $this->sm_options['email_notifications_to'] ) ) {
			$this->sm_options['email_notifications_to'] = $admin_id;
		}
		if ( ! get_userdata ( $this->sm_options['email_notifications_from'] ) ) {
			$this->sm_options['email_notifications_from'] = '';
		}
		if ( ! get_userdata ( $this->sm_options['send_post_emails_from'] ) && 'Post Author' !== $this->sm_options['send_post_emails_from'] ) {
			$this->sm_options['send_post_emails_from'] = '';
		}
		if ( ! get_userdata ( $this->sm_options['send_confirmation_emails_from'] ) ) {
			$this->sm_options['send_confirmation_emails_from'] = '';
		}

		return $ret;
	}

	/* Echo "checked" when true */
	function checked( $var ) {
		if ( $var ) echo "checked";
	}

	/* Echo "selected" when true */
	function selected( $var ) {
		if ( $var ) echo "selected";
	}

	/* Return the client's IP address */
	function get_ip() {
		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
		} else {
			$headers = $_SERVER;
		}
 
		if ( array_key_exists( 'X-Forwarded-For', $headers ) && ( filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) || filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) ) {
			$ip = $headers['X-Forwarded-For'];
		} elseif ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers ) && ( filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) || filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) ) {
			$ip = $headers['HTTP_X_FORWARDED_FOR'];
		} else {
			if ( filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				$ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
			} elseif ( filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				$ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
			} else {
				$ip = '';
			}
		}

		return $ip;		
	}
	
	/* Send out an email */
	function mail( $recipient, $sender, $name, $subject, $message ) {
		if ( '' == $recipient  || '' == $subject || '' == $message ) return;

		$subject = wp_strip_all_tags( stripslashes( $subject ) );
		$message = wp_strip_all_tags( stripslashes( $message ) );
	
		$headers[] = "Content-type:text/plain; charset=UTF-8";

		/* Set different From address other than default */
		if ( '' != $sender || '' != $name ) {
			$headers[] = "From: " . esc_html( $name ) . " <" . esc_attr( $sender ) . ">";
		}
		
		return wp_mail( $recipient, $subject, $message, $headers );	
	}

	/* Prepare the mail on the queue or send out directly */
	function prepare_mail( $recipient, $sender, $name, $subject, $message, $via_queue = 'yes' ) {
		if ( 'yes' === $this->sm_options['cron_enabled'] && 'yes' === $via_queue ) {
			global $wpdb;

			/* Add to queue */
			$table = $wpdb->prefix . 'subme_queue';
			$query = $wpdb->prepare( "INSERT INTO $table (recipient, sender, name, subject, message) VALUES (%s, %s, %s, %s, %s)",
				$recipient,
				$sender,
				$name,
				$subject,
				$message );
			$wpdb->query( $query );
		} else {
			/* Send mail directly */
			$this->mail( $recipient, $sender, $name, $subject, $message );
		}
	}

	/* Check if the email address is valid */
	function is_valid_email( $email ) {
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return false;
		}

		return true;
	}

	/* Substitute keywords in templates */
	function substitute( $text, $post = false ) {
		/* Replace variables */
		$text = preg_replace( '/@@BLOGLINK/', get_option( 'home' ), $text );
		$text = preg_replace( '/@@BLOGNAME/', get_option( 'blogname' ), $text );

		/* Replace post specific variables */
		if ( $post ) {
			$author = get_userdata( $post->post_author );
			$title = wp_strip_all_tags( stripslashes( $post->post_title ) );
			$content = wp_trim_words( wp_strip_all_tags( html_entity_decode( $post->post_content ) ), 50, ' [..]' );

			$text = preg_replace( '/@@AUTHORNAME/', ( '' != $author->display_name ? $author->display_name : $author->user_login ), $text );
			$text = preg_replace( '/@@SNIPPET/', $content, $text );
			$text = preg_replace( '/@@TITLE/', $title, $text );
			$text = preg_replace( '/@@PERMALINK/', get_permalink( $post->ID ), $text );
		}

		return $text;
	}

	/* Returns the total number of subscribers */
	function total_subscribers() {
		global $wpdb;

		$table = $wpdb->prefix . 'subme';
		$result = $wpdb->get_results( "SELECT COUNT(*) AS total FROM $table" );

		if ( $result ) {
			return $result[0]->total;
		} else {
			return 0;
		}
	}

	/* Return the total number of emails in the queue */
	function total_queue() {
		global $wpdb;

		$table = $wpdb->prefix . 'subme_queue';
		$result = $wpdb->get_results( "SELECT COUNT(*) AS total FROM $table" );

		if ( $result ) {
			return $result[0]->total;
		} else {
			return 0;
		}
	}

	/* Directly add a subscriber via the admin panel */
	function add_subscriber( $email ) {
		global $wpdb;
		global $sm_error;

		$email = strtolower( $email );

		/* Check if the email is valid */
		if ( ! $this->is_valid_email( $email ) ) {
			$sm_error = __( 'Sorry, but this does not seem like a valid email address.', 'subme' );

			return;
		}

		$table = $wpdb->prefix . 'subme';

		/* Check if the email already exists */
		$query = $wpdb->prepare( "SELECT email FROM $table WHERE email=%s", $email );
		$results = $wpdb->get_results( $query );
		if ( $results ) {
			$sm_error = __( 'Sorry, but this email address has already subscribed.', 'subme' );

			return;
		}

		/* Insert the new subscriber */
		$query = $wpdb->prepare( "INSERT INTO $table (email, active, timestamp, ip, conf_hash, conf_timestamp, conf_ip) VALUES (
			%s, '1', %d, %s, NULL, %d, '')",
				$email,
				time(),
				$this->get_ip(),
				time()
			);
		$wpdb->query( $query );
	}

	/* Delete a subscriber from the database */
	function delete_subscriber( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'subme';

		$query = $wpdb->prepare( "DELETE FROM $table WHERE id=%d", $id );
		$wpdb->query( $query );
	}

	/* Active a registered subscriber */
	function activate_subscriber( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'subme';

		$query = $wpdb->prepare( "UPDATE $table SET active=1 WHERE id=%d", $id );
		$wpdb->query( $query );
	}

	/* Deactivate a registered subscriber */
	function deactivate_subscriber( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'subme';

		$query = $wpdb->prepare( "UPDATE $table SET active=0 WHERE id=%d", $id );
		$wpdb->query( $query );
	}

	/* Delete queue item */
	function delete_queue( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'subme_queue';

		$query = $wpdb->prepare( "DELETE FROM $table WHERE id=%d", $id );
		$wpdb->query( $query );
	}

	/* Add new subscription */
	function subscribe( $email ) {
		global $wpdb;

		$email = strtolower( $email );

		/* Check if the email is valid */
		if ( ! $this->is_valid_email( $email ) ) {
			return __( 'Sorry, but this does not seem like a valid email address.', 'subme' );
		}

		$table = $wpdb->prefix . 'subme';

		/* Prevent flooding. This only works if we are able to retrieve the client's IP. */
		$ip = $this->get_ip();
		if ( $ip ) {
			$query = $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE ip=%s AND timestamp > %d",
				$ip,
				time() - 300 );
			$count = $wpdb->get_var( $query );	

			/* Allow no more than 5 subscriptions in 5 minutes (300 seconds) */
			if ( $count >= 5 ) {
				return __( 'You are moving too fast. Please be patient.', 'subme' );
			}
		}

		/* Check if the email already exists */
		$query = $wpdb->prepare( "SELECT email FROM $table WHERE email=%s LIMIT 1", $email );
		$results = $wpdb->get_results( $query );
		if ( $results ) {
			return __( 'Sorry, but this email address has already subscribed.', 'subme' );
		}

		/* Generate a new confirmation hash and get the AUTO_INCREMENT ID */
		$conf_hash = '1' . hash( 'sha256', base64_encode( openssl_random_pseudo_bytes( 32 ) . $email . time() ) );	
		$query = $wpdb->prepare( "INSERT INTO $table (email, active, timestamp, ip, conf_hash, conf_timestamp, conf_ip) VALUES (
			%s, '0', %d, %s, %s, NULL, NULL)",
				$email,
				time(),
				$this->get_ip(),
				$conf_hash
			);
		if ( ! $wpdb->query( $query ) ) {
			return __( 'Sorry, but something went wrong.', 'subme' );
		}
		$new_id = $wpdb->insert_id;

		/* Construct email for subscriber */
		$subj = $this->substitute( $this->sm_options['subscribe_subject'] );
		$msg = $this->substitute( $this->sm_options['subscribe_text'] );

		$url = add_query_arg( 'subme', $conf_hash . $new_id, get_option( 'home' ) );
		$msg = preg_replace( '/@@LINK/', $url, $msg );

		$msg = preg_replace( '/@@ACTION/', __( 'subscribe', 'subme' ), $msg );

		/* Prepare the email for sending */
		if ( '' == $this->sm_options['send_confirmation_emails_from'] ) {
			$this->prepare_mail( $email, '', '', $subj, $msg, $this->sm_options['confirmation_via_queue'] );
		} else {
			$from = get_userdata( $this->sm_options['send_confirmation_emails_from'] );
			$this->prepare_mail( $email, $from->user_email, ( '' != $from->display_name ? $from->display_name : $from->user_login ), $subj, $msg, $this->sm_options['confirmation_via_queue'] );
		}	

		/* Do we want a notification for ourselves? */
		if ( 'subscribers' === $this->sm_options['email_notification'] || 'both' === $this->sm_options['email_notification'] ) {
			$subj = sprintf( __( 'A new user has requested to subscribe to %s', 'subme' ), get_option( 'blogname' ) );
			$msg = sprintf( __( '%s has requested to subscribe to %s', 'subme' ), $email, get_option ( 'blogname' ) );

			/* Prepare the email for sending */
			$to = get_userdata( $this->sm_options['email_notifications_to'] );
			if ( '' == $this->sm_options['email_notifications_from'] ) {
				$this->prepare_mail( $to->user_email, '', '', $subj, $msg );
			} else {
				$from = get_userdata( $this->sm_options['email_notifications_from'] );
				$this->prepare_mail( $to->user_email, $from->user_email, ( '' != $from->display_name ? $from->display_name : $from->user_login ), $subj, $msg );
			}
		}

		/* Schedule the pseudo cron so notifications emails will get send when cron is used */
		if ( $this->sm_options['cron_enabled'] ) {
			if ( ! wp_next_scheduled( 'call_subme_cron' ) ) {
				/* When triggered, Wordpress will call the action hook call_subme_cron, which in turn will call the function subme_cron */
				wp_schedule_single_event( time() + ( absint( $this->sm_options['cron_minutes'] ) * 60 ), 'call_subme_cron' );
			}
		}

		return __( 'A confirmation message is on its way!', 'subme' );
	}

	/* Unsubscribe email */
	function unsubscribe( $email ) {
		global $wpdb;

		$email = strtolower( $email );

		/* Check if the email is valid */
		if ( ! $this->is_valid_email( $email ) ) {
			return __( 'Sorry, but this does not seem like a valid email address.', 'subme' );
		}

		$table = $wpdb->prefix . 'subme';

		/* Check if the email exists */
		$query = $wpdb->prepare( "SELECT email FROM $table WHERE email=%s LIMIT 1", $email );
		$results = $wpdb->get_results( $query );
		if ( ! $results ) {
			return __( 'Sorry, but this email address has not subscribed.', 'subme' );
		}

		/* Generate a new confirmation hash */
		$conf_hash = '0' . hash( 'sha256', base64_encode( openssl_random_pseudo_bytes( 32 ) . $email . time() ) );
		$query = $wpdb->prepare( "UPDATE $table SET conf_hash=%s, conf_timestamp=NULL, conf_ip=NULL WHERE email=%s",
				$conf_hash,
				$email
			);
		$wpdb->query( $query );

		/* Get the old ID */
		$query = $wpdb->prepare( "SELECT id FROM $table WHERE email=%s LIMIT 1", $email );
		$result = $wpdb->get_results( $query );
		if ( $result ) {
			$old_id = $result[0]->id;
		} else {
			return __( 'Sorry, but something went wrong.', 'subme' );
		}

		/* Construct email for unsubscriber */
		$subj = $this->substitute( $this->sm_options['subscribe_subject'] );
		$msg = $this->substitute( $this->sm_options['subscribe_text'] );

		$url = add_query_arg( 'subme', $conf_hash . $old_id, get_option( 'home' ) );
		$msg = preg_replace( '/@@LINK/', $url, $msg );

		$msg = preg_replace( '/@@ACTION/', __( 'unsubscribe', 'subme' ), $msg );

		/* Prepare the email for sending */
		if ( '' == $this->sm_options['send_confirmation_emails_from'] ) {
			$this->prepare_mail( $email, '', '', $subj, $msg, $this->sm_options['confirmation_via_queue'] );
		} else {
			$from = get_userdata( $this->sm_options['send_confirmation_emails_from'] );
			$this->prepare_mail( $email, $from->user_email, ( '' != $from->display_name ? $from->display_name : $from->user_login ), $subj, $msg, $this->sm_options['confirmation_via_queue'] );
		}

		/* Do we want a notification for ourselves? */
		if ( 'unsubscribers' === $this->sm_options['email_notification'] || 'both' === $this->sm_options['email_notification'] ) {
			$subj = sprintf( __( 'A user has requested to unsubscribe from %s', 'subme' ), get_option( 'blogname' ) );
			$msg = sprintf( __( '%s has requested to unsubscribe from %s', 'subme' ), $email, get_option( 'blogname' ) );

			/* Prepare the email for sending */
			$to = get_userdata( $this->sm_options['email_notifications_to'] );
			if ( '' == $this->sm_options['email_notifications_from'] ) {
				$this->prepare_mail( $to->user_email, '', '', $subj, $msg );
			} else {
				$from = get_userdata( $this->sm_options['email_notifications_from'] );
				$this->prepare_mail( $to->user_email, $from->user_email, ( '' != $from->display_name ? $from->display_name : $from->user_login ), $subj, $msg );
			}
		}

		/* Schedule the pseudo cron so notifications emails will get send when cron is used */
		if ( $this->sm_options['cron_enabled'] ) {
			if ( ! wp_next_scheduled( 'call_subme_cron' ) ) {
				/* When triggered, Wordpress will call the action hook call_subme_cron, which in turn will call the function subme_cron */
				wp_schedule_single_event( time() + ( absint( $this->sm_options['cron_minutes'] ) * 60 ), 'call_subme_cron' );
			}
		}

		return __( 'A confirmation message is on its way!', 'subme' );
	}

	/* Redirect wordpress to the subme shortcode form for confirming that subscription has succeeded */
	function confirm_redirect_subscribe() {
		$url = add_query_arg( 'status', 'subscribed', wp_get_shortlink( $this->sm_options['subme_page'] ) );
		wp_redirect( $url );
		die();
	}

	/* Redirect wordpress to the subme shortcode form confirming that the unsubscription has succeeded */
	function confirm_redirect_unsubscribe() {
		$url = add_query_arg( 'status', 'unsubscribed', wp_get_shortlink( $this->sm_options['subme_page'] ) );
		wp_redirect( $url );
		die();
	}

	/* Redirect wordpress to the subme shortcode form confirming that their was a problem process the confirmation link */ 
	function confirm_redirect_error() {
		$url = add_query_arg( 'status', 'wrong', wp_get_shortlink( $this->sm_options['subme_page'] ) );
		wp_redirect( $url );
		die();
	}

	/* Confirm (un)subscription */
	function confirm( $hash ) {
		global $wpdb;

		$redirect = '';
		$table = $wpdb->prefix . 'subme';
		$action = absint( substr( $hash, 0, 1 ) );
		$conf_hash = substr( $hash, 1, 64 );
		$id = absint( substr( $hash, 65 ) );

		/* Trap bad requests */
		if ( $id < 1 || strlen( $conf_hash ) <> 64 || $action < 0 || $action > 1 ) {
			$redirect = "confirm_redirect_error";
		} else {
			/* Subscribe */
			if ( '1' == $action ) {
				$query = $wpdb->prepare( "SELECT id FROM $table WHERE id=%d AND conf_hash=%s LIMIT 1",
					$id,
					$action . $conf_hash );
				$result = $wpdb->get_results( $query );
				if ( $result ) {
					$query = $wpdb->prepare( "UPDATE $table SET active=1, conf_hash=NULL, conf_timestamp=%d, conf_ip=%s WHERE id=%d",
						time(),
						$this->get_ip(),
						$result[0]->id );
					$wpdb->query( $query );
	
					$redirect = "confirm_redirect_subscribe";
				} else {
					$redirect = "confirm_redirect_error";
				}
			/* Unsubscribe */
			} elseif ( '0' == $action ) {

				$query = $wpdb->prepare( "SELECT id FROM $table WHERE id=%d AND conf_hash=%s LIMIT 1",
					$id,
					$action . $conf_hash );
				$result = $wpdb->get_results( $query );
				if ( $result ) {
					$query = $wpdb->prepare( "DELETE FROM $table WHERE id=%d",
						$result[0]->id );
					$wpdb->query( $query );
	
					$redirect = "confirm_redirect_unsubscribe";
				} else {
					$redirect = "confirm_redirect_error";
				}
			}
		}

		if ( '' == $redirect ) {
			$redirect = "confirm_redirect_error";
		}
 		add_action( 'template_redirect', array( &$this, $redirect ), 10 );
	}

	/* Called when a new post is pubished */
	function new_post( $new_status, $old_status, $post ) {
		global $wpdb;

		/* Check if it is a post (e.g. not a page) */
		if ( 'post' !== $post->post_type ) {
			return;
		}

		/* New post */
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			if ( 'yes' !== $this->sm_options['email_private_posts'] || 'private' !== $new_status || 'private' === $old_status ) {
				return;
			}
		}

		/* Password protected post */
		if ( 'yes' !== $this->sm_options['email_protected_posts'] && '' != $post->post_password ) {
			return;
		}

		$title = $this->substitute( $this->sm_options['notification_subject'], $post );
		$content = $this->substitute( $this->sm_options['notification_text'], $post );

		if ( '' == $this->sm_options['send_post_emails_from'] ) {
			$sender = '';
		} elseif ( 'Post Author' === $this->sm_options['send_post_emails_from'] ) {
			$sender = get_userdata ( $post->post_author );
		} else {
			$sender = get_userdata( $this->sm_options['send_post_emails_from'] );
		}

		$table = $wpdb->prefix . 'subme';
		$results = $wpdb->get_results( "SELECT email FROM $table WHERE active=1" );
		foreach ( $results as $result ) {
			/* Prepare email */
			if ( '' == $sender ) {
				$this->prepare_mail( $result->email, '', '', $title, $content );
			} else {
				$this->prepare_mail( $result->email, $sender->user_email, ( '' != $sender->display_name ? $sender->display_name : $sender->user_login ), $title, $content );
			}
		}

		if ( $this->sm_options['cron_enabled'] ) {
			if ( ! wp_next_scheduled( 'call_subme_cron' ) ) {
				/* When triggered, Wordpress will call the action hook call_subme_cron, which in turn will call the function subme_cron */
				wp_schedule_single_event( time() + ( absint( $this->sm_options['cron_minutes'] ) * 60 ), 'call_subme_cron' );
			}
		}
	}
	
	/* Process $_POST requests */
	function process_post() {
		global $sm_error;

		if ( ! isset( $_POST ) || ! isset( $_POST['form'] ) ) {
			return;
		}

		$current_user = wp_get_current_user();

		switch ( $_POST['form'] ) {
			case 'subscribers':
				/* Check user's permissions */
				if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_subscribers_to'] ) {
					break;
				}

				if ( isset( $_POST['subscribe'] ) && isset( $_POST['email'] ) ) {
					$this->add_subscriber( $_POST['email'] );
				} elseif ( isset( $_POST['apply'] ) && '-1' === $_POST['action'] ) {
					$sm_error = __( 'Please select the action you want to take.', 'subme' );
				} elseif ( isset( $_POST['apply'] ) ) {
					if ( ! isset( $_POST['cb'] ) ) {
						$sm_error = __( 'Please select at least one email address.', 'subme' );

						return;
					}

					if ( 'delete' === $_POST['action'] ) {
						/* Loop through each selected subscriber */
						foreach( $_POST['cb'] as $value ) {
							$this->delete_subscriber( absint( $value ) );
						}
					} elseif ( 'activate' === $_POST['action'] ) {
						/* Loop through each selected subscriber */
						foreach( $_POST['cb'] as $value ) {
							$this->activate_subscriber( absint( $value ) );
						}
					} elseif ( 'deactivate' === $_POST['action'] ) {
						/* Loop through each selected subscriber */
						foreach( $_POST['cb'] as $value ) {
							$this->deactivate_subscriber( absint( $value ) );
						}
					}
				}
			break;
			case 'queue':
				/* Check user's permissions */
				if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_queue_to'] ) {
					break;
				}

				if ( isset( $_POST['apply'] ) && 'delete' === $_POST['action'] ) {
					if( ! isset( $_POST['cb'] ) ) {
						$sm_error = __( 'Please select at least one email address.', 'subme' );

						return;
					}

					foreach( $_POST['cb'] as $value ) {
						$this->delete_queue( absint( $value ) );
					}
				}
			break;
			case 'settings':
				if ( ! isset( $_POST['tab'] ) ) {
					return;
				}

				switch ( $_POST['tab'] ) {
					case 'admin':
						/* Check user's permissions */
						if ( ! is_super_admin() ) {
							break;
						}

						if ( isset($_POST['delegate_email_settings_to'] ) ) {
							$this->sm_options['delegate_email_settings_to'] = absint( $_POST['delegate_email_settings_to'] );
						}

						if ( isset( $_POST['delegate_templates_to'] ) ) {
							$this->sm_options['delegate_templates_to'] = absint( $_POST['delegate_templates_to'] );
						}

						if ( isset( $_POST['delegate_appearance_to'] ) ) {
							$this->sm_options['delegate_appearance_to'] = absint( $_POST['delegate_appearance_to'] );
						}

						if( isset( $_POST['delegate_subscribers_to'] ) ) {
							$this->sm_options['delegate_subscribers_to'] = absint( $_POST['delegate_subscribers_to'] );
						}

						if( isset( $_POST['delegate_queue_to'] ) ) {
							$this->sm_options['delegate_queue_to'] = absint( $_POST['delegate_queue_to'] );
						}

						update_option( 'subme_options', $this->sm_options );
					break;
					case 'email':
						/* Check user's permissions */
						if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_email_settings_to'] ) {
							break;
						}

						if ( isset( $_POST['email_notification'] ) ) {
							switch( $_POST['email_notification'] ) {
								case 'subscribers':
								case 'unsubscribers':
								case 'both':
								case 'none':
									$this->sm_options['email_notification'] = $_POST['email_notification'];
								break;
								default:
									$this->sm_options['email_notification'] = 'both';
							}
						}

						if ( isset( $_POST['email_notifications_to'] ) ) {
							$this->sm_options['email_notifications_to'] = absint( $_POST['email_notifications_to'] );
						}

						if ( isset( $_POST['email_notifications_from'] ) ) {
							if ( '' == $_POST['email_notifications_from'] ) {
								$this->sm_options['email_notifications_from'] = '';
							} else {
								$this->sm_options['email_notifications_from'] = absint( $_POST['email_notifications_from'] );
							}
						}

						if ( isset( $_POST['protected_posts'] ) ) {
							if ( 'yes' === $_POST['protected_posts'] ) {
								$this->sm_options['email_protected_posts'] = 'yes';
							} else {
								$this->sm_options['email_protected_posts'] = 'no';
							}
						}
		
						if ( isset( $_POST['private_posts'] ) ) {
							if ( 'yes' === $_POST['private_posts'] ) {
								$this->sm_options['email_private_posts'] = 'yes';
							} else {
								$this->sm_options['email_private_posts'] = 'no';
							}
						}
		
						if ( isset( $_POST['cron_enabled'] ) ) {
							if ( 'yes' === $_POST['cron_enabled'] ) {
								$this->sm_options['cron_enabled'] = 'yes';
							} else {
								$this->sm_options['cron_enabled'] = 'no';
							}
						}

						if ( isset( $_POST['cron_minutes'] ) ) {
							$this->sm_options['cron_minutes'] = absint( $_POST['cron_minutes'] );
						}
		
						if ( isset( $_POST['confirmation_via_queue'] ) ) {
							if ( 'yes' === $_POST['confirmation_via_queue'] ) {
								$this->sm_options['confirmation_via_queue'] = 'yes';
							} else {
								$this->sm_options['confirmation_via_queue'] = 'no';
							}
						}
	
						if ( isset( $_POST['send_post_emails_from'] ) ) {
							if ( '' == $_POST['send_post_emails_from'] || 'Post Author' === $_POST['send_post_emails_from']) {
								$this->sm_options['send_post_emails_from'] = $_POST['send_post_emails_from'];
							} else {
								$this->sm_options['send_post_emails_from'] = absint( $_POST['send_post_emails_from'] );
							}
						}	

						if ( isset( $_POST['send_confirmation_emails_from'] ) ) {
							if ( '' == $_POST['send_confirmation_emails_from'] ) {
								$this->sm_options['send_confirmation_emails_from'] = '';
							} else {
								$this->sm_options['send_confirmation_emails_from'] = absint( $_POST['send_confirmation_emails_from'] );
							}
						}	

						if ( isset( $_POST['emails_per_burst'] ) ) {
							$this->sm_options['emails_per_burst'] = absint( $_POST['emails_per_burst'] );

							/* Make sure at least one email per burst is sent out. Otherwise the queue would fill up */
							if ( 0 == $this->sm_options['emails_per_burst'] ) {
								$this->sm_options['emails_per_burst'] = 1;
							}
						}

						update_option( 'subme_options', $this->sm_options );
					break;
					case 'template':
						/* Check user's permissions */
						if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_templates_to'] ) {
							break;
						}

						if ( isset ($_POST['subscribe_subject'] ) && '' != $_POST['subscribe_subject'] ) {
							$this->sm_options['subscribe_subject'] = sanitize_text_field( $_POST['subscribe_subject'] ) ;
						} else {
							if ( isset( $this->sm_options['subscribe_subject'] ) ) {
								unset( $this->sm_options['subscribe_subject'] );
							}
						}

						if ( isset( $_POST['subscribe_text'] ) && '' != $_POST['subscribe_text'] ) {
							$this->sm_options['subscribe_text'] = $_POST['subscribe_text'];
						} else {
							if ( isset( $this->sm_options['subscribe_text'] ) ) {
								unset( $this->sm_options['subscribe_text'] );
							}
						}
				
						if ( isset( $_POST['notification_subject'] ) && '' != $_POST['notification_subject'] ) {
							$this->sm_options['notification_subject'] = sanitize_text_field( $_POST['notification_subject'] );
						} else {
							if ( isset( $this->sm_options['notification_subject'] ) ) {
								unset( $this->sm_options['notification_subject'] );
							}
						}

						if ( isset( $_POST['notification_text'] ) && '' != $_POST['notification_text'] ) {
							$this->sm_options['notification_text'] = $_POST['notification_text'];
						} else {
							if ( isset( $this->sm_options['notification_text'] ) ) {
								unset( $this->sm_options['notification_text'] );
							}
						}

						/* Reload defaults in case templates are left blank */
						include( SMPATH . 'include/options.php' );

						/* Save options */
						update_option( 'subme_options', $this->sm_options );
					break;
					case 'appearance':
						/* Check user's permissions */
						if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_appearance_to'] ) {
							break;
						}

						if ( isset( $_POST['subme_page'] ) ) {
							if ( 'Select a page' === $_POST['subme_page'] ) {
								if ( isset( $this->sm_options['subme_page'] ) ) unset( $this->sm_options['subme_page'] );
							} else {
								$this->sm_options['subme_page'] = absint( $_POST['subme_page'] );
							}
						}

						$this->sm_options['show_meta'] = ( isset( $_POST['show_meta'] ) ? 1 : 0 );
						$this->sm_options['enable_widget'] = ( isset( $_POST['enable_widget'] ) ? 1 : 0 );

						update_option( 'subme_options', $this->sm_options );
					break;
				}
			break;
		}

	}
	
	var $sm_options = array();
}

/* Subme Widget Class */
class subme_widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'subme_widget', 
			'Subme', 
			array ('description' => 'Displays a subscription form', )
		);
	}

	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		$title = $instance['title'];
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>
<?php
	}

	// Creating widget front-end
	public function widget( $args, $instance ) {
		global $my_subme;

		extract( $args );

		$title = apply_filters( 'widget_title', 'Subscribe' );

		/* Before and after widget arguments are defined by themes */
		echo $args['before_widget'];

		echo '<div class="widget-text wp_widget_plugin_box">';
			if ( $title ) {
				echo $before_title . $title . $after_title;
			}

			$form = '';
			$msg = '';
	
			if ( isset( $_GET['status'] ) ) {
				if ( 'subscribed' === $_GET['status'] ) {
					$msg = __( 'Thank you for confirming your subscription', 'subme' );	
				} else if ( 'unsubscribed' === $_GET['status'] ) {
					$msg = __( 'You have successfully unsubscribed.', 'subme' );
				} else {
					$msg = __( 'Sorry, but something went wrong.', 'subme' );
				}

				$form .= '<p>';
				$form .= esc_html( $msg );
				$form .= '</p>';
			} else {
				/* Process the form when submitted */	
				if ( ( isset( $_POST['subscribe'] ) || isset( $_POST['unsubscribe'] ) ) && isset( $_POST['source'] ) && 'widget' === $_POST['source'] ) {
					/* Check nonce */
					if ( ! isset( $_POST['subme_widget_nonce'] ) || ! wp_verify_nonce( $_POST['subme_widget_nonce'], 'subme_widget' ) ) {
						return;
					}

					if ( ! isset( $_POST['email'] ) ) {
						return;
					}

					if ( ! $my_subme->is_valid_email( strtolower( $_POST['email'] ) ) ) {
						$msg = __( 'Sorry, but this does not seem like a valid email address.', 'subme' );
					} else {
						if ( isset( $_POST['subscribe'] ) ) {
							$msg = $my_subme->subscribe( $_POST['email'] );
						} else {
							$msg = $my_subme->unsubscribe( $_POST['email'] );
						}
					}
				}

				$form .= '<form method="post">';
					$form .= wp_nonce_field( 'subme_widget', 'subme_widget_nonce', true, false );
					$form .= '<input type="hidden" name="source" value="widget" />';
					$form .= '<p>';
					$form .= __( 'Your email address:', 'subme' ) . '<br />';	
					$form .= '<input type="text" name="email" /><br /><br />';
					$form .= '<input type="submit" name="subscribe" value="' . __( 'Subscribe', 'subme' ) . '" />';
					$form .= ' ';
					$form .= '<input type="submit" name="unsubscribe" value="' . __( 'Unsubscribe', 'subme' ) . '" />';
					$form .= '</p>';
				$form .= '</form>';

				if ( strlen( $msg ) > 0 ) {
					$form .= '<p>';
					$form .= esc_html( $msg );
					$form .= '</p>';
				}
			}

			echo $form;
		echo '</div>';	

		echo $args['after_widget'];
	}

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['text'] = strip_tags( $new_instance['text'] );
		$instance['textarea'] = strip_tags( $new_instance['textarea'] );

		return $instance;
	}
}

/* Besides initializing the plugin, the global variable my_subme is also used in the subme_widget class */
global $my_subme;
$my_subme = new subme;
$my_subme->init();

?>
