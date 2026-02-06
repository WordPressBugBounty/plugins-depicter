<?php
namespace Depicter\Integration;

use Depicter\Integration\MailChimp\MailChimp;
use Depicter\Integration\Manager;
use WPEmerge\ServiceProviders\ServiceProviderInterface;

/**
 * Load document data manager.
 */
class ServiceProvider implements ServiceProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function register( $container ) {
		$app = $container[ WPEMERGE_APPLICATION_KEY ];

		$container[ 'depicter.integration.manager' ] = function () {
			return new Manager();
		};

		$app->alias( 'integration', 'depicter.integration.manager' );

        $container[ 'depicter.integration.mailchimp' ] = function () {
			return new MailChimp();
		};

	}

	/**
	 * {@inheritDoc}
	 */
	public function bootstrap( $container ) {
        \Depicter::integration()->init();
	}

}
