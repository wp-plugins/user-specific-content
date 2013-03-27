<?php
/*
Plugin Name: User Specific Content
Plugin URI: http://en.bainternet.info
Description: This Plugin allows you to select specific users by user name, or by role name who can view a  specific post content or page content.
Version: 0.9.7
Author: Bainternet
Author URI: http://en.bainternet.info
*/
/*
		* 	Copyright (C) 2011  Ohad Raz
		*	http://en.bainternet.info
		*	admin@bainternet.info

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
		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Disallow direct access to the plugin file */
if (basename($_SERVER['PHP_SELF']) == basename (__FILE__)) {
	die('Sorry, but you cannot access this page directly.');
}

class bainternet_U_S_C {

	// Class Variables
	/**
	 * used as localiztion domain name
	 * @var string
	 */
	var $localization_domain = "bauspc";
	
	
	/**
	 * Class constarctor
	 */
    function __construct() {
		/* Define the custom box */
		add_action('add_meta_boxes', array($this,'User_specific_content_box'));
		/* Save Meta Box */
		add_action('save_post', array($this,'User_specific_content_box_inner_save'));
		/* add shortcodes */
		add_shortcode('O_U',array($this,'User_specific_content_shortcode'));
		/* options page */
		add_action('admin_menu', array($this,'admin_menu'));
        add_action('admin_init',  array($this, 'U_S_C_admin_init'));
		/* add_filter hooks */
		add_action('init',  array($this, 'U_S_C_init'));
		
		//Language Setup
		$locale = get_locale();
		load_plugin_textdomain( $this->localization_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		
    }
	
	//init
	public function U_S_C_init(){
		$options = $this->U_S_C_get_option();
		if ($options['run_on_the_content']){
			/* hook the_content to filter users */
			
			add_filter('the_content',array($this,'User_specific_content_filter'));
		}
		if ($options['run_on_the_excerpt']){
		
			/* hook the_excerpt to filter users */
			add_filter('the_excerpt',array($this,'User_specific_content_filter'));
		}
		//allow other filters
		do_action('User_specific_content_filter_add',$this);
	}
	
	
	//admin init
	public function U_S_C_admin_init(){
		register_setting( 'U_S_C_Options', 'U_S_C',array($this,'U_S_C_validate_options'));
		$this->U_S_C_get_option();
	}
	
	function U_S_C_validate_options($i){
		return $i;
	}
	
	
	//admin menu
	public function admin_menu() {
		add_options_page('User Specific Content', 'User Specific Content', 'manage_options', 'ba_U_S_C', array($this,'U_S_C_options'));
	}
	
	//options page
	public function U_S_C_options(){
		
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
		//print_r($_POST);
		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32">
			<?php if (isset($_POST['Update_data'])){echo 'good'; }?>
			</div><h2><a href="http://en.bainternet.info">Bainternet</a> <?php echo __(' User Specific Content','bauspc'); ?></h2>
			<h3><?php echo __('General settings:','bauspc'); ?></h3>
			<form method="post" action="options.php">
			<?php settings_fields('U_S_C_Options');
				$options = $this->U_S_C_get_option();
			?>
			<?php  //print_r($options); ?>

			<table class="form-table">

			<tr valign="top">
			<th scope="row"><?php echo __('Global Blocked message:','bauspc'); ?></th>
			<td><textarea type="text" name="U_S_C[b_massage]" ><?php echo $options['b_massage']; ?></textarea><br /> 
			<?php _e('<small>(if set in a metabox the it overwrites this message for that secific post/page)</small>','bauspc'); ?></td>
			</tr>
			

			<tr valign="top">
			<th scope="row"><?php echo __('Use with "the_content" hook?','bauspc'); ?></th>
			<td><input type="checkbox" name="U_S_C[run_on_the_content]" value="true" <?php echo ($options['run_on_the_content']) ? 'checked="checked"': ''; ?>" /><br /> 
			<?php _e('<small>(default checked)</small>','bauspc'); ?></td>
			</tr>
			
			<tr valign="top">
			<th scope="row"><?php echo __('Use with "the_excerpt" hook?','bauspc'); ?></th>
			<td><input type="checkbox" name="U_S_C[run_on_the_excerpt]" value="true" <?php echo ($options['run_on_the_excerpt']) ? 'checked="checked"': ''; ?>" /><br /> 
			<?php _e('<small>(check to make plugin run on archive / tags / category pages default unchecked)</small>','bauspc'); ?></td>
			</tr>
			</table>
			<h3><?php echo __('MetaBox settings:','bauspc'); ?></h3>
			<table class="form-table">
			<tr valign="top">
			<th scope="row"><?php echo __('list user names? ','bauspc'); ?></th>
			<td><input type="checkbox" name="U_S_C[list_users]" value="true" <?php echo ($options['list_users']) ? 'checked="checked"': ''; ?>" /><br /> 
			<?php _e('<small>(default checked) sites with a large number of users should uncheck this option</small>','bauspc'); ?></td>
			</tr>
			<tr valign="top">
			<th scope="row"><?php echo __('list user roles?','bauspc'); ?></th>
			<td><input type="checkbox" name="U_S_C[list_roles]" value="true" <?php echo ($options['list_roles']) ? 'checked="checked"': ''; ?>" /><br /> 
			<?php _e('<small>(default checked) sites with a large number of roles should uncheck this option</small>','bauspc'); ?></td>
			</tr>

			</table>
			<div>
				<?php $this->credits(); ?>
				<?php echo '<h3>New Feature</h3><p>Since version 0.7 you can use a shortcode <pre>[U_O]</pre> which accepts the following parameters: </p><ul>';
			echo '<li>user_id - specific user ids form more then one separate by comma</li>
			<li>user_name - specific user names form more then one separate by comma</li>
			<li>user_role - specific user role form more then one separate by comma</li>
			<li>blocked_message - specific Content Blocked message</li></ul><p>eg:</p><pre>[O_U user_role="Administrator" blocked_message="admins only!"]admin content goes here[/O_U]</pre>';
			?>
			</div>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes','bauspc'); ?>" />
			</p>
			</form>
		</div>
		<?php
	}
	
	//options
	public function U_S_C_get_option(){
		$temp = array(
		'b_massage' => '',
		'list_users' => true,
		'list_roles' => true,
		'run_on_the_content' => true,
		'run_on_the_excerpt' => false
		);
		
		$i = get_option('U_S_C');
		if (!empty($i)){
			if (isset($i['run_on_the_content']) && $i['run_on_the_content']){
				$temp['run_on_the_content'] = true;
			}else{
				$temp['run_on_the_content'] = false;
			}
			
			if (isset($i['run_on_the_excerpt']) && $i['run_on_the_excerpt']){
				$temp['run_on_the_excerpt'] = true;
			}else{
				$temp['run_on_the_excerpt'] = false;
			}
			
			if (isset($i['list_users']) && $i['list_users']){
				$temp['list_users'] = true;
			}else{
				$temp['list_users'] = false;
			}
			
			if (isset($i['list_roles']) && $i['list_roles']){
				$temp['list_roles'] = true;
			}else{
				$temp['list_roles'] = false;
			}
			
			if (isset($i['b_massage'])){
				$temp['b_massage'] = $i['b_massage'];
			}
		}
		
		update_option('U_S_C', $temp);
		//delete_option('U_S_C');
		return $temp;
	}
	
	/* Adds a box to the main column on the custom post type edit screens */
	public function User_specific_content_box() {
		add_meta_box('User_specific_content', __( 'User specific content box'),array($this,'User_specific_content_box_inner'),'post');
		add_meta_box('User_specific_content', __( 'User specific content box'),array($this,'User_specific_content_box_inner'),'page');
		//add metabox to custom post types
		$args=array(
			'public'   => true,
			'_builtin' => false
		); 
		//add metabox to custom post types edit screen
		$output = 'names'; // names or objects, note names is the default
		$operator = 'and'; // 'and' or 'or'
		$post_types=get_post_types($args,$output,$operator); 
		foreach ($post_types  as $post_type ) {
			add_meta_box('User_specific_content', __( 'User specific content box','bauspc'),array($this,'User_specific_content_box_inner'),$post_type);
		}
	}

	/* Prints the box content */
	public function User_specific_content_box_inner() {
		global $post,$wp_roles;
		//get options:
		
		$options = $this->U_S_C_get_option('U_S_C');
		$savedroles = get_post_meta($post->ID, 'U_S_C_roles',true);
		//var_dump($savedroles);
		$savedusers = get_post_meta($post->ID, 'U_S_C_users',true);
		$savedoptions = get_post_meta($post->ID, 'U_S_C_options',true);
		//var_dump($savedusers);
		// Use nonce for verification
		wp_nonce_field( plugin_basename(__FILE__), 'User_specific_content_box_inner' );
		//by role
		echo __('Select users to show this content to','bauspc');
		if ($options['list_roles']){
			echo '<h4>'.__('By User Role:','bauspc').'</h4>';
			if ( !isset( $wp_roles ) )
				$wp_roles = new WP_Roles();
			if (!empty($savedroles)){
				foreach ( $wp_roles->role_names as $role => $name ) {
					echo '<input type="checkbox" name="U_S_C_roles[]" value="'.$name.'"';
					if (in_array($name,$savedroles)){
						echo ' checked';
					}
					echo '>'.$name.'    ';
				}
			}else{
				foreach ( $wp_roles->role_names as $role => $name ) {
					echo '<input type="checkbox" name="U_S_C_roles[]" value="'.$name.'">'.$name.'    ';
				}
			}
		}
		
		//by user
		if ($options['list_users']){
			echo '<h4>'.__('By User Name:','bauspc').'</h4>';
			$blogusers = get_users('blog_id=1&orderby=nicename');
			$usercount = 0;
			if (!empty($savedusers)){
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
			}else{
				foreach ($blogusers as $user) {
					echo '<input type="checkbox" name="U_S_C_users[]" value="'.$user->ID.'">'.$user->display_name.'    ';
					$usercount = $usercount + 1;
					if ($usercount > 5){
						echo '<br/>';
						$usercount = 0;
					}
				}
			}
		}
		
		//other_options
		//logeed-in only
		echo '<h4>'.__('logged in users only:','bauspc').'</h4>';
		echo '<input type="checkbox" name="U_S_C_options[logged]" value="1"';
		if (isset($savedoptions['logged']) && $savedoptions['logged'] == 1){
			echo ' checked'; 
		}
		echo '>'.__('If this box is checked then content will show only to logged-in users and everyone else will get the blocked message','bauspc');
		//none logged-in
		echo '<h4>'.__('None logged in users only:','bauspc').'</h4>';
		echo '<input type="checkbox" name="U_S_C_options[non_logged]" value="1"';
		if (isset($savedoptions['non_logged']) && $savedoptions['non_logged'] == 1){
			echo ' checked'; 
		}
		echo '>'.__('If this box is checked then content will show only to non-logged in visitors and everyone else will get the blocked message','bauspc');
		echo '<h4>'.__('Content Blocked message:','bauspc').'</h4>';
		echo '<textarea rows="3" cols="70" name="U_S_C_message" id="U_S_C_message">'.get_post_meta($post->ID, 'U_S_C_message',true).'</textarea><br/>'.__('This message will be shown to anyone who is not on the list above.');
	} 
 
	/* When the post is saved, saves our custom data */
	function User_specific_content_box_inner_save( $post_id ) {
		global $post;
		  // verify this came from the our screen and with proper authorization,
		  // because save_post can be triggered at other times
		if (isset($_POST['User_specific_content_box_inner'])){
			if ( !wp_verify_nonce( $_POST['User_specific_content_box_inner'], plugin_basename(__FILE__) ) )
				return $post_id;
		}else{
			return $post_id;
		}
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


	public function User_specific_content_filter($content){
		global $post,$current_user;
		$savedoptions = get_post_meta($post->ID, 'U_S_C_options',true);
		$m = get_post_meta($post->ID, 'U_S_C_message',true);
		if (isset($savedoptions) && !empty($savedoptions)){
			// none logged only
			if (isset($savedoptions['non_logged']) && $savedoptions['non_logged'] == 1){
				if (is_user_logged_in()){
					return $this->displayMessage($m);
				}
			}
			//logged in users only
			if (isset($savedoptions['logged']) && $savedoptions['logged'] == 1){
				if (!is_user_logged_in()){
					return $this->displayMessage($m);
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
			$cu_r = $this->bausp_get_current_user_role();
			if ($cu_r){
				if (in_array($cu_r,$savedroles)){
					return $content;
					exit;
				}else{
					$run_check = 1;
				}
			}else{
				//failed role check
				$run_check = 1;
			}
		}
		//by user
		if (isset($savedusers) && !empty($savedusers)){
			get_currentuserinfo();
			if (in_array($current_user->ID,$savedusers)){
				return $content;
			}else{
				$run_check = $run_check + 1;
			}
			//failed both checks
			return $this->displayMessage($m);
		}
		if ($run_check > 0){
			return $this->displayMessage($m);
		}
		return $content;
	}

	/************************
	* helpers
	************************/

	public function bausp_get_current_user_role() {
		global $wp_roles;
		$current_user = wp_get_current_user();
		$roles = $current_user->roles;
		$role = array_shift($roles);
		return isset($wp_roles->role_names[$role]) ? translate_user_role($wp_roles->role_names[$role] ) : false;
	}
	
	public function credits(){
		echo '<ul style="list-style: square inside none; width: 300px; font-weight: bolder; padding: 20px; border: 2px solid; background-color: #FFFFE0; border-color: #E6DB55; position: fixed;  right: 120px; top: 150px;">
					<li> Any feedback or suggestions are welcome at <a href="http://en.bainternet.info/2011/user-specific-content-plugin">plugin homepage</a></li>
					<li> <a href="http://wordpress.org/tags/user-specific-content?forum_id=10">Support forum</a> for help and bug submittion</li>
					<li> Also check out <a href="http://en.bainternet.info/category/plugins">my other plugins</a></li>
					<li> And if you like my work <span style="color: #FF0000;">make a donation</span> <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=PPCPQV8KA3UQA"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif"></a>or atleast <a href="http://wordpress.org/extend/plugins/user-specific-content/">rank the plugin</a></li>
				</ul>';
	}//end function
	
	/************************
	*	shortcodes
	************************/

	public function User_specific_content_shortcode($atts, $content = null){
		extract(shortcode_atts(array(
	        "user_id" => '',
			"user_name" => '',
			"user_role" => '',
			"blocked_message" => '',
			"blocked_meassage" => null
	    ), $atts));
		
		global $post;
		if ($blocked_meassage !== null){
			$blocked_message = $blocked_meassage;
		}
		
		$options = $this->U_S_C_get_option('U_S_C');
		global $current_user;
        get_currentuserinfo();
		if ($user_id != '' || $user_name != '' || $user_role != ''){
		
			//check logged in
			if (!is_user_logged_in()){
				return $this->displayMessage($blocked_message);
			}
			//check user id
			if (isset($user_id) && $user_id != '' ){
				$user_id = explode(",", $user_id);
				if (!in_array($current_user->ID,$user_id)){
					return $this->displayMessage($blocked_message);
				}		
			}
			//check user name
			if (isset($user_name) && $user_name != '' ){
				$user_name = explode(",", $user_name);
				if (!in_array($current_user->user_login,$user_name)){
					return $this->displayMessage($blocked_message);
				}
			}
			//check user role
			if (isset($user_role) && $user_role != '' ){
				$user_role = explode(",", $user_role);
				if (!in_array($this->bausp_get_current_user_role(),$user_role)){
					return $this->displayMessage($blocked_message);
				}
			}
		}
		return apply_filters('user_spcefic_content_shortcode_filter',do_shortcode($content));
	}//end function

	public function displayMessage($m){
		global $post;
		if (isset($m) && $m != ''){
			return apply_filters('user_specific_content_blocked',$m,$post);
		}else{
			$options = $this->U_S_C_get_option('U_S_C');
			return apply_filters('user_specific_content_blocked',$options['b_massage'],$post);
		}
	}
}//end class

$U_S_C_i = new bainternet_U_S_C();