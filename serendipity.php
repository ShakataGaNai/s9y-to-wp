<?php
/*
Plugin Name: Serendipity to Wordpress importer
Description: Import content from a serendipity (s9y) powered weblog into WordPress
License: Public Domain or CC0 ( http://creativecommons.org/publicdomain/zero/1.0/ )
Version: 1.7
Changelog: Please see README.md
*/

set_time_limit(0);
ini_set('display_errors', true);

/* borrowed from movable type importer plugin */
if ( !defined('WP_LOAD_IMPORTERS') )
	return;


// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}
/* End borrowed */

/**
	Add These Functions to make our lives easier
**/


if(!function_exists('get_catbynicename')) {
    function get_catbynicename($category_nicename) {
        global $wpdb;
	$cat_id -= 0; 	// force numeric
	$name = $wpdb->get_var('SELECT cat_ID FROM '.$wpdb->categories.' WHERE category_nicename="'.$category_nicename.'"');	
	return $name;
    }
}

if(!function_exists('get_comment_count'))
{
	function get_comment_count($post_ID)
	{
		global $wpdb;
		return $wpdb->get_var('SELECT count(*) FROM '.$wpdb->comments.' WHERE comment_post_ID = '.$post_ID);
	}
}

if(!function_exists('link_exists'))
{
	function link_exists($linkname)
	{
		global $wpdb;
		return $wpdb->get_var('SELECT link_id FROM '.$wpdb->links.' WHERE link_name = "'.$wpdb->escape($linkname).'"');
	}
}

if(!function_exists('find_comment_parent'))
{
	function find_comment_parent($haystack, $needle)
	{
		ini_set('display_errors', true);
		foreach($haystack as $h)
		{
			if($h[0] == $needle)
				return $h[1];
		}
	}
}

if(!function_exists('get_cat_by_name'))
{
	function get_cat_by_name($cat_name)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT term_id from ".$wpdb->terms." WHERE name = '$cat_name'");
	}
}

// dobschat - 2008/11/08
function set_tags_from_s9y($item, $key, $post_id) {
	if(!empty($item)) { wp_set_post_tags($post_id, $item, TRUE); }
}
// --

/**
	The Main Importer Class
**/
class Serendipity_Import extends WP_Importer  {

        function connect_s9ydb() {
		 $s9ydb = new wpdb(get_option('s9yuser'), 
	         	           get_option('s9ypass'), 
                                   get_option('s9yname'), 
                                   get_option('s9yhost'));
                 $s9ydb->set_charset($s9ydb->dbh, get_option('s9ycharset'));
 		 set_magic_quotes_runtime(0);
		 return $s9ydb;
        }

	function header() 
	{
		echo '<div class="wrap">';
		echo '<h2>'.__('Import Serendipity').'</h2>';
		echo '<p>'.__('Steps may take a few minutes depending on the size of your database. Please be patient.').'</p>';
	}

	function footer() 
	{
		echo '</div>';
	}
	
	function greet() 
	{
		echo '<p>'.__('Howdy! This importer allows you to extract posts from any Serendipity 9 into your blog. This has not been tested on previous versions of Serendipity.  Mileage may vary.').'</p>';
		echo '<p>'.__('Your Serendipity Configuration settings are as follows:').'</p>';
		echo '<form action="admin.php?import=serendipity&amp;step=1" method="post">';
		$this->db_form();
		echo '<input type="submit" name="submit" value="'.__('Import Categories').'" />';
		echo '</form>';
	}

	function get_s9y_cats()
	{
		global $wpdb;
		// General Housekeeping
		$s9ydb = $this->connect_s9ydb(); 
		$prefix = get_option('spre');
		
		// Get Categories
		return $s9ydb->get_results('SELECT A.*, B.category_name AS parentname 
									FROM '.$prefix.'category A
									LEFT JOIN '.$prefix.'category B ON A.parentid = B.categoryid
									ORDER BY A.parentid, A.categoryid',
									 ARRAY_A);
	}
	
	function get_s9y_users()
	{
		global $wpdb;
		// General Housekeeping
		$s9ydb = $this->connect_s9ydb(); 
		
		// Get Users
		
		return $s9ydb->get_results('SELECT
							username,
							realname,
							email,
							userlevel
					    FROM '.$prefix.'authors', ARRAY_A);
	}
	
	function get_s9y_posts($start=0)
	{
		// General Housekeeping
		$s9ydb = $this->connect_s9ydb(); 
		$prefix = get_option('spre');
		
		// Get Posts
		// dobschat - 2008/11/08 - added "author"
		$posts = $s9ydb->get_results('SELECT 
							id,
							timestamp,
							authorid,
							author,
							last_modified,
							title,
							body,
							extended, 
							isdraft
 			   		      FROM '.$prefix.'entries LIMIT '.$start.',100', ARRAY_A);
		
		return $posts;
	}

        // Christian Harms - 2012/08/01 -- reading geotags from serendipity_event_geotag
        function get_s9y_geotags()
	{
		global $wpdb;
		// General Housekeeping
		$s9ydb = $this->connect_s9ydb(); 
		$prefix = get_option('spre');

		// Get only the geo_tags stored in the entryproperties table
		
		return $s9ydb->get_results('SELECT entryid, property, value 
                                            FROM '.$prefix.'entryproperties', ARRAY_A);
	}
	
	function get_s9y_comments()
	{
		global $wpdb;
		// General Housekeeping
		$s9ydb = $this->connect_s9ydb(); 
		$prefix = get_option('spre');
		
		// Get Comments
		return $s9ydb->get_results('SELECT * FROM '.$prefix.'comments', ARRAY_A);
	}
	
	function get_s9y_cat_assoc($post_id)
	{
		global $wpdb;
		// General Housekeeping
		$s9ydb = $this->connect_s9ydb(); 
		$prefix = get_option('spre');
		
		return $s9ydb->get_results("select * from ".$prefix."category, ".$prefix."entrycat, ".$prefix."entries where ".$prefix."entries.id = ".$prefix."entrycat.entryid and ".$prefix."category.categoryid=".$prefix."entrycat.categoryid and ".$prefix."entries.id =$post_id;");
	}
	
	// dobschat - 2008/11/08 - added function to get the tags from s9y
	function get_s9y_tag_assoc($post_id)
	{
		global $wpdb;
		// General Housekeeping
		$s9ydb = $this->connect_s9ydb(); 
		$prefix = get_option('spre');
		
		return $s9ydb->get_results("select ".$prefix."entrytags.tag AS tag from ".$prefix."entrytags, ".$prefix."entries where ".$prefix."entries.id = ".$prefix."entrytags.entryid and ".$prefix."entries.id =$post_id;", ARRAY_N);
	}
	// -----
	
	function cat2wp($categories='') 
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$s9ycat2wpcat = array();
		// Do the Magic
		if(is_array($categories))
		{
			echo '<p>'.__('Importing Categories...').'<br /><br /></p>';
			
			foreach ($categories as $category) 
			{
				$count++;
				extract($category);
				
				
				// Make Nice Variables
				$title = $wpdb->escape($category_name);
				$slug = $categoryid . '-' . sanitize_title($title);
				$name = $wpdb->escape($title);
				$parent = $s9ycat2wpcat[ $parentid ];
				
				$args = array('category_nicename' => $slug, 'cat_name' => $name );
				
				if( !empty( $parentid ))
					$args[ 'category_parent' ] = $parent;

				$ret_id = wp_insert_category($args);
				$s9ycat2wpcat[$categoryid] = $ret_id;
			}
			
			// Store category translation for future use
			add_option('s9ycat2wpcat', $s9ycat2wpcat);
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> categories imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Categories to Import!');
		return false;
	}

        // Christian Harms - 2012/08/01 -- convert into post_meta for the WP Geo plugin
	function geotags2wp($geotags='')
	{
		global $wpdb;	
		$s9ygeotags2wp = array();
		$count = 0;
		if (is_array($geotags))
		{
			echo '<p>'.__('Importing '.count($geotags).' Geo-Tags...').'<br /><br /></p>';
			
			foreach ($geotags as $geotag) 
			{
				extract($geotag);

				//http://codex.wordpress.org/Function_Reference/update_post_meta
				//if not exists, add_post_meta will be called
				
				if ($property == 'geo_long' || $property == 'geo_lat')
				{
					if ($property == 'geo_long') $meta_key = '_wp_geo_longitude';
					if ($property == 'geo_lat') $meta_key = '_wp_geo_latitude';

					$count++;
					update_post_meta($entryid, $meta_key, $value);
					array_push($s9ygeotags2wp, array('post_id'=>$entryid,
                                                                 'meta_key'=>$meta_key,
                                                                 'meta_value'=>$value));
				}
			}
			// Store geo_tags translation for future use
			add_option('s9ygeotags2wp', $s9ygeotags2wp);
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> geotags imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Geo-Tags to Import!');
		return false;
	}

	
	function users2wp($users='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$s9yid2wpid = array();
		
		// Midnight Mojo
		if(is_array($users))
		{
			echo '<p>'.__('Importing Users...').'<br /><br /></p>';
			foreach($users as $user)
			{
				$count++;
				extract($user);
				
				// Make Nice Variables
				$user_login = $wpdb->escape($username);
				$user_name = $wpdb->escape($realname);
				
				if($uinfo = get_user_by('login', $user_login))
				{
					
					wp_insert_user(array(
								'ID'			=> $uinfo->ID,
								'user_login'	=> $user_login,
								'user_nicename'	=> $user_name,
								'user_email'	=> $email,
								'user_url'		=> 'http://',
								'display_name'	=> $user_login)
								);
					$ret_id = $uinfo->ID;
				}
				else 
				{
					$ret_id = wp_insert_user(array(
								'user_login'	=> $user_login,
								'user_pass'     => 'password123',
								'user_nicename'	=> $user_name,
								'user_email'	=> $email,
								'user_url'	=> 'http://',
								'display_name'	=> $user_name)
								);
				}
				$s9yid2wpid[$user_id] = $ret_id;
				
				// Set Serendipity-to-WordPress permissions translation
				$transperms = array('255' => '10', '1' => '5', '0' => '2');
				
				// Update Usermeta Data
				$user = new WP_User($ret_id);
				if('10' == $transperms[$userlevel]) { $user->set_role('administrator'); }
				if('5'  == $transperms[$userlevel]) { $user->set_role('editor'); }
				if('2'  == $transperms[$userlevel]) { $user->set_role('contributor'); }

				
				update_user_meta( $ret_id, 'wp_user_level', $transperms[$userlevel] );
				update_user_meta( $ret_id, 'rich_editing', 'false');
			}// End foreach($users as $user)
			
			// Store id translation array for future use
			add_option('s9yid2wpid', $s9yid2wpid);
			
			
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> users imported.'), $count).'<br /><br /></p>';
			return true;
		}// End if(is_array($users)
		
		echo __('No Users to Import!');
		return false;
		
	}// End function user2wp()
	
	function posts2wp($posts='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$s9yposts2wpposts = get_option('s9yposts2wpposts');
		if ( !$s9yposts2wpposts ) $s9yposts2wpposts = array();

		$cats = array();

		// Do the Magic
		if(is_array($posts))
		{
			echo '<p>'.__('Importing Posts...').'<br /><br /></p>';
			foreach($posts as $post)
			{
				$count++;
				if(!is_array($post))
					$post = (array) $post;

				extract($post);
				
				$post_id = $id;
				
				// Jon (Snowulf.com) 2010-06-04 --  Re-instated & fixed draft/publish
				$post_status = null;
				if($isdraft == "true"){
					$post_status = 'draft';
				}else{
					$post_status = 'publish';
				}

				// dobschat - 2008/11/08 - chenged $authorid -> $author
				$uinfo = ( get_user_by( 'login', $author ) ) ? get_user_by( 'login', $author ) : 1;
				$authorid = ( is_object( $uinfo ) ) ? $uinfo->ID : $uinfo ;

				$post_title = $wpdb->escape($title);
				// dobschat - 2008/11/08 - split body and extended text in wp with <!--more-->
				if ($wpdb->escape($extended) != "") {
					$post_body = $wpdb->escape($body)."<!--more-->".$wpdb->escape($extended);
				} else {
					$post_body = $wpdb->escape($body);
				}
				//
				$post_time = date('Y-m-d H:i:s', $timestamp);
				$post_modified = date('Y-m-d H:i:s', $last_modified);
				
				// Import Post data into WordPress
				
				if($pinfo = post_exists($post_title,$post_body))
				{
					$ret_id = wp_insert_post(array(
							'ID'				=> $pinfo,
							'post_date'			=> $post_time,
							'post_date_gmt'		=> $post_time,
							'post_author'		=> $authorid,
							'post_modified'		=> $post_modified,
							'post_modified_gmt' => $post_modified_gmt,
							'post_title'		=> $post_title,
							'post_content'		=> $post_body,
							'post_status'		=> $post_status,
							'post_name'			=> sanitize_title($post_title)
							)
							);
				}
				else 
				{
					$ret_id = wp_insert_post(array(
							'post_date'			=> $post_time,
							'post_date_gmt'		=> $post_time,
							'post_author'		=> $authorid,
							'post_modified'		=> $post_modified,
							'post_modified_gmt' => $post_modified,
							'post_title'		=> $post_title,
							'post_content'		=> $post_body,
							'post_status'		=> $post_status,
							'menu_order'		=> $post_id,	// Jon (Snowulf.com) 2010-06-04 -- Added for some ID workaround hackery
							'post_name'			=> sanitize_title($post_title))
							);
				}
				$s9yposts2wpposts[] = array($id, $ret_id);
				
				// Make Post-to-Category associations
				$cats = $this->get_s9y_cat_assoc($id);
				
				$wpcats = array();
				if ( is_array($cats) )
				foreach($cats as $cat)
				{
					$c = get_category_by_slug($cat->categoryid . '-' . sanitize_title($cat->category_name));
					$wpcats[] = $c->term_id;
				}
				$cats = (is_array($wpcats)) ? $wpcats : (array) $wpcats;
				
				if(!empty($cats)) { wp_set_post_categories( $ret_id, $cats); }
				else { wp_set_post_categories( $ret_id, get_option('default_category')); }

				// dobschat - 2008/11/08 - added importing tags
				$tags = $this->get_s9y_tag_assoc($id);
				if (is_array($tags)) {
					array_walk_recursive($tags, 'set_tags_from_s9y', $ret_id);
				}
				// -----
			}
		}
		// Store ID translation for later use
		update_option('s9yposts2wpposts', $s9yposts2wpposts);
		
		echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> posts imported.'), $count).'<br /><br /></p>';
		return true;	
	}
	
	function comments2wp($comments='')
	{
		ini_set('display_errors', true);
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$s9ycm2wpcm = array();
		$postarr = get_option('s9yposts2wpposts');
		
		
		// Magic Mojo
		if(is_array($comments))
		{
			echo '<p>'.__('Importing Comments...').'<br /><br /></p>';
			foreach($comments as $comment)
			{
				$count++;
				extract($comment);
				// WordPressify Data
				$comment_ID = (int) $id;
				//$comment_post_ID = find_comment_parent($postarr, $id);
				$comment_approved = ($status == 'approved') ? 1 : 0;
				//parent comment not parent post;
				$comment_parent=$parent_id;
				$name = $wpdb->escape(($author));
				$email = $wpdb->escape($email);
				$web = $wpdb->escape($url);
				$message = $wpdb->escape($body);
				$wpdb->show_errors();
				$posted = date('Y-m-d H:i:s', $timestamp);
				if(comment_exists($name, $posted) > 0)
				{
					//$cinfo = comment_exists($name, $posted);
					// Update comments

					$ret_id = wp_update_comment(array(
							'comment_ID'			=> $comment_ID,
							'comment_post_ID'		=> find_comment_parent($postarr, $entry_id),
							'comment_author'		=> $name,
							'comment_parent'		=> $comment_parent,
							'comment_author_email'	=> $email,
							'comment_author_url'	=> $web,
							'comment_author_IP'		=> $ip,
							'comment_date'			=> $posted,
							'comment_content'		=> $message,
							'comment_approved'		=> $comment_approved)
							);
				}
				else 
				{
					// Insert comments
					$ret_id = wp_insert_comment(array(
							'comment_ID'			=> $comment_ID,
							'comment_post_ID'		=> find_comment_parent($postarr, $entry_id),
							'comment_author'		=> $name,
							'comment_author_email'	=> $email,
							'comment_author_url'	=> $web,
							'comment_author_IP'		=> $ip,
							'comment_date'			=> $posted,
							'comment_content'		=> $message,
							'comment_parent'		=> $comment_parent,
							'comment_approved'		=> $comment_approved)

							);
				$wpdb->query("UPDATE $wpdb->comments SET comment_ID=$comment_ID WHERE comment_ID=$ret_id");
				}
				//$s9ycm2wpcm[$comment_ID] = $ret_id;
			}
			// Store Comment ID translation for future use
			//add_option('s9ycm2wpcm', $s9ycm2wpcm);		

			
			// Associate newly formed categories with posts
			//get_comment_count($ret_id);
			
			
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> comments imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Comments to Import!');
		return false;
	}
		
	function import_categories() 
	{	
		// Category Import	
		$cats = $this->get_s9y_cats();
		$this->cat2wp($cats);
		add_option('s9y_cats', $cats);
		
		
			
		echo '<form action="admin.php?import=serendipity&amp;step=2" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Users'));
		echo '</form>';

	}

	function import_users()
	{
		// User Import
		$users = $this->get_s9y_users(); 
		$this->users2wp($users);
		
		echo '<form action="admin.php?import=serendipity&amp;step=3" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Posts'));
		echo '</form>';
	}
	
	function import_posts()
	{
		// Post Import
		
		// Process 100 posts per load, and reload between runs
		$start = $_REQUEST["start"];
		if ( !$start ) $start = 0;
		$posts = $this->get_s9y_posts($start);
		
		if ( count($posts) != 0 ) 
			$this->posts2wp($posts);
			
		if ( count($posts) == 100 ) 
		{
			echo "Reloading: More work to do.";
			$url = "admin.php?import=serendipity&step=3&start=".($start+100);
			?>
			<script type="text/javascript">
			window.location = '<?php echo $url; ?>';
			</script>
			<?php
			return;
		}
		
		echo '<form action="admin.php?import=serendipity&amp;step=4" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Comments'));
		echo '</form>';
	}
	
	function import_comments()
	{
		// Comment Import
		$comments = $this->get_s9y_comments();
		$this->comments2wp($comments);
		
		echo '<form action="admin.php?import=serendipity&amp;step=5" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Geo-Tags (if available)'));
		echo '</form>';
	}
	
        // Christian Harms - 2012/08/01 -- import geo tags as postmeta for WP Geo pluglin
        function import_geotags()
	{
		//read all tags
		$tags = $this->get_s9y_geotags();
		$this->geotags2wp($tags);

		echo '<form action="admin.php?import=serendipity&amp;step=6" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Finish'));
		echo '</form>';
	}		
	
	function cleanup_s9yimport()
	{
		delete_option('spre');
		delete_option('s9y_cats');
		delete_option('s9yid2wpid');
		delete_option('s9ycat2wpcat');
		delete_option('s9yposts2wpposts');
		delete_option('s9ycm2wpcm');
		delete_option('s9ylinks2wplinks');
		delete_option('s9yuser');
		delete_option('s9ypass');
		delete_option('s9yname');
		delete_option('s9yhost');
		delete_option('s9ygeotags2wp');
		$this->tips();
	}
	
	function tips()
	{
		echo '<p>'.__('Welcome to WordPress.  We hope (and expect!) that you will find this platform incredibly rewarding!  As a new WordPress user coming from serendipity, there are some things that we would like to point out.  Hopefully, they will help your transition go as smoothly as possible.').'</p>';
		echo '<h3>'.__('Users').'</h3>';
		echo '<p>'.__('You have already setup WordPress and have been assigned an administrative login and password.  Forget it.  You didn\'t have that login in serendipity, why should you have it here?  Instead we have taken care to import all of your users into our system.  Unfortunately there is one downside.  Because both WordPress and serendipity uses a strong encryption hash with passwords, it is impossible to decrypt it and we are forced to assign temporary passwords to all your users.') . ' <strong>' . __( 'Every user has the same username, but their passwords are reset to password123. It is strongly recommended that you change passwords immediately.') .'</strong></p>';
		echo '<h3>'.__('Preserving Authors').'</h3>';
		echo '<p>'.__('Secondly, we have attempted to preserve post authors.  If you are the only author or contributor to your blog, then you are safe.  In most cases, we are successful in this preservation endeavor.  However, if we cannot ascertain the name of the writer due to discrepancies between database tables, we assign it to you, the administrative user.').'</p>';
			echo '<h3>'.__('WordPress Resources').'</h3>';
		echo '<p>'.__('Finally, there are numerous WordPress resources around the internet.  Some of them are:').'</p>';
		echo '<ul>';
		echo '<li>'.__('<a href="http://www.wordpress.org">The official WordPress site</a>').'</li>';
		echo '<li>'.__('<a href="http://wordpress.org/support/">The WordPress support forums').'</li>';
		echo '<li>'.__('<a href="http://codex.wordpress.org">The Codex (In other words, the WordPress Bible)</a>').'</li>';
		echo '</ul>';
		echo '<p>'.sprintf(__('That\'s it! What are you waiting for? Go <a href="%1$s">login</a>!'), '/wp-login.php').'</p>';
	}
	
	function db_form()
	{
		echo '<ul>';
		printf('<li><label for="dbuser">%s</label> <input type="text" name="dbuser" id="dbuser" /></li>', __('Serendipity Database User:'));
		printf('<li><label for="dbpass">%s</label> <input type="password" name="dbpass" id="dbpass" /></li>', __('Serendipity Database Password:'));
		printf('<li><label for="dbname">%s</label> <input type="text" id="dbname" name="dbname" /></li>', __('Serendipity Database Name:'));
		printf('<li><label for="dbhost">%s</label> <input type="text" id="dbhost" name="dbhost" value="localhost" /></li>', __('Serendipity Database Host:'));
		printf('<li><label for="dbprefix">%s</label> <input type="text" name="dbprefix" id="dbprefix"  /></li>', __('Serendipity Table prefix (if any):'));
		printf('<li><label for="dbcharset">%s</label> <input type="text" name="dbcharset" id="dbcharset" value="utf8" /></li>', __('Serendipity Table charset:'));
		echo '</ul>';
	}
	
	function dispatch() 
	{

		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];
		$this->header();
		
		if ( $step > 0 ) 
		{
			if($_POST['dbuser'])
			{
				if(get_option('s9yuser'))
					delete_option('s9yuser');	
				add_option('s9yuser',$_POST['dbuser']);
			}
			if($_POST['dbpass'])
			{
				if(get_option('s9ypass'))
					delete_option('s9ypass');	
				add_option('s9ypass',$_POST['dbpass']);
			}
			
			if($_POST['dbname'])
			{
				if(get_option('s9yname'))
					delete_option('s9yname');	
				add_option('s9yname',$_POST['dbname']);
			}
			if($_POST['dbhost'])
			{
				if(get_option('s9yhost'))
					delete_option('s9yhost');
				add_option('s9yhost',$_POST['dbhost']); 
			}
			if($_POST['dbprefix'])
			{
				if(get_option('spre'))
					delete_option('spre');
				add_option('spre',$_POST['dbprefix']); 
			}			
			if($_POST['dbcharset'])
			{
				if(get_option('s9ycharset'))
					delete_option('s9ycharset');
				add_option('s9ycharset',$_POST['dbcharset']); 
			}			


		}

		switch ($step) 
		{
			default:
			case 0 :
				$this->greet();
				break;
			case 1 :
				$this->import_categories();
				break;
			case 2 :
				$this->import_users();
				break;
			case 3 :
				$this->import_posts();
				break;
			case 4 :
				$this->import_comments();
				break;
			case 5 :
				$this->import_geotags();
				break;
			case 6 :
				$this->cleanup_s9yimport();
				break;
		}
		
		$this->footer();
	}

	function Serendipity_Import() 
	{
		// Nothing.	
	}
}

$s9y_import = new Serendipity_Import();
register_importer('serendipity', 'Serendipity', __('Import posts from a Serendipity Blog'), array ($s9y_import, 'dispatch'));