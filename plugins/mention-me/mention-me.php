<?php
/*
Plugin Name: Mention-Me Widget
Plugin URI: http://wordpress.org/extend/plugins/mention-me/
Description: Simple widget based on p2s recent-comment widget in order to display @replies for a logged in user.
Author: Thorsten Ott <code (at) thorsten-ott.de>
Version: 1.0.5
Author URI: http://blog.webzappr.com
*/
class Mention_Me extends WP_Widget {
	function Mention_Me() { 
		$this->WP_Widget( false, __('Mention Me', 'mentionme'), array('description' => __('Recent mentions of your name (@replies)', 'mentionme')));
		
		add_action( 'comment_post', array(&$this, 'flush_widget_cache') );
		add_action( 'wp_set_comment_status', array(&$this, 'flush_widget_cache') );
		add_action( 'save_post', array(&$this, 'flush_widget_cache') );
		
		$this->default_num_to_show = 3;
		$this->default_avatar_size = 32;
	}
	
	function flush_widget_cache() {
		global $current_user;
		$users = get_users_of_blog();
		// wipe caches for all users
		foreach ( $users as $user ) {
 			wp_cache_delete( 'p2_recent_mentions_' . $user->ID, 'widget' ); 
		}
		wp_cache_delete( 'p2_recent_mentions_' . $current_user->ID, 'widget' ); 

	}
	
	
	function form( $instance ) {
		$title = esc_attr( $instance['title'] );
		$title_id = $this->get_field_id('title');
		$title_name = $this->get_field_name('title');
		$num_to_show = esc_attr( $instance['num_to_show'] );
		$num_to_show_id = $this->get_field_id('num_to_show');
		$num_to_show_name = $this->get_field_name('num_to_show');
		$show_also_post_followups = esc_attr( $instance['show_also_post_followups'] );
		$show_also_post_followups_id = $this->get_field_id('show_also_post_followups');
		$show_also_post_followups_name = $this->get_field_name('show_also_post_followups');
		$show_also_comment_followups = esc_attr( $instance['show_also_comment_followups'] );
		$show_also_comment_followups_id = $this->get_field_id('show_also_comment_followups');
		$show_also_comment_followups_name = $this->get_field_name('show_also_comment_followups');
		$avatar_size = esc_attr( $instance['avatar_size'] );
		$avatar_size_id = $this->get_field_id('avatar_size');
		$avatar_size_name = $this->get_field_name('avatar_size');
		$sizes = array(
			'32' => 'Yes &mdash; 32x32',
			'-1' => __('No avatar', 'p2'),
		);
?>
	<p>
		<label for="<?php echo $title_id ?>"><?php _e('Title:', 'mentionme') ?> 
			<input type="text" class="widefat" id="<?php echo $title_id ?>" name="<?php echo $title_name ?>"
				value="<?php echo $title; ?>" />
		</label>
	</p>
	<p>
		<label for="<?php echo $num_to_show_id ?>"><?php _e('Number of mentions to show:', 'mentionme') ?> 
			<input type="text" class="widefat" id="<?php echo $num_to_show_id ?>" name="<?php echo $num_to_show_name ?>"
				value="<?php echo $num_to_show; ?>" />
		</label>
	</p>
	<p>
		<label for="<?php echo $show_also_post_followups_id ?>"><?php _e('Show also replies to your posts without @:', 'mentionme') ?> 
			<input type="checkbox" value=1 id="<?php echo $show_also_post_followups_id ?>" name="<?php echo $show_also_post_followups_name ?>"
				<?php if( $show_also_post_followups ): echo 'checked="checked"'; endif; ?> />
		</label>
	</p>
	<p>
		<label for="<?php echo $show_also_comment_followups_id ?>"><?php _e('Show also replies to your comments without @:', 'mentionme') ?> 
			<input type="checkbox" value=1 id="<?php echo $show_also_comment_followups_id ?>" name="<?php echo $show_also_comment_followups_name ?>"
				<?php if( $show_also_comment_followups ): echo 'checked="checked"'; endif; ?> />
		</label>
	</p>
	<p>
		<label for="<?php echo $avatar_size_id ?>"><?php _e('Avatars:', 'mentionme') ?> 
			<select name="<?php echo $avatar_size_name ?>" id="<?php echo $avatar_size_id ?>">
			<?php foreach($sizes as $value => $label): ?>
				<option value="<?php echo $value ?>" <?php selected($value, $avatar_size); ?>><?php echo $label; ?></option>
			<?php endforeach; ?>
			</select>
		</label>
	</p>
	
<?php		
	}
	
	function update( $new_instance, $old_instance ) {
		$new_instance['num_to_show'] = (int)$new_instance['num_to_show']? (int)$new_instance['num_to_show'] : $this->default_num_to_show;
		$new_instance['avatar_size'] = (int)$new_instance['avatar_size']? (int)$new_instance['avatar_size'] : $this->default_avatar_size;
		$new_instance['show_also_post_followups'] = $new_instance['show_also_post_followups'] ? true : false;
		$new_instance['show_also_comment_followups'] = $new_instance['show_also_comment_followups'] ? true : false;
		return $new_instance;
	}
	
	function widget( $args, $instance ) {
		global $current_user;
		if ( empty( $current_user->ID ) ) {
			return;
		}
		extract( $args );
		
		$title = (isset( $instance['title'] ) && $instance['title'])? $instance['title'] : __('Recent mentions', 'p2');
		$num_to_show = (isset( $instance['num_to_show'] ) && (int)$instance['num_to_show'])? (int)$instance['num_to_show'] : $this->default_num_to_show;
		$show_also_comment_followups = ( isset( $instance['show_also_comment_followups'] ) && $instance['show_also_comment_followups'] ) ? true : false;
		$show_also_post_followups = ( isset( $instance['show_also_post_followups'] ) && $instance['show_also_post_followups'] ) ? true : false;
		$avatar_size = (isset( $instance['avatar_size'] ) && (int)$instance['avatar_size'])? (int)$instance['avatar_size'] : $this->default_avatar_size;
		
		$no_avatar = $avatar_size == '-1';
		
		$mentions = $this->recent_mentions( $num_to_show, $show_also_post_followups, $show_also_comment_followups );
		
		echo $before_widget . $before_title . wp_specialchars( $title ) . $after_title;
?>
		<table class='p2-recent-mentions' cellspacing='0' cellpadding='0' border='0' avatar="<?php echo esc_attr($avatar_size); ?>">
		<?php foreach( $mentions as $comment ):
			echo $this->single_comment_html( $comment, $avatar_size );
		endforeach;
		echo "\t</table>";
	}
	
	function comment_url_maybe_local( $comment ) {
		// Only use the URL's #fragment if the comment is visible on the page.
		// Works by detecting if the comment's post is visible on the page... may break if P2 decides to do clever stuff with comments when paginated
		if ( $comment->ID != $comment->post_id ) { // this is a comment
			$comment_url = get_comment_link( $comment->ID );
			if ( @constant( 'DOING_AJAX' ) && isset( $_GET['vp'] ) && is_array( $_GET['vp'] ) && in_array( $comment->post_id, $_GET['vp'] ) ) {
				$comment_url = "#comment-{$comment->ID}";
			} else {
				static $posts_on_page = false;
				if ( false === $posts_on_page ) {
					global $wp_query;
					
					$posts_on_page = array();
					foreach ( (array)array_keys( $wp_query->posts ) as $k )
						$posts_on_page[$wp_query->posts[$k]->ID] = true;
				}
	
				if ( isset( $posts_on_page[$comment->post_id] ) )
					$comment_url = "#comment-{$comment->ID}";
			}
		} else {
			$comment_url = get_permalink( $comment->ID );
			if ( @constant( 'DOING_AJAX' ) && isset( $_GET['vp'] ) && is_array( $_GET['vp'] ) && in_array( $comment->post_id, $_GET['vp'] ) ) {
				$comment_url = "#prologue-{$comment->ID}";
			} else {
				static $posts_on_page = false;
				if ( false === $posts_on_page ) {
					global $wp_query;
					
					$posts_on_page = array();
					foreach ( (array)array_keys( $wp_query->posts ) as $k )
						$posts_on_page[$wp_query->posts[$k]->ID] = true;
				}
	
				if ( isset( $posts_on_page[$comment->post_id] ) )
					$comment_url = "#prologue-{$comment->ID}";
			}
		}
		return $comment_url;
	}
	
	function p2_get_at_name_map_ids() {
		global $wpdb;
		$users = get_users_of_blog();
		// get display names (can take out if you only want to handle nicenames)
		foreach ( $users as $user ) {
 			$name_map["@$user->display_name"] = $user->ID;
		}

		// get nicenames (can take out if you only want to handle display names)
		$user_ids = join( ',', array_map( 'intval', $name_map ) );

		foreach ( $wpdb->get_results( "SELECT ID, user_nicename from $wpdb->users WHERE ID IN($user_ids)" ) as $user ) {
 			$name_map["@$user->user_nicename"] = $user->ID;
		}

		// remove any empty name just in case
		unset( $name_map['@'] );
		
		return $name_map;
	}
	
	function recent_mentions( $num_to_show, $show_also_post_followups=false, $show_also_comment_followups=false ) {
		global $wpdb, $current_user;

		//$cache = wp_cache_get( 'p2_recent_mentions_' . $current_user->ID, 'widget' );
		if ( !is_array( $cache ) ) {
			$cache = array();
		}
		if ( isset( $cache[$num_to_show] ) && is_array( $cache[$num_to_show] ) ) {
			return $cache[$num_to_show];
		}
		
		$mapping = array();		
		$name_map = $this->p2_get_at_name_map_ids();
		foreach( $name_map as $map => $user_id ) {
			if ( (int) $current_user->ID == (int) $user_id ) {
				$comment_mapping[] = "comment_content LIKE '%$map%'";
				$post_mapping[] = "post_content LIKE '%$map%'";
			}
		}
		
		if ( $show_also_comment_followups ) {
			$comment_replies = (array) $wpdb->get_results("SELECT comment_id as ID, comment_post_id as post_id, user_id as user_id, comment_author as author, comment_author_url as author_url, comment_author_email as author_email, comment_content as content, comment_date_gmt as gmt_date FROM $wpdb->comments WHERE comment_approved = '1' AND comment_parent IN ( SELECT comment_id FROM $wpdb->comments WHERE comment_approved = '1' AND user_id = {$current_user->ID} ) ORDER BY comment_date_gmt DESC");
		} else {
			$comment_replies = array();
		}
		if ( $show_also_post_followups ) {
			$post_replies = (array) $wpdb->get_results("SELECT comment_id as ID, comment_post_id as post_id, user_id as user_id, comment_author as author, comment_author_url as author_url, comment_author_email as author_email, comment_content as content, comment_date_gmt as gmt_date FROM $wpdb->comments WHERE  comment_approved = '1' AND comment_parent = 0 AND comment_post_id IN ( SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_author = {$current_user->ID} ) ORDER BY comment_date_gmt DESC");
		} else {
			$post_replies = array();
		}
			
		if ( !empty( $comment_mapping ) ) {
			$where_add = ' AND (' . join( " OR ", $comment_mapping ) . ') ';
			$mentions_comments = (array) $wpdb->get_results("SELECT comment_id as ID, comment_post_id as post_id, user_id as user_id, comment_author as author, comment_author_url as author_url, comment_author_email as author_email, comment_content as content, comment_date_gmt as gmt_date FROM $wpdb->comments WHERE comment_approved = '1' $where_add ORDER BY comment_date_gmt DESC");
		} else {
			$mentions_comments = array();
		}
		
		if ( !empty( $post_mapping ) ) {
			$where_add = ' AND (' . join( " OR ", $post_mapping ) . ') ';
			$mentions_posts = (array) $wpdb->get_results("SELECT ID as ID, ID as post_id, post_author as user_id, post_author as author, NULL as author_url, NULL as author_email, post_content as content, post_date_gmt as gmt_date FROM $wpdb->posts as p1 WHERE post_status = 'publish' $where_add ORDER BY post_date_gmt DESC");
		} else {
			$mentions_posts = array();
		}
		
		$mentions = array_merge( $comment_replies, $post_replies, $mentions_comments, $mentions_posts );
		
		$sort=array();
		foreach( $mentions as $data ) {
			$sort[strtotime($data->gmt_date)] = $data;
		}
		krsort( $sort ); 
		$mentions = array_slice( $sort, 0, $num_to_show );

		$cache[$num_to_show] = $mentions;
		wp_cache_add( 'p2_recent_mentions_' . $current_user->ID, $cache, 'widget' );
		return $mentions;
	}
	
	function single_comment_html($comment, $avatar_size ) {
		$no_avatar = $avatar_size == '-1';
		
		if ( !$comment->author ) $comment->author = __('Anonymous', 'p2');
		if ( (int) $comment->user_id > 0 ) {
			$user = get_userdata( $comment->user_id ); 
			$author_name = $user->display_name;
			$author_email = $user->user_email;
			$author_url = $user->user_url;
		} else {
			$author_name = $comment->author;
			$author_email = $comment->author_email;
			$author_url = $comment->author_url;
		}
		
		$author_html = $author_name;
		
		$excerpt = wp_html_excerpt( $author_name, 20 );
		if ( $author_name != $excerpt ) $author_name = $author_excerpt.'&hellip;';

		$avatar = $no_avatar? '' : get_avatar( $author_email, $avatar_size );
		
		$comment_author_url = $author_url ? clean_url( $author_url ) : '';
		if ( $comment_author_url ) {
			$avatar = "<a href='$comment_author_url' rel='nofollow'>$avatar</a>";
			// entitities in comment author are kept escaped in the db and tags are not allowed, so no need of HTML escaping here
			$author_html = "<a href='$comment_author_url' rel='nofollow'>$author_name</a>";
		}
		
		$author_name = esc_attr( $author_name );
		
		$row  = "<tr>";
		if ( !$no_avatar) $row .= "<td title='$author_name' class='avatar' style='height: ${avatar_size}px; width: ${avatar_size}px'>" . $avatar . '</td>';

		$post_title = wp_specialchars( strip_tags( get_the_title( $comment->post_id ) ) );
		$excerpt = wp_html_excerpt( $post_title, 30 );
		if ( $post_title != $excerpt ) $post_title = $excerpt.'&hellip;';

		$comment_content = strip_tags( $comment->content );
		$excerpt = wp_html_excerpt( $comment_content, 50 );
		if ( $comment_content != $excerpt ) $comment_content = $excerpt.'&hellip;';

		$comment_url = Mention_Me::comment_url_maybe_local( $comment );

		$row .= sprintf( '<td class="text">'.__( "%s on <a href='%s' class='tooltip' title='%s'>%s</a>" , 'mentionme') . '</td></tr>', $author_html, $comment_url, attribute_escape($comment_content), $post_title );
		return $row;
	}
}


function load_mention_me_widget() {
	register_widget( 'Mention_Me' );
}
add_action( 'widgets_init', 'load_mention_me_widget' );

?>
