<?php
/*
Plugin Name: Network notifier
Plugin URI: http://www.thomasclausen.dk/wordpress/
Description: Automatically sends out an e-mail notifying the users of a blog when a new post is published or a new comment is approved.
Version: 0.5
Author: Thomas Clausen
Author URI: http://www.thomasclausen.dk
*/

function network_notifier_get_users() {
	global $wpdb;
	
	$network_notifier_current_blog = get_current_blog_id() == 1 ? 'wpress_user_level' : 'wpress_' . get_current_blog_id() . '_user_level';
	$network_notifier_user_array = array();
	
	for ( $i = 0; $i <= 10; $i++ ) {
		$authors = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value = %s", $network_notifier_current_blog, $i ) );
		foreach ( $authors as $author ) {
			$author = get_userdata( $author->user_id );
			$name = $author->nickname;
			$email = strtolower( $author->user_email );
			
			$network_notifier_user_array[] = array( 'name' => $name, 'email' => $email );
		}
	}
	
	return $network_notifier_user_array;
}

function network_notifier_send_email( $to, $subject, $message ) {
	$network_notifier_site_name = str_replace( '"', "'", get_bloginfo( 'name' ) );
	$network_notifier_site_email = strtolower( get_bloginfo( 'admin_email' ) );
	
	$charset = get_bloginfo( 'charset' );
	$headers  = "From: \"{$network_notifier_site_name}\" <{$network_notifier_site_email}>\n";
	$headers .= "MIME-Version: 1.0\n";
	$headers .= "Content-Type: text/plain; charset=\"{$charset}\"\n";
	
	$send_email = wp_mail( $to, $subject, $message, $headers );
	
	return $send_email;
}

// E-mail all users when a post is published
function network_notifier_post_published( $pid ) {
	$pid = (int) $pid;
	
	$post = get_post( $pid );
	$post_status = $post->post_status;
	
	if ( $post_status == 'publish' ) :
		$network_notifier_user_array = network_notifier_get_users();
		
		$network_notifier_site_name = str_replace( '"', "'", get_bloginfo( 'name' ) );
		$network_notifier_site_url = site_url();
		
		foreach ( $network_notifier_user_array as $author ) {
			$name = esc_attr( $author['name'] );
			$email = esc_attr( strtolower( $author['email'] ) );
			
			//if ( is_email( $email ) && $email != $network_notifier_site_email ) : // if admin shouldn't get notified
			if ( is_email( $email ) ) :
				$subject = 'Nyt indlæg på "' . $network_notifier_site_name . '"';
				
				$message = 'Hej ' . $name . "\n\n";
				$message .= 'Der er kommet et nyt indlæg (' . $post->post_title . ') på "' . $network_notifier_site_name . '" (' . $network_notifier_site_url . '):' . "\n";
				$message .= $post->post_content . "\n\n";
				$message .= 'Link direkte til indlægget og evt. kommentarer:' . "\n";
				$message .= $post->guid . "\n\n";
				$message .= 'Med venlig hilsen' . "\n";
				$message .= 'IdeFA Gruppen';
				
				$network_notifier_sending_email = network_notifier_send_email( $email, $subject, $message );
			endif;
		}
	endif;
	
	return $pid;
}
add_action( 'publish_post', 'network_notifier_post_published' );

// Runs when a post is changed from private to published status.
// Action function arguments: post object. (Actions for all post status transitions are available: see wp_transition_post_status())
/*add_action( 'new_to_publish', 'network_notifier_post_published' );
add_action( 'pending_to_publish', 'network_notifier_post_published' );
add_action( 'draft_to_publish', 'network_notifier_post_published' );
add_action( 'auto-draft_to_publish', 'network_notifier_post_published' );
add_action( 'future_to_publish', 'network_notifier_post_published' );
add_action( 'private_to_publish', 'network_notifier_post_published' );
add_action( 'inherit_to_publish', 'network_notifier_post_published' );
add_action( 'trash_to_publish', 'network_notifier_post_published' );*/
// Makes it execute two times but without the correct ID

// Runs when a post is published, or if it is edited and its status is "published".
// Action function arguments: post ID.
//add_action( 'publish_future_post', 'network_notifier_post_published' );

// E-mail all users when a post is commented
function network_notifier_post_commented( $cid ) {
	$cid = (int) $cid;
	
	$comment = get_comment( $cid );
	
	if ($comment->comment_approved) :
		$network_notifier_user_array = network_notifier_get_users();
		
		$network_notifier_site_name = str_replace( '"', "'", get_bloginfo( 'name' ) );
		$network_notifier_site_url = site_url();
		
		foreach ( $network_notifier_user_array as $author ) {
			$name = esc_attr( $author['name'] );
			$email = esc_attr( strtolower( $author['email'] ) );
			
			//if ( is_email( $email ) && $email != $network_notifier_site_email ) : // if admin shouldn't get notified
			if ( is_email( $email ) ) :
				$subject = 'Ny kommentar på "' . $network_notifier_site_name . '"';
				
				$message = 'Hej ' . $name . "\n\n";
				$message .= 'Der er kommet en ny kommentar på "' . $network_notifier_site_name . '" (' . $network_notifier_site_url . '):' . "\n";
				$message .= $comment->comment_content . "\n\n";
				$message .= 'Link direkte til kommentaren:' . "\n";
				$message .= $network_notifier_site_url . '/index.php?p=' . $comment->comment_post_ID . '#comment-' . $cid . "\n\n";
				$message .= 'Med venlig hilsen' . "\n";
				$message .= 'IdeFA Gruppen';
				
				$network_notifier_sending_email = network_notifier_send_email( $email, $subject, $message );
			endif;
		}
	endif;
	
	return $cid;
}
add_action( 'comment_post', 'network_notifier_post_commented', 50 );

function network_notifier_show_users() {
	global $wpdb;
	$network_notifier_user_array = network_notifier_get_users();
	
	echo '<p>Denne side viser blot en liste med de brugere, der modtager e-mails for denne blog.</p>';
	echo '<pre><strong>Modtagere:</strong>' . "\r\n";
	foreach ( $network_notifier_user_array as $user ) {
		echo esc_attr( $user['name'] ) . ' (' . esc_attr( strtolower( $user['email'] ) ) . ')' . "\r\n";
	}
	echo '</pre>';
	
	/*$blogs = get_last_updated( ' ', 0, 5 );
	if ( is_array( $blogs ) ) :
		echo '<h2>Last updated:</h2>';
		echo '<ul>';
		foreach ( $blogs as $blog) :
			echo '<li>';
			echo '<a href="http://' . $blog['domain'] . $blog['path'] . '">' . get_blog_option( $blog['blog_id'], 'blogname' ) . ' (' . $blog['blog_id'] . ')</a></li>';
			// If blog == ? - don't run!
			switch_to_blog( $blog['blog_id'] );
			$args = array( 'post_type' => 'post', 'post_status' => 'publish', 'orderby' => 'date', 'posts_per_page' => 1 );
			$latest_post = new WP_Query( $args );
			while ( $latest_post->have_posts() ) : $latest_post->the_post(); ?>
				<h1><?php the_title(); ?></h1>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<?php the_content(); ?>
				</article>
			<?php endwhile;
			restore_current_blog();
			echo '</li>';
		endforeach; // blogs as blog
		echo '</ul>';
	endif;*/
	
	/*global $switched;
    switch_to_blog(7);
    echo 'You switched from blog ' . $switched . ' to 7';
    restore_current_blog();
    echo 'You switched back.';*/
	
	$stats = get_sitestats();
	echo '<p>Der er pt. oprettet ' . $stats['blogs'] . ' blogs og ' . $stats['users'] . ' brugere p&aring; hele netv&aelig;rket.</p>';
	
	/*
	Changelog
	0.4 - (28/12/2011)
	- Finde action der aktiveres n&aring;r der skrives en kommentar - og hvordan der ikke blot kommer en hvid side
	- Finde action der aktiveres n&aring;r der udgives et indl&aelig;g (kun ved udgiv - ikke ved draft eller private) - og hvordan der ikke blot kommer en hvid side
	
	0.3 - (27/12/2011)
	 - Vil pludselig ikke sende!! (m&aring;ske noget server knas)
	
	0.2 - (14/10/2011)
	- Tilf&oslash;jet lidt dokumentation.
	- &AElig;ndre funktionerne til at hente alle brugere og sende e-mails til to funktioner.
	
	0.1 - (26/09/2011)
	- Hent alle brugere fra denne blog.
	- Send e-mail rundt til alle brugere for denne blog (udvikler stadig s&aring; sender kun til "clausen@idefa.dk" og post ID er sat til "2").
	- "Vis brugere" under "Indstillinger" viser, e-mail og hvilke brugere der sendes til.
	*/
}

function network_notifier_init() {
	// Adds page under "Settings"
	add_options_page( 'Vis modtagere', 'Vis modtagere', 5, 'network_notifier_show_users', 'network_notifier_show_users' );
}
add_action( 'admin_menu', 'network_notifier_init' ); ?>