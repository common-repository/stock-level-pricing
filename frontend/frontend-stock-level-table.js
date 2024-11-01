// JS that handles tables for variations
jQuery( document ).ready(
	function ($) {
		$( '.variations_form' ).on(
			'found_variation',
			function (event, variation) {
				var variation_id = variation.variation_id;
				if (variation_id) {
					$.ajax(
						{
							url: slp_params.ajax_url,
							type: 'POST',
							data: {
								action: 'get_variation_rules_and_display',
								variation_id: variation_id,
								nonce: slp_params.nonce // Include nonce
							},
							success: function (response) {
								$( '.stock-level-pricing-wrapper' ).remove();
								$( '.woocommerce-variation-price' ).after( response );
							}
						}
					);
				}
			}
		);
	}
);
