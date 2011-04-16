<?php
/*
Plugin Name: User Specific Content
Plugin URI: http://en.bainternet.info
Description: This Plugin allows you to select specific users by user name, or by role name who can view a  specific post content or page content.
Version: 0.4
Author: Bainternet
Author URI: http://en.bainternet.info
*/

/* Define the custom box */
add_action('add_meta_boxes', 'User_specific_content_box');

/* Adds a box to the main column on the custom post type edit screens */
function User_specific_content_box() {
    add_meta_box('User_specific_content', __( 'User specific content box'),'User_specific_content_box_inner','post');
	add_meta_box('User_specific_content', __( 'User specific content box'),'User_specific_content_box_inner','page');
}

/* Prints the box content */
function User_specific_content_box_inner() {
	global $post,$wp_roles;
	$savedroles = get_post_meta($post->ID, 'U_S_C_roles',true);
	//var_dump($savedroles);
	$savedusers = get_post_meta($post->ID, 'U_S_C_users',true);
	$savedoptions = get_post_meta($post->ID, 'U_S_C_options',true);
	//var_dump($savedusers);
	// Use nonce for verification
	wp_nonce_field( plugin_basename(__FILE__), 'User_specific_content_box_inner' );
	//by role
	echo __('Select users to show this content to');
	echo '<h4>'.__('By User Role:').'</h4>';
	if ( !isset( $wp_roles ) )
		$wp_roles = new WP_Roles();
	foreach ( $wp_roles->role_names as $role => $name ) {
		echo '<input type="checkbox" name="U_S_C_roles[]" value="'.$name.'"';
		if (in_array($name,$savedroles)){
			echo ' checked';
		}
		echo '>'.$name.'    ';
	}
	//by user
	echo '<h4>'.__('By User Name:').'</h4>';
	$blogusers = get_users('blog_id=1&orderby=nicename');
    $usercount = 0;
	foreach ($blogusers as $user) {
		echo '<input type="checkbox" name="U_S_C_users[]" value="'.$user->ID.'"';
		if (in_array($user->ID,$savedusers)){
			echo ' checked';
		}
		echo '>'.$user->display_name.'    ';
		$usercount = $usercount + 1;
		if ($usercount > 5){
			echo '<br/>';
			$usercount = 0;
		}
    }
	//other_options
	//logeed-in only
	echo '<h4>'.__('logged in users only:').'</h4>';
	echo '<input type="checkbox" name="U_S_C_options[logged]" value="1"';
	if (isset($savedoptions['logged']) && $savedoptions['logged'] == 1){
		echo ' checked'; 
	}
	echo '>If this box is check then content will show only to logged-in users and everyone else will get the blocked massage';
	//none logged-in
	echo '<h4>'.__('None logged in users only:').'</h4>';
	echo '<input type="checkbox" name="U_S_C_options[non_logged]" value="1"';
	if (isset($savedoptions['non_logged']) && $savedoptions['non_logged'] == 1){
		echo ' checked'; 
	}
	echo '>If this box is check then content will show only to none logged-in visitors and everyone else will get the blocked massage';
	echo '<h4>'.__('Content Blocked message:').'</h4>';
	echo '<textarea rows="3" cols="70" name="U_S_C_message" id="U_S_C_message">'.get_post_meta($post->ID, 'U_S_C_message',true).'</textarea><br/>'.__('This message will be shown to anyone who is not on the list above.');
}
 
 
/* Save Meta Box */
add_action('save_post', 'User_specific_content_box_inner_save');

/* When the post is saved, saves our custom data */
function User_specific_content_box_inner_save( $post_id ) {
	global $post;
	  // verify this came from the our screen and with proper authorization,
	  // because save_post can be triggered at other times

	  if ( !wp_verify_nonce( $_POST['User_specific_content_box_inner'], plugin_basename(__FILE__) ) )
		  return $post_id;

	  // verify if this is an auto save routine. 
	  // If it is our form has not been submitted, so we dont want to do anything
	  if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		  return $post_id;
	  // OK, we're authenticated: we need to find and save the data
	$savedroles = get_post_meta($post_id, 'U_S_C_roles',true);
	$savedusers = get_post_meta($post_id, 'U_S_C_users',true);
	$savedoptions = get_post_meta($post->ID, 'U_S_C_options',true);
	
	if (isset($_POST['U_S_C_options']) && !empty($_POST['U_S_C_options'] )){
		foreach ($_POST['U_S_C_options'] as $key => $value ){
			$new_savedoptions[$key] = $value;
		}
		update_post_meta($post_id, 'U_S_C_options', $new_savedoptions);
	}else{
		 delete_post_meta($post_id, 'U_S_C_options');
	}
	if (isset($_POST['U_S_C_roles']) && !empty($_POST['U_S_C_roles'] )){
		foreach ($_POST['U_S_C_roles'] as $role){
			$new_roles[] = $role;
		}
		update_post_meta($post_id, 'U_S_C_roles', $new_roles);
	}else{
		if (count($savedroles) > 0){
			 delete_post_meta($post_id, 'U_S_C_roles');
		}
	}
	if (isset($_POST['U_S_C_users']) && !empty($_POST['U_S_C_users'])){
		foreach ($_POST['U_S_C_users'] as $u){
			$new_users[] = $u;
		}
		update_post_meta($post_id, 'U_S_C_users', $new_users);
	}else{
		if (count($savedusers) > 0){
			 delete_post_meta($post_id, 'U_S_C_users');
		}
	}
	if (isset($_POST['U_S_C_message'])){
		update_post_meta($post_id,'U_S_C_message', $_POST['U_S_C_message']);
	}
}

add_filter('the_content','User_specific_content_filter');
function User_specific_content_filter($content){
	global $post,$current_user;
	$savedoptions = get_post_meta($post->ID, 'U_S_C_options',true);
	if (isset($savedoptions) && !empty($savedoptions)){
		// none logged only
		if (isset($savedoptions['non_logged']) && $savedoptions['non_logged'] == 1){
			if (is_user_logged_in()){
				return get_post_meta($post->ID, 'U_S_C_message',true);
				exit;
			}
		}
		//logged in users only
		if (isset($savedoptions['logged']) && $savedoptions['logged'] == 1){
			if (!is_user_logged_in()){
				return get_post_meta($post->ID, 'U_S_C_message',true);
				exit;
			}
		}
	}
	$savedroles = get_post_meta($post->ID, 'U_S_C_roles',true);
	$run_check = 0;
	$savedusers = get_post_meta($post->ID, 'U_S_C_users',true);
	if (!count($savedusers) > 0 && !count($savedroles) > 0 ){
		return $content;
		exit;
	}
	//by role
	if (isset($savedroles) && !empty($savedroles)){
		get_currentuserinfo();
		$cu_r = bausp_get_current_user_role();
		if ($cu_r){
			if (in_array($cu_r,$savedroles)){
				return $content;
				exit;
			}
		}else{
			//failed role check
		$run_check = 1;
		}
		/*foreach ($savedroles as $role){
			if (current_user_can($role)) {
				return $content;
				exit;
			}
		}
		//failed role check
		$run_check = 1;
		*/
	}
	
	//by user
	if (isset($savedusers) && !empty($savedusers)){
		get_currentuserinfo();
		if (in_array($current_user->ID,$savedusers)){
			return $content;
		}
		else{
			$run_check = $run_check + 1;
		}
			//failed both checks
		return get_post_meta($post->ID, 'U_S_C_message',true);
	}
	if ($run_check > 0){
		return get_post_meta($post->ID, 'U_S_C_message',true);
	}
	return $content;
}

/************************
* helpers
************************/

function bausp_get_current_user_role() {
	global $wp_roles;
	$current_user = wp_get_current_user();
	$roles = $current_user->roles;
	$role = array_shift($roles);
	return isset($wp_roles->role_names[$role]) ? translate_user_role($wp_roles->role_names[$role] ) : false;
}
?>