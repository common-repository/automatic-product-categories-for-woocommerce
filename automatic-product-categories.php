<?php
/*
Plugin Name: Automatic Product Categories for WooCommerce
Version: 1.0.6
Description: Automatically assign product categories to new and existing products based on rules you define.
License: GNU General Public License version 3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Requires Plugins: woocommerce
Text Domain: automatic-product-categories-for-woocommerce
*/

namespace Penthouse\AutomaticProductCategories;

class AutomaticProductCategories {
	const VERSION = '1.0.6', ADMIN_CAPABILITY = 'manage_woocommerce';

	private $rulesListTable, $newProductIds = [];
	private static $instance;

	public static function instance() {
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action('current_screen', [$this, 'onCurrentScreen']);
		add_action('admin_menu', [$this, 'onAdminMenu']);
		add_action('save_post_product', [$this, 'processNewProduct'], 10, 3);
		add_action('woocommerce_update_product', [$this, 'processExistingProduct'], 10, 2);
		add_action('penthouse_apc_daily_rules', [$this, 'processProductsDaily']);
	}
	
	public function onAdminMenu() {
		add_submenu_page('edit.php?post_type=product', __('Automatic Product Categories for WooCommerce', 'automatic-product-categories-for-woocommerce'), __('Automatic Categories', 'automatic-product-categories-for-woocommerce'), self::ADMIN_CAPABILITY, 'automatic-product-categories-for-woocommerce', [$this, 'adminPage']);
	}
	
	public function onCurrentScreen($screen) {
		if ($screen->id == 'product_page_automatic-product-categories-for-woocommerce' && current_user_can(self::ADMIN_CAPABILITY)) {
			wp_enqueue_style('php_auto_productcats_admin', plugins_url('assets/css/admin.css', __FILE__), null, self::VERSION);
			wp_enqueue_script('php_auto_productcats_admin', plugins_url('assets/js/admin.js', __FILE__), ['wp-i18n'], self::VERSION);
			wp_set_script_translations('php_auto_productcats_admin', 'automatic-product-categories-for-woocommerce');
			
			require_once(__DIR__.'/includes/Rule.class.php');
			require_once(__DIR__.'/includes/RuleCondition.class.php');
			wp_localize_script('php_auto_productcats_admin', 'php_auto_productcats', [
				'conditions' => RuleCondition::getTypes(),
				'terms' => Rule::getTerms()
			]);
			
			if (!empty($_POST['php_apc_update'])) {
				$this->processSubmittedRules();
			}
			require_once(__DIR__.'/includes/RulesListTable.class.php');
			$this->rulesListTable = new RulesListTable(['screen' => get_current_screen()]);
			$this->rulesListTable->prepare_items();
		}
	}
	
	public function adminPage() {
		echo('<form method="post">');
		$this->rulesListTable->display();
		echo('</form>');
	}
	
	public function getSavedRules($enabledMode=null) {
		require_once(__DIR__.'/includes/Rule.class.php');
		
		$ruleData = json_decode(get_option('automatic-product-categories-for-woocommerce', '{"rules": []}'), true);
		if (empty($ruleData['rules']) || !is_array($ruleData['rules'])) {
			return [];
		}
		
		$rules = array_map(function($rule) {
			return Rule::fromArray($rule);
		}, $ruleData['rules']);
		
		if ($enabledMode !== null) {
			$rules = array_filter($rules, function($rule) use ($enabledMode) {
				return $rule->isEnabled($enabledMode);
			});
		}
		
		return $rules;
	}
	
	public function processSubmittedRules() {
		check_admin_referer('bulk-php-apc-rules');
		
		require_once(__DIR__.'/includes/Rule.class.php');
		require_once(__DIR__.'/includes/RuleCondition.class.php');
		
		$rules = [];
		$rulesToRun = [];
		
		if (isset($_POST['php_apc'])) {
			foreach ($_POST['php_apc'] as $ruleId => $ruleData) {
				$rule = new Rule();
				
				if (!empty($ruleData['enable_new'])) {
					$rule->setIsEnabled(Rule::ENABLED_FOR_NEW_PRODUCTS);
				}
				
				if (!empty($ruleData['enable_existing'])) {
					$rule->setIsEnabled(Rule::ENABLED_FOR_EXISTING_PRODUCTS);
				}
				
				if (!empty($ruleData['enable_daily'])) {
					$rule->setIsEnabled(Rule::ENABLED_DAILY);
					$hasDailyRule = true;
				}
				
				if (!empty($ruleData['positive_only'])) {
					$rule->setIsPositiveOnly();
				}
				
				if (isset($ruleData['terms']) && is_array($ruleData['terms'])) {
					foreach ($ruleData['terms'] as $taxonomy => $termIds) {
						if (is_array($termIds)) {
							$taxonomy = sanitize_text_field($taxonomy);
							array_map( function($termId) use ($rule, $taxonomy) {
								try {
									$rule->addTerm($taxonomy, (int) $termId);
								} catch (Exception $ex) { /* skip on failure */ }
							}, $termIds );
						}
					}
				}
				
				
				if (isset($ruleData['conditions']['condition']) && is_array($ruleData['conditions']['condition'])) {
					foreach ($ruleData['conditions']['condition'] as $index => $conditionType) {
						if (isset($ruleData['conditions']['match'][$index]) && isset($ruleData['conditions']['value'][$index]) && is_array($ruleData['conditions']['value'][$index])) {
							$values = [ array_map('sanitize_text_field', $ruleData['conditions']['value'][$index]) ];
							if (!empty($ruleData['conditions']['value2'][$index]) && is_array($ruleData['conditions']['value2'][$index])) {
								$values[] = array_map('sanitize_text_field', $ruleData['conditions']['value2'][$index]);
							}
							try {
								$rule->addCondition(
									new RuleCondition(
										sanitize_text_field($conditionType),
										sanitize_text_field($ruleData['conditions']['match'][$index]),
										$values
									)
								);
							} catch (Exception $ex) { /* skip on failure */ }
						}
					}
				}
				
				$rules[(int) $ruleId] = $rule;
				
				if (!empty($_POST['run']) && !empty($ruleData['select'])) {
					$rulesToRun[] = $rule;
				}
			}
		}
		
		if ($rules) {
		
			$ruleIds = array_keys($rules);
			if (min($ruleIds) < 0) {
				$maxId = max(0, max($ruleIds));
				foreach ($rules as $ruleId => $rule) {
					if ($ruleId < 0) {
						unset($rules[$ruleId]);
						$rules[$maxId - $ruleId] = $rule;
					}
				}
			}
			
			ksort($rules);
			
			$json = wp_json_encode(
				['rules' => array_map(function($rule) {
					return $rule->toArray();
				}, array_values($rules))]
			);
			
			update_option('automatic-product-categories-for-woocommerce', $json, false);
			
			if ($rulesToRun) {
				array_map(
					function($product) use ($rulesToRun) {
						$this->processProduct($product, $rulesToRun);
					},
					wc_get_products([
						'status' => 'publish',
						'limit' => -1,
						'orderby' => 'none'
					])
				);
			}
		
		} else {
			delete_option('automatic-product-categories-for-woocommerce');
		}
		
		$nextDailyEventTime = wp_next_scheduled('penthouse_apc_daily_rules');
		if (empty($hasDailyRule)) {
			if ($nextDailyEventTime) {
				wp_unschedule_event($nextDailyEventTime, 'penthouse_apc_daily_rules');
			}
		} else if (!$nextDailyEventTime) {
			wp_schedule_event( time() + 60, 'daily', 'penthouse_apc_daily_rules' );
		}
	}
	
	private function processProduct($product, $rules) {
		require_once(__DIR__.'/includes/Rule.class.php');
		
		if (!is_object($product)) {
			$product = wc_get_product($product);
		}
		
		foreach ($rules as $rule) {
			$rule->applyTo($product);
		}
	}
	
	public function processNewProduct($productId, $product=null, $isExisting=true) {
		if (!$isExisting) {
			require_once(__DIR__.'/includes/Rule.class.php');
			
			if ($product === null || !is_a($product, 'WC_Product')) {
				$product = is_object($productId) ? $productId : wc_get_product($productId);
			}
			
			$this->processProduct($product, $this->getSavedRules(Rule::ENABLED_FOR_NEW_PRODUCTS));
			$this->newProductIds[] = $product->get_id();
		}
	}
	
	public function processExistingProduct($productId, $product=null) {
		require_once(__DIR__.'/includes/Rule.class.php');
		
		if ($product === null) {
			$product = is_object($productId) ? $productId : wc_get_product($productId);
		}
		
		if (!in_array($product->get_id(), $this->newProductIds)) {
			$this->processProduct($product, $this->getSavedRules(Rule::ENABLED_FOR_EXISTING_PRODUCTS));
		}
	}
	
	public function processProductsDaily() {
		require_once(__DIR__.'/includes/Rule.class.php');
		
		$products = wc_get_products([
			'status' => 'publish',
			'limit' => -1,
			'orderby' => 'none'
		]);
		
		if ($products) {
			foreach ($products as $product) {
				$this->processProduct($product, $this->getSavedRules(Rule::ENABLED_DAILY));
			}
		}
	}
}

AutomaticProductCategories::instance();