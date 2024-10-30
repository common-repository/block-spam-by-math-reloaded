<?php
/*
Plugin Name: Block-Spam-By-Math-Reloaded
Plugin URI: http://www.jamespegram.com/block-spam-by-math-reloaded/
Description: This plugin protects your Wordpress 3.x login, comments, and new user/new blog signup process against spambots with a simple math question. This plugin adds an extra layer of protection against comment spam and spam blog creation bots. While nothing is 100% fool proof the concept has been proven many times in various forms in the past. Block Spam By Math Reloaded combines the features of WPMU Block Spam By Math and the original Block Spam By Math into one plugin that supports the Wordpress 3.x and Buddypress 1.2.7 platforms. This plugin is based on the original <a href="http://wordpress.org/extend/plugins/block-spam-by-math/">Block-Spam-By-Math</a> plugin created by <a href="http://www.grauonline.de">Alexander Grau</a>. 
Author: James Pegram
Version: 2.2.4
Author URI: http://www.jamespegram.com
*/

/*  Copyright 2009  
    James Pegram (email : jwpegram [make-an-at] gmail [make-a-dot] com)
    Alexander Grau (email : alex [make-an-at] grauonline [make-a-dot] de)

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

define('BSBM_VERSION', '2.2.4');	// Current version of the Plugin
define('BSBM_NAME', 'Block Spam By Math Reloaded');	// Name of the Plugin


// Define default value for plugin options
define ('BSBM_ANSWER_ERROR','Oops! Looks like you answered the security question incorrectly.');
define ('BSBM_EMPTY_ERROR','Oops! It appears you forgot to answer the security question.');
define ('BSBM_LOGIN_FORM',true);
define ('BSBM_SIGNUP_FORM',true);
define ('BSBM_COMMENT_FORM',true);
define ('BSBM_REGISTER_FORM',true);
define ('BSBM_MATHVALUE0','2');
define ('BSBM_MATHVALUE1','15');
define ('BSBM_NOTICE_MESSAGE','IMPORTANT! To be able to proceed, you need to solve the following simple math (so we know that you are a human) :-)');
define ('BSBM_HOOK_LOCATION','1');
define ('BSBM_CUSTOMHOOK','');
define ('BSBM_OVERRIDE_CSS',false);
define ('BSBM_TABINDEX','5');
define ('BSBM_EXCLUDE_ADMIN',false);
define ('BSBM_WP_ROLE','manage_options');

// Establish a few variables we may want to use later.
$bsbm_siteurl    = get_bloginfo('wpurl');
$bsbm_siteurl    = (strpos($bsbm_siteurl,'http://') === false) ? get_bloginfo('siteurl') : $bsbm_siteurl;
$bsbm_path       = preg_replace('/^.*wp-content[\\\\\/]plugins[\\\\\/]/', '', __FILE__);
$bsbm_path       = str_replace('\\','/',$bsbm_path);
$bsbm_fullpath   = $bsbm_siteurl.'/wp-content/plugins/'.substr($bsbm_path,0,strrpos($bsbm_path,'/')).'/';

$bsbm_salt	= "$2a$07$secretsaltstringASDFAS$";	// Random salt to use for generating answer hashes



register_activation_hook( __FILE__, 'bsbm_activate' );
register_uninstall_hook(__FILE__, 'bsbm_uninstall' );

if ( isset( $_POST['bsbm_uninstall'], $_POST['bsbm_uninstall_confirm'] ) ) {
    bsbm_uninstall();
}

add_action( 'init', 'bsbm_init' );
add_action('admin_menu', 'bsbm_admin_options');
add_action('init', 'bsbm_doit', 11, 1);
//bsbm_admin_warnings();


if ($_GET['page'] == 'bsbm') {
    wp_register_style('bsbm.css', $bsbm_fullpath . 'bsbm.css');
    wp_enqueue_style('bsbm.css');
}



// Initialize plugin
function bsbm_init() {
    if ( function_exists( 'load_plugin_textdomain' ) ) {
        load_plugin_textdomain( 'block-spam-by-math-reloaded', PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)) );
    }
}


// Add the appropriate security forms
function bsbm_doit() {
    $options = get_option('bsbm_options');
    // Are we dealing with a network version of Wordpress
    // or standalone
    if (is_multisite() == true) { $n=1; } else { $n=0; }
    switch ($n) {
        case 0:
            if ( !is_user_logged_in()) { $skip=false; }
            elseif ( current_user_can($options['bsbm_wp_role']) ) { $skip = true; }
            else { $skip = false; }
            break;
        case 1:
            if ( !is_user_logged_in()) { $skip=false; }
            elseif ( is_super_admin( $user_id ) ) { $skip = true; }
            elseif ( current_user_can_for_blog($blog_id,$options['bsbm_wp_role'])) { $skip = true; }
            else { $skip = false; }
            break;
        default:
            $skip = false;	
            break;
    }
// Run the security checks
if ($skip==false) {

    if ($options['bsbm_override_css'] == false) {
        add_action('wp_head', 'bsbm_stylesheet');
    }
    if ($options['bsbm_login_form'] == true) {
        add_action( 'login_form', 'bsbm_add_hidden_fields' );
        add_action( 'bp_sidebar_login_form', 'bsbm_add_hidden_fields' );
        add_action( 'wp_authenticate', 'bsbm_authenticate', 10, 2 );
    }
    if ($options['bsbm_hook_location'] == '1' && $options['bsbm_comment_form'] == true) {
        add_action( 'comment_form', 'bsbm_add_hidden_fields' );
        add_filter( 'preprocess_comment', 'bsbm_preprocess_comment' );	
    }
    elseif ($options['bsbm_hook_location'] == '2' && $options['bsbm_comment_form'] == true) {
        add_action($options['bsbm_customhook'], 'bsbm_add_hidden_fields' );
        add_filter( 'preprocess_comment', 'bsbm_preprocess_comment' );	
    }
    elseif ($options['bsbm_hook_location'] == '3' && $options['bsbm_comment_form'] == true) {
        add_action( 'after_comment_box', 'bsbm_add_hidden_fields' );
        add_filter( 'preprocess_comment', 'bsbm_preprocess_comment' );	
    } 
    if ($options['bsbm_signup_form'] == true) {
        add_action( 'signup_extra_fields', 'bsbm_add_hidden_fields' );
        add_action( 'bp_before_registration_submit_buttons', 'bsbm_add_hidden_fields' );
        add_action( 'wpmu_validate_user_signup', 'bsbm_validate_user_signup');
    }
    if ($options['bsbm_register_form'] == true) {
        add_action( 'register_form', 'bsbm_add_hidden_fields' );
        add_action( 'register_post', 'bsbm_registration',10,2 );
        add_filter('signup_blogform', 'bsbm_add_hidden_signup' );
    }
}

}





// Default stylesheet used to display security question
function bsbm_stylesheet() {
?>
<style type="text/css">
#bsbm_form { clear:both; margin:20px 0; }
#bsbm_form label { font-size: 16px; font-weight:bold; color: #999; margin:0; padding:10px 0;}
#bsbm_form .question { font-size: 14px; font-weight:normal; margin:0; padding:5px 0;}
#bsbm_form .answer { font-size: 12px; font-weight:normal;}
#bsbm_form .notice { font-size: 11px; font-weight:normal;}	
</style>
<?php
}


// Pass the hidden fields to wp-signup
function bsbm_add_hidden_signup() {
    if ( !empty( $_POST['mathvalue0']) && !empty($_POST['mathvalue1'] ) && !empty($_POST['mathvalue2'])) {
        echo "<input type='hidden' name='mathvalue_answer' value='$_POST[mathvalue_answer]' />";
        echo "<input type='hidden' name='mathvalue2' value='$_POST[mathvalue2]' />";
    }
}


// Add hidden fields to the various forms
function bsbm_add_hidden_fields($errors = '') {

    $options = get_option('bsbm_options');
    if (is_numeric($options['bsbm_mathvalue0'])) {  $mathvalue0 = rand($options['bsbm_mathvalue0'], $options['bsbm_mathvalue1']); } else {  $mathvalue0 = rand(2, 15); }
    if (is_numeric($options['bsbm_mathvalue1'])) {  $mathvalue1 = rand($options['bsbm_mathvalue0'], $options['bsbm_mathvalue1']);  } else { $mathvalue1 = rand(2, 15); }
    
    $mathvalue_answer = bsbm_hash(strval($mathvalue0+$mathvalue1));
    
    echo '<div id="bsbm_form"><label for="bsbm_question">' . __('Security Question:') . '</label>';
    
    // Only used during the new user/new blog sign up process
    if ( false !== strpos( $_SERVER['SCRIPT_NAME'], 'wp-signup.php')) { 
        if ( $errmsg = $errors->get_error_message('bsbm_question') ) { echo '<p class="error">'. $errmsg .'</p>'; }
    }
    echo '
    <div class="question">What is '. $mathvalue0 .' + '. $mathvalue1 .' ?</div>
    <div class="answer">
    <input type="text" tabindex="'. $options['bsbm_tabindex'] .'" name="mathvalue2" value="" />
    <div style="display:none">Please leave these two fields as-is:
    <input type="text" name="mathvalue_answer" value="'. $mathvalue_answer .'" />
    </div>
    </div>
    <div class="notice">';
    echo $options['bsbm_notice_message']; 
    echo '</div></div>';

}	



// Protection function for submitted login form
function bsbm_authenticate( $user_login, $user_password ) {
    if ( ( $user_login != '' ) && ( $user_password != '' ) ) {
        bsbm_check_hidden_fields();
    }
}

// Protection function for submitted comment form
function bsbm_preprocess_comment( $commentdata ) {
    bsbm_check_hidden_fields();
    return $commentdata;
}

// Protection function for submitted login form
function bsbm_registration( $user_login, $user_email ) {
    if ( ( $user_login != '' ) && ( $user_email != '' ) ) {
        bsbm_check_hidden_fields();
    }
}


// Check the hidden fields and process the answer
function bsbm_validate_user_signup($content) {
    $answer = bsbm_check_hidden_fields();
    $options = get_option('bsbm_options');
    
    if ($answer == 1) {
        $error = $options['bsbm_empty_error'];
        $errors = new WP_Error();
       	$errors->add('bsbm_question', $error);
    return array('bsbm_question' => $val2, 'errors' => $errors);
    }
    elseif ($answer == 2) {
        $error = $options['bsbm_answer_error'];
        $errors = new WP_Error();
       	$errors->add('bsbm_question', $error);
    return array('bsbm_question' => $val2, 'errors' => $errors);
    } else {
        return $content;
    }
}

// Check for hidden fields and wp_die() in case of error
function bsbm_check_hidden_fields() {
    $options = get_option('bsbm_options');
    
    // Get values from POST data
    $val_answer = '';
    $val2 = '';
    if ( isset( $_POST['mathvalue_answer'] ) ) {
        $val_answer = $_POST['mathvalue_answer'];
    }
    if ( isset( $_POST['mathvalue2'] ) ) {
        if( $_POST['mathvalue2'] != '' ) {
            $val2 = bsbm_hash(trim(strval($_POST['mathvalue2'])));
        }
    }
    // Handle checks for forms other than new user/new blog signups.
    if ( false === strpos( $_SERVER['SCRIPT_NAME'], 'wp-signup.php') ) { 
        if ( $val2 == '') {
            $error = $options['bsbm_empty_error'];
            wp_die( $error, '403 Forbidden', array( 'response' => 403 ) );
        }
        elseif ( ($val_answer == '') || (trim($val2) != trim($val_answer)) ) {
            $error = $options['bsbm_answer_error'];
            wp_die( $error, '403 Forbidden', array( 'response' => 403 ) );
        }
    }
    
    
    
    // Passes an error condition in the var $answer back to the function called by add_action
    // This allows us to insert the error message in the template, compensating for the fact get_header() is called 
    // before execution ever gets this far.
    // Note: If Wordpress ever alters the wp-signup.php or get_header() funtion to allow for a break condition
    // this can be handled a little cleaner.
    if ( $val2 == '') { return $answer=1; }
    elseif ( ($val_answer == '') || (trim($val2) != trim($val_answer)) ) { return $answer=2; }
    else { return $answer=3; }
}	



/*
============================================
ADMIN
============================================
*/

function bsbm_activate() {

    $default_options = array( 
    'bsbm_empty_error' => BSBM_EMPTY_ERROR,
    'bsbm_answer_error' => BSBM_ANSWER_ERROR,
    'bsbm_login_form' => BSBM_LOGIN_FORM,
    'bsbm_signup_form' => BSBM_SIGNUP_FORM,
    'bsbm_comment_form' => BSBM_COMMENT_FORM,
    'bsbm_register_form' => BSBM_REGISTER_FORM,
    'bsbm_mathvalue0' => BSBM_MATHVALUE0,
    'bsbm_mathvalue1' => BSBM_MATHVALUE1,
    'bsbm_notice_message' => BSBM_NOTICE_MESSAGE,	
    'bsbm_hook_location' => BSBM_HOOK_LOCATION,
    'bsbm_customhook' => BSBM_CUSTOMHOOK,
    'bsbm_override_css' => BSBM_OVERRIDE_CSS,
    'bsbm_tabindex' => BSBM_TABINDEX,
    'bsbm_wp_role' => BSBM_WP_ROLE
    );
    add_option('bsbm_options', $default_options);
    update_option('bsbm_version', BSBM_VERSION);
    
    return true;	
}


function bsbm_admin_options() {
    if ( function_exists('add_management_page') ) {
        add_options_page('Block Spam By Math', 'Block Spam By Math', 'manage_options', 'bsbm', 'bsbm_admin_settings');
        
        //call register settings function
        add_action( 'admin_init', 'bsbm_register_settings' );
    }
}


// Let's do a bit of validation on the submitted values, just in case something strange got submitted
function bsbm_options_validate($input) {
    $options = get_option('bsbm_options');
    $options['bsbm_empty_error'] = wp_filter_kses($input['bsbm_empty_error']);
    $options['bsbm_answer_error'] = wp_filter_kses($input['bsbm_answer_error']);
    $options['bsbm_login_form'] = ( $input['bsbm_login_form'] == true ? 1 : 0 );
    $options['bsbm_signup_form'] = ( $input['bsbm_signup_form'] == true ? 1 : 0 );
    $options['bsbm_comment_form'] = ( $input['bsbm_comment_form'] == true ? 1 : 0 );
    $options['bsbm_register_form'] = ( $input['bsbm_register_form'] == true ? 1 : 0 );
    $options['bsbm_mathvalue0'] = intval($input['bsbm_mathvalue0']);
    $options['bsbm_mathvalue1'] = intval($input['bsbm_mathvalue1']);
    $options['bsbm_notice_message'] = wp_filter_kses($input['bsbm_notice_message']);
    $options['bsbm_hook_location'] = wp_filter_kses($input['bsbm_hook_location']);
    $options['bsbm_customhook'] = wp_filter_kses($input['bsbm_customhook']);
    $options['bsbm_override_css'] = ( $input['bsbm_override_css'] == true ? 1 : 0 );
    $options['bsbm_tabindex'] = intval($input['bsbm_tabindex']);	
    $options['bsbm_wp_role'] = wp_filter_kses($input['bsbm_wp_role']);
    return $options;
}


// Administration menu
function bsbm_admin_settings() {
    global $bsbm_fullpath;
    
        // Check that the user has the required permission level 
        if (!current_user_can('manage_options')) { wp_die( __('You do not have sufficient permissions to access this page.') ); }
    
        
        $options = get_option('bsbm_options');
    ?>
    <div class="wrap">
    <div id="icon-options-general" class="icon32"><br /></div>
    <h2><?php echo BSBM_NAME .' ( v.'. BSBM_VERSION .' )'; ?></h2>
    
    <?php
    
        bsbm_admin_message();
         
        
        ?>
    
    <div class="postbox-container" style="width: 70%;">
    <div class="metabox-holder">	
    <div class="meta-box-sortables">
    <?php if ( $_GET['module'] == 'help')  { include('help.php'); get_help(); } else {	?>
    <form method="post" id="bsbmadmin" action="options.php">
    <?php settings_fields( 'bsbm_admin_options' ); ?>
    <div class="postbox"><?php do_settings_sections( 'bsbm_forms' ); ?></div>
    <div class="postbox"><?php do_settings_sections( 'bsbm_main' ); ?></div>	
    <div class="postbox"><?php do_settings_sections( 'bsbm_beta' ); ?></div>	
    </form>
    <?php } ?>
    </div></div>
    </div>
    
    <div class="postbox-container" style="width:26%; margin-left:10px;">
    <div class="metabox-holder">	
    <div class="meta-box-sortables">
    <?php bsbm_postbox_support(); ?>	

    <?php bsbm_postbox_uninstall(); ?>	
    </div></div>
    </div>
    </div>
    
    <?php
    

}

function bsbm_register_settings() {
    register_setting( 'bsbm_admin_options', 'bsbm_options','bsbm_options_validate');
    
    add_settings_section('bsbm_forms', 'Form Settings', 'bsbm_forms_settings', 'bsbm_forms');	
    add_settings_section('bsbm_main', 'Message Settings', 'bsbm_main_settings', 'bsbm_main');
    add_settings_section('bsbm_beta', 'Customize Comment Form Security Question Location (BETA)', 'bsbm_beta_settings', 'bsbm_beta');

}

function bsbm_forms_settings() { 
    $options = get_option('bsbm_options');
    echo '<div class="inside"><div class="intro"><p>Select the various forms the security question will appear on.</p></div>'; 
    echo '<fieldset>';
    echo '<dl><dt><label>Login forms:</label><p>Display security check on all login forms.</p></dt>
    <dd>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_login_form]" value="1"  ', checked('1', $options['bsbm_login_form']) ,' />
    <span>Yes</span>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_login_form]" value="0"  ', checked('0', $options['bsbm_login_form']) ,' />
    <span>No</span>	
    </dd></dl>';
    echo '<dl><dt><label>New user/new blog signup forms:</label><p>Display security check on the new user/new blog singup forms (network mode).</p></dt>
    <dd>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_signup_form]" value="1"  ', checked('1', $options['bsbm_signup_form']) ,' />
    <span>Yes</span>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_signup_form]" value="0"  ', checked('0', $options['bsbm_signup_form']) ,' />
    <span>No</span>	
    </dd></dl>';
    echo '<dl><dt><label>User Registration forms:</label><p>Display security check on user registration forms (single user mode).</p></dt>
    <dd>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_register_form]" value="1"  ', checked('1', $options['bsbm_register_form']) ,' />
    <span>Yes</span>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_register_form]" value="0"  ', checked('0', $options['bsbm_register_form']) ,' />
    <span>No</span>
    </dd></dl>';	
    
    echo '<dl><dt><label>Comment form:</label><p>Display security check on all comment forms.</p></dt>
    <dd>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_comment_form]" value="1"  ', checked('1', $options['bsbm_comment_form']) ,' />
    <span>Yes</span>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_comment_form]" value="0"  ', checked('0', $options['bsbm_comment_form']) ,' />
    <span>No</span>
    </dd></dl>';
    echo '<dl><dt><label>Override CSS styling:</label><p>Check this box if you wish to use your own css styling.</p></dt>
    <dd>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_override_css]" value="1"  ', checked('1', $options['bsbm_override_css']) ,' />
    <span>Yes</span>
    <input class="radio" type="radio"  name="bsbm_options[bsbm_override_css]" value="0"  ', checked('0', $options['bsbm_override_css']) ,' />
    <span>No</span>
    </dd></dl>';	
    echo '<dl><dt><label>Min Random Value:</label><p>Default value is 2</p></dt>
    <dd><input name="bsbm_options[bsbm_mathvalue0]" type="text" size="5" maxlength="5" value="'. $options['bsbm_mathvalue0'] .'" /></dd></dl>';	
    echo '<dl><dt><label>Max Random Value:</label><p>Default value is 15</p></dt>
    <dd><input name="bsbm_options[bsbm_mathvalue1]" type="text" size="5" maxlength="5" value="'. $options['bsbm_mathvalue1'] .'" /></dd></dl>';	
    
    echo '<dl><dt><label>Tab Index:</label><p>Assigns a tabindex value to the security form field. The default value is 5.</p></dt>
    <dd><input name="bsbm_options[bsbm_tabindex]" type="text" size="5" maxlength="5" value="'. $options['bsbm_tabindex'] .'" /></dd></dl>';	
    echo '<dl><dt><label>Exclude WP Role:</label><p>Minimum role a WP user must have to be exluded from having to answer the security check.</p></dt>
    <dd>
    <p class="sub1"><input class="radio" type="radio"  name="bsbm_options[bsbm_wp_role]" value="manage_options"  ', checked('manage_options', $options['bsbm_wp_role']) ,' />
    <span>Administrator</span></p>
    <p class="sub1"><input class="radio" type="radio"  name="bsbm_options[bsbm_wp_role]" value="publish_pages"  ', checked('publish_pages', $options['bsbm_wp_role']) ,' />
    <span>Editor</span></p>
    <p class="sub1"><input class="radio" type="radio"  name="bsbm_options[bsbm_wp_role]" value="publish_posts"  ', checked('publish_posts', $options['bsbm_wp_role']) ,' />
    <span>Author</span></p>
    <p class="sub1"><input class="radio" type="radio"  name="bsbm_options[bsbm_wp_role]" value="edit_posts"  ', checked('edit_posts', $options['bsbm_wp_role']) ,' />
    <span>Contributor</span></p>
    <p class="sub1"><input class="radio" type="radio"  name="bsbm_options[bsbm_wp_role]" value="read"  ', checked('read', $options['bsbm_wp_role']) ,' />
    <span>Subscriber</span></p>	
    </dd></dl>';	
    echo '</fieldset><div style="clear:both;"></div>';
    if (get_bloginfo('version') >= '3.1') { submit_button('Save Changes'); } else { echo '<input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes"  />'; }
    echo '</div>';
} 


function bsbm_main_settings() { 
    $options = get_option('bsbm_options');
    echo '<div class="inside"><div class="intro"><p>Customize the error/notice messages displayed.</p></div>'; 
    echo '<fieldset>';
    echo '<dl><dt><label>Empty Field Error Message:</label><p>This is the text that will be displayed if the security question field is left empty.<br />
    <em>You can use html here.</em></p></dt><dd><textarea name="bsbm_options[bsbm_empty_error]"  style="width:100%" rows="4">'. $options['bsbm_empty_error'] .'</textarea></dd></dl>';
    echo '<dl><dt><label>Incorrect Answer Error Message:</label><p>Display security check on the new user/new blog singup forms (network mode).<br />
    <em>You can use html here.</em></p></dt><dd><textarea name="bsbm_options[bsbm_answer_error]"  style="width:100%" rows="4">'. $options['bsbm_answer_error'] .'</textarea></dd></dl>';
    
    echo '<dl><dt><label>Notice Message:</label><p>Notice message displayed below security field.<br />
    <em>You can use html here.</em></p></dt><dd><textarea name="bsbm_options[bsbm_notice_message]"  style="width:100%" rows="4">'. $options['bsbm_notice_message'] .'</textarea></dd></dl>';
    
    echo '</fieldset><div style="clear:both;"></div>';
    if (get_bloginfo('version') >= '3.1') { submit_button('Save Changes','secondary'); } else { echo '<input type="submit" name="submit" id="submit" class="button-secondary" value="Save Changes"  />'; }	
    echo '</div>';

} 


function bsbm_beta_settings() { 

    $options = get_option('bsbm_options');
    echo '<div class="inside"><div class="intro"><p>The settings below are considered beta and may or may not work for you.</p></div>'; 
    echo '<fieldset>';
    echo '<dl><dt><label>Use default hook location:</label><p>Use the default Wordpress hook placement</p></dt><dd><input class="radio" type="radio"  name="bsbm_options[bsbm_hook_location]" value="1" ', checked('1', $options['bsbm_hook_location']) ,' /></dd></dl>';
    echo '<dl><dt><label>Use a custom hook location:</label><p>Use this option if your theme already has a custom hook location (ie. Thesis theme has do_action( \'thesis_hook_after_comment_box\' ); You would place thesis_hook_after_comment_box in the field above.</p></dt><dd><input class="radio" type="radio"  name="bsbm_options[bsbm_hook_location]" value="2" ', checked('2', $options['bsbm_hook_location']) ,' />
    <span><input name="bsbm_options[bsbm_customhook]" type="text" size="25" maxlength="95" value="'. $options['bsbm_customhook'] .'" /></span>
    <ul><p class="sub1">(ex: thesis_hook_after_comment_box )</p></ul>
    </dd></dl>';
    
    echo '<dl><dt><label>Manually add a custom hook:</label><p>Allows you to manually add a hook location.</p><p style="color:red;"><b>IMPORTANT!!!</b> In order to use this option you must add a do_action to a core file.</p></dt><dd><input class="radio" type="radio"  name="bsbm_options[bsbm_hook_location]" value="3"  ', checked('3', $options['bsbm_hook_location']) ,' />	
    <ul>
    <p class="sub2">Assuming you are using the default Wordpress theme (twentyten) open your <b>wp-includes/comment-template.php</b> file. <em>You may also have to locate the appropriate file based on the theme you are using.</em></p>
    <p><b>Find:</b> <em>(approx. line 1573)</em>';
    
    $mycode = '<?php echo $args[\'comment_notes_after\']; ?>';
    $mycode = htmlentities($mycode);
    echo '<pre style="color:green;">'.$mycode.'</pre>
    </p>
    <p style="padding-top:15px;"><b>Add After That:</b>';
    
    $mycode2 = '<?php do_action( \'after_comment_box\' ); ?>';
    $mycode2 = htmlentities($mycode2);
    echo '<pre style="color:blue;">'.$mycode2.'</pre>
    </p>	
    </ul></dd></dl>';	
    
    
    echo '</fieldset><div style="clear:both;"></div>';
    if (get_bloginfo('version') >= '3.1') { submit_button('Save Changes','secondary'); } else { echo '<input type="submit" name="submit" id="submit" class="button-secondary" value="Save Changes"  />'; }	
    echo '</div>';

} 





function bsbm_admin_message() { 
    global $bsbm_fullpath;
    $options = get_option('bsbm_options');
    // Check to see if the CSS override is true
    if ($options['bsbm_override_css'] == true) { ?>
    <div style="border:2px solid #888888;margin-bottom:10px;background-color:#fefdf6;padding:2px;">
    <strong style="margin-left:5px;">Add the following CSS to your themes style sheet and customize to your liking:</strong>
    <p>
    #bsbm_form { clear:both; margin:20px 0; }<br />
    #bsbm_form label { font-size: 16px; font-weight:bold; color: #999; margin:0; padding:10px 0;}<br />
    #bsbm_form .question { font-size: 14px; font-weight:normal; margin:0; padding:5px 0;}<br />
    #bsbm_form .answer { font-size: 12px; }<br />
    #bsbm_form .notice { font-size: 11px; }
    </p>
    </div>
    
    <?php }

}


 // On uninstall all Block Spam By Math Reloaded options will be removed from database
function bsbm_uninstall() {

    delete_option( 'bsbm_version' );
    delete_option( 'bsbm_options' );
    delete_option( 'bsbm_registered' );
    delete_option( 'bsbm_name' );
    delete_option( 'bsbm_email' );
    
    $current = get_option('active_plugins');
    array_splice($current, array_search( $_POST['plugin'], $current), 1 ); // Array-function!
    update_option('active_plugins', $current);
    header('Location: plugins.php?deactivate=true');
}
    
function bsbm_build_postbox( $id, $title, $content, $ech = TRUE ) {
    
    $output  = '<div id="bsbm_' . $id . '" class="postbox">';
    $output .= '<div class="handlediv" title="Click to toggle"><br /></div>';
    $output .= '<h3 class="hndle"><span>' . $title . '</span></h3>';
    $output .= '<div class="inside">';
    $output .= $content;
    $output .= '</div></div>';
    
    if ( $ech === TRUE )
    echo $output;
    else
    return $output;

}



function bsbm_postbox_support() {
    $output  = '<p>' . __( 'If you require support, or would like to contribute to the further development of this plugin, please choose one of the following;', 'bsbm' ) . '</p>';
    $output .= '<ul style="list-style:circle;margin-left:25px;">';
    $output .= '<li><a href="http://www.jamespegram.com/">' . __( 'Author Homepage', 'bsbm' ) . '</a></li>';
    $output .= '<li><a href="http://www.jamespegram.com/block-spam-by-math-reloaded/">' . __( 'Plugin Homepage', 'bsbm' ) . '</a></li>';
    $output .= '<li><a href="http://wordpress.org/extend/plugins/block-spam-by-math-reloaded/">' . __( 'Rate This Plugin', 'bsbm' ) . '</a></li>';
    $output .= '<li><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=10845671">' . __( 'Donate To The Cause', 'bsbm' ) . '</a></li>';
    $output .= '</ul>';	
    bsbm_build_postbox( 'display_options', __( 'Support', 'bsbm' ), $output );	
}


function bsbm_postbox_uninstall() {
    $output  = '<form action="" method="post">';
    $output .= '<input type="hidden" name="plugin" id="plugin" value="block-spam-by-math-reloaded/block-spam-by-math-reloaded.php" />';
    
    if ( isset( $_POST['bsbm_uninstall'] ) && ! isset( $_POST['bsbm_uninstall_confirm'] ) ) {
        $output .= '<p class="error">' . __( 'You must check the confirm box before continuing.', 'bsbm' ) . '</p>';
    }
    
    $output .= '<p>' . __( 'The options for this plugin are not removed on deactivation to ensure that no data is lost unintentionally.', 'bsbm' ) . '</p>';
    $output .= '<p>' . __( 'If you wish to remove all plugin information for your database be sure to run this uninstall utility first.', 'bsbm' ) . '</p>';
    $output .= '<p class="aside"><input type="checkbox" name="bsbm_uninstall_confirm" value="1" /> ' . __( 'Please confirm before proceeding.', 'bsbm' ) . '</p>';
    $output .= '<p class="bsbm_submit center"><input type="submit" name="bsbm_uninstall" class="button-secondary" value="' . __( 'Uninstall', 'bsbm' ) . '" /></p>';
    
    $output .= '</form>';
    bsbm_build_postbox( 'display_options', __( 'Uninstall Plugin', 'bsbm' ), $output );	
}


function bsbm_hash($string) {
global $bsbm_salt;
return crypt($string, $bsbm_salt);
}

?>