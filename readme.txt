=== WP-Cron Status Checker ===
Contributors: webheadllc
Donate Link: https://webheadcoder.com/donate-wp-cron-status-checker
Tags: cron, scheduled posts, wp-cron, woocommerce, logging, plugin updates, subscription, recurring, daily, weekly, monthly, billing, status, check, notify
Requires at least: 4.0
Requires PHP: 5.6
Tested up to: 6.0
Stable tag: 1.2.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

If WP-Cron runs important things for you, you better make sure WP-Cron always runs!

== Description ==

**What happens if WP-Cron stops working?**

WordPress, Themes, and Plugins would never know if a new version is out.  You could login to your website everyday for months, and never see any notices about updates. If you're not careful you'll soon have an out of date WordPress installation susceptible to hackers.  Scheduled posts would never get published, auto drafts never deleted... you get the picture.  Your website would crumble up and die.

Use Woocommerce?  Subscriptions?  Customers would never get billed again!  Sessions would never be deleted.  Scheduled sales would never appear.  Your website would become bloated while giving away subscriptions for one time payments.

**WP-Cron is important.  So make sure it keeps working.**

Think of this plugin as insurance, but free.  WordPress, plugins, themes, and servers are all moving parts that can be updated at anytime.  You can't guarantee any of these changes won't break your website in some way.  While it's not likely WP-Cron will stop working, if it does, you better know about it.

Every 24 hours this plugin automatically checks to see if WP-Cron is still able to run.  Obviously, it does not depend on WP-Cron.  Instead it sets its own transients that expire over 24 hours.  Whenever the transient expires it checks to make sure WordPress can run WP-Cron.  If an unexpected error occurs the you'll get an email.

In addition to checking if WP-Cron CAN run, this plugin now logs all hooks running with WP-Cron and if they fail or complete.  You'll be able to see what ran, when it ran, how long it took, and if it completed.

For your convenience The WP-Cron Status Checker is displayed on your WordPress admin dashboard.  The status page is accessible through the Tools -> WP Cron Status side menu.

**Know when WP-Cron doesn't complete**

When a WP-Cron hook fails you'll get notified soon after.  A hook "fails" when it takes longer than 5 minutes to complete.  You'll get an email within 24 hours (or based on the plugin's settings) to notify you of any failures.

Sometimes a plugin runs some code that abruptly exits the process and there is no way of knowing when it stopped.  WP-Cron Status Checker does it's best to detect this type of code.  If it's caught, the elapsed time is not recorded and you'll see "N/A" with an "Incomplete" status.  Other times when it's caught you'll see an "Exit" status which is considered complete.  [Please see the plugin page for more on completion statuses.](https://webheadcoder.com/wp-cron-status-checker/)

**PRO Version**
The PRO version removes the 3 log limit and lets you choose to keep logs longer and email more frequently.  Please see more on the plugin page:  [https://webheadcoder.com/wp-cron-status-checker/](https://webheadcoder.com/wp-cron-status-checker/)


== Frequently Asked Questions ==

= Why do I sometimes get emails saying WP-Cron Failed to Complete and other times it says WP-Cron Cannot Run. =
This plugin detects two types of errors with WP-Cron.  The error that says WP-Cron Cannot Run is usually related to an issue with WordPress as a whole, your server, or some permission issues.  The error that says it Failed to Complete is usualy related to specific PHP code that errored out.  

= I got an error saying "Unexpected HTTP response ..." =
This is an error you need to sort out with your web host or possibly other theme/plugin authors.  I got this error on my sites before (which is why I created this plugin) and I contacted my web host to resolve the issue.  

**403 error**  
Once I had a 403 error and the issue was resolved by the web host.  They fixed permission issues on admin-ajax.php.


**Problem with SSLv3**
One user had an error returned from this plugin that looked like this:
stream_socket_client(): SSL operation failed with code 1. OpenSSL Error messages: error:14094410:SSL routines:ssl3_read_bytes:sslv3 alert handshake failure stream_socket_client(): Failed to enable crypto stream_socket_client(): unable to connect to ssl://www.mywebsiteurl.com:443 (Unknown error)

That person reinstalled cURL and restarted PHP to resolve the issue.

== Screenshots ==

1. The WP-Cron Status Checker on the WordPress admin dashboard showing WP-Cron as working.
2. The WP-Cron Status Checker on the WordPress admin dashboard showing WP-Cron has an error.
3. The WP-Cron Status Checker on the WordPress admin dashboard showing WP-Cron is disabled, but still shows when WP-Cron was last run.
4. The WP-Cron Status Checker logs of WP-Cron.
5. The WP-Cron Status Checker showing the logs by hook name.
6. The WP-Cron Status Checker showing a failed job.

== Changelog ==

= 1.2.5 =
minor backend optimization.  
updated freemius SDK. 

= 1.2.4 =
updated freemius SDK.  

= 1.2.3 =
updated freemius SDK.  
added ability to activate license key on the network level for multisite.  

= 1.2.2 =
updated freemius SDK.  

= 1.2.1 =
updated freemius SDK
*PRO* fixed issue where activating PRO plugin did not deactivate free plugin.  

= 1.2 =
added more result details.  updated database tables.    
added option to consider incompleted runs to not be errors.  
added option to delete tables when the plugin is deleted.  
fixed error with WP v4.9.  
fixed other minor issues.   
*PRO* added option to specify hooks with incompleted runs to not be errors.
*PRO* added ability to see error messages if any.

= 1.1 =
updated error logs to stick around longer so it can be seen.  
fixed negative elapsed times.  
fixed errors for crons that run WP_Background_Process.  Time on these cannot be tracked since it just exits sometimes. 
added tracking when a event uses wp_die().   
added Uncompleted views.  
updated Freemius.  

= 1.0.1 =
Fixed missing database tables after updating.  

= 1.0 =
added status page to show past WP-Cron runs.  
added logging of elapsed time for each WP-Cron run and hooks.  
added settings page.  

= 0.3 =
added feature show the last time WP-Cron ran.

= 0.2 =
changed getting current time to be more reliable when timezone is not set.  

= 0.1 =
Initial release.
