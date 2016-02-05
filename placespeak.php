<?php
/**
 * @package PlaceSpeak
 * @version 1.0
 */
/*
Plugin Name: PlaceSpeak
Plugin URI: http://wordpress.org/plugins/placespeak/
Description: This plugin allows organizations with PlaceSpeak Connect accounts on Placespeak.com to use geoverification tools on their Wordpress pages.
Author: Victor Temprano / PlaceSpeak
Version: 1.0
Author URI: http://tempranova.com
*/

/*

This plugin creates a new table on install. This table contains app identifiers and other information.

*/

/** SETTING DATABASE TABLES */
global $placespeak_db_version;
$placespeak_db_version = '1.3';

function placespeak_install() {
	global $wpdb;
	global $placespeak_db_version;

    // Create table for holding apps and app information
	$table_name = $wpdb->prefix . 'placespeak';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		app_name varchar(50) NOT NULL,
		client_key varchar(50) NOT NULL,
		client_secret varchar(50) NOT NULL,
		redirect_uri varchar(200) NOT NULL,
        archived boolean not null default 0,
		UNIQUE KEY id (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
    
    // Here is where you can do updates on a version change, before you set the option
    add_option( 'placespeak_db_version', $placespeak_db_version);
    // Default user WP_USERS for user storage
    add_option( 'placespeak_user_storage', 'WP_USERS');
}
/* Initial data install */
function placespeak_install_data() {
	global $wpdb;
    
	$table_name = $wpdb->prefix . 'placespeak';
    
    $client_info = $wpdb->get_results("SELECT * FROM " . $table_name); 
    if(count($client_info) == 0 ) {
        $welcome_app = 'Sample App Name';
        $welcome_key = 'No app key entered.';
        $welcome_secret = 'No app secret entered.';
        $welcome_redirect = 'No redirect entered.';

        $table_name = $wpdb->prefix . 'placespeak';

        $wpdb->insert( 
            $table_name, 
            array( 
                'time' => current_time( 'mysql' ), 
                'app_name' => $welcome_app, 
                'client_key' => $welcome_key,  
                'client_secret' => $welcome_secret, 
                'redirect_uri' => $welcome_redirect
            ) 
        );
    }
}

register_activation_hook( __FILE__, 'placespeak_install' );
register_activation_hook( __FILE__, 'placespeak_install_data' );

// This is for the radio button on the settings page, that allows choice on how to store users
function choose_placespeak_user_table() {
	if ( isset( $_POST['choose-placespeak-user-table'] ) ) {
       $user_storage = $_POST['user_storage'];
        
       if($user_storage == 'WP_USERS') {
           update_option( 'placespeak_user_storage', 'WP_USERS');
       }
       if($user_storage == 'PS_USERS') {
           update_option( 'placespeak_user_storage', 'PS_USERS');
           // Check for table and maybe create it
           global $wpdb;
           $table_name = $wpdb->prefix . 'placespeak_users';
           if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                 //table not in database. Create new table
                 $charset_collate = $wpdb->get_charset_collate();

                 $sql = "CREATE TABLE $table_name (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                    user_id varchar(50) NOT NULL,
                    first_name varchar(50) NOT NULL,
                    last_name varchar(50) NOT NULL,
                    geo_labels varchar(200) NOT NULL,
                    verifications varchar(200) NOT NULL,
                    access_token varchar(500) NOT NULL,
                    refresh_token varchar(500) NOT NULL,
                    authorized_client_key varchar(500) NOT NULL,
                    UNIQUE KEY id (id)
                 ) $charset_collate;";
                 require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                 dbDelta( $sql );
           }
       }
    }
}
choose_placespeak_user_table();

/** ADDING MENU ITEM to WP */
add_action( 'admin_menu', 'my_plugin_menu' );
function my_plugin_menu() {
	add_options_page( 'PlaceSpeak Options', 'PlaceSpeak', 'manage_options', 'placespeak', 'my_plugin_options' );
}
/* Adding administration page */
function my_plugin_options() {
    
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
    // Get plugin data from DB and display
    global $wpdb;

	$table_name = $wpdb->prefix . 'placespeak';
    
    $client_info = $wpdb->get_results("SELECT * FROM " . $table_name); 
    
    // Get count of archived vs published
    $published_count = 0;
    $archived_count = 0;
    for ($i=0;$i<count($client_info); ++$i) {
        if($client_info[$i]->archived!=='0') {
            $archived_count += 1;
        } else {
            $published_count += 1;
        }
    }
    
    // Checking query string to see if there is a filter on app display
    $current_app_status_display = '';
    $query_strings = $_SERVER['QUERY_STRING'];
    $all_query_strings_array = explode("&", $query_strings);
    for($i=0;$i<count($all_query_strings_array);++$i) {
        $each_query_strings_array = explode("=", $all_query_strings_array[$i]);
        if($each_query_strings_array[0]=='app_status') {
            $current_app_status_display = $each_query_strings_array[1];
        }
    }
    
    // Seeing which option the user currently has set for saving user
    $user_storage = get_option('placespeak_user_storage');
    ?>

    <style>
        .inline-edit-row fieldset label span.title {
            width: 10em;
        }
        .inline-edit-row fieldset label span.input-text-wrap {
            margin-left: 10em;
        }
        .widefat th.check-column {
            text-align: center;
        }
        .submitLink {
          background-color: transparent;
          text-decoration: none;
          border: none;
          color: #a00;
          cursor: pointer;
          font-size: 13px;
        }
        .submitLink:hover {
          color: red;
        }

        .submitLink:focus {
          outline: none;
        }
    </style>
    
	<div class="wrap">
        <h3>Options</h3>
        <form action="" method="post">
            <input type="radio" name="user_storage" <?php if($user_storage == 'WP_USERS') echo "checked"; ?> value="WP_USERS" />Use default WP_USERS to store verified user information (<strong>default</strong>).
            <br>
            <input type="radio" name="user_storage" <?php if($user_storage == 'PS_USERS') echo "checked"; ?> value="PS_USERS" />Use custom PlaceSpeak user table to store verified user information.
            <br>
            <input type="submit" name="choose-placespeak-user-table" value="Save">
        </form>
        <h3>Add New PlaceSpeak App</h3>
        <form action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ) ?>" method="post">
            <table>
                <tr><td>App Name</td><td><input type="text" name="app-name" placeholder="App name"></td></tr>
                <tr><td>App Key</td><td><input type="text" name="app-key" placeholder="App Key"></td></tr>
                <tr><td>App Secret</td><td><input type="text" name="app-secret" placeholder="App Secret"></td></tr>
                <tr><td>Redirect URI</td><td><input type="text" name="redirect-url" placeholder="Redirect URL" value="<?php echo site_url(); ?>"> <small>This should be where your Wordpress is installed.</small></td></tr>
            </table>
            <input type="submit" name="add-new-app">
        </form>
        
        <h3>Current Apps</h3>
        <ul class="subsubsub">
            <li class="all"><a href="options-general.php?page=placespeak&app_status=all" <?php if($current_app_status_display=='all'||!$current_app_status_display) { echo 'class="current"'; } ?>>All <span class="count">(<?php echo ($published_count+$archived_count); ?>)</span></a> |</li>
            <li class="publish"><a href="options-general.php?page=placespeak&app_status=published" <?php if($current_app_status_display=='published') { echo 'class="current"'; } ?>>Published <span class="count">(<?php echo $published_count; ?>)</span></a> |</li>
            <li class="draft"><a href="options-general.php?page=placespeak&app_status=archived" <?php if($current_app_status_display=='archived') { echo 'class="current"'; } ?>>Archived <span class="count">(<?php echo $archived_count; ?>)</span></a></li>
        </ul>
        <table class="wp-list-table widefat fixed pages">
            <thead>
                <tr>
                    <th scope="row" class="check-column">ID</th>
                    <th class="manage-column column-author" id="author" scope="col"
                    style="">App Name</th>
                    <th class="manage-column column-author" id="author" scope="col"
                    style="">App key</th>
                    <th class="manage-column column-author" id="author" scope="col"
                    style="">App secret</th>
                    <th class="manage-column column-date sortable asc" id="date"
                    scope="col" style="">
                        <span>Redirect URI</span><span class=
                        "sorting-indicator"></span>
                    </th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php for ($i=0;$i<count($client_info); ++$i) { ?>
                    <?php if(($current_app_status_display=='published'&&$client_info[$i]->archived=='0')||($current_app_status_display=='archived'&&$client_info[$i]->archived=='1')||$current_app_status_display=='all'||!$current_app_status_display) { ?>
                        <tr <?php if($client_info[$i]->archived!=='0') { echo 'style="background-color: #fefefe;"'; } ?> class=
                        "post-<?php echo $client_info[$i]->id ?> type-page status-publish hentry alternate iedit author-self level-0"
                        id="post-<?php echo $client_info[$i]->id ?>">
                            <th scope="row" class="check-column"><?php echo $client_info[$i]->id ?></th>
                            <td class="post-title page-title column-title">
                                <strong>
                                    <?php echo $client_info[$i]->app_name ?>
                                </strong>
                                <div class="row-actions" style="visibility:visible;">
                                    <span class="edit">
                                        <span class="inline">
                                            <a style="cursor:pointer;" class="editinline" id="editapp-<?php echo $client_info[$i]->id ?>" title="Edit this item inline">Edit</a>
                                            <form action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ) ?>" method="post" style="display:inline;">
                                                <input id="app-id" name="app-id" type="hidden" value="<?php echo $client_info[$i]->id ?>"> 
                                                <?php if($client_info[$i]->archived!=='1') { ?>
                                                    <input type="submit" class="submitLink" name="archive-app" value="Archive">
                                                <?php } else { ?>
                                                    <input type="submit" class="submitLink" name="unarchive-app" value="Unarchive">
                                                <?php } ?>
                                            </form>
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td class="author column-author">
                                <?php echo $client_info[$i]->client_key ?> 
                            </td>
                            <td class="author column-author">
                                <?php echo $client_info[$i]->client_secret ?> 
                            </td>
                            <td class="author column-author">
                                <?php echo $client_info[$i]->redirect_uri ?> 
                            </td>
                        </tr>
                        <tr class="inline-edit-row inline-edit-row-page inline-edit-page quick-edit-row quick-edit-row-page inline-edit-page alternate inline-editor"
                        id="edit-<?php echo $client_info[$i]->id ?>" style="">
                            <td class="colspanchange" colspan="5">
                                <form action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ) ?>" method="post">
                                    <fieldset class="inline-edit-col-left">
                                        <div class="inline-edit-col">
                                            <h4>Edit</h4>
                                            <label>
                                                <span class="title">App name</span>
                                                <span class="input-text-wrap">
                                                    <input class="ptitle" name="app-name" type="text" value="<?php echo $client_info[$i]->app_name ?>">
                                                </span>
                                            </label>
                                            <label>
                                                <span class="title">App key</span>
                                                <span class="input-text-wrap">
                                                    <input class="ptitle" name="app-key" type="text" value="<?php echo $client_info[$i]->client_key ?>">
                                                </span>
                                            </label>
                                            <label>
                                                <span class="title">App secret</span>
                                                <span class="input-text-wrap">
                                                    <input class="ptitle" name="app-secret" type="text" value="<?php echo $client_info[$i]->client_secret ?>">
                                                </span>
                                            </label>
                                            <label>
                                                <span class="title">Redirect URI</span>
                                                <span class="input-text-wrap">
                                                    <input class="ptitle" name="redirect-url" type="text" value="<?php echo $client_info[$i]->redirect_uri ?> ">
                                                </span>
                                            </label>
                                        </div>
                                    </fieldset>
                                    <p class="submit inline-edit-save">
                                        <a accesskey="c" id="cancel-<?php echo $client_info[$i]->id ?>" class="button-secondary cancel alignleft">Cancel</a>
                                        <input id="app-id" name="app-id" type="hidden" value="<?php echo $client_info[$i]->id ?>"> 
                                        <input type="submit" class="button-primary save alignright" name="update-app">
                                        <span class="spinner"></span> 
                                        <br class="clear"></p>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        <h3>Basic shortcode embed</h3>
        <p>To embed a button that will allow users to sign in and verify themselves with PlaceSpeak, use the following shortcode: <strong>[placespeak_connect id="APP_ID"]</strong>.</p>
        <p>Example: [placespeak_connect id="1"]</p>
        <h3>Choosing a connect button</h3>
        <p>There are a number of different connect buttons you can use. They are listed below. To use them for an app, add the "button" parameter to your shortcode along with the colour description.</p>
        <p>Example: [placespeak_connect id="1" button="dark_blue"]</p>
        <table>
            <tr><td>Green (green)</td><td>Light Blue (light_blue)</td><td>Dark Blue (dark_blue)</td></tr>
            <tr><td><img src="<?php echo plugin_dir_url(__FILE__) ?>/img/connect_green.png"></td>
                <td><img src="<?php echo plugin_dir_url(__FILE__) ?>/img/connect_light_blue.png"></td>
                <td><img src="<?php echo plugin_dir_url(__FILE__) ?>/img/connect_dark_blue.png"></td>
            </tr>
        </table>
	</div>

<script>
    var idArray = [];
    
    // Create functions for each edit box
    var editButtons = document.getElementsByClassName('editinline');
    for(button in editButtons) {
        if(typeof editButtons[button].id!=='undefined') {
            var thisID = editButtons[button].id.substring(8);
            idArray.push(thisID);
        }
    }
    idArray.forEach(function(element,index,array) {
        var thisEditBox = document.getElementById('edit-'+element);
        var thisEditButton = document.getElementById('editapp-'+element);
        var thisCancelButton = document.getElementById('cancel-'+element);
        thisEditButton.addEventListener('click', function() {
            thisEditBox.style.display = '';
        });
        thisCancelButton.addEventListener('click', function() {
            thisEditBox.style.display = 'none';
        });
        // Hide edit boxes at start
        thisEditBox.style.display = 'none';
    });
    
</script>
<?php }

function add_new_app() {

	// if the submit button is clicked, send the email
	if ( isset( $_POST['add-new-app'] ) ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'placespeak';
        
		// sanitize form values
		$app_name        = sanitize_text_field( $_POST["app-name"] );
		$client_key      = sanitize_text_field( $_POST["app-key"] );
		$client_secret   = sanitize_text_field( $_POST["app-secret"] );
		$redirect_uri    = sanitize_text_field( $_POST["redirect-url"] );
    
        $wpdb->insert( 
            $table_name, 
            array( 
                'time' => current_time( 'mysql' ), 
                'app_name' => $app_name, 
                'client_key' => $client_key, 
                'client_secret' => $client_secret, 
                'redirect_uri' => $redirect_uri
            ) 
        );
    }
}
add_new_app();

/** Handling the form submission */
function update_app() {

	// if the submit button is clicked, send the email
	if ( isset( $_POST['update-app'] ) ) {

		// sanitize form values
		$app_id          = $_POST["app-id"];
		$app_name        = sanitize_text_field( $_POST["app-name"] );
		$client_key      = sanitize_text_field( $_POST["app-key"] );
		$client_secret   = sanitize_text_field( $_POST["app-secret"] );
		$redirect_uri    = sanitize_text_field( $_POST["redirect-url"] );
        
        global $wpdb;

        $table_name = $wpdb->prefix . 'placespeak';

        $wpdb->update( 
            $table_name, 
            array( 
                'time' => current_time( 'mysql' ), 
                'app_name' => $app_name, 
                'client_key' => $client_key, 
                'client_secret' => $client_secret, 
                'redirect_uri' => $redirect_uri, 
            ),
            array( 'ID' => $app_id ),
            array( 
                '%s',
                '%s',
                '%s',
                '%s'
            ),
            array( '%d' )
        );
	}
}
update_app();

// An extra value set to archived as 0 (false) or 1 (true)
/** Handling the form submission */
function archive_app() {

	// if the submit button is clicked, send the email
	if ( isset( $_POST['archive-app'] ) ) {

		// sanitize form values
		$app_id          = $_POST["app-id"];
        
        global $wpdb;

        $table_name = $wpdb->prefix . 'placespeak';

        $wpdb->update( 
            $table_name, 
            array( 
                'time' => current_time( 'mysql' ),
                'archived' => 1
            ),
            array( 'ID' => $app_id ),
            array( 
                '%d',
            ),
            array( '%d' )
        );
	}
}
archive_app();

// An extra value set to archived as 0 (false) or 1 (true)
/** Handling the form submission */
function unarchive_app() {

	// if the submit button is clicked, send the email
	if ( isset( $_POST['unarchive-app'] ) ) {

		// sanitize form values
		$app_id          = $_POST["app-id"];
        
        global $wpdb;

        $table_name = $wpdb->prefix . 'placespeak';

        $wpdb->update( 
            $table_name, 
            array( 
                'time' => current_time( 'mysql' ),
                'archived' => 0
            ),
            array( 'ID' => $app_id ),
            array( 
                '%d',
            ),
            array( '%d' )
        );
	}
}
unarchive_app();

// Enque scripts for the shortcode display
function placespeak_scripts() {
    wp_register_script( 'leaflet-js', plugin_dir_url(__FILE__) . '/js/leaflet.js', array('jquery'));
    wp_register_script( 'polyline-encoded-js', plugin_dir_url(__FILE__) . '/js/polyline.encoded.js', array('jquery','leaflet-js'));
	wp_register_script( 'placespeak-js', plugin_dir_url(__FILE__) . '/js/placespeak.js', array('jquery','leaflet-js','polyline-encoded-js'));

    wp_enqueue_style( 'leaflet-css', plugin_dir_url(__FILE__) . '/css/leaflet.css' );
	wp_enqueue_style( 'placespeak-css', plugin_dir_url(__FILE__) . '/css/placespeak.css' );
    
	wp_enqueue_script( 'leaflet-js', plugin_dir_url(__FILE__) . '/js/leaflet.js', array('jquery'),'0.7.7',false);
	wp_enqueue_script( 'polyline-encoded-js', plugin_dir_url(__FILE__) . '/js/polyline.encoded.js', array('leaflet-js'),'0.13',false);
	wp_enqueue_script( 'placespeak-js', plugin_dir_url(__FILE__) . '/js/placespeak.js', array('jquery','leaflet-js','polyline-encoded-js'),'1',true);
}

add_action( 'wp_enqueue_scripts', 'placespeak_scripts' );

// Shortcode for Connect button
function placespeak_connect_shortcode($atts) {
    // Get shortcode atts
    $shortcode_connect_atts = shortcode_atts( array(
        'id' => '1', // Default just pulls the first one
        'button' => 'green' // Default displays green button
    ), $atts );
    
    // Get plugin data from DB
    global $wpdb;

	$table_name = $wpdb->prefix . 'placespeak';
    
    $client_info = $wpdb->get_results("SELECT * FROM " . $table_name);

    // Do request to see if user is logged in and connected to an app
    /*
    $response = wp_remote_get( 'http://dev.placespeak.com/connect/testsignin/' );
    if( is_array($response) ) {
      $header = $response['headers']; // array of http header lines
      $body = $response['body']; // use the content
    }
    print_r($response);
    */
    $url = $_SERVER['REQUEST_URI'];
    $escaped_url = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
?>
    
    <div style="font-size:12px !important;">
<!--        <a onClick="openwindow('login')">Log In</a> | <a onClick="openwindow('signup')">Sign Up</a>-->
        <div id="placespeak_connect_button">
            <div style="margin-bottom:10px;">
                <a href="http://dev.placespeak.com/connect/authorize/?client_id=<?php echo $client_info[wp_kses_post($shortcode_connect_atts['id'])-1]->client_key ?>&response_type=code&scope=user_info&redirect_uri=<?php echo $client_info[wp_kses_post($shortcode_connect_atts['id'])-1]->redirect_uri ?>/wp-content/plugins/wp-placespeak-connect/oauth_redirect.php&state=<?php echo $escaped_url; ?>_<?php echo $shortcode_connect_atts['id']-1; ?>">
                    <img src="<?php echo plugin_dir_url(__FILE__); ?>/img/connect_<?php echo $shortcode_connect_atts['button']; ?>.png">
                </a>
            </div>
        </div>
        <input id="app_key" type="hidden" value="<?php echo $client_info[wp_kses_post($shortcode_connect_atts['id'])-1]->client_key ?>">
        <input id="url_directory" type="hidden" value="<?php echo plugin_dir_url(__FILE__) ?>">
        <div id="verified_by_placespeak" style="display:none;">
            <p>Your comment is verified by PlaceSpeak.<img id="placespeak_verified_question" src="<?php echo plugin_dir_url(__FILE__); ?>/img/question.png"</p>
            <div id="placespeak_verified_info" style="display:none;">
                <!-- <div id="placespeak_verified_info_triangle"></div> -->
                Because you have connected to this consultation using PlaceSpeak, PlaceSpeak will verify that your comment isn't spam and confirm your status as a resident (or not) of the consultation area. PlaceSpeak will not share any personal information, such as your address.
            </div>
        </div>
        <div id="placespeak_plugin_map" style="display:none;"></div>
        <div id="powered_by_placespeak" style="display:none;">
            <p>Powered by <img id="powered_by_placespeak_logo" src="<?php echo plugin_dir_url(__FILE__); ?>/img/placespeak_logo.png"></p>
        </div>
    </div>
<!--
    Keep this if we keep the sign up / log in buttons
    <script>
    function openwindow(type) {
        var width = window.innerWidth;
        var height = window.innerHeight;
        var left = (width / 2);
        var top = (height / 2);
        var url;
        var popupWidth;
        var popupHeight;
        if(type==='signup') {
            url = 'signup/';
            popupWidth = (screen.width*0.8)>1000 ? 1000 : (screen.width*0.8)
            popupHeight = (screen.height*0.7);
            left = screen.height*0.1;
            top = screen.height*0.1;
        } else {
            url = 'loginwidget/?next=/profile/';
            popupWidth = 500;
            popupHeight = 300;
        }
        var NewWindow = window.open('http://dev.placespeak.com/'+url,'mywindow','width='+popupWidth+',height='+popupHeight+',top='+top+',left='+left);
        NewWindow.onunload = refreshParent;
        function refreshParent() {
            NewWindow.opener.location.reload();
            NewWindow.onunload = refreshParent;
        }
    }
    </script>
-->

<?php }

add_shortcode('placespeak_connect', 'placespeak_connect_shortcode');
add_filter('widget_text', 'do_shortcode');

// Comment form hook, so it's added in a given post
// I could do this with an option check to add to all posts
// or allow it to be added to posts individually

// Note: this only works if you're using a theme with the hooks in the right places in the comments. This isn't all themes_api
add_action( 'comment_form_after_fields', 'placespeak_connect_field',10 );
function placespeak_connect_field() {
    // Gets placespeak_app_id out of the settings, if it's set
    $current_app_id = get_post_meta( get_the_ID(), 'placespeak_app_id', true);
    if($current_app_id) { 
        // Get plugin data from DB
        global $wpdb;

        $table_name = $wpdb->prefix . 'placespeak';

        $client_info = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE id = " . $current_app_id);
        
        $url = $_SERVER['REQUEST_URI'];
        $escaped_url = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );

    ?>
        <div style="font-size:12px !important;margin-bottom:20px;">
            <div id="placespeak_connect_button">
                <div style="margin-bottom:10px;">
                    <a href="http://dev.placespeak.com/connect/authorize/?client_id=<?php echo $client_info->client_key ?>&response_type=code&scope=user_info&redirect_uri=<?php echo $client_info->redirect_uri ?>/wp-content/plugins/wp-placespeak-connect/oauth_redirect.php&state=<?php echo $escaped_url; ?>_<?php echo $client_info->id; ?>">
                        <img src="<?php echo plugin_dir_url(__FILE__); ?>/img/connect_dark_blue.png">
                    </a>
                </div>
            </div>
            <input id="app_key" type="hidden" value="<?php echo $client_info->client_key ?>">
            <input id="url_directory" type="hidden" value="<?php echo plugin_dir_url(__FILE__) ?>">
            <div id="verified_by_placespeak" style="display:none;">
                <p>Your comment is verified by PlaceSpeak.<img id="placespeak_verified_question" src="<?php echo plugin_dir_url(__FILE__); ?>/img/question.png"</p>
                <div id="placespeak_verified_info" style="display:none;">
                    Because you have connected to this consultation using PlaceSpeak, PlaceSpeak will verify that your comment isn't spam and confirm your status as a resident (or not) of the consultation area. PlaceSpeak will not share any personal information, such as your address.
                </div>
            </div>
            <div id="placespeak_plugin_map" style="display:none;"></div>
            <div id="powered_by_placespeak" style="display:none;">
                <p>Powered by <img id="powered_by_placespeak_logo" src="<?php echo plugin_dir_url(__FILE__); ?>/img/placespeak_logo.png"></p>
            </div>
        </div>
    <?php 
    }
    
}

// This is where you select an app for your post or page
add_action( 'edit_form_after_editor', 'select_placespeak_app' );
function select_placespeak_app() {
    // Get plugin data from DB
    global $wpdb;

	$table_name = $wpdb->prefix . 'placespeak';
    
    $client_info = $wpdb->get_results("SELECT * FROM " . $table_name . " WHERE archived = 0");
    
    $post_id = $_GET['post'];
    $current_app_id = get_post_meta( $post_id, 'placespeak_app_id', true);
    if($current_app_id){
        $current_app = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE id = " . $current_app_id);
    }
    ?>
    <!-- Spit out a metabox; this is NOT a real metabox really -->
    <div id="select_placespeak_app" class="postbox " style="margin-top:40px;">
        <div class="handlediv" title="Click to toggle"><br></div>
        <h3 class="hndle ui-sortable-handle"><span>Select PlaceSpeak App</span></h3>
        <div class="inside">
            <?php if($current_app_id) { ?>
                <p>Current App: <strong><?php echo $current_app->app_name; ?></strong></p>
            <?php } ?>
            <label class="screen-reader-text" for="placespeak_app_id">App for this post/page</label>
            <select name="placespeak_app_id" id="placespeak_app_id">
                <option value="">(no app)</option>
                <!-- Some fiddling here to make sure the options come out correctly -->
                <?php for ($i=0;$i<count($client_info); ++$i) { ?>
                    <option class="level-0" value="<?php echo $client_info[$i]->id ?>" <?php if($client_info[$i]->id==$current_app_id) { echo "selected='selected'"; } ?>><?php echo $client_info[$i]->app_name; ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
<?php 
}

// It saves the placespeak_app_id when someone presses "Update" or "Save"
add_action( 'save_post', 'save_placespeak_app_info' );
function save_placespeak_app_info( $post_id ) {
    update_post_meta( $post_id, 'placespeak_app_id', sanitize_text_field( $_REQUEST['placespeak_app_id'] ) );
}

// This modified the comment save, so it also saves meta information with the hidden field
add_action( 'comment_post', 'save_user_comment_information' );
function save_user_comment_information($comment_id) {
    // If it has this input field, then it's been verified
    if($_POST['placespeak_verifications']) {
        add_comment_meta( $comment_id, 'placespeak_verified_user', $_POST['placespeak_user_id'] );
        add_comment_meta( $comment_id, 'placespeak_user_name', $_POST['placespeak_user_name'] );
        add_comment_meta( $comment_id, 'placespeak_user_verifications', $_POST['placespeak_verifications'] );
        add_comment_meta( $comment_id, 'placespeak_geo_labels', $_POST['placespeak_geo_labels'] );
            
    }
}
// Adding a column to "comments" that shows whether comment is verified or not
add_filter('manage_edit-comments_columns', 'add_new_comments_columns');
function add_new_comments_columns($comments_columns) {
    $comments_columns['placespeak_verified'] = 'PlaceSpeak Verified';
    $comments_columns['placespeak_user_name'] = 'PlaceSpeak User Name';
    $comments_columns['placespeak_region'] = 'PlaceSpeak App Region';
    return $comments_columns;
}
// Populating that column
add_action('manage_comments_custom_column','manage_comments_columns',10,2);
function manage_comments_columns($column_name, $id) {
    switch ($column_name) {
        case 'placespeak_verified':
            $user_id = get_comment_meta($id,'placespeak_verified_user',true);
            $verifications = get_comment_meta($id,'placespeak_user_verifications',true);
            $json_verifications = json_decode($verifications);
            if($user_id) {
                foreach($json_verifications as $key=>$verification_level) {
                    if($verification_level == 'True') {
                        echo ucfirst($key) . ': <img style="width:15px;" src="' . plugin_dir_url(__FILE__) . '/img/verified_checkbox.png""><br>';
                    } else {
                        echo ucfirst($key) . ': Not verified<br>';
                    }
                }
            }
            break;
        case 'placespeak_user_name':
            $user_name = get_comment_meta($id,'placespeak_user_name',true);
            if($user_name) {
                echo $user_name;
            }
            break;
        case 'placespeak_region':
            $user_geo_labels = get_comment_meta($id,'placespeak_geo_labels',true);
            if($user_geo_labels) {
                echo $user_geo_labels;
            } else {
                if(get_comment_meta($id,'placespeak_verified_user',true)) {
                    echo "User is not in this app's regions.";
                }
            }
            break;
        default:
            break;
    }
}

?>
