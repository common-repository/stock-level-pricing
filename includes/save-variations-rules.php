<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $wpdb;
$table_slp = $wpdb->prefix . 'stock_level_pricing_rules';

// Register the hook for saving rule.
add_action( 'wp_ajax_stocklvl_save_stock_level_pricing_rules', 'stocklvl_handle_ajax_save' );

// Function to handle AJAX save.
function stocklvl_handle_ajax_save() {
	global $wpdb, $table_slp;

		// Verify the nonce.
	if ( ! isset( $_POST['stock_level_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['stock_level_nonce'] ) ), 'stock_level_nonce_action' ) ) {
		wp_send_json_error( 'Nonce verification failed!' );
		die();
	}

	if ( isset( $_POST['rules'] ) && is_string( $_POST['rules'] ) ) {
		$decoded_rules = json_decode( stripslashes( sanitize_text_field( wp_unslash( $_POST['rules'] ) ) ), true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			foreach ( $decoded_rules as $rules_group ) {
				if ( ! isset( $rules_group['rules'] ) || ! is_array( $rules_group['rules'] ) ) {
					continue;
				}
				// Initialize empty array to collect existing rule IDs.
				$existing_rule_ids = array();

				// Loop through each rule in the rules group.
				foreach ( $rules_group['rules'] as $rule ) {
					$variation_id = $rule['variation_id'] ?? null;
					$product_id   = $rule['product_id'] ?? null;
					$rule_id      = $rule['rule_id'] ?? null;

					// Logic to delete rules that are no longer needed.
					$existing_rule_ids = array_map(
						function ( $r ) {
							return $r['rule_id'];
						},
						stocklvl_get_stock_level_pricing_rules( null, $variation_id )
					);

					// Prepare data for database insertion or update.
					$data = array(
						'variation_id' => $variation_id,
						'product_id'   => $product_id,
						'rule_type'    => $rule['rule_type'],
						'stock_level'  => $rule['stock_level'],
					);

					if ( isset( $rule['stocklvl_regular_price'] ) ) {
						$data['regular_price'] = $rule['stocklvl_regular_price'];
					}
					// Prepare data for sale_price.
					if ( isset( $rule['stocklvl_sale_price'] ) ) {
						if ( $rule['stocklvl_sale_price'] === '' || is_null( $rule['stocklvl_sale_price'] ) ) {
							$data['sale_price'] = null; // Assign null for empty sale_price.
						} else {
							$data['sale_price'] = $rule['stocklvl_sale_price']; // Assign actual value if not empty.
						}
					} else {
						$data['sale_price'] = null; // Assign null if sale_price is not set.
					}
					if ( isset( $rule['stocklvl_percentage_change'] ) ) {
						$data['percentage_change'] = $rule['stocklvl_percentage_change'];
					}

					if ( $rule_id ) {
						$wpdb->update( $table_slp, $data, array( 'rule_id' => $rule_id ) );
					} else {
						$wpdb->insert( $table_slp, $data );
					}
				}

				// Logic to delete rules.
				$submitted_rule_ids = array_map(
					function ( $r ) {
						return $r['rule_id'] ?? null;
					},
					$rules_group
				);
				$rule_ids_to_delete = array_diff( $existing_rule_ids, $submitted_rule_ids );

				foreach ( $rule_ids_to_delete as $rule_id_to_delete ) {
					$wpdb->delete( $table_slp, array( 'rule_id' => $rule_id_to_delete ) );
				}
			}
			wp_send_json_success( 'Data saved successfully' );
		} else {
			wp_send_json_error( 'JSON Decode Error' );
		}
	} else {
		wp_send_json_error( '$_POST["rules"] is not set or not a string.' );
	}
}

// Register the AJAX hook for removing a rule.
add_action( 'wp_ajax_remove_rule', 'stocklvl_handle_ajax_remove_rule' );

function stocklvl_handle_ajax_remove_rule() {
	global $wpdb, $table_slp;

	// Verify the nonce.
	if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'remove_rule_nonce' ) ) {
		wp_send_json_error( 'Nonce verification failed!' );
		die();
	}

	// Check if the rule_id is set.
	if ( isset( $_POST['rule_id'] ) ) {
		$rule_id = intval( $_POST['rule_id'] );

		// Delete the rule.
		$result = $wpdb->delete( $table_slp, array( 'rule_id' => $rule_id ) );

		if ( $result ) {
			wp_send_json_success( 'Rule removed successfully.' );
		} else {
			wp_send_json_error( 'Failed to remove rule.' );
		}
	} else {
		wp_send_json_error( 'Rule ID is not set.' );
	}
}
