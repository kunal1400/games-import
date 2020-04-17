<?php 
/**
* Registering the custom posttype for games
**/
add_action( 'init', 'rawg_games_posttype_callback' );
function rawg_games_posttype_callback() {
	$labels = array(
		'name'               => _x( 'Games', 'post type general name', 'your-plugin-textdomain' ),
		'singular_name'      => _x( 'Game', 'post type singular name', 'your-plugin-textdomain' ),
		'menu_name'          => _x( 'Games', 'admin menu', 'your-plugin-textdomain' ),
		'name_admin_bar'     => _x( 'Game', 'add new on admin bar', 'your-plugin-textdomain' ),
		'add_new'            => _x( 'Add New', 'Game', 'your-plugin-textdomain' ),
		'add_new_item'       => __( 'Add New Game', 'your-plugin-textdomain' ),
		'new_item'           => __( 'New Game', 'your-plugin-textdomain' ),
		'edit_item'          => __( 'Edit Game', 'your-plugin-textdomain' ),
		'view_item'          => __( 'View Game', 'your-plugin-textdomain' ),
		'all_items'          => __( 'All Games', 'your-plugin-textdomain' ),
		'search_items'       => __( 'Search Games', 'your-plugin-textdomain' ),
		'parent_item_colon'  => __( 'Parent Games:', 'your-plugin-textdomain' ),
		'not_found'          => __( 'No Game found.', 'your-plugin-textdomain' ),
		'not_found_in_trash' => __( 'No Game found in Trash.', 'your-plugin-textdomain' )
	);

	$args = array(
		'labels'             => $labels,
		'description'        => __( 'Description.', 'your-plugin-textdomain' ),
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'menu_icon' => 'dashicons-awards'
	);

	register_post_type( 'games', $args );
}


function rawg_games_register_settings() {
   add_option( 'rawg_games_import_started', 0);
   register_setting( 'rawg_games_options_group', 'rawg_games_import_started', 'rawg_games_callback' );
}
add_action( 'admin_init', 'rawg_games_register_settings' );

function rawg_games_register_options_page() {
  add_options_page('Rawg Settings', 'Rawg Settings', 'manage_options', 'rawg_games', 'rawg_games_options_page');
}
add_action('admin_menu', 'rawg_games_register_options_page');

function rawg_games_options_page() { ?>
  <div>
	  <?php screen_icon(); ?>
	  <h2>Rawg API Settings</h2>
	  <hr/>
	  <form method="post" action="options.php">
		  <?php settings_fields( 'rawg_games_options_group' ); ?>
		  <!-- <h3>This is my option</h3> -->
		  <!-- <p>Some text here.</p> -->
		  <table>
			  <tr valign="top">
				  <th scope="row">
				  	<label for="rawg_games_import_started">Start pulling data from API</label>
				  </th>
				  <td>
				  	<select name="rawg_games_import_started">
				  		<option <?php echo get_option('rawg_games_import_started') == "no" ? "selected" : "" ?> value="no">No</option>
				  		<option <?php echo get_option('rawg_games_import_started') == "yes" ? "selected" : "" ?> value="yes">Yes</option>
				  	</select>
				  </td>
			  </tr>
		  </table>
	  <?php submit_button(); ?>
	  </form>
  </div>
<?php
}