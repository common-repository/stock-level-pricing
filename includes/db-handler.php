<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $wpdb;
$table_slp        = $wpdb->prefix . 'stock_level_pricing_rules';
$global_slp_table = $wpdb->prefix . 'global_stock_level_pricing_rules';


// Create stock level pricing table for product-specific rules.
function stocklvl_create_stock_level_pricing_table() {
	global $wpdb;
	$table_slp = $wpdb->prefix . 'stock_level_pricing_rules';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_slp (
        rule_id mediumint(9) NOT NULL AUTO_INCREMENT,
        product_id mediumint(9) NOT NULL,
        variation_id mediumint(9) DEFAULT NULL,
        rule_type varchar(50) NOT NULL,
        stock_level int NOT NULL,
        percentage_change float DEFAULT NULL,
        regular_price float DEFAULT NULL,
        sale_price float DEFAULT NULL,
        PRIMARY KEY  (rule_id)
    ) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

// Create stock level pricing table for global rules.
function stocklvl_create_global_stock_level_pricing_table() {
	global $wpdb;
	$table_slp = $wpdb->prefix . 'global_stock_level_pricing_rules';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_slp (
        rule_id mediumint(9) NOT NULL AUTO_INCREMENT,
        rule_title varchar(255) DEFAULT NULL,
        product_ids varchar(255) DEFAULT NULL,
        category_ids varchar(255) DEFAULT NULL,
        rule_type varchar(50) NOT NULL,
        percentage_type varchar(50) DEFAULT NULL,
        percentage_rules text DEFAULT NULL,
        fixed_rules text DEFAULT NULL,
        PRIMARY KEY  (rule_id)
    ) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

// Handle Product-based stock level rules.

// Insert new rules.
function stocklvl_insert_pricing_rules( $product_id, $rules, $variation_id = null ) {
	global $wpdb;
	global $table_slp;

	$product_id   = isset( $product_id ) ? intval( $product_id ) : 0;
	$variation_id = isset( $variation_id ) ? intval( $variation_id ) : null;

	if ( $variation_id !== null ) {
		$wpdb->delete( $table_slp, array( 'variation_id' => $variation_id ), array( '%d' ) );
	} else {
		$wpdb->delete( $table_slp, array( 'product_id' => $product_id ), array( '%d' ) );
	}

	foreach ( $rules as $rule ) {
		$valid = true;

		// Validate percentage_change.
		if ( isset( $rule['stocklvl_percentage_change'] ) ) {
			$percentage_change = floatval( $rule['stocklvl_percentage_change'] );
			if ( $percentage_change < -100 ) {
				$valid = false;
			}
		}

		// Validate regular_price.
		if ( isset( $rule['stocklvl_regular_price'] ) ) {
			$regular_price = floatval( $rule['stocklvl_regular_price'] );
			if ( $regular_price <= 0 ) {
				$valid = false;
			}
		}

		// Validate sale_price.
		if ( isset( $rule['stocklvl_sale_price'] ) ) {
			$sale_price = floatval( $rule['stocklvl_sale_price'] );
			if ( $sale_price <= 0 ) {
				$valid = false;
			}
		}

		if ( $valid ) {
			$data = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'rule_type'    => $rule['type'],
				'stock_level'  => $rule['stock_level'],
			);

			if ( $rule['type'] == 'percent' ) {
				$data['percentage_change'] = $rule['stocklvl_percentage_change'];
			} else {
				$data['regular_price'] = $rule['stocklvl_regular_price'];
				// Check if sale_price is set and is not an empty string.
				$data['sale_price'] = isset( $rule['stocklvl_sale_price'] ) && trim( $rule['stocklvl_sale_price'] ) !== '' ? floatval( $rule['stocklvl_sale_price'] ) : null;
			}

			$wpdb->insert( $table_slp, $data );
		}
	}
}

// Get stock level pricing rules with caching.
function stocklvl_get_stock_level_pricing_rules($product_id, $level = 'product') {
    global $wpdb;
    $table_slp = $wpdb->prefix . 'stock_level_pricing_rules';

    // Static variable for caching results.
    static $cache = [];

    // Create a unique cache key based on function parameters.
    $cache_key = $level . '_' . $product_id;

    // Check if there's a cached result for the given parameters.
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    if ($level === 'product') {
        $query = "SELECT * FROM {$table_slp} WHERE product_id = %d AND variation_id IS NULL";
    } elseif ($level === 'variation') {
        $query = "SELECT * FROM {$table_slp} WHERE variation_id = %d";
    } else {
        return array(); // Invalid level specified.
    }

    $results = $wpdb->get_results($wpdb->prepare($query, $product_id), ARRAY_A);

    foreach ($results as $key => $result) {
        if ($result['sale_price'] === null) {
            $results[$key]['sale_price'] = ''; // Consider as empty.
        }
        foreach ($result as $field => $value) {
            $results[$key][$field] = esc_html($value);
        }
    }

    // Cache the results before returning them.
    $cache[$cache_key] = $results;

    return $results;
}



// Update multiple pricing rules by their IDs.
function stocklvl_update_pricing_rules_by_id( $updated_rules ) {
	global $wpdb;
	global $table_slp;

	foreach ( $updated_rules as $rule ) {
		if ( isset( $rule['rule_id'] ) ) {
			$rule['rule_id'] = intval( $rule['rule_id'] );
			$wpdb->update( $table_slp, $rule, array( 'rule_id' => $rule['rule_id'] ) );
		}
	}
}


function stocklvl_delete_pricing_rule( $rule_id, $variation_id = null ) {
	global $wpdb;
	global $table_slp;

	$rule_id      = isset( $rule_id ) ? intval( $rule_id ) : 0;
	$variation_id = isset( $variation_id ) ? intval( $variation_id ) : null;

	$where = array( 'rule_id' => $rule_id );
	if ( $variation_id !== null ) {
		$where['variation_id'] = $variation_id;
	}
	$wpdb->delete( $table_slp, $where, array( '%d' ) );
}

// Handle Global stock level rules.
function stocklvl_insert_global_pricing_rules( $data ) {
	global $wpdb;
	global $global_slp_table;

	// Flag to track validation status.
	$valid = true;

	// Sanitization.
	$data = array_map( 'sanitize_text_field', $data );

	// Validate rule_title.
	if ( ! is_string( $data['rule_title'] ) ) {
		$valid = false;
	}

	// Validate percentage_type.
	$valid_percentage_types = array( 'increase', 'decrease', '' );
	if ( ! in_array( $data['percentage_type'], $valid_percentage_types, true ) ) {
		$valid = false;
	}

	// Validate JSON fields.
	if ( ! empty( $data['percentage_rules'] ) && json_decode( $data['percentage_rules'] ) === null ) {
		$valid = false;
	}
	if ( ! empty( $data['fixed_rules'] ) && json_decode( $data['fixed_rules'] ) === null ) {
		$valid = false;
	}

	// Proceed with insertion only if all validations pass.
	if ( $valid ) {
		$inserted = $wpdb->insert(
			$global_slp_table,
			$data,
			array(
				'%s', // product_ids.
				'%s', // category_ids.
				'%s', // rule_type.
				'%s', // rule_title.
				'%s', // percentage_type.
				'%s', // percentage_rules.
				'%s',  // fixed_rules.
			)
		);
		return $inserted;
	} else {
		// Handle validation failure. You can return an error message or perform other actions as needed.
		return 'Validation failed. The data provided is not valid.';
	}
}

// Get global stock level pricing rules.
function stocklvl_get_global_stock_level_pricing_rules() {
    global $wpdb, $global_slp_table;

    // Static variable for caching results.
    static $cache = null;

    // If cache is already populated, return cached results.
    if ($cache !== null) {
        return $cache;
    }

    // Check if we are in the admin area.
    if (is_admin()) {
        // get_current_screen() function is only available in the admin, so we include the admin file that defines it.
        if (!function_exists('get_current_screen')) {
            require_once ABSPATH . 'wp-admin/includes/screen.php';
        }

        $current_screen = get_current_screen();

        // If the current screen is set and it's not the specific admin page, return an empty array.
        if ($current_screen && $current_screen->id !== 'woocommerce_page_stock_level_pricing') {
            return array();
        }
    }

    // If we're on the frontend or the specific admin page, proceed with fetching the rules.
    $query = "SELECT * FROM {$global_slp_table}";
    $results = $wpdb->get_results($query, ARRAY_A);

    // Store the results in cache before returning.
    $cache = $results;

    return $results;
}





// Delete a global pricing rule.
function stocklvl_delete_global_pricing_rule( $rule_id ) {
	global $wpdb;
	global $global_slp_table;

	$rule_id = isset( $rule_id ) ? intval( $rule_id ) : 0;

	$where = array( 'rule_id' => $rule_id );
	$wpdb->delete( $global_slp_table, $where, array( '%d' ) );
}

// Handle the form submission for global rules.
add_action( 'admin_post_stocklvl_save_stock_level_rule', 'stocklvl_save_stock_level_rule' );

// Function to update an existing rule.
function stocklvl_update_global_pricing_rule( $data, $rule_id ) {
	global $wpdb;
	global $global_slp_table;

	// Flag to track validation status.
	$valid = true;

	$rule_id = intval( $rule_id );
	$data    = array_map( 'sanitize_text_field', $data );

	// Validate rule_title.
	if ( ! is_string( $data['rule_title'] ) ) {
		$valid = false;
	}

	// Validate percentage_type.
	$valid_percentage_types = array( 'increase', 'decrease', '' );
	if ( ! in_array( $data['percentage_type'], $valid_percentage_types, true ) ) {
		$valid = false;
	}

	// Validate JSON fields.
	if ( ! empty( $data['percentage_rules'] ) && json_decode( $data['percentage_rules'] ) === null ) {
		$valid = false;
	}
	if ( ! empty( $data['fixed_rules'] ) && json_decode( $data['fixed_rules'] ) === null ) {
		$valid = false;
	}

	// Proceed with update only if all validations pass.
	if ( $valid ) {
		$updated = $wpdb->update(
			$global_slp_table,
			$data,
			array( 'rule_id' => $rule_id ),
			array(
				'%s', // product_ids.
				'%s', // category_ids.
				'%s', // rule_type.
				'%s', // rule_title.
				'%s', // percentage_type.
				'%s', // percentage_rules.
				'%s',  // fixed_rules.
			),
			array( '%d' ) // rule_id.
		);
		return $updated;
	} else {
		// Handle validation failure. You can return an error message or perform other actions as needed.
		return 'Validation failed. The data provided is not valid.';
	}
}


function stocklvl_get_global_pricing_rule_by_id( $rule_id ) {
	global $wpdb;
	global $global_slp_table;

	$rule_id = intval( $rule_id );
	$query   = "SELECT * FROM {$global_slp_table} WHERE rule_id = %d";
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared.
	$result = $wpdb->get_row( $wpdb->prepare( $query, $rule_id ), ARRAY_A );

	return $result;
}



// For bulk Delete
function stocklvl_delete_stock_level_rules_action() {
	global $wpdb;
	global $global_slp_table;

	$rule_ids = isset( $_POST['rule_ids'] ) ? array_map( 'intval', $_POST['rule_ids'] ) : array();

	// Security check for capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized user' );
	}

	// Verify the nonce.
	if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'stocklvl_delete_stock_level_rules_action' ) ) {
		wp_die( 'Nonce verification failed' );
	}

	// Check if rule_ids is set.
	if ( isset( $_POST['rule_ids'] ) && is_array( $_POST['rule_ids'] ) ) {
		$rule_ids = array_map( 'intval', $_POST['rule_ids'] );

		// Loop through rule_ids and delete them.
		foreach ( $rule_ids as $rule_id ) {
			stocklvl_delete_global_pricing_rule( $rule_id );
		}

		echo esc_html( 'Rules deleted successfully' );
	} else {
		echo 'Invalid or missing rule IDs';
	}
	exit;
}


// For edit page Delete.
function stocklvl_delete_stock_level_rule_action() {
	// Check for nonce for security.
	check_admin_referer( 'delete_stock_level_rule_nonce' );

	global $wpdb, $global_slp_table;
	$rule_id = isset( $_GET['rule_id'] ) ? intval( $_GET['rule_id'] ) : 0;

	if ( $rule_id > 0 ) {
		$wpdb->delete( $global_slp_table, array( 'rule_id' => $rule_id ) );
		// Redirect back to the list of rules with a message.
		wp_redirect( admin_url( 'admin.php?page=stock_level_pricing&message=Rule+Deleted' ) );
		exit;
	} else {
		wp_die( esc_html( 'Invalid rule ID: ' . $rule_id ) );
	}
}

// Add global stock level rule.
function stocklvl_save_stock_level_rule() {
	global $wpdb;
	global $global_slp_table;

	// Security check.
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'stocklvl_save_stock_level_rule' ) ) {
		wp_die( 'Nonce verification failed.' );
	}

	// Initialize variables to ensure they are arrays.
	$sale_price_raw = array();
	$sale_price     = array();

	if ( ( isset( $_POST['stocklvl_apply_for_categories'] ) || isset( $_POST['stocklvl_apply_for_products'] ) ) && isset( $_POST['stocklvl_pricing-type'] ) && isset( $_POST['stocklvl_rule_title'] ) ) {
		$categories      = isset( $_POST['stocklvl_apply_for_categories'] ) && is_array( $_POST['stocklvl_apply_for_categories'] ) ? implode( ',', array_map( 'sanitize_text_field', wp_unslash( $_POST['stocklvl_apply_for_categories'] ) ) ) : '';
		$products        = isset( $_POST['stocklvl_apply_for_products'] ) && is_array( $_POST['stocklvl_apply_for_products'] ) ? implode( ',', array_map( 'sanitize_text_field', wp_unslash( $_POST['stocklvl_apply_for_products'] ) ) ) : '';
		$rule_type       = isset( $_POST['stocklvl_pricing-type'] ) ? sanitize_text_field( wp_unslash( $_POST['stocklvl_pricing-type'] ) ) : '';
		$rule_title      = isset( $_POST['stocklvl_rule_title'] ) ? sanitize_text_field( wp_unslash( $_POST['stocklvl_rule_title'] ) ) : '';
		$percentage_type = isset( $_POST['stocklvl_percentage-type'] ) ? sanitize_text_field( wp_unslash( $_POST['stocklvl_percentage-type'] ) ) : '';

		$stock_level_percent = isset( $_POST['stock_level_percent'] ) ? array_map( 'intval', $_POST['stock_level_percent'] ) : array();
		$percentage_change   = isset( $_POST['stocklvl_percentage_change'] ) ? array_map( 'floatval', $_POST['stocklvl_percentage_change'] ) : array();

		$stock_level_fixed = isset( $_POST['stock_level_fixed'] ) ? array_map( 'intval', $_POST['stock_level_fixed'] ) : array();
		$regular_price     = isset( $_POST['stocklvl_regular_price'] ) ? array_map( 'floatval', $_POST['stocklvl_regular_price'] ) : array();
		if ( isset( $_POST['stocklvl_sale_price'] ) ) {
			$sale_price_raw = (array) wp_unslash( $_POST['stocklvl_sale_price'] ); // Cast to array to ensure it's always an array.
			$sale_price     = array_map(
				function ( $price ) {
					return is_numeric( $price ) ? floatval( sanitize_text_field( $price ) ) : null; // Sanitize and convert to float.
				},
				$sale_price_raw
			);
		}

		// Serialize percentage_rules and fixed_rules only if their corresponding data is present.
		$rule_type        = sanitize_text_field( wp_unslash( $_POST['stocklvl_pricing-type'] ) );
		$percentage_rules = array();
		$fixed_rules      = array();

		// Conditional check for rule type to avoid processing missing data.
		if ( $rule_type === 'percentage' ) {
			// Assume $stock_level_percent and $percentage_change are always set when this rule type is selected.
			$percentage_rules = wp_json_encode(
				array_map(
					function ( $stock_level, $change ) {
						return compact( 'stock_level', 'change' );
					},
					$stock_level_percent,
					$percentage_change
				)
			);
		} elseif ( $rule_type === 'fixed' ) {
			// Similar approach for fixed_rules.
			$fixed_rules = wp_json_encode(
				array_map(
					function ( $stock_level, $regular, $sale ) {
						return compact( 'stock_level', 'regular', 'sale' );
					},
					$stock_level_fixed,
					$regular_price,
					$sale_price
				)
			);
		}

		$data = array(
			'product_ids'      => $products,
			'category_ids'     => $categories,
			'rule_type'        => $rule_type,
			'rule_title'       => $rule_title,
			'percentage_type'  => $percentage_type,
			'percentage_rules' => $percentage_rules,
			'fixed_rules'      => $fixed_rules,
		);

		// Determine the type of rule that is NOT being saved.
		$unused_rule_type = $rule_type === 'fixed' ? 'percentage_rules' : 'fixed_rules';

		// Set the unused rule type to NULL.
		$data[ $unused_rule_type ] = '';

		if ( isset( $_POST['rule_id'] ) && ! empty( $_POST['rule_id'] ) ) {
			// Update existing rule.
			$rule_id = intval( $_POST['rule_id'] );
			stocklvl_update_global_pricing_rule( $data, $rule_id );
		} else {
			// Insert new rule.
			stocklvl_insert_global_pricing_rules( $data );
		}

		// Redirect back to your rule list page.
		wp_redirect( esc_url_raw( admin_url( 'admin.php?page=stock_level_pricing' ) ) );
		exit();
	}
}
