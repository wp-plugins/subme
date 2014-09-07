<?php
if ( ! defined('ABSPATH') ) {
	exit();
}

/* Check user's permissions */
$current_user = wp_get_current_user();
if ( ! is_super_admin() &&
	$current_user->ID != $this->sm_options['delegate_email_settings_to'] &&
	$current_user->ID != $this->sm_options['delegate_templates_to'] &&
	$current_user->ID != $this->sm_options['delegate_appearance_to'] ) {

	echo '<p>';
	echo __( 'You do not have the right permissions to view or change any of the settings. Please contact the Administrator if you feel that this should not be the case.', 'subme' );
	echo '</p>';

	return;
}

if ( isset( $_POST['form'] ) ) {
	/* Check nonce */
	if ( ! isset( $_POST['subme_settings_nonce'] ) || ! wp_verify_nonce( $_POST['subme_settings_nonce'], 'subme_settings' ) ) {
		exit();
	}

	$this->process_post();
}
?>


<div class="wrap">
	<h2 class="nav-tab-wrapper">
		<?php 
		if ( is_super_admin() ) {
			echo '<a class="nav-tab" href="' . esc_url( '?page=sm_settings&tab=admin' ) . '">' . __( 'Admin', 'subme' ) . '</a>';
		}

		if ( is_super_admin() || $current_user->ID == $this->sm_options['delegate_email_settings_to'] ) {
			echo '<a class="nav-tab" href="' . esc_url( '?page=sm_settings&tab=email' ) . '">' . __( 'Email Settings', 'subme' ) . '</a>';
		}

		if ( is_super_admin() || $current_user->ID == $this->sm_options['delegate_templates_to'] ) {
			echo '<a class="nav-tab" href="' . esc_url( '?page=sm_settings&tab=template' ) . '">' . __( 'Templates', 'subme' ) . '</a>';
		}

		if ( is_super_admin() || $current_user->ID == $this->sm_options['delegate_appearance_to'] ) {
			echo '<a class="nav-tab" href="' . esc_url( '?page=sm_settings&tab=appearance' ) . '">' . __( 'Appearance', 'subme' ) . '</a>';
		}
		?>
	</h2>

	<div class="metabox-holder">
	<form method="post">
		<?php wp_nonce_field( 'subme_settings', 'subme_settings_nonce', true, true ); ?>
		<input type="hidden" name="form" value="settings">

<?php
		/* Display content based on the selected tab */
		if ( isset ( $_GET['tab'] ) ) {
			$tab = $_GET['tab'];
		} else {
			/* Automatically select the default tab based on the user's permissions */
			if ( is_super_admin() ) {
				$tab = 'admin';
			} else {
				if ( $current_user->ID == $this->sm_options['delegate_appearance_to'] ) {
					$tab = 'appearance';
				}

				if ( $current_user->ID == $this->sm_options['delegate_templates_to'] ) {
					$tab = 'template';
				}

				if ( $current_user->ID == $this->sm_options['delegate_email_settings_to'] ) {
					$tab = 'email';
				}
			}
		}
		switch ( $tab ) {
			case 'admin':
				if ( ! is_super_admin() ) {
					break;
				}
?>
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<div class="postbox-container">
					<div class="postbox">
						<h3 class="hndle"><span><?php echo __( 'Plugin Status', 'subme' ); ?></span></h3>
						<div class="inside">
							<table class="form-table">
							<tbody>
								<tr>
									<th>
										<?php echo __( 'Plugin configured correctly:', 'subme' ); ?>
									</th>
									<td>
										<?php
										$ret = $this->check_options();
										echo '<strong>' . ( 'OK' === $ret['status'] ? __( 'OK', 'subme' ) : __( 'NO, please check your settings.' ) ) . '</strong>';
	
										if ( 'OK' !== $ret['status'] ) {
											echo '<br />';
											echo '<br />';

											foreach( $ret['msgs'] as $msg ) {
												echo '<font color="#ff0000">' . $msg . '</font><br />';
											}
										}
										?>
									</td>
								</tr>
							</tbody>
							</table>

						</div>
					</div>

					<div class="postbox">
						<h3 class="hndle"><span><?php echo __( 'Delegations', 'subme' ); ?></span></h3>
						<div class="inside">
							<?php
								$args = array( 'fields' => array( 'ID', 'display_name' ) );
								$users = get_users( $args );
							?>

							<table class="form-table">	
							<tbody>
								<tr>
									<th scope="row">		
										<?php echo __( 'Delegate Email Settings to:', 'subme' ); ?>
									</th>
									<td>
										<select name="delegate_email_settings_to">
										<?php
										foreach ( $users as $user ) {
											echo '<option value="' . absint( $user->ID ) . '" ';
											$this->selected( $this->sm_options['delegate_email_settings_to'] == $user->ID ? true : false );
											echo '>' . esc_html( $user->display_name ) . '</option>';
										}
										?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Delegate Templates to:', 'subme' ); ?>
									</th>
									<td>
										<select name="delegate_templates_to">
										<?php
										foreach ( $users as $user ) {
											echo '<option value="' . absint( $user->ID ) . '" ';
											$this->selected( $this->sm_options['delegate_templates_to'] == $user->ID ? true : false );
											echo '>' . esc_html( $user->display_name ) . '</option>';
										}
										?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Delegate Appearance to:', 'subme' ); ?>
									</th>
									<td>
										<select name="delegate_appearance_to">
										<?php
										foreach ( $users as $user ) {
											echo '<option value="' . absint( $user->ID ) . '" ';
											$this->selected ( $this->sm_options['delegate_appearance_to'] == $user->ID ? true : false );
											echo '>' . esc_html( $user->display_name ) . '</option>';
										}
										?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Delegate Subscribers to:', 'subme' ); ?>
									</th>
									<td>
										<select name="delegate_subscribers_to">
										<?php
										foreach ( $users as $user ) {
											echo '<option value="' . absint( $user->ID ) . '" ';
											$this->selected( $this->sm_options['delegate_subscribers_to'] == $user->ID ? true : false );
											echo '>' . esc_html( $user->display_name ) . '</option>';
										}
										?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Delegate Queue to:', 'subme' ); ?>
									</th>
									<td>
										<select name="delegate_queue_to">
										<?php
										foreach ( $users as $user ) {
											echo '<option value="' . absint( $user->ID ) . '" ';
											$this->selected( $this->sm_options['delegate_queue_to'] == $user->ID ? true : false );
											echo '>' . esc_html( $user->display_name ) . '</option>';
										}
										?>
										</select>
									</td>
								</tr>
							</tbody>
							</table>
						</div>
					</div>
				</div>
<?php
			break;
			case 'email':
				if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_email_settings_to'] ) {
					break;
				}

?>
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<div class="postbox-container">
					<div class="postbox">
						<h3 class="hndle"><span><?php echo __( 'Subcriber Notifications', 'subme' ); ?></span></h3>
						<div class="inside">
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<?php echo __( 'Send notifications for:', 'subme' ); ?>
									</th>
									<td>
										<input type="radio" name="email_notification" value="subscribers" <?php $this->checked( 'subscribers' === $this->sm_options['email_notification'] ? true : false );?> /><?php echo __( 'subscribers', 'subme' ); ?>
										<input type="radio" name="email_notification" value="unsubscribers" <?php $this->checked( 'unsubscribers' === $this->sm_options['email_notification'] ? true : false );?>/><?php echo __( 'unsubscribers', 'subme' ); ?>
										<input type="radio" name="email_notification" value="both" <?php $this->checked( 'both' === $this->sm_options['email_notification'] ? true : false );?>/><?php echo __( 'both', 'subme' ); ?>
										<input type="radio" name="email_notification" value="none" <?php $this->checked( 'none' === $this->sm_options['email_notification'] ? true : false );?>/><?php echo __( 'none', 'subme' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Send notifications to:', 'subme' ); ?>
									</th>
									<td>
										<select name="email_notifications_to">
											<?php
											$args = array( 'fields' => array( 'ID', 'display_name' ) );
											$users = get_users( $args );
											foreach ( $users as $user ) {
												echo '<option value="' . absint( $user->ID ) . '" ';
												$this->selected( $this->sm_options['email_notifications_to'] == $user->ID ? true : false );
												echo '>' . esc_html( $user->display_name ) . '</option>';
											}	
											?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Send notifications from:', 'subme' ); ?>
									</th>
									<td>
										<select name="email_notifications_from">
											<?php
											echo '<option value="" ';
											$this->selected( 'Default' == $this->sm_options['email_notifications_from'] ? true : false );
											echo '>Default</option>';
											$args = array( 'fields' => array( 'ID', 'display_name' ) );
											$users = get_users( $args );
											foreach ( $users as $user ) {
												echo '<option value="' . absint( $user->ID ) . '" ';
												$this->selected( $this->sm_options['email_notifications_from'] == $user->ID ? true : false );
												echo '>' . esc_html( $user->display_name ) . '</option>';
											}	
											?>
										</select>
									</td>
								</tr>
							</tbody>
							</table>
						</div>
					</div>

					<div class="postbox">
						<h3 class="hndle"><span><?php echo __( 'Confirmation Emails', 'subme' ); ?></span></h3>
						<div class="inside">
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<?php echo __( 'Send confirmation emails from:', 'subme' ); ?>
									</th>
									<td>
										<select name="send_confirmation_emails_from">
											<?php
											echo '<option value="" ';
											$this->selected( 'Default' === $this->sm_options['send_confirmation_emails_from'] ? true : false );
											echo '>Default</option>';

											$args = array( 'fields' => array( 'ID', 'display_name' ) );
											$users = get_users( $args );
											foreach ( $users as $user ) {
												echo '<option value="' . absint( $user->ID ) . '" ';
												$this->selected( $this->sm_options['send_confirmation_emails_from'] == $user->ID ? true : false );
												echo '>' . esc_html( $user->display_name ) . '</option>';
											}	
											?>
										</select>
									</td>
								</tr>
							</tbody>
							</table>
						</div>
					</div>

					<div class="postbox">
						<h3 class="hndle"><span><?php echo __( 'New Posts', 'subme' ); ?></span></h3>
						<div class="inside">
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<?php echo __( 'Send new post emails from:', 'subme' ); ?>
									</th>
									<td>
										<select name="send_post_emails_from">
											<?php
											echo '<option value="" ';
											$this->selected( 'Default' === $this->sm_options['send_post_emails_from'] ? true : false );
											echo '>' . __( 'Default', 'subme' ) . '</option>';

											echo '<option value="Post Author" ';
											$this->selected( 'Post Author' === $this->sm_options['send_post_emails_from'] ? true : false );
											echo '>' . __( 'Post Author', 'subme' ) . '</option>';

											$args = array( 'fields' => array( 'ID', 'display_name' ) );
											$users = get_users( $args );
											foreach ( $users as $user ) {
												echo '<option value="' . absint( $user->ID ) . '" ';
												$this->selected( $this->sm_options['send_post_emails_from'] == $user->ID ? true : false );
												echo '>' . esc_html( $user->display_name ) . '</option>';
											}	
											?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Send emails for protected posts:', 'subme' ); ?>
									</th>
									<td>
										<input type="radio" name="protected_posts" value="yes" <?php $this->checked( 'yes' === $this->sm_options['email_protected_posts'] ? true : false );?>/><?php echo __( 'yes', 'subme' ); ?>
										<input type="radio" name="protected_posts" value="no" <?php $this->checked( 'no' === $this->sm_options['email_protected_posts'] ? true : false );?>/><?php echo __( 'no', 'subme' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Send emails for private posts:', 'subme' ); ?>
									</th>
									<td>
										<input type="radio" name="private_posts" value="yes" <?php $this->checked( 'yes' === $this->sm_options['email_private_posts'] ? true : false );?>/><?php echo __( 'yes', 'subme' ); ?>
										<input type="radio" name="private_posts" value="no" <?php $this->checked( 'no' === $this->sm_options['email_private_posts'] ? true : false );?>/><?php echo __( 'no', 'subme' ); ?>
									</td>
								</tr>
							</tbody>
							</table>
						</div>
					</div>

					<div class="postbox">
						<h3 class="hndle"><span><?php echo __( 'Email Queue', 'subme' ); ?></span></h3>
						<div class="inside">
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<?php echo __( 'Cron jobs enabled:', 'subme' ); ?>
									</th>
									<td>
										<input type="radio" name="cron_enabled" value="yes" onclick="subme_cron_enabled (1);" <?php $this->checked( 'yes' === $this->sm_options['cron_enabled'] ? true : false );?>/><?php echo __( 'yes', 'subme' ); ?>
										<input type="radio" name="cron_enabled" value="no" onclick="subme_cron_enabled (0);" <?php $this->checked( 'no' === $this->sm_options['cron_enabled'] ? true : false );?>/><?php echo __( 'no', 'subme' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Send confirmation emails via the queue:', 'subme' ); ?>
									</th>
									<td>
										<input type="radio" id="subme_confirmation_via_queue_yes" name="confirmation_via_queue" value="yes" <?php $this->checked( 'yes' === $this->sm_options['confirmation_via_queue'] ? true : false );?> /><?php echo __( 'yes', 'subme' ); ?>
										<input type="radio" id="subme_confirmation_via_queue_no" name="confirmation_via_queue" value="no" <?php $this->checked( 'no' === $this->sm_options['confirmation_via_queue'] ? true : false );?> /><?php echo __( 'no', 'subme' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'How often does the cron job run (minutes)?', 'subme' ); ?>
									</th>
									<td>
										<input type="text"<?php if ( 'no' === $this->sm_options['cron_enabled'] ) echo " disabled "; ?>id="subme_cron_minutes" name="cron_minutes" class="small-text" value="<?php echo absint( $this->sm_options['cron_minutes'] );?>" />
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php echo __( 'Maximum numbers of emails allowed to send per burst:', 'subme' ); ?>
									</th>
									<td>
										<input type="text"<?php if ( 'no' === $this->sm_options['cron_enabled'] ) echo " disabled "; ?>id="subme_emails_per_burst" name="emails_per_burst" class="small-text" value="<?php echo absint( $this->sm_options['emails_per_burst'] );?>" />
									</td>
								</tr>
							</tbody>
							</table>
						</div>
					</div>

					<div class="postbox">
						<h3 class="hndle"><span><?php echo __( 'Return-Path', 'subme' ); ?></span></h3>
						<div class="inside">
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<?php echo __( 'Set Return-Path header to:', 'subme' ) . ' '; ?>
									</th>
									<td>
										<input type="radio" name="return_path" value="Admin" <?php $this->checked( 'Admin' === $this->sm_options['return_path'] ? true : false ); ?> /><?php echo __( 'Admin email', 'subme' ); ?>
										<input type="radio" name="return_path" value="From address" <?php $this->checked( 'From address' === $this->sm_options['return_path'] ? true : false ); ?> /><?php echo __( 'From address', 'subme' ); ?>
										<input type="radio" name="return_path" value="Default" <?php $this->checked( '' == $this->sm_options['return_path'] ? true : false ); ?> /><?php echo __( 'Default', 'subme' ); ?>
									</td>
								</tr>
					
							</tbody>
							</table>
						</div>
					</div>
				</div>
<?php
			break;
			case 'template':
				if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_templates_to'] ) {
					break;
				}
?>
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<div class="postbox-container">
					<table style="width: 100% border-collapse: separate; border-spacing: 0px; *border-collapse: expression('separate', cellSpacing = '50px');">
						<tr>
							<td style="vertical-align: top;">
								<div class="postbox">
									<h3 class="hndle"><span><?php echo __( 'Confirmation email', 'subme' ); ?></span></h3>
									<div class="inside">
										<?php echo __( 'Subject line:', 'subme' ); ?>
										<input type="text" name="subscribe_subject" value="<?php echo esc_attr( stripslashes( $this->sm_options['subscribe_subject'] ) );?>" size="50" />
										<br /><br />
										<textarea rows="10" cols="60" name="subscribe_text"><?php echo esc_textarea( stripslashes( $this->sm_options['subscribe_text'] ) ); ?></textarea>
									</div>
								</div>
							</td>

							<td style="vertical-align: top;" rowspan="3">
								<div class="postbox">
									<h3 style="color: red;" class="hndle"><span><?php echo __( 'KEYWORD SUBSTITUTION', 'subme' ); ?></span></h3>
									<div class="inside">
										<dl>
											<dt><b>@@ACTION</b></dt><dd><?php echo __( 'Action performed. This can be either <i>subscribe</i> or <i>unsubscribe</i>', 'subme' ); ?></dd><br />
											<dt><b>@@AUTHORNAME</b></dt><dd><?php echo __( 'The post author\'s name', 'subme' ); ?></dd><br />
											<dt><b>@@BLOGLINK</b></dt><dd><?php echo esc_html( get_option( 'home' ) ); ?></dd><br />
											<dt><b>@@BLOGNAME</b></dt><dd><?php echo esc_html( get_option( 'blogname' ) ); ?></dd><br />
											<dt><b>@@LINK</b></dt><dd><?php echo __( 'The (un)subscribe link for confirmation', 'subme' ); ?></dd><br />
											<dt><b>@@PERMALINK</b></dt><dd><?php echo __( 'The post\'s link', 'subme' ); ?></dd><br />
											<dt><b>@@SNIPPET</b></dt><dd><?php echo __( 'Snippet of the blog post', 'subme' ); ?></dd></br />
											<dt><b>@@TITLE</b></dt><dd><?php echo __( 'The post\'s title', 'subme' ); ?></dd><br />
										</dl>
									</div>
								</div>
							</td>
						</tr>

						<tr>
							<td style="vertical-align: top;">
								<div class="postbox">
									<h3 class="hndle"><span><?php echo __( 'Notification email', 'subme' ); ?></span></h3>
									<div class="inside">
										<?php echo __( 'Subject line:', 'subme' ); ?>
										<input type="text" name="notification_subject" value="<?php echo esc_attr( stripslashes( $this->sm_options['notification_subject'] ) );?>" size="50" />
										<br /><br />
										<textarea rows="10" cols="60" name="notification_text"><?php echo esc_textarea( stripslashes( $this->sm_options['notification_text'] ) ); ?></textarea>
									</div>
								</div>
							</td>
						</tr>
					</table>
				</div>

<?php
			break;
			case 'appearance':
				if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_appearance_to'] ) {
					break;
				}

?>
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<div class="postbox-container">
					<div class="postbox">
						<h3 class="hndle"><span><?php echo __( 'SubMe Landing Page', 'subme' ); ?></span></h3>
						<div class="inside">
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<?php echo __( 'Default SubMe page:', 'subme' ); ?>
									</th>
									<td>
										<select name="subme_page">
											<option value="Select a page"><?php echo __( 'Select a page', 'subme' ); ?></option>
											<?php
											$args = array( 'post_type' => 'page', 'post_status'  => 'publish' );
											$pages = get_pages( $args );
											foreach ( $pages as $page ) {
												echo '<option value="' . absint( $page->ID ) . '" ';
												$this->selected( ( isset( $this->sm_options['subme_page'] ) ? ( $this->	sm_options['subme_page'] == $page->ID ? true : false ) : false ) );
												echo '>' . esc_html( $page->post_title ) . '</option>';
											}
											?>	
										</select>
									</td>
								</tr>
							</tbody>
							</table>
						</div>
					</div>

					<div class="postbox">
						<h3 class="hndle"><span><?php echo __( 'Appearance', 'subme' ); ?></span></h3>
						<div class="inside">
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<?php echo __( 'Visualization:', 'subme' ); ?>
									</th>
									<td>
										<input type="checkbox" name="show_meta" value="1" <?php $this->checked( $this->sm_options['show_meta'] ); ?> />
										<?php echo __( 'Show link in META', 'subme' ); ?>
										<div class="clear"></div>

										<input type="checkbox" name="enable_widget" value="1" <?php $this->checked( $this->sm_options['enable_widget'] ); ?> />
										<?php echo __( 'Enable SubMe widget', 'subme' ); ?>
									</td>
								</tr>
							</tbody>
							</table>
						</div>
					</div>
				</div>				
<?php
			break;
		}
?>

		<div class="clear"></div>
		<p class="submit">
			<input type="submit" class="button-primary" name="submit" value="<?php echo __( 'Submit', 'subme' ); ?>" />
		</p>
	</form>
	</div> <?php /* metabox-holder */ ?>
</div>
