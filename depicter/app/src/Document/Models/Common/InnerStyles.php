<?php
namespace Depicter\Document\Models\Common;

class InnerStyles
{
	/**
	 * @var Styles|null
	 */
	public $items;

	/**
	 * @var array
	 */
	public $dynamicStyleProperties = [];

    public function __set(string $name, $value) {
        $this->dynamicStyleProperties[ $name ] = $value;
    }

    public function __get(string $name) {
        return $this->dynamicStyleProperties[ $name ] ?? null;
    }

    public function __isset(string $name) {
        return isset( $this->dynamicStyleProperties[ $name ] );
    }
}
