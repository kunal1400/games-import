<?php
/*
Plugin Name: Games Importer
Plugin URI: https://www.paidmembershipspro.com/wp/pmpro-customizations/
Description: Import data from API https://api.rawg.io/docs into wordpress post
Version: .1
Author: Kunal Malviya
*/

function games_importer_custom_cron_schedule( $schedules ) {
    $schedules['every_one_minute'] = array(
        'interval' => 60, // Every 1 hours
        'display'  => __( 'Every 1 minutes' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'games_importer_custom_cron_schedule' );

// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'games_importer_cron_hook' ) ) {
    wp_schedule_event( time(), 'every_one_minute', 'games_importer_cron_hook' );
}

/// Hook into that action that'll fire every six hours
add_action( 'games_importer_cron_hook', 'per_min_event' );

// add_action('init', 'per_min_event');
function per_min_event() {

	/****** DB COUNTER CODE: START ******/
	$counter = get_option('_counter');

	// If counter is set in db then update
	if( !empty($counter) ) {
		$counter++;
	} 
	else {
		$counter = 1;
	}

	// Updating the option
	update_option('_counter', $counter);

	/****** END ******/

	// Calling the games api to get vr games
	$returnString = get_games_by_tag( $counter );

	// If response is not null 
	if($returnString) {
		update_option('_counter_response_'.$counter, json_encode($returnString));
	}
	// die;
}


function get_games_by_tag($page = 1) {
	$tag = 'vr';
	$requestUrl = 'https://api.rawg.io/api/games?tags='.$tag.'&page='.$page;
	$request = wp_remote_get( $requestUrl );

	if( is_wp_error( $request ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $request );
	$data = json_decode( $body, true );

	// if($data['next']) {
	// 	return $requestUrl;
	// }
	// else {
	// 	return null;
	// }

	// If count is greater than 0 then start download
	if( $data['results'] && count($data['results']) > 0 ) {
		$insertedIds = array();
		foreach ($data['results'] as $i => $gameInfo) {
			$postType = 'post';
			
			// Checking if post with same slug is present in db or not
			$dbPosts = get_posts(array(
			  'name'        => $gameInfo['slug'],
			  'post_type'   => $postType,
			  'post_status' => array('draft'),
			  'numberposts' => 1
			));

			// If post not exists then get call game detail api do insert in db
			if( count($dbPosts) == 0 ) {
				$insertedIds[] = set_game_detail($gameInfo['id']);				
			}
		}
		return $insertedIds;
	}
	else {
		// All apis has been called with pagination
		return null;
	}
}

function set_game_detail($gameId) {
	$requestUrl = 'https://api.rawg.io/api/games/'.$gameId;

	$request = wp_remote_get( $requestUrl );

	if( is_wp_error( $request ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $request );
	$data = json_decode( $body, true );

	// If valid data is coming from API then insert that data as post
	if( !empty($data['id']) ) {
		$newPostId = wp_insert_post(array(
			'post_title' => $data['name'], 
			'post_type' => 'post', 
			'post_status' => 'draft',
			'post_name' => $data['slug'],
			'post_content' => $data['description_raw'],
		));
		return $newPostId;
	}
	else {
		return null;
	}
}

/**
* The site is using adavanced custom fields so we are updating all fields by this function
* https://www.advancedcustomfields.com/resources/update_field/
**/
function save_custom_fields($post_id) {
	// Text fields
	update_field('name_original', $value, $post_id);
	update_field('released', $value, $post_id);
	update_field('tba', $value, $post_id);
	update_field('ratings__title', $value, $post_id);
	update_field('parent_platforms__platform__name', $value, $post_id);
	update_field('platforms__platform__name', $value, $post_id);
	update_field('stores__store__name', $value, $post_id);
	update_field('developers__name', $value, $post_id);
	update_field('genres__name', $value, $post_id);
	update_field('tags__language', $value, $post_id);
	update_field('publishers__name', $value, $post_id);
	update_field('publishers__games_count', $value, $post_id);
	update_field('publishers__name', $value, $post_id);
	// Textarea
	update_field('tags__name', $value, $post_id);
	update_field('description_raw', $value, $post_id);
	// Number fields
	update_field('metacritic', $value, $post_id);
	update_field('playtime', $value, $post_id);
	// Image
	update_field('background_image', $value, $post_id);
	// Link
	update_field('website', $value, $post_id);
	update_field('stores__url', $value, $post_id);
	update_field('stores__store__domain', $value, $post_id);
}
