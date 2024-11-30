<?php
namespace Depicter\Services;

use Averta\WordPress\Utility\JSON;
use Depicter;
use Depicter\GuzzleHttp\Exception\GuzzleException;

class UsageService {

	/**
	 * Get collected data
	 *
	 * @return array|mixed
	 */
    public function get() {
		return \Depicter::options()->get( 'usage', '');
    }

	/**
	 * Check if user agrees with collect data consent or not
	 *
	 * @return bool
	 */
	public function userAllowedToCollectData() {
		$dataCollectionConsent = Depicter::options()->get( 'data_collect_consent', '');
		return ! empty( $dataCollectionConsent ) && $dataCollectionConsent == 'allow';
	}

	/**
	 * Collect data
	 *
	 * @return void
	 */
    public function collect() {
		if ( ! $this->userAllowedToCollectData() ) {
			return;
		}

		$documents = \Depicter::documentRepository()->document()->where('parent', 0 )->get()->toArray();
		$documentTypes = [];
		$importantElements = [
			'"type":"dpcCouponBox"' => 0,
			'"type":"form"' => 0,
			'"type":"wpShortcode"' => 0,
			'"type":"dpcIframe"' => 0,
			'"type":"dpcCountdown"' => 0,
			'"datasource"' => 0
		];

		if ( !empty( $documents ) ) {
			foreach ($documents as $document) {
				$document['type'] = $document['type'] === 'custom' ? 'slider' : $document['type'];
				if ( isset ( $documentTypes[ $document['type'] ] ) ) {
					$documentTypes[ $document['type'] ] += 1;
				} else {
					$documentTypes[ $document['type'] ] = 1;
				}

				foreach( $importantElements as $element => $count ) {
					$importantElements[ $element ] = $count + substr_count( $document['content'], $element );
				}
			}
		}

		$pageBuilders = [
			'wpBakery' => 'Vc_Manager',
			'elementor' => '\Elementor\Plugin',
			'divi' => 'ET_Builder_Plugin',
			'beaverBuilder' => '\FLBuilder',
			'oxygen' => '\OxyEl',
			'bricks' => '\Bricks\Elements'
		];

		foreach( $pageBuilders as $pageBuilder => $condition ) {
			if ( ! class_exists( $condition ) ) {
				unset( $pageBuilders[ $pageBuilder ] );
			}
		}

		$usage = [
			'document_stat' => count( $documents ),
			'document_types' => $documentTypes,
			'important_elements' => $importantElements,
			'document_features' => '',
			'wp_tools' => [
				'themeName' => wp_get_theme()->get( 'Name' ),
				'pageBuilders' => $pageBuilders,
			]
		];

		\Depicter::options()->set( 'usage', $usage );
    }

	/**
	 * @throws GuzzleException
	 */
	public function send() {
		if ( ! $this->userAllowedToCollectData() ) {
			return;
		}

		$usage = Depicter::options()->get( 'usage', []);
		Depicter::remote()->post( '/v1/usage/collect', [
			'form_params' => [
				'collected_data' => JSON::encode( $usage ),
			]
		]);
	}
}
