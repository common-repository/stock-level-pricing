<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly.
}
require_once plugin_dir_path( __FILE__ ) . '../includes/db-handler.php';
// Add stock level pricing fields for simple product and parent product.
function stocklvl_add_stock_level_pricing_fields() {
    global $post;
    // Return early if the product hasn't been published yet (covers both new and drafted products)
    if ( empty( $post->post_status ) || $post->post_status === 'auto-draft' || $post->post_status === 'draft' ) {
        return;
    }
    $product_id = ( isset( $post->ID ) ? intval( $post->ID ) : 0 );
    $rules = stocklvl_get_stock_level_pricing_rules( $product_id );
    $percent_rules = array();
    $fixed_rules = array();
    foreach ( $rules as $rule ) {
        if ( $rule['rule_type'] == 'percent' ) {
            $percent_rules[] = $rule;
        } else {
            $fixed_rules[] = $rule;
        }
    }
    $type = ( !empty( $percent_rules ) ? 'percent' : (( !empty( $fixed_rules ) ? 'fixed' : 'percent' )) );
    $type = sanitize_text_field( $type );
    ?>
	<div class="slp_options_group">
		<?php 
    wp_nonce_field( 'stock_level_nonce_action_simple', 'stock_level_nonce' );
    ?>
		<h3 style="padding-left: 10px;"><?php 
    esc_html_e( 'Adjust price with stock level changes', 'stock-level-pricing' );
    ?></h3>
		<p class="form-field">
			<label for="stocklvl_pricing_change_type"><?php 
    esc_html_e( 'Type:', 'stock-level-pricing' );
    ?></label>
			<select id="stocklvl_pricing_change_type" class="select short">
				<option value="fixed" <?php 
    selected( $type, 'fixed' );
    ?>><?php 
    esc_html_e( 'Fixed Price', 'stock-level-pricing' );
    ?></option>

				<?php 
    ?>
					<!-- Placeholder for non-premium users -->
					<option disabled><?php 
    esc_html_e( 'Percentage (Available in Premium)', 'stock-level-pricing' );
    ?></option>
				<?php 
    ?>
			</select>
			<button id="stocklvl_add_rule_button" class="button" style="margin-left: 10px;"><?php 
    esc_html_e( 'Add Rule', 'stock-level-pricing' );
    ?></button>
		</p>
		<textarea name="stock_level_data" id="stock_level_data" style="display:none;"></textarea>

<table id="stocklvl_pricing_rules_table_percent" class="widefat">
	<thead>
		<tr>
			<th><?php 
    esc_html_e( 'Stock Level', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Percentage Change', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Action', 'stock-level-pricing' );
    ?></th>
		</tr>
	</thead>
		<?php 
    ?>
</table>

<table id="stocklvl_pricing_rules_table_fixed" class="widefat">
	<thead>
		<tr>
			<th><?php 
    esc_html_e( 'Stock Level', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Regular Price', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Sale Price', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Action', 'stock-level-pricing' );
    ?></th>
		</tr>
	</thead>
	<tbody>
	<?php 
    foreach ( $fixed_rules as $rule ) {
        ?>
		<tr data-rule-id="<?php 
        echo esc_attr( $rule['rule_id'] );
        ?>">
			<td><input type="number" name="stock_level_fixed[]" value="<?php 
        echo esc_attr( $rule['stock_level'] );
        ?>" /></td>
			<td><input type="number" name="stocklvl_regular_price[]" value="<?php 
        echo esc_attr( $rule['regular_price'] );
        ?>" /></td>
			<td><input type="number" name="stocklvl_sale_price[]" value="<?php 
        echo esc_attr( $rule['sale_price'] );
        ?>" /></td>
			<td><button type="button" class="button stocklvl_remove-rule-button"><?php 
        esc_html_e( 'Remove', 'stock-level-pricing' );
        ?></button></td>
			<td><input type="hidden" name="rule_id[]" value="<?php 
        echo esc_attr( $rule['rule_id'] );
        ?>" /></td>

		</tr>
	<?php 
    }
    ?>
	</tbody>
</table>
		
		<button id="stocklvl_add_rule_button_below" class="button" style="display: none; margin: 5px;"><?php 
    esc_html_e( 'Add Rule', 'stock-level-pricing' );
    ?></button>
	</div>
	<?php 
}

// Add stock level pricing fields for variations.
function stocklvl_add_stock_level_pricing_fields_for_variations(  $loop, $variation_data, $variation  ) {
    // Get the parent product ID of the variation.
    $parent_product_id = ( isset( $variation->post_parent ) ? intval( $variation->post_parent ) : 0 );
    // Get and sanitize variation ID.
    $variation_id = ( isset( $variation->ID ) ? intval( $variation->ID ) : 0 );
    // Check if "Manage stock?" is enabled for the parent product.
    $manage_stock_parent = get_post_meta( $parent_product_id, '_manage_stock', true );
    // Check if "Manage stock?" is enabled for the variation.
    $manage_stock_variation = get_post_meta( $variation->ID, '_manage_stock', true );
    // If "Manage stock?" is not enabled for both the parent product and the variation, return early.
    if ( $manage_stock_parent !== 'yes' && $manage_stock_variation !== 'yes' ) {
        return;
    }
    // Get stock level pricing rules for the current variation.
    $rules = stocklvl_get_stock_level_pricing_rules( $variation_id, 'variation' );
    // Separate and sanitize rules.
    $percent_rules = array();
    $fixed_rules = array();
    foreach ( $rules as $rule ) {
        $rule = array_map( 'sanitize_text_field', $rule );
        if ( $rule['rule_type'] == 'percent' ) {
            $percent_rules[] = $rule;
        } else {
            $fixed_rules[] = $rule;
        }
    }
    // Determine the initial type to display (percent or fixed).
    $type = ( !empty( $percent_rules ) ? 'percent' : (( !empty( $fixed_rules ) ? 'fixed' : 'percent' )) );
    $type = sanitize_text_field( $type );
    // Adding data-loop attribute to the wrapper div.
    echo '<div class="woocommerce_variation" data-loop="' . esc_attr( $loop ) . '" data-num-rules="' . esc_attr( count( $rules ) ) . '" data-type="' . esc_attr( $type ) . '">';
    ?>
<div class="options_group stock-level-options-group">
	<?php 
    wp_nonce_field( 'stock_level_nonce_action', 'stock_level_nonce' );
    ?>
		<!-- Adding nonce for removing rule -->
		<input type="hidden" id="remove_rule_nonce" value="<?php 
    echo esc_attr( wp_create_nonce( 'remove_rule_nonce' ) );
    ?>" />
		<h4 class="stock-level-heading"><?php 
    esc_html_e( 'Adjust price with stock level changes for Variation', 'stock-level-pricing' );
    ?></h4>
	<p class="form-field stock-level-form-field">
		<label for="stocklvl_pricing_change_type_variation_<?php 
    echo esc_attr( $loop );
    ?>"><?php 
    esc_html_e( 'Type:', 'stock-level-pricing' );
    ?></label>
		<select id="stocklvl_pricing_change_type_variation_<?php 
    echo esc_attr( $loop );
    ?>" class="select short stock-level-select" style="margin-bottom: 2px;">
			<option value="fixed" <?php 
    echo ( $type == 'fixed' ? 'selected' : '' );
    ?>>Fixed Price</option>

			<?php 
    ?>
			<!-- Placeholder for non-premium users -->
			<option disabled><?php 
    esc_html_e( 'Percentage (Available in Premium)', 'stock-level-pricing' );
    ?></option>
			<?php 
    ?>

		</select>
			<button id="stocklvl_add_rule_button_variation_<?php 
    echo esc_attr( $loop );
    ?>" class="button add-rule-button"><?php 
    esc_html_e( 'Add Rule', 'stock-level-pricing' );
    ?></button>
	</p>
	<textarea name="stock_level_data_variation_<?php 
    echo esc_attr( $loop );
    ?>" id="stock_level_data_variation_<?php 
    echo esc_attr( $loop );
    ?>" class="hidden-textarea"></textarea>

<!-- Adding table for percent rules -->
<table id="stocklvl_pricing_rules_table_percent_variation_<?php 
    echo esc_attr( $loop );
    ?>" class="widefat" style="display:none; border: none; border-spacing: 0 10px;">
	<thead>
		<tr>
			<th><?php 
    esc_html_e( 'Stock Level', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Percentage Change', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Action', 'stock-level-pricing' );
    ?></th>
		</tr>
	</thead>
		<?php 
    ?>
</table>

<!-- Adding table for fixed rules -->
<table id="stocklvl_pricing_rules_table_fixed_variation_<?php 
    echo esc_attr( $loop );
    ?>" class="widefat" style="display:none; border: none; border-spacing: 0 10px;">
	<thead>
		<tr>
			<th><?php 
    esc_html_e( 'Stock Level', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Regular Price', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Sale Price', 'stock-level-pricing' );
    ?></th>
			<th><?php 
    esc_html_e( 'Action', 'stock-level-pricing' );
    ?></th>
		</tr>
	</thead>
	<tbody>
	<?php 
    foreach ( $fixed_rules as $rule ) {
        ?>
			<tr data-rule-id="<?php 
        echo esc_attr( $rule['rule_id'] );
        ?>" style="border-top: none;">
			<td><input type="number" name="stock_level_fixed_variation_<?php 
        echo esc_attr( $loop );
        ?>[]" value="<?php 
        echo esc_attr( $rule['stock_level'] );
        ?>" style="width: 10%;" disabled="disabled" /></td>
			<td><input type="number" name="stocklvl_regular_price_variation_<?php 
        echo esc_attr( $loop );
        ?>[]" value="<?php 
        echo esc_attr( $rule['regular_price'] );
        ?>" style="width: 10%;" disabled="disabled" /></td>
			<td><input type="number" name="stocklvl_sale_price_variation_<?php 
        echo esc_attr( $loop );
        ?>[]" value="<?php 
        echo esc_attr( $rule['sale_price'] );
        ?>" style="width: 10%;" disabled="disabled" /></td>
			<td style="text-align: left;"><button type="button" class="button stocklvl_remove-rule-button" data-rule-id="<?php 
        echo esc_attr( $rule['rule_id'] );
        ?>" style="margin-top: 5px;">Remove</button></td>
		</tr>
	<?php 
    }
    ?>
	</tbody>
</table>

<button id="stocklvl_add_rule_button_below_variation_<?php 
    echo esc_attr( $loop );
    ?>" class="button add-rule-button" style="display:none;">Add Rule</button>

</div>
	<?php 
    echo '</div>';
}

// Attach the functions to the appropriate WooCommerce hooks.
add_action(
    'woocommerce_product_after_variable_attributes',
    'stocklvl_add_stock_level_pricing_fields_for_variations',
    10,
    3
);
add_action( 'woocommerce_product_options_inventory_product_data', 'stocklvl_add_stock_level_pricing_fields' );