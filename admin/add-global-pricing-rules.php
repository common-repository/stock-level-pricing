<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly.
}
// Check if WooCommerce is active before using its functions.
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
    // Stop execution if WooCommerce is not active.
}
// Conditional to check if premium version is active.
$is_premium_active = stocklvl_fs()->is__premium_only();
// Function to add submenu.
function stock_level_pricing_add_rule_menu() {
    add_submenu_page(
        'options.php',
        __( 'Add New Stock Level Rule', 'stock-level-pricing' ),
        __( 'Add New Rule', 'stock-level-pricing' ),
        'manage_options',
        'add_stock_level_rule',
        'stocklvl_add_stock_level_rule_page_callback'
    );
    // Immediately remove it to hide from admin menu
    add_action( 'admin_head', function () {
        remove_submenu_page( 'options.php', 'add_stock_level_rule' );
    } );
}

add_action( 'admin_menu', 'stock_level_pricing_add_rule_menu' );
// Callback function for submenu.
function stocklvl_add_stock_level_rule_page_callback() {
    // Fetch WooCommerce categories.
    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ) );
    // Fetch WooCommerce products.
    $products = get_posts( array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
    ) );
    ?>

	<div class="wrap" style="font-family: Arial, sans-serif;">
		<h1 style="margin-bottom: 20px;"><?php 
    esc_html_e( 'Add New Stock Level Rule', 'stock-level-pricing' );
    ?>
		<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=stock_level_pricing' ) );
    ?>" style="margin-left: 20px; font-size: 14px;">&larr; <?php 
    esc_html_e( 'Back to Rules', 'stock-level-pricing' );
    ?></a>
		</h1>
		<!-- Parent container to hold both columns -->
		<div id="stock-level-rule-container">
		<form id="stock-level-rule-form" action="<?php 
    echo esc_url( admin_url( 'admin-post.php' ) );
    ?>" method="post" style="border: 1px solid #ccc; padding: 20px; border-radius: 5px;">
			<?php 
    wp_nonce_field( 'stocklvl_save_stock_level_rule', '_wpnonce' );
    ?>
			<input type="hidden" name="action" value="stocklvl_save_stock_level_rule">
			<!-- Title -->
			<div style="margin-bottom: 2px; margin-top: 2px;">
				<label for="stocklvl_rule_title" style="font-weight: bold; margin-bottom: 2px;"><?php 
    esc_html_e( 'Title:', 'stock-level-pricing' );
    ?></label>
				<input id="stocklvl_rule_title" type="text" name="stocklvl_rule_title" style="width: 100%; padding: 2px;">
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
        ?>"><?php 
        echo esc_html( $category->name );
        ?></option>
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
        ?>"><?php 
        echo esc_html( $product->post_title );
        ?></option>
				<?php 
    }
    ?>
			</select>
		</div>


			<!-- Pricing type -->
			<div style="margin-bottom: 15px;">
				<label style="font-weight: bold; margin-bottom: 3px;">Pricing type:</label><br>

				<!-- Fixed option is always available -->
				<label>
					<input type="radio" name="stocklvl_pricing-type" value="fixed"> Fixed
				</label>

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
				<button type="button" id="stocklvl_add_rule_button_percent" class="button" style="margin-top: 5px;">Add Rule</button>
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
					<tr>
					<td><input type="number" name="stock_level_fixed[]" placeholder="≤ items left" /></td>
						<td><input type="number" name="stocklvl_regular_price[]" value="" /></td>
						<td><input type="number" name="stocklvl_sale_price[]" value="" /></td>
						<td><button type="button" class="button remove-rule-button">Remove</button></td>
					</tr>
				</tbody>
			</table>
				<button type="button" id="stocklvl_add_rule_button_fixed" class="button" style="margin-top: 5px;">Add Rule</button>
		</div>

			<div style="display: flex; justify-content: space-between;">
				<input type="submit" value="Save Rule" class="button button-primary">
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

	// Adding event listeners for existing and future remove buttons.
	document.addEventListener('click', function (event) {
		if (event.target && event.target.classList.contains('remove-rule-button')) {
			event.target.closest('tr').remove();
		}
	});
});

	</script>

	<?php 
}
