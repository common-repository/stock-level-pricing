=== Stock Level Pricing ===
Contributors: krandrew, freemius
Tags: woocommerce, inventory management, dynamic pricing, stock level price, woo stock
Requires at least: 4.9
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create pricing based on current Inventory status, give discounts or increase price depending on how many pieces of product left in stock

== Description ==
Stock Level Pricing is an easy-to-use plugin that adjusts WooCommerce product prices based on the quantity of products left in the stock. It's designed to work with products where "Stock management" is enabled, offering effective pricing strategies for better inventory control and sales. The plugin's nuanced pricing adjustments based on stock levels mean you can dynamically control your product pricing, turning stock management into a key aspect of your sales strategy.

## Use Cases
1. **Increase Price When Stock is Low**: Perfect for high-demand products with limited stock. This feature helps store owners take advantage of low availability by raising prices as stock decreases, aiming to increase profits.
2. **Discounts to Sell Leftovers**: Activates discounts automatically when stock is low, encouraging customers to buy the remaining items. This is great for moving inventory quickly and avoiding excess stock.
3. **Volume Discounts for Excess Stock**: When there's a lot of stock left, the plugin can reduce prices to boost sales and lower inventory levels. For example, you can adjust prices when only a few items remain in stock, making this ideal for managing large inventories and attracting customers looking for deals.

## Features
- **Compatibility**: Works smoothly with simple products, variable products, and subscriptions in WooCommerce.
- **Pricing Types**:
  - *Flat Price*: Users can set a regular and sale price that becomes active when inventory reaches specified levels.
  - *Percentage Discount*: Automatically applies a percentage discount to the current product price based on inventory changes *[premium feature]*.
- **Increase or Lower the Price**:
  - *Price Increase for Low Stock*: Raises prices as stock gets lower, using scarcity to make products seem more valuable *[premium feature]*.
  - *Discounts for Lowering Stock Levels*: Encourages buying by lowering prices as more stock is available *[premium feature]*.
- **Global Stock Level Pricing Rules**: Apply pricing rules across multiple products or categories based on their stock levels.
- **Rule Priorities**: Adheres to a hierarchy of variation rules, followed by parent product rules, and then global rules to ensure consistent pricing.
- **Customizable Stock Level Pricing Table**: Display the table with stock level rules on the product page, with the current stock level rule highlighted for customer clarity.
- **Sale Price Display Option**: Can show the price set by stock level rules as a sale price to draw attention and promote sales.

This plugin is a key tool for WooCommerce users who want to adjust their pricing based on how much stock they have, improving both sales and inventory management. 

Find more information on how to use plugin in the documentation here: 
**[Stock Level Pricing Documentation](https://stock-level-pricing.notion.site/Stock-Level-Pricing-Documentation-7399ba7841c24288b2587aab7356f786)**

== Installation ==
1. Upload the plugin files to the \'/wp-content/plugins/stock-level-pricing\' directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the \'Plugins\' screen in WordPress
3. Use the WooCommerce > Settings > Products > Stock Level Pricing to configure the plugin
4. To design global stock-level pricing rules rules go to WooCommerce > Stock Level Pricing
5. To create pricing rules separately for each product, go to the product page > Product data > Inventory and find the "Adjust price with stock level changes" section.


== Frequently Asked Questions ==
= Can I set different pricing rules for different categories or individual products? =
Answer: Yes, the plugin allows you to set global stock level pricing rules that can be applied across multiple products or categories. Additionally, you have the flexibility to create specific rules for individual products or variations. This means you can tailor your pricing strategy to match the sales approach for each product or category, whether it\'s a flat price adjustment or a percentage discount based on stock levels.

= What happens if there are conflicting pricing rules for a product? =
Answer: In cases where there might be conflicting rules (such as a variation-specific rule and a global rule), Stock Level Pricing follows a set hierarchy. Variation-specific rules take the highest priority, followed by parent product rules, and then global rules. In case you have two or more global pricing rules for one product - the one created first will have higher priority. 

= How does the plugin manage price increases or decreases by percentage based on stock levels? =
Answer: The Stock Level Pricing plugin offers a flexible feature that allows you to either increase or decrease prices by a specified percentage as part of the stock level rule. This means you can set rules to automatically raise prices by a certain percentage when stock levels are low, taking advantage of scarcity.


== Screenshots ==
1. Simple stock pricing rules setup
2. Customize pricing table and other settings
3. Global stock level pricing rule - Fixed Prices 
4. Global stock level pricing rule - Percentage discount
5. How to set stock level pricing rules on product level 
6. Stock level pricing rule displayed on product page
7. Stock level rules for variations
8. Pricing rules change on variation selection
9. Global stock level pricing rules table

== Changelog ==

2024-06-09 - version 1.0.3
* Fix: Bulk edit actions interception
* Fix: Saving product without stock level pricing rules
* Added: Plugin documentation link
* Update: WP+Woo latest version support
* Update: Freemius version

2024-03-18 - version 1.0.2
* Fix: Woo Product Addons Compatibility + zeroed prices in cart
* Fix: Global pricing rules affected products without stock level
* Update: WP latest version support

2024-03-08 - version 1.0.1
* Fix: Error when Woo isn't active
* Fix: Error on orders/subscriptions pages
* Update: Direction of stock level ranges calculation

2024-02-20 - version 1.0.0
* Initial release