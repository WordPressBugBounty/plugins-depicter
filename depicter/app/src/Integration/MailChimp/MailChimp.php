<?php
namespace Depicter\Integration\MailChimp;


use Averta\Core\Utility\Arr;
use Averta\WordPress\Utility\JSON;
use Depicter\GuzzleHttp\Client;
use Depicter\GuzzleHttp\Exception\GuzzleException;

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
        $region = substr($apiKey, strpos($apiKey, '-') + 1);
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
        $region = substr($apiKey, strpos($apiKey, '-') + 1);
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

        $mappedEmailField = array_filter( $config['fieldMapping'], function ( $fieldMap ) {
           return !empty( $fieldMap['to'] ) && $fieldMap['to'] == 'email_address';
        });

        if ( empty( $mappedEmailField ) ) {
            return [
                'success' => false,
                'error'   => __( 'Email is required', 'depicter' )
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

        $region = substr($config['apiKey'], strpos($config['apiKey'], '-') + 1);
        $url = "https://" . $region . ".api.mailchimp.com/3.0/lists/" . $config['listId'] . "/members/";


        $payload = [
            'status' => 'subscribed',
        ];
        $mergeFields = [];
        foreach ( $leadFields as $leadField ) {
            if ( $leadField['name'] == 'email' ) {
                $payload['email_address'] = $leadField['value'];
                continue;
            }

            $mappedField = array_filter( $config['fieldMapping'], function ( $fieldMap ) use ( $leadField ) {
                return !empty( $fieldMap['from'] ) && $fieldMap['from'] == 'field:' . $leadField['name'];
            });

            if ( empty( $mappedField ) ) {
                continue;
            }

            $mergeFields[ $mappedField[0]['to'] ] = $leadField['value'];
        }
        $payload['merge_fields'] = $mergeFields;

        $client = new Client();
        try {
            $response = $client->post( $url, [
                'auth' => [
                    'myToken', $config['apiKey'],
                ],
                'json' => $payload
            ]);

            // todo: we have to check the final response here
            return [
                'success' => true,
                'response' => JSON::decode($response->getBody()->getContents(), true)
            ];
        } catch ( GuzzleException $e ){
            return [
                'success' => false,
                'error'   => __( 'Mailchimp API error', 'depicter' )
            ];
        }
    }

    public function getConfig( $source_id ) {
        $editorData = \Depicter::document()->getEditorRawData( $source_id );
        if ( empty( $editorData ) ) {
            return [
                'success' => false,
                'error'   => __( 'Empty document data', 'depicter' )
            ];
        }

        $editorData = JSON::decode( $editorData, true );
        if ( empty( $editorData['options']['integrations']['mailchimp']['config'] ) || empty( $editorData['options']['integrations']['mailchimp']['enabled'] ) ) {
            return [
                'success' => false,
                'error'   => __( 'Document is not connected to mailchimp', 'depicter' )
            ];
        }

        return $editorData['options']['integrations']['mailchimp']['config'];
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
