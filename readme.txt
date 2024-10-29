=== Automatic Product Categories for WooCommerce ===
Contributors: penthouseplugins
Tags: ecommerce, product categories
Requires at least: 6.0
Tested up to: 6.6.1
Requires PHP: 7.0
Stable tag: 1.0.6
License: GNU General Public License version 3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Automatically assign product categories to new and existing products based on rules you define.

== Description ==

This plugins adds an "Automatic Categories" item to the Products menu in the WordPress admin, which allows the user to set up rules for automatic product category assignment. Rules can be applied:

- Automatically to new and/or existing products when created or saved, respectively
- Manually on all existing products from the Products > Automatic Categories screen.

== Frequently Asked Questions ==

== Installation ==

This plugin can be installed under Plugins > Add New Plugin, either by searching for the plugin title or by downloading the plugin zip file and uploading it via the Upload Plugin feature.

== Changelog ==

=== 1.0.6 ===

* Show "and" operator between multiple rule conditions
* Declare text domain
* Declare plugin dependency

=== 1.0.5 ===

* Add rule condition: Product total sales
* Add rule condition: Days since product created
* Add rule condition: Days since product modified
* Add rule condition: Product category
* Add the ability to automatically run rules daily on publicly published products
* Add case insensitive match types for "Product meta field" condition
* Improve UI layout on smaller monitors
* Fix: Certain comparison settings (for example, "less than") result in an exception when saving
* Fix: PHP fatal error related to exception
* Fix: Certain match settings on numeric conditions result in an exception when saving rules
* Fix: Exception when saving rule including a "Product price" condition
* Fix: JavaScript error related to "Product meta field" condition
* Fix: Product attribute value condition not working correctly
* 

=== 1.0.4 ===

* When removing product categories due to an enabled rule not matching, add the default (Uncategorized) category if the product has no other categories
* Don't show the default (Uncategorized) category in the "Categories/Tags to Add/Remove" column
* Label tweak

=== 1.0.3 ===

* Added case-insensitive string matching options
* Added option not to remove categories/tags for non-matching rules
* Added product tag rule condition
* Clarified button label

=== 1.0.2 ===

* Add header comments to JS and CSS files

=== 1.0.1 ===

* Add an additional permissions check to admin functionality

=== 1.0.0 ===

* Initial release