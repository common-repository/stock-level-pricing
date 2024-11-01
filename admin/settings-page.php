<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly.
// Hook to add admin menu.
add_filter( 'woocommerce_get_sections_products', 'stocklvl_add_section' );
function stocklvl_add_section(  $sections  ) {
    $sections['stock_level_pricing'] = __( 'Stock Level Pricing', 'stock-level-pricing' );
    return $sections;
}

// Hook to add settings.
add_filter(
    'woocommerce_get_settings_products',
    'stocklvl_all_settings',
    10,
    2
);
function stocklvl_all_settings(  $settings, $current_section  ) {
    // Check if the current section is what we want.
    if ( $current_section == 'stock_level_pricing' ) {
        $stocklvl_settings = array();
        // Add Title to the Settings.
        $stocklvl_settings[] = array(
            'name' => __( 'Stock Level Pricing Settings', 'stock-level-pricing' ),
            'type' => 'title',
            'desc' => __( 'The following options are specific to Stock Level Pricing plugin.', 'stock-level-pricing' ),
            'id'   => 'slp',
        );
        // Add new setting "Show pricing table".
        $stocklvl_settings[] = array(
            'name'     => __( 'Show pricing table', 'stock-level-pricing' ),
            'desc'     => __( 'Display pricing rules on product page with current stock rule highlighted', 'stock-level-pricing' ),
            'id'       => 'woocommerce_slp_show_pricing_table',
            'type'     => 'checkbox',
            'default'  => 'no',
            'desc_tip' => true,
        );
        // Fields here will be shown or hidden using JavaScript.
        // "Stock level column title" field.
        $stocklvl_settings[] = array(
            'name'     => __( 'Stock level column', 'stock-level-pricing' ),
            'desc_tip' => __( 'In this column the value of stock level from appropriate stock level rules will be displayed', 'stock-level-pricing' ),
            'id'       => 'woocommerce_slp_stock_level_column_title',
            'type'     => 'text',
            'css'      => 'min-width:300px;',
            'class'    => 'slp-dependent-field',
            'default'  => __( 'Stock level', 'stock-level-pricing' ),
        );
        // "Percentage Change" field.
        $stocklvl_settings[] = array(
            'name'     => __( 'Percentage change column', 'stock-level-pricing' ),
            'desc_tip' => __( 'In this column, the % change between regular product price and price set by stock level rules will be calculated', 'stock-level-pricing' ),
            'id'       => 'woocommerce_slp_percentage_change',
            'type'     => 'text',
            'css'      => 'min-width:100px;',
            'class'    => 'slp-dependent-field',
            'default'  => __( 'Percentage change', 'stock-level-pricing' ),
        );
        // "Price" field.
        $stocklvl_settings[] = array(
            'name'     => __( 'Price column', 'stock-level-pricing' ),
            'desc_tip' => __( 'In this column, you will be able to see the final calculated price of the product set by stock level rules', 'stock-level-pricing' ),
            'id'       => 'woocommerce_slp_price',
            'type'     => 'text',
            'css'      => 'min-width:100px;',
            'class'    => 'slp-dependent-field',
            'default'  => __( 'Price', 'stock-level-pricing' ),
        );
        // "Explainer" field.
        $stocklvl_settings[] = array(
            'name'     => __( 'Table explainer', 'stock-level-pricing' ),
            'desc_tip' => __( 'You can add text that will appear right after the table to explain how pricing works', 'stock-level-pricing' ),
            'id'       => 'woocommerce_slp_explainer',
            'type'     => 'text',
            'css'      => 'min-width:100px;',
            'class'    => 'slp-dependent-field',
            'default'  => __( 'Stock level pricing rules table explainer', 'stock-level-pricing' ),
        );
        // Conditional to check if premium version is active.
        $is_premium_active = stocklvl_fs()->is__premium_only();
        // Check if the user does not have the premium version.
        if ( !$is_premium_active ) {
            // Add non-interactive settings for non-premium users.
            // Add setting for "Decrease or Increase Price by %".
            $stocklvl_settings[] = array(
                'name'     => __( 'Adjust Price by %' ),
                'desc'     => '<p class="description"><b>Available in the premium version </b><a target="_blank" style="color:red" href="/wp-admin/admin.php?billing_cycle=annual&page=stock-level-pricing-pricing">Upgrade now</a></p>',
                'id'       => 'woocommerce_slp_price_adjustment_direction_non_premium',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select non-premium-dropdown',
                'disabled' => true,
                'options'  => array(
                    'increase' => __( 'Increase', 'stock-level-pricing' ),
                    'decrease' => __( 'Decrease', 'stock-level-pricing' ),
                ),
            );
            // Add setting for "Display % discounted price as".
            $stocklvl_settings[] = array(
                'name'     => __( 'Display % discounted price as' ),
                'desc'     => '<p class="description"><b>Available in the premium version </b><a target="_blank" style="color:red" href="/wp-admin/admin.php?billing_cycle=annual&page=stock-level-pricing-pricing">Upgrade now</a></p>',
                'id'       => 'woocommerce_slp_display_discount_as_non_premium',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select non-premium-dropdown',
                'disabled' => true,
                'options'  => array(
                    'regular_price' => __( 'Regular Price', 'stock-level-pricing' ),
                    'sale_price'    => __( 'Sale Price', 'stock-level-pricing' ),
                ),
            );
        }
        // Save button.
        $stocklvl_settings[] = array(
            'type' => 'sectionend',
            'id'   => 'slp',
        );
        // Add action to trigger recalculation when settings are saved.
        add_action( 'woocommerce_update_options_products_stock_level_pricing', 'stocklvl_clear_woocommerce_cart_cache' );
        return $stocklvl_settings;
    }
    return $settings;
}

function stocklvl_clear_woocommerce_cart_cache() {
    // Clear WooCommerce cart cache.
    WC_Cache_Helper::get_transient_version( 'cart', true );
}

// Enqueue JavaScript for settings page to handle the toggle functionality.
function stocklvl_slp_enqueue_admin_scripts(  $hook  ) {
    if ( 'woocommerce_page_wc-settings' !== $hook ) {
        return;
    }
    wp_enqueue_script(
        'slp-settings-page-js',
        plugin_dir_url( __FILE__ ) . 'settings-page.js',
        array('jquery'),
        null,
        true
    );
}

add_action( 'admin_enqueue_scripts', 'stocklvl_slp_enqueue_admin_scripts' );