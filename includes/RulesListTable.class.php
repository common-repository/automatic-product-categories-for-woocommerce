<?php
namespace Penthouse\AutomaticProductCategories;

defined('ABSPATH') || exit;

// based on wp-admin\includes\class-wp-links-list-table.php

/**
 * List Table API: WP_Links_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

class RulesListTable extends \WP_List_Table {
	
	public function __construct( $args = array() ) {
		parent::__construct(
			array(
				'plural' => 'php-apc-rules',
				'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
			)
		);
	}

	public function prepare_items() {
		$this->items = AutomaticProductCategories::instance()->getSavedRules();
	}

	public function no_items() {
		esc_html_e( 'No rules found.', 'automatic-product-categories-for-woocommerce' );
		echo('</td></tr>');
		echo('<tr class="php-apc-template">');
		$this->single_row_columns(new Rule());
		echo('</tr>');
		echo('<tr class="hidden"><td colspan="'.((int) count($this->get_columns())).'">');
	}

	protected function extra_tablenav( $which ) {
		if ($which == 'top') {
		?>
			<input type="hidden" name="php_apc_update" value="1">
		<?php
		}
		?>
		<div class="alignleft actions">
			<button class="button button-primary">
				<?php esc_html_e('Save', 'automatic-product-categories-for-woocommerce' ); ?>
			</button>
			<button class="button button-secondary" name="run" value="1">
				<?php esc_html_e('Save All & Run Selected', 'automatic-product-categories-for-woocommerce' ); ?>
			</button>
			<button class="button button-secondary php-apc-add-rule" type="button">
				<?php esc_html_e('Add New Rule', 'automatic-product-categories-for-woocommerce' ); ?>
			</button>
		</div>
		<?php
	}
	
	public function display() {
		parent::display();
		?>
		<h2 class="clear"><?php esc_html_e('Documentation Notes', 'automatic-product-categories-for-woocommerce'); ?></h2>
		<p>
			<strong><?php esc_html_e('Days since product created', 'automatic-product-categories-for-woocommerce'); ?>, <?php esc_html_e('Days since product modified', 'automatic-product-categories-for-woocommerce'); ?>:</strong>
			<?php esc_html_e('These conditions are based on the date_created and date_modified product fields, respectively. These fields may not always reflect the actual creation/modification date, for example if they are edited by the user. Evaluation of these conditions includes fractional days (at seconds resolution). For example, if 3 days and 6 hours have elapsed since product creation, this is evaluated as 3.25 days. A rule with value "greater than 3" would be applied, while a rule with value "equal to 3" would not.', 'automatic-product-categories-for-woocommerce'); ?>
		</p>
		<p>
			<strong><?php esc_html_e('Save All & Run Selected', 'automatic-product-categories-for-woocommerce'); ?>:</strong>
			<?php esc_html_e('Runs the selected rules on products with the "publish" status.', 'automatic-product-categories-for-woocommerce'); ?>
		</p>
		<?php
	}
	
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'enable'       => esc_html__( 'Enabled?', 'automatic-product-categories-for-woocommerce' ),
			'conditions'        => esc_html__( 'Conditions', 'automatic-product-categories-for-woocommerce' ),
			'terms' => esc_html__( 'Categories/Tags to Add/Remove', 'automatic-product-categories-for-woocommerce' ),
			'remove' => ''
		);
	}

	protected function get_sortable_columns() {
		return [];
	}

	protected function get_default_primary_column_name() {
		return 'enable';
	}

	public function column_cb( $rule ) {
		$ruleId = $rule->getId();
		?>
		<label>
			<input type="checkbox" name="php_apc[<?php echo((int) $ruleId); ?>][select]" data-rule-id="<?php echo((int) $ruleId); ?>" value="1" />
			<span class="screen-reader-text"><?php esc_html_e( 'Select rule', 'automatic-product-categories-for-woocommerce' ); ?></span>
		</label>
		<?php
	}
	
	public function column_enable( $rule ) {
		$ruleId = $rule->getId();
		?>
		<label>
			<input type="checkbox" name="php_apc[<?php echo((int) $ruleId); ?>][enable_new]" value="1"<?php checked($rule->isEnabled(Rule::ENABLED_FOR_NEW_PRODUCTS)); ?>>
			<?php esc_html_e('For new products', 'automatic-product-categories-for-woocommerce'); ?>
		</label>
		<label>
			<input type="checkbox" name="php_apc[<?php echo((int) $ruleId); ?>][enable_existing]" value="1"<?php checked($rule->isEnabled(Rule::ENABLED_FOR_EXISTING_PRODUCTS)); ?>>
			<?php esc_html_e('For existing products (when updated)', 'automatic-product-categories-for-woocommerce'); ?>
		</label>
		<label>
			<input type="checkbox" name="php_apc[<?php echo((int) $ruleId); ?>][enable_daily]" value="1"<?php checked($rule->isEnabled(Rule::ENABLED_DAILY)); ?>>
			<?php esc_html_e('For publicly published products (daily schedule) - BETA', 'automatic-product-categories-for-woocommerce'); ?>
		</label>
		<label>
			<input type="checkbox" name="php_apc[<?php echo((int) $ruleId); ?>][positive_only]" value="1"<?php checked($rule->isPositiveOnly()); ?>>
			<?php esc_html_e('Don\'t remove categories/tags if rule doesn\'t match', 'automatic-product-categories-for-woocommerce'); ?>
		</label>
		<?php
	}

	public function column_conditions( $rule ) {
		if ($rule) {
			echo('<span data-json="'.esc_attr(wp_json_encode($rule->toArray()['conditions'])).'"></span>');
		}
	}
	
	public function column_terms( $rule ) {
		if ($rule) {
			echo('<span data-json="'.esc_attr(wp_json_encode($rule->toArray()['terms'])).'"></span>');
		}
	}
	
	public function column_remove( $rule ) {
		?>
		<button type="button" class="button button-secondary php-apc-remove-rule"><?php esc_html_e( 'Remove', 'automatic-product-categories-for-woocommerce' ); ?></button>
		<?php
	}
	
}
