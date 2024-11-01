<?php

/**
 * Plugin Name: Stock Level Pricing
 * Plugin URI: https://wordpress.org/plugins/stock-level-pricing
 * Description: Change product pricing based on stock levels (inventory) in WooCommerce.
 * Version: 1.0.3
 * Author: Andrew
 * Author URI: https://twitter.com/aandrewkrk
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 4.9
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 6.9
 * WC tested up to: 8.7
 * Text Domain: stock-level-pricing
 * Domain Path: /languages
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly.
}
/**
 * Check if WooCommerce is active
 */
function stocklvl_check_woocommerce() {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        add_action( 'admin_notices', 'stocklvl_woocommerce_inactive_notice' );
        return false;
        // Stop further execution of the plugin code
    }
    return true;
}

/**
 * Admin notice for WooCommerce not active
 */
function stocklvl_woocommerce_inactive_notice() {
    ?>
	<div class="notice notice-warning is-dismissible">
		<p><?php 
    _e( 'Stock Level Pricing requires WooCommerce to be installed and active.', 'stock-level-pricing' );
    ?></p>
	</div>
	<?php 
}

// Check if WooCommerce is active, if not return early.
if ( !stocklvl_check_woocommerce() ) {
    return;
}
if ( !function_exists( 'stocklvl_fs' ) ) {
    // Create a helper function for easy SDK access.
    function stocklvl_fs() {
        global $stocklvl_fs;
        if ( !isset( $stocklvl_fs ) ) {
            // Include Freemius SDK.
            require_once __DIR__ . '/freemius/start.php';
            $stocklvl_fs = fs_dynamic_init( array(
                'id'             => '14366',
                'slug'           => 'stock-level-pricing',
                'type'           => 'plugin',
                'public_key'     => 'pk_09887a2549a06cbf0d1f2d1f27e08',
                'is_premium'     => false,
                'premium_suffix' => 'Premium',
                'has_addons'     => false,
                'has_paid_plans' => true,
                'trial'          => array(
                    'days'               => 7,
                    'is_require_payment' => true,
                ),
                'menu'           => array(
                    'first-path' => 'plugins.php',
                    'support'    => false,
                ),
                'is_live'        => true,
            ) );
        }
        return $stocklvl_fs;
    }

    // Init Freemius.
    stocklvl_fs();
    // Signal that SDK was initiated.
    do_action( 'stocklvl_fs_loaded' );
}
// Include the required files.
require_once plugin_dir_path( __FILE__ ) . 'admin/meta-box.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/price-adjustment-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/db-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/save-variations-rules.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/links.php';
require_once plugin_dir_path( __FILE__ ) . 'admin-scripts.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/save-parent-rules.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/settings-page.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/add-global-pricing-rules.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/edit-global-pricing-rules.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/global-pricing-rules-table.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/global-price-adjustment-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/variations-price-adjustment-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'frontend/display-stock-level-table.php';
require_once plugin_dir_path( __FILE__ ) . 'integrations/woo-product-addons.php';
// Register a hooks to create the stock level pricing tables upon plugin activation.
register_activation_hook( __FILE__, 'stocklvl_create_stock_level_pricing_table' );
register_activation_hook( __FILE__, 'stocklvl_create_global_stock_level_pricing_table' );
// HPOS and Cart and Checkout bloks compatibility declaration.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
// Cart and Checkout blocks compatibility declaration.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );