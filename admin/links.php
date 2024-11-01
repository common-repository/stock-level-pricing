<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Add additional links to the plugin meta on the plugins page.
function stocklvl_plugin_row_meta( $links, $file ) {
	$plugin_base_name = plugin_basename( dirname( __DIR__ ) . '/stock-level-pricing.php' );

	if ( $file === $plugin_base_name ) {
		$docs_link = '<a href="https://stock-level-pricing.notion.site/Stock-Level-Pricing-Documentation-7399ba7841c24288b2587aab7356f786" target="_blank">' . esc_html__( 'Documentation', 'stock-level-pricing' ) . '</a>';
		$links[]   = $docs_link; // Append the new link to the existing links array.
	}

	return $links;
}
add_filter( 'plugin_row_meta', 'stocklvl_plugin_row_meta', 10, 2 );


// Add Settings link and additional links to the plugin page.
function stocklvl_add_plugin_page_settings_link( $links ) {
	$account_link  = '<span class="1"><a href="' . esc_url( admin_url( 'admin.php?page=stock-level-pricing-account' ) ) . '"><b style="color: green">' . esc_html__( 'Account', 'stock-level-pricing' ) . '</b></a> </span>';
	$contact_link  = '<span class="2"><a href="' . esc_url( admin_url( 'admin.php?page=stock-level-pricing-contact' ) ) . '"><b style="color: green">' . esc_html__( 'Contact Us', 'stock-level-pricing' ) . '</b></a> </span>';
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=stock_level_pricing' ) ) . '">' . esc_html__( 'Settings', 'stock-level-pricing' ) . '</a>';

	// Add the new links before the existing settings link.
	array_unshift( $links, $contact_link, $account_link, $settings_link );

	// Conditional to check if premium version is active.
	$is_premium_active = stocklvl_fs()->is__premium_only();

	// Check if the user does not have the premium version.
	if ( ! $is_premium_active ) {
		$premium_link = '<span class="3"><a href="' . esc_url( admin_url( 'admin.php?billing_cycle=annual&page=stock-level-pricing-pricing' ) ) . '"><b style="color: red">' . esc_html__( 'Go Premium', 'stock-level-pricing' ) . '</b></a></span>';
		array_unshift( $links, $premium_link );
	}

	return $links;
}

$stocklvl_plugin_basename = plugin_basename( dirname( __DIR__ ) . '/stock-level-pricing.php' );
add_filter( "plugin_action_links_$stocklvl_plugin_basename", 'stocklvl_add_plugin_page_settings_link' );
