<?php

namespace DotOrg\FreeMySite\CMSDetection;

/**
 * Detection powered by Whatcms.org API
 *
 * API is also capable of returning the software version number
 */
class WhatcmsDetector implements Detector {
	private string $apiKey;

	public function __construct( $apiKey ) {
		$this->apiKey = $apiKey;
	}

	public function run( $url ) : Result {
		$detection_result          = new Result();
		$detection_result->success = false;

		$apiEndpoint = add_query_arg(
			array(
				'key'     => $this->apiKey,
				'url'     => $url,
				'private' => true // requires paid plan
			),
			'https://whatcms.org/API/Tech'
		);

		$response = wp_remote_get( $apiEndpoint );
		if ( is_wp_error( $response ) ) {
			$detection_result->reason = 'network error';
			return $detection_result;
		}

		$json_response = wp_remote_retrieve_body( $response );
		$info          = json_decode( $json_response, true );
		if ( $info[ 'result' ][ 'code' ] != "200" ) {
			$detection_result->reason = 'http status ' . $info[ 'result' ][ 'code' ];
			return $detection_result;
		}

		foreach ( $info[ 'results' ] as $result ) {
			foreach ( $result[ 'categories' ] as $category ) {
				if ( in_array( $category, array( 'Blog', 'CMS' ) ) ) {
					$detection_result->success     = true;
					$detection_result->cms         = $result[ 'name' ];
					$detection_result->cms_version = $result[ 'version' ];

					return $detection_result;
				}
			}
		}

		$detection_result->reason = 'unexpected response';
		return $detection_result;
	}
}