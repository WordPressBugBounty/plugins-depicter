<?php

namespace Depicter\Services;

class AuthorizationService {

	/**
	 * Whether the current user has specified capabilities or not.
	 *
	 * @param string|array $capabilities
	 *
	 * @return bool
	 */
	public function currentUserCan( $capabilities ){
		if( empty( $capabilities ) ){
			return false;
		}

		$capabilities = (array) $capabilities;
		foreach( $capabilities as $capability ){
			if( current_user_can( $capability ) ){
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether current user is allowed to publish document or not
	 *
	 * @return bool
	 */
	public function currentUserCanPublishDocument(){
		return $this->currentUserCan( [ 'manage_options', 'publish_depicter' ] );
	}

	/**
	 * Checks if user has enough quota to publish a new document based on their tier
	 *
	 * @return bool
	 */
	public function userHasPublishQuota(){
		// For free tier users, check if they've reached the limit (currently 2)
		if ( \Depicter::auth()->isFreeTier() ) {
			return \Depicter::documentRepository()->getNumberOfPublishedDocuments() < 2;
		}

		return true;
	}
}
