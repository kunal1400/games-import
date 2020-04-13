<?php
/*
Plugin Name: Games Importer
Plugin URI: https://www.paidmembershipspro.com/wp/pmpro-customizations/
Description: Import data from API https://api.rawg.io/docs into wordpress post
Version: .1
Author: Kunal Malviya
*/

register_activation_hook( __FILE__, 'game_importer_plugin_activation' );
function game_importer_plugin_activation() {
    if ( ! wp_next_scheduled ( 'per_min_event' ) ) {
        wp_schedule_event( time(), '1min', 'per_min_event' );
    }
}

function per_min_event() {
	echo $counter = get_option('_counter');
	if(!empty($counter)) {
		$counter++;
		update_option('_counter', $counter);
	} else {
		update_option('_counter', 1);
	}
}

add_action('init', 'per_min_event');

function get_games_by_tag() {
	$tag = 'vr';
	$page = 1;
	
	while ( $page < 10000 ) {
		$request = wp_remote_get( 'https://api.rawg.io/api/games?tags='.$tag.'&page='.$page );

		if( is_wp_error( $request ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $request );
		$data = json_decode( $body, true );

		echo '<pre>';
		echo $page;
		print_r($data['next']);
		// print_r($request);
		echo '</pre>';
		$page++;	
	}
	

	// // If count is greater than 0 then start download
	// if( count($data['results']) > 0 ) {
	// 	foreach ($data['results'] as $i => $gameInfo) {
	// 		$postType = 'post';
	// 		// $gameInfo['id'];
	// 		// $gameInfo['slug'];
	// 		// $gameInfo['name'];

	// 		$dbPosts = get_posts(array(
	// 		  'name'        => $gameInfo['slug'],
	// 		  'post_type'   => $postType,
	// 		  'post_status' => array('draft'),
	// 		  'numberposts' => 1
	// 		));

	// 		// If post not exists then do insert otherwise do update
	// 		if( count($dbPosts) == 0 ) {
	// 			$newPostId = wp_insert_post(array(
	// 			  'post_title' => $gameInfo['name'], 
	// 			  'post_type' => 'post', 
	// 			  'post_status' => 'draft',
	// 			  'post_name' => $gameInfo['slug']
	// 			));
	// 		}
	// 	}
	// }

	die;
}