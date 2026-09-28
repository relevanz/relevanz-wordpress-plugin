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

class Relevatracking_Public
{

	// Legacy WordPress-plugin default: pixels fire out of the box. Merchants
	// who use an external CMP override this from the Additional HTML field or
	// via the tag-push endpoint. (The cross-plugin spec recommends `false`
	// here, but the WordPress release line has shipped `true` since 2.1.x and
	// keeping that behaviour avoids breaking existing installs on update.)
	const DEFAULT_ADDITIONAL_HTML = '<script type="text/javascript">  var relevanzAppForcePixel = true;</script>';
	const TAG_PUSH_MAX_BYTES = 51200; // 50 KB
	const TRACKER_BASE = 'https://pix.hyj.mobi/rt';
	const CONVERSION_BASE = 'https://d.hyj.mobi/conv';

	private $plugin_name;
	private $version;

	protected $apikey;
	protected static $cache = array();
	protected $client_id;
	protected $additional_html;

	protected $order_id;
	protected $products_name = array();
	protected $product_ids = array();
	protected $order_total;
	protected $order_currency;

	// Set to true once retargeting_confirmation() has emitted its tags so the
	// wp_footer fallback path (relevatracking()) doesn't double-fire.
	protected $conversion_fired = false;

	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$this->client_id = self::load_client_id();

		add_action('releva_csvexport', array($this, 'csvexport'));
		add_action('releva_callback', array($this, 'callback'));
		add_action('releva_health',   array($this, 'health'));
		add_action('releva_tag',      array($this, 'tag_push'));

		add_action('woocommerce_before_thankyou', array($this, 'retargeting_confirmation'), 40);
	}

	public static function load_client_id()
	{
		$apikey = (string)get_option('relevatracking_api_key');
		$storeId = md5(__METHOD__ . "::client_id::" . $apikey);

		if (!isset(self::$cache[$storeId])) {
			// Persisted client_id wins — avoid hitting the backend on every page render.
			$stored = (string)get_option('relevatracking_client_id');
			if ($stored !== '') {
				self::$cache[$storeId] = $stored;
			} elseif ($apikey) {
				$response = self::fetchUserId($apikey);
				if (!empty($response)) {
					self::$cache[$storeId] = $response;
					if (false === get_option('relevatracking_client_id')) {
						add_option('relevatracking_client_id', $response);
					} else {
						update_option('relevatracking_client_id', $response);
					}
				}
			}
		}

		return isset(self::$cache[$storeId]) ? self::$cache[$storeId] : 0;
	}

	/**
	 * Calls /v1/campaigns/get with the callback-url parameter per §5.1.
	 * Returns the user_id string on success, or null on failure.
	 */
	public static function fetchUserId($apikey, $timeout = 10)
	{
		$check = Relevatracking_Admin::verifyApiKey($apikey, $timeout);
		return $check['status'] === 'valid' ? $check['user_id'] : null;
	}

	/**
	 * Tracking is on unless the merchant unchecked "Active" in the settings.
	 */
	public static function isActive()
	{
		return (string)get_option('relevatracking_active', '1') !== '0';
	}

	public function releva_init_action()
	{
		$releva_action = isset($_GET['releva_action']) ? $_GET['releva_action'] : '';
		if ($releva_action) {
			$releva_action = preg_replace('/\W/', '', $releva_action);
			do_action('releva_' . $releva_action);
		}
	}

	public function getPhpVersion()
	{
		return array(
			'version' => phpversion(),
			'sapi-name' => php_sapi_name(),
			'memory-limit' => ini_get('memory_limit'),
			'max-execution-time' => ini_get('max_execution_time'),
		);
	}

	public function getDbVersion()
	{
		global $wpdb;
		$version_comment = $wpdb->get_var('SELECT @@version_comment AS `server`');
		$db_version = $wpdb->get_var('SELECT VERSION()');
		return array(
			'version' => $db_version,
			'server'  => $version_comment,
		);
	}

	public function getServerEnvironment()
	{
		return array(
			'server-software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : null,
			'php' => $this->getPhpVersion(),
			'db' => $this->getDbVersion(),
		);
	}

	public function getCallbacks()
	{
		$base = site_url();
		$callbacks = array(
			'callback' => array(
				'url' => $base . '?releva_action=callback',
				'parameters' => array(),
			),
			'health' => array(
				'url' => $base . '?releva_action=health',
				'parameters' => array(),
			),
			'tag' => array(
				'url' => $base . '?releva_action=tag',
				'parameters' => array(),
			),
		);

		if (class_exists('WooCommerce', false)) {
			$callbacks['export'] = array(
				'url' => $base . '?releva_action=csvexport',
				'parameters' => array(
					'format'           => array('values' => array('csv','json'), 'default' => 'csv',  'optional' => true),
					'page'             => array('type' => 'integer',              'default' => 1,      'optional' => true),
					'limit'            => array('type' => 'integer',              'default' => 100,    'optional' => true),
					'include_variants' => array('type' => 'string',               'default' => 'true', 'optional' => true),
					'descriptionLong'  => array('type' => 'string',               'default' => 'true', 'optional' => true),
					'inStockOnly'      => array('type' => 'string',               'default' => 'true', 'optional' => true),
					'use_seo_urls'     => array('type' => 'string',               'default' => 'true', 'optional' => true),
					'country'          => array('type' => 'string',               'optional' => true),
				),
			);
		}

		return $callbacks;
	}

	/**
	 * Timing-safe compare of the auth hash query parameter against md5(apikey:client_id).
	 */
	protected function checkAuth($allow_admin = false)
	{
		// Dev guide §7.3: a logged-in shop admin may open the read-only endpoints
		// (callback, export) directly for diagnostics. Never for the tag push —
		// that would let a cross-site POST rewrite the Additional HTML.
		if ($allow_admin && current_user_can('manage_options')) {
			return true;
		}

		$apikey = (string)get_option('relevatracking_api_key');
		$client_id = (string)get_option('relevatracking_client_id');
		$auth = isset($_GET['auth']) ? (string)$_GET['auth'] : '';
		$expected = md5($apikey . ':' . $client_id);

		if ($apikey === '' || $client_id === '' || !hash_equals($expected, $auth)) {
			$this->sendUnauthorized();
			return false;
		}
		return true;
	}

	protected function sendNoStoreHeaders($contentType = 'application/json')
	{
		if (!headers_sent()) {
			header('Content-Type: ' . $contentType);
			header('Cache-Control: no-store');
			header('Pragma: no-cache');
		}
	}

	protected function sendUnauthorized()
	{
		if (!headers_sent()) {
			status_header(401);
			header('Content-Type: application/json');
			header('Cache-Control: no-store');
		}
		echo json_encode(array(
			'message' => 'Invalid authentication',
			'error'   => 'Unauthorized',
			'statusCode' => 401,
		));
		exit;
	}

	public function callback()
	{
		if (!$this->checkAuth(true)) {
			return;
		}
		if (!current_user_can('manage_options')) {
			update_option('relevatracking_last_callback', time(), false);
		}

		while (ob_get_level() > 0) { ob_end_clean(); }
		$this->sendNoStoreHeaders('application/json');

		global $wp_version;
		$wc_version = '';
		$system = 'WordPress';
		// class_exists() also covers a network-activated WooCommerce on multisite,
		// which never appears in the site's own `active_plugins` option.
		if (class_exists('WooCommerce', false)) {
			$wc_version = defined('WC_VERSION') ? WC_VERSION : '';
			$system = 'WooCommerce';
		}

		$additional_html = (string)get_option('relevatracking_additional_html');
		if ($additional_html === '') {
			$additional_html = self::DEFAULT_ADDITIONAL_HTML;
		}

		$callback = array(
			'plugin-version' => $this->version,
			'shop' => array(
				'system' => $system,
				'version' => $wc_version,
				// `wp_version` is the field name the releva.nz backend has consumed
				// since plugin 2.1.9; keep it for backwards compatibility. The
				// kebab-case `wordpress-version` is the new spec-compliant name.
				'wp_version' => $wp_version,
				'wordpress-version' => $wp_version,
				'is-cloud' => false,
			),
			'environment' => $this->getServerEnvironment(),
			'additional_html' => $additional_html,
			'callbacks' => $this->getCallbacks(),
		);

		echo json_encode($callback);
		exit;
	}

	/**
	 * Health check endpoint. No auth required. Minimal body.
	 */
	public function health()
	{
		while (ob_get_level() > 0) { ob_end_clean(); }
		$this->sendNoStoreHeaders('application/json');

		$apikey = (string)get_option('relevatracking_api_key');
		$client_id = (string)get_option('relevatracking_client_id');

		if (!self::isActive()) {
			status_header(503);
			echo json_encode(array(
				'ok' => false,
				'plugin-version' => $this->version,
				'reason' => 'tracking-disabled',
			));
			exit;
		}

		$body = array(
			'ok' => true,
			'plugin-version' => $this->version,
		);
		if ($apikey === '' || $client_id === '') {
			$body['configured'] = false;
		}
		echo json_encode($body);
		exit;
	}

	/**
	 * Tag push endpoint. POST with auth. Updates Additional HTML.
	 */
	public function tag_push()
	{
		if (strtoupper((string)(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')) !== 'POST') {
			while (ob_get_level() > 0) { ob_end_clean(); }
			if (!headers_sent()) {
				status_header(405);
				header('Content-Type: application/json');
				header('Cache-Control: no-store');
				header('Allow: POST');
			}
			echo json_encode(array(
				'message' => 'Method Not Allowed',
				'error' => 'MethodNotAllowed',
				'statusCode' => 405,
			));
			exit;
		}

		if (!$this->checkAuth()) {
			return;
		}

		$raw = file_get_contents('php://input');
		if ($raw === false) { $raw = ''; }
		if (strlen($raw) > self::TAG_PUSH_MAX_BYTES) {
			while (ob_get_level() > 0) { ob_end_clean(); }
			if (!headers_sent()) {
				status_header(413);
				header('Content-Type: application/json');
				header('Cache-Control: no-store');
			}
			echo json_encode(array(
				'message' => 'Payload too large (max 50 KB)',
				'error' => 'PayloadTooLarge',
				'statusCode' => 413,
			));
			exit;
		}

		$payload = json_decode($raw, true);
		$html = is_array($payload) && isset($payload['html']) ? (string)$payload['html'] : null;

		if ($html === null) {
			while (ob_get_level() > 0) { ob_end_clean(); }
			if (!headers_sent()) {
				status_header(400);
				header('Content-Type: application/json');
				header('Cache-Control: no-store');
			}
			echo json_encode(array(
				'message' => 'Missing "html" field in JSON body',
				'error' => 'BadRequest',
				'statusCode' => 400,
			));
			exit;
		}

		$sanitized = self::sanitizeAdditionalHtml($html);

		if (false === get_option('relevatracking_additional_html')) {
			add_option('relevatracking_additional_html', $sanitized);
		} else {
			update_option('relevatracking_additional_html', $sanitized);
		}

		while (ob_get_level() > 0) { ob_end_clean(); }
		$this->sendNoStoreHeaders('application/json');
		echo json_encode(array('ok' => true));
		exit;
	}

	/**
	 * Strip the four banned HTML tags per spec §6.3, regardless of attributes / spacing / casing.
	 */
	public static function sanitizeAdditionalHtml($html)
	{
		return preg_replace(
			'#<\s*/?\s*(iframe|object|embed|link)\b[^>]*>#i',
			'',
			(string)$html
		);
	}

	public function csvexport()
	{
		if (!class_exists('WooCommerce', false)) {
			wp_send_json_success();
			return;
		}

		if (!$this->checkAuth(true)) {
			return;
		}

		$format          = isset($_GET['format']) ? strtolower((string)$_GET['format']) : 'csv';
		if (!in_array($format, array('csv', 'json'), true)) { $format = 'csv'; }
		$page            = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
		$limit           = isset($_GET['limit']) ? max(1, min(2500, (int)$_GET['limit'])) : 100;
		$include_variants = self::truthyParam('include_variants', true);
		$desc_long_param  = self::truthyParam('descriptionLong', true);
		$in_stock_only    = self::truthyParam('inStockOnly', true);
		$use_seo_urls     = self::truthyParam('use_seo_urls', true);

		$args = array(
			'status'  => 'publish',
			'limit'   => $limit,
			'page'    => $page,
			'paginate' => true,
			// Must be upper-case: WP_Query ignores 'id' and falls back to post_date,
			// which makes pages overlap for products created in the same second.
			'orderby' => 'ID',
			'order'   => 'asc',
		);
		if ($in_stock_only) {
			$args['stock_status'] = 'instock';
		}

		$query = wc_get_products($args);
		$products = is_object($query) && isset($query->products) ? $query->products : (is_array($query) ? $query : array());
		$total = is_object($query) && isset($query->total) ? (int)$query->total : count($products);

		while (ob_get_level() > 0) { ob_end_clean(); }

		if (count($products) === 0) {
			if (!headers_sent()) {
				status_header(404);
				header('Content-Type: application/json');
				header('Cache-Control: no-store');
				header('X-Relevanz-Product-Count: ' . $total);
			}
			echo json_encode(array(
				'message' => 'No products found',
				'statusCode' => 404,
			));
			exit;
		}

		if (!headers_sent()) {
			if ($format === 'json') {
				header('Content-Type: application/json; charset=utf-8');
			} else {
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename="relevanz_catalog_page_' . $page . '.csv"');
			}
			header('Cache-Control: no-store');
			header('X-Relevanz-Product-Count: ' . $total);
		}

		$rows = array();
		foreach ($products as $p) {
			$rows = array_merge($rows, $this->buildProductRows($p, $include_variants, $desc_long_param, $in_stock_only, $use_seo_urls));
		}

		if ($format === 'json') {
			echo json_encode($rows);
			exit;
		}

		$header = array(
			'id', 'variationId', 'gtin', 'brand', 'categoryIds', 'name',
			'descriptionShort', 'descriptionLong',
			'price', 'priceOffer', 'priceNet', 'priceNetOffer',
			'basePrice', 'basePriceUnit', 'basePriceQuantity', 'taxrate', 'from',
			'quantity', 'link', 'image', 'lastUpdate',
		);
		$op = fopen('php://output', 'wb');
		fputcsv($op, $header, ',', '"');
		foreach ($rows as $row) {
			$ordered = array();
			foreach ($header as $col) {
				$ordered[] = isset($row[$col]) ? $row[$col] : '';
			}
			fputcsv($op, $ordered, ',', '"');
		}
		fclose($op);
		exit;
	}

	protected static function truthyParam($name, $default = true)
	{
		if (!isset($_GET[$name])) {
			return (bool)$default;
		}
		$v = strtolower((string)$_GET[$name]);
		if (in_array($v, array('0', 'false', 'no', 'off', ''), true)) return false;
		if (in_array($v, array('1', 'true', 'yes', 'on'), true)) return true;
		return (bool)$default;
	}

	/**
	 * Build CSV rows for a product (and its variants if applicable).
	 * Returns an array of associative arrays keyed by canonical column names.
	 */
	protected function buildProductRows($product, $include_variants, $desc_long, $in_stock_only, $use_seo_urls)
	{
		if (!is_object($product)) {
			$product = wc_get_product($product);
		}
		if (empty($product)) {
			return array();
		}

		$rows = array();

		if ($include_variants && method_exists($product, 'get_type') && $product->get_type() === 'variable') {
			$variation_ids = $product->get_children();
			foreach ($variation_ids as $vid) {
				$variation = wc_get_product($vid);
				if (!$variation) continue;
				if ($in_stock_only && !$variation->is_in_stock()) continue;
				$rows[] = $this->productRowArray($product, $variation, $desc_long, $use_seo_urls);
			}
			if (empty($rows) && !$in_stock_only) {
				$rows[] = $this->productRowArray($product, null, $desc_long, $use_seo_urls);
			}
			return $rows;
		}

		if ($in_stock_only && !$product->is_in_stock()) {
			return array();
		}
		$rows[] = $this->productRowArray($product, null, $desc_long, $use_seo_urls);
		return $rows;
	}

	protected function productRowArray($product, $variation, $desc_long, $use_seo_urls)
	{
		$product_id = $product->get_id();
		$priceSource = $variation ? $variation : $product;

		$regular = $priceSource->get_regular_price();
		$sale    = $priceSource->get_sale_price();

		$row = array();
		$row['id'] = $product_id;
		$row['variationId'] = $variation ? $variation->get_id() : '';
		$row['gtin'] = self::extractGtin($product, $variation);
		$row['brand'] = self::extractBrand($product);

		$post_categories = wp_get_post_terms($product_id, 'product_cat');
		$catIds = array();
		foreach ((array)$post_categories as $term) {
			if (is_object($term) && isset($term->term_id)) {
				$catIds[] = (string)$term->term_id;
			}
		}
		$row['categoryIds'] = implode(',', $catIds);

		$name = $product->get_name();
		if ($variation && method_exists($variation, 'get_name')) {
			$varName = $variation->get_name();
			if ($varName) { $name = $varName; }
		}
		$row['name'] = $name;
		$row['descriptionShort'] = $product->get_short_description();
		$row['descriptionLong']  = $desc_long ? $product->get_description() : '';

		$row['price']         = $regular !== '' ? wc_format_decimal(wc_get_price_including_tax($priceSource, array('price' => $regular)), 2) : '';
		$row['priceOffer']    = $sale !== '' && $sale !== null ? wc_format_decimal(wc_get_price_including_tax($priceSource, array('price' => $sale)), 2) : $row['price'];
		$row['priceNet']      = $regular !== '' ? wc_format_decimal(wc_get_price_excluding_tax($priceSource, array('price' => $regular)), 2) : '';
		$row['priceNetOffer'] = $sale !== '' && $sale !== null ? wc_format_decimal(wc_get_price_excluding_tax($priceSource, array('price' => $sale)), 2) : $row['priceNet'];

		// WooCommerce has no native base price (unit price) — left empty.
		$row['basePrice'] = '';
		$row['basePriceUnit'] = '';
		$row['basePriceQuantity'] = '';
		$row['taxrate'] = self::taxRate($priceSource);

		// Variable products: lowest net price across the variations.
		$row['from'] = '';
		if ($product->get_type() === 'variable') {
			$min = $product->get_variation_price('min', false);
			if ($min !== '' && $min !== null) {
				$row['from'] = wc_format_decimal(wc_get_price_excluding_tax($product, array('price' => $min)), 2);
			}
		}

		$stock = $priceSource->get_stock_quantity();
		if ($stock === null || $stock === '') {
			$row['quantity'] = $priceSource->is_in_stock() ? 1 : 0;
		} else {
			$row['quantity'] = (int)$stock;
		}

		$link = $product->get_permalink();
		if (!$use_seo_urls) {
			$link = get_permalink($product_id);
			$link = add_query_arg(array('p' => $product_id), home_url('/'));
		}
		$row['link'] = $link;

		$images = $this->get_images($variation ?: $product);
		$row['image'] = isset($images[0]) ? $images[0] : '';

		$lastUpdate = get_the_modified_time('U', $product_id);
		if (empty($lastUpdate)) {
			$lastUpdate = get_the_time('U', $product_id);
		}
		$row['lastUpdate'] = $lastUpdate ? gmdate('c', (int)$lastUpdate) : '';

		return $row;
	}

	/**
	 * Effective tax rate in percent for the shop's base country (dev guide §11.3.1:
	 * the market rate, never the maximum across the tax table). Compound rates
	 * are applied the way WooCommerce applies them.
	 */
	protected static function taxRate($product)
	{
		if (!wc_tax_enabled() || !$product->is_taxable()) {
			return '0';
		}
		$rates = WC_Tax::get_base_tax_rates($product->get_tax_class());
		return wc_format_decimal(array_sum(WC_Tax::calc_tax(100, $rates, false)), 2, true);
	}

	protected static function extractGtin($product, $variation)
	{
		$candidates = array('_gtin', 'gtin', '_ean', 'ean', '_upc', 'upc');
		$ids = array();
		if ($variation) { $ids[] = $variation->get_id(); }
		$ids[] = $product->get_id();
		foreach ($ids as $pid) {
			foreach ($candidates as $key) {
				$v = get_post_meta($pid, $key, true);
				if (!empty($v)) return (string)$v;
			}
		}
		return '';
	}

	protected static function extractBrand($product)
	{
		// Common WooCommerce brand taxonomies (built-in WC brands + popular plugins).
		$taxonomies = array('product_brand', 'pwb-brand', 'pa_brand', 'yith_product_brand');
		foreach ($taxonomies as $tax) {
			if (!taxonomy_exists($tax)) continue;
			$terms = wp_get_post_terms($product->get_id(), $tax);
			if (is_array($terms) && !empty($terms) && isset($terms[0]->name)) {
				return (string)$terms[0]->name;
			}
		}
		return '';
	}

	private function get_images($product)
	{
		$images = $attachment_ids = array();
		$product_image = $product->get_image_id();
		if (!empty($product_image)) {
			$attachment_ids[] = $product_image;
		}
		if (method_exists($product, 'get_gallery_image_ids')) {
			$attachment_ids = array_merge($attachment_ids, $product->get_gallery_image_ids());
		}
		foreach ($attachment_ids as $attachment_id) {
			$attachment_post = get_post($attachment_id);
			if (is_null($attachment_post)) continue;
			$attachment = wp_get_attachment_image_src($attachment_id, 'full');
			if (!is_array($attachment)) continue;
			$images[] = current($attachment);
		}
		if (empty($images) && function_exists('wc_placeholder_img_src')) {
			$images[] = wc_placeholder_img_src();
		}
		return $images;
	}

	/**
	 * Footer hook — emit one tag per page render.
	 */
	public function relevatracking()
	{
		if (!$this->client_id || !self::isActive()) {
			return;
		}

		$additional_html = (string)get_option('relevatracking_additional_html');
		$this->additional_html = $additional_html !== '' ? $additional_html : self::DEFAULT_ADDITIONAL_HTML;

		// Order-success page is normally handled by the WC `woocommerce_before_thankyou` hook
		// (-> retargeting_confirmation). On WordPress installs that render the order page via the
		// block-based Checkout block in some theme combinations that hook never fires, so we
		// reach wp_footer with no pixel emitted. Fall back to resolving the order from the URL
		// here and rendering the conversion variants ourselves.
		if (class_exists('WooCommerce', false) && function_exists('is_order_received_page') && is_order_received_page()) {
			if (!$this->conversion_fired) {
				$order_id = $this->resolveOrderIdFromRequest();
				if ($order_id) {
					$this->retargeting_confirmation($order_id);
				}
			}
			return;
		}

		$url = $this->resolveTrackerUrl();
		if ($url) {
			$this->addTrackingCode($url, '');
		}
	}

	/**
	 * Pull the order ID off the order-received endpoint. WC routes
	 * /checkout/order-received/{ORDER_ID}/?key={KEY} so the ID is in the
	 * `order-received` query var; falls back to looking it up by the `key` param.
	 */
	protected function resolveOrderIdFromRequest()
	{
		$ep = get_query_var('order-received');
		if (!empty($ep)) {
			return (int)$ep;
		}
		$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
		if ($key !== '' && function_exists('wc_get_order_id_by_order_key')) {
			$oid = wc_get_order_id_by_order_key($key);
			if ($oid) {
				return (int)$oid;
			}
		}
		return 0;
	}

	/**
	 * Decide which retargeting URL to render based on current WP context.
	 */
	protected function resolveTrackerUrl()
	{
		$user_id = get_current_user_id();
		$cid = $this->client_id;

		$wc_active = class_exists('WooCommerce', false);

		// Search results (action=l)
		if (is_search()) {
			$term = isset($_GET['s']) ? (string)$_GET['s'] : get_search_query();
			return self::buildTrackerUrl('l', $cid, $user_id, array('products' => $term));
		}

		if ($wc_active) {
			// Product page (action=p)
			if (function_exists('is_product') && is_product()) {
				global $product;
				$id = (is_object($product) && method_exists($product, 'get_id')) ? $product->get_id() : get_the_ID();
				return self::buildTrackerUrl('p', $cid, $user_id, array('id' => $id));
			}

			// Category page (action=c)
			if (function_exists('is_product_category') && is_product_category()) {
				$cat = get_queried_object();
				if (is_object($cat) && isset($cat->term_id)) {
					return self::buildTrackerUrl('c', $cid, $user_id, array('id' => $cat->term_id));
				}
			}

			// Cart page (action=w)
			if (function_exists('is_cart') && is_cart()) {
				$ids = array();
				if (function_exists('WC') && WC()->cart) {
					foreach (WC()->cart->get_cart() as $item) {
						if (!empty($item['product_id'])) {
							$ids[] = (int)$item['product_id'];
						}
					}
				}
				$params = array();
				if (!empty($ids)) {
					$params['id'] = implode(',', array_unique($ids));
				}
				return self::buildTrackerUrl('w', $cid, $user_id, $params);
			}
		}

		// Default: homepage / static / everything else → action=s
		return self::buildTrackerUrl('s', $cid, $user_id, array());
	}

	protected static function buildTrackerUrl($action, $cid, $user_id, array $extra)
	{
		$params = array(
			't'      => 'd',
			'action' => $action,
			'cid'    => $cid,
		);
		foreach ($extra as $k => $v) {
			if ($v === '' || $v === null) continue;
			$params[$k] = $v;
		}
		if ($user_id) {
			$params['custid'] = $user_id;
		}
		// Dev guide 1.2: URL-encoded current page URL on every retargeting action.
		$params['url'] = self::currentPageUrl();
		return self::TRACKER_BASE . '?' . http_build_query($params);
	}

	protected static function currentPageUrl()
	{
		$host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : (string)parse_url(home_url(), PHP_URL_HOST);
		$uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/';
		return (is_ssl() ? 'https' : 'http') . '://' . $host . $uri;
	}

	/**
	 * Order success page — renders the conversion pixel plus an anonymous fallback variant.
	 */
	public function retargeting_confirmation($order_id)
	{
		if ($this->conversion_fired || !self::isActive()) {
			return;
		}

		$this->load_confirmation_order_id($order_id);

		if (!$this->client_id || !$this->order_id) {
			return;
		}

		$this->conversion_fired = true;

		$user_id = get_current_user_id();
		$products = implode(',', $this->product_ids);

		$base = array(
			'cid'      => $this->client_id,
			'orderId'  => $this->order_id,
			'amount'   => $this->order_total,
			'products' => $products,
			'currency' => $this->order_currency,
			'network'  => 'relevanz',
		);

		// Full conversion (with custid when logged in).
		$full = $base;
		if ($user_id) {
			$full['custid'] = $user_id;
		}
		$fullUrl = self::CONVERSION_BASE . '?' . http_build_query($full);

		// Anonymous variant — never includes custid.
		$anon = $base;
		$anon['anon'] = 1;
		$anonUrl = self::CONVERSION_BASE . '?' . http_build_query($anon);

		// Retargeting pixel action=t (dev guide §10.7) — consent-only, no anonymous variant.
		$orderTrackerUrl = self::buildTrackerUrl('t', $this->client_id, $user_id, array(
			'orderId'  => $this->order_id,
			'amount'   => $this->order_total,
			'products' => $products,
		));

		$additional_html = (string)get_option('relevatracking_additional_html');
		$this->additional_html = $additional_html !== '' ? $additional_html : self::DEFAULT_ADDITIONAL_HTML;

		$this->addTrackingCode($fullUrl, $anonUrl, $orderTrackerUrl);
	}

	private function load_confirmation_order_id($order_id_arg = null)
	{
		$order = null;

		if (!empty($order_id_arg) && function_exists('wc_get_order')) {
			$order = wc_get_order($order_id_arg);
		}

		if (!$order) {
			$key = isset($_GET['key']) ? (string)$_GET['key'] : null;
			if ($key && function_exists('wc_get_order_id_by_order_key')) {
				$oid = wc_get_order_id_by_order_key($key);
				if ($oid && function_exists('wc_get_order')) {
					$order = wc_get_order($oid);
				}
			}
		}

		if (!$order) {
			return false;
		}

		$this->order_id = $order->get_order_number();
		// Conversion `amount` per dev-guide §10.7.1: net product revenue,
		// excluding tax, shipping and fees:
		//   amount = total − total_tax − shipping_net − fees_net
		// get_total_tax() covers items+shipping+fees tax. Only surcharges
		// (positive fees) are removed — a negative fee is a discount and stays
		// deducted, like a coupon.
		$fees = 0.0;
		foreach ($order->get_fees() as $fee) {
			$fees += max(0.0, (float)$fee->get_total());
		}
		$this->order_total = number_format(
			(float)$order->get_total() - (float)$order->get_total_tax() - (float)$order->get_shipping_total() - $fees,
			2,
			'.',
			''
		);
		$this->order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '');

		foreach ($order->get_items() as $item) {
			$variation_id = method_exists($item, 'get_variation_id') ? $item->get_variation_id() : (isset($item['variation_id']) ? $item['variation_id'] : 0);
			$product_id = method_exists($item, 'get_product_id') ? $item->get_product_id() : (isset($item['product_id']) ? $item['product_id'] : 0);

			if ($variation_id) {
				$variation = wc_get_product($variation_id);
				$id = $variation ? $variation->get_parent_id() : $product_id;
				$name = $variation ? $variation->get_name() : '';
			} else {
				$product = wc_get_product($product_id);
				$id = $product ? $product->get_id() : $product_id;
				$name = $product ? $product->get_name() : '';
			}
			if ($id) {
				$this->product_ids[] = $id;
				$this->products_name[] = $name;
			}
		}
		return true;
	}

	/**
	 * Inject the page's tracking script. `$anonUrl` and `$orderTrackerUrl` are
	 * empty on every page except the order-success page.
	 */
	public function addTrackingCode($url, $anonUrl = '', $orderTrackerUrl = '')
	{
		// Load in footer (`in_footer=true`) so the script runs after </body>
		// has parsed — the polling loop creates <script> tags and appends them
		// to document.body, which doesn't exist when the script is in <head>.
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url(__FILE__) . 'js/relevatracking-public.js',
			array(),
			$this->version,
			true
		);

		$assign = 'var relevanzURL = ' . wp_json_encode((string)$url) . ';' .
		          'var relevanzAnonymousURL = ' . wp_json_encode((string)$anonUrl) . ';' .
		          'var relevanzOrderTrackerURL = ' . wp_json_encode((string)$orderTrackerUrl) . ';';
		wp_add_inline_script($this->plugin_name, $assign, 'before');
		wp_add_inline_script($this->plugin_name, $this->additional_html, 'before');
	}
}
