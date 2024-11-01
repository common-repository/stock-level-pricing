<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly.
}
// Add the submenu for editing rules.
function stock_level_pricing_edit_rule_menu() {
    add_submenu_page(
        'options.php',
        __( 'Edit Stock Level Rule', 'stock-level-pricing' ),
        __( 'Edit Rule', 'stock-level-pricing' ),
        'manage_options',
        'edit_stock_level_rule',
        'stocklvl_edit_stock_level_rule_page_callback'
    );
    // Immediately remove it to hide from admin menu
    add_action( 'admin_head', function () {
        remove_submenu_page( 'options.php', 'edit_stock_level_rule' );
    } );
}

add_action( 'admin_menu', 'stock_level_pricing_edit_rule_menu' );
// Register the action hook for deleting a rule.
add_action( 'admin_post_delete_stock_level_rule', 'stocklvl_delete_stock_level_rule_action' );
// Callback function for the submenu.
function stocklvl_edit_stock_level_rule_page_callback() {
    global $wpdb;
    // Get rule_id from URL.
    $rule_id = ( isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0 );
    if ( $rule_id === 0 ) {
        echo esc_html__( 'Invalid Rule ID', 'stock-level-pricing' );
        return;
    }
    // Get and sanitize the nonce value.
    $nonce = ( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '' );
    // Verify nonce.
    if ( !$nonce || !wp_verify_nonce( $nonce, 'edit_stock_level_rule_' . $rule_id ) ) {
        wp_die( 'Security check failed' );
    }
    // Fetch the rule details from the database.
    // Prepare the query.
    $prepared_query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}global_stock_level_pricing_rules WHERE rule_id = %d", $rule_id );
    // Execute the query.
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared.
    $rule = $wpdb->get_row( $prepared_query );
    if ( is_null( $rule ) ) {
        echo esc_html__( 'Rule not found', 'stock-level-pricing' );
        return;
    }
    // Decode the JSON percentage_rules and fixed_rules strings.
    $percentage_rules_from_db = json_decode( $rule->percentage_rules, true );
    $fixed_rules_from_db = json_decode( $rule->fixed_rules, true );
    // Populate variables from the database rule here.
    $rule_title_from_db = $rule->rule_title;
    $category_ids_from_db = ( $rule->category_ids ? explode( ',', $rule->category_ids ) : array() );
    $product_ids_from_db = ( $rule->product_ids ? explode( ',', $rule->product_ids ) : array() );
    $rule_type_from_db = $rule->rule_type;
    // Fetch WooCommerce categories.
    $args = array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    );
    $categories = get_terms( $args );
    // Fetch WooCommerce products.
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
    );
    $products = get_posts( $args );
    ?>

	<div class="wrap" style="font-family: Arial, sans-serif;">
		<h1 style="margin-bottom: 20px;">Edit Stock Level Rule
		<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=stock_level_pricing' ) );
    ?>" style="margin-left: 20px; font-size: 14px;">&larr; Back to Rules</a>
		</h1>

		<!-- Parent container -->
		<div id="stock-level-rule-container">
			<!-- Form start -->
			<form id="stock-level-rule-form" action="<?php 
    echo esc_url( admin_url( 'admin-post.php' ) );
    ?>" method="post" style="border: 1px solid #ccc; padding: 20px; border-radius: 5px;">
				<!-- Nonce field -->
				<?php 
    wp_nonce_field( 'stocklvl_save_stock_level_rule', '_wpnonce' );
    ?>
				
				<!-- Hidden rule_id input here -->
				<input type="hidden" name="action" value="stocklvl_save_stock_level_rule">
				<input type="hidden" name="rule_id" value="<?php 
    echo esc_attr( $rule_id );
    ?>" />


		<!-- Form start -->
		<form id="stock-level-rule-form" action="<?php 
    echo esc_url( admin_url( 'admin-post.php' ) );
    ?>" method="post">

			
			<!-- Title -->
			<div style="margin-bottom: 2px; margin-top: 2px;">
				<label for="stocklvl_rule_title" style="font-weight: bold; margin-bottom: 2px;"><h3 style="margin: 2;"><?php 
    esc_html_e( 'Title:', 'stock-level-pricing' );
    ?></h3></label>
				<input type="text" id="stocklvl_rule_title" name="stocklvl_rule_title" value="<?php 
    echo esc_attr( $rule_title_from_db );
    ?>" style="width: 100%; padding: 2px;">
			</div>
			
			<!-- Spacing between Title and Categories -->
			<div style="margin-top: 30px;"></div>

		<!-- Apply for categories -->
		<div style="margin-bottom: 15px;">
			<label for="stocklvl_apply_for_categories" style="font-weight: bold; margin-bottom: 3px;"><?php 
    esc_html_e( 'Apply for categories:', 'stock-level-pricing' );
    ?></label><br>
			<select id="stocklvl_apply_for_categories" class="stock-level-selectWoo" name="stocklvl_apply_for_categories[]" multiple style="width: 100%; padding: 5px;">
				<?php 
    foreach ( $categories as $category ) {
        ?>
					<option value="<?php 
        echo esc_attr( $category->term_id );
        ?>" <?php 
        echo ( in_array( $category->term_id, $category_ids_from_db ) ? 'selected' : '' );
        ?>>
					<?php 
        echo esc_html( $category->name );
        ?>
					</option>
				<?php 
    }
    ?>
			</select>
		</div>

		<!-- Apply for products -->
		<div style="margin-bottom: 15px;">
			<label for="stocklvl_apply_for_products" style="font-weight: bold; margin-bottom: 3px;"><?php 
    esc_html_e( 'Apply for products:', 'stock-level-pricing' );
    ?></label><br>
			<select id="stocklvl_apply_for_products" class="stock-level-selectWoo" name="stocklvl_apply_for_products[]" multiple style="width: 100%; padding: 5px;">
				<?php 
    foreach ( $products as $product ) {
        ?>
					<option value="<?php 
        echo esc_attr( $product->ID );
        ?>" <?php 
        echo ( in_array( $product->ID, $product_ids_from_db ) ? 'selected' : '' );
        ?>>
					<?php 
        echo esc_html( $product->post_title );
        ?>
					</option>
				<?php 
    }
    ?>
			</select>
		</div>

		<!-- Rule Type (Pricing type) -->
		<div style="margin-bottom: 15px;">
			<label style="font-weight: bold; margin-bottom: 3px;"><?php 
    esc_html_e( 'Rule Type:', 'stock-level-pricing' );
    ?></label><br>
			<label><input type="radio" name="stocklvl_pricing-type" value="fixed" <?php 
    echo ( $rule_type_from_db == 'fixed' ? 'checked' : '' );
    ?>><?php 
    esc_html_e( 'Fixed', 'stock-level-pricing' );
    ?></label>

					<?php 
    ?>
					<!-- Placeholder for non-premium users -->
					<p><em>Percentage pricing type is available in the premium version.</em> <a href="/wp-admin/admin.php?billing_cycle=annual&page=stock-level-pricing-pricing">Upgrade now</a></p>
				<?php 
    ?>
				
		</div>

			<!-- Fields that show based on Pricing type -->
		<div id="percentage-fields" style="margin-bottom: 15px; display: none;">
			<label for="stocklvl_percentage-type" style="font-weight: bold; padding: 3px;">Percentage Increase or Decrease:</label><br>
				<select name="stocklvl_percentage-type" id="stocklvl_percentage-type" style="width: 100%; padding: 5px; margin-bottom: 10px;">
				<option value="increase">Increase</option>
				<option value="decrease">Decrease</option>
			</select>

			<!-- Stock Level and Percentage Change Table -->
			<table id="stocklvl_pricing_rules_table_percent" class="widefat">
				<thead>
					<tr>
						<th>Stock Level</th>
						<th>Percentage Change</th>
						<th>Action</th>
					</tr>
				</thead>
				<?php 
    ?>
			</table>
					<button type="button" id="stocklvl_add_rule_button_percent" class="button" style="margin-top: 5px;"><?php 
    esc_html_e( 'Add Rule', 'stock-level-pricing' );
    ?></button>
		</div>            


				<!-- Stock Level and Fixed Table -->
			<div id="stocklvl_fixed-fields" style="margin-bottom: 15px; display: none;">
			<table id="stocklvl_pricing_rules_table_fixed" class="widefat">
				<thead>
					<tr>
						<th>Stock Level</th>
						<th>Regular Price</th>
						<th>Sale Price</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php 
    if ( $rule_type_from_db == 'fixed' ) {
        foreach ( $fixed_rules_from_db as $detail ) {
            echo '<tr>';
            echo '<td><input type="number" name="stock_level_fixed[]" value="' . esc_attr( $detail['stock_level'] ) . '" /></td>';
            echo '<td><input type="number" name="stocklvl_regular_price[]" value="' . esc_attr( $detail['regular'] ) . '" /></td>';
            echo '<td><input type="number" name="stocklvl_sale_price[]" value="' . esc_attr( $detail['sale'] ) . '" /></td>';
            echo '<td><button type="button" class="button remove-rule-button">Remove</button></td>';
            echo '</tr>';
        }
    }
    ?>
				</tbody>
			</table>
				<button type="button" id="stocklvl_add_rule_button_fixed" class="button" style="margin-top: 5px;">Add Rule</button>
		</div>


		<!-- Additional fields, tables, and buttons go here - autopopulated from bd -->
				<div style="display: flex; justify-content: space-between;">
					<input type="submit" value="<?php 
    esc_attr_e( 'Save Rule', 'stock-level-pricing' );
    ?>" class="button button-primary">
					<a href="<?php 
    echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=delete_stock_level_rule&rule_id=' . $rule_id ), 'delete_stock_level_rule_nonce' ) );
    ?>"
						class="delete-rule-link" 
						style="color: red; align-self: center; margin-left: 20px;">
						<?php 
    esc_html_e( 'Remove Rule', 'stock-level-pricing' );
    ?>
					</a>

				</div>
			</form>
		</div>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			let radioButtons = document.getElementsByName('stocklvl_pricing-type');
			let percentageFields = document.getElementById('percentage-fields');
			let fixedFields = document.getElementById('stocklvl_fixed-fields');

			for (let radio of radioButtons) {
				radio.addEventListener('change', function () {
					if (this.value === 'percentage') {
						percentageFields.style.display = 'block';
						fixedFields.style.display = 'none';
					} else if (this.value === 'fixed') {
						fixedFields.style.display = 'block';
						percentageFields.style.display = 'none';
					}
				});
			}

			// Initialize SelectWoo
			jQuery('.stock-level-selectWoo').selectWoo({
				width: '100%'
			});


	const addButtonPercent = document.getElementById('stocklvl_add_rule_button_percent');
	const addButtonFixed = document.getElementById('stocklvl_add_rule_button_fixed');

	const tableBodyPercent = document.querySelector('#stocklvl_pricing_rules_table_percent tbody');
	const tableBodyFixed = document.querySelector('#stocklvl_pricing_rules_table_fixed tbody');

	addButtonPercent.addEventListener('click', function () {
		const newRow = document.createElement('tr');

		newRow.innerHTML = `
			<td><input type="number" name="stock_level_percent[]" value="" /></td>
			<td><input type="number" name="stocklvl_percentage_change[]" value="" />%</td>
			<td><button type="button" class="button remove-rule-button">Remove</button></td>
		`;

		tableBodyPercent.appendChild(newRow);
	});

	addButtonFixed.addEventListener('click', function () {
		const newRow = document.createElement('tr');

		newRow.innerHTML = `
			<td><input type="number" name="stock_level_fixed[]" value="" /></td>
			<td><input type="number" name="stocklvl_regular_price[]" value="" /></td>
			<td><input type="number" name="stocklvl_sale_price[]" value="" /></td>
			<td><button type="button" class="button remove-rule-button">Remove</button></td>
		`;

		tableBodyFixed.appendChild(newRow);
	});

		let percentageTypeFromDb = "<?php 
    echo esc_js( $rule->percentage_type );
    ?>";
		if (percentageTypeFromDb) {
			jQuery('#stocklvl_percentage-type').val(percentageTypeFromDb);
		}

	// Adding event listeners for remove buttons.
	document.addEventListener('click', function (event) {
		if (event.target && event.target.classList.contains('remove-rule-button')) {
			event.target.closest('tr').remove();
		}
	});

		const confirmationMessage = "<?php 
    echo esc_js( __( 'Are you sure you want to delete this rule?', 'stock-level-pricing' ) );
    ?>";

		document.addEventListener('click', function (event) {
			if (event.target && event.target.matches('.delete-rule-link')) {
				// Check confirmation and prevent default action if not confirmed
				if (!confirm(confirmationMessage)) {
					event.preventDefault();
				}
			}
		});


		let ruleTypeFromDb = '<?php 
    echo esc_js( $rule->rule_type );
    ?>';

	if (ruleTypeFromDb === 'percentage') {
		percentageFields.style.display = 'block';
		fixedFields.style.display = 'none';
	} else if (ruleTypeFromDb === 'fixed') {
		fixedFields.style.display = 'block';
		percentageFields.style.display = 'none';
	}
});

	</script>

	<?php 
}
