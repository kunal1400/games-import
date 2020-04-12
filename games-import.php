<?php
/*
Plugin Name: Games Importer
Plugin URI: https://www.paidmembershipspro.com/wp/pmpro-customizations/
Description: Import data from API https://api.rawg.io/docs into wordpress post
Version: .1
Author: Kunal Malviya
*/

// // If this file is called directly, abort.
// if ( ! defined( 'WPINC' ) ) {
// 	die;
// }

include 'admin/functions.php';

/**
* If the name of schedule event is same as previous one then it was not working so 
* made it dynamic. Now if any issue occur in future then just update the name of event.
**/
define('SCHEDULE_HOOK_NAME', 'games_importer_cron_hook');

/*** 
* When plugin activated then callback function will be called and set schedule 
***/
register_activation_hook(__FILE__, 'cp_fb_schedule');


/*** 
* Plugin activation callback function.
* This function set a schedule by providing Hook to us.
***/
function cp_fb_schedule() {
    // Schedule an action if it's not already scheduled
	if ( ! wp_next_scheduled( SCHEDULE_HOOK_NAME ) ) {
	    wp_schedule_event( time(), 'every_one_minute', SCHEDULE_HOOK_NAME );
	}
}

/*** 
* Whenever hook is called then the callback function will run
***/
add_action( SCHEDULE_HOOK_NAME, 'per_min_event' );


/*** 
* By this function we are setting the time interval
***/
function games_importer_custom_cron_schedule( $schedules ) {
    if( !isset($schedules["every_one_minute"]) ){
    	$schedules['every_one_minute'] = array(
	        'interval' => 60, // Every 1 minute
	        'display'  => __( 'Every 1 minutes' ),
	    );
    }
    return $schedules;
}
add_filter( 'cron_schedules', 'games_importer_custom_cron_schedule' );


/*** 
* When plugin deactivated then callback function will be called 
***/
register_deactivation_hook(__FILE__, 'my_deactivation');


/*** 
* Plugin deactivation callback function and this function clear a schedule.
***/
function my_deactivation() {
    wp_clear_scheduled_hook( SCHEDULE_HOOK_NAME );
}

// add_action('wp_ajax_my_action', 'per_min_event');
// add_action('init', 'per_min_event');
function per_min_event() {
	if(get_option('rawg_games_import_started') == "no") {
		return;
	}

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
	if(count($returnString['data']) > 0) {
		update_option('_counter_response_'.$counter, json_encode($returnString));
	} else {
		// update_option('_counter', 0);		
		update_option('rawg_games_import_started', 'no');
	}

}

function get_games_by_tag($page = 1) {
	$tag = 'vr';
	$thisYear = date("Y-m-d", time());
	$pastYear = date("Y-m-d", strtotime(date("Y-m-d", time()) . " - 365 day"));
	// https://api.rawg.io/api/games?tags=vr&dates=2019-01-01,2020-12-01&ordering=-rating
	$requestUrl = 'https://api.rawg.io/api/games?tags='.$tag.'&dates='.$pastYear.','.$thisYear.'&ordering=-rating&page_size=3&page='.$page;

	$request = wp_remote_get( $requestUrl );

	if( is_wp_error( $request ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $request );
	$data = json_decode( $body, true );	

	// If count is greater than 0 then start download
	if( $data['results'] && count($data['results']) > 0 ) {
		$insertedIds = array();
		foreach ($data['results'] as $index => $gameInfo) {
			$postType = 'games';
			$minimumRatingsCount = 5;
			
			// Setting the custom filters because some paramters are not present in api
			if( !empty($gameInfo['ratings_count']) && $gameInfo['ratings_count'] > $minimumRatingsCount ) {

				// Checking if post with same slug is present in db or not
				$dbPosts = get_posts(array(
				  'name'        => $gameInfo['slug'],
				  'post_type'   => $postType,
				  'post_status' => array('draft', 'publish'),
				  'numberposts' => 1
				));		

				// If post not exists then get call game detail api do insert in db
				if( count($dbPosts) == 0 ) {
					// echo "<p>Post Not exists</p>";
					$insertedIds[] = set_game_detail($gameInfo['id']);
				}
				else {
					// echo "<p>Post exists</p>";
					$insertedIds[] = $gameInfo['slug'].' already present';					
				}				
			}
			else {
				$message = $gameInfo['slug'].' ratings_count is smaller than '.$minimumRatingsCount;
				// echo $message;
				$insertedIds[] = $message;
			}
		}	
		// echo '<pre>';
		// print_r($insertedIds);
		// echo '</pre>';
		return array('url'=>$requestUrl, 'data' => $insertedIds);		
	}
	else {
		// All apis has been called with pagination
		return array('url'=>$requestUrl, 'data' => []);
	}
}

function set_game_detail($gameId) {
	require_once( ABSPATH . 'wp-admin/includes/taxonomy.php');
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
			'post_type' => 'games', 
			'post_status' => 'publish',
			'post_name' => $data['slug'],
			'post_content' => $data['description_raw'],
		));

		// If post has been inserted then save custom fields
		if($newPostId) {
			
			// Updating the api url from where we pulled the data
			$customFields['_rawg_api_url'] = $requestUrl;

			$customFields = array( 
				'name_original' => $data['name_original'],
				'released' => $data['released'], 
				'tba' => $data['tba'],
				'description_raw' => $data['description_raw'],
				'metacritic' => 76,
				'playtime' => $data['playtime'],
				'website' => $data['website']
			);

			// If metacritic
			if ( !empty($data['metacritic']) ) {				
				$customFields['metacritic'] = $data['metacritic'];
			}

			// If parent_platforms
			if ( !empty($data['parent_platforms']) ) {
				$pps = '';
				$allCategories = array();
				foreach ($data['parent_platforms'] as $i => $pp) {					
					$termName = $pp['platform']['name'];

					// Checking if parent_platforms exists
					$categoryId = term_exists( $termName, 'platforms' );					
					if(!empty($categoryId['term_id'])) {
						$allCategories[] = (int)$categoryId['term_id'];
					}
					else {
						$insertedPlatforms = wp_insert_term( $termName, 'platforms' );
						$allCategories[] = (int)$insertedPlatforms['term_id'] ;
					}
					// if($i == 0)
					// 	$pps .= $pp['platform']['name'];
					// else
					// 	$pps .= ', '.$pp['platform']['name'];					
				}
				wp_set_object_terms( $newPostId, $allCategories, 'platforms', false );
				// $customFields['parent_platforms__platform__name'] = $pps;
			}

			// If parent_platforms link
			if ( !empty($data['platforms']) ) {
				$html = '<div class="game__system-requirement">';
				$allCategories = array();				
				foreach ($data['platforms'] as $i => $pp) {
					$termName = $pp['platform']['name'];

					// Checking if parent_platforms exists
					$categoryId = term_exists( $termName, 'platforms' );					
					if(!empty($categoryId['term_id'])) {
						$allCategories[] = (int)$categoryId['term_id'];
					}
					else {
						$insertedPlatforms = wp_insert_term( $termName, 'platforms' );
						$allCategories[] = (int)$insertedPlatforms['term_id'] ;
					}

					$html .= '<h2 class="heading heading_2 game__block-title game__block-title_inner">System requirements for <span class="game__system-requirement-title">'.$termName.'</span></h2>';

					if( !empty($pp['requirements']['minimum']) ) {
						$html .= '<div>'.$pp['requirements']['minimum'].'</div>';
					} else {
						$html .= 'No minimum requirements needed';
					}

					if( !empty($pp['requirements']['recommended']) ) {
						$html .= '<div>'.$pp['requirements']['recommended'].'</div>';
					} else {
						$html .= 'Nothing to recommended';
					}
				}
				$html .= '</div>';
				wp_set_object_terms( $newPostId, $allCategories, 'platforms', false );				
				$customFields['platforms__platform__name'] = $html;
			}

			// If parent_platforms
			if ( !empty($data['stores']) ) {
				update_post_meta( $newPostId, '_available_stores', json_encode($data['stores']) );
				// foreach ($data['stores'] as $i => $store) {
				// 	$customFields['stores__url'] = $store['url'];
				// 	$customFields['stores__store__domain'] = $store['store']['domain'];
				// 	$customFields['stores__store__name'] = $store['store']['name'];
				// }
			}

			// If developers
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

			// If publishers
			if ( !empty($data['publishers']) ) {
				foreach ($data['publishers'] as $i => $publisher) {
					$customFields['publishers__name'] = $publisher['name'];
					$customFields['publishers__games_count'] = $publisher['games_count'];
				}
			}

			// If genres need link
			if ( !empty($data['genres']) ) {
				$pps = '';
				$allCategories = array();
				foreach ($data['genres'] as $i => $pp) {
					$termName = $pp['name'];

					// Checking if genres exists
					$categoryId = term_exists( $termName, 'genres' );					
					if(!empty($categoryId['term_id'])) {
						$allCategories[] = (int)$categoryId['term_id'];
					}
					else {
						$insertedPlatforms = wp_insert_term( $termName, 'genres' );
						$allCategories[] = (int)$insertedPlatforms['term_id'] ;
					}

					// // Checking if category exists if not then insert default wp category
					// $categoryId = term_exists( $pp['name'], 'category' );					
					// if(!empty($categoryId['term_id'])) {
					// 	$allCategories[] = $categoryId['term_id'];
					// }
					// else {
					// 	$allCategories[] = wp_insert_category( array('cat_name' => $pp['name']) );
					// }

					// if($i == 0)
					// 	$pps .= $pp['name'];
					// else
					// 	$pps .= ', '.$pp['name'];					
				}				
				// wp_set_post_categories( $newPostId, $allCategories, true );
				wp_set_object_terms( $newPostId, $allCategories, 'genres', false );				
				// $customFields['genres__name'] = $pps;
			}

			// If parent_platforms
			if ( !empty($data['tags']) ) {
				$pps = '';
				$allTags = array();
				foreach ($data['tags'] as $i => $pp) {
					$allTags[] = $pp['name'];
					if($i == 0)
						$pps .= $pp['name'];
					else
						$pps .= ', '.$pp['name'];					
				}
				// wp_set_post_tags( $newPostId, $allTags, true );
				$customFields['tags__name'] = $pps;
			}

			// Storing background image in Advanced Custom Field
			if( !empty($data['background_image']) ) {
				$attachId = saveRemoteUrl($data['background_image'] , $data['slug']);				
				if($attachId) {
					$customFields['background_image'] = $attachId;
				}
			}

			// Storing background image in Advanced Custom Field
			if( !empty($data['background_image_additional']) ) {
				$nattachId = saveRemoteUrl($data['background_image_additional'] , $data['slug']);
				if($nattachId) {
					$customFields['background_image_additional'] = $nattachId;
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

// function fetchNext($data, $index, $totalItems) {
// 	$index++;
// 	echo 'Looping ==>'.$index.'/'.$totalItems.'<br/>';
// 	if( $index < $totalItems ) {
// 		$gameInfo = $data['results'][$index];
// 		$postType = 'games';
// 		$minimumRatingsCount = 30;
		
// 		// Setting the custom filters because some paramters are not present in api
// 		if( !empty($gameInfo['ratings_count']) && $gameInfo['ratings_count'] > $minimumRatingsCount ) {

// 			// Checking if post with same slug is present in db or not
// 			$dbPosts = get_posts(array(
// 			  'name'        => $gameInfo['slug'],
// 			  'post_type'   => $postType,
// 			  'post_status' => array('draft', 'publish'),
// 			  'numberposts' => 1
// 			));		

// 			// If post not exists then get call game detail api do insert in db
// 			if( count($dbPosts) == 0 ) {
// 				echo "<p>Post Not exists</p>";
// 				$insertedIds[] = set_game_detail($gameInfo['id']);
// 				fetchNext($data, $index, $totalItems);
// 			}
// 			else {
// 				echo "<p>Post exists</p>";
// 				$insertedIds[] = $gameInfo['slug'].' already present';					
// 				fetchNext($data, $index, $totalItems);	
// 			}
			
// 		}
// 		else {
// 			$message = $gameInfo['slug'].' ratings_count is smaller than '.$minimumRatingsCount;
// 			echo $message;
// 			$insertedIds[] = $message;
// 			fetchNext($data, $index, $totalItems);			
// 		}
// 	}
// 	else {
// 		echo "<p>Iteration completed</p>";
// 		// echo '<pre>';
// 		// print_r($insertedIds);
// 		// echo '</pre>';
// 		// Iteration completed
// 		// return $insertedIds;
// 	}
// }