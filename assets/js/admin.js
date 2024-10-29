/*!
This file is part of Automatic Product Categories for WooCommerce. For copyright and licensing information, please see ../../license/license.txt
*/

jQuery(document).ready(function($) {
	$('.php-apc-add-rule').on('click', function() {
		$('.php-apc-rules tbody > tr.no-items:first').hide();
		var $newRow = $('.php-apc-rules tbody > tr:not(.no-items):first').clone().removeClass('php-apc-template');
		$newRow.find(':checkbox').prop('checked', false);
		$newRow.find('.column-conditions, .column-terms').empty();
		var newRuleId = -1;
		$('.php-apc-rules input[data-rule-id]').each( function() {
			if ($(this).attr('data-rule-id') <= newRuleId) {
				newRuleId = $(this).attr('data-rule-id') - 1;
			}
		} );
		$newRow.find('input[data-rule-id]:first').attr('data-rule-id', newRuleId);
		$newRow.find('[name^="php_apc["]').each(function() {
			var fieldName = $(this).attr('name');
			$(this).attr('name', 'php_apc[' + newRuleId + ']' + fieldName.substring(fieldName.indexOf(']') + 1));
		});
		$newRow.appendTo('.php-apc-rules');
		initRow($newRow);
	});
	
	$('.php-apc-rules')
		.on('click', '.php-apc-remove-rule', function() {
			$(this).closest('tr').remove();
		})
		.on('click', '.php-apc-add-condition', function() {
			var $this = $(this);
			var $conditionSelectDiv = $this.prev('div').clone();
			var $conditionSelect = $conditionSelectDiv.find('.php-apc-condition-select:first');
			var fieldName = $conditionSelect.attr('name');
			var conditionId = parseInt(fieldName.substring(fieldName.lastIndexOf('[') + 1, fieldName.length - 1)) + 1;
			$conditionSelect.attr('name', fieldName.substring(0, fieldName.lastIndexOf('[')) + '[' + conditionId + ']');
			$conditionSelectDiv.insertBefore($this);
			$conditionSelect.val( $conditionSelect.children().first().val() );
			$conditionSelect.trigger('change');
		})
		.on('click', '.php-apc-remove-condition', function() {
			$(this).parent().remove();
		});
	
	
	function initRow($row) {
		var ruleId = $row.find('input[data-rule-id]:first').attr('data-rule-id');
		
		var selectedConditions = $row.find('.column-conditions [data-json]').remove().attr('data-json');
		selectedConditions = selectedConditions && selectedConditions.length ? JSON.parse(selectedConditions) : [ ['title', 'exact', ''] ];
		
		for (var i = 0; i < selectedConditions.length; ++i) {
			var $conditionSelect = $('<select>').addClass('php-apc-condition-select').attr('name', 'php_apc[' + ruleId + '][conditions][condition][' + i + ']').append(
				Object.entries(window.php_auto_productcats.conditions).map( function(condition) {
					return $('<option>').attr('value', condition[0]).text(condition[1].title);
				} )
			);
			$row.find('.column-conditions').append(
				$('<div>').append(
					$('<span>').text( wp.i18n.__('and', 'automatic-product-categories-for-woocommerce') ),
					$conditionSelect,
					$('<button>').attr('type', 'button').addClass('button button-secondary php-apc-remove-condition').text( wp.i18n.__('Remove', 'automatic-product-categories-for-woocommerce') )
				)
			);
			
			$conditionSelect.val(selectedConditions[i][0]).trigger('change');
			$conditionSelect.siblings('select[name$="[match][' + i + ']"]:first').val(selectedConditions[i][1]);
			$conditionSelect.siblings(':input[name$="[value][' + i + '][]"]:first').val(selectedConditions[i][2][0]);
			if (selectedConditions[i][2].length > 1) {
				$conditionSelect.siblings(':input[name$="[value2][' + i + '][]"]:first').val(selectedConditions[i][2][1]);
			}
		}
		
		$row.find('.column-conditions').append(
			$('<button>').attr('type', 'button').addClass('button button-primary php-apc-add-condition').text( wp.i18n.__('Add Condition', 'automatic-product-categories-for-woocommerce') )
		);
		
		var selectedTerms = $row.find('.column-terms [data-json]').remove().attr('data-json');
		selectedTerms = selectedTerms ? JSON.parse(selectedTerms) : {};
		
		$row.find('.column-terms').append(
			Object.entries(window.php_auto_productcats.terms).filter( function(terms) {
				return Object.values(terms[1].terms).length;
			} ).map( function(terms) {
				var $termsSelect = $('<select>').addClass('php-apc-condition-select').attr({
						name: 'php_apc[' + ruleId + '][terms][' + terms[0]  + '][]',
						multiple: true
					}).append(
						Object.entries(terms[1].terms).map( function(term) {
							return $('<option>').attr('value', term[0]).text(term[1]);
						} )
					);
				
				if (selectedTerms[terms[0]] && selectedTerms[terms[0]].length) {
					$termsSelect.val(selectedTerms[terms[0]]);
				}
				return $('<label>').text( terms[1].title ).append($termsSelect);
			} )
		);
		
	}
	
	
	$(document.body).on('change', '.php-apc-rules .php-apc-condition-select', function() {
		var $this = $(this);
		var fieldName = $this.attr('name');
		ruleId = fieldName.substring(8, fieldName.indexOf(']'));
		conditionId = fieldName.substring(fieldName.lastIndexOf('[') + 1, fieldName.length - 1);
		
		var condition = window.php_auto_productcats.conditions[$this.val()];
		$this.siblings(':not(button,span:first-child)').remove();
		
		var subControls = [];
		
		if (condition) {
			switch (condition.type) {
				case 'string':
					// translators: %s = match type (example: "is exactly")
					var caseInsensitiveLabel = wp.i18n.__('%s (case-insensitive)', 'automatic-product-categories-for-woocommerce');
					subControls.push(
						$('<select>').attr('name', 'php_apc[' + ruleId + '][conditions][match][' + conditionId + ']').append(
							[
								['exact', wp.i18n.__('is exactly', 'automatic-product-categories-for-woocommerce')],
								['exacti', caseInsensitiveLabel.replace('%s', wp.i18n.__('is exactly', 'automatic-product-categories-for-woocommerce'))],
								['notexact', wp.i18n.__('is not exactly', 'automatic-product-categories-for-woocommerce')],
								['notexacti', caseInsensitiveLabel.replace('%s', wp.i18n.__('is not exactly', 'automatic-product-categories-for-woocommerce'))],
								['contains', wp.i18n.__('contains', 'automatic-product-categories-for-woocommerce')],
								['containsi', caseInsensitiveLabel.replace('%s', wp.i18n.__('contains', 'automatic-product-categories-for-woocommerce'))],
								['notcontains', wp.i18n.__('does not contain', 'automatic-product-categories-for-woocommerce')],
								['notcontainsi', caseInsensitiveLabel.replace('%s', wp.i18n.__('does not contain', 'automatic-product-categories-for-woocommerce'))],
							].map( function(option) {
								return $('<option>').attr('value', option[0]).text(option[1]);
							} )
						),
						$('<input>').attr({
							type: 'text',
							name: 'php_apc[' + ruleId + '][conditions][value][' + conditionId + '][]'
						}),
						$('<input>').attr({
							type: 'hidden',
							name: 'php_apc[' + ruleId + '][conditions][value2][' + conditionId + '][]'
						})
					);
					break;
				case 'number':
				case 'currency':
					subControls.push(
						$('<select>').attr('name', 'php_apc[' + ruleId + '][conditions][match][' + conditionId + ']').append(
							[
								['exact', wp.i18n.__('is exactly', 'automatic-product-categories-for-woocommerce')],
								['notexact', wp.i18n.__('is not exactly', 'automatic-product-categories-for-woocommerce')],
								['>', wp.i18n.__('greater than', 'automatic-product-categories-for-woocommerce')],
								['>=', wp.i18n.__('greater than or equal to', 'automatic-product-categories-for-woocommerce')],
								['<', wp.i18n.__('less than', 'automatic-product-categories-for-woocommerce')],
								['<=', wp.i18n.__('less than or equal to', 'automatic-product-categories-for-woocommerce')],
								['between', wp.i18n.__('between', 'automatic-product-categories-for-woocommerce')],
								['notbetween', wp.i18n.__('not between', 'automatic-product-categories-for-woocommerce')],
							].map( function(option) {
								return $('<option>').attr('value', option[0]).text(option[1]);
							} )
						),
						$('<input>').attr({
							type: 'text',
							name: 'php_apc[' + ruleId + '][conditions][value][' + conditionId + '][]'
						}),
						$('<input>').attr({
							type: 'text',
							name: 'php_apc[' + ruleId + '][conditions][value2][' + conditionId + '][]'
						}).addClass('hidden')
					);
					break;
				case 'select':
					var entries = Object.entries(condition.options);
					subControls.push(
						$('<select>').attr({
								name: 'php_apc[' + ruleId + '][conditions][value][' + conditionId + '][]',
								multiple: entries.length > 2
						}).append(
							entries.map( function(option) {
								return $('<option>').attr('value', option[0]).text(option[1]);
							} )
						),
						$('<input>').attr({
							type: 'hidden',
							name: 'php_apc[' + ruleId + '][conditions][value2][' + conditionId + '][]'
						}),
						$('<input>').attr({
							type: 'hidden',
							name: 'php_apc[' + ruleId + '][conditions][match][' + conditionId + ']'
						})
					);
					break;
				case 'secondary_select':
					subControls.push(
						$('<select>').attr({
								name: 'php_apc[' + ruleId + '][conditions][value][' + conditionId + '][]'
						}).append(
							Object.entries(condition.options).map( function(option) {
								return $('<option>').attr('value', option[0]).text(option[1].title);
							} )
						).addClass('php-apc-secondary-select'),
						$('<input>').attr({
							type: 'hidden',
							name: 'php_apc[' + ruleId + '][conditions][match][' + conditionId + ']'
						})
					);
					break;
				case 'secondary_string':
					var caseInsensitiveLabel = wp.i18n.__('%s (case-insensitive)', 'automatic-product-categories-for-woocommerce');
					subControls.push(
						$('<select>').attr('name', 'php_apc[' + ruleId + '][conditions][value][' + conditionId + '][]').append(
							Object.entries(condition.options).map( function(option) {
								return $('<option>').attr('value', option[0]).text(option[1]);
							} )
						).addClass('php-apc-secondary-string-select'),
						$('<select>').attr('name', 'php_apc[' + ruleId + '][conditions][match][' + conditionId + ']').append(
							[
								['exact', wp.i18n.__('is exactly', 'automatic-product-categories-for-woocommerce')],
								['exacti', caseInsensitiveLabel.replace('%s', wp.i18n.__('is exactly', 'automatic-product-categories-for-woocommerce'))],
								['notexact', wp.i18n.__('is not exactly', 'automatic-product-categories-for-woocommerce')],
								['notexacti', caseInsensitiveLabel.replace('%s', wp.i18n.__('is not exactly', 'automatic-product-categories-for-woocommerce'))],
								['contains', wp.i18n.__('contains', 'automatic-product-categories-for-woocommerce')],
								['containsi', caseInsensitiveLabel.replace('%s', wp.i18n.__('contains', 'automatic-product-categories-for-woocommerce'))],
								['notcontains', wp.i18n.__('does not contain', 'automatic-product-categories-for-woocommerce')],
								['notcontainsi', caseInsensitiveLabel.replace('%s', wp.i18n.__('does not contain', 'automatic-product-categories-for-woocommerce'))],
							].map( function(option) {
								return $('<option>').attr('value', option[0]).text(option[1]);
							} )
						),
						$('<input>').attr({
							type: 'text',
							name: 'php_apc[' + ruleId + '][conditions][value2][' + conditionId + '][]'
						})
					);
					break;
				
			}
			
			$this.after(subControls);
			$this.siblings('.php-apc-secondary-select').trigger('change');
		}
	});
	
	
	$(document.body).on('change', '.php-apc-rules .php-apc-secondary-select', function() {
		var $this = $(this);
		var fieldName = $this.attr('name');
		ruleId = fieldName.substring(8, fieldName.indexOf(']'));
		conditionId = fieldName.substring(fieldName.lastIndexOf('[', fieldName.length - 3) + 1, fieldName.length - 3);
		
		$this.next('select').remove();
		var subCondition = window.php_auto_productcats.conditions[$this.siblings('select:first').val()].options[$this.val()];
		
		$('<select>').attr({
			name: 'php_apc[' + ruleId + '][conditions][value2][' + conditionId + '][]',
			multiple: true
		}).append(
			Object.entries(subCondition.options).map( function(option) {
				return $('<option>').attr('value', option[0]).text(option[1]);
			} )
		).insertAfter($this);
	});
	
	$('.php-apc-rules tbody tr:not(.php-apc-template)').each(function() {
		initRow($(this));
	});
});