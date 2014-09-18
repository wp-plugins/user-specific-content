<?php
/*
Plugin Name: User Specific Content
Plugin URI: http://en.bainternet.info
Description: This Plugin allows you to select specific users by user name, or by role name who can view a  specific post content or page content.
Version: 1.0.2
Author: Bainternet
Author URI: http://en.bainternet.info
*/
/*
		* 	Copyright (C) 2014  Ohad Raz
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
	
	public $options = false;
	/**
	 * Class constructor
	 */
    function __construct() {
		//Language Setup
		$locale = get_locale();
		load_plugin_textdomain( $this->localization_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		$this->hooks();
    }

    /**
     * hooks 
     * function used to add action and filter hooks
     * Used with `admin_hooks`, `client_hooks`, `and common_hooks`
     * @return void
     */
    public function hooks(){
    	if (is_admin()){
    		$this->admin_hooks();
    	}else{
    		$this->client_hooks();
    	}
    	$this->common_hooks();
    }

    /**
     * common_hooks
     * hooks for both admin and client sides
     * @return void
     */
    public function common_hooks(){
    	/* add_filter hooks */
		add_action('init',  array($this, 'U_S_C_init'));
		/* Save Meta Box */
		add_action('save_post', array($this,'User_specific_content_box_inner_save'));
		/* add shortcodes */
		add_shortcode('O_U',array($this,'User_specific_content_shortcode'));
    }

    /**
     * admin_hooks
     * Admin side hooks should go here
     * @return void
     */
    public function admin_hooks(){
    	//add admin panel
		if (!class_exists('SimplePanel'))
			require_once(plugin_dir_path(__FILE__).'panel/Simple_Panel_class.php');

		require_once(plugin_dir_path(__FILE__).'panel/User_specific_content_panel.php');

		/* Define the custom box */
		add_action('add_meta_boxes', array($this,'User_specific_content_box'));
    }

    /**
     * client_hooks
     * client side hooks should go here
     * @return void
     */
    public function client_hooks(){}

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
	

	
	//options
	public function U_S_C_get_option(){
		if ($this->options) return $this->options;

		$temp = array(
			'b_massage'           => '',
			'list_users'          => true,
			'list_roles'          => true,
			'run_on_the_content'  => true,
			'run_on_the_excerpt'  => false,
			'posttypes'           =>  array('post' => true, 'page' => true ),
			'capability'          => 'manage_options',
			'user_role_list_type' => 'checkbox',
			'user_list_type'      => 'checkbox',
		);
		
		$i = get_option('U_S_C');
		if (!empty($i)){
			//checkboxes
			$checkboxes = array(
				'run_on_the_content',
				'run_on_the_excerpt',
				'list_users',
				'list_roles',
			);
			
			//all others
			foreach ($i as $c => $value) {
				if (in_array($c, $checkboxes)){
					if (isset($i[$c]) && $i[$c]){
						$temp[$c] = true;
					}else{
						$temp[$c] = false;
					}
				}else{
					$temp[$c] = $value;
				}
			}

		}
		
		update_option('U_S_C', $temp);
		$this->options = $temp;
		//delete_option('U_S_C');
		return $temp;
	}
	
	/* Adds a box to the main column on the custom post type edit screens */
	public function User_specific_content_box($post_type) {
		$options = $this->U_S_C_get_option();
		if ( !current_user_can($options['capability']) ) 
			return;

		
		$allowed_types = array();
		foreach ((array)$options['posttypes'] as $key => $value) {
			if ($value)
				$allowed_types[] = $key;
		}
		//added a filter to enable controling the allowed post types by filter hook
		$allowed_types = apply_filters('USC_allowed_post_types',$allowed_types);

		if (in_array($post_type,(array)$allowed_types) )
			add_meta_box('User_specific_content', __( 'User specific content box'),array($this,'User_specific_content_box_inner'),$post_type);

		//allow custom types by action hook
		do_action('USC_add_meta_box',$this);
	}

	/* Prints the box content */
	public function User_specific_content_box_inner() {
		global $post,$wp_roles;
		//get options:
		
		$options = $this->U_S_C_get_option('U_S_C');
		$savedroles = get_post_meta($post->ID, 'U_S_C_roles',true);
		$savedusers = get_post_meta($post->ID, 'U_S_C_users',true);
		$savedoptions = get_post_meta($post->ID, 'U_S_C_options',true);

		// Use nonce for verification
		wp_nonce_field( plugin_basename(__FILE__), 'User_specific_content_box_inner' );
		//by role
		echo __('Select users to show this content to','bauspc');
		if ($options['list_roles']){
			echo '<h4>'.__('By User Role:','bauspc').'</h4><p>';
			if ( !isset( $wp_roles ) )
				$wp_roles = new WP_Roles();
			if (empty($savedroles)) 
				$savedroles = array();
			if ('checkbox' == $options['user_role_list_type']){
				foreach ( $wp_roles->role_names as $role => $name ) {
					echo '<label>
					<input type="checkbox" name="U_S_C_roles[]" value="'.$name.'"';
					if (in_array($name,$savedroles)){ echo ' checked';}
					echo '>'.$name.'</label>    ';
				}
			}else{
				echo '<select name="U_S_C_roles[]" multiple="multiple">';
				foreach ( $wp_roles->role_names as $role => $name ) {
					echo '<option ';
					if (in_array($name,$savedroles)){ echo ' selected="selected" ';}
					echo 'value="'.$name.'">'.$name.'</option>';
				}
				echo '</select> - <a href="#" class="button clearselection_usc">Clear selection</a>';
			}
		}
		echo '</p>';
		
		//by user
		if ($options['list_users']){
			echo '<h4>'.__('By User Name:','bauspc').'</h4><p>';
			$site_id = 1;
			if (is_multisite())
				$site_id = get_current_blog_id();
			$blogusers = get_users('blog_id='.$site_id.'&orderby=nicename');
			if (empty($savedusers)) 
				$savedusers = array();
			if ('checkbox' == $options['user_list_type']){
				foreach ($blogusers as $user) {
					echo '<label><input type="checkbox" name="U_S_C_users[]" value="'.$user->ID.'"';
					if (in_array($user->ID,$savedusers)){
						echo ' checked';
					}
					echo '>'.$user->display_name.'</label>    ';
				}
			}else{
				echo '<select name="U_S_C_users[]" multiple="multiple">';
				foreach ($blogusers as $user) {
					echo '<option ';
					if (in_array($user->ID,(array)$savedusers)){ echo ' selected="selected" ';}
					echo 'value="'.$user->ID.'">'.$user->display_name.'</option>';
				}
				echo '</select> - <a href="#" class="button clearselection_usc">Clear selection</a>';
			}
			echo '</p>';
		}
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($){
			$('.clearselection_usc').click(function(e){
				e.preventDefault();
				$(this).prev().val([]);
			});
		});
		</script>
		<?php
		//other_options
		echo '<h4>'.__('Members and Guests','bauspc').'</h4>';
		//logeed-in only
		echo '<p><label>
		<input type="checkbox" name="U_S_C_options[logged]" value="1"';
		if (isset($savedoptions['logged']) && $savedoptions['logged'] == 1){
			echo ' checked'; 
		}
		echo '>'.__('logged in users only','bauspc').'</label><br/><span class="description">'
		.__('If this box is checked then content will show only to logged-in users and everyone else will get the blocked message','bauspc');
		echo '</span></p>';
		
		//none logged-in
		echo '<hp><label>
		<input type="checkbox" name="U_S_C_options[non_logged]" value="1"';
		if (isset($savedoptions['non_logged']) && $savedoptions['non_logged'] == 1){
			echo ' checked'; 
		}
		echo '>'.__('None logged in users only','bauspc').'</label><br/><span class="description">'.
		__('If this box is checked then content will show only to non-logged in visitors and everyone else will get the blocked message','bauspc');
		echo '<h4>'.__('Content Blocked message:','bauspc').'</h4><p>';
		echo '<textarea rows="3" cols="70" name="U_S_C_message" id="U_S_C_message">'.get_post_meta($post->ID, 'U_S_C_message',true).'</textarea>
		<br/><span class="description">'.__('This message will be shown to anyone who is not on the list above.').'</span></p>';
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
		$run_check = 0;
		//get saved roles
		$savedroles = get_post_meta($post->ID, 'U_S_C_roles',true);
		//get saved users
		$savedusers = get_post_meta($post->ID, 'U_S_C_users',true);
		if (!count($savedusers) > 0 && !count($savedroles) > 0 ){
			return $content;
			exit;
		}
		//by role
		if (isset($savedroles) && !empty($savedroles)){
			get_currentuserinfo();
			foreach ((array)$savedroles as $role) {
				if ($this->has_role(strtolower($role))){
					return $content;
					exit;
				}
			}
			//failed role check
			$run_check = 1;
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
	/**
	 * Deprecated 1.0.2
	 */
	public function bausp_get_current_user_role() {}

	/**
	 * @since 1.0.2
	 */
	public function has_role($role, $user_id = null){
		if ( is_numeric( $user_id ) )
			$user = get_userdata( $user_id );
		else
			$user = wp_get_current_user();

		if ( empty( $user ) )
			return false;

		return in_array( $role, (array) $user->roles );
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
			'logged_status' => '',
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
				if (!$this->has_role($user_role)){
					return $this->displayMessage($blocked_message);
				}
			}
		}
		if ($logged_status = 'in'){
			if (!is_user_logged_in()){
				return $this->displayMessage($blocked_message);
			}
		}
		if ($logged_status = 'out'){
			if (is_user_logged_in()){
				return $this->displayMessage($blocked_message);
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
add_action('init','init_uspc_plugin',0);
function init_uspc_plugin(){
	global $U_S_C_i;
	$U_S_C_i = new bainternet_U_S_C();
}



function mrpu_plugin_init() {
	load_plugin_textdomain( 'multiple-roles-per-user', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
}
add_action( 'plugins_loaded', 'mrpu_plugin_init' );
 
function mrpu_admin_enqueue_scripts( $handle ) {
	if ( 'user-edit.php' == $handle ) {
		// We need jQuery to move things around :)
		wp_enqueue_script( 'jquery' );
	}
}
add_action( 'admin_enqueue_scripts', 'mrpu_admin_enqueue_scripts', 10 );
 
/**
 * Adds the GUI for selecting multiple roles per user
 */
function mrpu_add_multiple_roles_ui( $user ) {
	// Not allowed to edit user - bail
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	$roles = get_editable_roles();
	$user_roles = array_intersect( array_values( $user->roles ), array_keys( $roles ) ); ?>
	<div class="mrpu-roles-container">
		<h3><?php _e( 'User Roles', 'multiple-roles-per-user' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="user_credits"><?php _e( 'Roles', 'multiple-roles-per-user' ); ?></label></th>
				<td>
					<?php foreach ( $roles as $role_id => $role_data ) : ?>
						<label for="user_role_<?php echo esc_attr( $role_id ); ?>">
							<input type="checkbox" id="user_role_<?php echo esc_attr( $role_id ); ?>" value="<?php echo esc_attr( $role_id ); ?>" name="mrpu_user_roles[]"<?php echo in_array( $role_id, $user_roles ) ? ' checked="checked"' : ''; ?> />
							<?php echo $role_data['name']; ?>
						</label>
						<br />
					<?php endforeach; ?>
					<br />
					<span class="description"><?php _e( 'Select one or more roles for this user.', 'multiple-roles-per-user' ); ?></span>
					<?php wp_nonce_field( 'mrpu_set_roles', '_mrpu_roles_nonce' ); ?>
				</td>
			</tr>
		</table>
	</div>
	<?php 
	// Do some hacking around to hide the built-in user roles selector
	// First hide it with CSS and then get rid of it with jQuery ?>
	<style>
		label[for="role"],
		select#role {
			display: none;
		}
	</style>
	<script type="text/javascript">
		(function($){
			$(document).ready(function(){
				var row = $('select#role').closest('tr');
				var clone = row.clone();
				// clone.insertAfter( $('select#role').closest('tr') );
				row.html( $('.mrpu-roles-container tr').html() );
				$('.mrpu-roles-container').remove();
			})
		})(jQuery)
	</script>
<?php }
add_action( 'edit_user_profile', 'mrpu_add_multiple_roles_ui', 0 );
 
/**
 * Saves the selected roles for the user
 */
function mrpu_save_multiple_user_roles( $user_id ) {
	// Not allowed to edit user - bail
	if ( ! current_user_can( 'edit_user', $user_id ) || ! wp_verify_nonce( $_POST['_mrpu_roles_nonce'], 'mrpu_set_roles' ) ) {
		return;
	}
	
	$user = new WP_User( $user_id );
	$roles = get_editable_roles();
	$new_roles = isset( $_POST['mrpu_user_roles'] ) ? (array) $_POST['mrpu_user_roles'] : array();
	// Get rid of any bogus roles
	$new_roles = array_intersect( $new_roles, array_keys( $roles ) );
	$roles_to_remove = array();
	$user_roles = array_intersect( array_values( $user->roles ), array_keys( $roles ) );
	if ( ! $new_roles ) {
		// If there are no roles, delete all of the user's roles
		$roles_to_remove = $user_roles;
	} else {
		$roles_to_remove = array_diff( $user_roles, $new_roles );
	}
 
	foreach ( $roles_to_remove as $_role ) {
		$user->remove_role( $_role );
	}
 
	if ( $new_roles ) {
		// Make sure that we don't call $user->add_role() any more than it's necessary
		$_new_roles = array_diff( $new_roles, array_intersect( array_values( $user->roles ), array_keys( $roles ) ) );
		foreach ( $_new_roles as $_role ) {
			$user->add_role( $_role );
		}
	}
}
add_action( 'edit_user_profile_update', 'mrpu_save_multiple_user_roles' );
 
/**
 * Gets rid of the "Role" column and adds-in the "Roles" column
 */
function mrpu_add_roles_column( $columns ) {
	$old_posts = isset( $columns['posts'] ) ? $columns['posts'] : false;
	unset( $columns['role'], $columns['posts'] );
	$columns['mrpu_roles'] = __( 'Roles', 'multiple-roles-per-user' );
	if ( $old_posts ) {
		$columns['posts'] = $old_posts;
	}
 
	return $columns;
}
add_filter( 'manage_users_columns', 'mrpu_add_roles_column' );
 
/**
 * Displays the roles for a user
 */
function mrpu_display_user_roles( $value, $column_name, $user_id ) {
	static $roles;
	if ( ! isset( $roles ) ) {
		$roles = get_editable_roles();
	}
	if ( 'mrpu_roles' == $column_name ) {
		$user = new WP_User( $user_id );
		$user_roles = array();
		$_user_roles = array_intersect( array_values( $user->roles ), array_keys( $roles ) );
		foreach ( $_user_roles as $role_id ) {
			$user_roles[] = $roles[ $role_id ]['name'];
		}
 
		return implode( ', ', $user_roles );
	}
 
	return $value;
}
add_filter( 'manage_users_custom_column', 'mrpu_display_user_roles', 10, 3 );