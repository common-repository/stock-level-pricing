jQuery( document ).ready(
	function ($) {
		// Function to toggle the visibility of dependent fields.
		function stocklvl_toggleDependentFields() {
			var showPricingTable = $( '#woocommerce_slp_show_pricing_table' ).is( ':checked' );
			$( '.slp-dependent-field' ).closest( 'tr' ).toggle( showPricingTable );
		}

		// Toggle fields on document ready.
		stocklvl_toggleDependentFields();

		// Toggle fields on change of the "Show pricing table" checkbox.
		$( '#woocommerce_slp_show_pricing_table' ).change(
			function () {
				stocklvl_toggleDependentFields();
			}
		);
	}
);


jQuery( document ).ready(
	function ($) {
		// Disable all dropdowns with the 'non-premium-dropdown' class.
		$( 'select.non-premium-dropdown' ).prop( 'disabled', true );
	}
);