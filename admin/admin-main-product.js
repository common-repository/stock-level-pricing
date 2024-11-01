jQuery( document ).ready(
	function ($) {
		var product_id = $( '#post_ID' ).val();

		// Set the step attribute for existing percentage_change, regular_price, and sale_price fields.
		$( 'input[name="percentage_change[]"], input[name="regular_price[]"], input[name="sale_price[]"]' ).attr( 'step', '0.01' );

		// Function to toggle fields based on the selected type
		function stocklvl_toggleFields() {
			var type = $( '#stocklvl_pricing_change_type' ).val();

			// Always hide both tables initially
			$( '#stocklvl_pricing_rules_table_percent, #stocklvl_pricing_rules_table_fixed' ).hide();
			$( '#stocklvl_add_rule_button' ).show(); // Show the Add Rule button
			$( '#stocklvl_add_rule_button_below' ).hide(); // Hide the Add Rule button below the table

			if (type === 'percent' && $( '#stocklvl_pricing_rules_table_percent tbody tr' ).length > 0) {
				$( '#stocklvl_pricing_rules_table_percent' ).show();
				$( '#stocklvl_add_rule_button' ).hide();
				$( '#stocklvl_add_rule_button_below' ).show();
			} else if (type === 'fixed' && $( '#stocklvl_pricing_rules_table_fixed tbody tr' ).length > 0) {
				$( '#stocklvl_pricing_rules_table_fixed' ).show();
				$( '#stocklvl_add_rule_button' ).hide();
				$( '#stocklvl_add_rule_button_below' ).show();
			}
		}
		// Function to add a new rule row
		function stocklvl_addRule(type, defaultStockLevel, ruleId = '', hidestocklvl_AddRuleButtonBelow = false) {
			var tableBody = type === 'percent' ? $('#stocklvl_pricing_rules_table_percent tbody') : $('#stocklvl_pricing_rules_table_fixed tbody');
			var stockLevel = defaultStockLevel ? defaultStockLevel : '';
			var newRow = `<tr data-rule-id="${ruleId}">
						<td><input type="number" name="stock_level_${type}[]" value="${stockLevel}" min="1" placeholder="≤ items left" /></td>`;
		
			if (type === 'percent') {
				newRow += `<td><input type="number" name="stocklvl_percentage_change[]" step="0.01"></td>`;
			} else {
				newRow += `<td><input type="number" name="stocklvl_regular_price[]" step="0.01"></td>
						   <td><input type="number" name="stocklvl_sale_price[]" step="0.01"></td>`;
			}
		
			newRow += `<td><button type="button" class="button stocklvl_remove-rule-button">Remove</button></td></tr>`;
			
			tableBody.append(newRow);
		
			if (hidestocklvl_AddRuleButtonBelow) {
				$('#stocklvl_add_rule_button_below').hide();
			} else {
				$('#stocklvl_add_rule_button_below').show();
			}
		}
		

		$( document ).on(
			'change',
			'input[name="stock_level"], input[name="stock_level_percent[]"], input[name="stock_level_fixed[]"], input[name="stocklvl_percentage_change"], input[name="stocklvl_percentage_change[]"], input[name="stocklvl_regular_price"], input[name="stocklvl_regular_price[]"], input[name="stocklvl_sale_price"], input[name="stocklvl_sale_price[]"]',
			function () {
				var type       = $( this ).closest( 'table' ).attr( 'id' ).split( '_' ).pop();
				var stockLevel = $( this ).val();
				var inputName  = $( this ).attr( 'name' );
				if (inputName === "stock_level" || inputName === "stock_level_percent[]" || inputName === "stock_level_fixed[]") {
					if ( ! isStockLevelUnique( type, stockLevel, this )) {
						alert( 'Stock Level must be unique. Please enter a different value.' );
						$( this ).val( '' );
					}
				}
			}
		);

		// Event listener for the "Remove" button inside the table rows
		$( document ).on(
			'click',
			'.stocklvl_remove-rule-button',
			function (e) {
				e.preventDefault();
				$( this ).closest( 'tr' ).remove();
			}
		);

		// Event listener for the "Add Rule" button (first click)
		$( '#stocklvl_add_rule_button' ).click(
			function (e) {
				e.preventDefault();
				var type = $( '#stocklvl_pricing_change_type' ).val();
				stocklvl_addRule( type, true, true ); // Adding the first row with default value and hiding the 'add_rule_button_below'
				$( this ).hide();
				$( '#stocklvl_add_rule_button_below' ).show();

				// Show the corresponding table
				if (type === 'percent') {
					$( '#stocklvl_pricing_rules_table_percent' ).show();
				} else if (type === 'fixed') {
					$( '#stocklvl_pricing_rules_table_fixed' ).show();
				}
			}
		);

		// Event Listener for the Second "Add Rule" Button
		$( '#stocklvl_add_rule_button_below' ).click(
			function (e) {
				e.preventDefault();
				var type = $( '#stocklvl_pricing_change_type' ).val();
				stocklvl_addRule( type, false, false );
				$( '#stocklvl_add_rule_button_below' ).show();
			}
		);

		// Event listener for type selection
		$( 'body' ).on(
			'change',
			'select[id^="stocklvl_pricing_change_type"]',
			function () {
				var selectId = $( this ).attr( 'id' );
				if (selectId === "stocklvl_pricing_change_type") {
					stocklvl_toggleFields();
				}
			}
		);

		// Initial call to toggle fields based on the default selection
		stocklvl_toggleFields( stock_level_pricing_data.initial_type );

		// Function to prepare data for a given entity (parent product)
		function stocklvl_prepareDataForEntity(tableElem, dataArr) {
			const type = tableElem.attr( 'id' ).split( '_' ).pop().replace( /_\d+$/, '' );

			let stockLevelInputName = type === 'percent' ? 'stock_level_percent[]' : 'stock_level_fixed[]';

			tableElem.find( 'tbody tr' ).each(
				function () {
					let stockValue = $( this ).find( 'input[name="' + stockLevelInputName + '"]' ).val() || $( this ).find( 'input[name="stock_level"]' ).val();

					let rule = {
						type: type,
						stock_level: stockValue,
						stocklvl_percentage_change: $( this ).find( 'input[name^="stocklvl_percentage_change"]' ).val() || '',
						stocklvl_regular_price: $( this ).find( 'input[name^="stocklvl_regular_price"]' ).val() || '',
						stocklvl_sale_price: $( this ).find( 'input[name^="stocklvl_sale_price"]' ).val() || ''
					};

					// Only add the respective fields based on the rule type
					if (type === 'percent') {
						delete rule.regular_price;
						delete rule.sale_price;
					} else {
						delete rule.percentage_change;
					}

					let rule_id = $( this ).data( 'rule-id' ); // Capture the rule_id from the data attribute
					if (rule_id) {
						rule.rule_id = rule_id;
					}

					dataArr.push( rule );
				}
			);
		}

		// Function to prepare data for submission
		function stocklvl_prepareDataForSubmission() {
			let data = [];

			// Capture rules for both percent and fixed tables
			['percent', 'fixed'].forEach(
				function (type) {
					const tableElem = $( '#stocklvl_pricing_rules_table_' + type );
					stocklvl_prepareDataForEntity( tableElem, data );
				}
			);

			// Check if any stock_level is missing
			data.forEach(
				rule => {
                if ( ! rule.stock_level) {
                }
				}
			);

			// Update the hidden textarea for the parent product
			$( '#stock_level_data' ).val( JSON.stringify( data ) );
		}

		// Function that checks if entered stock level value is unique
		function stocklvl_isStockLevelUnique(type, stockLevel, excludeInput) {
			var tableBody           = type === 'percent' ? $( '#stocklvl_pricing_rules_table_percent tbody' ) : $( '#stocklvl_pricing_rules_table_fixed tbody' );
			var isUnique            = true;
			var stockLevelInputName = type === 'percent' ? 'stock_level_percent[]' : 'stock_level_fixed[]';

			tableBody.find( 'input[name="' + stockLevelInputName + '"]' ).each(
				function () {
					if (this !== excludeInput && $( this ).val() == stockLevel) {
						isUnique = false;
						return false;
					}
				}
			);

			return isUnique;
		}

		// Event listener for form submission
		$( 'form#post' ).submit(
			function (e) {
				stocklvl_prepareDataForSubmission();
			}
		);
	}
);
