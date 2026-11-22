<?php
namespace Depicter\Utility;

use \Averta\WordPress\Utility\JSON;
use \Averta\WordPress\Utility\Sanitize as SanitizeBase;

class Sanitize extends SanitizeBase {

	/**
	 * Tags that text elements are allowed to render with
	 */
	const TEXT_TAGS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span' ];

	/**
	 * Element types which render their `tag` option as the html tag name
	 */
	const TEXT_ELEMENT_TYPES = [ 'text', 'woosalebadge', 'woostockstatus' ];

	public static function html( $input, $allowed_tags = null, $namespace = null, $auto_p = false ){
		// A fix to allow empty data url for src in image tag
		if( $namespace === 'depicter/output' ){
			add_filter( 'wp_kses_uri_attributes', [ __CLASS__, 'enforceSvgUriAttributes' ], 20 );
			add_filter( 'wp_kses_uri_attributes', [ __CLASS__, 'skipSrcEscapeTemporary' ], 25 );
			add_filter( 'safe_style_css', [ __CLASS__, 'modifyAllowedCssAttributes' ]);
			add_filter( 'safecss_filter_attr_allow_css', [ __CLASS__, 'checkAllowedCssValue' ], 10, 2);
		}
		$sanitized = parent::html( $input, $allowed_tags, $namespace, $auto_p );
		if( $namespace === 'depicter/output' ){
			remove_filter( 'wp_kses_uri_attributes', [ __CLASS__, 'enforceSvgUriAttributes' ], 20 );
			remove_filter( 'wp_kses_uri_attributes', [ __CLASS__, 'skipSrcEscapeTemporary' ], 25 );
			remove_filter( 'safe_style_css', [ __CLASS__, 'modifyAllowedCssAttributes' ]);
			remove_filter( 'safecss_filter_attr_allow_css', [ __CLASS__, 'checkAllowedCssValue' ], 10, 2);
		}

		return $sanitized;
	}

	/**
     * Retrieves default WordPress HTML tags
     *
     * @return array
     */
	protected static function defaultAllowedTags(){
		$tags = parent::defaultAllowedTags();

		$tags['style'] = [
			'type'  => true
		];
		$tags['script'] = [
			'id'    => true,
			'src'   => true
		];
		$tags['link'] = [
			'rel'   => true,
			'id'    => true,
			'href'  => true,
			'media' => true,
		];
		return $tags;
	}

	/**
	 * Treat SMIL/SVG value attributes as URI attributes so wp_kses strips javascript: protocols.
	 *
	 * @param array $uriAttributes
	 *
	 * @return array
	 */
	public static function enforceSvgUriAttributes( $uriAttributes ) {
		return array_values( array_unique( array_merge( (array) $uriAttributes, [
			'to',
			'from',
			'values',
			'by',
			'xlink:href',
		] ) ) );
	}

	/**
	 * Ignore src escaping because `wp_kses` strips `data:` from image placeholder source in PHP 8.0+
	 *
	 * @param array $uriAttributes
	 *
	 * @return array $uriAttributes
	 */
	public static function skipSrcEscapeTemporary( $uriAttributes ) {
	    if ( ( $key = array_search( 'src', $uriAttributes ) ) !== false) {
			unset( $uriAttributes[ $key ] );
		}
		return $uriAttributes;
	}

	/**
	 * Modify allowed css attributes
	 *
	 * @param $properties
	 *
	 * @return mixed
	 */
	public static function modifyAllowedCssAttributes( $properties ) {
		$properties = array_merge( $properties, [
			'fill',
			'opacity',
			'stroke',
			'stroke-width',
			'stroke-opacity',
			'fill-opacity',
			'stop-color',
			'transform',
			'fill-rule',
			'clip-rule',
			'stroke-linejoin',
			'stroke-miterlimit'
		]);

		return $properties;
	}

	/**
	 * Check for allowed css values
	 *
	 * @param $allowed
	 * @param $css_test_string
	 *
	 * @return bool
	 */
	public static function checkAllowedCssValue( $allowed, $css_test_string ): bool{

		$allowedCssValues = [
			'rotate',
			'scale',
			'evenodd',
			'round'
		];

		foreach( $allowedCssValues as $value ) {
			if ( ! $allowed && str_contains( $css_test_string, $value ) ) {
				return true;
			}
		}
		return $allowed;
	}

	/**
	 * Sanitize data for editor input. This will recursively sanitize all strings in the data structure.
	 * 
	 * @param mixed $data The data to sanitize.
	 * @return mixed The sanitized data.
	 */
	public static function editorData($data) {
		if (is_object($data)) {
			foreach ($data as $key => $value) {
				$data->{$key} = self::editorData($value);
			}

			return $data;
		}

		if (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[$key] = self::editorData($value);
			}

			return $data;
		}

		if (is_string($data)) {
			return wp_kses_post($data);
		}

		return $data;
	}

	/**
	 * Retrieves a safe tag name for text elements, falls back to default if the tag is not allowed
	 *
	 * @param mixed  $tag
	 * @param string $default
	 *
	 * @return string
	 */
	public static function textTag( $tag, $default = 'p' ) {
		$tag = is_string( $tag ) ? strtolower( trim( $tag ) ) : '';

		return in_array( $tag, self::TEXT_TAGS, true ) ? $tag : $default;
	}

	/**
	 * Whether unfiltered html is disallowed on the site. Used on render where there is no author to check.
	 *
	 * @return bool
	 */
	public static function isUnfilteredHtmlDisallowed(): bool {
		return defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML;
	}

	/**
	 * Sanitizes the document markup without allowing script tags.
	 * The init script of document should be appended after this sanitization.
	 *
	 * @param string $markup
	 *
	 * @return string
	 */
	public static function documentMarkup( $markup ) {
		$tags = static::defaultAllowedTags();
		unset( $tags['script'] );

		return self::html( (string) $markup, $tags, 'depicter/output' );
	}

	/**
	 * Sanitizes editor json of a user who is not allowed to use unfiltered html.
	 *
	 * @param string      $editorJson          Editor data to save
	 * @param string|null $previousEditorJson  Current saved editor data, used to keep existing custom scripts
	 *
	 * @return string|false  Sanitized json or false on failure
	 */
	public static function editorJson( $editorJson, $previousEditorJson = null ) {
		$editor = JSON::decode( $editorJson );
		if ( ! is_object( $editor ) && ! is_array( $editor ) ) {
			return false;
		}

		$previous = ! empty( $previousEditorJson ) && JSON::isJson( $previousEditorJson ) ? JSON::decode( $previousEditorJson ) : null;

		$editor = self::editorDocument( self::editorData( $editor ), $previous );

		return JSON::encode( $editor );
	}

	/**
	 * Sanitizes the structure of editor data.
	 * Restricts the tag of text elements and prevents adding or changing custom javascript (callbacks and customJS actions).
	 *
	 * @param object|array      $editor    Decoded editor data
	 * @param object|array|null $previous  Decoded current editor data
	 *
	 * @return object|array
	 */
	public static function editorDocument( $editor, $previous = null ) {
		$document         = self::prop( $editor, 'document' ) ?? $editor;
		$previousDocument = self::prop( $previous, 'document' ) ?? $previous;

		// Keep the callbacks which are already saved, ignore the submitted ones
		$options = self::prop( $document, 'options' );
		if ( is_object( $options ) && property_exists( $options, 'callbacks' ) ) {
			$options->callbacks = self::prop( self::prop( $previousDocument, 'options' ), 'callbacks' ) ?? [];
		}

		$elements = self::prop( $document, 'elements' );
		if ( ! is_object( $elements ) && ! is_array( $elements ) ) {
			return $editor;
		}

		$previousElements = self::prop( $previousDocument, 'elements' );

		foreach ( $elements as $elementId => $element ) {
			if ( ! is_object( $element ) ) {
				continue;
			}

			$type    = strtolower( (string) self::prop( $element, 'type' ) );
			$options = self::prop( $element, 'options' );
			if ( in_array( $type, self::TEXT_ELEMENT_TYPES, true ) && is_object( $options ) && property_exists( $options, 'tag' ) ) {
				$options->tag = self::textTag( $options->tag );
			}

			$actions = self::prop( $element, 'actions' );
			if ( ! is_object( $actions ) && ! is_array( $actions ) ) {
				continue;
			}

			$previousActions = self::prop( self::prop( $previousElements, $elementId ), 'actions' );
			foreach ( $actions as $actionKey => $action ) {
				$actionOptions = self::prop( $action, 'options' );
				if ( self::prop( $action, 'type' ) !== 'customJS' || ! is_object( $actionOptions ) ) {
					continue;
				}
				// Keep the custom js which is already saved for this action, otherwise drop it
				$previousAction = self::prop( $previousActions, $actionKey );
				$actionOptions->value = self::prop( $previousAction, 'type' ) === 'customJS'
					? ( self::prop( self::prop( $previousAction, 'options' ), 'value' ) ?? '' )
					: '';
			}
		}

		return $editor;
	}

	/**
	 * Retrieves a property of an object or a key of an array
	 *
	 * @param mixed      $node
	 * @param string|int $key
	 *
	 * @return mixed|null
	 */
	protected static function prop( $node, $key ) {
		if ( is_object( $node ) ) {
			return $node->{$key} ?? null;
		}
		if ( is_array( $node ) ) {
			return $node[ $key ] ?? null;
		}

		return null;
	}
}
