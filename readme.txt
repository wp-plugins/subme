=== SubMe ===
Tags: cron, email, e-mail, mailing list, mailinglist, mail list, maillist, notify, notification, plain text, plaintext, post, posts, subscribe, subscribers, subscription
Requires at least: 3.9
Tested up to: 4.2.2
Stable tag: 2.0.2
License: GPL3

SubMe notifies subscribers by email when a new post has been published.

== Description ==

SubMe provides a simple subscription management and email notification system for WordPress blogs that sends plain text email notifications to a list of subscribers when you publish a new post. SubMe allows you to send out email bursts by using the Wordpress cron functionality. Its purpose is to provide a simple notification system that works and is secure. Its purpose is not to be the most feature rich plugin.

= Features =

* Email verification.
* Subscription widget.
* Customize email templates.
* Custom CSS
* Subscribers management.
* Users can manage their subscription.
* Delegate admin settings to different users.
* Admin Notification of new subscriber.
* Rate limit subscriptions from the same IP address.
* Cron functionality for sending out emails in bursts.
* Export subscribers in CSV format.
* Import subscribers from CSV.

= Language Support =

* English
* Dutch (nl_NL)

== Installation ==

1. Log in to your WordPress blog and visit Plugins->Add New.
2. Search for SubMe, click "Install Now" and then Activate the Plugin.
3. Create a [WordPress Page](http://codex.wordpress.org/Pages) to display the subscription form. Manually insert the SubMe shortcode: [SubMe]. Ensure the shortcode is on a line by itself. This shortcode will automatically be replaced by a subscription form.
4. Visit the "SubMe -> Settings -> Appearance" menu and select the page you have created in step 3 as the Default SubMe page.
5. Configure other options to your wishes.
6. Visit the "SubMe -> Subscribers" menu.
7. Manually subscribe people as you see fit.
8. On the SubMe->Settings Admin page, check if the plugin is configured properly (Plugin configured correctly: OK).

== Screenshots ==

1. The SubMe -> Settings Admin page.
2. The SubMe -> Settings Email page.
3. The SubMe -> Settings Templates page.
4. The SubMe -> Settings Subscriber Options page.
5. The SubMe -> Settings Appearance page.
6. The Subscribers page to manage all subscribers.
7. The Queue page to manage the emails that are scheduled to be sent out.

== Frequently Asked Questions ==

= Why doesn't the plugin work? =
Please check if the 'Plugin configured correctly: OK' message appears on the SubMe -> Settings Admin page. When it says it is OK, then the plugin is configured correctly and it should work. Otherwise, a message should appear saying what is wrong or what is missing. Also have a look at the [Installation](https://wordpress.org/plugins/subme/installation/) instructions.

= How to solve the 'Failed to get the site admin ID.' message? =
The admin ID is found by comparing the site email address (Settings -> General -> Email Address) with that of the users. If none of the users have this email address configured, then the user's ID cannot be retrieved. Therefore, make sure that the admin user has the same email address as is configured in Settings -> General -> Email Address.

= What file format should the CSV file have for importing subscribers? =
The first line should contain the headers Active and Email. Each record should be on a separate line. The best way to get started is to create an export of the current subscribers and to edit the file as needed.

== Changelog ==

See complete changelog [here.](http://plugins.svn.wordpress.org/subme/trunk/changelog.txt)
