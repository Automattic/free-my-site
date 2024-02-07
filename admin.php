<?php

namespace DotOrg\FreeMySite\UI;

use DotOrg\FreeMySite;
use DotOrg\FreeMySite\CMSDetection\Whatcms_Detector;
use DotOrg\FreeMySite\GuideSourcing;
use DotOrg\FreeMySite\Storage\Store;

class AdminUI {
	const ADMIN_PAGE_SLUG = 'free-my-site-page';
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

		// add page under Tools menu
		add_action( 'admin_menu', function() {
			add_submenu_page(
				'tools.php',                                    // Parent menu slug
				__( 'Free My Site', 'free-my-site' ),           // Page title
				__( 'Free My Site', 'free-my-site' ),           // Menu title
				'manage_options',                               // Capability
				self::ADMIN_PAGE_SLUG,                          // Menu slug
				array( $this, 'admin_page_content' )  // Callback function to display the page
			);
		} );

		// ensure pre-requisites are met
		add_action( 'tools_page_' . self::ADMIN_PAGE_SLUG, function() {
			$whatcms_api_key_supplied = defined( 'WHATCMS_API_KEY' ) && ! empty( WHATCMS_API_KEY );

			if ( ! $whatcms_api_key_supplied ) {
				$this->add_admin_notice( 'WHATCMS_API_KEY not supplied as a PHP constant.', 'error' );
			}
		} );

		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
	}

	private function add_admin_notice( $notice, $type ) {
		if ( ! is_array( $notice ) ) {
			$notices = [ $notice ];
		} else {
			$notices = $notice;
		}
		add_action( 'admin_notices', function() use ( $notices, $type ) {
			$css_class = "notice notice-$type";
			printf( '<div class="%s"><p>%s</p></div>', $css_class, implode( '</p><p>', $notices ) );
		} );
	}

	public function admin_page_content() {
		// Either we show the default page, where we prompt for site url,
		// or we show the assist page, where we guide them through the migration process
		if ( ! empty( $_GET[ 'instance' ] ) ) {
			$this->admin_page_migrate();
		} else {
			$this->admin_page_default();
		}
	}

	public function admin_page_migrate() {
		$instance_id = $_GET[ 'instance' ];
		$details     = Store::get_instance_details( $instance_id );

		if ( empty( $details ) ) {
			$this->add_admin_notice( 'Invalid instance ID supplied.', 'error' );
		} else {
			$this->add_admin_notice( 'Your website <code>' . $details[ 'site_url' ] . '</code> is hosted on <strong>' . $details[ 'cms' ] . '</strong> platform.', 'info' );
		}
		?>
        <div class="wrap">
            <h2>Free My Site</h2>
			<?php do_action( 'admin_notices' ) ?>
			<?php if ( ! empty( $details ) ) { ?>
                <p>Let's follow the guide shown below:</p>
				<?php $this->display_steps( GuideSourcing\parse_guide( $details[ 'markdown_guide' ] ) ); ?>
			<?php } ?>
        </div>
		<?php
	}

	public function display_steps( $parsed_guide ) {
		?>
        <div id="free-my-site-accordion">
			<?php
			foreach ( $parsed_guide as $index => $section ) {
				if ( $section[ 'heading_level' ] === 2 ) {
					if ( strpos( $section[ 'heading_content' ], 'Step' ) === 0 ) {
						?>
                        <h3 onclick="toggleSection(this)"><?php echo esc_html( $section[ 'heading_content' ] ) ?></h3>
                        <div style="<?php echo $index === 0 ? '' : 'display:none;'; ?>">
							<?php echo $this->markdown_parser->text( $section[ 'section_content' ] ); ?>
                        </div>
						<?php
					}
				}
			}
			?>
        </div>
        <script type="text/javascript">
            function toggleSection(header) {
                // hide all but clicked upon section
                document.querySelectorAll('#free-my-site-accordion h3').forEach(function (h3Element) {
                    if (header !== h3Element) {
                        h3Element.nextElementSibling.style.display = 'none';
                    }
                });
                // toggle the clicked upon section
                const content = header.nextElementSibling;
                if (content.style.display === "block") {
                    content.style.display = "none";
                } else {
                    content.style.display = "block";
                }
            }

            // open first section, on page load
            document.getElementById('free-my-site-accordion').firstElementChild.click();
        </script>
        <style>
            #free-my-site-accordion h3 {
                background: #CCC;
                padding: 7px 12px;
                cursor: pointer;
            }

            #free-my-site-accordion img {
                max-width: 100%;
                border: solid 1px #CCCCCC;
            }
        </style>
		<?php
	}

	public function admin_page_default() {
		$whatcms_api_key_supplied = defined( 'WHATCMS_API_KEY' ) && ! empty( WHATCMS_API_KEY );
		?>
        <div class="wrap">
            <h2>Free My Site</h2>
			<?php do_action( 'admin_notices' ); ?>
            <br/><br/>
            <form method="POST" action="">
				<?php wp_nonce_field( 'free_my_site_input', 'nonce' ); ?>
                <label for="site_url">Enter your website URL:</label>
                <input type="text" name="site_url" id="site_url" value="<?php
				echo ! empty( $_REQUEST[ 'site_url' ] ) ? esc_url( $_REQUEST[ 'site_url' ] ) : ''; ?>"
                       required>
                <p class="submit">
                    <input type="submit" name="submit_url" <?php
					echo $whatcms_api_key_supplied ? '' : 'disabled' ?> class="button button-primary"
                           value="Submit">
                </p>
            </form>
        </div>
		<?php
	}

	public function handle_form_submission() {
		global $pagenow;

		if ( $pagenow != 'tools.php' || ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] != self::ADMIN_PAGE_SLUG ) ) {
			return; // no business
		}

		if ( $_SERVER[ 'REQUEST_METHOD' ] !== 'POST' ) {
			return;
		}

		// nonce verification
		if ( ! isset( $_POST[ 'nonce' ] ) || ! wp_verify_nonce( $_POST[ 'nonce' ], 'free_my_site_input' ) ) {
			$this->add_admin_notice(
				[
					'Sorry but it could not be verified that the request came from a valid or legitimate form
                            submission.',
					'Please try again below'
				],
				'error'
			);
			return;
		}

		// input validation
		if ( ! $this->is_site_url_valid( $_POST[ 'site_url' ] ) ) {
			$this->add_admin_notice( 'Please enter a valid URL.', 'error' );
			return;
		}

		$site_url = $_POST[ 'site_url' ]; // passed validation above, so safe to use

		$cms = $this->detect_cms( $site_url );
		if ( empty( $cms ) ) {
			$this->add_admin_notice( 'Failed to identify CMS.', 'error' );
			return;
		}

		$guide_url      = $this->get_guide_url_for_cms( $cms );
		$markdown_guide = $this->fetch_guide( $guide_url );

		$instance_id = Store::new_instance( $site_url, $cms, $markdown_guide );
		if ( $instance_id === false ) {
			$this->add_admin_notice( 'Failed to save free_my_site instance', 'error' );
			return;
		}

		// submission handled, redirect now
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => self::ADMIN_PAGE_SLUG,
					'instance' => $instance_id,
				),
				admin_url( 'tools.php' ),
			)
		);
		die();
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

	private function detect_cms( $site_url ) : string {
		$d      = new Whatcms_Detector( WHATCMS_API_KEY );
		$result = $d->run( $site_url );

		if ( $result->success ) {
			return $result->cms;
		}

		return '';
	}

	public function get_guide_url_for_cms( $cms ) : string {
		$cms = strtolower( $cms );

		if ( isset( $this->guides[ $cms ] ) ) {
			return $this->guides[ $cms ];
		}

		return '';
	}

	private function fetch_guide( $guide_url ) : string {
		$guide = new GuideSourcing\HTTPSourcer( $guide_url );
		return $guide->fetch();
	}
}
