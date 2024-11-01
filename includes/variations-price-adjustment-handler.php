<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check if the product variation or its parent has stock level pricing rules.
 *
 * @param int $variation_id The ID of the variation.
 * @param int $parent_id The ID of the parent product.
 * @return bool True if there are applicable rules, false otherwise.
 */
function stocklvl_has_variation_level_rules( $variation_id, $parent_id ) {
	// Skip execution if in the admin panel.
	if ( is_admin() ) {
		return false;
	}

	// Static cache variable.
	static $cache = array();

	// Create a unique cache key for the combination of variation and parent IDs.
	$cache_key = $variation_id . '_' . $parent_id;

	// Check if the result is already cached.
	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	global $wpdb;
	$table_slp = $wpdb->prefix . 'stock_level_pricing_rules';

	// Query for variation-specific rules.
	$count_variation = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_slp} WHERE variation_id = %d", $variation_id ) );

	// Query for parent-specific rules when variation has no specific rules.
	$count_parent = $count_variation > 0 ? 0 : $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_slp} WHERE product_id = %d AND variation_id IS NULL", $parent_id ) );

	// Cache the result.
	$has_rules           = ( $count_variation > 0 || $count_parent > 0 );
	$cache[ $cache_key ] = $has_rules;

	return $has_rules;
}



/**
 * Fetch stock level pricing rules for a variation.
 *
 * @param int $variation_id The ID of the variation.
 * @param int $parent_id The ID of the parent product.
 * @return array An array of pricing rules.
 */
function stocklvl_get_stock_level_pricing_rules_for_variation( $variation_id, $parent_id ) {
	global $wpdb;
	$table_slp = $wpdb->prefix . 'stock_level_pricing_rules';

	// Static variable for caching results.
	static $cache = array();

	// Create a unique cache key based on function parameters.
	$cache_key = 'variation_' . $variation_id . '_parent_' . $parent_id;

	// Check if there's a cached result for the given parameters.
	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	// Include the table name directly and use placeholders only for the dynamic values.
	$rules_variation = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_slp} WHERE variation_id = %d", $variation_id ), ARRAY_A );
	if ( empty( $rules_variation ) ) {
		$rules_parent = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_slp} WHERE product_id = %d AND variation_id IS NULL", $parent_id ), ARRAY_A );
		$result       = $rules_parent;
	} else {
		$result = $rules_variation;
	}

	// Cache the results before returning them.
	$cache[ $cache_key ] = $result;

	return $result;
}



function stocklvl_adjust_displayed_variation_prices( $price, $variation ) {

	// Check if this product instance is a clone for add-ons and skip if true.
	if ( isset( $variation->is_cloned_for_addons ) && $variation->is_cloned_for_addons ) {
		return $price;
	}

	// Remove the filter to prevent infinite loop.
	remove_filter( 'woocommerce_product_variation_get_price', 'stocklvl_adjust_displayed_variation_prices', 10 );

	// If not managing stock, return original price.
	if ( ! $variation->managing_stock() && ! empty( $variation->get_parent_data()['managing_stock'] ) && ! $variation->get_parent_data()['managing_stock'] ) {
		add_filter( 'woocommerce_product_variation_get_price', 'stocklvl_adjust_displayed_variation_prices', 10, 2 );
		return $price;
	}

	$stock_quantity = $variation->managing_stock() ? $variation->get_stock_quantity() : wc_get_product( $variation->get_parent_id() )->get_stock_quantity();

	if ( $stock_quantity === null ) {
		add_filter( 'woocommerce_product_variation_get_price', 'stocklvl_adjust_displayed_variation_prices', 10, 2 );
		return $price;
	}

	$rules = stocklvl_get_stock_level_pricing_rules_for_variation( $variation->get_id(), $variation->get_parent_id() );
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

	if ( ! $applicable_rule ) {
		add_filter( 'woocommerce_product_variation_get_price', 'stocklvl_adjust_displayed_variation_prices', 10, 2 );
		return $price;
	}

	$price_adjustment_direction = strtolower( get_option( 'woocommerce_slp_price_adjustment_direction', 'increase' ) );
	$display_discount_as        = get_option( 'woocommerce_slp_display_discount_as', 'regular_price' );

	$wc_regular_price = floatval( $variation->get_regular_price() );
	$original_price   = stocklvl_get_original_variation_price( $variation ); // Fetch the original price without any adjustments.

	if ( $applicable_rule['rule_type'] === 'fixed' ) {
		if ( isset( $applicable_rule['sale_price'] ) && ! empty( $applicable_rule['sale_price'] ) ) {
			// Set sale price.
			$variation->set_sale_price( $applicable_rule['sale_price'] );
			// Set regular price.
			$variation->set_regular_price( $applicable_rule['regular_price'] );
			$price = $applicable_rule['sale_price'];
		} else {
			$price = $applicable_rule['regular_price'];
		}
	} elseif ( $applicable_rule['rule_type'] === 'percent' ) {
		$price_change   = ( $price_adjustment_direction === 'increase' ) ? ( 1 + ( $applicable_rule['percentage_change'] / 100 ) ) : ( 1 - ( $applicable_rule['percentage_change'] / 100 ) );
		$adjusted_price = $original_price * $price_change; // Use the original price for the adjustment.

		if ( $display_discount_as == 'sale_price' ) {
			$variation->set_regular_price( $wc_regular_price );
			$variation->set_sale_price( $adjusted_price );
			$price = $adjusted_price;
		} else {
			$price = $adjusted_price;
		}
	}

	// Add the filter back before exiting the function.
	add_filter( 'woocommerce_product_variation_get_price', 'stocklvl_adjust_displayed_variation_prices', 10, 2 );

	return $price;
}


/**
 * Fetch the original price of a variation without applying stock level pricing rules.
 *
 * @param WC_Product_Variation $variation The product variation object.
 * @return float The original price of the variation.
 */
function stocklvl_get_original_variation_price( $variation ) {
	// Remove the filter to prevent interference.
	remove_filter( 'woocommerce_product_variation_get_price', 'stocklvl_adjust_displayed_variation_prices', 10 );

	// Get the original price.
	$original_price = $variation->get_price();

	// Re-add the filter.
	add_filter( 'woocommerce_product_variation_get_price', 'stocklvl_adjust_displayed_variation_prices', 10, 2 );

	return $original_price;
}


/**
 * Adjust the price range for variable products.
 *
 * @param string              $price Price HTML.
 * @param WC_Product_Variable $product Variable product object.
 * @return string Adjusted price HTML.
 */
function stocklvl_adjust_variable_product_price_range( $price, $product ) {
	// Check if the product is a variable product.
	if ( ! $product->is_type( 'variable' ) ) {
		return $price;
	}

	// Fetch all variations of the product.
	$variations = $product->get_available_variations();

	// Initialize minimum and maximum prices to null.
	$min_price = null;
	$max_price = null;

	foreach ( $variations as $variation_data ) {
		// Get the variation object.
		$variation = wc_get_product( $variation_data['variation_id'] );

		// Fetch the original price of this variation.
		$original_price = stocklvl_get_original_variation_price( $variation );

		// Calculate the adjusted price for this variation.
		$adjusted_price = stocklvl_adjust_displayed_variation_prices( $original_price, $variation );

		// Update minimum and maximum prices based on the adjusted price.
		$min_price = is_null( $min_price ) ? $adjusted_price : min( $min_price, $adjusted_price );
		$max_price = is_null( $max_price ) ? $adjusted_price : max( $max_price, $adjusted_price );
	}

	// Format the minimum and maximum prices and return the price range.
	$price = wc_format_price_range( $min_price, $max_price );
	return $price;
}

// Hook the above function to the woocommerce_variable_sale_price_html and woocommerce_variable_price_html filters.
if ( ! is_admin() ) {
	add_filter( 'woocommerce_variable_sale_price_html', 'stocklvl_adjust_variable_product_price_range', 10, 2 );
	add_filter( 'woocommerce_variable_price_html', 'stocklvl_adjust_variable_product_price_range', 10, 2 );
	add_filter( 'woocommerce_product_variation_get_price', 'stocklvl_adjust_displayed_variation_prices', 10, 2 );
}
