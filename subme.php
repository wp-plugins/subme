<?php

/*
 * Plugin Name: SubMe
 * Description: SubMe notifies subscribers by email when an new post has been published.
 * Version: 2.0.2
 * Author: SubMe
 * Licence: GPL3
 * Text Domain: SubMe
 */

/* 
 * Version number used to check if database updates are necessary 
 */
define( 'SMVERSION', '2.0.2' );

/* Copyright 2015 SubMe
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
	/* User options which are stored in Wordpress */
	var $sm_options = array();

	function init() {
		/* Localization hook */
		add_action( 'init', array ( &$this, 'load_localization' ) );

		/* Load saved options */
		$this->sm_options = get_option( 'subme_options' );

		/* Load default options. Functions that have not been loaded previously will be set. None will be overridden. */
		include( SMPATH . 'include/options.php' );

		/* Install the tables if needed */
		$this->install();

		/* If needed, go through all updates till the latest version */
		while( $this->sm_options['version'] !== SMVERSION ) {
			$this->update();
		}

		/* Load javascript */
		add_action( 'wp_enqueue_scripts', array( &$this, 'load_js' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'load_js' ) );

		/* Load CSS */
		add_action( 'wp_enqueue_scripts', array( &$this, 'load_css' ) );

		/* Add menu to wordpress */
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );

		/* Check if all options are set correctly. If not, then stop running this plugin. 
		 * This protects the user from running an inproperly configured plugin
		 */
		$ret = $this->check_options();
		if ( 'OK' !== $ret['status'] ) {
			/* Stop the plugin if something if wrong with one or more options */
			return;
		}

		/* Only add action when the return_path header is not set to Default. */
		if ( '' != $this->sm_options['return_path'] ) {
			add_action( 'phpmailer_init', array( &$this, 'fix_return_path' ) );
		}

		/* Make sure cron function is called when enabled */
		if( $this->sm_options['cron_enabled'] ) {
			add_action( 'call_subme_cron', array( &$this, 'subme_cron' ) );
		}

		/* Add link to Meta */
		if ( $this->sm_options['show_meta'] ) {
			 add_action( 'wp_meta', array( &$this, 'add_meta' ), 0 );
		}

		/* Enable SubMe Widget */
		if ( $this->sm_options['enable_widget'] ) {
			add_action( 'widgets_init', array( &$this, 'add_widget' ) );
		}

		/* Add shortcode */
		add_shortcode( 'SubMe', array( &$this, 'sm_shortcode' ) );

		/* Trigger when a user registers */
		if ( 'yes' === $this->sm_options['auto_subscribe'] ) {
			add_action( 'user_register', array( &$this, 'auto_subscribe' ), 10, 1 );
		}

		/* Trigger when new messages are posted */
		add_action ( 'transition_post_status', array( &$this, 'new_post' ), 10, 3 );

		/* Check if subscribers need to be exported */
		if ( isset( $_POST['export'] ) ) {
			/* Check user's permissions */
			$current_user = wp_get_current_user();
			if ( is_super_admin() || $current_user->ID == $this->sm_options['delegate_subscribers_to'] ) {
				$this->export_subscribers();
			}
		}

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
			$current_user->ID != $this->sm_options['delegate_subscriber_options_to'] &&
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
			$current_user->ID == $this->sm_options['delegate_subscriber_options_to'] ||
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

	/* Load stylesheets */
	function load_css() {
		wp_enqueue_style( 'subme_style', plugins_url( 'subme/css/subme.css', dirname( __FILE__ ) ) );
	}

	/* Automatically subscribe upon user registration */
	function auto_subscribe( $user_id ) {
		if ( ! isset( $_POST['user_email'] ) ) {
			return;
		}

		/* Subscribe user. The message returned by the function will be ignored. */
		$this->subscribe( $_POST['user_email'] );				
	}

	/* Display admin update message */
	function display_admin_updated( $msg ) {
		?>
		
		<div class="updated">
			<p>
				<strong><?php echo esc_html( $msg ); ?></strong>
			</p>
		</div>
		
		<?php
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
		global $wpdb;

		$form = '';
		$msg = '';
	
		if ( isset( $_GET['status'] ) ) {
			/* 
			 * Status page after completing the (un)subscribing process
			 */

			if ( 'subscribed' === $_GET['status'] ) {
				$msg = __( 'Thank you for confirming your subscription', 'subme' );	
			} else if ( 'unsubscribed' === $_GET['status'] ) {
				$msg = __( 'You have successfully unsubscribed', 'subme' );
			} else {
				$msg = __( 'Something unexpected has happend.', 'subme' );
			}

			$form .= '<div class="subme_form_confirm_msg">';
				$form .= esc_html( $msg );
			$form .= '</div>';
		} elseif ( isset( $_GET['id'] ) && isset( $_GET['h'] ) ) {
			/*
			 * Manage subscription form
			 */

			if ( strlen( $_GET['id'] ) != 64 || strlen( $_GET['h'] ) != 40 ) {
				$form .= '<div class="subme_form_confirm_msg">';
					$form .= esc_html( __( 'Something unexpected has happend.', 'subme' ) );
				$form .= '</div>';

				return $form;
			}

			$id_hash = $_GET['id'];
			$mgmt_hash = $_GET['h'];

			/* Get user's settings */
			$table = $wpdb->prefix . 'subme';
			$query = $wpdb->prepare( "SELECT `id`, `email`, `authors`, `categories` FROM " . $table . " WHERE `active`=1 AND `id_hash`=%s AND `mgmt_hash`=%s LIMIT 1",
				$id_hash,
				$mgmt_hash
			);
			if ( ! ( $result = $wpdb->get_results( $query ) ) ) {
				$form .= '<div class="subme_form_confirm_msg">';
					$form .= esc_html( __( 'Something unexpected has happend.', 'subme' ) );
				$form .= '</div>';

				return $form;
			}

			$id = $result[0]->id;
			$email = $result[0]->email;
			$selected_authors = ( 'all' === $result[0]->authors ? 'all' : explode( ',', $result[0]->authors ) );
			$selected_categories = ( 'all' === $result[0]->categories ? 'all' : explode( ',', $result[0]->categories ) );

			/* Proces form when submitted */
			if ( isset( $_POST['submit'] )) {
				/* Check nonce */
				if ( ! isset( $_POST['subme_page_nonce'] ) || ! wp_verify_nonce( $_POST['subme_page_nonce'], 'subme_page' ) ) {
					return;
				}

				if ( isset( $_POST['subme_manage_authors_all'] ) ) {
					$new_selected_authors = 'all';
				} else {
					$new_selected_authors = ( isset( $_POST['subme_selected_authors'] ) ? $_POST['subme_selected_authors'] : array() );
				}

				if ( isset( $_POST['subme_manage_categories_all'] ) ) {
					$new_selected_categories = 'all';
				} else {
					$new_selected_categories = ( isset( $_POST['subme_selected_categories'] ) ? $_POST['subme_selected_categories'] : array() );
				}

				/* Process the $_POST request */
				$ret = $this->process_manage_post( $id, $new_selected_authors, $new_selected_categories );

				/* Retrieve the return after process the form */
				$msg = $ret['msg'];

				/* Update the selected authors and categories with that what the user has submitted */
				$selected_authors = ( 'all' === $ret['selected_authors'] ? 'all' : explode( ',', $ret['selected_authors'] ) );
				$selected_categories = ( 'all' === $ret['selected_categories'] ? 'all' : explode( ',', $ret['selected_categories'] ) );
			} elseif ( isset( $_POST['unsubscribe'] ) ) {
				$msg = $this->unsubscribe( $email );
			}

			$form .= '<div class="subme_manage_container">';
				$form .= '<div class="subme_manage_form">';
					$form .= '<form method="post">';
						$form .= wp_nonce_field( 'subme_page', 'subme_page_nonce', true, false );
						$form .= '<input type="hidden" name="source" value="subme_page" />';
					
						/* Display a message if any */
						if ( '' != $msg ) {
							$form .= '<div class="subme_manage_msg_wrapper">';
								$form .= '<p class="subme_manage_msg">';
										$form .= esc_html( $msg );
								$form .= '</p>';
							$form .= '</div>';
						}

						$form .= '<div class="subme_manage_description">';
							$form .= '<p>';
								$form .= __( 'Choose to which authors or categories you would like to subscribe. Please note that you will receive notifications when you have subscribed to an author or a category. This means that you could receive a notification when a new post is published in a category you have subscribed to, but you have not subscribed to the author of the post.', 'subme' );
							$form .= '</p>';
						$form .= '</div>';

						$form .= '<div class="subme_manage_options">';

							/* Manage authors */
							$form .= '<div class="subme_manage_authors">';
								$form .= '<div class="subme_manage_authors_title">';
									$form .= '<h2>Subscribe to the following authors</h2>';
								$form .= '</div>';

								$form .= '<div class="subme_manage_authors_all">';
									$form .= '<input type="checkbox" id="subme_manage_authors_all" name="subme_manage_authors_all" value="1" onclick="subme_manage_authors_enable()"' . ( 'all' === $selected_authors ? ' checked' : '' ) . '> Subscribe to all current and new authors';
								$form .= '</div>';

								$form .= '<div class="subme_checkbox_container">';
									$form .= '<ul class="subme_checkbox_ul">';
										$authors = $this->get_users();
					
										/* User can subscribe to all authors */
										if ( 'all' === $this->sm_options['allowed_authors'] ) {
											foreach( $authors as $author ) {
												$checked = '';
												$disabled = '';

												if ( 'all' === $selected_authors ) {
													$disabled = ' disabled';
												} else {
													foreach( $selected_authors as $selected_author ) {
														if ( $selected_author == $author->ID ) {
															$checked = ' checked';
	
															break;
														}
													}
												}

												$form .= '<li class="subme_checkbox_li"><input type="checkbox" name="subme_selected_authors[' . esc_attr( wp_hash( $author->ID ) ) . ']" value="1"' . $checked . $disabled . '> ' . esc_html( $author->display_name ) .'</li>';
											}
										} else {
											$allowed_authors = explode( ',', $this->sm_options['allowed_authors'] );
		
											foreach( $authors as $author ) {
												/* Check if user is allowed to subscribe to the author */
												$author_id = false;
												foreach( $allowed_authors as $allowed_author ) {
													if ( $author->ID == $allowed_author ) {
														$author_id = $author->ID;

														break;
													}
												}
												
												/* Check if user is subscribed to the author */
												if ( $author_id ) {
													$checked = '';
													$disabled = '';

													if ( 'all' === $selected_authors ) {
														$disabled = ' disabled';
													} else {
														foreach( $selected_authors as $selected_author ) {
															if ( $author_id == $selected_author ) {
																$checked = ' checked';

																break;
															}
														}
													}

													$form .= '<li class="subme_checkbox_li"><input type="checkbox" name="subme_selected_authors[' . esc_attr( wp_hash( $author->ID ) ) . ']" value="1"' . $checked . $disabled . '>' . esc_html( $author->display_name ) . '</li>';
												}
											}
										}
									$form .= '</ul>';
								$form .= '</div>';
							$form .= '</div>';

							/* Manage categories */
							$form .= '<div class="subme_manage_categories">';
								$form .= '<div class="subme_manage_categories_title">';
									$form .= '<h3>Subscribe to the following categories</h3>';
								$form .= '</div>';

								$form .= '<div class="subme_manage_categories_all">';
									$form .= '<input type="checkbox" id="subme_manage_categories_all" name="subme_manage_categories_all" value="1" onclick="subme_manage_categories_enable()"' . ( 'all' === $selected_categories ? ' checked' : '' ) . '> Subscribe to all current and new categories';
								$form .= '</div>';

								$form .= '<div class="subme_checkbox_container">';
									$form .= '<ul class="subme_checkbox_ul">';
										$categories = $this->get_categories();

										/* User can subscribe to all category */
										if ( 'all' === $this->sm_options['allowed_categories'] ) {
											foreach( $categories as $category ) {
												$checked = '';
												$disabled = '';

												if ( 'all' === $selected_categories ) {
													$disabled = ' disabled';
												} else {
													foreach( $selected_categories as $selected_category ) {
														if ( $selected_category == $category['cat']->cat_ID ) {
															$checked = ' checked';
															break;
														}
													}
												}

												$form .= '<li class="subme_checkbox_li"><input type="checkbox" name="subme_selected_categories[' . esc_attr( wp_hash( $category['cat']->cat_ID ) ) . ']" value="1"' . $checked . $disabled . '> ' . esc_html( $category['cat']->name ) .'</li>';
											}
										} else {
											$allowed_categories = explode( ',', $this->sm_options['allowed_categories'] );

											foreach( $categories as $category ) {
												/* Check if it is allowed to subscribe to the category */
												$category_id = false;
												foreach( $allowed_categories as $allowed_category ) {
													if ( $category['cat']->cat_ID == $allowed_category ) {
														$category_id = $category['cat']->cat_ID;
														break;
													}
												}

												/* Check if the user has subscribed to the category */
												if ( $category_id ) {
													$disabled = '';
													$checked = '';

													if ( 'all' === $selected_categories ) {
														$disabled = ' disabled';
													} else {
														foreach( $selected_categories as $selected_category ) {
															if ( $category_id == $selected_category ) {
																$checked = ' checked';
																break;
															}
														}
													}

													$form .= '<li class="subme_checkbox_li"><input type="checkbox" name="subme_selected_categories[' . esc_attr( wp_hash( $category['cat']->cat_ID ) ) . ']" value="1"' . $checked . $disabled . '>' . esc_html( $category['cat']->name ) . '</li>';
												}
											}					
										}
									$form .= '</ul>';
								$form .= '</div>';
							$form .= '</div>';
						$form .= '</div>';

						$form .= '<div class="subme_manage_submit_wrapper">';
							$form .= '<div class="subme_manage_submit">';
								$form .= '<input type="submit" id="subme_manage_subme_submit" class="subme_manage_submit" name="submit" value="' . __( 'Save changes', 'subme' ) . '" />';
							$form .= '</div>';

							$form .= '<div class="subme_manage_unsubscribe">';
								$form .= '<input type="submit" id="subme_manage_unsubscribe_submit" class="subme_manage_submit" name="unsubscribe" value="' . __( 'Unsubscribe', 'subme' ) . '" />';
							$form .= '</div>';
						$form .= '</div>';
					$form .= '</form>';
				$form .= '</div>';
			$form .= '</div>';
		} else {
			/* 
			 * Subscribe/Unsubscribe/Manage Subscription form
			 */

			/* Process the form when submitted */	
			if ( isset( $_POST ) && isset( $_POST['source'] ) && 'subme_page' === $_POST['source'] ) {
				/* Check nonce */
				if ( ! isset( $_POST['subme_page_nonce'] ) || ! wp_verify_nonce( $_POST['subme_page_nonce'], 'subme_page' ) ) {
					return;
				}

				/* Process request */
				if ( isset( $_POST['subscribe'] ) ) {
					$msg = $this->process_form_widget_post( 'subscribe' );
				} elseif ( isset( $_POST['unsubscribe'] ) ) {
					if ( 'yes' === $this->sm_options['show_form_unsubscribe'] ) {
						$msg = $this->process_form_widget_post( 'unsubscribe' );
					}
				} elseif ( isset( $_POST['manage'] ) ) {
					if ( 'yes' === $this->sm_options['show_form_unsubscribe'] ) {
						$msg = $this->process_form_widget_post( 'manage' );
					}
				}
			}

			$form .= '<div class="subme_form_container">';
				$form .= '<div class="subme_form_form">';
					$form .= '<form method="post">';
						$form .= wp_nonce_field( 'subme_page', 'subme_page_nonce', true, false );
						$form .= '<input type="hidden" name="source" value="subme_page" />';

						/* Form description */
						if ( 'yes' === $this->sm_options['enable_form_description'] ) {
							$form .= '<div class="subme_form_description">';
								$form .= '<p>';
									$form .= esc_html( $this->sm_options['form_description'] );
								$form .= '</p>';
							$form .= '</div>';
						}

						/* Email address */
						$form .= '<div class="subme_form_email_wrapper">';
							$form .= '<label id="subme_form_email_label" class="subme_form_label" for="subme_form_email_input">';
								$form .= __( 'Your email address:', 'subme' );
							$form .= '</label>';
							$form .= '<input type="text" id="subme_form_email_input" class="subme_form_input" name="email" />';
						$form .= '</div>';

						$form .= '<div class="subme_form_msg">';
							$form .= '<p>';
								$form .= esc_html( $msg );
							$form .= '</p>';
						$form .= '</div>';

						/* Submit buttons */
						$form .= '<div class="subme_form_submit_wrapper">';
							$form .= '<input type="submit" id="subme_form_subscribe_submit" class="subme_form_submit" name="subscribe" value="' . __( 'Subscribe', 'subme' ) . '" />';

							if ( 'yes' === $this->sm_options['show_form_unsubscribe'] ) {
								if ( 'yes' === $this->sm_options['allow_subscriber_options'] ) {
									$form .= '<input type="submit" id="subme_form_manage_submit" class="subme_form_submit" name="manage" value="' . __( 'Manage Subscription', 'subme' ) . '" />';
								} else {
									$form .= '<input type="submit" id="subme_form_unsubscribe_submit" class="subme_form_submit" name="unsubscribe" value="' . __( 'Unsubscribe', 'subme' ) . '" />';
								}
							}
						$form .= '</div>';
					$form .= '</form>';
				$form .= '</div>';
			$form .= '</div>';
		}

		return $form;
	}

	/* Cron automatically called by Wordpress */
	function subme_cron() {
		if ( DEFINED( 'SM_CRON_ACTIVE' ) ) return;
		define( 'SM_CRON_ACTIVE', true );

		global $wpdb;

		$table = $wpdb->prefix . 'subme_queue';
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

			if ( ! wp_next_scheduled( 'call_subme_cron' ) ) {
				/* When triggered, Wordpress will call the action hook call_subme_cron, which in turn will call the function subme_cron */
				wp_schedule_single_event( time() + ( absint( $this->sm_options['cron_minutes'] ) * 60 ), 'call_subme_cron' );
			}
		}
	}

	/* Generates a sha1 hash as a randomly generated string. */
	function gen_salt() {
		if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$salt = hash( 'sha1', microtime() . wp_salt() . base64_encode( openssl_random_pseudo_bytes( 16 ) ) );
		} else {
			$salt = hash( 'sha1', microtime() . wp_salt() . mt_rand( 100000, 999999 ) );
		}

		return $salt;
	}

	/* Generates a unique id_hash */
	function gen_id_hash() {
		global $wpdb;

		$table = $wpdb->prefix . 'subme';

		/* Generate a new id_hash and check if it already exists. */
		do {
			if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
				$id_hash = hash( 'sha256', $this->gen_salt() . base64_encode( openssl_random_pseudo_bytes( 16 ) ) );
			} else {
				$id_hash = hash( 'sha256', $this->gen_salt() . mt_rand( 100000, 999999 ) );
			}

			$query = $wpdb->prepare( "SELECT id FROM " . $table . " WHERE id_hash=%s",
				$id_hash 
			);
			$result = $wpdb->get_results( $query );
		} while ( $result );

		return $id_hash;
	}

	/* Install the tables for the active blog if needed. Called from the init function. */
	function install() {
		global $wpdb;
		global $my_subme;

		/* Fresh install if SubMe tables do not exist */
		$table = $wpdb->prefix . "subme";
		if ( $table != $wpdb->get_var( "SHOW TABLES LIKE '" . $table . "'" ) ) {
			/* Create SubMe table */
			$query = "CREATE TABLE " . $table . " (
				`id` int(11) NOT NULL auto_increment,
				`id_hash` VARCHAR(64) NOT NULL DEFAULT '',
				`email` varchar(64) NOT NULL DEFAULT '',
				`active` tinyint(1) NOT NULL DEFAULT '0',
				`timestamp` int(11) NOT NULL DEFAULT '0',
				`ip` varchar(64) NOT NULL DEFAULT '',
				`conf_hash` varchar(65) DEFAULT NULL,
				`conf_timestamp` int(11) DEFAULT NULL,
				`conf_ip` varchar(64) DEFAULT NULL,
				`mgmt_hash` VARCHAR(40) NOT NULL DEFAULT '',
				`authors` VARCHAR(2048) NOT NULL DEFAULT 'all',
				`categories` VARCHAR(2048) NOT NULL DEFAULT 'all',
				UNIQUE KEY (`id_hash`),
				UNIQUE KEY (`email`),
				PRIMARY KEY (`id`)
				)";
			$wpdb->query( $query );

			/* Create SubMe queue table */
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
			$wpdb->query( $query );

			/* Store the SubMe version */
			$this->sm_options['version'] = SMVERSION;
			update_option( 'subme_options', $this->sm_options );
		}
	}

	/* Update SubMe database if needed. */
	function update() {
		global $wpdb;
		$updated = false;

		/* The version field was not set till version 1.2.3. This is needed for the update function to work properly. */
		if ( ! isset( $this->sm_options['version'] ) ) $this->sm_options['version'] = '1.2.3';

		switch( $this->sm_options['version'] ) {
			case '1.2.3':
				$table = $wpdb->prefix . "subme";

				/* Create id_hash column */
				$query = "ALTER TABLE " . $table . " ADD COLUMN `id_hash` VARCHAR(64) DEFAULT ''";
				$wpdb->query( $query );

				/* Create mgmt_id column */
				$query = "ALTER TABLE " . $table . " ADD COLUMN `mgmt_hash` VARCHAR(40) DEFAULT ''";
                		$wpdb->query( $query );		

				/* Create authors columm */
				$query = "ALTER TABLE " . $table . " ADD COLUMN `authors` VARCHAR(2048) NOT NULL DEFAULT 'all'";
				$wpdb->query( $query );

				/* Create categories columm */
				$query = "ALTER TABLE " . $table . " ADD COLUMN `categories` VARCHAR(2048) NOT NULL DEFAULT 'all'";
				$wpdb->query( $query );

				/* All current subscribers need to be updated */
				$query = "SELECT `id`, `email` FROM " . $table . " WHERE `id_hash`='' OR `mgmt_hash`=''";
				$results = $wpdb->get_results( $query );
				foreach( $results as $user ) {
					$id_hash = $this->gen_id_hash();
					$mgmt_hash = hash( 'sha1', $this->gen_salt() . $user->email );

					$query = $wpdb->prepare( "UPDATE " . $table . " SET `id_hash`=%s, `mgmt_hash`=%s WHERE `id`=%s",
						$id_hash,
						$mgmt_hash,
						$user->id );
					$wpdb->query( $query );
				}

				/* Modify id_hash column to disallow NULL values */
				$query = "ALTER TABLE " . $table . " MODIFY `id_hash` VARCHAR(64) NOT NULL DEFAULT ''";
				$wpdb->query( $query );

				/* Add unique constraint to id_hash */
				$query = "ALTER TABLE " . $table . " ADD UNIQUE (`id_hash`)";
				$wpdb->query( $query );

				/* Modify mgmt_hash column to disallow NULL values */
				$query = "ALTER TABLE " . $table . " MODIFY `mgmt_hash` VARCHAR(40) NOT NULL DEFAULT ''";
				$wpdb->query( $query );	

				/* Modify conf_hash column to a smaller size */
				$query = "ALTER TABLE " . $table . " MODIFY `conf_hash` VARCHAR(65) DEFAULT NULL";
				$wpdb->query( $query );

				/* Fix stripslashes */
				$this->sm_options['subscribe_subject'] = stripslashes( $this->sm_options['subscribe_subject'] );
				$this->sm_options['subscribe_text']  = stripslashes( $this->sm_options['subscribe_text'] );
				$this->sm_options['notification_subject'] = stripslashes( $this->sm_options['notification_subject'] );
				$this->sm_options['notification_text'] = stripslashes( $this->sm_options['notification_text'] );
				$this->sm_options['manage_subject'] = stripslashes( $this->sm_options['manage_subject'] );
				$this->sm_options['manage_text'] = stripslashes( $this->sm_options['manage_text'] );
				$this->sm_options['form_description'] = stripslashes( $this->sm_options['form_description'] );
				$this->sm_options['widget_description'] = stripslashes( $this->sm_options['widget_description'] );
				$this->sm_options['custom_css'] = stripslashes( $this->sm_options['custom_css'] );

				/* Update the stored version number to next release */
				$this->sm_options['version'] = '2.0';
			break;
			case '2.0':
				$updated = true;

				/* Update the stored version number to next release */
				$this->sm_options['version'] = '2.0.1';
			case '2.0.1':
				$updated = true;

				/* Update the stored version number to next release */
				$this->sm_options['version'] = '2.0.2';
			break;
		}

		if ( true === $updated ) {
			file_put_contents( SMPATH . '/css/subme.css', $this->minify_css( $this->sm_options['custom_css'] ) );
		}

		update_option( 'subme_options', $this->sm_options );	
	}

	/* Checks if all options are correctly set. When something is wrong the plugin will not function. */
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

		/* Check if the custom subme.css file content is equal to what is stored in the database */
		if ( is_admin() ) {
			if ( ! ( $css = file_get_contents( SMPATH . 'css/subme.css' ) ) ) {
				$ret['status'] = 'NO';
				array_push( $ret['msgs'], __( 'The custom stylesheet subme.css could not be openend. Please make sure that the file exists and that you have the right permissions. Creating the subme.css file should be done from the Appearance tab.', 'subme' ) );
			}  else {
				if ( $css !== $this->minify_css( $this->sm_options['custom_css'] ) ) {
					$ret['status'] = 'NO';
					array_push( $ret['msgs'], __( 'The custom stylesheet subme.css seems to be outdated. Please make sure all custom CSS changes are done in the Appearance tab.', 'subme' ) );
				}
			}
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
	
	/* Set the return path equal to that of the From address */
	function fix_return_path( $phpmailer ) {
		if ( 'Admin' === $this->sm_options['return_path'] ) {
			$phpmailer->Sender = get_option( 'admin_email' );
		} elseif ( 'From address' === $this->sm_options['return_path'] ) {
			$phpmailer->Sender = $phpmailer->From;
		}
	}

	/* Send out an email */
	function mail( $recipient, $sender, $name, $subject, $message ) {
		if ( '' == $recipient  || '' == $subject || '' == $message ) return;

		$subject = $subject;
		$message = $message;
	
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

	/* Strips plain text without cutting words */
	function trim_words( $text, $length = 700 ) {
		$len = strlen( $text );
		
		/* Text is not longer than length */
		if ( $len <= $length ) {
			return $text;
		}

		$last_space = strrpos( substr( $text, 0, $length ), ' ' );
		return substr( $text, 0, $last_space ) . ' [..]';
	}

	/* Substitute keywords in templates */
	function substitute( $text, $post = false ) {
		/* Replace variables */
		$text = preg_replace( '/@@BLOGLINK/', get_option( 'home' ), $text );
		$text = preg_replace( '/@@BLOGNAME/', get_option( 'blogname' ), $text );

		/* Replace post specific variables */
		if ( $post ) {
			/* Include the Html2Text class */
			require_once( SMPATH . '/include/Html2Text.php' );

			$author = get_userdata( $post->post_author );
			$title = wp_strip_all_tags( stripslashes( $post->post_title ) );

			/* Get plain text excerpt of the post */
			$html2text = new Html2Text\Html2Text( trim( $post->post_content ) );
			$content = trim( $this->trim_words( $html2text->get_text() ) );

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
	function add_subscriber( $email, $active = 1 ) {
		global $wpdb;
		global $sm_error;

		$email = strtolower( trim( $email ) );

		/* Check if the email is valid */
		if ( ! $this->is_valid_email( $email ) ) {
			$sm_error = __( 'Sorry, but this does not seem like a valid email address.', 'subme' );

			return false;
		}

		$table = $wpdb->prefix . 'subme';

		/* Check if the email already exists */
		$query = $wpdb->prepare( "SELECT email FROM $table WHERE email=%s", $email );
		$results = $wpdb->get_results( $query );
		if ( $results ) {
			$sm_error = __( 'Sorry, but this email address has already subscribed.', 'subme' );

			return false;
		}

		/* Generate hashes */
		$salt = $this->gen_salt();
		$id_hash = $this->gen_id_hash();
		$mgmt_hash = hash( 'sha1', $salt . $email );

		/* Insert the new subscriber */
		$query = $wpdb->prepare( "INSERT INTO $table (id_hash, email, active, timestamp, ip, conf_hash, conf_timestamp, conf_ip, mgmt_hash) VALUES (
			%s, %s, %d, %d, %s, NULL, %d, '', %s)",
				$id_hash,
				$email,
				$active,
				time(),
				$this->get_ip(),
				time(),
				$mgmt_hash
			);
		$wpdb->query( $query );

		return true;
	}

	/* Export subscribers as CSV file */
	function export_subscribers() {
		global $wpdb;
		global $sm_error;

		$table = $wpdb->prefix . 'subme';
		$results = $wpdb->get_results( "SELECT active, email FROM $table" );

		/* Send out headers */
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="subscribers_' . date( 'Ymd' ). '.csv";' );
		header( 'Content-Transfer-Encoding: binary' );

		echo "Active,Email\r\n";
		foreach ( $results as $result ) {
			echo ( $result->active ? 'Yes' : 'No' ) . ',', $result->email . "\r\n";
		}
		
		exit(0);
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

		/* Generate hashes */
		$salt = $this->gen_salt();
		$id_hash = $this->gen_id_hash();
		$conf_hash = '1' . hash( 'sha256', $salt . $email );
		$mgmt_hash = hash( 'sha1', $salt . $email );

		/* Generate a new confirmation hash and get the AUTO_INCREMENT ID */
		$conf_hash = '1' . hash( 'sha256', $this->gen_salt() . $email );	
		$query = $wpdb->prepare( "INSERT INTO $table (id_hash, email, active, timestamp, ip, conf_hash, conf_timestamp, conf_ip, mgmt_hash) VALUES (
			%s, %s, '0', %d, %s, %s, NULL, NULL, %s)",
				$id_hash,
				$email,
				time(),
				$this->get_ip(),
				$conf_hash,
				$mgmt_hash
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

		$url = add_query_arg( array( 'id' => $id_hash, 'h' => $mgmt_hash . $new_id ), get_option( 'home' ) );
		$msg = preg_replace( '/@@MANAGELINK/', $url, $msg );

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
		$conf_hash = '0' . hash( 'sha256', $this->gen_salt() . $email . time() );
		$query = $wpdb->prepare( "UPDATE $table SET conf_hash=%s, conf_timestamp=NULL, conf_ip=NULL WHERE email=%s",
				$conf_hash,
				$email
			);
		$wpdb->query( $query );

		/* Get the old ID */
		$query = $wpdb->prepare( "SELECT id, id_hash, mgmt_hash FROM $table WHERE email=%s LIMIT 1", $email );
		$result = $wpdb->get_results( $query );
		if ( $result ) {
			$id = $result[0]->id;
			$id_hash = $result[0]->id_hash;
			$mgmt_hash = $result[0]->mgmt_hash;
		} else {
			return __( 'Sorry, but something went wrong.', 'subme' );
		}

		/* Construct email for unsubscriber */
		$subj = $this->substitute( $this->sm_options['subscribe_subject'] );
		$msg = $this->substitute( $this->sm_options['subscribe_text'] );

		$url = add_query_arg( 'subme', $conf_hash . $id, get_option( 'home' ) );
		$msg = preg_replace( '/@@LINK/', $url, $msg );

		$url = add_query_arg( array( 'id' => $id_hash, 'h' => $mgmt_hash . $id ), get_option( 'home' ) );
		$msg = preg_replace( '/@@MANAGELINK/', $url, $msg );

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

	/* Manage subscription */
	function manage( $email ) {
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

		/* Get the id_hash */
		$query = $wpdb->prepare( "SELECT `id`, `id_hash`, `conf_hash`, `mgmt_hash`, `active` FROM $table WHERE `email`=%s LIMIT 1", $email );
		$result = $wpdb->get_results( $query );
		if ( $result ) {
			if ( 1 != $result[0]->active ) {
				return __( 'Sorry, but this email address has not been confirmed yet.', 'subme' );
			}

			$id = $result[0]->id;
			$id_hash = $result[0]->id_hash;
			$conf_hash = $result[0]->conf_hash;
			$mgmt_hash = $result[0]->mgmt_hash;
		} else {
			return __( 'Sorry, but something went wrong.', 'subme' );
		}

		/* Construct email for manage subscription */
		$subj = $this->substitute( $this->sm_options['manage_subject'] );
		$msg = $this->substitute( $this->sm_options['manage_text'] );

		/* Prepare url for Manage Subscription */
		$url = add_query_arg( array( 'id' => $id_hash, 'h' => $mgmt_hash ), wp_get_shortlink( $this->sm_options['subme_page'] ) );
		$msg = preg_replace( '/@@MANAGELINK/', $url, $msg );

		/* Prepare the email for sending */
		if ( '' == $this->sm_options['send_manage_emails_from'] ) {
			$this->prepare_mail( $email, '', '', $subj, $msg, $this->sm_options['manage_via_queue'] );
		} else {
			$from = get_userdata( $this->sm_options['send_manage_emails_from'] );
			$this->prepare_mail( $email, $from->user_email, ( '' != $from->display_name ? $from->display_name : $from->user_login ), $subj, $msg, $this->sm_options['manage_via_queue'] );
		}

		/* Schedule the pseudo cron so notifications emails will get send when cron is used */
		if ( $this->sm_options['cron_enabled'] ) {
			if ( ! wp_next_scheduled( 'call_subme_cron' ) ) {
				/* When triggered, Wordpress will call the action hook call_subme_cron, which in turn will call the function subme_cron */
				wp_schedule_single_event( time() + ( absint( $this->sm_options['cron_minutes'] ) * 60 ), 'call_subme_cron' );
			}
		}

		return __( 'A message with a link to the manage your subscription has been sent!', 'subme' );
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

		/* Get the Post's categories */
		$categories = wp_get_post_categories( $post->ID );

		/* Get the authors and category IDs for which notifications are allowed */
		$allowed_authors = explode( ',', $this->sm_options['allowed_authors'] );
		$allowed_categories = explode( ',', $this->sm_options['allowed_categories'] );

		/* Check if the post belongs to an author for which it is allowed to send nofitications */
		$allowed = false;
		if ( 'all' === $this->sm_options['allowed_authors'] ) {
			$allowed = true;
		} else {
			foreach( $allowed_authors as $allowed_author ) {
				if ( $allowed_author == $post->post_author ) {
					$allowed = true;

					break;
				}
			}
		}

		/* Check if the post belongs to an category for which it is allowed to send nofitications */
		if ( ! $allowed ) {
			if ( 'all' === $this->sm_options['allowed_categories'] ) {
				$allowed = true;
			} else {
				foreach( $allowed_categories as $allowed_category ) {
					foreach( $categories as $category ) {
						if ( $allowed_category == $category ) {
							$allowed = true;

							break;
						}
					}

					if ( $allowed) break;
				}		
			}	
		}

		/* Do not send notifications if the post doesn't fall into the allowed authors or categories selection */
		if ( ! $allowed ) {
			return;
		}
		
		/* Set the title and content */
		$title = $this->substitute( $this->sm_options['notification_subject'], $post );
		$content = $this->substitute( $this->sm_options['notification_text'], $post );

		/* Set the sender of the notification emails */
		if ( '' == $this->sm_options['send_post_emails_from'] ) {
			$sender = '';
		} elseif ( 'Post Author' === $this->sm_options['send_post_emails_from'] ) {
			$sender = get_userdata( $post->post_author );
		} else {
			$sender = get_userdata( $this->sm_options['send_post_emails_from'] );
		}

		$table = $wpdb->prefix . 'subme';
		$results = $wpdb->get_results( "SELECT `id_hash`, `email`, `mgmt_hash`, `authors`, `categories` FROM $table WHERE active=1" );
		foreach ( $results as $result ) {
			/* Check if user has subscribed to the post's author or category  */
			if ( 'yes' === $this->sm_options['allow_subscriber_options'] ) {
				$selected_authors = $result->authors;
				$selected_categories = $result->categories;

				if ( 'all' !== $selected_authors && 'all' !== $selected_categories ) {
					$selected_authors = explode( ',', $selected_authors );
					$selected_categories = explode( ',', $selected_categories );
					$selected = false;

					/* Check if the user subscribed to the author of the post */
					foreach( $selected_authors as $selected_author ) {
						if ( $selected_author == $post->post_author ) {
							$selected = true;

							break;
						}
					}

					/* Check if the user subscribed to the category of the post */
					if ( ! $selected ) {
						foreach( $selected_categories as $selected_category ) {
							foreach( $categories as $category ) {
								if ( $selected_category == $category ) {
									$selected = true;

									break;
								}
							}

							if ( $selected ) break;
						}		
					}

					/* User has not subscribed to the post's author or category. Continue to the next user */
					if ( ! $selected ) {
						continue;
					}
				}
			}

			/* Prepare url for Manage Subscription */
			if ( 'yes' === $this->sm_options['allow_subscriber_options'] ) {
				$url = add_query_arg( array( 'id' => $result->id_hash . $id, 'h' => $result->mgmt_hash ), wp_get_shortlink( $this->sm_options['subme_page'] ) );
				$__content = preg_replace( '/@@MANAGELINK/', $url, $content );
			} else {
				$__content = $content;
			}

			/* Prepare email */
			if ( '' == $sender ) {
				$this->prepare_mail( $result->email, '', '', $title, $__content );
			} else {
				$this->prepare_mail( $result->email, $sender->user_email, ( '' != $sender->display_name ? $sender->display_name : $sender->user_login ), $title, $__content );
			}
		}

		if ( $this->sm_options['cron_enabled'] ) {
			if ( ! wp_next_scheduled( 'call_subme_cron' ) ) {
				/* When triggered, Wordpress will call the action hook call_subme_cron, which in turn will call the function subme_cron */
				wp_schedule_single_event( time() + ( absint( $this->sm_options['cron_minutes'] ) * 60 ), 'call_subme_cron' );
			}
		}
	}

	/* Return a list of all Wordpress users, except users with the role Subscriber */
	function get_users() {
		$users = array();
		$result = get_users( 'orderby=name&order=ASC' );

		foreach( $result as $user ) {
			if ( ! in_array( 'subscriber', $user->roles ) ) {
				$users[] = $user;
			}
		}

		return $users;
	}

	/* Recursive function to get all categories and the number of indents to align with parents */
	function get_categories( &$list = array(), $parent = false, $indent = 0 ) {
		/* Initial request of all first level categories */
		if ( ! $parent ) {
			$cats = get_categories( array( 'parent' => 0, 'hide_empty' => false ) );
		} else {
			$cats = get_categories( array( 'parent' => $parent->cat_ID, 'hide_empty' => false ) );
		}

		foreach( $cats as $cat ) {
				$item = array( 'indent' => $indent, 'cat' => $cat );

				array_push( $list, $item );

				$this->get_categories( $list, $cat, $indent + 1 );
		}
		
		return $list;	
	}

	/* Minify CSS */
	function minify_css( $css ) {
		$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
		$css = preg_replace( '/\s+/', '', $css );
		$css = str_replace( array( "\r\n", "\r", "\n", "\t" ), '', $css );

		return $css;
	}
	
	/* Process default form and widget $_POSTs */
	function process_form_widget_post( $action ) {
		$email = ( isset( $_POST['email'] ) ? trim( strtolower( $_POST['email'] ) ) : '' );

		$msg = '';

		if ( ! $this->is_valid_email( strtolower( $email ) ) ) {
			$msg = __( 'Sorry, but this does not seem like a valid email address.', 'subme' );
		} else {
			if ( 'subscribe' === $action ) {
				$msg = $this->subscribe( $email );
			} elseif ( 'unsubscribe' === $action ) {
				$msg = $this->unsubscribe( $email );
			} elseif ( 'manage' === $action ) {
				if ( 'yes' === $this->sm_options['allow_subscriber_options'] ) {
					$msg = $this->manage( $email );
				}
			} else {
				$msg = __( 'Something unexpected has happend.', 'subme' );
			}
		}

		return $msg;
	}

	/* Process manage subscriber form */
	function process_manage_post( $id, $new_selected_authors, $new_selected_categories ) {
		global $wpdb;

		/* SubMe database table */
		$table = $wpdb->prefix . 'subme';

		/* Authors */
		if ( 'all' === $new_selected_authors ) {
			$selected_authors = 'all';
		} else {
			$authors = $this->get_users();
			$selected_authors = '';
			foreach( $new_selected_authors as $key => $value ) {
				if ( 'all' === $this->sm_options['allowed_authors'] ) {
					/* Check if user exists */
					$author_id = false;
					foreach( $authors as $author ) {
						if ( wp_hash( $author->ID ) === $key ) {
							$author_id = $author->ID;
							break;
						}
					}

					if ( $author_id ) {
						if ( '' == $selected_authors ) {
							$selected_authors = $author_id;
						} else {
							$selected_authors .= ',' . $author_id;
						}
					}
				} else {
					/* Check if user is allowed */
					$author_id = false;
					$allowed_authors = explode( ',', $this->sm_options['allowed_authors'] );
					foreach( $allowed_authors as $allowed_author ) {
						if ( wp_hash( $allowed_author ) === $key ) {
							$author_id = $allowed_author;
							break;
						}
					}	

					if ( $author_id ) {
						if ( '' == $selected_authors ) {
							$selected_authors = $author_id;
						} else {
							$selected_authors .= ',' . $author_id;
						}
					}
				}
			}
		}

		/* Categories */
		if ( 'all' === $new_selected_categories ) {
			$selected_categories = 'all';
		} else {
			$categories = $this->get_categories();
			$selected_categories = '';
			foreach( $new_selected_categories as $key => $value ) {
				if ( 'all' === $this->sm_options['allowed_categories'] ) {
					/* Check if category exists */
					$category_id = false;
					foreach( $categories as $category ) {
						if ( wp_hash( $category['cat']->cat_ID ) === $key ) {
							$category_id = $category['cat']->cat_ID;
							break;
						}
					}

					if ( $category_id ) {
						if ( '' == $selected_categories ) {
							$selected_categories = $category_id;
						} else {
							$selected_categories .= ',' . $category_id;
						}
					}
				} else {
					/* Check if user is allowed to subscribe to category */
					$category_id = false;
					$allowed_categories = explode( ',', $this->sm_options['allowed_categories'] );
					foreach( $allowed_categories as $allowed_category ) {
						if ( wp_hash( $allowed_category ) === $key ) {
							$category_id = $allowed_category;
							break;
						}
					}

					if ( $category_id ) {
						if ( '' == $selected_categories ) {
							$selected_categories = $category_id;
						} else {
							$selected_categories .= ',' . $category_id;
						}
					}
				}
			}
		}

		if ( strlen( $selected_authors ) > 2048 ) {
			$msg = __( 'Sorry, but there are too many authors selected.', 'subme' );
			$ret = array( 'selected_authors' => $selected_authors, 'selected_categories' => $selected_categories, 'msg' => $msg );

			return $ret;
		}

		if ( strlen( $selected_categories ) > 2048 ) {
			$msg = __( 'Sorry, but there are too many categories selected.', 'subme' );
			$ret = array( 'selected_authors' => $selected_authors, 'selected_categories' => $selected_categories, 'msg' => $msg );

			return $ret;
		}

		/* Update the user's data */
		$query = $wpdb->prepare( "UPDATE " . $table . " SET `authors`=%s, `categories`=%s WHERE `id`=%s",
			$selected_authors,
			$selected_categories,
			$id
		);

		if ( false === $wpdb->query( $query ) ) {
			$msg = __( 'Sorry, but something went wrong.', 'subme' );
		} else {
			$msg = __( 'Your changes have been saved.', 'subme' );
		}

		$ret = array( 'selected_authors' => $selected_authors, 'selected_categories' => $selected_categories, 'msg' => $msg );

		return $ret;
	}

	/* Process $_POST requests */
	function process_admin_post() {
		global $sm_error;
		global $sm_updated;

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
					if ( $this->add_subscriber( $_POST['email'] ) ) {
						$sm_updated = sprintf( __( "Added %s", 'subme' ), $_POST['email'] );
					}
				} elseif ( isset( $_POST['apply'] ) && '-1' === $_POST['action'] ) {
					$sm_error = __( 'Please select the action you want to take.', 'subme' );
				} elseif ( isset( $_POST['apply'] ) ) {
					if ( ! isset( $_POST['cb'] ) || ! is_array( $_POST['cb'] ) ) {
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
				} elseif ( isset( $_POST['import'] ) ) {
					if ( $_FILES['file']['error'] > 0 ) {
						$sm_error = __( 'Please select a CSV file to upload.', 'subme' );

						return;
					}
					
					/* Read all records and try to import them */
					$file = file_get_contents( $_FILES['file']['tmp_name'] );
					$records = preg_split( '/\r\n|\r|\n/', $file );
					$count = 0;
					$imported = 0;
					$failures = array();
					$format_error = false;
					foreach ( $records as $record ) {
						$count++;

						/* Skip the first line */
						if ( 1 == $count ) continue;

						/* Ignore empty lines */
						if ( empty( $record ) ) continue;

						$data = explode( ',', $record );

						/* Check if there are two columns (Active, Email) */
						if ( 2 != count( $data) ) {
							$format_error = true;

							continue;
						}
		
						$active = ( 'Yes' === $data[0] ? 1 : 0 );

						if ( ! $this->add_subscriber( $data[1], $active ) ) {
							/* Only add printable data to failures. */
							if ( 0 < strlen( esc_html( $data[1] ) ) ) {
								$failures[] = $data[1];
							}
						} else {
							$imported++;
						}						
					}

					/* Show number of imported emails */
					$sm_updated = sprintf ( __( "Imported %d %s.", 'subme' ), $imported, ( 1 == $imported ? __( 'email address', 'subme' ) : __( 'email adresses', 'subme' ) ) );

					/* Show errors */
					$sm_error = '';
					if ( $format_error ) {
						$sm_error = __( 'Invalid CSV file format detected.', 'subme' );
					}
					if ( 0 < count( $failures ) ) {
						$count = 0;
						
						$sm_error .= ( '' == $sm_error ? '' : ' ' );
						$sm_error .= __( 'Failed importing the following email addresses:', 'subme' ) . ' ';
						foreach ( $failures as $email ) {
							if ( 0 == $count ) {
								$sm_error .= $email;
							} else {
								$sm_error .= ', ' . $email;
							}

							$count++;
						}
					}
				}
			break;
			case 'queue':
				/* Check user's permissions */
				if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_queue_to'] ) {
					break;
				}

				if ( isset( $_POST['apply'] ) && '-1' === $_POST['action'] ) {
					$sm_error = __( 'Please select the action you want to take.', 'subme' );
				} elseif ( isset( $_POST['apply'] ) && 'delete' === $_POST['action'] ) {
					if( ! isset( $_POST['cb'] ) || ! is_array( $_POST['cb'] ) ) {
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

						$users = $this->get_users();

						if ( isset($_POST['delegate_email_settings_to'] ) ) {
							$user_id = false;
							foreach( $users as $user ) {
								if ( wp_hash( $user->ID ) === $_POST['delegate_email_settings_to'] ) {
									$user_id = $user->ID;
									break;
								}
							}

							if ( $user_id ) {
								$this->sm_options['delegate_email_settings_to'] = $user_id;
							}
						}

						if ( isset( $_POST['delegate_templates_to'] ) ) {
							$user_id = false;
							foreach( $users as $user ) {
								if ( wp_hash( $user->ID ) === $_POST['delegate_templates_to'] ) {
									$user_id = $user->ID;
									break;
								}
							}

							if ( $user_id ) {
								$this->sm_options['delegate_templates_to'] = $user_id;
							}
						}

						if ( isset( $_POST['delegate_subscriber_options_to'] ) ) {
							$user_id = false;
							foreach( $users as $user ) {
								if ( wp_hash( $user->ID ) === $_POST['delegate_subscriber_options_to'] ) {
									$user_id = $user->ID;
									break;
								}
							}

							if ( $user_id ) {
								$this->sm_options['delegate_subscriber_options_to'] = $user_id;
							}
						}

						if ( isset( $_POST['delegate_appearance_to'] ) ) {
							$user_id = false;
							foreach( $users as $user ) {
								if ( wp_hash( $user->ID ) === $_POST['delegate_appearance_to'] ) {
									$user_id = $user->ID;
									break;
								}
							}

							if ( $user_id ) {
								$this->sm_options['delegate_appearance_to'] = $user_id;
							}
						}

						if ( isset( $_POST['delegate_subscribers_to'] ) ) {
							$user_id = false;
							foreach( $users as $user ) {
								if ( wp_hash( $user->ID ) === $_POST['delegate_subscribers_to'] ) {
									$user_id = $user->ID;
									break;
								}
							}

							if ( $user_id ) {
								$this->sm_options['delegate_subscribers_to'] = $user_id;
							}
						}

						if ( isset( $_POST['delegate_queue_to'] ) ) {
							$user_id = false;
							foreach( $users as $user ) {
								if ( wp_hash( $user->ID ) === $_POST['delegate_queue_to'] ) {
									$user_id = $user->ID;
									break;
								}
							}

							if ( $user_id ) {
								$this->sm_options['delegate_queue_to'] = $user_id;
							}
						}

						if ( isset( $_POST['subscriber_items_per_page'] ) ) {
							if ( 5 <= ( int ) $_POST['subscriber_items_per_page'] && 100 >= ( int ) $_POST['subscriber_items_per_page'] && 0 === ( int ) $_POST['subscriber_items_per_page'] % 5 ) {
								$this->sm_options['subscriber_items_per_page'] = ( int ) $_POST['subscriber_items_per_page'];
							}
						}

						if ( isset( $_POST['queue_items_per_page'] ) ) {
							if ( 5 <= ( int ) $_POST['queue_items_per_page'] && 100 >= ( int ) $_POST['queue_items_per_page'] && 0 === ( int ) $_POST['queue_items_per_page'] % 5 ) {
								$this->sm_options['queue_items_per_page'] = ( int ) $_POST['queue_items_per_page'];
							}
						}

						update_option( 'subme_options', $this->sm_options );
					break;
					case 'email':
						/* Check user's permissions */
						if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_email_settings_to'] ) {
							break;
						}

						$users = $this->get_users();

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
							$user_id = false;
							foreach( $users as $user ) {
								if ( wp_hash( $user->ID ) === $_POST['email_notifications_to'] ) {
									$user_id = $user->ID;
									break;
								}
							}

							if ( $user_id ) {
								$this->sm_options['email_notifications_to'] = $user_id;
							}
						}

						if ( isset( $_POST['email_notifications_from'] ) ) {
							if ( '' == $_POST['email_notifications_from'] ) {
								$this->sm_options['email_notifications_from'] = '';
							} else {
								$user_id = false;
								foreach( $users as $user ) {
									if ( wp_hash( $user->ID ) === $_POST['email_notifications_from'] ) {
										$user_id = $user->ID;
										break;
									}
								}

								if ( $user_id ) {
									$this->sm_options['email_notifications_from'] = $user_id;
								}
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
	
						if ( isset( $_POST['manage_via_queue'] ) ) {
							if ( 'yes' === $_POST['manage_via_queue'] ) {
								$this->sm_options['manage_via_queue'] = 'yes';
							} else {
								$this->sm_options['manage_via_queue'] = 'no';
							}
						}
	
						if ( isset( $_POST['send_post_emails_from'] ) ) {
							if ( '' == $_POST['send_post_emails_from'] || 'Post Author' === $_POST['send_post_emails_from']) {
								$this->sm_options['send_post_emails_from'] = $_POST['send_post_emails_from'];
							} else {
								$user_id = false;
								foreach( $users as $user ) {
									if ( wp_hash( $user->ID ) === $_POST['send_post_emails_from'] ) {
										$user_id = $user->ID;
										break;
									}
								}

								if ( $user_id ) {
									$this->sm_options['send_post_emails_from'] = $user_id;
								}
							}
						}	

						if ( isset( $_POST['send_confirmation_emails_from'] ) ) {
							if ( '' == $_POST['send_confirmation_emails_from'] ) {
								$this->sm_options['send_confirmation_emails_from'] = '';
							} else {
								$user_id = false;
								foreach( $users as $user ) {
									if ( wp_hash( $user->ID ) === $_POST['send_confirmation_emails_from'] ) {
										$user_id = $user->ID;
										break;
									}
								}

								if ( $user_id ) {
									$this->sm_options['send_confirmation_emails_from'] = $user_id;
								}
							}
						}	

						if ( isset( $_POST['send_manage_emails_from'] ) ) {
							if ( '' == $_POST['send_manage_emails_from'] ) {
								$this->sm_options['send_manage_emails_from'] = '';
							} else {
								$user_id = false;
								foreach( $users as $user ) {
									if ( wp_hash( $user->ID ) === $_POST['send_manage_emails_from'] ) {
										$user_id = $user->ID;
										break;
									}
								}

								if ( $user_id ) {
									$this->sm_options['send_manage_emails_from'] = $user_id;
								}
							}
						}	

						if ( isset( $_POST['emails_per_burst'] ) ) {
							$this->sm_options['emails_per_burst'] = absint( $_POST['emails_per_burst'] );

							/* Make sure at least one email per burst is sent out. Otherwise the queue would fill up */
							if ( 0 == $this->sm_options['emails_per_burst'] ) {
								$this->sm_options['emails_per_burst'] = 1;
							}
						}

						if ( isset( $_POST['return_path'] ) ) {
							if ( 'Admin' === $_POST['return_path'] ) {
								$this->sm_options['return_path'] = 'Admin';
							} elseif ( 'From address' === $_POST['return_path'] ) {
								$this->sm_options['return_path'] = 'From address';
							} else {
								$this->sm_options['return_path'] = '';
							}
						}

						update_option( 'subme_options', $this->sm_options );
					break;
					case 'template':
						/* Check user's permissions */
						if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_templates_to'] ) {
							break;
						}

						if ( isset ($_POST['subscribe_subject'] ) && '' != stripslashes( $_POST['subscribe_subject'] ) ) {
							$this->sm_options['subscribe_subject'] = sanitize_text_field( stripslashes( $_POST['subscribe_subject'] ) );
						} else {
							if ( isset( $this->sm_options['subscribe_subject'] ) ) {
								unset( $this->sm_options['subscribe_subject'] );
							}
						}

						if ( isset( $_POST['subscribe_text'] ) && '' != stripslashes( $_POST['subscribe_text'] ) ) {
							$this->sm_options['subscribe_text'] = stripslashes( $_POST['subscribe_text'] );
						} else {
							if ( isset( $this->sm_options['subscribe_text'] ) ) {
								unset( $this->sm_options['subscribe_text'] );
							}
						}
				
						if ( isset( $_POST['notification_subject'] ) && '' != stripslashes( $_POST['notification_subject'] ) ) {
							$this->sm_options['notification_subject'] = sanitize_text_field( stripslashes( $_POST['notification_subject'] ) );
						} else {
							if ( isset( $this->sm_options['notification_subject'] ) ) {
								unset( $this->sm_options['notification_subject'] );
							}
						}

						if ( isset( $_POST['notification_text'] ) && '' != stripslashes( $_POST['notification_text'] ) ) {
							$this->sm_options['notification_text'] = stripslashes( $_POST['notification_text'] );
						} else {
							if ( isset( $this->sm_options['notification_text'] ) ) {
								unset( $this->sm_options['notification_text'] );
							}
						}

						if ( isset( $_POST['manage_subject'] ) && '' != stripslashes( $_POST['manage_subject'] ) ) {
							$this->sm_options['manage_subject'] = sanitize_text_field( stripslashes( $_POST['manage_subject'] ) );
						} else {
							if ( isset( $this->sm_options['manage_subject'] ) ) {
								unset( $this->sm_options['manage_subject'] );
							}
						}

						if ( isset( $_POST['manage_text'] ) && '' != stripslashes( $_POST['manage_text'] ) ) {
							$this->sm_options['manage_text'] = stripslashes( $_POST['manage_text'] );
						} else {
							if ( isset( $this->sm_options['manage_text'] ) ) {
								unset( $this->sm_options['manage_text'] );
							}
						}

						/* Reload defaults in case templates are left blank. This is used to reset the templates to the default text. */
						include( SMPATH . 'include/options.php' );

						/* Save options */
						update_option( 'subme_options', $this->sm_options );
					break;
					case 'subscriber':
						/* Check user's permissions */
						if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_subscriber_options_to'] ) {
							break;
						}

						if ( isset( $_POST['allow_subscriber_options'] ) ) {
							if ( 'yes' === $_POST['allow_subscriber_options'] ) {
								$this->sm_options['allow_subscriber_options'] = 'yes';
							} else {
								$this->sm_options['allow_subscriber_options'] = 'no';
							}
						}

						if ( isset( $_POST['auto_subscribe'] ) ) {
							if ( 'yes' === $_POST['auto_subscribe'] ) {
								$this->sm_options['auto_subscribe'] = 'yes';
							} else {
								$this->sm_options['auto_subscribe'] = 'no';
							}
						}

						if ( isset( $_POST['all_authors'] ) ) {
							$this->sm_options['allowed_authors'] = 'all';
						} elseif ( isset( $_POST['allowed_authors'] ) && is_array( $_POST['allowed_authors'] ) ) {
							$authors = $this->get_users();
							$allowed_authors = "";

							foreach( $_POST['allowed_authors'] as $key => $value ) {
								/* Check if user exists */
								$author_id = false;
								foreach( $authors as $author ) {
									if ( wp_hash( $author->ID ) === $key ) {
										$author_id = $author->ID;
										break;
									}
								}

								if ( $author_id ) {
									if ( '' == $allowed_authors ) {
										$allowed_authors = $author_id;
									} else {
										$allowed_authors .= ',' . $author_id;
									}
								}
							}

							$this->sm_options['allowed_authors'] = $allowed_authors;
						}

						if ( isset( $_POST['all_categories'] ) ) {
							$this->sm_options['allowed_categories'] = 'all';
						} elseif ( isset( $_POST['allowed_categories'] ) && is_array( $_POST['allowed_categories'] ) ) {
							$items = $this->get_categories();
							$allowed_categories = "";

							foreach( $_POST['allowed_categories'] as $key => $value ) {
								/* Check if category exists */
								$category_id = false;
								foreach( $items as $item ) {
									$category = $item['cat'];
									if ( wp_hash( $category->cat_ID ) === $key ) {
										$category_id = $category->cat_ID;
										break;
									}
								}

								if ( $category_id ) {
									if ( '' == $allowed_categories ) {
										$allowed_categories = $category_id;
									} else {
										$allowed_categories .= ',' . $category_id;
									}
								}
							}

							$this->sm_options['allowed_categories'] = $allowed_categories;
						}

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

						if ( isset( $_POST['show_form_unsubscribe'] ) ) {
							if ( 'yes' === $_POST['show_form_unsubscribe'] ) {
								$this->sm_options['show_form_unsubscribe'] = 'yes';
							} else {
								$this->sm_options['show_form_unsubscribe'] = 'no';
							}
						}

						if ( isset( $_POST['enable_form_description'] ) ) {
							if ( 'yes' === $_POST['enable_form_description'] ) {
								$this->sm_options['enable_form_description'] = 'yes';
							} else {
								$this->sm_options['enable_form_description'] = 'no';
							}
						}

						if ( isset( $_POST['form_description'] ) && '' != stripslashes( $_POST['form_description'] ) ) {
							$this->sm_options['form_description'] = stripslashes( $_POST['form_description'] );
						} else {
							if ( isset( $_POST['form_description'] ) ) {
								unset( $this->sm_options['form_description'] );
							}
						}

						if ( isset( $_POST['show_widget_unsubscribe'] ) ) {
							if ( 'yes' === $_POST['show_widget_unsubscribe'] ) {
								$this->sm_options['show_widget_unsubscribe'] = 'yes';
							} else {
								$this->sm_options['show_widget_unsubscribe'] = 'no';
							}
						}

						if ( isset( $_POST['enable_widget_description'] ) ) {
							if ( 'yes' === $_POST['enable_widget_description'] ) {
								$this->sm_options['enable_widget_description'] = 'yes';
							} else {
								$this->sm_options['enable_widget_description'] = 'no';
							}
						}

						if ( isset( $_POST['widget_description'] ) && '' != stripslashes( $_POST['widget_description'] ) ) {
							$this->sm_options['widget_description'] = stripslashes( $_POST['widget_description'] );
						} else {
							if ( isset( $_POST['widget_description'] ) ) {
								unset( $this->sm_options['widget_description'] );
							}
						}

						if ( isset( $_POST['custom_css'] ) && '' != stripslashes( $_POST['custom_css'] ) ) {
							$this->sm_options['custom_css'] = stripslashes( $_POST['custom_css'] );

							if ( ! file_put_contents( SMPATH . '/css/subme.css', $this->minify_css( $this->sm_options['custom_css'] ) ) ) {
								$this->display_admin_error( __( 'Failed to write to subme.css', 'subme' ) );
							}
						} else {
							if ( isset( $_POST['custom_css'] ) ) {
								unset( $this->sm_options['custom_css'] );
							}
						}

						/* Reload defaults in case templates are left blank. This is used to reset the templates to the default text. */
						include( SMPATH . 'include/options.php' );

						update_option( 'subme_options', $this->sm_options );
					break;
				}
			break;
		}

	}	
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
		<p><label for="<?php echo esc_attr( $this->get_field_id('title') ); ?>">Title: <input class="widefat" id="<?php echo esc_attr( $this->get_field_id('title') ); ?>" name="<?php echo esc_attr( $this->get_field_name('title') ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>
<?php
	}

	/* Creating widget front-end */
	public function widget( $args, $instance ) {
		global $my_subme;

		extract( $args );

		$title = apply_filters( 'widget_title', $instance['title'] );

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
				if ( isset( $_POST ) && isset( $_POST['source'] ) && 'widget' === $_POST['source'] ) {
					/* Check nonce */
					if ( ! isset( $_POST['subme_widget_nonce'] ) || ! wp_verify_nonce( $_POST['subme_widget_nonce'], 'subme_widget' ) ) {
						return;
					}

					/* Process request */
					if ( isset( $_POST['subscribe'] ) ) {
						$msg = $my_subme->process_form_widget_post( 'subscribe' );
					} elseif ( isset( $_POST['unsubscribe'] ) ) {
						if ( 'yes' === $my_subme->sm_options['show_widget_unsubscribe'] ) {
							$msg = $my_subme->process_form_widget_post( 'unsubscribe' );
						}
					} elseif ( isset( $_POST['manage'] ) ) {
						if ( 'yes' === $my_subme->sm_options['show_widget_unsubscribe'] ) {
							$msg = $my_subme->process_form_widget_post( 'manage' );
						}
					}
				}

				$form .= '<div class="subme_widget_container">';
					$form .= '<div class="subme_widget_form">';
						$form .= '<form method="post">';
							$form .= wp_nonce_field( 'subme_widget', 'subme_widget_nonce', true, false );
							$form .= '<input type="hidden" name="source" value="widget" />';

							/* Form description */
							if ( 'yes' === $my_subme->sm_options['enable_widget_description'] ) {
								$form .= '<div class="subme_widget_description">';
									$form .= '<p>';
										$form .= esc_html( $my_subme->sm_options['widget_description'] );
									$form .= '</p>';
								$form .= '</div>';
							}

							/* Email address */
							$form .= '<div class="subme_widget_email_wrapper">';
								$form .= '<label id="subme_widget_email_label" class="subme_widget_label" for="subme_widget_email_input">';
									$form .= __( 'Your email address:', 'subme' );
								$form .= '</label>';
								$form .= '<input type="text" id="subme_widget_email_input" class="subme_widget_input" name="email" />';
							$form .= '</div>';

							$form .= '<div class="subme_widget_msg">';
								$form .= '<p>';
									$form .= esc_html( $msg );
								$form .= '</p>';
							$form .= '</div>';
	
							/* Submit buttons */
							$form .= '<div class="subme_widget_submit_wrapper">';
								$form .= '<input type="submit" id="subme_widget_subscribe_submit" class="subme_widget_submit" name="subscribe" value="' . __( 'Subscribe', 'subme' ) . '" />';

								if ( 'yes' === $my_subme->sm_options['show_widget_unsubscribe'] ) {
									if ( 'yes' === $my_subme->sm_options['allow_subscriber_options'] ) {
										$form .= '<input type="submit" id="subme_widget_manage_submit" class="subme_widget_submit" name="manage" value="' . __( 'Manage Subscription', 'subme' ) . '" />';
									} else {
										$form .= '<input type="submit" id="subme_widget_unsubscribe_submit" class="subme_widget_submit" name="unsubscribe" value="' . __( 'Unsubscribe', 'subme' ) . '" />';
									}
								}
							$form .= '</div>';
						$form .= '</form>';
					$form .= '</div>';
				$form .= '</div>';
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
