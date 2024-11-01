jQuery( document ).ready(
	function ($) {
		var product_id = $( '#post_ID' ).val();

		// UI part of JS file:

		// Set the step attribute for existing percentage_change, regular_price, and sale_price fields
		$( 'input[name="stocklvl_percentage_change[]"], input[name="stocklvl_regular_price[]"], input[name="stocklvl_sale_price[]"]' ).attr( 'step', '0.01' );

		// Function to toggle fields for variations
		function stocklvl_toggleFieldsForVariation(variationIndex) {
			var type         = $( '#stocklvl_pricing_change_type_variation_' + variationIndex ).val();  // Get the selected type for the variation
			var percentTable = $( '#stocklvl_pricing_rules_table_percent_variation_' + variationIndex );
			var fixedTable   = $( '#stocklvl_pricing_rules_table_fixed_variation_' + variationIndex );

			// Get the number of existing rules for this variation from the data-num-rules attribute
			var numRules = $( 'div[data-loop="' + variationIndex + '"]' ).data( 'num-rules' );

			// Hide both tables initially
			percentTable.hide();
			fixedTable.hide();

			if (numRules > 0) {
				// There are existing rules, so show the table for the type that has rules
				$( '#stocklvl_pricing_rules_table_' + type + '_variation_' + variationIndex ).show();
			} else {
				// Show the table corresponding to the selected type, if it has rows
				if (type === 'percent' && percentTable.find( 'tbody tr' ).length > 0) {
					percentTable.show();
				} else if (type === 'fixed' && fixedTable.find( 'tbody tr' ).length > 0) {
					fixedTable.show();
				}
			}
		}

		// Function to add a new rule row for variations
		function stocklvl_addRuleForVariation(type, defaultStockLevel, variationIndex) {
			if ( ! type || ! variationIndex) {
				return;
			}
			// Hide the initial "Add Rule" button
			$( '#stocklvl_add_rule_button_variation_' + variationIndex ).hide();

			// Show the "Add Rule" button below the table
			$( '#stocklvl_add_rule_button_below_variation_' + variationIndex ).show();
			var tableBody  = type === 'percent' ? $( '#stocklvl_pricing_rules_table_percent_variation_' + variationIndex + ' tbody' ) : $( '#stocklvl_pricing_rules_table_fixed_variation_' + variationIndex + ' tbody' );
			var stockLevel = '';
			var newRow = '<tr><td><input type="number" style="width:10%;" name="stock_level_variation_' + variationIndex + '[]" value="' + stockLevel + '" min="1" placeholder="≤ value"></td>';

			if (type === 'percent') {
				newRow += '<td><input type="number" style="width:10%;" name="stocklvl_percentage_change_variation_' + variationIndex + '[]" step="0.01"></td>';
			} else {
				newRow += '<td><input type="number" style="width:10%;" name="stocklvl_regular_price_variation_' + variationIndex + '[]" step="0.01"></td><td><input type="number" style="width:10%;" name="stocklvl_sale_price_variation_' + variationIndex + '[]" step="0.01"></td>';
			}

			newRow += '<td><button type="button" class="button stocklvl_remove-rule-button" style="margin-top: 5px;">Remove</button></td></tr>';

			tableBody.append( newRow );

			// Show the table and its header
			$( '#stocklvl_pricing_rules_table_' + type + '_variation_' + variationIndex ).show();
			$( '#stocklvl_pricing_rules_table_' + type + '_variation_' + variationIndex + ' thead' ).show();
		}

		// Event listener for the "Add Rule" button below the table for each variation
		$( document ).on(
			'click',
			'[id^=stocklvl_add_rule_button_below_variation_]',
			function (e) {
				e.preventDefault();
				var variationIndex = $( this ).attr( 'id' ).split( '_' ).pop();
				var type           = $( '#stocklvl_pricing_change_type_variation_' + variationIndex ).val();
				stocklvl_addRuleForVariation( type, false, variationIndex );  // Adding subsequent rows without default value
			}
		);

		// Event listener for dynamically created "Add Rule" buttons
		$( 'body' ).on(
			'click',
			'button[id^="stocklvl_add_rule_button"], button[id^="stocklvl_add_rule_button_variation_"]',
			function (e) {

				e.preventDefault();
				var btnId          = $( this ).attr( 'id' );
				var match          = btnId.match( /stocklvl_add_rule_button(?:_variation_)?(\d+)?/ );
				var variationIndex = match ? match[1] : '';
				var type           = $( '#stocklvl_pricing_change_type_variation_' + variationIndex ).val();

				if (variationIndex) {
					stocklvl_addRuleForVariation( type, true, variationIndex );
					$( '#stocklvl_pricing_rules_table_' + type + '_' + variationIndex ).show();
					$( '#stocklvl_add_rule_button_' + variationIndex ).hide();
					$( '#add_rule_button_below_' + variationIndex ).show();
				}
			}
		);

		// Function to initialize all variations
		function stocklvl_initializeAllVariations() {
			$( '.woocommerce_variation' ).each(
				function (index, elem) {
					const variationIndex = $( elem ).data( 'loop' );
					stocklvl_toggleFieldsForVariation( variationIndex );
				}
			);
		}

		// Call the function when the document is ready
		$( document ).ready(
			function () {
				stocklvl_initializeAllVariations();
			}
		);

		// Call the function after WooCommerce AJAX events (if any)
		$( document ).ajaxComplete(
			function () {
				stocklvl_initializeAllVariations();
			}
		);

		// Event listener for type selection for variations
		$( 'body' ).on(
			'change',
			'select[id^="pricing_change_type_variation_"]',
			function () {
				var selectId       = $( this ).attr( 'id' );
				var match          = selectId.match( /pricing_change_type_variation_(\d+)/ );
				var variationIndex = match ? match[1] : '';
				stocklvl_toggleFieldsForVariation( variationIndex );
			}
		);

		var ajaxInProgress = false;

		// Saving part of JS file:

		// Function to remove a rule
		function stocklvl_removeRule(event) {
			var button = $( event.currentTarget );
			var ruleId = button.data( 'rule-id' );
			var row    = button.closest( 'tr' );

			$.ajax(
				{
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'remove_rule',
						rule_id: ruleId,
						security: $( '#remove_rule_nonce' ).val()
					},
					success: function (response) {
						if (response.success) {
							row.remove();
						} else {
						}
					},
					error: function (xhr, textStatus, error) {
					}
				}
			);
		}

		// Attach event listener to remove-rule-button
		$( document ).on( 'click', '.stocklvl_remove-rule-button', stocklvl_removeRule );

		// Function to prepare data for submission for variations
		function stocklvl_prepareVariationDataForSubmission() {
			if (ajaxInProgress) {
				return;
			}

			ajaxInProgress = true;

			let data      = [];
			let productId = $( '#post_ID' ).val();

			if ( ! productId) {
				console.log( "Error: Product ID is missing." );
				return;
			}

			$( '.woocommerce_variation' ).each(
				function () {
					const variationId    = $( this ).find( '.variable_post_id' ).val();
					const variationIndex = $( this ).index();

					if ( ! variationId) {
						return true; // Continue to the next iteration
					}

					let rules    = [];
					let ruleType = $( this ).find( '#stocklvl_pricing_change_type_variation_' + variationIndex ).val();

					$(this).find(`tbody input[name^="stock_level_variation_${variationIndex}"]`).each(
						function (i) {
							let stockLevel = $( this ).val();

							if (ruleType === 'percent') {
								let percentageChange = $( this ).closest( 'tr' ).find( `input[name = "stocklvl_percentage_change_variation_${variationIndex}[]"]` ).val();
								if (stockLevel && percentageChange) {
									rules.push(
										{
											rule_type: ruleType,
											stock_level: stockLevel,
											stocklvl_percentage_change: percentageChange,
											product_id: productId,
											variation_id: variationId,
											variation_index: variationIndex
										}
									);
								}
							} else {
								let regularPrice = $( this ).closest( 'tr' ).find( `input[name = "stocklvl_regular_price_variation_${variationIndex}[]"]` ).val();
								let salePrice    = $( this ).closest( 'tr' ).find( `input[name = "stocklvl_sale_price_variation_${variationIndex}[]"]` ).val();
								if (stockLevel && (regularPrice || salePrice)) {
									rules.push(
										{
											rule_type: ruleType,
											stock_level: stockLevel,
											stocklvl_regular_price: regularPrice,
											stocklvl_sale_price: salePrice,
											product_id: productId,
											variation_id: variationId,
											variation_index: variationIndex
										}
									);
								}
							}
						}
					);

					if (rules.length > 0) {
						data.push( { variation_id: variationId, rules: rules } );
					} else {
						console.error( `No rules captured for variation ID ${variationId} at variation index ${variationIndex}.` );
					}
				}
			);

			// AJAX request to save data
			$.ajax(
				{
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'stocklvl_save_stock_level_pricing_rules',
						rules: JSON.stringify( data ),
						stock_level_nonce: $( '#stock_level_nonce' ).val()
					},
					error: function (jqXHR, textStatus, errorThrown) {
						alert( 'There was an error saving the data. Please try again.' );
					},
					complete: function () {
						ajaxInProgress = false;
					}
				}
			);
		}

		// When the main product (post) is updated
		$( 'form#post' ).one(
			'submit',
			function (e) {
				e.preventDefault();
				stocklvl_prepareVariationDataForSubmission();
				e.currentTarget.submit();
			}
		);

		// When the 'Save changes' button for variations is clicked
		$( '.save-variation-changes' ).click(
			function (e) {
				e.preventDefault();
				stocklvl_prepareVariationDataForSubmission();
			}
		);
	}
);

jQuery(document).ready(function($) {
    // Function to toggle stock level pricing fields based on manage stock and product status.
    function toggleStockLevelPricingFields() {
        var manageStockChecked = $('#_manage_stock').is(':checked');
        var isNewProduct = $('.slp_options_group').hasClass('new-product');

        // Show the fields if manage stock is checked and it's not a new product.
        if (manageStockChecked && !isNewProduct) {
            $('.slp_options_group').show();
        } else {
            $('.slp_options_group').hide();
        }
    }

    // Call the toggle function initially and on manage stock checkbox changes.
    toggleStockLevelPricingFields();
    $('#_manage_stock').change(toggleStockLevelPricingFields);
});

