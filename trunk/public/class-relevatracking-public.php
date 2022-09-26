<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://releva.nz
 * @since      1.0.0
 *
 * @package    Relevatracking
 * @subpackage Relevatracking/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Relevatracking
 * @subpackage Relevatracking/public
 * @author     Relevanz <tec@releva.nz>
 */
class Relevatracking_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		// Is there a client_id ?
		//$this->load_client_id();
		$this->client_id = self::load_client_id();

		add_action( 'releva_csvexport', array( $this, 'csvexport' ) );
		add_action( 'releva_callback', array( $this, 'callback' ) );

	}

	//const RELEVATRC_KEY_URL = 'http://api.hyj.mobi/user/get';
	protected $apikey;
	protected static $cache = array();

	public static function load_client_id() {

		/*
		$this->client_id = (string)get_option( $this->plugin_name . '_client_id' );
		if(empty($this->client_id)) {

		}
		*/

		$apikey = (string)get_option( 'relevatracking_api_key' );
		$storeId = md5(__METHOD__ . "::client_id::" . $apikey);


		if (!isset(self::$cache[$storeId])){

			if($apikey) {

			$queryParams['apikey'] = $apikey;
			$url='https://backend.releva.nz/v1/campaigns/get';
			$connectUrl = $url. '?' . http_build_query($queryParams);
			$data = self::getUrl($connectUrl, 5);
			
			 $response=self::arrayGetValue($data, 'response');
			 $response = json_decode ($response) ;

			if(!empty($response) && is_object($response) && isset($response->user_id)) {
               self::$cache[$storeId] = $response->user_id;
			}

			}

		}

		if (isset(self::$cache[$storeId])){
			return self::$cache[$storeId] ;
		} else {
			return 0;
		}

	}



	public function releva_init_action(){
		//releva_action=jsonexport
		$releva_action = isset($_GET['releva_action'])?$_GET['releva_action']:'';
		//echo "<pre>"; var_export($releva_action); echo "</pre>";
		// exit;
		if($releva_action) {
		$releva_action = preg_replace('/\W/', '', $releva_action);
		do_action('releva_'.$releva_action);
		}
       }

	public function getPhpVersion() {
		return [
			'version' => phpversion(),
			'sapi-name' => php_sapi_name(),
			'memory-limit' => ini_get( 'memory_limit' ),
			'max-execution-time' => ini_get( 'max_execution_time' ),
		];
	}

	public function getDbVersion() {
		global $wpdb;

		$version_comment = $wpdb->get_var( 'SELECT @@version_comment AS `server`' );
		return [
			//'version' => $wpdb->db_server_info(),
			'server' => $version_comment,
		];
	}

	public function getServerEnvironment() {
		return [
			'server-software' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null,
			'php' => $this->getPhpVersion(),
			'db' => $this->getDbVersion(),
		];
	}

	public function getCallbacks() {
		return [
			'callback' => [
				'url' => site_url('?releva_action=callback'),
				'parameters' => [],
			],
			'export' => [
				'url' => site_url('?releva_action=csvexport'),
				'parameters' => [
					'page' => [
						'type' => 'integer',
						'optional' => true,
						'info' => [
							'items-per-page' => 2500,
						],
					],
				],
			],
		];
	}

	public function callback() {
		$apikey = (string)get_option( 'relevatracking_api_key' );
		$client_id = (string)get_option( 'relevatracking_client_id' );
		$auth = isset($_GET['auth'])?$_GET['auth']:'';
		$page = isset($_GET['page'])?$_GET['page']:0;

		if( $auth != md5( $apikey.':'.$client_id ) )
		{
			exit;
		}

		ob_end_flush();
		ob_start();

		header('Content-Type: application/json');

		$wc_version = '';
		if( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
		{
			$wc_version = WC_VERSION;
		}
		$callback = [
			'plugin-version' => $this->version,
			'shop' => [
				'system' => 'WooCommerce',
				'version' => $wc_version,
			],
			'environment' => $this->getServerEnvironment(),
			'callbacks' => $this->getCallbacks(),
		];
		echo json_encode( $callback );

		echo ob_get_clean();
		exit;
	}


	public function csvexport() {
		$apikey = (string)get_option( 'relevatracking_api_key' );
		$client_id = (string)get_option( 'relevatracking_client_id' );
		$auth = isset($_GET['auth'])?$_GET['auth']:'';
		$page = isset($_GET['page'])?$_GET['page']:0;
		$limit = isset($_GET['limit'])?$_GET['limit']:500;

		if( $auth != md5( $apikey.':'.$client_id ) )
		{
			exit;
		}

		$args = array( 
			'status' => 'publish', 
			'limit' => -1, 
			'orderby' => 'id', 
			'order' => 'asc'
		);

		/*
		$args = array(
			'post_status'   => array('publish'),
			'fields'        => 'ids',
			'posts_per_page'=> -1,
			'post_type'     => array('product'),
			'orderby' => 'id',
			'order' => 'asc',
		);
		*/

		if( $page > 0 )
		{
			$args['limit'] = $limit;
			$args['page'] = $page;
		}

		$products = wc_get_products( $args );

		if(count($products) == 0) {
			header('HTTP/1.0 404 Not Found');
        	exit; 
		}

		// The Loop
		$numProducts = 0;
		global $wpdb;

		ob_end_flush();
		ob_start();

		header('Content-Type: text/csv');
		header('X-Relevanz-Product-Count: '.$the_query->found_posts);
		$op = fopen("php://output", "wb");
		$header = ['id','categoryIds','name','descriptionShort','descriptionLong','price','priceOffer','link','image','lastUpdate'];
		fputcsv($op, $header, ',', '"');


			//while ( $the_query->have_posts() ) {
			//$the_query->the_post();
			foreach ($products as $p) {
				$product_id = $p->id;
				//$the_query->post->ID = $product_id

				$single_product = array();
				$product = wc_get_product($product_id);
				if(empty($product) ) {
					continue;
				}
				$single_product['id'] = $product_id;
				//$single_product['product_id'] = $product->get_id();

				$post_categories = wp_get_post_terms($product_id, $taxonomy = 'product_cat');
				$cat = ''; $ii = 0;
				foreach((array)$post_categories as $post_category):
					if($ii > 0){$cat .= ',';}
					//$cat .= $post_category->name;
					$cat .= $post_category->term_id;
					$ii++;
				endforeach;
				$single_product['categoryIds'] = $cat;

				$single_product['name'] = $product->get_name();
				$single_product['descriptionShort'] = $product->get_short_description();
				//$single_product['descriptionLong'] = $product->get_description();
				$single_product['descriptionLong'] = '';


				$single_product['price'] = wc_get_price_including_tax( $product, array('price' => $product->get_regular_price() ) );
				//number_format( floatval( get_post_meta( $product_id, '_regular_price', true ) ), 2, '.', '' );
				$single_product['priceOffer'] = wc_get_price_including_tax( $product, array('price' => $product->get_sale_price() ) );
				//( get_post_meta( $product_id, '_sale_price', true ) ? number_format( floatval( get_post_meta( $product_id, '_sale_price', true ) ), 2, '.', '' ) : number_format( floatval( get_post_meta( $product_id, '_regular_price', true ) ), 2, '.', '' ) );

				$single_product['link'] = $product->get_permalink();

				$single_product['image'] = $this->get_images( $product )[0];

				$lastUpdate = get_the_modified_time( 'U', $product_id );
				if( empty( $lastUpdate ) )
				{
					$lastUpdate = get_the_time( 'U', $product_id );
				}
				$single_product['lastUpdate'] = $lastUpdate;

				fputcsv( $op, $single_product, ',', '"' );
			}

		fclose($op);

		echo ob_get_clean();
		exit;
	}

	/**
	 * Get the images for a product or product variation
	 *
	 * @since 2.1
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	private function get_images( $product ) {
		$images        = $attachment_ids = array();
		$product_image = $product->get_image_id();

		// Add featured image.
		if ( ! empty( $product_image ) ) {
			$attachment_ids[] = $product_image;
		}

		// add gallery images.
		$attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

		// Build image data.
		foreach ( $attachment_ids as $position => $attachment_id ) {

			$attachment_post = get_post( $attachment_id );

			if ( is_null( $attachment_post ) ) {
				continue;
			}

			$attachment = wp_get_attachment_image_src( $attachment_id, 'full' );

			if ( ! is_array( $attachment ) ) {
				continue;
			}

			$images[] = current( $attachment );
		}

		// Set a placeholder image if the product has no images set.
		if ( empty( $images ) ) {

			$images[] = wc_placeholder_img_src();
		}

		return $images;
	}


  /**
   * Returns thumbnail if it exists, if not, returns the WC placeholder image
   * @param int $id
   * @return string
   */
  private function get_one_thumbnail($id){
    $image = false;
    $thumb_id = get_post_thumbnail_id( $id );

    if( $thumb_id )
      $image = wp_get_attachment_image_src( $thumb_id, 'shop_thumbnail' );

    if( is_array($image) )
      return $image[0];

    return wc_placeholder_img_src();
  }


	public static function getUrl($url, $timeout = 5)
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

		if (isset($array[$name]))
		{
			$result = $array[$name];
		}

		// Handle the default case
		if (is_null($result))
		{
			$result = $default;
		}

		// Handle the type constraint
		switch (strtoupper($type))
		{
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
				if (!is_array($result))
				{
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


	protected $client_id ;
	public $url_js ;

	// Add tracking for pages:
	public function relevatracking() {
		/**
		 * Don't initialize the plugin when WooCommerce is not active.
		 */
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return;
		}

		// is there any option client_id
		if($this->client_id) {
			// FRONT_PAGE
			$this->retargeting_front_page();
			// CATEGORY
			$this->retargeting_category();
			// PRODUCT
			$this->retargeting_product();
			// PRODUCT
			$this->retargeting_cart();			
			// ORDER SUCCESS PAGE
			$this->retargeting_confirmation();
			// ANY OTHER PAGE
			$this->retargeting_other();
		}
	}


	// FRONT PAGE Index page
	public function retargeting_front_page() {
		$user_id = get_current_user_id();

   // URL:  https://pix.hyj.mobi/rt?t=d&action=s&cid=CLIENT_ID
		if ( is_front_page() ) {
			$this->url_js='https://pix.hyj.mobi/rt?t=d&action=s&cid='.$this->client_id;
			if($user_id != 0) {
				$this->url_js .= '&custid='.$user_id; 
			}
			echo $this->render( 'front-page' );
		}
	}

    // CATEGORY PAGE
	public function retargeting_category() {
		$user_id = get_current_user_id();

		// URL:  https://pix.hyj.mobi/rt?t=d&action=c&cid=CLIENT_ID&id=CATEGORY_ID
		//echo "<pre>is_product_taxonomy"; var_export(is_product_taxonomy() ); echo "</pre>";
		if ( function_exists('is_product_category') && is_product_category() ) {
         global $wp_query;
		 // get the query object
		 $cat = $wp_query->get_queried_object();
		 if(!empty($cat) && is_object($cat)) {
			$id = $cat->term_id;
			$this->url_js='https://pix.hyj.mobi/rt?t=d&action=c&cid='.$this->client_id.'&id='.$id;
			if($user_id != 0) {
				$this->url_js .= '&custid='.$user_id; 
			}
			echo $this->render( 'front-page' );
		 }
		}
	}

    // CATEGORY PAGE
	public function retargeting_cart() {
		$user_id = get_current_user_id();

		// URL:  https://pix.hyj.mobi/rt?t=d&action=w&cid=CLIENT_ID
		if (is_cart() ) {
			$this->url_js='https://pix.hyj.mobi/rt?t=d&action=w&cid='.$this->client_id;
			if($user_id != 0) {
				$this->url_js .= '&custid='.$user_id; 
			}
			echo $this->render( 'front-page' );         
		}
	}	

	// PRODUCT PAGE
	public function retargeting_product() {
		$user_id = get_current_user_id();

    //URL:  https://pix.hyj.mobi/rt?t=d&action=p&cid=CLIENT_ID&id=PRODUCT_ID
		if ( is_product() ) {
			global $product;
            $id = $product->get_id();
			if(!$id) {
			$id = get_the_ID();
			}
			$this->url_js='https://pix.hyj.mobi/rt?t=d&action=p&cid='.$this->client_id.'&id='.$id;
			if($user_id != 0) {
				$this->url_js .= '&custid='.$user_id; 
			}
			echo $this->render( 'front-page' );
		}
	}


	// Order Success page
	public function retargeting_confirmation() {
		$user_id = get_current_user_id();
/*
    URL:  https://d.hyj.mobi/convNetw?cid=CLIENT_ID&orderId=ORDER_ID&amount=ORDER_TOTAL&eventName=ARTILE_ID1,ARTILE_ID2,ARTILE_ID3&network=relevanz
*/
		if ( is_order_received_page() ) {

			$this->load_confirmation_order_id();
			$eventname='';
			if(count($this->product_ids)) {
			//$eventname = json_encode(implode(',',$this->products_name));
			$eventname = implode(',',$this->product_ids);
			}

			$this->url_js='https://d.hyj.mobi/convNetw?cid='.$this->client_id.'&orderId='.$this->order_id.'&amount='.$this->order_total.'&eventName='.$eventname.'&network=relevanz';
			if($user_id != 0) {
				$this->url_js .= '&custid='.$user_id; 
			}

			//echo "<pre>"; var_export($this->url_js); echo "</pre>";
			//exit;
			echo $this->render( 'front-page' );
		}
	}

	public function retargeting_other() {
		$user_id = get_current_user_id();

		if(!is_front_page() && !(function_exists('is_product_category') && is_product_category()) && !is_product() && !is_order_received_page() && !is_cart()) {
			$this->url_js='https://pix.hyj.mobi/rt?t=d&action=s&cid='.$this->client_id;
			if($user_id != 0) {
				$this->url_js .= '&custid='.$user_id; 
			}
			echo $this->render( 'front-page' );			
		}
	}

	protected $order_id ;
	protected $products_name = array();
	protected $product_ids = array();
	protected $order_total ;

	// get Order Success page data
	private function load_confirmation_order_id() {
		$key=isset($_GET['key'])?$_GET['key']:null;
		$this->order_id = wc_get_order_id_by_order_key( $key );
		//wc_order_59576109b13fa
		if($this->order_id) {
		$order = new \WC_Order( $this->order_id );
		$this->order_id = $order->get_order_number();
		$this->order_total = number_format( (float) $order->get_total() - $order->get_total_tax() - $order->get_total_shipping(), wc_get_price_decimals(), '.', '' );
		foreach ( $order->get_items() as $item ) {

			if ( $item['variation_id'] ) {
				$product = new \WC_Product_Variation( $item['variation_id'] );
				$id = $product->get_parent_id();
			} else {
				$product = new \WC_Product( $item['product_id'] );
				$id = $product->get_id();
			}
			$this->products_name[] = $product->get_name();
			$this->product_ids[] = $id;
		}
		return true;
		}
		return false;
	}

	private function escapeJavaScriptText($string)
		{
			return str_replace("\n", '\n', str_replace('"', '\"', addcslashes(str_replace("\r", '', (string)$string), "\0..\37'\\")));
		}


	public function load_current_category_id() {

		global $wp_query;
		$category = $wp_query->get_queried_object();
		$this->product_category_id = $category->term_id;
	}

	public function load_category_products_id() {

		global $wp_query;
		$this->products_id = array();
		foreach ( $wp_query->posts as $product_post ) {
			$product = new \WC_Product( $product_post->ID );
			$this->products_id[] = $product->get_sku();
		}
	}

	public function load_product_categories_id() {

		global $post;
		$product_categories = get_the_terms( $post->ID, 'product_cat' );
		$this->product_categories_id = array_keys( $product_categories );
	}

	public function load_product_id() {

		global $post;
		$product = new \WC_Product( $post->ID );
		$this->product_id = $product->get_sku();
	}

	public function load_product_price() {

		global $product;
		$this->product_price = $product->get_price();
	}

	public function load_cart_products_id() {

		$this->products_id = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$this->products_id[] = $cart_item['data']->get_sku();
		}
	}

	public function load_cart_products_price() {

		$this->cart_products_price = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$this->cart_products_price[] = $cart_item['data']->get_price();
		}
	}

	public function load_cart_total() {

		$this->cart_total = WC()->cart->cart_contents_total;
	}


	public function load_confirmation_products_price() {

		$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
		$order = new \WC_Order( $order_id );

		$this->products_price = array();
		foreach ( $order->get_items() as $item ) {

			if ( $item['variation_id'] ) {
				$product = new \WC_Product_Variation( $item['variation_id'] );
			} else {
				$product = new \WC_Product( $item['product_id'] );
			}
			$this->products_price[] = $product->get_price();
		}
	}

	public function load_currency() {

		$this->currency = 'EUR';
	}

	public function load_new_customer() {

		global $current_user;

		$this->new_customer = 0;
		if ( isset( $current_user->ID ) ) {
			$args = array();
			$args['post_type'] = 'shop_order';
			$args['numberposts'] = -1;
			$args['post_status'] = 'publish';
			$args['meta_key'] = '_customer_user';
			$args['meta_value'] = $current_user->ID;
			$wp_query = new \WP_Query( $args );
			$orders_id = wp_list_pluck( $wp_query->posts, 'ID' );
			wp_reset_query();
			// First order
			if ( count( $orders_id ) == 1 ) {
				$this->new_customer = 1;
			};
		}
	}

	public function render( $view_file ) {

		ob_start();
		include plugin_dir_path( __FILE__ ) . 'partials/' . $view_file . '.php';
		$result = ob_get_contents();
		ob_end_clean();
		return $result;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Relevatracking_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Relevatracking_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/relevatracking-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Relevatracking_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Relevatracking_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/relevatracking-public.js', array( 'jquery' ), $this->version, false );

	}

}
