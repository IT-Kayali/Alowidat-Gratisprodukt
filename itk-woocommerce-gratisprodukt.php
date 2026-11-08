<?php
/**
 * Plugin Name: IT-Kayali WooCommerce Gratisprodukt
 * Description: Fügt automatisch genau ein konfigurierbares Gratisprodukt zum WooCommerce-Warenkorb hinzu. Optional ab einem Mindestwarenwert.
 * Version: 1.1.0
 * Author: IT-Kayali
 * Text Domain: itk-wc-free-gift
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);

			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

final class ITK_WC_Auto_Free_Gift {

	const VERSION           = '1.1.0';
	const OPTION_PRODUCT_ID = 'itk_wc_free_gift_product_id';
	const OPTION_MIN_AMOUNT = 'itk_wc_free_gift_min_amount';
	const SETTINGS_GROUP    = 'itk_wc_free_gift_settings';
	const PAGE_SLUG         = 'itk-wc-free-gift';

	/**
	 * Verhindert Endlosschleifen bei automatischen Warenkorbänderungen.
	 *
	 * @var bool
	 */
	private $syncing = false;

	/**
	 * Erlaubt das interne Hinzufügen des Gratisprodukts,
	 * während manuelles Hinzufügen blockiert bleibt.
	 *
	 * @var bool
	 */
	private $internal_add = false;

	/**
	 * Singleton.
	 *
	 * @var ITK_WC_Auto_Free_Gift|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

		// Warenkorb synchronisieren.
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'sync_gift' ), 20 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'sync_gift' ), 20 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'sync_gift' ), 20 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'sync_gift' ), 20 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'sync_gift' ), 20 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'enforce_gift_cart_state' ), 100 );

		// Manuelles Hinzufügen verhindern.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'block_manual_add' ), 10, 6 );

		// Klassischer Warenkorb: Entfernen-Link und Mengenfeld sperren.
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'hide_remove_link' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'lock_quantity_html' ), 10, 3 );

		// Cart/Checkout Blocks: Menge nicht editierbar und exakt 1.
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( $this, 'store_api_quantity_editable' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( $this, 'store_api_quantity_one' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( $this, 'store_api_quantity_one' ), 10, 3 );

		// Gratisprodukt nicht normal im Shop anbieten.
		add_filter( 'woocommerce_product_is_visible', array( $this, 'hide_gift_from_catalog' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'redirect_gift_product_page' ) );
	}

	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_PRODUCT_ID,
			array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => array( $this, 'sanitize_product_id' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_MIN_AMOUNT,
			array(
				'type'              => 'number',
				'default'           => 0,
				'sanitize_callback' => array( $this, 'sanitize_min_amount' ),
			)
		);
	}

	public function sanitize_product_id( $value ) {
		$product_id = absint( $value );

		if ( 0 === $product_id ) {
			return 0;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			add_settings_error(
				self::OPTION_PRODUCT_ID,
				'itk_free_gift_invalid_product',
				'Das ausgewählte Produkt wurde nicht gefunden.',
				'error'
			);
			return 0;
		}

		/*
		 * Absichtlich nur einfache Produkte:
		 * So bleibt das Gratisprodukt eindeutig und unabhängig von Variationsauswahlen.
		 */
		if ( ! $product->is_type( 'simple' ) ) {
			add_settings_error(
				self::OPTION_PRODUCT_ID,
				'itk_free_gift_simple_only',
				'Bitte ein einfaches WooCommerce-Produkt als Gratisprodukt auswählen.',
				'error'
			);
			return 0;
		}

		return $product_id;
	}

	public function sanitize_min_amount( $value ) {
		if ( '' === $value || null === $value ) {
			return 0;
		}

		if ( function_exists( 'wc_format_decimal' ) ) {
			$value = wc_format_decimal( $value );
		}

		return max( 0, (float) $value );
	}

	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			'Gratisprodukt',
			'Gratisprodukt',
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	public function admin_assets( $hook_suffix ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
		}

		if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );

			wp_add_inline_style(
				'woocommerce_admin_styles',
				'.itk-free-gift-card{max-width:820px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:22px 24px;margin-top:18px}.itk-free-gift-card .form-table th{width:220px}.itk-free-gift-help{color:#646970;margin-top:6px}.itk-free-gift-status{margin-top:18px;padding:12px 14px;background:#f6f7f7;border-left:4px solid #2271b1}.itk-free-gift-status strong{display:inline-block;min-width:150px}'
			);
		}
	}

	public function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">Einstellungen</a>'
		);

		return $links;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$product_id = $this->get_gift_product_id();
		$min_amount = $this->get_min_amount();
		$product     = $product_id ? wc_get_product( $product_id ) : false;
		?>
		<div class="wrap">
			<h1>WooCommerce Gratisprodukt</h1>
			<p>Wähle ein Produkt aus, das automatisch genau einmal zum Warenkorb hinzugefügt wird.</p>

			<?php settings_errors(); ?>

			<div class="itk-free-gift-card">
				<form method="post" action="options.php">
					<?php settings_fields( self::SETTINGS_GROUP ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( self::OPTION_PRODUCT_ID ); ?>">Gratisprodukt</label>
							</th>
							<td>
								<select
									class="wc-product-search"
									style="width: 100%; max-width: 520px;"
									id="<?php echo esc_attr( self::OPTION_PRODUCT_ID ); ?>"
									name="<?php echo esc_attr( self::OPTION_PRODUCT_ID ); ?>"
									data-placeholder="Produkt suchen …"
									data-action="woocommerce_json_search_products"
									data-allow_clear="true"
								>
									<?php if ( $product ) : ?>
										<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected">
											<?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?>
										</option>
									<?php endif; ?>
								</select>

								<p class="itk-free-gift-help">
									Bitte ein <strong>einfaches Produkt</strong> auswählen. Das Plugin blendet dieses Produkt im Shop aus und blockiert manuelles Hinzufügen.
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( self::OPTION_MIN_AMOUNT ); ?>">Mindestwarenwert</label>
							</th>
							<td>
								<input
									type="number"
									min="0"
									step="0.01"
									id="<?php echo esc_attr( self::OPTION_MIN_AMOUNT ); ?>"
									name="<?php echo esc_attr( self::OPTION_MIN_AMOUNT ); ?>"
									value="<?php echo esc_attr( $min_amount ); ?>"
									class="regular-text"
								/>
								<?php echo wp_kses_post( get_woocommerce_currency_symbol() ); ?>

								<p class="itk-free-gift-help">
									<strong>0 oder leer = keine Mindestgrenze.</strong> Versandkosten zählen nicht zum Mindestwarenwert.
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( 'Einstellungen speichern' ); ?>
				</form>

				<div class="itk-free-gift-status">
					<p>
						<strong>Aktuelles Geschenk:</strong>
						<?php echo $product ? esc_html( $product->get_name() . ' (#' . $product_id . ')' ) : 'Nicht ausgewählt'; ?>
					</p>
					<p>
						<strong>Mindestwarenwert:</strong>
						<?php
						if ( $min_amount > 0 ) {
							echo wp_kses_post( wc_price( $min_amount ) );
						} else {
							echo 'Keiner – ab dem ersten normalen Produkt (Gutscheine zählen nicht)';
						}
						?>
					</p>
					<p>
						<strong>Verhalten:</strong>
						Genau 1×, Preis 0,00, nicht dauerhaft entfernbar. Reine digitale Alowidat-Gutscheinkäufe lösen weder Gratisprobe noch Probe-Versand aus. Bei gemischtem Warenkorb zählt nur der Warenwert der normalen Produkte.
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	private function get_gift_product_id() {
		return absint( get_option( self::OPTION_PRODUCT_ID, 0 ) );
	}

	private function get_min_amount() {
		return max( 0, (float) get_option( self::OPTION_MIN_AMOUNT, 0 ) );
	}

	private function is_current_gift_item( $cart_item ) {
		$gift_id = $this->get_gift_product_id();

		if ( ! $gift_id ) {
			return false;
		}

		$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

		return $gift_id === $product_id;
	}

	private function is_marked_gift_item( $cart_item ) {
		return ! empty( $cart_item['itk_wc_free_gift'] );
	}

	/**
	 * Die rein digitale Alowidat-Geschenkkarte ist kein qualifizierender
	 * Warenkauf. Die Produkt-ID kommt aus dem aktiven Gutschein-Plugin.
	 * Dadurch bleiben andere virtuelle Produkte weiterhin berechtigt.
	 */
	private function is_alowidat_gift_card( $cart_item ) {
		$pid = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		$voucher_id = absint( get_option( 'alw_gc_product_id', 0 ) );
		if ( $pid > 0 && $voucher_id > 0 && $pid === $voucher_id ) {
			return true;
		}
		// Sicherer Fallback bei bereits aufgebautem Warenkorb, z. B. nach Plugin-Update.
		return isset( $cart_item['alw_gc_amount_cents'] ) && (int) $cart_item['alw_gc_amount_cents'] > 0;
	}

	private function eligible_subtotal( $cart ) {
		$total = 0.0;

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( $this->is_marked_gift_item( $cart_item ) || $this->is_current_gift_item( $cart_item ) || $this->is_alowidat_gift_card( $cart_item ) ) {
				continue;
			}

			if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
				continue;
			}

			$qty = isset( $cart_item['quantity'] ) ? max( 0, (float) $cart_item['quantity'] ) : 0;

			if ( $qty <= 0 ) {
				continue;
			}

			$product = $cart_item['data'];
			$price   = (float) $product->get_price();

			/*
			 * Verwendet die WooCommerce-Preisdarstellung des Shops
			 * (inkl./exkl. Steuer entsprechend der Shop-Einstellung).
			 * Versand wird nicht berücksichtigt.
			 */
			$total += (float) wc_get_price_to_display(
				$product,
				array(
					'qty'   => $qty,
					'price' => $price,
				)
			);
		}

		return max( 0, $total );
	}

	private function has_normal_product( $cart ) {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( $this->is_marked_gift_item( $cart_item ) || $this->is_current_gift_item( $cart_item ) || $this->is_alowidat_gift_card( $cart_item ) ) {
				continue;
			}

			if ( isset( $cart_item['quantity'] ) && (float) $cart_item['quantity'] > 0 ) {
				return true;
			}
		}

		return false;
	}

	private function cart_is_eligible( $cart ) {
		if ( ! $this->has_normal_product( $cart ) ) {
			return false;
		}

		$minimum = $this->get_min_amount();

		if ( $minimum <= 0 ) {
			return true;
		}

		return ( $this->eligible_subtotal( $cart ) + 0.00001 ) >= $minimum;
	}

	public function sync_gift( $cart = null ) {
		if ( $this->syncing ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart ) {
			$cart = WC()->cart;
		}

		if ( ! $cart ) {
			return;
		}

		$this->syncing = true;

		try {
			$gift_id      = $this->get_gift_product_id();
			$gift_keys    = array();
			$stale_keys   = array();

			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$is_marked = $this->is_marked_gift_item( $cart_item );
				$is_current = $gift_id && $this->is_current_gift_item( $cart_item );

				if ( $is_marked && ! $is_current ) {
					$stale_keys[] = $cart_item_key;
					continue;
				}

				if ( $is_current ) {
					$gift_keys[] = $cart_item_key;
				}
			}

			// Alte Gratisprodukte entfernen, falls in den Einstellungen gewechselt wurde.
			foreach ( $stale_keys as $stale_key ) {
				$cart->remove_cart_item( $stale_key );
			}

			if ( ! $gift_id ) {
				return;
			}

			$gift_product = wc_get_product( $gift_id );

			if ( ! $gift_product || ! $gift_product->is_type( 'simple' ) ) {
				return;
			}

			$eligible = $this->cart_is_eligible( $cart );

			if ( $eligible ) {
				if ( empty( $gift_keys ) ) {
					$this->internal_add = true;

					$cart_item_key = $cart->add_to_cart(
						$gift_id,
						1,
						0,
						array(),
						array(
							'itk_wc_free_gift' => true,
						)
					);

					$this->internal_add = false;

					if ( $cart_item_key && isset( $cart->cart_contents[ $cart_item_key ]['data'] ) ) {
						$cart->cart_contents[ $cart_item_key ]['data']->set_price( 0 );
						$cart->cart_contents[ $cart_item_key ]['itk_wc_free_gift'] = true;
						$cart->cart_contents[ $cart_item_key ]['quantity'] = 1;
					}
				} else {
					$keep_key = array_shift( $gift_keys );

					if ( isset( $cart->cart_contents[ $keep_key ] ) ) {
						$cart->cart_contents[ $keep_key ]['itk_wc_free_gift'] = true;
						$cart->cart_contents[ $keep_key ]['quantity'] = 1;

						if ( isset( $cart->cart_contents[ $keep_key ]['data'] ) ) {
							$cart->cart_contents[ $keep_key ]['data']->set_price( 0 );
						}
					}

					// Doppelte Geschenkzeilen entfernen.
					foreach ( $gift_keys as $duplicate_key ) {
						$cart->remove_cart_item( $duplicate_key );
					}
				}
			} else {
				foreach ( $gift_keys as $gift_key ) {
					$cart->remove_cart_item( $gift_key );
				}
			}
		} finally {
			$this->internal_add = false;
			$this->syncing      = false;
		}
	}

	public function enforce_gift_cart_state( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		$gift_id = $this->get_gift_product_id();

		if ( $gift_id ) {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( ! $this->is_current_gift_item( $cart_item ) && ! $this->is_marked_gift_item( $cart_item ) ) {
					continue;
				}

				if ( isset( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product' ) ) {
					$cart_item['data']->set_price( 0 );
				}

				if ( isset( $cart_item['quantity'] ) && 1 !== (int) $cart_item['quantity'] ) {
					$cart->set_quantity( $cart_item_key, 1, false );
				}
			}
		}

		$this->sync_gift( $cart );
	}

	public function block_manual_add( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		$gift_id = $this->get_gift_product_id();

		if ( $gift_id && absint( $product_id ) === $gift_id && ! $this->internal_add ) {
			return false;
		}

		return $passed;
	}

	public function hide_remove_link( $link, $cart_item_key ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $link;
		}

		$cart_item = WC()->cart->get_cart_item( $cart_item_key );

		if ( $cart_item && ( $this->is_current_gift_item( $cart_item ) || $this->is_marked_gift_item( $cart_item ) ) ) {
			return '';
		}

		return $link;
	}

	public function lock_quantity_html( $product_quantity, $cart_item_key, $cart_item ) {
		if ( $this->is_current_gift_item( $cart_item ) || $this->is_marked_gift_item( $cart_item ) ) {
			return '<span class="itk-free-gift-quantity">1</span>';
		}

		return $product_quantity;
	}

	public function store_api_quantity_editable( $editable, $product, $cart_item = null ) {
		if ( is_array( $cart_item ) && ( $this->is_current_gift_item( $cart_item ) || $this->is_marked_gift_item( $cart_item ) ) ) {
			return false;
		}

		return $editable;
	}

	public function store_api_quantity_one( $value, $product, $cart_item = null ) {
		if ( is_array( $cart_item ) && ( $this->is_current_gift_item( $cart_item ) || $this->is_marked_gift_item( $cart_item ) ) ) {
			return 1;
		}

		return $value;
	}

	public function hide_gift_from_catalog( $visible, $product_id ) {
		$gift_id = $this->get_gift_product_id();

		if ( $gift_id && absint( $product_id ) === $gift_id ) {
			return false;
		}

		return $visible;
	}

	public function redirect_gift_product_page() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$gift_id = $this->get_gift_product_id();

		if ( $gift_id && absint( get_queried_object_id() ) === $gift_id ) {
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}
	}
}

function itk_wc_free_gift_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function () {
				if ( current_user_can( 'activate_plugins' ) ) {
					echo '<div class="notice notice-error"><p><strong>IT-Kayali WooCommerce Gratisprodukt:</strong> WooCommerce muss aktiviert sein.</p></div>';
				}
			}
		);
		return;
	}

	ITK_WC_Auto_Free_Gift::instance();
}
add_action( 'plugins_loaded', 'itk_wc_free_gift_boot', 20 );

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( ITK_WC_Auto_Free_Gift::OPTION_PRODUCT_ID, false ) ) {
			add_option( ITK_WC_Auto_Free_Gift::OPTION_PRODUCT_ID, 0 );
		}

		if ( false === get_option( ITK_WC_Auto_Free_Gift::OPTION_MIN_AMOUNT, false ) ) {
			add_option( ITK_WC_Auto_Free_Gift::OPTION_MIN_AMOUNT, 0 );
		}
	}
);
