<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://releva.nz
 * @since      1.0.0
 *
 * @package    Relevatracking
 * @subpackage Relevatracking/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Relevatracking
 * @subpackage Relevatracking/admin
 * @author     Relevanz <tec@releva.nz>
 */
class Relevatracking_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_init', array($this, 'load_options'));

        //add_action( 'admin_init', array( $this, 'register_settings' ) );
        //add_action( 'admin_menu', array( $this, 'add_admin_menu_item' ) );
        //add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );

        // Am I active?
        if (isset($this->options[$this->get_id() . '_active'])) {
            $option_active = get_option($this->options[$this->get_id() . '_active']['id']);
            if ($option_active !== false and $option_active == false) {
                return;
            }
        }
    }

    protected $options = array();
    const MENU_LABEL = 'Releva tracking';
    const MENU_POSITION = '5';
    const RELEVATRC_KEY_URL = 'https://backend.releva.nz/v1/campaigns/get';

    // self::checkRelevaUser($apikey);
    public static function checkRelevaUser($apikey = '', $url = '', $timeout = 5)
    {
        $username = null;
        if (!$url) {
            $url = self::RELEVATRC_KEY_URL;
        }
        $queryParams['apikey'] = $apikey;

        $connectUrl = $url . '?' . http_build_query($queryParams);
        $data = self::getUrl($connectUrl, 5);

        $response = self::arrayGetValue($data, 'response');
        $response = json_decode($response);

        //echo "<pre>"; var_export($response); echo "</pre>";

        if (!empty($response) && is_object($response) && isset($response->user_id)) {
            $username = $response->user_id;
        }

        return $username;
    }

    public function get_id()
    {
        return $this->plugin_name;
        //return 'st_' . str_replace( '-', '_', basename( __DIR__ ) );
    }

    public function register_settings()
    {
        foreach ($this->options as $option) {
            register_setting($this->get_id() . '_group', $this->get_id() . '_' . $option['name'], array($this, 'api_opt_validate'));
        }
    }

    public function api_opt_validate($input)
    {
        $api_key_post = sanitize_text_field($this->butler_post('relevatracking_api_key'));

        if ($api_key_post) {
            $check_user = self::checkRelevaUser($api_key_post);
            $compare = get_option('relevatracking_api_key');

            if (!empty($check_user) && is_numeric($check_user) && $relevatracking_client_id = intval($check_user)) {
                $_POST['relevatracking_client_id'] = $relevatracking_client_id;

                if (false === get_option('relevatracking_client_id')) {
                    add_option('relevatracking_client_id', $relevatracking_client_id);
                } else {
                    update_option('relevatracking_client_id', $relevatracking_client_id);
                }

                add_settings_error($this->plugin_name, 'api_key', __('Settings saved successfully!', $this->plugin_name), 'updated');
                return sanitize_text_field($input);
            } else {
                //$mess = __('Error saving settings', $this->plugin_name);
                //$mess .= ".\n\t" . __('Invalid Key!', $this->plugin_name);
				$mess = __('Invalid Key!', $this->plugin_name).' ( '.$api_key_post.' )';
                add_settings_error($this->plugin_name, 'api_key', $mess, 'error');
                //Return $compare;
                return;
            }
        } else {
            //empty
            add_settings_error($this->plugin_name, 'api_key', __('Invalid Key!', $this->plugin_name), 'error');
            return $compare;
        }

        return sanitize_text_field($input);
    }

    public function load_options()
    {
        $option = array();

        /*
        $option['name' ] = 'client_id';
        $option['id'   ] = $this->get_id() . '_' . $option['name'];
        $option['type' ] = 'text';
        $option['label'] = __( 'Client ID', $this->plugin_name);
        $option['hint']  = __( 'Please do not forget to enter your client ID' , $this->plugin_name);
        $option['value'] = get_option( $option['id'] );
        $this->options[ $option['id'] ] = $option;
         */

        //relevatracking_api_key
        $option['name'] = 'api_key';
        $option['id'] = $this->get_id() . '_' . $option['name'];
        $option['type'] = 'text';
        $option['label'] = __('API Key', $this->plugin_name);
        $option['hint'] = __('Please do not forget to enter your API Key', $this->plugin_name);
        $option['value'] = get_option($option['id']);
        $this->options[$option['id']] = $option;
    }

    public function add_admin_menu_item()
    {

        // Add your own main menu item
        // Releva tracking
        add_menu_page(
            __('releva.nz', $this->plugin_name),
            __('releva.nz', $this->plugin_name),
            'manage_options',
            $this->get_id() . '_menu',
            array($this, 'render_admin_menu'),
            'dashicons-screenoptions',
            self::MENU_POSITION
        );

        add_submenu_page($this->get_id() . '_menu', __('Settings', $this->plugin_name), __('Settings', $this->plugin_name), 'manage_options', $this->get_id() . '_menu');

        add_submenu_page(
            $this->get_id() . '_menu',
            __('releva.nz', $this->plugin_name),
            __('releva.nz', $this->plugin_name),
            'manage_options',
            $this->get_id() . '_chart',
            array($this, 'render_admin_chart')
        );
    }

    public function render_admin_menu()
    {
        echo $this->render('admin-menu');
    }

    protected $api_key;
	protected $client_id;
    public function render_admin_chart()
    {
        $this->api_key = get_option($this->plugin_name . '_api_key');
		//$this->api_key = $this->api_key.'_';

            $check_user = null;
			if(!empty($this->api_key)) {
			$check_user = self::checkRelevaUser($this->api_key);
			}

            if (!empty($check_user) && is_numeric($check_user) && $relevatracking_client_id = intval($check_user)) {
			   $this->client_id = $relevatracking_client_id;
              echo $this->render('admin-chart');
			}else {

			//$this->api_key = '';

			echo '<div style="margin: 25px 20px 0 2px;" id="setting-error-api_key" class="error settings-error"><p><strong>' .__('Invalid Key!', $this->plugin_name) . '</strong></p></div>' . "\n";


			$dialog_received = __('If you are already registered and have received our key, enter it in the following:', $this->plugin_name);
			$dialog_received .=' <a href="admin.php?page=relevatracking_menu"><strong>' .__('Settings', $this->plugin_name) . '</strong></a>';

            $dialog_register = __('<a href="https://releva.nz" target="_blank">Still unable to register. Now catch up</a>', $this->plugin_name);

			echo '<div style="margin: 25px 20px 0 2px;" id="setting-error-api_key" class="update-nag"><p>' .$dialog_received . '</p><p>' .$dialog_register . '</p></div>' . "\n";
			}

    }

    public function render($view_file)
    {
        ob_start();
        include plugin_dir_path(__FILE__) . 'partials/' . $view_file . '.php';
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }

    public function add_admin_notice_OLD($text)
    {
        $this->admin_notices[] = __($text, $this->get_id());
    }

    /**
     * @version 1.1.0
     */
    public function add_admin_notice($text, $type = 'success')
    {
        $valid_types = array();
        $valid_types[] = 'success';
        $valid_types[] = 'error';
        if (in_array($type, $valid_types)) {
            $notice = array();
            $notice['text'] = __($text, $this->get_id());
            $notice['type'] = $type;
            $this->admin_notices[] = $notice;
        }
    }

    public function render_admin_notices()
    {
        return;
        static $once = false;
        echo $this->render('admin-notices');
        $once = true;
    }

    public function butler_get($name, $array = false, $default = null)
    {
        if ($array === false) {
            $array = $_GET;
        }

        if ((is_string($name) || is_numeric($name)) && !is_float($name)) {
            if (is_array($array) && isset($array[$name])) {
                return $array[$name];
            } elseif (is_object($array) && isset($array->$name)) {
                return $array->$name;
            } elseif (is_string($array)) {
                if ($name == $array) {
                    return true;
                } else {
                    return false;
                }
            }
        }

        return $default;
    }

    public function butler_post($name, $data = null)
    {
        if ($data) {
            $_POST = $data;
        }

        return $this->butler_get($name, $_POST);
    }

    public function add_message($text, $type = '', $position = null)
    {
        $message = array();
        $message['text'] = $text;
        $message['type'] = $type;

        if (isset($position)) {
            $messages_1 = array_slice($this->messages, 0, $position);
            $messages_2 = array_slice($this->messages, $position, count($this->messages) - $position);
            $this->messages = array_merge($messages_1, array($message), $messages_2);
        } else {
            $this->messages[] = $message;
        }
    }

    public function get_messages()
    {
        return $this->messages;
    }

    private static function getUrl($url, $timeout = 5)
    {
        $curl_opts = array(
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => true,
        );
        $ch = curl_init();
        curl_setopt_array($ch, $curl_opts);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $result = array(
            'response' => $response,
            'info' => $info,
            'errno' => $errno,
            'error' => $error,
        );
        return $result;
    }

    public static function arrayGetValue(&$array, $name, $default = null, $type = '')
    {
        $result = null;

        if (isset($array[$name])) {
            $result = $array[$name];
        }

        // Handle the default case
        if (is_null($result)) {
            $result = $default;
        }

        // Handle the type constraint
        switch (strtoupper($type)) {
            case 'INT':
            case 'INTEGER':
                // Only use the first integer value
                @preg_match('/-?[0-9]+/', $result, $matches);
                $result = @(int) $matches[0];
                break;

            case 'FLOAT':
            case 'DOUBLE':
                // Only use the first floating point value
                @preg_match('/-?[0-9]+(\.[0-9]+)?/', $result, $matches);
                $result = @(float) $matches[0];
                break;

            case 'BOOL':
            case 'BOOLEAN':
                $result = (bool) $result;
                break;

            case 'ARRAY':
                if (!is_array($result)) {
                    $result = array($result);
                }
                break;

            case 'STRING':
                $result = (string) $result;
                break;

            case 'WORD':
                $result = (string) preg_replace('#\W#', '', $result);
                break;

            case 'NONE':
            default:
                // No casting necessary
                break;
        }
        return $result;
    }

    public function add_general_settings()
    {
        if (!empty($_POST)) {
            $client_id = sanitize_text_field($this->butler_post('client_id'));
            $api_key = sanitize_text_field($this->butler_post('api_key'));

            $client_id_opt = 'relevatracking_client_id';
            $api_key_opt = $this->plugin_name . '_api_key';

            update_option($client_id_opt, $client_id);
            update_option($api_key_opt, $api_key);

            if (get_option($client_id_opt)) {
                echo '1';
            } else {
                echo '0';
            }
            exit();

            /*
        if( get_option( 'relevatracking_client_id' ) ) {
        update_option( 'relevatracking_client_id', 44 );
        } else {
        add_option( 'relevatracking_client_id', 55 , '', true );
        }

        // add $api_key
        if(get_option($api_key_opt)){
        update_option($api_key_opt, $api_key);
        }
        else {
        add_option($api_key_opt, $api_key, '', true );
        }
         */
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/relevatracking-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery-iframe-auto-height', plugin_dir_url(__FILE__) . 'js/jquery-iframe-auto-height.js', array('jquery'), $this->version, false);

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/relevatracking-admin.js', array('jquery'), $this->version, false);

        wp_localize_script($this->plugin_name, $this->plugin_name . '_opt', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'dialog_received' => __('If you are already registered and have received our key, enter it in the following:', $this->plugin_name),
            'dialog_register' => __('<a href="https://releva.nz" target="_blank">Still unable to register. Now catch up</a>', $this->plugin_name),
            'dialog_invalid' => __('Invalid Key!', $this->plugin_name),
            'dialog_ok' => __('Send', $this->plugin_name),
            'settings_saved' => __('Releva Settings Saved', $this->plugin_name),
            'settings_error' => __('Error saving settings', $this->plugin_name),
        ));
    }
}
