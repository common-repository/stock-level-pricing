<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Enqueue admin styles.
function stocklvl_pricing_enqueue_admin_styles() {
	// Use plugin version for versioning.
	$plugin_version = '1.0.0';
	wp_enqueue_style( 'stock-level-pricing-admin', plugin_dir_url( __FILE__ ) . 'css/admin.css' );
}
add_action( 'admin_enqueue_scripts', 'stocklvl_pricing_enqueue_admin_styles' );

function stocklvl_pricing_enqueue_my_custom_scripts() {
	global $post; // Declare $post as global at the beginning of the function.

	// Get the current screen information.
	$screen = get_current_screen();

	// Only enqueue the script and localize data on WooCommerce product pages.
	if ( 'product' === $screen->post_type ) {
		// Generate a new nonce for remove rule action.
		$remove_rule_nonce = wp_create_nonce( 'remove_rule_nonce' );

			$plugin_version      = '1.0.0';
			$script_dependencies = array( 'jquery' );

		wp_enqueue_script(
			'admin-main-product',
			plugin_dir_url( __FILE__ ) . 'admin/admin-main-product.js',
			$script_dependencies,
			$plugin_version,
			true
		);

		wp_enqueue_script(
			'admin-variations',
			plugin_dir_url( __FILE__ ) . 'admin/admin-variations.js',
			$script_dependencies,
			$plugin_version,
			true
		);

		$type = 'percent';
		if ( $post && isset( $post->ID ) ) {
			$rules = stocklvl_get_stock_level_pricing_rules( $post->ID );
			$type  = ! empty( $rules['percent'] ) ? 'percent' : ( ! empty( $rules['fixed'] ) ? 'fixed' : 'percent' );

			$existing_data = array(
				'initial_type'      => $type,
				'product_id'        => $post->ID,
				'is_variation'      => $post->post_type === 'product_variation',
				'remove_rule_nonce' => $remove_rule_nonce,
			);

			wp_localize_script( 'admin-main-product', 'stock_level_pricing_data', $existing_data );
			wp_localize_script( 'admin-variations', 'stock_level_pricing_data', $existing_data );
		}
	}
}


// Function to Enqueue selectWoo and select2.
function stocklvl_pricing_admin_scripts() {
	wp_enqueue_script( 'selectWoo', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full.min.js', array( 'jquery' ) );
	wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css' );
}
add_action( 'admin_enqueue_scripts', 'stocklvl_pricing_admin_scripts' );


// Hook into the 'admin_enqueue_scripts' action.
add_action( 'admin_enqueue_scripts', 'stocklvl_pricing_enqueue_my_custom_scripts' );
