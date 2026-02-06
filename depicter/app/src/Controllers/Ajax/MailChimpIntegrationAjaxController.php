<?php

namespace Depicter\Controllers\Ajax;

use Averta\WordPress\Utility\JSON;
use Depicter;
use Depicter\Utility\Sanitize;
use Psr\Http\Message\ResponseInterface;
use WPEmerge\Requests\RequestInterface;

class MailChimpIntegrationAjaxController
{

    /**
     * Get API keys
     *
     * @param RequestInterface $request
     * @param $view
     *
     * @return ResponseInterface
     */
    public function getApiKeys( RequestInterface $request, $view ): ResponseInterface {
        return Depicter::json(Depicter::options()->get('integration.mailchimp.api_keys', []));
    }

    /**
     * Save API keys
     *
     * @param RequestInterface $request
     * @param $view
     *
     * @return ResponseInterface
     */
    public function saveApiKey( RequestInterface $request, $view): ResponseInterface
    {
        $apiKey = Sanitize::textfield( $request->body( 'api_key', '') );
        $apiKey = trim($apiKey);

        if ( empty( $apiKey ) ) {
            return Depicter::json([
                'errors' => [ __( 'API Key is required.', 'depicter' ) ]
            ])->withStatus(400);
        }

        $apiKeys = \Depicter::options()->get('integration.mailchimp.api_keys', []);
        if ( ! in_array( $apiKey, $apiKeys ) ) {
            $apiKeys[] = $apiKey;
        }

        // return true only if the api key is changed or new api key provided
        Depicter::options()->set('integration.mailchimp.api_keys', $apiKeys );

        return Depicter::json([
            'success' => true
        ])->withStatus(200);
    }

    /**
     * Delete API key
     *
     * @param RequestInterface $request
     * @param $view
     *
     * @return ResponseInterface
     */
    public function deleteApiKey( RequestInterface $request, $view): ResponseInterface
    {
        $apiKey = Sanitize::textfield( $request->body( 'api_key', '') );
        $apiKey = trim($apiKey);

        if ( empty( $apiKey ) ) {
            return Depicter::json([
                'errors' => [ __( 'API Key is required.', 'depicter' ) ]
            ])->withStatus(400);
        }

        $apiKeys = \Depicter::options()->get('integration.mailchimp.api_keys', []);
        if ( in_array( $apiKey, $apiKeys ) ) {
            $apiKeys = array_diff($apiKeys, [$apiKey]);
            Depicter::options()->set('integration.mailchimp.api_keys', array_values($apiKeys) );

            return Depicter::json([
                'success' => true
            ])->withStatus(200);
        }

        return Depicter::json([
            'errors' => [ __( 'API Key not found.', 'depicter' ) ]
        ])->withStatus(404);
    }

    /**
     * Retrieves the list of audiences from provided account
     *
     * @param  RequestInterface  $request
     * @param                    $view
     *
     * @return ResponseInterface
     */
    public function audienceLists( RequestInterface $request, $view ): ResponseInterface
    {
        $apiKey = Sanitize::textfield( $request->query( 'api_key', '') );
        if ( empty( $apiKey ) ) {
            return Depicter::json([
                'errors' => [ __( 'API Key is required.', 'depicter' ) ]
            ])->withStatus(400);
        }

        return \Depicter::json(
            Depicter::integration()->mailchimp()->getAudienceList( $apiKey )
        );
    }

    /**
     * Retrieves the list of fields from a provided audience
     *
     * @param RequestInterface $request
     * @param $view
     *
     * @return ResponseInterface
     */
    public function audienceFields( RequestInterface $request, $view ): ResponseInterface
    {
        $apiKey = Sanitize::textfield( $request->query( 'api_key', '') );
        if ( empty( $apiKey ) ) {
            return Depicter::json([
                'errors' => [ __( 'API Key is required.', 'depicter' ) ]
            ])->withStatus(400);
        }

        $audienceID = Sanitize::textfield( $request->query( 'audience_id', '') );
        if ( empty( $audienceID ) ) {
            return Depicter::json([
                'errors' => [ __( 'Audience ID is required.', 'depicter' ) ]
            ])->withStatus(400);
        }

        return Depicter::json(
            Depicter::integration()->mailchimp()->getListFields( $apiKey, $audienceID )
        );
    }
}
