<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly.
}
// Import DB handler to get access to the functions.
require_once 'db-handler.php';
/**
 * Fetch and decode global pricing rules from the database.
 *
 * @return array An array containing the decoded pricing rules.
 */
function stocklvl_fetch_and_decode_global_rules() {
    $raw_rules = stocklvl_get_global_stock_level_pricing_rules();
    $decoded_rules = array();
    foreach ( $raw_rules as $raw_rule ) {
        $decoded_rules[] = array(
            'product_ids'      => maybe_unserialize( $raw_rule['product_ids'] ),
            'category_ids'     => maybe_unserialize( $raw_rule['category_ids'] ),
            'rule_type'        => $raw_rule['rule_type'],
            'rule_title'       => $raw_rule['rule_title'],
            'percentage_type'  => $raw_rule['percentage_type'],
            'percentage_rules' => json_decode( $raw_rule['percentage_rules'], true ),
            'fixed_rules'      => json_decode( $raw_rule['fixed_rules'], true ),
        );
    }
    return $decoded_rules;
}

/**
 * Check if product-level pricing rules exist for a given product ID.
 *
 * @param int $product_id The ID of the product to check.
 * @return bool True if product-level rules exist, otherwise false.
 */
function stocklvl_has_product_level_rules(  $product_id  ) {
    // Skip execution if in the admin panel.
    if ( is_admin() ) {
        return false;
    }
    // Static variable for caching results.
    static $cache = array();
    // Check if there's a cached result for the given product ID.
    if ( isset( $cache[$product_id] ) ) {
        return $cache[$product_id];
    }
    global $wpdb;
    $table_slp = $wpdb->prefix . 'stock_level_pricing_rules';
    // Query the database to count the number of rules for the product.
    $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table_slp}` WHERE product_id = %d", $product_id ) );
    // Determine if product-level rules exist and cache the result.
    $has_rules = $count > 0;
    $cache[$product_id] = $has_rules;
    return $has_rules;
}

// Function to apply fixed rules.
function stocklvl_apply_fixed_rules(  $rule, $stock_quantity  ) {
    usort( $rule['fixed_rules'], function ( $a, $b ) {
        return $a['stock_level'] <=> $b['stock_level'];
    } );
    $applied_fixed = null;
    foreach ( $rule['fixed_rules'] as $fixed_rule ) {
        if ( $stock_quantity <= $fixed_rule['stock_level'] ) {
            $applied_fixed = $fixed_rule;
            break;
        }
    }
    return $applied_fixed;
    // This will be an array containing 'regular' and 'sale' keys.
}

// Main function to apply display pricing rules.
function stocklvl_apply_display_pricing_rules(  $price, $product  ) {
    // Check if this product instance is a clone for add-ons and skip if true.
    if ( isset( $product->is_cloned_for_addons ) && $product->is_cloned_for_addons ) {
        return $price;
    }
    // Remove the filter to prevent infinite loop.
    remove_filter( 'woocommerce_product_get_price', 'stocklvl_apply_display_pricing_rules', 10 );
    // Skip applying the global rule if product-level rules exist.
    $product_id = $product->get_id();
    if ( stocklvl_has_product_level_rules( $product_id ) ) {
        add_filter(
            'woocommerce_product_get_price',
            'stocklvl_apply_display_pricing_rules',
            10,
            2
        );
        return $price;
    }
    // Check if product manages stock and if stock quantity is greater than zero.
    if ( !$product->managing_stock() || $product->get_stock_quantity() < 1 ) {
        add_filter(
            'woocommerce_product_get_price',
            'stocklvl_apply_display_pricing_rules',
            10,
            2
        );
        return $price;
        // Exit if stock management is off or stock is less than 1.
    }
    $global_rules = stocklvl_fetch_and_decode_global_rules();
    $product_id = $product->get_id();
    $stock_quantity = $product->get_stock_quantity();
    $applied_price = null;
    $price = floatval( $price );
    foreach ( $global_rules as $rule ) {
        $product_ids = ( is_array( $rule['product_ids'] ) ? $rule['product_ids'] : explode( ',', $rule['product_ids'] ) );
        $category_ids = ( is_array( $rule['category_ids'] ) ? $rule['category_ids'] : explode( ',', $rule['category_ids'] ) );
        if ( in_array( $product_id, $product_ids ) || has_term( $category_ids, 'product_cat', $product_id ) ) {
            if ( $rule['rule_type'] === 'percentage' ) {
                $applied_price = stocklvl_apply_percentage_rules(
                    $rule,
                    $price,
                    $stock_quantity,
                    $product
                );
            } elseif ( $rule['rule_type'] === 'fixed' ) {
                $applied_fixed = stocklvl_apply_fixed_rules( $rule, $stock_quantity );
                if ( $applied_fixed !== null ) {
                    $product->set_regular_price( $applied_fixed['regular'] );
                    // Update the regular price.
                    if ( isset( $applied_fixed['sale'] ) && !empty( $applied_fixed['sale'] ) ) {
                        $product->set_sale_price( $applied_fixed['sale'] );
                        $applied_price = $applied_fixed['sale'];
                    } else {
                        $product->set_sale_price( '' );
                        // Clearing sale price.
                        $applied_price = $applied_fixed['regular'];
                    }
                }
            }
            if ( $applied_price !== null ) {
                break;
            }
        }
    }
    // If a modified price is calculated.
    if ( $applied_price !== null ) {
        $price = $applied_price;
    }
    // Add the filter back before exiting the function.
    add_filter(
        'woocommerce_product_get_price',
        'stocklvl_apply_display_pricing_rules',
        10,
        2
    );
    return $price;
}

function stocklvl_apply_variation_pricing_rules(  $price, $variation  ) {
    // Remove the filter to prevent infinite loop.
    remove_filter( 'woocommerce_product_variation_get_price', 'stocklvl_apply_variation_pricing_rules', 10 );
    $parent_product_id = $variation->get_parent_id();
    $variation_id = $variation->get_id();
    $stock_quantity = $variation->get_stock_quantity();
    // If the variation or its parent has product-level pricing rules, simply return the price without making changes.
    if ( stocklvl_has_variation_level_rules( $variation_id, $parent_product_id ) ) {
        // Add the filter back before exiting the function.
        add_filter(
            'woocommerce_product_variation_get_price',
            'stocklvl_apply_variation_pricing_rules',
            10,
            2
        );
        return $price;
    }
    // Check if variation manages stock and if stock quantity is greater than zero.
    if ( !$variation->managing_stock() || $variation->get_stock_quantity() < 1 ) {
        add_filter(
            'woocommerce_product_variation_get_price',
            'stocklvl_apply_variation_pricing_rules',
            10,
            2
        );
        return $price;
        // Exit if stock management is off or stock is less than 1.
    }
    if ( !$stock_quantity ) {
        // If there's no stock for the variation, get stock from the parent product.
        $parent_product = wc_get_product( $parent_product_id );
        $stock_quantity = $parent_product->get_stock_quantity();
    }
    $applied_price = null;
    $price = floatval( $price );
    $global_rules = stocklvl_fetch_and_decode_global_rules();
    foreach ( $global_rules as $rule ) {
        $product_ids = ( is_array( $rule['product_ids'] ) ? $rule['product_ids'] : explode( ',', $rule['product_ids'] ) );
        $category_ids = ( is_array( $rule['category_ids'] ) ? $rule['category_ids'] : explode( ',', $rule['category_ids'] ) );
        if ( in_array( $parent_product_id, $product_ids ) || has_term( $category_ids, 'product_cat', $parent_product_id ) ) {
            if ( $rule['rule_type'] === 'percentage' ) {
                $applied_price = stocklvl_apply_percentage_rules(
                    $rule,
                    $price,
                    $stock_quantity,
                    $variation
                );
            } elseif ( $rule['rule_type'] === 'fixed' ) {
                $applied_fixed = stocklvl_apply_fixed_rules( $rule, $stock_quantity );
                if ( $applied_fixed !== null ) {
                    $variation->set_regular_price( $applied_fixed['regular'] );
                    // Update the regular price.
                    if ( isset( $applied_fixed['sale'] ) && !empty( $applied_fixed['sale'] ) ) {
                        $variation->set_sale_price( $applied_fixed['sale'] );
                        $applied_price = $applied_fixed['sale'];
                    } else {
                        $variation->set_sale_price( '' );
                        // Clearing sale price.
                        $applied_price = $applied_fixed['regular'];
                    }
                }
            }
            if ( $applied_price !== null ) {
                break;
            }
        }
    }
    if ( $applied_price !== null ) {
        $price = $applied_price;
    }
    // Add the filter back before exiting the function.
    add_filter(
        'woocommerce_product_variation_get_price',
        'stocklvl_apply_variation_pricing_rules',
        10,
        2
    );
    return $price;
}

// Hooks to adjust displayed product prices.
if ( !is_admin() ) {
    add_filter(
        'woocommerce_product_get_price',
        'stocklvl_apply_display_pricing_rules',
        10,
        2
    );
    add_filter(
        'woocommerce_product_variation_get_price',
        'stocklvl_apply_variation_pricing_rules',
        10,
        2
    );
}