<?php 
namespace Penthouse\AutomaticProductCategories;

defined('ABSPATH') || exit;

class RuleCondition {
	
	static $types;
	
	private $type;
	private $matchType;
	private $values;
	
	function __construct($type, $matchType, $values) {
		$types = self::getTypes();
		if (!isset($types[$type]) || !is_array($values) || !$values || count($values) > 2 || !is_array($values[0]) || (isset($values[1]) && !is_array($values[1]))) {
			throw new Exception();
		}
		
		$this->type = $type;
		$this->values = $values;
		
		switch ($types[$type]['type']) {
			case 'string':
			case 'secondary_string':
				$allowedMatchTypes = ['exact', 'exacti', 'notexact', 'notexacti', 'contains', 'containsi', 'notcontains', 'notcontainsi'];
				break;
			case 'number':
			case 'currency':
				$allowedMatchTypes = ['exact', 'notexact', '<', '<=', '>', '>=', 'between', 'notbetween'];
				break;
			default:
				$allowedMatchTypes = [''];
		}
		
		$matchType = wp_specialchars_decode($matchType);
		
		if (!in_array($matchType, $allowedMatchTypes)) {
			throw new \Exception();
		}
		
		$this->matchType = $matchType;
	}
	
	function toArray() {
		return [$this->type, $this->matchType, $this->values];
	}
	
	function getValue() {
		return isset($this->values[0]) ? $this->values[0] : null;
	}
	
	function isSatisfiedBy($product) {
		$types = self::getTypes();
		if (!isset($types[$this->type]['test'])) {
			return false;
		}
		return $types[$this->type]['test']($this, $product);
	}
	
	function stringValueMatch($testString, $matchType=null, $useValue2=false) {
		if ($matchType === null) {
			$matchType = $this->matchType;
		}
		
		if (!isset($this->values[$useValue2 ? 1 : 0])) {
			return false;
		}
		
		$testString = (string) $testString;
		$value = (string) ($useValue2 ? $this->values[1][0] : $this->values[0][0]);
		
		switch ($matchType) {
			case 'exact':
				return $testString === $value;
			case 'exacti':
				return strtolower($testString) === strtolower($value);
			case 'notexact':
				return $testString !== $value;
			case 'notexacti':
				return strtolower($testString) !== strtolower($value);
			case 'contains':
				return strpos($testString, $value) !== false;
			case 'containsi':
				return strpos(strtolower($testString), strtolower($value)) !== false;
			case 'notcontains':
				return strpos($testString, $value) === false;
			case 'notcontainsi':
				return strpos(strtolower($testString), strtolower($value)) === false;
		}
		
		return false;
	}
	
	function numberValueMatch($testNumber, $matchType=null, $useValue2=false) {
		if ($matchType === null) {
			$matchType = $this->matchType;
		}
		
		if (!isset($this->values[$useValue2 ? 1 : 0])) {
			return false;
		}
		
		$testNumber = (float) $testNumber;
		$value = (float) ($useValue2 ? $this->values[1][0] : $this->values[0][0]);
		
		switch ($matchType) {
			case 'exact':
				return $testNumber === $value;
			case 'notexact':
				return $testNumber !== $value;
			case '>':
				return $testNumber > $value;
			case '>=':
				return $testNumber >= $value;
			case '<':
				return $testNumber < $value;
			case '<=':
				return $testNumber <= $value;
			case 'between':
				return count($this->values) == 2 && $testNumber >= $value && $testNumber <= (float) $this->values[1];
			case 'notbetween':
				return count($this->values) == 2 && ($testNumber < $value || $testNumber > (float) $this->values[1]);
		}
		
		return false;
	}
	
	function listMatch($testValues, $useValue2=false) {
		if (!isset($this->values[$useValue2 ? 1 : 0])) {
			return false;
		}
		
		$value = ($useValue2 ? $this->values[1] : $this->values[0]);
		
		return (bool) array_intersect($value, (array) $testValues);
	}
	
	static function fromArray($arr) {
		require_once(__DIR__.'/RuleCondition.class.php');
		if (!is_array($arr) || count($arr) != 3 || !is_array($arr[2]) || count($arr[2]) > 2 || !is_array($arr[2][0]) || (isset($arr[2][1]) && !is_array($arr[2][1]))) {
			throw new Exception();
		}
		$value = [ array_map('sanitize_text_field', $arr[2][0]) ];
		if (count($arr[2]) == 2) {
			$value[] = array_map('sanitize_text_field', $arr[2][1]);
		}
		return new RuleCondition(
			sanitize_text_field($arr[0]),
			sanitize_text_field($arr[1]),
			$value
		);
	}
	
	static function getTypes() {
		if (!isset(self::$types)) {
			$attributes = wc_get_attribute_taxonomies();
			$attributes = array_column($attributes, null, 'attribute_id');
			
			self::$types = [
				'title' => [
					'title' => __('Product title', 'automatic-product-categories-for-woocommerce'),
					'type' => 'string',
					'test' => function($condition, $product) {
						return $condition->stringValueMatch($product->get_title());
					}
				],
				'description' => [
					'title' => __('Product description', 'automatic-product-categories-for-woocommerce'),
					'type' => 'string',
					'test' => function($condition, $product) {
						return $condition->stringValueMatch($product->get_description());
					}
				],
				'price' => [
					'title' => __('Product price', 'automatic-product-categories-for-woocommerce'),
					'type' => 'currency',
					'test' => function($condition, $product) {
						return $condition->numberValueMatch($product->get_price());
					}
				],
				'sales' => [
					'title' => __('Product total sales', 'automatic-product-categories-for-woocommerce'),
					'type' => 'currency',
					'test' => function($condition, $product) {
						return $condition->numberValueMatch($product->get_total_sales());
					}
				],
				'type' => [
					'title' => __('Product type', 'automatic-product-categories-for-woocommerce'),
					'type' => 'select',
					'options' => wc_get_product_types(),
					'test' => function($condition, $product) {
						return $condition->listMatch($product->get_type());
					}
				],
				'created_days' => [
					'title' => __('Days since product created', 'automatic-product-categories-for-woocommerce'),
					'type' => 'number',
					'test' => function($condition, $product) {
						$date = $product->get_date_created();
						return $date ? $condition->numberValueMatch((time() - $date->getTimestamp()) / 86400) : false;
					}
				],
				'modified_days' => [
					'title' => __('Days since product modified', 'automatic-product-categories-for-woocommerce'),
					'type' => 'number',
					'test' => function($condition, $product) {
						$date = $product->get_date_modified();
						return $date ? $condition->numberValueMatch((time() - $date->getTimestamp()) / 86400) : false;
					}
				],
				'stock_status' => [
					'title' => __('Product stock status', 'automatic-product-categories-for-woocommerce'),
					'type' => 'select',
					'options' => wc_get_product_stock_status_options(),
					'test' => function($condition, $product) {
						return $condition->listMatch($product->get_stock_status());
					}
				],
				'stock_quantity' => [
					'title' => __('Product stock quantity', 'automatic-product-categories-for-woocommerce'),
					'type' => 'number',
					'test' => function($condition, $product) {
						return $condition->numberValueMatch($product->get_stock_quantity());
					}
				],
				'on_sale' => [
					'title' => __('Product is on sale', 'automatic-product-categories-for-woocommerce'),
					'type' => 'select',
					'options' => [
						'yes' => __('Yes', 'automatic-product-categories-for-woocommerce'),
						'no' => __('No', 'automatic-product-categories-for-woocommerce')
					],
					'test' => function($condition, $product) {
						return $condition->stringValueMatch($product->is_on_sale() ? 'yes' : 'no', 'exact');
					}
				],
				'attribute' => [
					'title' => __('Product attribute value', 'automatic-product-categories-for-woocommerce'),
					'type' => 'secondary_select',
					'options' => array_map(function($attribute) {
							return [
								'title' => $attribute->attribute_label,
								'options' => get_terms(['taxonomy' => 'pa_'.$attribute->attribute_name, 'fields' => 'id=>name', 'hide_empty' => false])
							];
						}, $attributes),
					'test' => function($condition, $product) {
						$taxonomyName = wc_attribute_taxonomy_name_by_id((int) current($condition->getValue()));
						return $taxonomyName ? $condition->listMatch(wc_get_product_terms( $product->get_id(), $taxonomyName, ['fields' => 'ids'] ), true) : false;
					}
					
				],
				'meta' => [
					'title' => __('Product meta field', 'automatic-product-categories-for-woocommerce'),
					'type' => 'secondary_string',
					'options' => self::getProductMetaKeys(),
					'test' => function($condition, $product) {
						return $condition->stringValueMatch($product->get_meta(current($condition->getValue())), null, true);
					}
				],
				'category' => [
					'title' => __('Product category', 'automatic-product-categories-for-woocommerce'),
					'type' => 'string',
					'test' => function($condition, $product) {
						foreach ( wc_get_product_terms( $product->get_id(), 'product_cat', ['fields' => 'names'] ) as $categoryName ) {
							if ( $condition->stringValueMatch($categoryName) ) {
								return true;
							}
						}
						return false;
					}
				],
				'tag' => [
					'title' => __('Product tag', 'automatic-product-categories-for-woocommerce'),
					'type' => 'string',
					'test' => function($condition, $product) {
						foreach ( wc_get_product_terms( $product->get_id(), 'product_tag', ['fields' => 'names'] ) as $tagName ) {
							if ( $condition->stringValueMatch($tagName) ) {
								return true;
							}
						}
						return false;
					}
				],
			];
		}
		
		return array_map(function($type) {
			return array_diff_key($type, ['test']);
		}, self::$types);
	}
	
	private static function getProductMetaKeys() {
		global $wpdb;
		$result = $wpdb->get_col('SELECT DISTINCT meta_key FROM '.$wpdb->postmeta.' JOIN '.$wpdb->posts.' ON (ID=post_id) WHERE post_type="product" ORDER BY meta_key ASC');
		return array_combine($result, $result);
	}
	
}