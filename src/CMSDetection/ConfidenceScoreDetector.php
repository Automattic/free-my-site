<?php

namespace DotOrg\FreeMySite\CMSDetection;

class ConfidenceScoreDetector implements Detector {
	private string $url;
	private string $html_source;
	private \WP_Error $error;

	private array $detectors = [
		'WordPress'
	];
	private array $score = [];

	public function __construct() {
		$this->error = new \WP_Error();
	}

	public function run( $url ) : Result {
		$this->url = $this->ensure_http_https_in_url( $url );
		$this->fetch_html();

		$result = new Result();

		// any http errors?
		if ( $this->error->has_errors() ) {
			$result->success = false;
			$result->note    = implode( 'and ', $this->error->get_error_messages() );
			return $result;
		}

		// run all confidence scorers of diff cms code-prints
		foreach ( $this->detectors as $detector ) {
			$scorerClassName = "DotOrg\\FreeMySite\\CMSDetection\\ConfidenceScorer\\$detector";
			$scorer          = new $scorerClassName( $this->url, $this->html_source );

			list( $this->score[ $detector ], $result->cms_version ) = $scorer->detect();

			if ( $this->score[ $detector ] >= 100 ) {
				break;
			}
		}

		$high_score = max( $this->score );
		if ( $high_score < 80 ) {
			$result->success = false;
			$result->note    = 'low confidence score';
			return $result;
		}

		$result->success = true;
		$result->cms     = array_search( $high_score, $this->score );
		$result->note    = 'confidence score: [' . $high_score . '] cms version: [' . $result->cms_version . ']';

		return $result;
	}

	public function ensure_http_https_in_url( $url ) : string {
		// Parse the URL
		$url_parts = parse_url( $url );

		// If the scheme (protocol) is not present, OR it's neither http nor https
		if ( ! isset( $url_parts[ 'scheme' ] ) || ! in_array( $url_parts[ 'scheme' ], array( 'http', 'https' ) ) ) {
			// Prepend "http://" to the URL
			$url_parts[ 'scheme' ] = 'http';
		}

		// Reconstruct the URL
		$reconstructed_url = $url_parts[ 'scheme' ] . '://';
		if ( isset( $url_parts[ 'user' ] ) ) {
			$reconstructed_url .= $url_parts[ 'user' ];
			if ( isset( $url_parts[ 'pass' ] ) ) {
				$reconstructed_url .= ':' . $url_parts[ 'pass' ];
			}
			$reconstructed_url .= '@';
		}
		if ( isset( $url_parts[ 'host' ] ) ) {
			$reconstructed_url .= $url_parts[ 'host' ];
		}
		if ( isset( $url_parts[ 'port' ] ) ) {
			$reconstructed_url .= ':' . $url_parts[ 'port' ];
		}
		if ( isset( $url_parts[ 'path' ] ) ) {
			$reconstructed_url .= $url_parts[ 'path' ];
		}
		if ( isset( $url_parts[ 'query' ] ) ) {
			$reconstructed_url .= '?' . $url_parts[ 'query' ];
		}
		if ( isset( $url_parts[ 'fragment' ] ) ) {
			$reconstructed_url .= '#' . $url_parts[ 'fragment' ];
		}

		return $reconstructed_url;
	}

	public function fetch_html() {
		$response = wp_remote_get( $this->url );
		if ( is_wp_error( $response ) ) {
			$this->error = $response;
			return;
		}

		$this->html_source = wp_remote_retrieve_body( $response );
	}
}