<?php
		if ( ! defined('ABSPATH') ) {
			exit();
		}

		/*
		 * Loads defaults settings when the plugin is not or is partially configured by the user 
		 */

		/* Admin tab */
                if ( ! isset( $this->sm_options['delegate_email_settings_to'] ) ) $this->sm_options['delegate_email_settings_to'] = '';
                if ( ! isset( $this->sm_options['delegate_templates_to'] ) ) $this->sm_options['delegate_templates_to'] = '';
                if ( ! isset( $this->sm_options['delegate_appearance_to'] ) ) $this->sm_options['delegate_appearance_to'] = '';
		if ( ! isset( $this->sm_options['delegate_subscribers_to'] ) ) $this->sm_options['delegate_subscribers_to'] = '';
		if ( ! isset( $this->sm_options['delegate_queue_to'] ) ) $this->sm_options['delegate_queue_to'] = '';

		/* Email settings tab */
                if ( ! isset( $this->sm_options['email_notification'] ) ) $this->sm_options['email_notification'] = 'both';
                if ( ! isset( $this->sm_options['email_notifications_to'] ) ) $this->sm_options['email_notifications_to'] = '';
                if ( ! isset( $this->sm_options['email_notifications_from'] ) ) $this->sm_options['email_notifications_from'] = '';
                if ( ! isset( $this->sm_options['email_protected_posts'] ) ) $this->sm_options['email_protected_posts'] = 'no';
                if ( ! isset( $this->sm_options['email_private_posts'] ) ) $this->sm_options['email_private_posts'] = 'no';
		if ( ! isset( $this->sm_options['confirmation_via_queue'] ) ) $this->sm_options['confirmation_via_queue'] = 'yes';
                if ( ! isset( $this->sm_options['send_confirmation_emails_from'] ) ) $this->sm_options['send_confirmation_emails_from'] = '';
                if ( ! isset( $this->sm_options['send_post_emails_from'] ) ) $this->sm_options['send_post_emails_from'] = 'Post Author';
                if ( ! isset( $this->sm_options['cron_enabled'] ) ) $this->sm_options['cron_enabled'] = 'no';
                if ( ! isset( $this->sm_options['cron_minutes'] ) ) $this->sm_options['cron_minutes'] = '0';
                if ( ! isset( $this->sm_options['emails_per_burst'] ) ) $this->sm_options['emails_per_burst'] = '0';

		/* Templates tab */
                if ( ! isset( $this->sm_options['subscribe_subject'] ) ) $this->sm_options['subscribe_subject'] = '[@@BLOGNAME] Please confirm your request';
                if ( ! isset( $this->sm_options['subscribe_text'] ) ) $this->sm_options['subscribe_text'] = '@@BLOGNAME has received a request to @@ACTION this email address. To complete your request please use the link below:

@@LINK

If you did not request this, please feel free to disregard this notice!

Best regards,

@@BLOGNAME';
                if ( ! isset( $this->sm_options['notification_subject'] ) ) $this->sm_options['notification_subject'] = '[@@BLOGNAME] @@TITLE';
                if ( ! isset( $this->sm_options['notification_text'] ) ) $this->sm_options['notification_text'] = '@@BLOGNAME has posted a new item.

"@@SNIPPET"

You may view the latest post at
@@PERMALINK

You received this email because you asked to be notified when new posts are published.

Best regards,

@@BLOGLINK';
		/* Appearance tab */
		if ( ! isset( $this->sm_options['subme_page'] ) ) $this->sm_options['subme_page'] = '';
		if ( ! isset( $this->sm_options['show_meta'] ) ) $this->sm_options['show_meta'] = 1;
		if ( ! isset( $this->sm_options['enable_widget'] ) ) $this->sm_options['enable_widget'] = 0;
?>
