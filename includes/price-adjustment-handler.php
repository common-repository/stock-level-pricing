<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adjusts product prices based on stock level rules.
 */

/**
 * Modify product prices based on stock level rules.
 *
 * @param float      $price Original product price.
 * @param WC_Product $product WooCommerce product object.
 * @return float Adjusted product price.
 */
function stocklvl_adjust_displayed_product_prices( $price, $product ) {

	// Check if this product instance is a clone for add-ons and skip if true.
	if ( isset( $product->has_addons ) && $product->has_addons ) {
		return $price;
	}

	if ( $product->get_meta( 'stocklvl_price_in_cart_recalculated' ) === 'yes' ) {
		return $price;
	}

	// Check if product manages stock and if stock quantity is greater than zero.
	if ( ! $product->managing_stock() || $product->get_stock_quantity() < 1 ) {
		return $price; // Exit if stock management is off or stock is less than 1.
	}

	// Check if product type is variable, if yes then return the original price.
	if ( $product->get_type() == 'variable' ) {
		return $price;
	}

	// Store the original price in a transient for a short period (5 minutes).
	set_transient( 'original_price_' . $product->get_id(), $price, 300 );

	// Remove the filter to prevent infinite loop.
	remove_filter( 'woocommerce_product_get_price', 'stocklvl_adjust_displayed_product_prices', 10 );

	// Fetch existing setting for price adjustment direction.
	$price_adjustment_direction = strtolower( get_option( 'woocommerce_slp_price_adjustment_direction', 'increase' ) );

	if ( $product->managing_stock() ) {
		$stock_quantity = $product->get_stock_quantity();
		$rules          = stocklvl_get_stock_level_pricing_rules( $product->get_id() );

		usort(
			$rules,
			function ( $a, $b ) {
				return $a['stock_level'] <=> $b['stock_level'];
			}
		);

		// Find the rule immediately greater than the current stock level
		$applicable_rule = null;
		foreach ( $rules as $rule ) {
			if ( $stock_quantity <= $rule['stock_level'] ) {
				$applicable_rule = $rule;
				break; // Found the rule just above the current stock level
			}
		}

		if ( $applicable_rule ) {
			if ( $applicable_rule['rule_type'] == 'fixed' ) {
				if ( isset( $applicable_rule['sale_price'] ) && $applicable_rule['sale_price'] !== 0 && $applicable_rule['sale_price'] != null ) {
					$price = $applicable_rule['sale_price'];
				} else {
					$price = $applicable_rule['regular_price'];
				}
			} elseif ( $applicable_rule['rule_type'] == 'percent' ) {
				$percentage_change = $applicable_rule['percentage_change'] / 100;
				$price             = ( $price_adjustment_direction == 'increase' ) ?
							$product->get_price() * ( 1 + $percentage_change ) :
							$product->get_price() * ( 1 - $percentage_change );
			}
		} else {
			// If no applicable rule is found, maintain the original price
			$price = get_transient( 'original_price_' . $product->get_id() );
		}
	}
	// Add the filter back before exiting the function.
	add_filter( 'woocommerce_product_get_price', 'stocklvl_adjust_displayed_product_prices', 10, 2 );

	return $price;
}



function stocklvl_customize_displayed_html_price( $html, $product ) {

	// Check if product manages stock and if stock quantity is greater than zero.
	if ( ! $product->managing_stock() || $product->get_stock_quantity() < 1 ) {
		return $html;
	}

	// Check if product type is variable, if yes then return the original html price.
	if ( $product->get_type() == 'variable' ) {
		return $html;
	}

	// Check if the product has stock level pricing rules.
	$product_id = $product->get_id();

	if ( stocklvl_has_product_level_rules( $product_id ) ) {
		$display_discount_as = get_option( 'woocommerce_slp_display_discount_as', 'regular_price' );

		// Fetch the original price from transient.
		$original_price = get_transient( 'original_price_' . $product_id );

		// Fetch the adjusted price (this will trigger the stocklvl_adjust_displayed_product_prices function).
		$adjusted_price = apply_filters( 'woocommerce_product_get_price', $original_price, $product );

		// Fetch the applicable rule.
		$stock_quantity = $product->get_stock_quantity();
		$rules          = stocklvl_get_stock_level_pricing_rules( $product_id );
		usort(
			$rules,
			function ( $a, $b ) {
				return $a['stock_level'] <=> $b['stock_level'];
			}
		);

		// Find the rule immediately greater than the current stock level
		$applicable_rule = null;
		foreach ( $rules as $rule ) {
			if ( $stock_quantity <= $rule['stock_level'] ) {
				$applicable_rule = $rule;
				break; // Found the rule just above the current stock level
			}
		}

		// Determine which price values to use.
		if ( $applicable_rule ) {
			// Handle fixed rules.
			if ( $applicable_rule['rule_type'] == 'fixed' ) {
				$original_price = $applicable_rule['regular_price'];
				if ( isset( $applicable_rule['sale_price'] ) && ! is_null( $applicable_rule['sale_price'] ) && is_numeric( $applicable_rule['sale_price'] ) ) {
					$adjusted_price = $applicable_rule['sale_price'];
					$html           = '<del>' . wc_price( $original_price ) . '</del> <ins>' . wc_price( $adjusted_price ) . '</ins>';
				} else {
					$adjusted_price = $original_price;
					$html           = wc_price( $adjusted_price );
				}
			} elseif ( $applicable_rule['rule_type'] == 'percent' ) {
				// Handle percent rules.
				if ( $display_discount_as == 'sale_price' ) {
					$html = '<del>' . wc_price( $original_price ) . '</del> <ins>' . wc_price( $adjusted_price ) . '</ins>';
				} else {
					$html = wc_price( $adjusted_price );
				}
			}
		}
	}

	return $html;
}

if ( ! is_admin() ) {
	add_filter( 'woocommerce_get_price_html', 'stocklvl_customize_displayed_html_price', 10, 2 );
	add_filter( 'woocommerce_product_get_price', 'stocklvl_adjust_displayed_product_prices', 10, 2 );
}


add_action(
	'woocommerce_before_calculate_totals',
	function ( \WC_Cart $cart ) {
		if ( ! empty( $cart->cart_contents ) ) {
			foreach ( $cart->cart_contents as $key => $cart_item ) {
				if ( $cart_item['data'] instanceof WC_Product ) {
					// Check if the product has stock level rules or is an add-on product.
					$has_rules  = stocklvl_has_product_level_rules( $cart_item['data']->get_id() );
					$has_addons = isset( $cart_item['addons'] ) && ! empty( $cart_item['addons'] );

					if ( $has_rules || $has_addons ) {
						$price = stocklvl_adjust_displayed_product_prices( false, $cart_item['data'] );
						if ( false !== $price ) {
							$price = apply_filters( 'stocklvlv_wcaddon_cart_recalculate', $price, $cart_item, $key );
							$cart_item['data']->set_price( $price );
							$cart_item['data']->add_meta_data( 'stocklvl_price_in_cart_recalculated', 'yes' );
						}
					}
				}
			}
		}
	},
	10,
	1
);
