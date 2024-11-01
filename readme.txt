=== WP2Act ===
Contributors: crmaddon
Tags: act,wp2act,crmaddon-contact,contact-information
Requires at least: 4.6
Tested up to: 4.9
Stable tag: 1.0.6
Requires at least: 3.0

A very fast way to send your contact information to crmaddon.

== Description ==

This plugin help you easily to generate a tag which after we click we will jump to a sending contact info page, using the web api, we will get the users' contact info from the web site.

The WP2Act will be served to the vast majority of your users:

1. Users who are logged in or not
2. Every page in the web site(of course you can set the pages where you want to show the tag)
3. Users can update the information after he/she sending the information according the email address they have filled

Different people have different needs, after they submit their contact information, we can contact them and then get their needs, so we can make customization of plug ins


= Recommended Settings =
1. Activate the plugin
2. Go to the setting menu,fill the ACT Username and Database; it's ok to leave the URl null; now we need't the ACT Password
2. Go to the show page menu, click the selector you want to show for the tag(It won't display in any pages by default),of course you can press "CTRL" then select more than one for one time.

== Installation ==

Install like any other plugin,directly from your plugins page but make sure you have custom permalinks enable or download the plugin first then copy it to the plugins category.

Detail:
1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the WP2Act Setting,file the ACT Username and Database and then submit.It's ok to leave the URL null and we haven't use the ACT password yet.
4. Use the Show Pages menu to select the pages you want to show the tag(it won't show in any pages by default), of course your can press the "CTRL" and then select more than one for one time.


== Frequently Asked Questions ==

= Why can't I see the tag after I install the plugin? =
Go to the Show Pages menu under WP2Act, select the selector and then submit then it will show.

= Why do I fail to send the contact?  =
Check if your computer is connected to the Internet, check if you fill the ACT Username and Database and make sure they are correct.

== Upgrade Notice ==
Fix for older versions of WordPress, catch fatal errors before they're cached, preload fixes.

== Changelog ==


= 1.3 =
- add the cache for the pull information
- solve the crash
- use the wp default style

= 1.2 =
- code optimization

= 1.1 =
- Change the type of the activity

= 1.0 =
- Make user the code can't be modified by the enduser
- add the log file download link
- modify the mobile phone field
- solve bug when create or update a contact
- modify the prompt message

= 0.9 =
- modify group create
- modify activity create 
- modify the field show
- modify the country list

= 0.8 =
- add title configuration page
- modify the field show 
- modify the contract address field
- modify the process of creating the contact
- solve the bug when create the activity

= 0.7 =
- modify the the show of the contact link
- modify the process of creating a contact
- add the log for the plugin
- modify the contact page
- solve the bugs of initialization of the act
- add the setting link for the plugin management page for the admin
- add the connection test
- modify the setting module

= 0.6 =
* Init code.




