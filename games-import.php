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

//// Hook into that action that'll fire every six hours
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

	// // If response is not null 
	// if($returnString) {
	// 	update_option('_counter_response_'.$counter, json_encode($returnString));
	// }
}


function get_games_by_tag($page = 1) {
	$tag = 'vr';
	$requestUrl = 'https://api.rawg.io/api/games?tags='.$tag.'&dates=2019-01-01,2020-12-01&ordering=-rating&page_size=1&page='.$page;

	// https://api.rawg.io/api/games?tags=vr&dates=2019-01-01,2020-12-01&ordering=-rating

	$request = wp_remote_get( $requestUrl );

	if( is_wp_error( $request ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $request );
	$data = json_decode( $body, true );

	// If count is greater than 0 then start download
	if( $data['results'] && count($data['results']) > 0 ) {		
		$insertedIds = array();
		$i = -1;
		$allItems = count($data['results']);
		return fetchNext($data, $i, $allItems);
		// return $insertedIds;
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

		// If post has been inserted then save custom fields
		if($newPostId) {
			
			// Updating the api url from where we pulled the data
			update_post_meta($newPostId, '_rawg_api_url', $requestUrl);

			$customFields = array( 
				'name_original' => $data['name_original'],
				'released' => $data['released'], 
				'tba' => $data['tba'],
				'description_raw' => $data['description_raw'],
				'metacritic' => $data['metacritic'],
				'playtime' => $data['playtime'],
				'website' => array(
					'url' => $data['website'],
					'target' => '_blank',
					'title' => 'website',
				)
			);

			// If parent_platforms
			if ( !empty($data['parent_platforms']) ) {
				$pps = '';
				foreach ($data['parent_platforms'] as $i => $pp) {
					if($i == 0)
						$pps .= $pp['platform']['name'];
					else
						$pps .= ', '.$pp['platform']['name'];					
				}
				$customFields['parent_platforms__platform__name'] = $pps;
			}

			// If parent_platforms
			if ( !empty($data['platforms']) ) {
				$pps = '';
				foreach ($data['platforms'] as $i => $pp) {
					if($i == 0)
						$pps .= $pp['platform']['name'];
					else
						$pps .= ', '.$pp['platform']['name'];					
				}
				$customFields['platforms__platform__name'] = $pps;
			}

			// If parent_platforms
			if ( !empty($data['stores']) ) {
				foreach ($data['stores'] as $i => $store) {
					$customFields['stores__url'] = array(
						'url' => $store['url'],
						'target' => '_blank',
						'title' => 'Store Url',
					);
					$customFields['stores__store__domain'] = array(
						'url' => $store['store']['domain'],
						'target' => '_blank',
						'title' => 'Store Domain',
					);					
					$customFields['stores__store__name'] = $store['store']['name'];				
				}
			}

			// If parent_platforms
			if ( !empty($data['developers']) ) {
				$pps = '';
				foreach ($data['developers'] as $i => $pp) {
					if($i == 0)
						$pps .= $pp['name'];
					else
						$pps .= ', '.$pp['name'];					
				}
				$customFields['developers__name'] = $pps;			
			}

			// If parent_platforms
			if ( !empty($data['publishers']) ) {
				foreach ($data['publishers'] as $i => $publisher) {
					$customFields['publishers__name'] = $publisher['name'];
					$customFields['publishers__games_count'] = $publisher['games_count'];
				}
			}

			// If parent_platforms
			if ( !empty($data['genres']) ) {
				$pps = '';
				foreach ($data['genres'] as $i => $pp) {
					if($i == 0)
						$pps .= $pp['name'];
					else
						$pps .= ', '.$pp['name'];					
				}
				$customFields['genres__name'] = $pps;			
			}

			// If parent_platforms
			if ( !empty($data['tags']) ) {
				$pps = '';
				foreach ($data['tags'] as $i => $pp) {
					if($i == 0)
						$pps .= $pp['name'];
					else
						$pps .= ', '.$pp['name'];					
				}
				$customFields['tags__name'] = $pps;			
			}

			// Storing background image in Advanced Custom Field
			if( !empty($data['background_image']) ) {
				$attachId = saveRemoteUrl($data['background_image'] , $data['slug']);				
				if($attachId) {
					$customFields['background_image'] = $attachId;
				}
			}
			save_custom_fields($customFields, $newPostId);

		}
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
function save_custom_fields( $customFields, $post_id ) {

	foreach ($customFields as $key => $value) {
		update_field($key, $value, $post_id);
	}

	// // Text fields
	// // update_field('name_original', $value, $post_id);
	// // update_field('released', $value, $post_id);
	// // update_field('tba', $value, $post_id);

	// update_field('ratings__title', $value, $post_id);
	// // update_field('parent_platforms__platform__name', $value, $post_id);
	// // update_field('platforms__platform__name', $value, $post_id);
	// // update_field('stores__store__name', $value, $post_id);
	// // update_field('developers__name', $value, $post_id);
	// // update_field('genres__name', $value, $post_id);
	// update_field('tags__language', $value, $post_id);
	// // update_field('publishers__name', $value, $post_id);
	// // update_field('publishers__games_count', $value, $post_id);
	// // Textarea
	// // update_field('tags__name', $value, $post_id);
	// // update_field('description_raw', $value, $post_id);
	// // Number fields
	// // update_field('metacritic', $value, $post_id);
	// // update_field('playtime', $value, $post_id);
	// // Image
	// update_field('background_image', $value, $post_id);
	// // Link
	// // update_field('website', $value, $post_id);
	// // update_field('stores__url', $value, $post_id);
	// // update_field('stores__store__domain', $value, $post_id);
}

// add_action('init','saveRemoteUrl');
function saveRemoteUrl( $remoteUrl, $slug='' ) {
	include_once( ABSPATH . 'wp-admin/includes/image.php' );

	$arrContextOptions = array(
	    "ssl" => array(
	        "verify_peer" => false,
	        "verify_peer_name" => false,
	    ),
	); 
	// $remoteUrl = 'https://images.hqseek.com/pictures/Playboy_Corin_Riggs_set1/10429JR-0160.jpg';

	if(!empty($remoteUrl)) {
		$filename 	= $slug.'_'.time().'.png';
		$uploaddir 	= wp_upload_dir();
		$uploadfile = $uploaddir['path'] . '/' . $filename;
		$contents 	= file_get_contents($remoteUrl, false, stream_context_create($arrContextOptions));
		$savefile 	= fopen($uploadfile, 'w');
		fwrite($savefile, $contents);
		fclose($savefile);

		$wp_filetype = wp_check_filetype(basename($filename), null );

		$attachment = array(
		    'post_mime_type' => $wp_filetype['type'],
		    'post_title' => $filename,
		    'post_content' => '',
		    'post_status' => 'inherit'
		);

		$attach_id = wp_insert_attachment( $attachment, $uploadfile );
		$imagenew = get_post( $attach_id );
		$fullsizepath = get_attached_file( $imagenew->ID );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return $attach_id;
	}
	else {
		return null;
	}	
}

function fetchNext($data, $index, $totalItems) {
	$index++;	
	// echo 'Looping ==>'.$index.'/'.$totalItems.'<br/>';
	if( $index < $totalItems ) {
		$gameInfo = $data['results'][$index];
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
			fetchNext($data, $index, $totalItems);
		}
		else {
			$insertedIds[] = $gameInfo['slug'].' already present';					
			fetchNext($data, $index, $totalItems);	
		}
	}
	else {
		// Iteration completed
		return $insertedIds;
	}
}
