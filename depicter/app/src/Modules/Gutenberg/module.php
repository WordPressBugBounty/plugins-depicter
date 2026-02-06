<?php

namespace Depicter\Modules\Gutenberg;

class Module
{
    public function __construct() {
        $this->initGutenbergBlock();
		add_action( 'admin_enqueue_scripts', [ $this, 'loadGutenbergAdminWidgetScripts'] );
		add_action( 'wp_enqueue_scripts', [$this, 'loadGutenbergWidgetScripts']);
    }

    public function loadGutenbergWidgetScripts() {
        if ( $this->hasDepicter() ) {
            wp_enqueue_style(
                'depicter-gutenberg',
                \Depicter::core()->assets()->getUrl() . '/app/src/Modules/Gutenberg/build/index.css',
                [],
                '1.0.0'
            );
        }
	}

	public function loadGutenbergAdminWidgetScripts() {

		$current_screen = get_current_screen();
		if ( !$current_screen->is_block_editor() ) {
			return;
		}

		$list = [
			[
				'id' => "0",
				'name' => __( 'Select Slider', 'depicter' )
			]
		];
		$documents = \Depicter::documentRepository()->select( ['id', 'name'] )->where('type', 'not in', ['popup', 'banner-bar'])->orderBy('modified_at', 'DESC')->findAll()->get();
		$list = $documents ? array_merge( $list, $documents->toArray() ) : $list;
		if ( !empty( $list ) ) {
			foreach ( $list as $key => $item ) {
				$list[ $key ]['label'] = $item['name'];
				unset( $list[ $key ]['name'] );

				$list[ $key ]['value'] = $item['id'];
				unset( $list[ $key ]['id'] );
			}
		}

		// load common assets
		\Depicter::front()->assets()->enqueueStyles();
		\Depicter::front()->assets()->enqueueScripts(['player', 'iframe-resizer']);

		wp_localize_script( 'wp-block-editor', 'depicterSliders',[
			'list' => $list,
			'ajax_url' => admin_url('admin-ajax.php'),
			'editor_url' => \Depicter::editor()->getEditUrl('1'),
			'token' => \Depicter::csrf()->getToken( \Depicter\Security\CSRF::EDITOR_ACTION ),
			'publish_text' => esc_html__( 'Publish Slider', 'depicter' ),
			'edit_text' => esc_html__( 'Edit Slider', 'depicter' )
		]);

	}

	public function initGutenbergBlock() {
		register_block_type( __DIR__ . '/build', [
			'render_callback' => [ $this, 'renderGutenbergBlock' ]
		] );
	}

	public function renderGutenbergBlock( $blockAttributes ) {

		if ( !empty( $blockAttributes['id'] ) ) {
			$id = (int) $blockAttributes['id'];
			return depicter( $id, ['echo' => false ] );
		} else {
			echo esc_html__( 'Slider ID required', 'depicter' );
		}

	}

    public function hasDepicter($post_id = null) {
        if (!$post_id) {
            global $post;
            $post_id = $post->ID ?? null;
        }
        
        if (!$post_id) {
            return false;
        }
        
        $post_content = get_post_field('post_content', $post_id);
        
        // Check for Gutenberg block comments
        return strpos($post_content, '<!-- wp:depicter/slider') !== false;
    }

}

new Module();