<?php

namespace DotOrg\FreeMySite\UI;

use DotOrg\FreeMySite;
use DotOrg\FreeMySite\CMSDetection\Whatcms_Detector;
use DotOrg\FreeMySite\GuideSourcing;
use DotOrg\FreeMySite\Plugin\Manager;
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
		add_action( 'admin_init', array( $this, 'handle_instance_deletion' ) );
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
			<h1>Free My Site</h1>
			<?php do_action( 'admin_notices' ) ?>
			<?php if ( ! empty( $details ) ) { ?>
				<p>Let's follow the guide shown below:</p>
				<?php $this->display_steps( GuideSourcing\parse_guide( $details[ 'markdown_guide' ] ), $details ); ?>
			<?php } ?>
		</div>
		<?php
	}

	public function display_steps( $parsed_guide, $instance_details ) {
		?>
		<div id="free-my-site-accordion">
			<?php
			foreach ( $parsed_guide as $index => $section ) {
				if ( $section[ 'heading_level' ] === 2 ) {
					if ( strpos( $section[ 'heading_content' ], 'Step' ) === 0 ) {
						?>
						<div class="accordion-section">
							<div class="section-heading">
								<h3><?php echo esc_html( $section[ 'heading_content' ] ) ?></h3>
								<?php $this->present_assist_options( $section, $index, $parsed_guide, $instance_details ); ?>
							</div>
							<div class="section-content">
								<?php echo $this->markdown_parser->text( $section[ 'section_content' ] ); ?>
							</div>
						</div>
						<?php
					}
				}
			}
			?>
		</div>
		<script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                const sectionHeadings = document.querySelectorAll('#free-my-site-accordion .section-heading');
                sectionHeadings.forEach(function (sectionHeading) {
                    sectionHeading.addEventListener('click', function (event) {
                        // Ignore if its one of the assistance element that we offer
                        if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'A') {
                            sectionHeadings.forEach(function (sectionHeading) {
                                sectionHeading.nextElementSibling.style.display = 'none';
                            });
                            if (sectionHeading.nextElementSibling.style.display === 'block') {
                                sectionHeading.nextElementSibling.style.display = 'none';
                            } else {
                                sectionHeading.nextElementSibling.style.display = 'block';
                            }
                        }
                    });
                });

                // open the current step's accordion
                const urlsp = new URLSearchParams(window.location.search);
                const step = parseInt(urlsp.get('step')) || 1;
                document.querySelectorAll('#free-my-site-accordion .section-heading').forEach(function (element, index) {
                    if (index + 1 === step) {
                        element.dispatchEvent(new MouseEvent('click'));
                    }
                });
            });
		</script>
		<style>
            #free-my-site-accordion .accordion-section {
                margin: 5px 0;
                padding: 5px;
                border: solid 1px #CCCCCC;
            }

            #free-my-site-accordion .section-heading {
                display: flex;
                flex-direction: row;
                align-items: center;
                background: #CCC;
                padding: 7px 15px;
                cursor: pointer;
            }

            #free-my-site-accordion .section-heading h3 {
                flex: 1;
                margin-right: 10px;
            }

            #free-my-site-accordion .section-content {
                display: none;
            }

            #free-my-site-accordion img {
                max-width: 100%;
                border: solid 2px #CCCCCC;
            }
		</style>
		<?php
	}

	public function present_assist_options( $section, $index, $parsed_guide, $instance_details ) {
		$cms      = strtolower( $instance_details[ 'cms' ] );
		$site_url = $instance_details[ 'site_url' ];

		if ( strpos( $section[ 'heading_content' ], 'Export' ) ) {

			switch ( $cms ) {
				case 'wordpress':
					$export_page_link = $site_url . '/wp-admin/export.php';
					break;
				default:
					$export_page_link = '';
			}
			?>
			↗️ &nbsp;<a href="<?php echo esc_url( $export_page_link ) ?>" target="_blank">
				<strong><?php echo __( 'Visit Export page', 'free-my-site' ) ?></strong>
			</a>
			<?php

		} else if ( stripos( $section[ 'heading_content' ], 'Install' ) ) {

			// figure out which plugin
			if ( strpos( $section[ 'heading_content' ], 'WordPress Importer' ) ) {
				$plugin_slug = 'wordpress-importer';
			} else if ( $cms == 'tumblr' && stripos( $section[ 'heading_content' ], 'importer' ) ) {
				$plugin_slug = 'tumblr-importer';
			}

			if ( empty( $plugin_slug ) ) {
				return;
			}

			$plugin_manager = new Manager();
			if ( $plugin_manager->is_installed( $plugin_slug ) ) {
				?>✅ Already installed!<?php
			} else {
				$install_link = $plugin_manager->get_install_link( $plugin_slug );
				?>
				✨ &nbsp;<a target="_blank" href="<?php echo $install_link ?>"><strong>Install this plugin</strong></a>
				<?php
			}

		} else if ( false !== stripos( $section[ 'heading_content' ], 'Upload' ) ) {

			if ( false !== stripos( $section[ 'heading_content' ], 'WXR' ) ) {
				?>🔼 &nbsp;<a target="_blank" href="<?php echo admin_url( 'admin.php?import=wordpress' ) ?>"><strong>Upload
						WXR</strong></a><?php
			} else {
				?><a href="<?php echo admin_url( 'admin.php?import=wordpress' ) ?>"></a><?php
			}

		} else if ( false !== stripos( $section[ 'heading_content' ], 'Connect' ) ) {

			if ( false !== stripos( $section[ 'heading_content' ], 'Tumblr' ) ) {
				?>↔️ &nbsp;<a target="_blank" href="<?php echo admin_url( 'admin.php?import=tumblr' ) ?>"><strong>Connect
						To Tumblr</strong></a><?php
			}

		}
	}

	public function admin_page_default() {
		$whatcms_api_key_supplied = defined( 'WHATCMS_API_KEY' ) && ! empty( WHATCMS_API_KEY );
		?>
		<div class="wrap">
			<h1>Free My Site</h1>
			<?php //do_action( 'admin_notices' ); // admin notices showing up twice with this, need to figure out why ?>
			<br/><br/>
			<form method="POST" action="">
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<label for="site_url">Your Website URL</label>
						</th>
						<td>
							<input type="text" name="site_url" id="site_url" required value="<?php
							echo ! empty( $_REQUEST[ 'site_url' ] ) ? esc_url( $_REQUEST[ 'site_url' ] ) : ''; ?>">
							<p class="description" id="tagline-description">
								<?php echo __( 'Please enter URL for your website that you want to migrate.', 'free-my-site' ); ?>
							</p>
						</td>
					</tr>
					</tbody>
				</table>
				<?php wp_nonce_field( 'free_my_site_input', 'nonce' ); ?>
				<p class="submit">
					<input type="submit" name="submit_url" value="Submit" class="button button-primary" <?php
					echo $whatcms_api_key_supplied ? '' : 'disabled' ?>>
				</p>
			</form>
			<?php if ( Store::has_instances() ) { ?>
				<br><br><br>
				<h2>Resume previous attempts</h2>
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
					<tr>
						<th scope="col" id="instance_id" class="manage-column column-primary">Instance ID</th>
						<th scope="col" id="website" class="manage-column column-status">Website</th>
						<th scope="col" id="action" class="manage-column column-next_steps">Action</th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( Store::get_all_instances() as $instance ) { ?>
						<tr id="instance-<?php echo $instance[ 'id' ]; ?>">
							<td class="column-primary">
								<pre><?php echo $instance[ 'instance_id' ]; ?></pre>
							</td>
							<td class="column-status">
								<pre><?php echo $instance[ 'site_url' ] ?></pre>
							</td>
							<td class="column-action">
								<a href="<?php echo esc_attr( admin_url( 'tools.php?page=free-my-site-page&instance=' . $instance[ 'instance_id' ] ) ); ?>">
									<span class="button button-secondary">
										Resume
									</span>
								</a>
								<form action="" method="POST" style="display: inline-block;">
									<input type="hidden" name="instance_id_to_delete"
										   value="<?php echo $instance[ 'instance_id' ] ?>"/>
									<?php wp_nonce_field( 'free_my_site_instance_del', 'nonce' ); ?>
									<input type="submit" class="button button-link-delete" name="delete_instance"
										   value="Delete"/>
								</form>
							</td>
						</tr>
					<?php } ?>
					</tbody>
					<tfoot>
					<tr>
						<th scope="col" id="instance_id" class="manage-column column-primary">Instance ID</th>
						<th scope="col" id="website" class="manage-column column-status">Website</th>
						<th scope="col" id="action" class="manage-column column-next_steps">Action</th>
					</tr>
					</tfoot>
				</table>
			<?php } ?>
		</div>
		<?php
	}

	public function handle_form_submission() {
		global $pagenow;

		if ( $pagenow != 'tools.php' || ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] != self::ADMIN_PAGE_SLUG ) ) {
			return; // no business
		}

		if ( $_SERVER[ 'REQUEST_METHOD' ] !== 'POST' || ! isset( $_POST[ 'submit_url' ] ) ) {
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

	public function handle_instance_deletion() {
		global $pagenow;

		if ( $pagenow != 'tools.php' || ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] != self::ADMIN_PAGE_SLUG ) ) {
			return; // no business
		}

		if ( $_SERVER[ 'REQUEST_METHOD' ] !== 'POST' || ! isset( $_POST[ 'delete_instance' ] ) ) {
			return;
		}

		// nonce verification
		if ( ! isset( $_POST[ 'nonce' ] ) || ! wp_verify_nonce( $_POST[ 'nonce' ], 'free_my_site_instance_del' ) ) {
			$this->add_admin_notice(
				[
					'Sorry but it could not be verified that the request came from a legitimate action',
					'Please try again.'
				],
				'error'
			);
			return;
		}

		$instance_id = $_POST[ 'instance_id_to_delete' ];

		if ( ! Store::delete_instance_by_id( $instance_id ) ) {
			$this->add_admin_notice( 'Failed to delete instance.', 'error' );
			return;
		}

		// instance deleted, redirect now
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::ADMIN_PAGE_SLUG,
				),
				admin_url( 'tools.php' ),
			)
		);
		die();
	}
}
