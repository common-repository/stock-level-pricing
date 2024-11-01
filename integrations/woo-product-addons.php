<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

	/**
	 * Add extra addons costs to product price in cart.
	 *
	 * @param  float  $price
	 * @param  array  $cart_item
	 *
	 * @return int|mixed
	 */


// Woo Product Add-ons integration.
	add_filter(
		'woocommerce_addons_cloned_product_with_filtered_price',
		'stocklvl_wc_pao_prevent_addon_price_changes'
	);

	function stocklvl_wc_pao_prevent_addon_price_changes( $cloned_product ) {
		// Add a unique property to the cloned product object to identify it as having add-ons.
		$cloned_product->has_addons = true;

		return $cloned_product;
	}


	add_action(
		'stocklvlv_wcaddon_cart_recalculate',
		function ( $price, $cart_item ) {
			$extra_cost = 0.0; // Ensure extra_cost is initialized as a float

			if ( isset( $cart_item['addons'] ) && false !== $price ) {
				$price = (float) $price; // Ensure that price is treated as a float
				foreach ( $cart_item['addons'] as $addon ) {
					$price_type  = $addon['price_type'];
					$addon_price = (float) $addon['price']; // Explicitly cast to float

					switch ( $price_type ) {
						case 'percent':
							$extra_cost += $price * ( $addon_price / 100.0 ); // Calculate percentage based extra cost
							break;
						case 'fixed':
							$extra_cost += $addon_price / (float) $cart_item['quantity']; // Ensure division by quantity is accurate
							break;
						default:
							$extra_cost += $addon_price;
							break;
					}
				}

				return $price + $extra_cost; // Sum original price and extra cost
			}

			return $price;
		},
		10,
		3
	);
