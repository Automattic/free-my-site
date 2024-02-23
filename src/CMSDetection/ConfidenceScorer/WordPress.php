<?php

namespace DotOrg\FreeMySite\CMSDetection\ConfidenceScorer;

class WordPress {
	private string $url;
	private string $html_source;

	private string $identified_version = '';

	// list of methods that employ diff techniques for detection that should be called in order
	private array $detection_techniques_methods = [
		'check_wordpress_meta_tag',
		'check_wordpress_urls',
		'check_for_wordpress_paths_in_html_src',
	];

	private array $wordpress_urls = [
		[
			'url_path'             => 'wp-login.php',
			'expected_status_code' => 200,
			'confidence_boost'     => 100,
		],
	];

	private int $confidence = 0;

	public function __construct( $url, $html_source ) {
		$this->url         = trailingslashit( $url );
		$this->html_source = $html_source;
	}

	public function detect() : array {
		foreach ( $this->detection_techniques_methods as $method ) {
			$this->$method();

			if ( $this->confidence >= 100 ) {
				return [ 100, $this->identified_version ];
			}
		}

		return [ $this->confidence, $this->identified_version ];
	}

	private function check_for_wordpress_paths_in_html_src() {
		$occurrences = substr_count( $this->html_source, $this->url . 'wp-content/themes/' ) +
					   substr_count( $this->html_source, $this->url . 'wp-content/plugins/' );
		$this->increase_confidence( 10 * $occurrences );
	}

	private function increase_confidence( $value ) {
		$this->confidence += $value;
	}

	private function check_wordpress_urls() {
		foreach ( $this->wordpress_urls as $wordpress_url ) {
			if ( $this->get_http_status( $this->url . $wordpress_url[ 'url_path' ] ) === $wordpress_url[ 'expected_status_code' ] ) {
				$this->increase_confidence( $wordpress_url[ 'confidence_boost' ] );
			}
		}
	}

	private function get_http_status( $url ) : ?int {
		$headers = @get_headers( $url );
		if ( $headers && isset( $headers[ 0 ] ) ) {
			return (int) substr( $headers[ 0 ], 9, 3 );
		}
		return null;
	}

	private function check_wordpress_meta_tag() {
		// Check for WordPress version meta tag
		if ( preg_match( '/<meta name="generator" content="WordPress ([0-9.]+)" \/>/i', $this->html_source, $matches ) ) {
			$this->increase_confidence( 100 );
			// Example: Extract version number
			$this->identified_version = $matches[ 1 ];
		}
	}
}