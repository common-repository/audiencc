<?php
/**
Plugin Name: WP Audiencc
Plugin URI: http://audien.cc/
Description: Create and manage an interactive and social mobile site from within WordPress.
Author: Audiencc
Version: 0.1
Author URI: http://audien.cc/
Text Domain: wp-audiencc

=== RELEASE NOTES ===
2009-11-11 – v0.1 – Initial Release
*/


class WPAudiencc {

    /**
     * Initalize the plugin by registering the hooks
     */
    function __construct() {

        // Load localization domain
        load_plugin_textdomain( 'wp-audiencc', false, dirname(plugin_basename(__FILE__)) . '/languages' );

        // Register hooks
        add_action( 'admin_menu', array(&$this, 'register_settings_page') );
        add_action( 'admin_init', array(&$this, 'add_settings') );

        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_$plugin", array(&$this, 'add_action_links'));

        add_action('wp_head', array(&$this, 'add_embed_code'));
        add_action('wp_footer', array(&$this, 'add_toolbar_code'));

        // hook the admin notices action
        add_action( 'admin_notices', array(&$this, 'show_messages'));

        add_action('wp_ajax_check_availability', array(&$this, 'check_availability_callback'));

        $options = get_option('audiencc-options');
        if ($options['auto-detect'] == "true") {

            if (!is_admin() && stristr($_SERVER['REQUEST_URI'], 'wp-login.php') === FALSE) {
                $this->detect_mobile();
            }
        }

    }

    function check_availability_callback() {
        $subdomain = $_POST['subdomain'];
        
        if (!class_exists('AudienccAPI')) {
            include_once(dirname(__FILE__). '/php/AudienccAPI.class.php');
        }

        $api = new AudienccAPI();
        $response = $api->check_availability($subdomain);
        $response = json_decode($response);

        $available = $response->subdomain->available;

        // This assumes that the api return 1 instead of true
        if ($available == "1") {
            die(' -  (Available)');
        } else {
            die (' - (Already taken. Choose another)');
        }

    }

    /**
     * Register the settings page
     */
    function register_settings_page() {
        $page = add_options_page( __('WP Audiencc', 'wp-audiencc'), __('WP Audiencc', 'wp-audiencc'), 8, 'wp-audiencc', array(&$this, 'settings_page') );

        /* Using registered $page handle to hook script load */
        add_action('admin_print_scripts-' . $page, array(&$this, 'add_script'));
    }

    function add_script() {
        wp_enqueue_script('wp-audiencc', plugins_url('js/wp-audiencc.js', __FILE__), array('jquery'), '1.0');
    }

    /**
     * Embed code in header
     */
    function add_embed_code() {
        $options = get_option('audiencc-options');

        $domain_ownership_token  = $options['domain_ownership_token'];
        echo "<meta name='audiencc-site-verification' content='$domain_ownership_token' />";
    }

    function add_toolbar_code() {
        $options = get_option('audiencc-options');

        if ($options['toolbar'] == "true") {
            $toolbar_url = $options['toolbar_url'];
            echo "<script type='text/javascript' src='$toolbar_url'></script>";
        }
    }

    /**
     * add options
     */
    function add_settings() {
        // Register options
        register_setting( 'wp-audiencc', 'audiencc-options', array(&$this, 'validate_options'));
    }


    /**
     * Validate Options
     * 
     * @param <type> $input
     * @return <type>
     */
    function validate_options($input) {
        $old_options = get_option('audiencc-options');
        if (isset($_POST['wp-audiencc-link'])) {
            // Handle Login

            $login_username = $input['login-username'];
            $login_password = $input['login-password'];

            if (!class_exists('AudienccAPI')) {
                include_once(dirname(__FILE__). '/php/AudienccAPI.class.php');
            }

            $api = new AudienccAPI($login_username, $login_password);
            $account_info = $api->get_account_data();
            $account_info = json_decode($account_info);
            if ($api->errorMessage != '') {
                $input['linked'] = 'false';
                $input['linked-username'] = '';
                if ($api->errorCode == '401') {
                    $input['error_msg'] = 'Username and password is not correct.';
                } else {
                    $input['error_msg'] = $api->errorMessage;
                }
            } else {
                $input['linked'] = 'true';
                $input['linked-username'] = $login_username;
                $input['mobile_url'] = $account_info->account->mobile_url;
                $input['toolbar_url'] = $account_info->account->toolbar_url;
                $input['domain_ownership_token'] = $account_info->account->domain_ownership_token;
                $input['twitter'] = $account_info->account->twitter_username;
            }
        } else if (isset ($_POST['wp-audiencc-enable'])) {
            $input['toolbar'] = ($input['toolbar'] == 'true') ? 'true' :'false';
            $input['auto-detect'] = ($input['auto-detect'] == 'true') ? 'true' :'false';
            $input['linked']  = $old_options['linked'];
            $input['linked-username']  = $old_options['linked-username'];
            $input['mobile_url']  = $old_options['mobile_url'];
            $input['toolbar_url']  = $old_options['toolbar_url'];
            $input['domain_ownership_token']  = $old_options['domain_ownership_token'];
        } else if (isset ($_POST['wp-audiencc-create'])) {
            $account_info = array(
                'account[name]' => $input['name'],
                'account[subdomain]' => $input['subdomain'],
                'account[url]' => get_bloginfo('url'),
                'account[feed_url]' => $input['feed-url'],
                'account[twitter_username]'  => $input['twitter'],
                'account[password]'  => $input['password'],
                'account[email]'  => $input['email'],
                'account[description]'  => $input['description']
            );

            if (!class_exists('AudienccAPI')) {
                include_once(dirname(__FILE__). '/php/AudienccAPI.class.php');
            }

            $api = new AudienccAPI();
            $new_account = $api->create_account($account_info);
            $new_account = json_decode($new_account);

            if ($api->errorMessage != '') {
                $input['linked'] = 'false';
                $input['linked-username'] = '';
//                $input['error_msg'] = __('There was some problem in creating your account.', 'wp-audiencc');
                $input['error_msg'] = $api->errorMessage;
//                }
            } else {
                $input['linked'] = 'true';
                $input['linked-username'] = $account_info->account->email;
                $input['mobile_url'] = $account_info->account->mobile_url;
                $input['toolbar_url'] = $account_info->account->toolbar_url;
                $input['domain_ownership_token'] = $account_info->account->domain_ownership_token;
                $input['twitter'] = $account_info->account->twitter_username;
            }
        } else if (isset($_POST['wp-audiencc-change'])) {

            $input['linked']  = $old_options['linked'];
            $input['linked-username']  = $old_options['linked-username'];
            $input['mobile_url']  = $old_options['mobile_url'];
            $input['toolbar_url']  = $old_options['toolbar_url'];
            $input['domain_ownership_token']  = $old_options['domain_ownership_token'];

            $username = $old_options['linked-username'];
            $password = $input['password']; $input['password'] = '';

            $account_info = array(
                'account[name]' => $input['name'],
                'account[feed_url]' => $input['feed-url'],
                'account[twitter_username]'  => $input['twitter'],
                'account[password]'  => $input['password'],
                'account[description]'  => $input['description'],
                '_method' => 'put'
            );

            if (!class_exists('AudienccAPI')) {
                include_once(dirname(__FILE__). '/php/AudienccAPI.class.php');
            }

            $api = new AudienccAPI($username, $password);

            $new_values = $api->update_account($account_info);
            $new_values = json_decode($new_values);

            if ($api->errorMessage != '') {
                $input['linked'] = 'false';
                $input['linked-username'] = '';
//                $input['error_msg'] = __('There was some problem in creating your account.', 'wp-audiencc');
                $input['error_msg'] = $api->errorMessage;
//                }
            } else {
                $input['name'] = $new_values->account->name;
                $input['feed-url'] = $new_values->account->feed_url;
                $input['twitter'] = $new_values->account->twitter_username;
                $input['description'] = $new_values->account->description;
            }
        }

        return $input;
    }

    /**
     * admin notices
     */
    function show_messages() {
        $option = get_option('audiencc-options');
        if ($option['error_msg'] != '') {
            echo "<div class = 'updated'><p>" . $option['error_msg'] ."</p></div>";
            $option['error_msg'] = '';
            update_option('audiencc-options', $option);
        }
    }

    /**
     * Dipslay the Settings page
     */
    function settings_page() {
?>
        <div class="wrap">
            <div style = "background: url('<?php echo plugins_url('images/audiencc-logo.png', __FILE__); ?>');" class="icon32"><br /></div>
            <h2><?php _e( 'WP Audiencc Settings', 'wp-audiencc' ); ?></h2>

            <form id="audiencc_form" method="post" action="options.php">
                <?php settings_fields('wp-audiencc'); ?>
                <?php $options = get_option('audiencc-options'); ?>

<?php
                $linked = $options['linked'];
                if ($linked == "true") {

                    $linked_username = $options['linked-username'];
?>
                    <h3><?php _e( 'Linked Audiencc Account', 'wp-audiencc' ); ?></h3>
                    <p><?php _e("This WordPress installation is linked to ", 'wp-audiencc');?><?php echo $linked_username; ?><?php _e(" audiencc account.", 'wp-audiencc');?></p>
                    <table class="form-table">
                        <tr valign="top">
                            <td>
                                <label><input type="checkbox" name="audiencc-options[auto-detect]" value="true" <?php checked('true', $options['auto-detect']); ?> /> <?php _e("Enable auto-redirect for smartphone users", 'wp-audiencc');?></label>
                            </td>
                        </tr>

                        <tr valign="top">
                            <td>
                                <label><input type="checkbox" name="audiencc-options[toolbar]" value="true" <?php checked('true', $options['toolbar']); ?> /> <?php _e("Enable Toolbar", 'wp-audiencc');?></label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="wp-audiencc-enable" class="button-primary" value="<?php _e('Enable', 'wp-audiencc') ?>" />
                    </p>

                    <h3><?php _e( 'Edit Audiencc Account details (please also enter your password for any changes)', 'wp-audiencc' ); ?></h3>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e( 'Site Name', 'wp-audiencc' ); ?></th>
                            <td>
                                <label><input  class="regular-text" type="text" name="audiencc-options[name]" value="<?php echo $options['name']; ?>" /></label>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e( 'Site Description', 'wp-audiencc' ); ?></th>
                            <td>
                                <label><input  class="regular-text" type="text" name="audiencc-options[description]" value="<?php echo $options['description']; ?>" /></label>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">&nbsp;</th>
                            <td>
                                &nbsp;
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e( 'RSS Feed', 'wp-audiencc' ); ?></th>
                            <td>
                                <label><input  class="regular-text" type="text" name="audiencc-options[feed-url]" value="<?php echo $options['feed-url']; ?>" /></label>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e( 'Twitter', 'wp-audiencc' ); ?></th>
                            <td>
                                <label><input  class="regular-text" type="text" name="audiencc-options[twitter]" value="<?php echo $options['twitter']; ?>" /></label>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e( 'Password', 'wp-audiencc' ); ?></th>
                            <td>
                                    <label><input type="password" name="audiencc-options[password]" value="" /></label>
                            </td>
                        </tr>

                    </table>

                    <p class="submit">
                        <input type="submit" name="wp-audiencc-change" class="button-primary" value="<?php _e('Update', 'wp-audiencc') ?>" />
                    </p>

                    <div class='feedback'>
                        <h3>How can we improve?</h3>
                        <p>We have built Audiencc with your feedback. Please help us make this awesome:
                        <a href="http://bit.ly/accemail" class="button blue" target="_blank">Feedback</a></p>
                    </div>
<?php
                } else {
?>
                    <h3><?php _e( 'Link your existing Audiencc account', 'wp-audiencc' ); ?></h3>
                    <p><?php _E("If you already have an account with Audiencc then enter the details below.", 'wp-audiencc');?></p>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e( 'Email address', 'wp-audiencc' ); ?></th>
                            <td>
                                <label><input class="regular-text" type="text" name="audiencc-options[login-username]" value="<?php echo $options['login-username']; ?>" /></label>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e( 'Password', 'wp-audiencc' ); ?></th>
                            <td>
                                <label><input  class="regular-text" type="password" name="audiencc-options[login-password]" value="" /></label>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="wp-audiencc-link" class="button-primary" value="<?php _e('Link', 'wp-audiencc') ?>" />
                    </p>

                <h3><?php _e( 'Register for a new account', 'wp-audiencc' ); ?></h3>
                <p><?php _e("If you don't have an account already then you can register for free" ,'wp-audiencc');?></p>
                <h3><?php _e('Sign up: ' ,'wp-audiencc'); echo get_bloginfo('url'); ?></h3>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e( 'Site Name', 'wp-audiencc' ); ?></th>
                        <td>
                            <label><input class="regular-text"  type="text" name="audiencc-options[name]" value="<?php echo get_bloginfo('name'); ?>" /></label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e( 'RSS Feed', 'wp-audiencc' ); ?></th>
                        <td>
                            <label><input class="regular-text"  type="text" name="audiencc-options[feed-url]" value="<?php echo get_bloginfo('rss2_url'); ?>" /></label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e( 'Twitter', 'wp-audiencc' ); ?></th>
                        <td>
                            <label><input  class="regular-text" type="text" name="audiencc-options[twitter]" value="<?php  ?>" /></label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e( 'Email', 'wp-audiencc' ); ?></th>
                        <td>
                            <label><input class="regular-text"  type="text" id="wpau_email" name="audiencc-options[email]" value="<?php echo get_option('admin_email'); ?>" /></label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e( 'Password', 'wp-audiencc' ); ?></th>
                        <td>
                            <label><input type="password" id="password" name="audiencc-options[password]" value="" /></label>
                            <label><input type="password" id ="confirm-password" name="confirm-password" value="" /></label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e( 'Subdomain', 'wp-audiencc' ); ?></th>
                        <td>
                            <label><input  class="regular-text" type="text"  id="wpau_domain" name="audiencc-options[subdomain]" value="" /><?php _e('.audien.cc', 'wp-audiencc' ); ?></label> <span id="wpau_domain_msg"></span>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e( 'Site Description', 'wp-audiencc' ); ?></th>
                        <td>
                            <label><input  class="regular-text" type="text" name="audiencc-options[description]" value="<?php echo get_bloginfo('description'); ?>" /></label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"></th>
                        <td>
                            <label><input type="checkbox" name="terms" id="terms" value="true" /> <a href ="http://audien.cc/home/terms_of_service" target="_blank"><?php _e("Agree to Terms of Service", 'wp-audiencc');?></a></label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="wp-audiencc-create" id="wp-audiencc-create" class="button-primary" value="<?php _e('Create', 'wp-audiencc') ?>" />
                </p>
<?php
                }
?>
            </form>
        </div>
<?php
        // Display credits in Footer
        add_action( 'in_admin_footer', array(&$this, 'add_footer_links'));
    }

    /**
     * hook to add action links
     *
     * @param <type> $links
     * @return <type>
     */
    function add_action_links( $links ) {
        // Add a link to this plugin's settings page
        $settings_link = '<a href="options-general.php?page=wp-audiencc">' . __("Settings", 'wp-audiencc') . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Adds Footer links. Based on http://striderweb.com/nerdaphernalia/2008/06/give-your-wordpress-plugin-credit/
     */
    function add_footer_links() {
        $plugin_data = get_plugin_data( __FILE__ );
        printf('%1$s ' . __("plugin", 'wp-audiencc') .' | ' . __("Version", 'wp-audiencc') . ' %2$s | '. __('by', 'wp-audiencc') . ' %3$s<br />', $plugin_data['Title'], $plugin_data['Version'], $plugin_data['Author']);
    }

    /**
     * Detects mobile browser version. Adpated from wptouch
     *
     * @param <type> $query
     */
    function detect_mobile($query = '') {
        $container = $_SERVER['HTTP_USER_AGENT'];
        // The below prints out the user agent array. Uncomment to see it shown on the page.
        // Add whatever user agents you want here to the array if you want to make this show on another device.
        // No guarantees it'll look pretty, though!
                $useragents = array(
                "iphone",                                // Apple iPhone
                "ipod",                                          // Apple iPod touch
                "aspen",                                 // iPhone simulator
                "dream",                                 // Pre 1.5 Android
                "android",                       // 1.5+ Android
                "cupcake",                       // 1.5+ Android
                "blackberry9500",        // Storm
                "blackberry9530",        // Storm
                "opera mini",            // Experimental
                "webos",                                 // Experimental
                "incognito",                     // Other iPhone browser
                "webmate"                        // Other iPhone browser
        );

        foreach ( $useragents as $useragent ) {
            if ( eregi( $useragent, $container )) {
                    $option = get_option('audiencc-options');
                    $mobile_url = $option['mobile_url'];
                    header("Location: $mobile_url");
                    exit(0);
            }
        }
    }

    // PHP4 compatibility
    function WPAudiencc() {
        $this->__construct();
    }
}

// Start this plugin once all other plugins are fully loaded
add_action( 'init', 'WPAudiencc' ); function WPAudiencc() { global $WPAudiencc; $WPAudiencc = new WPAudiencc(); }
?>