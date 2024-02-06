<?php

namespace DotOrg\FreeMySite\GuideSourcing;

interface Sourcer {
	public function fetch() : string;
}

/**
 * Sourcing via HTTP
 */
class HTTPSourcer implements Sourcer {
	private string $source;

	public function __construct( $url ) {
		$this->source = $url;
	}

	public function fetch() : string {
		// Fetch the content from the URL using wp_remote_get
		$response = wp_remote_get( $this->source );

		// Check if response is valid and no errors occurred
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			return wp_remote_retrieve_body( $response );
		}

		return '';
	}
}
