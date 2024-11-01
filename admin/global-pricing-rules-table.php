<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Function to create new admin menu.
function stock_level_pricing_submenu() {
	add_submenu_page(
		'woocommerce', // Parent slug.
		__( 'Stock Level Pricing', 'stock-level-pricing' ), // Page title.
		__( 'Stock Level Pricing', 'stock-level-pricing' ), // Menu title.
		'manage_options', // Capability.
		'stock_level_pricing', // Menu slug.
		'stocklvl_display_stock_level_pricing_rules' // Callback function.
	);
}
add_action( 'admin_menu', 'stock_level_pricing_submenu' );
add_action( 'wp_ajax_delete_stock_level_rules', 'stocklvl_delete_stock_level_rules_action' );

// Function to get the name of a product by its ID.
function stocklvl_get_product_name_by_id( $id ) {
	$product = wc_get_product( $id );
	return $product ? $product->get_name() : 'Not selected';
}

// Function to get the name of a category by its ID.
function stocklvl_get_category_name_by_id( $id ) {
	$term = get_term( $id, 'product_cat' );
	if ( is_wp_error( $term ) ) {
		// Handle the error accordingly. For instance, log it or return a default string.
		return __( 'Not selected', 'stock-level-pricing' );
	}
	return $term ? $term->name : 'Not selected';
}

// Callback function to display the global table.
function stocklvl_display_stock_level_pricing_rules() {
	// Check user capability.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'stock-level-pricing' ) );
	}

	echo '<form id="stock-level-pricing-bulk-action-form" method="post">';
	// Query database for existing rules.
	$existing_rules = stocklvl_get_global_stock_level_pricing_rules();

	?>
<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Stock Level Pricing', 'stock-level-pricing' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=add_stock_level_rule' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Rule', 'stock-level-pricing' ); ?></a>
		<hr class="wp-header-end">
		<!-- Bulk Actions -->
		<div class="tablenav top" style="margin-bottom: 20px;">
			<div class="alignleft actions bulkactions">

					<!-- Add Nonce Field Here for Security -->
					<?php wp_nonce_field( 'stocklvl_delete_stock_level_rules_action', 'delete_stock_level_rules_nonce' ); ?>

				<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'stock-level-pricing' ); ?></label>
				<select name="action" id="bulk-action-selector-top">
					<option value="-1"><?php esc_html_e( 'Bulk Actions', 'stock-level-pricing' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'stock-level-pricing' ); ?></option>
				</select>
					<input type="submit" id="doaction" class="button action" value="<?php esc_attr_e( 'Apply', 'stock-level-pricing' ); ?>">
			</div>
		</div>

		<!-- Main Table -->
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" id="cb" class="manage-column column-cb check-column">
					<input id="cb-select-all-1" type="checkbox" style="position: relative; margin: 15px 2px 0px 10px;">
					</th>
					<th scope="col"><?php esc_html_e( 'Title', 'stock-level-pricing' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Products', 'stock-level-pricing' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Categories', 'stock-level-pricing' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type of Rule', 'stock-level-pricing' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Edit', 'stock-level-pricing' ); ?></th>
				</tr>
			</thead>
			
			<tbody>
				<?php
				// Query database for existing rules.
				$existing_rules = stocklvl_get_global_stock_level_pricing_rules();
				if ( is_array( $existing_rules ) && ! empty( $existing_rules ) ) {
					foreach ( $existing_rules as $rule ) {
						echo '<tr>';
						echo '<td><input type="checkbox" name="bulk_delete[]" value="' . esc_attr( $rule['rule_id'] ) . '"></td>';

						// Create the edit link with nonce for the title.
						$edit_url_for_title            = admin_url( "admin.php?page=edit_stock_level_rule&id={$rule['rule_id']}" );
						$edit_url_with_nonce_for_title = wp_nonce_url( $edit_url_for_title, 'edit_stock_level_rule_' . $rule['rule_id'] );
						echo "<td><a href='" . esc_url( $edit_url_with_nonce_for_title ) . "'>" . esc_html( $rule['rule_title'] ) . '</a></td>';

						$product_names         = array_map( 'stocklvl_get_product_name_by_id', explode( ',', $rule['product_ids'] ) );
						$escaped_product_names = array_map( 'esc_html', $product_names );
						echo '<td>' . implode( ', ', $escaped_product_names ) . '</td>';

						$category_names         = array_map( 'stocklvl_get_category_name_by_id', explode( ',', $rule['category_ids'] ) );
						$escaped_category_names = array_map( 'esc_html', $category_names );
						echo '<td>' . implode( ', ', $escaped_category_names ) . '</td>';

						echo '<td>' . esc_html( $rule['rule_type'] ) . '</td>';

						// Create the edit link with nonce for the Edit button.
						$edit_url_for_button            = admin_url( "admin.php?page=edit_stock_level_rule&id={$rule['rule_id']}" );
						$edit_url_with_nonce_for_button = wp_nonce_url( $edit_url_for_button, 'edit_stock_level_rule_' . $rule['rule_id'] );
						echo "<td><a href='" . esc_url( $edit_url_with_nonce_for_button ) . "'>Edit</a></td>";
						echo '</tr>';
					}
				} else {
					echo '<tr><td colspan="6">' . esc_html__( 'No rules found.', 'stock-level-pricing' ) . '</td></tr>';
				}
				?>
			</tbody>   
			
			<tfoot>
				<tr>
					<th scope="col"></th>
					<th scope="col"><?php esc_html_e( 'Title', 'stock-level-pricing' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Products', 'stock-level-pricing' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Categories', 'stock-level-pricing' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type of Rule', 'stock-level-pricing' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Edit', 'stock-level-pricing' ); ?></th>
				</tr>
			</tfoot>
		</table>
		
	</div>

	<?php
	echo '</form>';
}

// Function to enqueue scripts and add inline scripts only on the stock level pricing admin page.
function stocklvl_stock_pricing_enqueue_scripts( $hook ) {
	if ( $hook !== 'woocommerce_page_stock_level_pricing' ) {
		return;
	}

	// Register a dummy script handle if no separate JS file.
	wp_register_script( 'stock-level-pricing-dummy-handle', '' );
	wp_enqueue_script( 'stock-level-pricing-dummy-handle' );

	// Localize script to pass PHP variables to JS.
	$translation_array = array(
		'confirmMessage' => __( 'Are you sure you want to delete selected rules?', 'stock-level-pricing' ),
	);
	wp_localize_script( 'stock-level-pricing-dummy-handle', 'stocklvl', $translation_array );

	// Inline scripts.
	$script1 = "
        jQuery(document).ready(function($) {
            $('#cb-select-all-1').click(function() {
                $('input[type=\"checkbox\"]').prop('checked', this.checked);
            });
        });
    ";

	$script2 = "
        jQuery(document).ready(function($) {
            $('#stock-level-pricing-bulk-action-form').submit(function(e) {
                var selectedAction = $('#bulk-action-selector-top').val();
                if (selectedAction === 'delete') {
                    e.preventDefault();
                    var selectedIDs = [];
                    $('input[name=\"bulk_delete[]\"]:checked').each(function() {
                        selectedIDs.push($(this).val());
                    });

                    if (selectedIDs.length > 0 && confirm(stocklvl.confirmMessage)) {
                        var data = {
                            'action': 'delete_stock_level_rules',
                            'rule_ids': selectedIDs,
                            'security': $('#delete_stock_level_rules_nonce').val()
                        };

                        $.post(ajaxurl, data, function(response) {
                            alert(response);
                            location.reload();
                        });
                    }
                }
            });
        });
    ";

	wp_add_inline_script( 'stock-level-pricing-dummy-handle', $script1, 'after' );
	wp_add_inline_script( 'stock-level-pricing-dummy-handle', $script2, 'after' );
}
add_action( 'admin_enqueue_scripts', 'stocklvl_stock_pricing_enqueue_scripts' );
