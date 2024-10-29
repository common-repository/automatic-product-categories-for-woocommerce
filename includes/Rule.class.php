<?php 
namespace Penthouse\AutomaticProductCategories;

defined('ABSPATH') || exit;

class Rule {
	const ENABLED_FOR_NEW_PRODUCTS = 1;
	const ENABLED_FOR_EXISTING_PRODUCTS = 2;
	const ENABLED_DAILY = 3;
	
	private $isEnabledForNew = false;
	private $isEnabledForExisting = false;
	private $isEnabledDaily = false;
	private $positiveOnly = false;
	private $conditions = [];
	private $terms = [];
	private $id = 0;
	
	private static $lastId = 0;
	
	function __construct() {
		$this->id = ++self::$lastId;
	}
	
	function isSatisfiedBy($product) {
		foreach ($this->conditions as $condition) {
			if (!$condition->isSatisfiedBy($product)) {
				return false;
			}
		}
		
		return true;
	}
	
	function applyTo($product) {
		if ($this->isSatisfiedBy($product)) {
			foreach ($this->terms as $taxonomy => $termIds) {
				if ($termIds) {
					wp_set_object_terms($product->get_id(), $termIds, $taxonomy, true);
					if ($taxonomy == 'product_cat') {
						wp_remove_object_terms($product->get_id(), (int) get_option('default_product_cat', 0), $taxonomy);
					}
				}
			}
		} else if (!$this->positiveOnly) {
			foreach ($this->terms as $taxonomy => $termIds) {
				if ($termIds) {
					wp_remove_object_terms($product->get_id(), $termIds, $taxonomy);
					if ($taxonomy == 'product_cat') {
						$assignedCategories = wp_get_object_terms($product->get_id(), 'product_cat', ['orderby' => 'none', 'fields' => 'ids']);
						if (is_array($assignedCategories) && empty($assignedCategories)) {
							$defaultCategory = (int) get_option('default_product_cat', 0);
							if ($defaultCategory) {
								wp_set_object_terms($product->get_id(), $defaultCategory, 'product_cat', true);
							}
						}
					}
				}
			}
		}
	}
	
	function toArray() {
		return [
			'enable_new' => (int) $this->isEnabledForNew,
			'enable_existing' => (int) $this->isEnabledForExisting,
			'enable_daily' => (int) $this->isEnabledDaily,
			'positive_only' => (int) $this->positiveOnly,
			'conditions' => array_map(function($condition) {
				return $condition->toArray();
			}, $this->conditions),
			'terms' => $this->terms
		];
	}
	
	static function fromArray($arr) {
		if (!is_array($arr)) {
			throw new Exception();
		}
		$rule = new Rule();
		
		if (!empty($arr['enable_new'])) {
			$rule->setIsEnabled(self::ENABLED_FOR_NEW_PRODUCTS);
		}
		
		if (!empty($arr['enable_existing'])) {
			$rule->setIsEnabled(self::ENABLED_FOR_EXISTING_PRODUCTS);
		}
		
		if (!empty($arr['enable_daily'])) {
			$rule->setIsEnabled(self::ENABLED_DAILY);
		}
		
		if (!empty($arr['positive_only'])) {
			$rule->setIsPositiveOnly();
		}
		
		if (isset($arr['conditions'])) {
			require_once(__DIR__.'/RuleCondition.class.php');
			
			if (!is_array($arr['conditions'])) {
				throw new Exception();
			}
			
			array_map(function($conditionData) use ($rule) {
				$rule->addCondition( RuleCondition::fromArray($conditionData) );
			}, $arr['conditions']);
		}
		
		
		if (isset($arr['terms'])) {
			if (!is_array($arr['terms'])) {
				throw new Exception();
			}
			
			foreach ($arr['terms'] as $taxonomy => $terms) {
				$taxonomy = sanitize_text_field($taxonomy);
				array_map(function($termId) use ($rule, $taxonomy) {
					$rule->addTerm( $taxonomy, (int) $termId );
				}, $terms);
			}
		}
		
		return $rule;
	}
	
	function getId() {
		return $this->id;
	}
	
	function setIsEnabled($mode, $enabled=true) {
		switch ($mode) {
			case self::ENABLED_FOR_NEW_PRODUCTS:
				$this->isEnabledForNew = (bool) $enabled;
				break;
			case self::ENABLED_FOR_EXISTING_PRODUCTS:
				$this->isEnabledForExisting = (bool) $enabled;
				break;
			case self::ENABLED_DAILY:
				$this->isEnabledDaily = (bool) $enabled;
				break;
		}
	}
	
	function isEnabled($mode) {
		switch ($mode) {
			case self::ENABLED_FOR_NEW_PRODUCTS:
				return $this->isEnabledForNew;
			case self::ENABLED_FOR_EXISTING_PRODUCTS:
				return $this->isEnabledForExisting;
			case self::ENABLED_DAILY:
				return $this->isEnabledDaily;
		}
	}
	
	function setIsPositiveOnly($positiveOnly=true) {
		$this->positiveOnly = true;
	}
	
	function isPositiveOnly() {
		return $this->positiveOnly;
	}
	
	function getSupportedTermTaxonomies() {
		return ['product_cat', 'product_tag'];
	}
	
	function addTerm($taxonomy, $termId) {
		if (!in_array($taxonomy, $this->getSupportedTermTaxonomies()) || !term_exists((int) $termId, $taxonomy)) {
			throw new Exception();
		}
		
		if (!isset($this->terms[$taxonomy])) {
			$this->terms[$taxonomy] = [];
		}
		
		if (!in_array($termId, $this->terms[$taxonomy])) {
			$this->terms[$taxonomy][] = $termId;
		}
	}
	
	function addCondition(RuleCondition $condition) {
		$this->conditions[] = $condition;
	}
	
	
	static function getTerms() {
		return [
			'product_cat' => [
				'title' => __('Categories', 'automatic-product-categories-for-woocommerce'),
				'terms' => get_terms(['taxonomy' => 'product_cat', 'fields' => 'id=>name', 'hide_empty' => false, 'exclude' => [(int) get_option('default_product_cat', 0)]])
			],
			'product_tag' => [
				'title' => __('Tags', 'automatic-product-categories-for-woocommerce'),
				'terms' => get_terms(['taxonomy' => 'product_tag', 'fields' => 'id=>name', 'hide_empty' => false])
			]
		];
	}
		
}