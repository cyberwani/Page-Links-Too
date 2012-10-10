<?php

/*
	Plugin Name: Page Links Too
	Plugin URI: http://gizburdt.com
	Description: Allows you to point WordPress pages or posts to a URL of your choosing.
	Version: 0.1
	Author: Gijs Jorissen
	Author URI: http://gizbburdt.com
*/

/* 	Credits
	
	This plugin was initially made by Mark Jaquith. Though I loved it very much, 
	I found a shortage. It wasn't possible to link to another Post.
	So that's why a built this plugin, by using his code as base.
	So thanks to Mark Jaquith!
*/

/*  Copyright 2012 Gijs Jorissen

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class Page_Links_Too 
{
	var $links;
	var $targets;
	var $targets_on_this_page;

	/**
	 * Class constructor
	 * Adds textdomain and all the needed hooks
	 *
	 * @author Mark Jaquith
	 * @since 0.1
	 * 
	 */
	function __construct() 
	{
		load_plugin_textdomain( 'page-links-too', false, basename( dirname( __FILE__ ) ) . '/languages' );

		add_action( 'admin_init', array( $this, 'register_admin_styles' ) );
		add_action( 'admin_print_styles', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_init', array( $this, 'register_admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		add_filter( 'wp_list_pages',       array( $this, 'wp_list_pages'       )        );
		add_action( 'template_redirect',   array( $this, 'template_redirect'   )        );
		add_filter( 'page_link',           array( $this, 'link'                ), 20, 2 );
		add_filter( 'post_link',           array( $this, 'link'                ), 20, 2 );
		add_filter( 'post_type_link',      array( $this, 'link'                ), 20, 2 );
		add_action( 'do_meta_boxes',       array( $this, 'add_meta_boxes'      ), 20, 2 );
		add_action( 'save_post',           array( $this, 'save_post'           )        );
		add_filter( 'wp_nav_menu_objects', array( $this, 'wp_nav_menu_objects' ), 10, 2 );
		add_action( 'load-post.php',       array( $this, 'load_post'           )        );
		add_filter( 'the_posts',           array( $this, 'the_posts'           )        );
	}


	function register_admin_styles()
	{
		wp_register_style( 'page-links-too-css', plugin_dir_url( __FILE__ ) . '/assets/css/style.css', '', '', 'screen' );
	}
	
	function enqueue_admin_styles()
	{
		wp_enqueue_style( 'page-links-too-css' );
	}

	function register_admin_scripts()
	{
		wp_register_script( 'page-links-too-js', plugin_dir_url( __FILE__ ) . '/assets/js/functions.js', array( 'jquery' ) );
	}


	function enqueue_admin_scripts()
	{
		wp_enqueue_script( 'page-links-too-js' );
	}

	
	/**
	 * Returns post ids and meta values that have a given key
	 * 
	 * @param  string $key post meta key
	 * @return array an array with objects
	 *
	 * @author Mark Jaquith
	 * @since 0.1
	 * 
	 */
	function meta_by_key( $key ) 
	{
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = %s", $key ) );
	}


	function load_post() 
	{
		if( isset( $_GET['post'] ) ) 
		{
			if( get_post_meta( absint( $_GET['post'] ), '_links_to', true ) ) 
			{
				add_action( 'admin_notices', array( $this, 'add_notificiation' ) );
			}
		}
	}


	function add_notificiation()
	{
		?><div class="updated"><p><?php _e( '<strong>Note</strong>: This content is pointing to an alternate URL. Use the &#8220;Page Links Too&#8221; box to change this behavior.', 'page-links-to' ); ?></p></div><?php
	}


	function id_to_url_callback( &$val, $key ) 
	{
		$val = get_permalink( $val );
	}


	/**
	 * Returns all links for the current site
	 * 
	 * @return array an array of links, keyed by post ID
	 *
	 * @author  Mark Jaquith
	 * @since 0.1
	 * 
	 */
	function get_links() 
	{
		global $wpdb, $blog_id;

		if ( ! isset( $this->links[$blog_id] ) )
			$links_to = $this->meta_by_key( '_links_to' );
		else
			return $this->links[$blog_id];

		if ( ! $links_to ) 
		{
			$this->links[$blog_id] = false;
			return false;
		}

		foreach ( (array) $links_to as $link )
			$this->links[$blog_id][$link->post_id] = (int) $link->meta_value;

		return $this->links[$blog_id];
	}

	
	/**
	 * Returns all targets for the current site
	 * 
	 * @return array an array of targets, keyed by post ID
	 *
	 * @author  Mark Jaquith
	 * @since 0.1
	 * 
	 */
	function get_targets()
	{
		global $wpdb, $target_cache, $blog_id;

		if ( ! isset( $this->targets[$blog_id] ) )
			$links_to = $this->meta_by_key( '_links_to_target' );
		else
			return $this->targets[$blog_id];

		if ( ! $links_to ) {
			$this->targets[$blog_id] = false;
			return false;
		}

		foreach ( (array) $links_to as $link )
			$this->targets[$blog_id][$link->post_id] = $link->meta_value;

		ob_start();
		echo '<pre>';
		var_dump( $this->targets[$blog_id] );
		echo '</pre>';

		return $this->targets[$blog_id];
	}

	
	/**
	 * Adds the meta box to the post or page edit screen
	 * 
	 * @param string $page    
	 * @param string $context
	 * 
	 */
	function add_meta_boxes( $page, $context )
	{
		if( in_array( $page, apply_filters( 'page_links_too_post_types', array_keys( get_post_types( array( 'show_ui' => true ) ) ) ) ) );
			add_meta_box( 'page_links_too', __( 'Point this content to:' ), array( $this, 'meta_box' ), $page, 'side', 'low' );
	}


	/**
	 * Meta box callback
	 * 
	 */
	function meta_box( $post )
	{
		echo '<div class="page_links_too">';
			wp_nonce_field( plugin_basename( __FILE__ ), 'page_links_too_nonce' );

			$type = get_post_meta( $post->ID, '_links_to_type', true);
			$type = ( ! $type ) ? 'wp' : $type;

			$url = $type == 'alternate' ? get_post_meta( $post->ID, '_links_to', true ) : '';
			$url = ( ! $url ) ? 'http://' : $url;

			$aid = $type == 'post' ? get_post_meta( $post->ID, '_links_to', true ) : '';
			
			?>
			
			<p>
				<input type="radio" id="plt_link_wp" name="plt_link" value="wp" <?php checked( $type, 'wp' ); ?> /> 
				<label for="plt_link_wp"><?php _e( 'Its normal WordPress URL', 'page-links-too' ); ?></label>
			</p>
			<div class="plt_section">
				<p>
					<input type="radio" id="plt_link_post" name="plt_link" value="post" <?php checked( $type, 'post' ); ?> /> 
					<label for="plt_link_post"><?php _e( 'Another Post', 'page-links-too' ); ?></label>
				</p>
				
				<div class="plt_expander hide-if-js">
					<p>					
						<select name="plt_link_to_id" id="plt_link_to_id">
							<option value="0"><?php _e('Choose a Post', 'page-links-too') ?></option>
							<?php 

								$post_types = get_post_types( array( 'show_ui' => true ) );

								foreach( $post_types as $post_type )
								{
									echo '<option value="0" disabled="disabled" style="font-weight: bold;">' . $post_type . '</option>';
									$posts = get_posts( array( 'post_type' => $post_type, 'exclude' => $post->ID ) );

									foreach( $posts as $p )
									{
										echo '<option value="' . $p->ID . '" ' . selected( $aid, $p->ID, false ) . '>&nbsp;&nbsp;&nbsp;' . $p->post_title . '</option>';
									}
								}

							?>
						</select>
					</p>
				</div>
			</div>
			<div class="plt_section">
				<p>
					<input type="radio" id="plt_link_alternate" name="plt_link" value="alternate" <?php checked( $type, 'alternate' ); ?> /> 
					<label for="plt_link_alternate"><?php _e( 'An alternate URL', 'page-links-too' ); ?></label>
				</p>
				
				<div class="plt_expander <?php echo ( ! $linked ) ? 'hide-if-js' : ''; ?>">
					<p>
						<input name="plt_link_to_url" type="text" id="plt_link_to_url" value="<?php echo esc_attr( $url ); ?>" />
					</p>
					<p>
						<input type="checkbox" name="plt_link_to_new_window" id="plt_link_to_new_window" value="_blank" <?php checked( '_blank', get_post_meta( $post->ID, '_links_to_target', true ) ); ?>>
						<label for="plt_link_to_new_window"><?php _e( 'Open this link in a new window', 'page-links-to' ); ?></label>
					</p>
				</div>
			</div>
			
			<?php

		echo '</div>';
	}


	/**
	 * Saves data on post save
	 * @param int $post_ID a post ID
	 * @return int the post ID that was passed in
	 */
	function save_post( $post_id ) 
	{
		if( ! wp_verify_nonce( $_POST['page_links_too_nonce'], plugin_basename( __FILE__ ) ) ) return;
		if( ! isset( $_POST['plt_link'] ) ) return;

		$type = $_POST['plt_link'];
		$link = false;

		if( $type == 'wp' )
		{
			delete_post_meta( $post_id, '_links_to_type' );
			delete_post_meta( $post_id, '_links_to' );
			delete_post_meta( $post_id, '_links_to_target' );
		}
		elseif( $type == 'post' )
		{
			if( $link = $_POST['plt_link_to_id'] == 0 )
				$type = 'wp';
			else
				$link = $_POST['plt_link_to_id'];

			delete_post_meta( $post_id, '_links_to_target' );
		}
		elseif( $type == 'alternate' )
		{
			$link = stripslashes( $_POST['plt_link_to_url'] );
			if ( 0 === strpos( $link, 'www.' ) )
				$link = 'http://' . $link;
			
			if ( isset( $_POST['plt_link_to_new_window'] ) )
				update_post_meta( $post_id, '_links_to_target', '_blank' );
			else
				delete_post_meta( $post_id, '_links_to_target' );
		}

		if( $link )
		{
			update_post_meta( $post_id, '_links_to_type', $type );
			update_post_meta( $post_id, '_links_to', $link );
		}

		return $post_id;
	}

	
	/**
	 * Filter for post link
	 * 
	 * @param  string $link
	 * @param  mixed $post
	 * @return string
	 * 
	 */
	function link( $link, $post ) 
	{
		$links = $this->get_links();

		// Really strange, but page_link gives us an ID and post_link gives us a post object
		$id = ( is_object( $post ) && $post->ID ) ? $post->ID : $post;

		if ( isset( $links[$id] ) && $links[$id] )
		{
			$link = is_int( $links[$id] ) ? get_permalink( $links[$id] ) : esc_url( $links[$id] );
		}

		return $link;
	}


	/**
	 * Performs a redirect, if appropriate
	 */
	function template_redirect() 
	{
		if ( ! is_single() && ! is_page() )
			return;

		global $wp_query;

		$link = get_post_meta( $wp_query->post->ID, '_links_to', true );
		if( is_int( $link ) ) $link = get_permalink( $link );

		if ( ! $link )
			return;

		wp_redirect( $link, 301 );
		exit;
	}


	/**
	 * Filters the list of pages to alter the links and targets
	 * @param string $pages the wp_list_pages() HTML block from WordPress
	 * @return string the modified HTML block
	 */
	function wp_list_pages( $pages ) 
	{
		$highlight = false;
		$links = $this->get_links();
		$target_cache = $this->get_targets();

		if ( ! $links && ! $target_cache )
			return $pages;

		$this_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$targets = array();

		foreach ( (array) $links as $id => $page ) 
		{
			if ( isset( $target_cache[$id] ) )
				$targets[$page] = $target_cache[$id];

			if ( str_replace( 'http://www.', 'http://', $this_url ) == str_replace( 'http://www.', 'http://', is_int( $page ) ? get_permalink( $page ) : $page ) || ( is_home() && str_replace( 'http://www.', 'http://', trailingslashit( get_bloginfo( 'url' ) ) ) == str_replace( 'http://www.', 'http://', is_int( $page ) ? get_permalink( $page ) : trailingslashit( $page ) ) ) ) {
				$highlight = true;
				$current_page = is_int( $page ) ? get_permalink( $page ) : esc_url( $page );
			}
		}

		if ( count( $targets ) ) 
		{
			foreach ( $targets as  $p => $t ) {
				$p = esc_url( $p );
				$t = esc_attr( $t );
				$pages = str_replace( '<a href="' . $p . '"', '<a href="' . $p . '" target="' . $t . '"', $pages );
			}
		}

		if ( $highlight ) 
		{
			$pages = preg_replace( '| class="([^"]+)current_page_item"|', ' class="$1"', $pages ); // Kill default highlighting
			$pages = preg_replace( '|<li class="([^"]+)"><a href="' . preg_quote( $current_page ) . '"|', '<li class="$1 current_page_item"><a href="' . $current_page . '"', $pages );
		}

		return $pages;
	}


	function wp_nav_menu_objects( $items, $args ) 
	{
		$target_cache = $this->get_targets();

		$new_items = array();

		foreach ( $items as $item ) 
		{
			if ( isset( $target_cache[$item->object_id] ) )
				$item->target = $target_cache[$item->object_id];
			
			$new_items[] = $item;
		}

		return $new_items;
	}
	

	function the_posts( $posts )
	{
		$target_cache = $this->get_targets();

		if ( is_array( $target_cache ) && count( $target_cache ) ) 
		{
			$pids = array();

			foreach ( (array) $posts as $post )
				$pids[$post->ID] = $post->ID;

			$targets = array_keys( array_intersect_key( $target_cache, $pids ) );

			if ( count( $targets ) ) 
			{
				array_walk( $targets, array( $this, 'id_to_url_callback' ) );
				$targets = array_unique( $targets );
				$this->targets_on_this_page = $targets;
				
				wp_enqueue_script( 'jquery' );
				add_action( 'wp_head', array( $this, 'targets_in_new_window_via_js' ) );
			}
		}

		return $posts;
	}


	function targets_in_new_window_via_js() 
	{
		?><script>(function($){var t=<?php echo json_encode( $this->targets_on_this_page ); ?>;$(document).ready(function(){var a=$('a');$.each(t,function(i,v){a.filter('[href="'+v+'"]').attr('target','_blank');});});})(jQuery);</script><?php
	}

}

new Page_Links_Too;
