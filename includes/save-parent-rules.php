<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Save or update stock level pricing rules to the database.
function stocklvl_save_stock_level_pricing_rules( $post_id ) {

	// Check for WooCommerce save action, autosave.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		error_log( 'Exiting due to DOING_AUTOSAVE.' );
		return;
	}

	// Check if stock level data is being submitted.
	$rules_json = isset( $_POST['stock_level_data'] ) ? wp_unslash( $_POST['stock_level_data'] ) : '';
	if ( empty( $rules_json ) ) {
		// If no stock level data is submitted, no need to check the nonce or proceed further.
		return;
	}

	// Now, verify nonce as stock level data is present.
	if ( ! isset( $_POST['stock_level_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['stock_level_nonce'] ) ), 'stock_level_nonce_action_simple' ) ) {
		error_log( 'Nonce verification failed or not set when trying to save stock level rules.' );
		return;
	}

	global $wpdb, $table_slp;

	// Sanitize and decode the submitted JSON rules.
	$rules_json      = isset( $_POST['stock_level_data'] ) ? sanitize_text_field( wp_unslash( $_POST['stock_level_data'] ) ) : '';
	$submitted_rules = json_decode( stripslashes( $rules_json ), true );
	if ( ! is_array( $submitted_rules ) ) {
		$submitted_rules = array();
	}

	$existing_rules          = stocklvl_get_stock_level_pricing_rules( $post_id );
	$existing_rules_by_stock = array_column( $existing_rules, null, 'stock_level' );
	$existing_rule_ids       = array_column( $existing_rules, 'rule_id' );
	$submitted_rule_ids      = isset( $_POST['rule_id'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['rule_id'] ) ) : array();

	$rules_to_delete = array_diff( $existing_rule_ids, $submitted_rule_ids );
	foreach ( $rules_to_delete as $rule_id ) {
		$wpdb->delete( $table_slp, array( 'rule_id' => $rule_id ) );
	}

	$submitted_rule_types = array_column( $submitted_rules, 'type' );

	if ( in_array( 'fixed', $submitted_rule_types ) ) {
		$wpdb->delete(
			$table_slp,
			array(
				'product_id'   => $post_id,
				'rule_type'    => 'percent',
				'variation_id' => null,
			)
		);
	}

	if ( in_array( 'percent', $submitted_rule_types ) ) {
		$wpdb->delete(
			$table_slp,
			array(
				'product_id'   => $post_id,
				'rule_type'    => 'fixed',
				'variation_id' => null,
			)
		);
	}

	foreach ( $submitted_rules as $rule_data ) {
		stocklvl_update_stock_level_pricing_rules( $rule_data, $post_id, $table_slp );
	}

	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-success is-dismissible"><p>Your stock pricing rules have been updated!</p></div>';
		}
	);
}

function stocklvl_update_stock_level_pricing_rules( $rule_data, $post_id, $table_slp ) {
	global $wpdb;

	if ( ! isset( $rule_data['stock_level'] ) || ! isset( $rule_data['type'] ) ) {
		return;
	}

	$stock_level = absint( $rule_data['stock_level'] );
	$data        = array(
		'product_id'  => $post_id,
		'rule_type'   => $rule_data['type'],
		'stock_level' => absint( $rule_data['stock_level'] ),
	);

	if ( $rule_data['type'] == 'percent' && isset( $rule_data['stocklvl_percentage_change'] ) ) {
		$data['percentage_change'] = floatval( $rule_data['stocklvl_percentage_change'] );
	} elseif ( isset( $rule_data['stocklvl_regular_price'] ) ) {
		$data['regular_price'] = floatval( $rule_data['stocklvl_regular_price'] );
		// Check if sale_price is set and is not an empty string.
		if ( isset( $rule_data['stocklvl_sale_price'] ) && trim( $rule_data['stocklvl_sale_price'] ) !== '' ) {
			$data['sale_price'] = floatval( $rule_data['stocklvl_sale_price'] );
		} else {
			// Set sale_price to null if it's not set or is an empty string.
			$data['sale_price'] = null;
		}
	} else {
		return;
	}

	// Check if rule_id is set in the submitted data.
	if ( isset( $rule_data['rule_id'] ) && is_numeric( $rule_data['rule_id'] ) && $rule_data['rule_id'] > 0 ) {
		// If rule_id is set and is a valid number, update the existing rule.
		$where = array( 'rule_id' => $rule_data['rule_id'] );

		$rows_updated = $wpdb->update( $table_slp, $data, $where );

	} else {
		// Fallback to inserting a new rule if no valid rule_id is provided.
		$wpdb->insert( $table_slp, $data );
	}
}

// Nonce field in form for security.
function stocklvl_add_stock_level_nonce() {
	global $post;
	if ( $post && 'product' === $post->post_type ) {
		wp_nonce_field( 'stock_level_nonce_action_simple', 'stock_level_nonce' );
	}
}
add_action( 'add_meta_boxes', 'stocklvl_add_stock_level_nonce' );

// Hook for parent products.
add_action( 'save_post_product', 'stocklvl_save_stock_level_pricing_rules', 20, 1 );
