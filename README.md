# s9y-to-wp
This script will help you migrate from [Serendipity](http://www.s9y.org/) to [Wordpress](http://wordpress.org/).   It is a plugin that you drop into Wordpress that will read your S9y database and migrate over your posts and comments.  

## License 
There was none when I started, so there shall be none when I'm done. -JD

Public Domain or [CC0](http://creativecommons.org/publicdomain/zero/1.0/)

## Instructions 
IF YOU HAVE MORE THAN 9000 POSTS OR COMMENTS:  Please change the "+9000" and "-9000" below (3 locations total)
to be a number larger than what you have.  It is arbitrary, but important to prevent ID collisions during update.

1. In the Wordpress DB
 * Truncate (remove) all records from: `wp_posts`, `wp_postmeta`, `wp_term_relationship`, `wp_term_taxonomy`, wp_comments, wp_commentmeta
 * Remove all records BUT default (ID 1) from `wp_terms`  (Honestly not sure about this one, but I found it is safer to leave default)
2. Copy included serendipity.php to /wordpress/wp-admin/import/
3. Login to Wordpress Admin Interface
4. Goto Tools > Import
5. Click "Serendipity"
6. Fill out the information, follow the proccess
7. Run the following SQL in the Wordpress DB
 *  ``` UPDATE `wp_comments` as a SET a.`comment_post_ID`= (SELECT b.`menu_order` FROM `wp_posts` as b WHERE a.`comment_post_ID`= b.`ID`);  ```
 *  ``` UPDATE `wp_term_relationships` SET `object_id`=`object_id`+9000;  ```
 *  ``` UPDATE `wp_term_relationships` as a SET a.`object_id`= (SELECT b.`menu_order` FROM `wp_posts` as b WHERE a.`object_id`-9000= b.`ID`);  ```
 *  ``` UPDATE `wp_posts` SET `ID`=`ID`+9000;  ```
 *  ``` UPDATE `wp_posts` SET `ID`=`menu_order`, `guid`=CONCAT("http://MYURL.COM/?p=",`menu_order`), `menu_order`=null; -- Replace MYURL.COM with your URL  ```
 *  ``` SELECT `id`+1 FROM `wp_posts` ORDER BY `id` DESC LIMIT 0,1;  ```
 *  ``` ALTER TABLE `wp_posts`  AUTO_INCREMENT = XXX;  -- Where XXX is the value returned from the select above  ```
8. Should be done.

Note: The above was tested in Serendipity 1.5.2 and Wordpress 3.0 

## Known issues 
* Comments become "flat" (IE "parents" aren't translated)
* After the import, you'll need to edit one of the categories (just open and save it) to fix the hierarchy.

## History & Change log
### GitHub
GitHub Move (2013-07-13) - [Jon Davis](http://snowulf.com)
* Migrated code from [Google Code](https://snowulf.googlecode.com/svn/trunk/wordpress/s9y-to-wp/) to new home on [GitHub](https://github.com/ShakataGaNai/s9y-to-wp) 
* http://snowulf.com/2013/07/13/s9y-to-wp-has-a-new-home-on-github/

### Version 1.5
Version 1.5 Release (2010-10-27) - [Simone Tellini](http://www.tellini.info)

* Added support for nested categories  
* http://tellini.info/2010/10/serendipity-to-wordpress/   

### Version 1.4
Version 1.4 Release (2010-06-10) - [Jon Davis](http://snowulf.com)

* Added draft/publish handling
* Added method to get post numbers to match on either side (see readme.txt file)
* Tested with S9y 1.5.2 to WP 2.9.2/3.0RC
* http://snowulf.com/2010/06/11/serendipity-to-wordpress-post-import/
* Code borrowed with permission from [Dobschat.de](http://www.dobschat.de/serendipity-s9y-importer-for-wordpress-1-3/#englishversion)

### Version 1.3
Version 1.3 Released (2008-11-08) - [Dobschat](http://www.dobschat.de/)

* split articles with ``` <!--more--> ```
* import article-tags from s9y

### Verison 1.2
Version 1.2 Released (2008-09-04)

* Fixes Category Association
* Breaks processing into smaller blogs for large imports
* Performance improvements

### Version 1.1
Version 1.1 Released (2006-02-01) - [Dobschat](http://www.dobschat.de/)

### Version 1
Version 1 released - [Dobschat](http://www.dobschat.de/)

