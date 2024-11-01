<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function stocklvl_enqueue_stock_level_scripts() {
	// Check if it's a product page, if not, return early.
	if ( ! is_product() ) {
		return;
	}

	// Enqueue the script.
	wp_enqueue_script( 'frontend-stock-level-table', plugin_dir_url( __FILE__ ) . 'frontend-stock-level-table.js', array( 'jquery' ), '1.0.0', true );

	// Create and localize nonce.
	wp_localize_script(
		'frontend-stock-level-table',
		'slp_params',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'slp_nonce' ), // Create and pass nonce.
		)
	);
}
add_action( 'wp_enqueue_scripts', 'stocklvl_enqueue_stock_level_scripts' );

// Function to choose what rule is applicable for product.
function stocklvl_get_applicable_rule( $current_stock, $rules ) {
	usort(
		$rules,
		function ( $a, $b ) {
			return $a['stock_level'] <=> $b['stock_level'];
		}
	);

	$applicable_rule_index = null;
	foreach ( $rules as $index => $rule ) {
		if ( $current_stock <= $rule['stock_level'] ) {
			$applicable_rule_index = $index; // Save the last index that matches
			break; // Found the applicable rule
		}
	}

	if ( is_null( $applicable_rule_index ) ) {
		// If the current stock is less than the first rule's stock level, no rule applies
		return null;
	} elseif ( isset( $rules[ $applicable_rule_index + 1 ] ) ) {
		// If there's a next rule, return the current one
		return $rules[ $applicable_rule_index ];
	} else {
		// If the current stock is greater than any rule's stock level, return the last rule
		return end( $rules );
	}
}



// Helper function to get global rules for a given product.
function stocklvl_get_global_rules_for_product( $product_id ) {
	$global_rules = stocklvl_fetch_and_decode_global_rules();

	foreach ( $global_rules as $rule ) {
		$product_ids  = is_array( $rule['product_ids'] ) ? $rule['product_ids'] : explode( ',', $rule['product_ids'] );
		$category_ids = is_array( $rule['category_ids'] ) ? $rule['category_ids'] : explode( ',', $rule['category_ids'] );

		if ( in_array( $product_id, $product_ids ) || has_term( $category_ids, 'product_cat', $product_id ) ) {
			return $rule;  // Return the whole rule.
		}
	}
	return null;
}

// This function will format the global rule for processing.
function stocklvl_format_global_rule( $global_rule ) {
	$formatted_rules        = array();
	$global_percentage_type = isset( $global_rule['percentage_type'] ) ? strtolower( $global_rule['percentage_type'] ) : 'increase';

	// Handle percentage rules.
	if ( ! empty( $global_rule['percentage_rules'] ) ) {
		foreach ( $global_rule['percentage_rules'] as $rule ) {
			$rule['rule_type']                  = 'percentage';
			$rule['percentage_change']          = $rule['change'];
			$rule['price_adjustment_direction'] = $global_percentage_type;
			$formatted_rules[]                  = $rule;
		}
	}

	// Handle fixed rules.
	if ( ! empty( $global_rule['fixed_rules'] ) ) {
		foreach ( $global_rule['fixed_rules'] as $rule ) {
			$rule['rule_type']     = 'fixed';
			$rule['regular_price'] = $rule['regular'];
			$rule['sale_price']    = $rule['sale'];
			$formatted_rules[]     = $rule;
		}
	}

	return $formatted_rules;
}

function stocklvl_format_stock_level_display( $index, $rules ) {
	// Special case: If the stock level is 1, display it directly without a range.
	if ( $rules[ $index ]['stock_level'] == 1 ) {
		return '1';
	}

	if ( $index == 0 ) {
		// If it's the first rule and its stock level is greater than 1, format as "1 - [stock_level]".
		return '1 - ' . $rules[ $index ]['stock_level'];
	} elseif ( isset( $rules[ $index - 1 ] ) ) {
		// If it's not the first rule, calculate the range based on the previous rule's stock level.
		$prev_range = $rules[ $index - 1 ]['stock_level'] + 1;
		$range      = $rules[ $index ]['stock_level'];
		return $prev_range . ' - ' . $range;
	}

	// Removed the part for the last rule formatting as "[stock_level] +".
	// Add any additional handling here if needed or leave it as is for simplicity.
}




// Main function to display the stock level table on product page.
function stocklvl_display_stock_level_pricing_table_on_frontend() {
	// If not on product page, return early.
	if ( ! is_product() ) {
		return;
	}

	// If not on product page or if the "Show pricing table" setting is disabled, return early.
	if ( ! is_product() || 'yes' !== get_option( 'woocommerce_slp_show_pricing_table', 'no' ) ) {
		return;
	}

	// Adding the CSS for table.
	echo '<style>
        .stock-level-pricing-table tr.highlight {
            font-weight: bold; /* Keeps the text bold */
            box-shadow: 0 0px 5px #888888; /* Adds a shadow around the row */
        }

        .stock-level-pricing-table tr {
        opacity: 0.7; /* Decrease opacity for all rows */
        }

        .stock-level-pricing-table tr.highlight {
            opacity: 1; /* Full opacity for the highlighted row */
        }
    </style>';

	global $product;
	$product_id    = $product->get_id();
	$current_stock = $product->get_stock_quantity();

	// Fetch the original regular and sale prices directly from the product meta.
	$original_regular_price = get_post_meta( $product_id, '_regular_price', true );
	$original_sale_price    = get_post_meta( $product_id, '_sale_price', true );

	// Check if a sale price exists and is not empty, otherwise fall back to the regular price.
	$wc_regular_price           = $product->get_regular_price();
	$base_price_for_calculation = '' !== $original_sale_price ? $original_sale_price : $original_regular_price;

	$applicable_rules = stocklvl_get_global_rules_for_product( $product_id );

	// If the product does not manage stock, return early.
	if ( ! $product->managing_stock() ) {
		return;
	}

	// If the product does not manage stock or is out of stock or stock level is less than 1, return early.
	if ( ! $product->managing_stock() || ! $product->is_in_stock() || $product->get_stock_quantity() < 1 ) {
		return;
	}

	// Check if the product is a variation.
	if ( $product->is_type( 'variable' ) ) {
		// Get the default variation ID or first variation ID as a fallback.
		$default_variation_id = $product->get_default_attributes();
		if ( $default_variation_id ) {
			$default_variation = wc_get_product_variation_attributes( $default_variation_id );
		} else {
			$available_variations = $product->get_available_variations();
			$default_variation    = reset( $available_variations );
		}
		$product_id = $default_variation['variation_id'];
	}

	// Fetching the rules.
	$rules        = stocklvl_get_stock_level_pricing_rules( $product_id );
	$global_rules = stocklvl_get_global_rules_for_product( $product_id );

	if ( ! $rules && $global_rules ) {
		$rules = stocklvl_format_global_rule( $global_rules );
	}

	// Check for global rules if no product-level rules are found.
	if ( ! $rules ) {
		$global_rules = stocklvl_get_global_rules_for_product( $product_id );
		if ( $global_rules ) {
			$rules = stocklvl_format_global_rule( $global_rules );
		}
	}
	// No rules to display.
	if ( ! $rules ) {
		return;
	}

	$percent_rules = array();
	$fixed_rules   = array();

	foreach ( $rules as $rule ) {
		// If it's a global rule (i.e., it comes with its own percentage_type).
		if ( isset( $rule['percentage_rules'] ) ) {
			foreach ( $rule['percentage_rules'] as $global_rule ) {
				$temp_rule              = $global_rule;
				$temp_rule['rule_type'] = $rule['rule_type'];

				// Use the global rule's percentage_type.
				$temp_rule['price_adjustment_direction'] = isset( $global_rule['percentage_type'] ) ? strtolower( $global_rule['percentage_type'] ) : 'increase';

				// Other processing.
				$percent_rules[] = $temp_rule;
			}
		} else {
			// It's a local rule, use the global setting as default.
			$rule['price_adjustment_direction'] = strtolower( get_option( 'woocommerce_slp_price_adjustment_direction', 'increase' ) );

			// If the rule has its own direction setting, use it.
			if ( isset( $rule['price_adjustment_direction'] ) ) {
				$rule['price_adjustment_direction'] = strtolower( $rule['price_adjustment_direction'] );
			}
			if ( isset( $rule['change'] ) ) {
				$rule['percentage_change'] = $rule['change'];
			}
			if ( isset( $rule['regular'] ) ) {
				$rule['regular_price'] = $rule['regular'];
			}
			if ( isset( $rule['sale'] ) ) {
				$rule['sale_price'] = $rule['sale'];
			}

			// Recognizing both 'percent' and 'percentage' for rule type.
			if ( isset( $rule['rule_type'] ) && ( $rule['rule_type'] == 'percent' || $rule['rule_type'] == 'percentage' ) ) {
				$percent_rules[] = $rule;
			} else {
				$fixed_rules[] = $rule;
			}
		}
	}

	// Determine the applicable rule for percentage and fixed types.
	$applicable_percent_rule = stocklvl_get_applicable_rule( $current_stock, $percent_rules );
	$applicable_fixed_rule   = stocklvl_get_applicable_rule( $current_stock, $fixed_rules );

	// Fetch the settings for the column titles.
	$stock_level_column_title       = get_option( 'woocommerce_slp_stock_level_column_title', 'Stock level' );
	$percentage_change_column_title = get_option( 'woocommerce_slp_percentage_change', 'Percentage change' );
	$price_column_title             = get_option( 'woocommerce_slp_price', 'Price' );
	$table_explainer_text           = get_option( 'woocommerce_slp_explainer', 'Stock level pricing rules table explainer' );

	// Sort the percent rules by stock level.
	usort(
		$percent_rules,
		function ( $a, $b ) {
			return $a['stock_level'] <=> $b['stock_level'];
		}
	);

	// Sort the fixed rules by stock level.
	usort(
		$fixed_rules,
		function ( $a, $b ) {
			return $a['stock_level'] <=> $b['stock_level'];
		}
	);

	// If there are rules, display them in a table.
	if ( ! empty( $percent_rules ) || ! empty( $fixed_rules ) ) {

		// Loop through each percentage rule and generate table rows.
		if ( ! empty( $percent_rules ) ) {
			echo '<table class="stock-level-pricing-table">';
			echo '<thead><tr><th>' . esc_html( $stock_level_column_title ) . '</th><th>' . esc_html( $percentage_change_column_title ) . '</th><th>' . esc_html( $price_column_title ) . '</th></tr></thead>';
			echo '<tbody>';
			foreach ( $percent_rules as $index => $rule ) {
				// Determine if this rule is the applicable one.
				$is_applicable = $applicable_percent_rule && $rule['stock_level'] === $applicable_percent_rule['stock_level'] && $rule['percentage_change'] === $applicable_percent_rule['percentage_change'];

				$highlight = $is_applicable ? 'highlight' : '';
				echo "<tr class='{$highlight}'>";

				// Determine the price adjustment direction.
				$price_adjustment_direction = 'increase'; // Default value.
				if ( isset( $rule['percentage_type'] ) ) {
					// It's a global rule with its own percentage_type.
					$price_adjustment_direction = strtolower( $rule['percentage_type'] );
				} elseif ( isset( $global_rules['percentage_type'] ) ) {
					// Use the global rule's percentage_type if available.
					$price_adjustment_direction = strtolower( $global_rules['percentage_type'] );
				} else {
					// It's a local rule, use the global setting from woocommerce_slp_price_adjustment_direction.
					$price_adjustment_direction = strtolower( get_option( 'woocommerce_slp_price_adjustment_direction', 'increase' ) );
				}

				// Calculate the adjusted price based on the direction.
				$price_change   = ( $price_adjustment_direction == 'increase' )
							? ( 1 + ( $rule['percentage_change'] / 100 ) )
							: ( 1 - ( $rule['percentage_change'] / 100 ) );
				$adjusted_price = $base_price_for_calculation * $price_change;

				echo '<td>' . esc_html( stocklvl_format_stock_level_display( $index, $percent_rules ) ) . '</td>';
				echo '<td>' . esc_html( $rule['percentage_change'] ) . '%</td>';
				echo '<td>' . wp_kses_post( wc_price( $adjusted_price ) ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody>';
			echo '</table>';
		}
		if ( ! empty( $fixed_rules ) ) {
			echo '<table class="stock-level-pricing-table">';
			echo '<thead><tr><th>' . esc_html( $stock_level_column_title ) . '</th><th>' . esc_html( $percentage_change_column_title ) . '</th><th>' . esc_html( $price_column_title ) . '</th></tr></thead>';
			echo '<tbody>';
			foreach ( $fixed_rules as $index => $rule ) {
				// Determine if this rule is the applicable one.
				$is_applicable = ( $applicable_fixed_rule !== null ) && ( $rule['stock_level'] === $applicable_fixed_rule['stock_level'] );

				$highlight = $is_applicable ? 'highlight' : '';
				echo "<tr class='{$highlight}'>";

				$final_price = ( isset( $rule['sale_price'] ) && $rule['sale_price'] !== null && $rule['sale_price'] != null ) ? floatval( $rule['sale_price'] ) : floatval( $rule['regular_price'] );

				// Calculate percentage difference based on the presence of sale price.
				if ( isset( $rule['sale_price'] ) && $rule['sale_price'] != null ) {
					$percentage_difference = ( ( $final_price - floatval( $rule['regular_price'] ) ) / floatval( $rule['regular_price'] ) ) * 100;
				} else {
					$percentage_difference = ( ( $final_price - $base_price_for_calculation ) / $original_regular_price ) * 100;
				}

				echo '<td>' . esc_html( stocklvl_format_stock_level_display( $index, $fixed_rules ) ) . '</td>';
				echo '<td>' . esc_html( round( abs( $percentage_difference ), 2 ) ) . '%</td>';
				echo '<td>' . wp_kses_post( wc_price( $final_price ) ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody>';
			echo '</table>';
		}
		// Display the text right after the tables.
		echo '<p style="margin-top: 10px;">' . esc_html( $table_explainer_text ) . '</p>';
	}
}
add_action( 'woocommerce_single_product_summary', 'stocklvl_display_stock_level_pricing_table_on_frontend', 15 );

// Function to display the stock level table on product page for variations.
function stocklvl_get_variation_rules_and_display_ajax() {
	check_ajax_referer( 'slp_nonce', 'nonce' ); // Verify nonce.

	$variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;

	// Check if the "Show pricing table" setting is disabled.
	if ( 'yes' !== get_option( 'woocommerce_slp_show_pricing_table', 'no' ) ) {
		wp_die(); // If the setting is not enabled, exit the function.
	}

	// Continue with the rest of the function if the setting is enabled.
	if ( $variation_id <= 0 ) {
		wp_die(); // If no variation ID is present, exit the function.
	}

	$product_variation = wc_get_product( $variation_id );
	if ( ! $product_variation ) {
		wp_die(); // If the product variation does not exist, exit the function.
	}

	// Check if stock management is enabled for this variation.
	if ( ! $product_variation->managing_stock() ) {
		wp_die(); // If stock management is not enabled, exit the function.
	}

	// Check if the stock level is less than 1 or if the product is out of stock.
	$current_stock = $product_variation->get_stock_quantity();
	if ( $current_stock < 1 || ! $product_variation->is_in_stock() ) {
		wp_die(); // If the stock level is zero or less, or if the product is out of stock, exit the function.
	}

	$parent_id = $product_variation->get_parent_id();
	$rules     = stocklvl_get_stock_level_pricing_rules_for_variation( $variation_id, $parent_id );

	// If there are no rules for the variation, check for global rules.
	if ( ! $rules ) {
		$global_rules = stocklvl_get_global_rules_for_product( $parent_id ); // Fetch global rules using the parent ID.
		if ( $global_rules ) {
			foreach ( $global_rules as &$global_rule ) {
				// Normalize the rule keys.
				if ( isset( $global_rule['change'] ) ) {
					$global_rule['percentage_change'] = $global_rule['change'];
					unset( $global_rule['change'] ); // Remove the old key.
				}
				if ( isset( $global_rule['percent'] ) ) {
					$global_rule['percentage'] = $global_rule['percent'];
					unset( $global_rule['percent'] ); // Remove the old key.
				}
				if ( isset( $global_rule['regular'] ) ) {
					$global_rule['regular_price'] = $global_rule['regular'];
					unset( $global_rule['regular'] ); // Remove the old key.
				}
				if ( isset( $global_rule['sale'] ) ) {
					$global_rule['sale_price'] = $global_rule['sale'];
					unset( $global_rule['sale'] ); // Remove the old key.
				}
			}
			$rules = stocklvl_format_global_rule( $global_rules ); // Format the global rules to match the expected structure.
		}
	}

	// If there are still no rules, exit the function.
	if ( ! $rules ) {
		wp_die();
	}

	// Fetch the original sale price directly from the variation meta.
	$original_sale_price = get_post_meta( $variation_id, '_sale_price', true );

	// Check if a sale price exists and is not empty, otherwise fall back to the regular price.
	$wc_regular_price   = $product_variation->get_regular_price();
	$product_base_price = '' !== $original_sale_price ? $original_sale_price : $wc_regular_price;
	$current_stock      = $product_variation->get_stock_quantity();

	// Fetch rules for the variation.
	$rules = stocklvl_get_stock_level_pricing_rules_for_variation( $variation_id, $parent_id );
	if ( ! $rules ) {
		$global_rules = stocklvl_get_global_rules_for_product( $parent_id );
		if ( $global_rules ) {
			$rules = stocklvl_format_global_rule( $global_rules );
		}
	}

	// No rules to display.
	if ( ! $rules ) {
		wp_die();
	}

	// Split rules into percent and fixed rules, and set the price_adjustment_direction.
	$percent_rules = array();
	$fixed_rules   = array();
	foreach ( $rules as $rule ) {
		// Check if it's a global rule.
		if ( isset( $rule['percentage_rules'] ) ) {
			foreach ( $rule['percentage_rules'] as $global_rule ) {
				$temp_rule              = $global_rule;
				$temp_rule['rule_type'] = $rule['rule_type'];

				// Directly use the percentage_type from the global rule.
				$temp_rule['price_adjustment_direction'] = isset( $global_rule['percentage_type'] )
														? strtolower( $global_rule['percentage_type'] )
														: 'increase'; // Default to 'increase' if not specified.

				if ( $temp_rule['rule_type'] == 'percent' || $temp_rule['rule_type'] == 'percentage' ) {
					$percent_rules[] = $temp_rule;
				} else {
					$fixed_rules[] = $temp_rule;
				}
			}
		} else {
			// For local rules, use the global setting from the options.
			$rule['price_adjustment_direction'] = strtolower( get_option( 'woocommerce_slp_price_adjustment_direction', 'increase' ) );
			if ( $rule['rule_type'] == 'percent' || $rule['rule_type'] == 'percentage' ) {
				$percent_rules[] = $rule;
			} else {
				$fixed_rules[] = $rule;
			}
		}
	}

	// Determine the applicable rule for percentage and fixed types.
	$applicable_percent_rule = stocklvl_get_applicable_rule( $current_stock, $percent_rules );
	$applicable_fixed_rule   = stocklvl_get_applicable_rule( $current_stock, $fixed_rules );

	// Fetch the settings for the column titles.
	$stock_level_column_title       = get_option( 'woocommerce_slp_stock_level_column_title', 'Stock level' );
	$percentage_change_column_title = get_option( 'woocommerce_slp_percentage_change', 'Percentage change' );
	$price_column_title             = get_option( 'woocommerce_slp_price', 'Price' );
	$table_explainer_text           = get_option( 'woocommerce_slp_explainer', 'Stock level pricing rules table explainer' );

	// Sort the percent rules by stock level.
	usort(
		$percent_rules,
		function ( $a, $b ) {
			return $a['stock_level'] <=> $b['stock_level'];
		}
	);

	// Sort the fixed rules by stock level.
	usort(
		$fixed_rules,
		function ( $a, $b ) {
			return $a['stock_level'] <=> $b['stock_level'];
		}
	);

	// Begin output buffering.
	ob_start();

		echo '<div class="stock-level-pricing-wrapper" style="margin-top: 10px;">';  // Start wrapper.

	if ( ! empty( $percent_rules ) ) {
		echo '<table class="stock-level-pricing-table">';
		echo '<thead><tr><th>' . esc_html( $stock_level_column_title ) . '</th><th>' . esc_html( $percentage_change_column_title ) . '</th><th>' . esc_html( $price_column_title ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $percent_rules as $index => $rule ) {
			// Determine if this rule is the applicable one based on stock level comparison.
				$is_applicable = $applicable_percent_rule && $rule['stock_level'] === $applicable_percent_rule['stock_level'] && $rule['percentage_change'] === $applicable_percent_rule['percentage_change'];

				$highlight = $is_applicable ? 'highlight' : '';
				echo "<tr class='{$highlight}'>";

				// Determine the price adjustment direction.
				$price_adjustment_direction = 'increase'; // Default value.
			if ( isset( $rule['percentage_type'] ) ) {
				// It's a global rule with its own percentage_type.
				$price_adjustment_direction = strtolower( $rule['percentage_type'] );
			} elseif ( isset( $global_rules['percentage_type'] ) && ! isset( $rule['percentage_rules'] ) ) {
				// Use the global rule's percentage_type for local rules.
				$price_adjustment_direction = strtolower( $global_rules['percentage_type'] );
			} else {
				// It's a local rule, use the global setting from woocommerce_slp_price_adjustment_direction.
				$price_adjustment_direction = strtolower( get_option( 'woocommerce_slp_price_adjustment_direction', 'increase' ) );
			}

				// Calculate the adjusted price based on the direction.
				$price_change   = ( $price_adjustment_direction == 'increase' )
								? ( 1 + ( $rule['percentage_change'] / 100 ) )
								: ( 1 - ( $rule['percentage_change'] / 100 ) );
				$adjusted_price = $product_base_price * $price_change;

				echo '<td>' . esc_html( stocklvl_format_stock_level_display( $index, $percent_rules ) ) . '</td>';
				echo '<td>' . esc_html( $rule['percentage_change'] ) . '%</td>';
				echo '<td>' . wp_kses_post( wc_price( $adjusted_price ) ) . '</td>';
				echo '</tr>';
		}
			echo '</tbody>';
			echo '</table>';
	}

	if ( ! empty( $fixed_rules ) ) {
		echo '<table class="stock-level-pricing-table" style="margin-top: 10px;">';
		echo '<thead><tr><th>' . esc_html( $stock_level_column_title ) . '</th><th>' . esc_html( $percentage_change_column_title ) . '</th><th>' . esc_html( $price_column_title ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $fixed_rules as $index => $rule ) {
			$is_applicable = ( $applicable_fixed_rule !== null ) && ( $rule['stock_level'] === $applicable_fixed_rule['stock_level'] );

			$highlight = $is_applicable ? 'highlight' : '';
			echo "<tr class='{$highlight}'>";

			$final_price = ( isset( $rule['sale_price'] ) && $rule['sale_price'] !== null && $rule['sale_price'] != null ) ? floatval( $rule['sale_price'] ) : floatval( $rule['regular_price'] );

			// Calculate percentage difference based on the presence of sale price.
			if ( isset( $rule['sale_price'] ) && $rule['sale_price'] != null ) {
				$percentage_difference = ( ( $final_price - floatval( $rule['regular_price'] ) ) / floatval( $rule['regular_price'] ) ) * 100;
			} else {
				$percentage_difference = ( ( $final_price - $product_base_price ) / $wc_regular_price ) * 100;
			}

			echo '<td>' . esc_html( stocklvl_format_stock_level_display( $index, $fixed_rules ) ) . '</td>';
			echo '<td>' . esc_html( round( abs( $percentage_difference ), 2 ) ) . '%</td>';
			echo '<td>' . wp_kses_post( wc_price( $final_price ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

	}
		$output = ob_get_clean(); // Get the buffered output.
		echo wp_kses_post( $output );

		echo '<p style="margin-top: 10px;">' . esc_html( $table_explainer_text ) . '</p>';
		echo '</div>';
	wp_die();
}
add_action( 'wp_ajax_nopriv_get_variation_rules_and_display', 'stocklvl_get_variation_rules_and_display_ajax' );
add_action( 'wp_ajax_get_variation_rules_and_display', 'stocklvl_get_variation_rules_and_display_ajax' );
