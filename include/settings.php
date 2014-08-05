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

	<form method="post">
		<?php wp_nonce_field( 'subme_settings', 'subme_settings_nonce', true, true ); ?>
		<input type="hidden" name="form" value="settings">

		<p>
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

				<?php
					$ret = $this->check_options();
					echo __( 'Plugin configured correctly:', 'subme' ) . '<strong>' . ( 'OK' === $ret['status'] ? __( ' OK', 'subme' ) : __( ' NO, please check your settings.' ) ) . '</strong>';

					if ( 'OK' !== $ret['status'] ) {
						echo '<br />';
						echo '<br />';

						foreach( $ret['msgs'] as $msg ) {
							echo '<font color="#ff0000">' . $msg . '</font><br />';
						};
					}
				?>

				<hr />

				<?php
				$args = array( 'fields' => array( 'ID', 'display_name' ) );
				$users = get_users( $args );
				?>

				<?php echo __( 'Delegate Email Settings to:', 'subme' ); ?>
				<label>
				<select name="delegate_email_settings_to">
				<?php
				foreach ( $users as $user ) {
					echo '<option value="' . absint( $user->ID ) . '" ';
					$this->selected( $this->sm_options['delegate_email_settings_to'] == $user->ID ? true : false );
					echo '>' . esc_html( $user->display_name ) . '</option>';
				}
				?>
				</select>
				</label>
				<br />

				<?php echo __( 'Delegate Templates to:', 'subme' ); ?>
				<label>
				<select name="delegate_templates_to">
				<?php
				foreach ( $users as $user ) {
					echo '<option value="' . absint( $user->ID ) . '" ';
					$this->selected( $this->sm_options['delegate_templates_to'] == $user->ID ? true : false );
					echo '>' . esc_html( $user->display_name ) . '</option>';
				}
				?>
				</select>
				</label>
				<br />

				<?php echo __( 'Delegate Appearance to:', 'subme' ); ?>
				<label>
				<select name="delegate_appearance_to">
				<?php
				foreach ( $users as $user ) {
					echo '<option value="' . absint( $user->ID ) . '" ';
					$this->selected ( $this->sm_options['delegate_appearance_to'] == $user->ID ? true : false );
					echo '>' . esc_html( $user->display_name ) . '</option>';
				}
				?>
				</select>
				</label>
				<br />

				<?php echo __( 'Delegate Subscribers to:', 'subme' ); ?>
				<label>
				<select name="delegate_subscribers_to">
				<?php
				foreach ( $users as $user ) {
					echo '<option value="' . absint( $user->ID ) . '" ';
					$this->selected( $this->sm_options['delegate_subscribers_to'] == $user->ID ? true : false );
					echo '>' . esc_html( $user->display_name ) . '</option>';
				}
				?>
				</select>
				</label>
				<br />

				<?php echo __( 'Delegate Queue to:', 'subme' ); ?>
				<label>
				<select name="delegate_queue_to">
				<?php
				foreach ( $users as $user ) {
					echo '<option value="' . absint( $user->ID ) . '" ';
					$this->selected( $this->sm_options['delegate_queue_to'] == $user->ID ? true : false );
					echo '>' . esc_html( $user->display_name ) . '</option>';
				}
				?>
				</select>
				</label>
				<br />

<?php
			break;
			case 'email':
				if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_email_settings_to'] ) {
					break;
				}

?>
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<?php echo __( 'Send notifications for:', 'subme' ); ?>
				<label><input type="radio" name="email_notification" value="subscribers" <?php $this->checked( 'subscribers' === $this->sm_options['email_notification'] ? true : false );?> /><?php echo __( 'subscribers', 'subme' ); ?></label>
				<label><input type="radio" name="email_notification" value="unsubscribers" <?php $this->checked( 'unsubscribers' === $this->sm_options['email_notification'] ? true : false );?>/><?php echo __( 'unsubscribers', 'subme' ); ?></label>
				<label><input type="radio" name="email_notification" value="both" <?php $this->checked( 'both' === $this->sm_options['email_notification'] ? true : false );?>/><?php echo __( 'both', 'subme' ); ?></label>
				<label><input type="radio" name="email_notification" value="none" <?php $this->checked( 'none' === $this->sm_options['email_notification'] ? true : false );?>/><?php echo __( 'none', 'subme' ); ?></label>

				<br />
				<?php echo __( 'Send email notifications to:', 'subme' ); ?>
				<label>
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
				</label>

				<br />
				<?php echo __( 'Send email notifications from:', 'subme' ); ?>
				<label>
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
				</label>

				<br />
				<br />
				<?php echo __( 'Send confirmation emails from:', 'subme' ); ?>
				<label>
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
				</label>

				<br />
				<?php echo __( 'Send new post emails from:', 'subme' ); ?>
				<label>
				<select name="send_post_emails_from">
					<?php
					echo '<option value="" ';
					$this->selected( 'Default' === $this->sm_options['send_post_emails_from'] ? true : false );
					echo '>Default</option>';

					echo '<option value="Post Author" ';
					$this->selected( 'Post Author' === $this->sm_options['send_post_emails_from'] ? true : false );
					echo '>Post Author</option>';

					$args = array( 'fields' => array( 'ID', 'display_name' ) );
					$users = get_users( $args );
					foreach ( $users as $user ) {
						echo '<option value="' . absint( $user->ID ) . '" ';
						$this->selected( $this->sm_options['send_post_emails_from'] == $user->ID ? true : false );
						echo '>' . esc_html( $user->display_name ) . '</option>';
					}	
					?>
				</select>
				</label>

				<br />
				<?php echo __( 'Send emails for protected posts:', 'subme' ); ?>
				<label><input type="radio" name="protected_posts" value="yes" <?php $this->checked( 'yes' === $this->sm_options['email_protected_posts'] ? true : false );?>/>yes</label>
				<label><input type="radio" name="protected_posts" value="no" <?php $this->checked( 'no' === $this->sm_options['email_protected_posts'] ? true : false );?>/>no</label>
			
				<br />
				<?php echo __( 'Send emails for private posts:', 'subme' ); ?>
				<label><input type="radio" name="private_posts" value="yes" <?php $this->checked( 'yes' === $this->sm_options['email_private_posts'] ? true : false );?>/>yes</label>
				<label><input type="radio" name="private_posts" value="no" <?php $this->checked( 'no' === $this->sm_options['email_private_posts'] ? true : false );?>/>no</label>

				<br />
				<br />
				<?php echo __( 'Cron jobs enabled:', 'subme' ); ?>
				<label><input type="radio" name="cron_enabled" value="yes" onclick="subme_cron_enabled (1);" <?php $this->checked( 'yes' === $this->sm_options['cron_enabled'] ? true : false );?>/>yes</label>
				<label><input type="radio" name="cron_enabled" value="no" onclick="subme_cron_enabled (0);" <?php $this->checked( 'no' === $this->sm_options['cron_enabled'] ? true : false );?>/>no</label>

				<br />
				<?php echo __( 'Send confirmation emails via the queue:', 'subme' ); ?>
				<label><input type="radio" id="subme_confirmation_via_queue" name="confirmation_via_queue" value="yes" <?php $this->checked( 'yes' === $this->sm_options['confirmation_via_queue'] ? true : false );?>/>yes</label>
				<label><input type="radio" name="confirmation_via_queue" value="no" <?php $this->checked( 'no' === $this->sm_options['confirmation_via_queue'] ? true : false );?>/>no</label>

				<br />
				<?php echo __( 'How often does the cron job run (minutes)?', 'subme' ); ?>
				<label>
				<input type="text"<?php if ( 'no' === $this->sm_options['cron_enabled'] ) echo " disabled "; ?>id="subme_cron_minutes" name="cron_minutes" value="<?php echo absint( $this->sm_options['cron_minutes'] );?>" />
				</label>

				<br />
				<?php echo __( 'Maximum numbers of emails allowed to send per burst:', 'subme' ); ?>
				<label>
				<input type="text"<?php if ( 'no' === $this->sm_options['cron_enabled'] ) echo " disabled "; ?>id="subme_emails_per_burst" name="emails_per_burst" value="<?php echo absint( $this->sm_options['emails_per_burst'] );?>" />
				</label>
<?php
			break;
			case 'template':
				if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_templates_to'] ) {
					break;
				}
?>
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<div>
				<p>
					<table style="width: 100% border-collapse: separate; border-spacing: 5px; *border-collapse: expression('separate', cellSpacing = '50px');">
						<tr>
							<td style="vertical-align: top;">
								<h2><?php echo __( 'Subscribe/unsubscribe email', 'subme' ); ?></h2>
							
								<?php echo __( 'Subject line:', 'subme' ); ?>
								<input type="text" name="subscribe_subject" value="<?php echo esc_attr( stripslashes( $this->sm_options['subscribe_subject'] ) );?>" size="50" />
								<br /><br />
								<textarea rows="10" cols="60" name="subscribe_text"><?php echo esc_textarea( stripslashes( $this->sm_options['subscribe_text'] ) ); ?></textarea>
								<hr />
							</td>

							<td style="vertical-align: top;" rowspan="3">
								<h2 style="color: red;"><?php echo __( 'KEYWORD SUBSTITUTION', 'subme' ); ?></h2>
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
							</td>
						</tr>

						<tr>
							<td style="vertical-align: top;">
								<h2><?php echo __( 'Notification email', 'subme' ); ?></h2>
								<?php echo __( 'Subject line:', 'subme' ); ?>
								<input type="text" name="notification_subject" value="<?php echo esc_attr( stripslashes( $this->sm_options['notification_subject'] ) );?>" size="50" />
								<br /><br />
								<textarea rows="10" cols="60" name="notification_text"><?php echo esc_textarea( stripslashes( $this->sm_options['notification_text'] ) ); ?></textarea>
								<hr />
							</td>
						</tr>
					</table>
				</p>
				</div>
				<hline />

<?php
			break;
			case 'appearance':
				if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_appearance_to'] ) {
					break;
				}

?>
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<label>
				<?php echo __( 'Default SubMe page:', 'subme' ); ?>
				<select name="subme_page">
					<option value="Select a page"><?php echo __( 'Select a page', 'subme' ); ?></option>
					<?php
					$args = array( 'post_type' => 'page', 'post_status'  => 'publish' );
					$pages = get_pages( $args );
					foreach ( $pages as $page ) {
						echo '<option value="' . absint( $page->ID ) . '" ';
						$this->selected( ( isset( $this->sm_options['subme_page'] ) ? ( $this->sm_options['subme_page'] == $page->ID ? true : false ) : false ) );
						echo '>' . esc_html( $page->post_title ) . '</option>';
					}
					?>

				</select>
				</label>
				<br />
				<br />

				<label>
				<input type="checkbox" name="show_meta" value="1" <?php $this->checked( $this->sm_options['show_meta'] ); ?> />
				</label>
				<?php echo __( 'Show link in META', 'subme' ); ?>
				<br />

				<label>
				<input type="checkbox" name="enable_widget" value="1" <?php $this->checked( $this->sm_options['enable_widget'] ); ?> />
				</label>
				<?php echo __( 'Enable SubMe widget', 'subme' ); ?>
				<br />
				
<?php
			break;
		}
?>

		</p>
		<p class="submit">
			<input type="submit" class="button-primary" name="submit" value="Submit" />
		</p>
	</form>
</div>
