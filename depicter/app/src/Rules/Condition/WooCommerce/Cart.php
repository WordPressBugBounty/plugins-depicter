<?php

namespace Depicter\Rules\Condition\WooCommerce;

use Averta\Core\Utility\Arr;
use Depicter\Rules\Condition\Base as ConditionBase;

class Cart extends ConditionBase
{
	/**
	 * @inheritdoc
	 */
	public $slug = 'WooCommerce_Cart';

	/**
	 * @inheritdoc
	 */
	public $control = 'dropdown';

	/**
	 * @inheritdoc
	 */
	protected $belongsTo = 'WooCommerce';

	/**
	 * Default value
	 *
	 * @var array|string
	 */
	protected $defaultValue = 'is_not_empty';

	/**
	 * @inheritdoc
	 */
	public function getLabel(): ?string{
		return __('Cart', 'depicter' );
	}

	/**
	 * @inheritDoc
	 */
	public function getControlOptions(){
		$options = parent::getControlOptions();

		return Arr::merge( $options, [ 'options' => [
			[
				'label' => __( 'Cart Is Empty', 'depicter' ),
				'value' => 'is_empty'
			],
			[
				'label' => __( 'Cart Is Not Empty', 'depicter' ),
				'value' => 'is_not_empty'
			],
		]]);
	}

	/**
	 * @inheritdoc
	 */
	public function check( $value = null ): bool{

		$isIncluded = false;

		$value = $value ?? $this->value[0] ?? $this->defaultValue;

		if ( $value == 'is_empty') {
			if ( WC()->cart->is_empty() ) {
				$isIncluded = true;
			}
		} else if ( $value == 'is_not_empty') {
			if ( ! WC()->cart->is_empty() )  {
				$isIncluded = true;
			}
		}

		return $this->selectionMode === 'include' ? $isIncluded : !$isIncluded;
	}
}
