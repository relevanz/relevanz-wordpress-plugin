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
    }

    protected $options = array();
    const MENU_LABEL = 'Releva tracking';
    const MENU_POSITION = '5';
    const RELEVATRC_KEY_URL = 'https://backend.releva.nz/v1/campaigns/get';

    const FRONTEND_URL = 'https://frontend.releva.nz/';

    /**
     * Validate an API key against the releva.nz backend (dev guide §5), sending
     * this site's callback URL. Server-to-server only.
     *
     * Returns array('status' => 'valid'|'invalid'|'unreachable'|'error', ...):
     * 'user_id' when valid, 'http_code' + 'message' for backend errors.
     */
    public static function verifyApiKey($apikey, $timeout = 10)
    {
        $apikey = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$apikey);
        if ($apikey === '') {
            return array('status' => 'invalid');
        }

        $queryParams = array(
            'apikey'       => $apikey,
            'callback-url' => site_url('?releva_action=callback'),
        );
        $data = self::getUrl(self::RELEVATRC_KEY_URL . '?' . http_build_query($queryParams), $timeout);
        if ($data['errno'] || $data['response'] === false) {
            return array('status' => 'unreachable');
        }

        $code = isset($data['info']['http_code']) ? (int)$data['info']['http_code'] : 0;
        $response = json_decode($data['response']);
        if (($code === 200 || $code === 304) && is_object($response) && isset($response->user_id) && is_numeric($response->user_id)) {
            return array('status' => 'valid', 'user_id' => (string)$response->user_id);
        }
        if ($code === 401) {
            return array('status' => 'invalid');
        }
        return array(
            'status'    => 'error',
            'http_code' => $code,
            'message'   => is_object($response) && isset($response->message) ? (string)$response->message : '',
        );
    }

    public function get_id()
    {
        return $this->plugin_name;
        //return 'st_' . str_replace( '-', '_', basename( __DIR__ ) );
    }

    public function register_settings()
    {
        $callbacks = array(
            'api_key'         => 'api_opt_validate',
            'additional_html' => 'additional_html_validate',
            'active'          => 'active_validate',
        );
        foreach ($this->options as $option) {
            register_setting($this->get_id() . '_group', $this->get_id() . '_' . $option['name'], array($this, $callbacks[$option['name']]));
        }
    }

    /**
     * Sanitize callback for the API key. Validates against the backend only when
     * the key changed (dev guide §4.2); on failure the previous key is kept.
     */
    public function api_opt_validate($input)
    {
        // WordPress runs the sanitize callback twice when the option is created
        // (update_option -> add_option); validate and report only once.
        static $done = array();
        $key = sanitize_text_field((string)$input);
        if (array_key_exists($key, $done)) {
            return $done[$key];
        }

        $previous = (string)get_option('relevatracking_api_key');

        if ($key === '') {
            delete_option('relevatracking_client_id');
            add_settings_error($this->plugin_name, 'api_key', __('API key removed. Tracking is inactive until a valid key is saved.', $this->plugin_name), 'updated');
            return $done[$key] = '';
        }

        if ($key === $previous && (string)get_option('relevatracking_client_id') !== '') {
            add_settings_error($this->plugin_name, 'api_key', __('Settings saved successfully!', $this->plugin_name), 'updated');
            return $done[$key] = $key;
        }

        $check = self::verifyApiKey($key);
        switch ($check['status']) {
            case 'valid':
                update_option('relevatracking_client_id', $check['user_id']);
                add_settings_error($this->plugin_name, 'api_key', __('Settings saved successfully!', $this->plugin_name), 'updated');
                return $done[$key] = $key;
            case 'unreachable':
                $message = __('Could not reach releva.nz, please retry.', $this->plugin_name);
                break;
            case 'error':
                /* translators: 1: HTTP status code, 2: error message from releva.nz */
                $message = sprintf(__('releva.nz returned an error (HTTP %1$s): %2$s', $this->plugin_name), $check['http_code'], $check['message']);
                break;
            default:
                $message = __('Invalid Key!', $this->plugin_name);
        }
        if ($previous !== '') {
            $message .= ' ' . __('The previously saved API key is still in use.', $this->plugin_name);
        }
        add_settings_error($this->plugin_name, 'api_key', $message, 'error');
        return $done[$key] = $previous;
    }

    /**
     * Additional HTML is injected verbatim into every page, so changing it needs
     * the `unfiltered_html` capability — on multisite only network admins have
     * it. The tag-push endpoint (releva.nz support) is unaffected.
     */
    public function additional_html_validate($input)
    {
        $html = Relevatracking_Public::sanitizeAdditionalHtml((string)$input);
        $previous = (string)get_option('relevatracking_additional_html');
        // Browsers submit textarea line breaks as CRLF — not a change.
        $changed = str_replace("\r\n", "\n", $html) !== str_replace("\r\n", "\n", $previous);
        if ($changed && !current_user_can('unfiltered_html')) {
            add_settings_error($this->plugin_name, 'additional_html', __('You are not allowed to change the Additional HTML (on multisite only network administrators can). The previous value is kept.', $this->plugin_name), 'error');
            return $previous;
        }
        return $html;
    }

    public function active_validate($input)
    {
        return empty($input) ? '0' : '1';
    }

    public function load_options()
    {
        $option = array();

        //relevatracking_api_key
        $option['name'] = 'api_key';
        $option['id'] = $this->get_id() . '_' . $option['name'];
        $option['type'] = 'text';
        $option['label'] = __('API Key', $this->plugin_name);
        $option['hint'] = __('Please do not forget to enter your API Key', $this->plugin_name);
        $option['value'] = get_option($option['id']);
        $this->options[$option['id']] = $option;

        $option2 = array();
        $option2['name'] = 'additional_html';
        $option2['id'] = $this->get_id() . '_' . $option2['name'];
        $option2['type'] = 'textarea';
        $option2['label'] = __('Additional HTML', $this->plugin_name);
        $option2['hint'] = __('Enter additional html e.g. for Consent Plugin Integration', $this->plugin_name);
        $option2['value'] = get_option($option2['id']);
        $this->options[$option2['id']] = $option2;

        $option3 = array();
        $option3['name'] = 'active';
        $option3['id'] = $this->get_id() . '_' . $option3['name'];
        $option3['type'] = 'checkbox';
        $option3['label'] = __('Active', $this->plugin_name);
        $option3['hint'] = __('Uncheck to pause tracking without removing the configuration', $this->plugin_name);
        $option3['value'] = Relevatracking_Public::isActive();
        $this->options[$option3['id']] = $option3;
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
            array($this, 'render_admin_chart'),
            'dashicons-screenoptions',
            self::MENU_POSITION
        );

        add_submenu_page(
            $this->get_id() . '_menu',
            __('Settings', $this->plugin_name),
            __('Settings', $this->plugin_name),
            'manage_options',
            $this->get_id() . '_settings',
            array($this, 'render_admin_menu')
        );
    }

    public function render_admin_menu()
    {
        echo $this->render('admin-menu');
    }

    /**
     * Multisite: the configuration is per site — name the site so the scope is
     * explicit (dev guide §16). Empty on single-site installs.
     */
    public function get_scope_label()
    {
        if (!is_multisite()) {
            return '';
        }
        /* translators: 1: site name, 2: site URL */
        return sprintf(__('Settings for site "%1$s" (%2$s). Every site of the network has its own releva.nz configuration.', $this->plugin_name), get_bloginfo('name'), home_url('/'));
    }

    /**
     * Multisite: other sites of the network using the same API key (dev guide §4.4).
     */
    public function get_sites_sharing_api_key()
    {
        $api_key = (string)get_option($this->plugin_name . '_api_key');
        if (!is_multisite() || $api_key === '') {
            return array();
        }
        $sites = array();
        foreach (get_sites(array('fields' => 'ids', 'number' => 0, 'site__not_in' => array(get_current_blog_id()))) as $site_id) {
            if ((string)get_blog_option($site_id, $this->plugin_name . '_api_key') === $api_key) {
                $sites[] = get_blog_option($site_id, 'blogname') . ' (' . get_home_url($site_id, '/') . ')';
            }
        }
        return $sites;
    }

    protected $iframe_url;
    public function render_admin_chart()
    {
        // Uses the key validated on save — no backend round trip per page view.
        $api_key = (string)get_option($this->plugin_name . '_api_key');
        $configured = $api_key !== '' && (string)get_option('relevatracking_client_id') !== '';
        $this->iframe_url = $configured
            ? 'https://frontend.releva.nz/token?' . http_build_query(array('token' => $api_key))
            : self::FRONTEND_URL;

        if (!$configured) {
            $dialog_received = __('If you already registered, you can find your API Key in your account and enter it here:', $this->plugin_name);
            $dialog_received .= ' <a href="' . esc_url(admin_url('admin.php?page=relevatracking_settings')) . '"><strong>' . __('Settings', $this->plugin_name) . '</strong></a>';
            $dialog_register = __('<a href="https://releva.nz" target="_blank">Not registered yet? Get started now!</a>', $this->plugin_name);
            echo '<div style="margin: 25px 20px 0 2px;" class="notice notice-warning"><p>' . $dialog_received . '</p><p>' . $dialog_register . '</p></div>' . "\n";
        }

        echo $this->render('admin-chart');
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

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/relevatracking-admin.css', array(), $this->version, 'all');
    }
}
