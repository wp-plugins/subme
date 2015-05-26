<?php
		if ( ! defined('ABSPATH') ) {
			exit();
		}

		/*
		 * Loads defaults settings when the plugin is not or partially configured by the user 
		 */

		/* Admin tab */
                if ( ! isset( $this->sm_options['delegate_email_settings_to'] ) ) $this->sm_options['delegate_email_settings_to'] = '';
                if ( ! isset( $this->sm_options['delegate_templates_to'] ) ) $this->sm_options['delegate_templates_to'] = '';
                if ( ! isset( $this->sm_options['delegate_subscriber_options_to'] ) ) $this->sm_options['delegate_subscriber_options_to'] = '';
                if ( ! isset( $this->sm_options['delegate_appearance_to'] ) ) $this->sm_options['delegate_appearance_to'] = '';
		if ( ! isset( $this->sm_options['delegate_subscribers_to'] ) ) $this->sm_options['delegate_subscribers_to'] = '';
		if ( ! isset( $this->sm_options['delegate_queue_to'] ) ) $this->sm_options['delegate_queue_to'] = '';
		if ( ! isset( $this->sm_options['subscriber_items_per_page'] ) ) $this->sm_options['subscriber_items_per_page'] = '5';
		if ( ! isset( $this->sm_options['queue_items_per_page'] ) ) $this->sm_options['queue_items_per_page'] = '5';

		/* Email settings tab */
                if ( ! isset( $this->sm_options['email_notification'] ) ) $this->sm_options['email_notification'] = 'both';
                if ( ! isset( $this->sm_options['email_notifications_to'] ) ) $this->sm_options['email_notifications_to'] = '';
                if ( ! isset( $this->sm_options['email_notifications_from'] ) ) $this->sm_options['email_notifications_from'] = '';
                if ( ! isset( $this->sm_options['email_protected_posts'] ) ) $this->sm_options['email_protected_posts'] = 'no';
                if ( ! isset( $this->sm_options['email_private_posts'] ) ) $this->sm_options['email_private_posts'] = 'no';
		if ( ! isset( $this->sm_options['confirmation_via_queue'] ) ) $this->sm_options['confirmation_via_queue'] = 'yes';
                if ( ! isset( $this->sm_options['send_confirmation_emails_from'] ) ) $this->sm_options['send_confirmation_emails_from'] = '';
		if ( ! isset( $this->sm_options['manage_via_queue'] ) ) $this->sm_options['manage_via_queue'] = 'no';
		if ( ! isset( $this->sm_options['send_manage_emails_from'] ) ) $this->sm_options['send_manage_emails_from'] = '';
                if ( ! isset( $this->sm_options['send_post_emails_from'] ) ) $this->sm_options['send_post_emails_from'] = 'Post Author';
                if ( ! isset( $this->sm_options['cron_enabled'] ) ) $this->sm_options['cron_enabled'] = 'no';
                if ( ! isset( $this->sm_options['cron_minutes'] ) ) $this->sm_options['cron_minutes'] = '0';
                if ( ! isset( $this->sm_options['emails_per_burst'] ) ) $this->sm_options['emails_per_burst'] = '0';
		if ( ! isset( $this->sm_options['return_path'] ) ) $this->sm_options['return_path'] = '';

		/* Templates tab */
                if ( ! isset( $this->sm_options['subscribe_subject'] ) ) $this->sm_options['subscribe_subject'] = __( '[@@BLOGNAME] Please confirm your request', 'subme' );
                if ( ! isset( $this->sm_options['subscribe_text'] ) ) $this->sm_options['subscribe_text'] = __( '@@BLOGNAME has received a request to @@ACTION this email address. To complete your request please use the link below:

@@LINK

If you did not request this, please feel free to disregard this notice!

Best regards,

@@BLOGNAME', 'subme' );
                if ( ! isset( $this->sm_options['notification_subject'] ) ) $this->sm_options['notification_subject'] = '[@@BLOGNAME] @@TITLE';
                if ( ! isset( $this->sm_options['notification_text'] ) ) $this->sm_options['notification_text'] = __( '@@BLOGNAME has posted a new item.

"@@SNIPPET"

You may view the latest post at
@@PERMALINK

You received this email because you asked to be notified when new posts are published.

Best regards,

@@BLOGLINK', 'subme' );
		if ( ! isset( $this->sm_options['manage_subject'] ) ) $this->sm_options['manage_subject'] = __( '[@@BLOGNAME] Manage your subscription', 'subme' );
		if ( ! isset( $this->sm_options['manage_text'] ) ) $this->sm_options['manage_text'] = __( '@@BLOGNAME has received a request to send you a link in order to manage your subscription, please use the link below:

@@MANAGELINK

If you did not request this, please feel free to disregard this notice!

Best regards,

@@BLOGNAME', 'subme' );

		/* Subscriber Options tab */
		if ( ! isset( $this->sm_options['allow_subscriber_options'] ) ) $this->sm_options['allow_subscriber_options'] = 'no';
		if ( ! isset( $this->sm_options['auto_subscribe'] ) ) $this->sm_options['auto_subscribe'] = 'no';
		if ( ! isset( $this->sm_options['allowed_authors'] ) ) $this->sm_options['allowed_authors'] = 'all';
		if ( ! isset( $this->sm_options['allowed_categories'] ) ) $this->sm_options['allowed_categories'] = 'all';

		/* Appearance tab */
		if ( ! isset( $this->sm_options['subme_page'] ) ) $this->sm_options['subme_page'] = '';
		if ( ! isset( $this->sm_options['show_meta'] ) ) $this->sm_options['show_meta'] = 1;
		if ( ! isset( $this->sm_options['enable_widget'] ) ) $this->sm_options['enable_widget'] = 0;
		if ( ! isset( $this->sm_options['show_form_unsubscribe'] ) ) $this->sm_options['show_form_unsubscribe'] = 'yes';
		if ( ! isset( $this->sm_options['enable_form_description'] ) ) $this->sm_options['enable_form_description'] = 'no';
		if ( ! isset( $this->sm_options['form_description'] ) ) $this->sm_options['form_description'] = __( 'A short description shown at the top of the SubMe Form.', 'subme' );
		if ( ! isset( $this->sm_options['show_widget_unsubscribe'] ) ) $this->sm_options['show_widget_unsubscribe'] = 'no';
		if ( ! isset( $this->sm_options['enable_widget_description'] ) ) $this->sm_options['enable_widget_description'] = 'no';
		if ( ! isset( $this->sm_options['widget_description'] ) ) $this->sm_options['widget_description'] = __( 'A short description shown at the top of the SubMe Widget.', 'subme' );
		if ( ! isset( $this->sm_options['custom_css'] ) ) $this->sm_options['custom_css'] = '/* SubMe backend */

.subme_checkbox_container {
	border: 1px;
	width: 100%;
	min-width: 300px;
	height: 120px;
	overflow-y: scroll;
	margin-bottom: 1em;
}

.subme_checkbox_ul {
	list-style-type: none;
	list-style:none;
	margin: 0px;
}

.subme_checkbox_li {
	list-style-type: none;
	list-style:none;
	margin: 0px;
}

/*SubMe Form */

.subme_form_input {
	width: 100%;
}

.subme_form_label {
	display: block;
	width: 100%;
}

.subme_form_submit_wrapper {
	display: block;
	margin-top: 1em;
}

#subme_form_unsubscribe_submit {
	margin-left: 1em;
}

#subme_form_manage_submit {
	margin-left: 1em;
}

.subme_form_msg {
	font-style: italic;
}

/*** SubMe manage subscription ***/

.subme_manage_msg_wrapper {
	display: table;
	width: 100%;
	height: 4em;
	text-align: left;
	margin-bottom: 1em;
	font-weight: bold;
	font-size: large;
}

p.subme_manage_msg {
	display: table-cell;
	vertical-align: middle;
}

.subme_manage_authors_all {
	padding-bottom: 1em;
}

.subme_manage_categories_all {
	padding-bottom: 1em;
}

.subme_manage_submit_wrapper {
	display: block;
	width: 100%;
	margin-top: 1em;
}

.subme_manage_submit {
	display: inline-block;
}

.subme_manage_unsubscribe {
	display: inline-block;
	float: right;
}

.subme_manage_options {
	display: block;
	width: 100%;
	padding: 0px;
}
.subme_manage_authors {
	display: block;
}

/*** SubMe Widget ***/

.subme_widget_input {
	width: 100%;
}

.subme_widget_label {
	display: block;
	width: 100%;
}

.subme_widget_submit_wrapper {
	display: block;
	width: 100%;
	margin-top: 1em;
	text-align:left;
}

#subme_widget_unsubscribe_submit {
	margin-left: 1em;
	float: right;
}

#subme_widget_manage_submit {
	margin-left: 1em;
	float: right;
}

.subme_widget_msg {
	font-style: italic;
}';
?>
