<?php
namespace Depicter\Integration\MailChimp;


use Averta\Core\Utility\Arr;
use Averta\WordPress\Utility\JSON;
use Depicter\GuzzleHttp\Client;
use Depicter\GuzzleHttp\Exception\GuzzleException;
use Depicter\GuzzleHttp\Exception\ResponseException;

class MailChimp
{

    /**
     * Retrieves the list of audiences from provided account
     *
     * @param string $apiKey
     *
     * @return array
     */
    public function getAudienceList($apiKey) {
        $region = $this->getRegion($apiKey);
        $url = "https://$region.api.mailchimp.com/3.0/lists";

        try {
            $body = $this->callApi($apiKey, $url);

            return [
                'hits'    => $body['lists'] ?? []
            ];
        } catch (GuzzleException $e) {
            return [
                'hits'    => [],
                'errors'   => [ $e->getMessage() ]
            ];
        }
    }

    /**
     * Retrieves the list of fields from provided audience
     *
     * @param string $apiKey
     * @param string|int $listId
     *
     * @return array
     */
    public function getListFields( $apiKey, $listId ) {
        $region = $this->getRegion($apiKey);
        $url = "https://$region.api.mailchimp.com/3.0/lists/$listId/merge-fields";

        try{
            $body = $this->callApi($apiKey, $url);

            $mergeFields = empty( $body['status'] ) ? [
                [
                    "merge_id" => 0,
                    "tag" => "email_address",
                    "name" => "Email",
                    "type" => "text",
                    "required" => true,
                    "default_value" => "",
                    "public" => true,
                    "display_order" => 0,
                    "options" => [
                        "size" => 25,
                        "choices" => null
                    ]
                ]
            ] : [];

            if ( ! empty( $body['merge_fields'] ) ) {
                $mergeFields = Arr::merge( $mergeFields, $body['merge_fields']);
            }

            return [
                'hits'  => $mergeFields
            ];
        } catch (GuzzleException $e) {
            return [
                'hits'    => [],
                'errors'   => [ $e->getMessage() ]
            ];
        }
    }

    public function submitToMailchimp( $leadId ): array
    {
        try{
            $lead = \Depicter::leadRepository()->get( $leadId );
            if ( empty( $lead ) ) {
                return [
                    'success' => false,
                    'error'   => __( 'Lead does not exist', 'depicter' )
                ];
            }
        } catch ( \Exception $e ){
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }

        $config = $this->getConfig( $lead['source_id'] );
        if ( empty( $config ) ) {
            return [
                'success' => false,
                'error'   => __( 'Lead form is not connected to mailchimp', 'depicter' )
            ];
        }

        if ( empty( $config['apiKey'] ) ) {
            return [
                'success' => false,
                'error'   => __( 'Mailchimp API key is required', 'depicter' )
            ];
        }

        $region = $this->getRegion( $config['apiKey'] );
        if ( empty( $region ) ) {
            return [
                'success' => false,
                'error'   => __( 'Mailchimp API key is not valid', 'depicter' )
            ];
        }

        if ( empty( $config['listId'] ) ) {
            return [
                'success' => false,
                'error'   => __( 'Mailchimp audience is required', 'depicter' )
            ];
        }

        try{
            $leadFields = \Depicter::leadFieldRepository()->leadField()->where( 'lead_id', $leadId )->findAll()->get();
            if ( $leadFields ) {
                $leadFields = $leadFields->toArray();
            } else {
                return [
                    'success' => false,
                    'error'   => __( 'Lead does not have any field', 'depicter' )
                ];
            }
        } catch ( \Exception $e ){
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }

        // lead field values keyed by field name
        $leadValues = [];
        foreach ( $leadFields as $leadField ) {
            $leadValues[ $leadField['name'] ] = $leadField['value'];
        }

        $email = '';
        $mergeFields = [];
        $fieldMapping = ! empty( $config['fieldMapping'] ) && is_array( $config['fieldMapping'] ) ? $config['fieldMapping'] : [];
        foreach ( $fieldMapping as $fieldMap ) {
            if ( empty( $fieldMap['from'] ) || empty( $fieldMap['to'] ) || 0 !== strpos( $fieldMap['from'], 'field:' ) ) {
                continue;
            }

            $fieldName = substr( $fieldMap['from'], strlen( 'field:' ) );
            if ( ! isset( $leadValues[ $fieldName ] ) ) {
                continue;
            }

            if ( $fieldMap['to'] == 'email_address' ) {
                $email = $leadValues[ $fieldName ];
                continue;
            }

            $mergeFields[ $fieldMap['to'] ] = $leadValues[ $fieldName ];
        }

        // Fallback to the field named "email" if email field is not mapped
        if ( empty( $email ) && ! empty( $leadValues['email'] ) ) {
            $email = $leadValues['email'];
        }

        $email = trim( $email );
        if ( empty( $email ) || ! is_email( $email ) ) {
            return [
                'success' => false,
                'error'   => __( 'A valid email is required', 'depicter' )
            ];
        }

        // Upsert the member, so existing contacts get updated instead of returning "Member Exists" error
        $subscriberHash = md5( strtolower( $email ) );
        $url = "https://" . $region . ".api.mailchimp.com/3.0/lists/" . rawurlencode( $config['listId'] ) . "/members/" . $subscriberHash;

        $payload = [
            'email_address' => $email,
            'status_if_new' => 'subscribed',
        ];
        // Mailchimp expects merge_fields to be an object, so skip it when it's empty
        if ( ! empty( $mergeFields ) ) {
            $payload['merge_fields'] = $mergeFields;
        }

        $client = new Client();
        try {
            $response = $client->put( $url, [
                'auth' => [
                    'anystring', $config['apiKey'],
                ],
                'json' => $payload
            ]);

            return [
                'success' => true,
                'response' => JSON::decode($response->getBody()->getContents(), true)
            ];
        } catch ( ResponseException $e ){
            return [
                'success' => false,
                'error'   => $this->getErrorMessage( $e )
            ];
        } catch ( GuzzleException $e ){
            return [
                'success' => false,
                'error'   => __( 'Mailchimp API error', 'depicter' ) . ': ' . $e->getMessage()
            ];
        }
    }

    /**
     * Retrieves mailchimp config of a document
     *
     * @param int $source_id
     *
     * @return array|false  Mailchimp config or false if document is not connected to mailchimp
     */
    public function getConfig( $source_id ) {
        $editorData = \Depicter::document()->getEditorRawData( $source_id );
        if ( empty( $editorData ) ) {
            return false;
        }

        $editorData = JSON::decode( $editorData, true );
        if ( empty( $editorData['options']['integrations']['mailchimp']['config'] ) || empty( $editorData['options']['integrations']['mailchimp']['enabled'] ) ) {
            return false;
        }

        return $editorData['options']['integrations']['mailchimp']['config'];
    }

    /**
     * Retrieves data center region from API key. e.g "us21" from "xxxxxxxx-us21"
     *
     * @param string $apiKey
     *
     * @return string
     */
    protected function getRegion( $apiKey ) {
        $apiKey = trim( (string) $apiKey );
        $position = strrpos( $apiKey, '-' );
        if ( false === $position ) {
            return '';
        }

        $region = substr( $apiKey, $position + 1 );

        return preg_match( '/^[a-z0-9]+$/i', $region ) ? $region : '';
    }

    /**
     * Extracts the error message from mailchimp error response
     *
     * @param ResponseException $e
     *
     * @return string
     */
    protected function getErrorMessage( ResponseException $e ) {
        $message = __( 'Mailchimp API error', 'depicter' ) . ' (' . $e->getResponse()->getStatusCode() . ')';

        $body = JSON::decode( (string) $e->getResponse()->getBody(), true );
        if ( ! empty( $body['title'] ) ) {
            $message .= ': ' . $body['title'];
        }
        if ( ! empty( $body['detail'] ) ) {
            $message .= ' - ' . $body['detail'];
        }

        return $message;
    }

    protected function callApi($apiKey, $url){

        $client = new Client();
        $response = $client->get( $url, [
            'auth' => ['anystring', $apiKey], // Mailchimp expects username:apikey format
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        return JSON::decode($response->getBody()->getContents(), true);
    }
}
