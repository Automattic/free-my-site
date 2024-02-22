<?php

namespace DotOrg\FreeMySite\Admin;

class PluginManager {
	/**
	 * Function to get the clickable installation link to install a plugin based on its slug on WP.org plugins repo
	 *
	 * @param $plugin_slug
	 *
	 * @return string
	 */
	public function get_install_link( $plugin_slug ) : string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'install-plugin',
					'plugin' => $plugin_slug,
					'from'   => 'import',
				),
				self_admin_url( 'update.php' )
			),
			'install-plugin_' . $plugin_slug
		);
	}

	/**
	 * Function to check whether a plugin is installed by checking its slug (WP.org plugins repo) by checking for its dir
	 *
	 * This can be improved upon by actually checking whether that plugin is installed by not relying on dir name for
	 * the plugin, but for now, this is sufficient.
	 *
	 * @param $plugin_slug
	 *
	 * @return bool
	 */
	public function is_installed( $plugin_slug ) : bool {
		return file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug );
	}
}