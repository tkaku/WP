<?php
/**
 * Welcart_Tax class.
 *
 * @package Welcart e-Commerce
 */

if( !defined( 'ABSPATH' ) ) {
	exit;
}

class Welcart_Tax {

	/**
	 * Instance of this class.
	 */
	protected static $instance = null;

	public $tax_rate_standard;
	public $tax_rate_reduced;
	public $item_total_price;
	public $subtotal_standard;
	public $subtotal_reduced;
	public $discount_standard;
	public $discount_reduced;
	public $discount;
	public $tax_standard;
	public $tax_reduced;
	public $tax;
	public $cart_standard;
	public $cart_reduced;
	public $reduced_taxrate_mark;

	/**
	 * Constructor.
	 *
	 */
	public function __construct() {
		$this->reduced_taxrate_mark = apply_filters( 'usces_filter_reduced_taxrate_mark', __( '(*)', 'usces' ) );
		$this->initialize_data();
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {
		if( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Initialize
	 */
	public function initialize_data() {
		$options = get_option( 'usces' );
		$this->tax_rate_standard = ( isset( $options['tax_rate'] ) ) ? $options['tax_rate'] : 0;
		$this->tax_rate_reduced = ( isset( $options['tax_rate_reduced'] ) ) ? $options['tax_rate_reduced'] : 0;
		$this->item_total_price = 0;
		$this->subtotal_standard = 0;
		$this->subtotal_reduced = 0;
		$this->discount_standard = 0;
		$this->discount_reduced = 0;
		$this->discount = 0;
		$this->tax_standard = 0;
		$this->tax_reduced = 0;
		$this->tax = 0;
		$this->cart_standard = array();
		$this->cart_reduced = array();
	}

	public function get_sku_applicable_taxrate( $post_id, $sku ) {
		global $usces;

		$skus = $usces->get_skus( $post_id, 'code' );
		if( isset( $skus[$sku]['taxrate'] ) ) {
			$applicable_taxrate = $skus[$sku]['taxrate'];
		} else {
			$applicable_taxrate = 'standard';
		}
		return $applicable_taxrate;
	}

	public function get_sku_tax_rate( $post_id, $sku ) {
		global $usces;

		$applicable_taxrate = $this->get_sku_applicable_taxrate( $post_id, $sku );
		if( 'reduced' == $applicable_taxrate ) {
			$tax_rate = $usces->options['tax_rate_reduced'];
		} else {
			$tax_rate = $usces->options['tax_rate'];
		}
		return $tax_rate;
	}

	public function set_ordercart_applicable_taxrate( $ordercart_id, $sku ) {
		global $usces, $wpdb;

		if( isset( $sku['taxrate'] ) && 'reduced' == $sku['taxrate'] ) {
			$tkey = 'reduced';
			$tvalue = $usces->options['tax_rate_reduced'];
		} else {
			$tkey = 'standard';
			$tvalue = $usces->options['tax_rate'];
		}
		$query = $wpdb->prepare( "INSERT INTO {$wpdb->prefix}usces_ordercart_meta 
			( cart_id, meta_type, meta_key, meta_value ) VALUES ( %d, 'taxrate', %s, %s )", 
			$ordercart_id, $tkey, $tvalue
		);
		$wpdb->query( $query );
	}

	public function get_ordercart_applicable_taxrate( $ordercart_id, $post_id = 0, $sku = '' ) {
		$taxrate = usces_get_ordercart_meta( 'taxrate', $ordercart_id );
		if( $taxrate && isset( $taxrate[0]['meta_key'] ) ) {
			$applicable_taxrate = $taxrate[0]['meta_key'];
		} elseif( !empty( $post_id ) && !empty( $sku ) ) {
			$applicable_taxrate = $this->get_sku_applicable_taxrate( $post_id, $sku );
		} else {
			$applicable_taxrate = 'standard';
		}
		return $applicable_taxrate;
	}

	public function set_cart( $cart = array() ) {
		global $usces;

		if( empty( $cart ) ) {
			$cart = $usces->cart->get_cart();
		}

		$this->subtotal_standard = 0;
		$this->subtotal_reduced = 0;
		$this->cart_standard = array();
		$this->cart_reduced = array();

		foreach( (array)$cart as $cart_row ) {
			$items_price = (float)$cart_row['price'] * (float)$cart_row['quantity'];
			if( isset( $cart_row['cart_id'] ) ) {
				$taxrate = usces_get_ordercart_meta( 'taxrate', $cart_row['cart_id'] );
				if( $taxrate && isset( $taxrate[0]['meta_key'] ) ) {
					if( 'reduced' == $taxrate[0]['meta_key'] ) {
						$this->subtotal_reduced += (float)$items_price;
						$this->cart_reduced[] = $cart_row;
					} else {
						$this->subtotal_standard += (float)$items_price;
						$this->cart_standard[] = $cart_row;
					}
				} else {
					$sku = ( isset( $cart_row['sku_code'] ) ) ? $cart_row['sku_code'] : urldecode( $cart_row['sku'] );
					$skus = $usces->get_skus( $cart_row['post_id'], 'code' );
					if( isset( $skus[$sku]['taxrate'] ) && 'reduced' == $skus[$sku]['taxrate'] ) {
						$this->subtotal_reduced += (float)$items_price;
						$this->cart_reduced[] = $cart_row;
					} else {
						$this->subtotal_standard += (float)$items_price;
						$this->cart_standard[] = $cart_row;
					}
				}
			} else {
				$sku = urldecode( $cart_row['sku'] );
				$skus = $usces->get_skus( $cart_row['post_id'], 'code' );
				if( isset( $skus[$sku]['taxrate'] ) && 'reduced' == $skus[$sku]['taxrate'] ) {
					$this->subtotal_reduced += (float)$items_price;
					$this->cart_reduced[] = $cart_row;
				} else {
					$this->subtotal_standard += (float)$items_price;
					$this->cart_standard[] = $cart_row;
				}
			}
		}
	}

	public function get_order_discount( $cart = array(), $condition = array() ) {
		global $usces;

		if( empty( $cart ) ) {
			$cart = $usces->cart->get_cart();
		}
		if( empty( $condition ) ) {
			$condition = $usces->get_condition();
		}

		$this->set_cart( $cart );
		$this->discount_standard = 0;
		$this->discount_reduced = 0;
		$this->discount = 0;

		if( 'Promotionsale' == $condition['display_mode'] ) {
			if( 'discount' == $condition['campaign_privilege'] ) {
				if( 0 === (int)$condition['campaign_category'] ) {
					foreach( (array)$this->cart_standard as $cart_row ) {
						$items_discount = (float)sprintf( '%.3f', (float)$cart_row['price'] * (float)$cart_row['quantity'] * (float)$condition['privilege_discount'] / 100 );
						$this->discount_standard += $items_discount;
					}
					foreach( (array)$this->cart_reduced as $cart_row ) {
						$items_discount = (float)sprintf( '%.3f', (float)$cart_row['price'] * (float)$cart_row['quantity'] * (float)$condition['privilege_discount'] / 100 );
						$this->discount_reduced += $items_discount;
					}
				} else {
					foreach( (array)$this->cart_standard as $cart_row ) {
						if( in_category( (int)$condition['campaign_category'], $cart_row['post_id'] ) ) {
							$items_discount = (float)sprintf( '%.3f', (float)$cart_row['price'] * (float)$cart_row['quantity'] * (float)$condition['privilege_discount'] / 100 );
							$this->discount_standard += $items_discount;
						}
					}
					foreach( (array)$this->cart_reduced as $cart_row ) {
						if( in_category( (int)$condition['campaign_category'], $cart_row['post_id'] ) ) {
							$items_discount = (float)sprintf( '%.3f', (float)$cart_row['price'] * (float)$cart_row['quantity'] * (float)$condition['privilege_discount'] / 100 );
							$this->discount_reduced += $items_discount;
						}
					}
				}
				if( 0 != $this->discount_standard || 0 != $this->discount_reduced ) {
					$decimal = $usces->get_currency_decimal();
					if( 0 == $decimal ) {
						$this->discount_standard = ceil( $this->discount_standard );
						$this->discount_reduced = ceil( $this->discount_reduced );
					} else {
						$decipad = (int)str_pad( '1', $decimal+1, '0', STR_PAD_RIGHT );
						$this->discount_standard = ceil( $this->discount_standard * $decipad ) / $decipad;
						$this->discount_reduced = ceil( $this->discount_reduced * $decipad ) / $decipad;
					}
					$this->discount_standard *= -1;
					$this->discount_reduced *= -1;
					$this->discount = $this->discount_standard + $this->discount_reduced;
				}
			}
		}
		$this->discount = apply_filters( 'usces_order_discount', $this->discount, $cart );
		return $this->discount;
	}

	public function get_order_tax( $materials = array() ) {
		global $usces;

		$this->initialize_data();
		if( !empty( $materials ) ) {
			extract( $materials );// $total_items_price $shipping_charge $discount $cod_fee $use_point $carts $condition
		}
		if( empty( $carts ) ) {
			$cart = $usces->cart->get_cart();
		} else {
			$cart = $carts;
		}
		if( empty( $condition ) ) {
			$condition = $usces->get_condition();
		}

		$this->tax_rate_standard = ( isset( $condition['tax_rate'] ) ) ? $condition['tax_rate'] : $usces->options['tax_rate'];
		$this->tax_rate_reduced = ( isset( $condition['tax_rate_reduced'] ) ) ? $condition['tax_rate_reduced'] : $usces->options['tax_rate_reduced'];

		$reduced_taxrate_before = ( !empty( $order_id ) ) ? $this->was_reduced_taxrate_before( $order_id ) : false;
		if( $reduced_taxrate_before ) {
			$this->set_cart( $cart );
			$this->subtotal_standard = $usces->get_order_meta_value( 'subtotal_standard', $order_id );
			$this->subtotal_reduced = $usces->get_order_meta_value( 'subtotal_reduced', $order_id );
			$this->discount_standard = $usces->get_order_meta_value( 'discount_standard', $order_id );
			$this->discount_reduced = $usces->get_order_meta_value( 'discount_reduced', $order_id );
			$this->discount = $this->discount_standard + $this->discount_reduced;
			$this->item_total_price = $total_items_price;
		} else {
			$this->get_order_discount( $cart, $condition );
			$this->item_total_price = $this->subtotal_standard + $this->subtotal_reduced;
			if( 'all' == $condition['tax_target'] ) {
				if( !empty( $shipping_charge ) ) {
					$this->subtotal_standard += (float)$shipping_charge;
				}
				if( !empty( $cod_fee ) ) {
					$this->subtotal_standard += (float)$cod_fee;
				}
			}
		}

		if( 'include' == $condition['tax_mode'] ) {
			if( 0 < $this->subtotal_standard ) {
				$this->tax_standard = (float)sprintf( '%.3f', ( (float)$this->subtotal_standard + (float)$this->discount_standard ) * (float)$this->tax_rate_standard / ( 100 + (float)$this->tax_rate_standard ) );
			}
			if( 0 < $this->subtotal_reduced ) {
				$this->tax_reduced = (float)sprintf( '%.3f', ( (float)$this->subtotal_reduced + (float)$this->discount_reduced ) * (float)$this->tax_rate_reduced / ( 100 + (float)$this->tax_rate_reduced ) );
			}
		} else {
			if( 0 < $this->subtotal_standard ) {
				$this->tax_standard = (float)sprintf( '%.3f', ( (float)$this->subtotal_standard + (float)$this->discount_standard ) * (float)$this->tax_rate_standard / 100 );
			}
			if( 0 < $this->subtotal_reduced ) {
				$this->tax_reduced = (float)sprintf( '%.3f', ( (float)$this->subtotal_reduced + (float)$this->discount_reduced ) * (float)$this->tax_rate_reduced / 100 );
			}
		}
		$this->tax_standard = usces_tax_rounding_off( $this->tax_standard, $condition['tax_method'] );
		$this->tax_reduced = usces_tax_rounding_off( $this->tax_reduced, $condition['tax_method'] );
		$this->tax = $this->tax_standard + $this->tax_reduced;
	}

	public function set_order_condition_reduced_taxrate( $order_id ) {
		global $usces, $wpdb;

		$condition = usces_get_order_condition( $order_id );
		$condition['tax_mode'] = $usces->options['tax_mode'];
		$condition['tax_target'] = $usces->options['tax_target'];
		$condition['tax_rate'] = $usces->options['tax_rate'];
		$condition['applicable_taxrate'] = ( isset( $usces->options['applicable_taxrate'] ) ) ? $usces->options['applicable_taxrate'] : 'standard';
		$condition['tax_rate_reduced'] = ( isset( $usces->options['tax_rate_reduced'] ) ) ? $usces->options['tax_rate_reduced'] : $usces->options['tax_rate'];
		$query = $wpdb->prepare( "UPDATE {$wpdb->prefix}usces_order SET order_condition = %s WHERE ID = %d", serialize( $condition ), $order_id );
		$wpdb->query( $query );
	}

	public function was_reduced_taxrate_before( $order_id ) {
		global $usces;

		$subtotal_standard = $usces->get_order_meta_value( 'subtotal_standard', $order_id );
		$subtotal_reduced = $usces->get_order_meta_value( 'subtotal_reduced', $order_id );
		return ( $subtotal_standard || $subtotal_reduced );
	}
}

new Welcart_Tax();
