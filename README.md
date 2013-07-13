Through the history of this Serendipty S9y to Wordpress plugin, it has been many places.  I found version 1.3 at 
http://dobschat.de/2008/11/14/serendipity-s9y-importer-for-wordpress-1-3/#englishversion
and hijacked it for my own use.  I made a few small changes (So far) which include adding draft/published support
and the ability get the post id's "fixed".

I'm happy to present my slightly updated and improved Serendipity to Wordpress importer Version 1.4

Good luck
-Jon Davis
http://snowulf.com/

= License =
There was none when I started, so there shall be none when I'm done.
Public Domain or CC0 ( http://creativecommons.org/publicdomain/zero/1.0/ )

= Instructions =
IF YOU HAVE MORE THAN 9000 POSTS OR COMMENTS:  Please change the "+9000" and "-9000" below (3 locations total)
to be a number larger than what you have.  It is arbitraty, but important to prevent ID collisions during update.

# In the Wordpress DB
#* Truncate (remove) all records from: `wp_posts`, `wp_postmeta`, `wp_term_relationship`, `wp_term_taxonomy`, wp_comments, wp_commentmeta
#* Remove all records BUT default (ID 1) from `wp_terms`  (Honestly not sure about this one, but I found it is safer to leave default)
# Copy included serendipity.php to /wordpress/wp-admin/import/
# Login to Wordpress Admin Interface
# Goto Tools > Import
# Click "Serendipity"
# Fill out the information, follow the proccess
# Run the following SQL in the Wordpress DB
#* UPDATE `wp_comments` as a SET a.`comment_post_ID`= (SELECT b.`menu_order` FROM `wp_posts` as b WHERE a.`comment_post_ID`= b.`ID`);
#* UPDATE `wp_term_relationships` SET `object_id`=`object_id`+9000;
#* UPDATE `wp_term_relationships` as a SET a.`object_id`= (SELECT b.`menu_order` FROM `wp_posts` as b WHERE a.`object_id`-9000= b.`ID`);
#* UPDATE `wp_posts` SET `ID`=`ID`+9000;
#* UPDATE `wp_posts` SET `ID`=`menu_order`, `guid`=CONCAT("http://MYURL.COM/?p=",`menu_order`), `menu_order`=null; -- Replace MYURL.COM with your URL
#* SELECT `id`+1 FROM `wp_posts` ORDER BY `id` DESC LIMIT 0,1;
#* ALTER TABLE `wp_posts`  AUTO_INCREMENT = XXX;  -- Where XXX is the value returned from the select above
# Should be done.

Note: The above was tested in Serendipity 1.5.2 and Wordpress 2.9.2/3.0RC

= Known issues = 
* Comments become "flat" (IE "parents" aren't translated)
* After the import, you'll need to edit one of the categories (just open and save it) to fix the hierarchy.

= Version 1.5 =
Version 1.5 Release (2010-10-27) - Simone Tellini (tellini.info)
Added support for nested categories
http://tellini.info/2010/10/serendipity-to-wordpress/
