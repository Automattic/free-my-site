<?php

namespace DotOrg\FreeMySite\UI;

use DotOrg\FreeMySite;
use DotOrg\FreeMySite\CMSDetection\Whatcms_Detector;
use DotOrg\FreeMySite\GuideSourcing;
use DotOrg\FreeMySite\Storage\Store;

class AdminUI {
	private $markdown_parser;

	private $guides = [
		'squarespace' => 'https://raw.githubusercontent.com/WordPress/data-liberation/trunk/guides/squarespace-to-wordpress.md',
		'tumblr'      => 'https://raw.githubusercontent.com/WordPress/data-liberation/trunk/guides/tumblr-to-wordpress.md',
		'wix'         => 'https://raw.githubusercontent.com/WordPress/data-liberation/trunk/guides/wix-to-wordpress.md',
		'wordpress'   => 'https://raw.githubusercontent.com/WordPress/data-liberation/trunk/guides/wordpress-to-wordpress.md',
	];

	private $other_guides = [
		'rss' => 'https://raw.githubusercontent.com/WordPress/data-liberation/trunk/guides/rss-to-wordpress.md',
	];

	public function __construct() {
		$this->markdown_parser = new \ParseDown();

		add_action(
			'admin_menu', function () {
			add_submenu_page(
				'tools.php',                                  // Parent menu slug
				__( 'Free My Site', 'free-my-site' ),         // Page title
				__( 'Free My Site', 'free-my-site' ),         // Menu title
				'manage_options',                             // Capability
				'free-my-site-page',                          // Menu slug
				array( $this, 'free_my_site_page_contents' )  // Callback function to display the page
			);
		}
		);
	}

	public function free_my_site_page_contents() {
		// figure out what page we are on
		// Default or first page, where we accept site url
		// Second page, where we display the identified CMS and show steps + start process prompt
		// Third page and so on, following the steps of the guide
		// Last page, confirming everthing went as expected

		if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
			if ( ! $this->is_site_url_valid( $_POST[ 'site_url' ] ) ) {
				?>
                <div class="notice notice-error"><p>Please enter a valid URL</p></div>
				<?php
				$this->free_my_site_page_default();
				return;
			}

			$site_url = $_POST[ 'site_url' ]; // passed validation above, so safe to use

			$cms = $this->detect_cms( $site_url );
			if ( empty( $cms ) ) {
				$this->free_my_site_page_default();
				return;
			}

			$guide_url = $this->get_guide_url_for_cms( $cms );
			var_dump( $guide_url );
			$markdown_guide = $this->fetch_guide( $guide_url );
			var_dump( strlen( $markdown_guide ) );
			Store::new_instance( $site_url, $markdown_guide );

			$this->free_my_site_page_identified_cms( $cms, $markdown_guide );
			return;
		}

		if ( ! isset( $_GET[ 'instance' ] ) ) {
			$this->free_my_site_page_default();
			return;
		}
	}

	public function is_site_url_valid( $site_url ) : bool {
		if ( empty( $site_url ) ) {
			return false;
		}

		// Remove all illegal characters from a url
		$url = filter_var( $site_url, FILTER_SANITIZE_URL );

		$valid_url      = filter_var( $url, FILTER_VALIDATE_URL );
		$valid_http_url = $valid_url && ( substr( $url, 0, 7 ) === "http://" || substr( $url, 0, 8 ) === "https://" );

		return $valid_http_url;
	}

	public function free_my_site_page_default() {
		?>
        <div class="wrap">
            <h2>Free My Site</h2>
			<?php
			$whatcms_api_key_supplied = defined( 'WHATCMS_API_KEY' ) && ! empty( WHATCMS_API_KEY );

			if ( ! $whatcms_api_key_supplied ) {
				?>
                <div class="notice notice-error">
                    <p>WHATCMS_API_KEY not supplied as a PHP constant.</p>
                </div>
				<?php
			}
			?>

            <br/><br/>

            <form method="POST" action="">
                <label for="site_url">Enter your website URL:</label>
                <input type="text" name="site_url" id="site_url" value="<?php
				echo ! empty( $_REQUEST[ 'site_url' ] ) ? esc_url( $_REQUEST[ 'site_url' ] ) : ''; ?>" required>
                <p class="submit">
                    <input type="submit" name="submit_url" <?php
					echo $whatcms_api_key_supplied ? '' : 'disabled' ?> class="button button-primary" value="Submit">
                </p>
            </form>
        </div>
		<?php
	}

	private function detect_cms( $site_url ) : string {
		$d      = new Whatcms_Detector( WHATCMS_API_KEY );
		$result = $d->run( $site_url );

		if ( ! $result->success ) {
			?>
            <div class="notice notice-error"><p>Failed to detect CMS: <?php
					echo $result->reason; ?></p></div>
			<?php
			return '';
		}

		return $result->cms;
	}

	public function get_guide_url_for_cms( $cms ) {
		$cms = strtolower( $cms );

		if ( isset( $this->guides[ $cms ] ) ) {
			return $this->guides[ $cms ];
		}

		return '';
	}

	private function fetch_guide( $guide_url ) {
		$guide = new GuideSourcing\HTTPSourcer( $guide_url );
		return $guide->fetch();
	}

	public function free_my_site_page_identified_cms( $cms, $markdown_guide ) {
		?>
        <div class="wrap">
            <h2>Free My Site</h2>
            <br/><br/>
            <p>Your website is hosted on <strong><?php
					echo $cms ?></strong> platform.</p>
            <p>Let's follow the guide shown below:</p>

			<?php
			$this->display_steps( GuideSourcing\parse_guide( $markdown_guide ) ); ?>
        </div>
		<?php
	}

	public function display_steps( $parsed_guide ) {
		foreach ( $parsed_guide as $section ) {
			if ( $section[ 'heading_level' ] === 2 ) {
				if ( strpos( $section[ 'heading_content' ], 'Step' ) === 0 ) {
					echo '"' . $section[ 'heading_content' ] . '"';
					echo $this->markdown_parser->text( $section[ 'section_content' ] );

					echo "<br>****<br>";
				} else {

				}
			}
		}
	}
}
