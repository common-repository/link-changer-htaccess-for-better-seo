<?php

/**
 * Plugin Name:		Link changer htaccess for better SEO
 * Plugin URI:		https://wordpress.org/plugins/link-changer-htaccess/
 * Description:		Prepare rules for .htaccess file before changing URL in Wordpress
 * Version:		1.0
 * Requires at least:	5.4.2
 * Requires PHP:	7.2
 * Author:              Alexander Butyuhin
 * Author URI:		https://roboteye.biz/
 * License:		PL v2 or later
 * License URI:		https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:		link-changer-htaccess
 * Domain Path:		/languages
 */


global $wpdb;
$table_name = $wpdb->prefix . 'old_linky';

// install table in DB
function lch_create_db() {

	global $wpdb;
	$lch_products_db_version = '1.0';
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'old_linky';
			
			
            $sql = "CREATE TABLE $table_name (
                    ID bigint(20) NOT NULL AUTO_INCREMENT,
                            `line_id` bigint(20) NOT NULL,
                            `guid` text NOT NULL,
                            `type` varchar(20) NOT NULL,
                            PRIMARY KEY  (ID)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
            add_option( 'link-changer-htaccess_db_version', $lch_products_db_version );

            $url = get_site_url();

            $all = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('publish', 'future', 'private')");


            $posts_per_page = 500;
            $start = 0;
            $x = ceil($all/$posts_per_page);


            for ($i = 0; $i<= $x; $i++){

                    $posts = $wpdb->get_results("SELECT ID, guid FROM {$wpdb->posts} WHERE post_status IN ('publish', 'future', 'private') ORDER BY ID ASC LIMIT $start, $posts_per_page", ARRAY_N);


                            foreach ( (array) $posts as $post ) {

                                            $wpdb->insert( 
                                                    $table_name, 
                                                    array( 
                                                            'line_id' => $post[0], 
                                                            //'name' => $post->post_name, 
                                                            'guid' => str_replace($url, "", get_permalink($post[0]))."/",
                                                            'type' => 'post'
                                                    )
/* 									array( 
                                                            'line_id' => $post->ID, 
                                                            //'name' => $post->post_name, 
                                                            'guid' => str_replace($url, "", get_permalink($post->ID))."/",
                                                            'type' => 'post'
                                                    ) */

                                            );
                            }
                            gc_collect_cycles();
                            ob_clean();
                            unset($posts, $post);

                            $start = ($i+1)*$posts_per_page+1;
            }

                    //SELECT TERMS
            $posts = $wpdb->get_results("SELECT t.term_id AS id, t.slug AS post_url FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE  tt.taxonomy = 'category'");
                    foreach ( (array) $posts as $post ) {

            //var_dump($post);	

                                    $wpdb->insert( 
                                            $table_name, 
                                            array( 
                                                    'line_id' => $post->id, 
                                                    //'name' => $post->post_name, 
                                                    'guid' => str_replace($url, "", get_category_link($post->id))."/",
                                                    'type' => 'category'
                                            )

                                    );

                    }


                    //SELECT TERMS
            $posts = $wpdb->get_results("SELECT t.term_id AS id, t.slug AS post_url FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE   tt.taxonomy = 'post_tag'");
                    foreach ( (array) $posts as $post ) {

            //var_dump($post);	

                                    $wpdb->insert( 
                                            $table_name, 
                                            array( 
                                                    'line_id' => $post->id, 
                                                    //'name' => $post->post_name, 
                                                    'guid' => str_replace($url, "", get_tag_link($post->id))."/",
                                                    'type' => 'tag'
                                            )

                                    );

                    }
					
}

if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
	
	lch_create_db();

	
}
	
//remove table on deactivation
register_deactivation_hook( __FILE__, 'link_changer_htaccess_plugin_remove_database' );
function link_changer_htaccess_plugin_remove_database() {
     global $wpdb;
     $table_name = $wpdb->prefix . 'old_linky';
     $sql = "DROP TABLE IF EXISTS $table_name";
     $wpdb->query($sql);
     delete_option("link-changer-htaccess_db_version");
}



//MENU
add_action('admin_menu', 'lch_plugin_menu');
 
function lch_plugin_menu(){
        add_menu_page( 'LCH Plugin Page', 'LHC Plugin', 'manage_options', 'link_changer_htaccess-plugin', 'lch_init' );
}
 
function lch_init(){
	
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.')    );
		}
        echo "<h1>Hello!</h1>";
		echo "<h2><span style='color:red;'>Attention!!!</span> For correct work, plugin must be activated before any changes of the links.</h2><br>";
		echo "If you are ready, please do the following steps:<br>";
		echo "1) Create back up of your site and database<br>";
		echo "2) go to <a href='".site_url()."/wp-admin/options-permalink.php'>Permalinks</a> and change &#34;<strong>Permalinks Setting</strong>&#34;<br>";
		echo "3) return to this page and click &#34;<strong>Prepare redirection</strong>&#34; button below<br>";
		echo "<br>";
		echo '<form action="options-general.php?page=link_changer_htaccess-plugin" method="post">';
		wp_nonce_field('lch_button_clicked');
		echo '<input name="lch_button" type="hidden" value="1">';
		submit_button('Prepare redirection');
		echo "</form>";
		
		if (isset($_POST['lch_button']) && check_admin_referer('lch_button_clicked')) {
			
			// the button has been pressed AND we've passed the security check
			lch_button_action();
		}
		
}


function lch_button_action() {

	echo '<div id="message" class="updated fade"><p>'
			.'RewriteEngine On<br>'
			.'RewriteBase /<br><br>';
	global $wpdb;
	$table_name = $wpdb->prefix . 'old_linky';
	
	
	// POSTS
	$old_posts = $wpdb->get_results("SELECT line_id, guid FROM $table_name WHERE type ='post' ");

			
	$posts = $wpdb->get_results("SELECT id FROM {$wpdb->posts} WHERE post_status IN ('publish', 'future', 'private')");
			foreach ( (array) $posts as $post ) {
					foreach ( (array) $old_posts as $p ) {
							if ($post->id == $p->line_id){
								echo 'Redirect 301 '.$p->guid.' '.get_permalink($post->id)."<br>\n";
							}
					}
			}
			
	
	//CATEGORIES
	$old_posts = $wpdb->get_results("SELECT line_id, guid FROM $table_name WHERE type ='category' ");

			
	$posts = $wpdb->get_results("SELECT t.term_id AS id, t.slug AS post_url FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE  tt.taxonomy = 'category'");
			foreach ( (array) $posts as $post ) {
					foreach ( (array) $old_posts as $p ) {
							if ($post->id == $p->line_id){
								echo 'Redirect 301 '.$p->guid.' '.get_category_link($post->id)."<br>\n";
							}
					}
			}
			
	//TAGS
	$old_posts = $wpdb->get_results("SELECT line_id, guid FROM $table_name WHERE type ='tag' ");

			
	$posts = $wpdb->get_results("SELECT t.term_id AS id, t.slug AS post_url FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE   tt.taxonomy = 'post_tag'");
			foreach ( (array) $posts as $post ) {
					foreach ( (array) $old_posts as $p ) {
							if ($post->id == $p->line_id){
								echo 'Redirect 301 '.$p->guid.' '.get_tag_link($post->id)."<br>\n";
							}
					}
			}
	
	echo 'The end of the rules. Do not copy this line to your .htaccess file.' . '</p></div>';

} 