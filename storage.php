<?php

namespace DotOrg\FreeMySite\Storage;

class Store {
	public function __construct() {
		add_action(
			'init', function () {
			register_post_type(
				'freemysite_instance', array(
					'labels'              => array(
						'name'          => __( 'Free My Site Import Instances' ),
						'singular_name' => __( 'Free My Site Import Instance' )
					),
					'public'              => false,
					'has_archive'         => false,
					'show_ui'             => true, // @TODO revert after development
					'exclude_from_search' => true,
					'supports'            => array( 'title', 'editor', 'custom-fields', 'comments' )
				)
			);
		}
		);
	}

	public static function new_instance( $site_url, $markdown_guide ) {
		$instance_id = self::generate_instance_id();
		$post_id     = wp_insert_post(
			array(
				'post_type'   => 'freemysite_instance',
				'post_status' => 'publish',
				'post_title'  => 'Instance ' . $instance_id,
				'post_content' => $markdown_guide
			)
		);

		if ( $post_id ) {
			update_post_meta( $post_id, 'instance_id', $instance_id );
			update_post_meta( $post_id, 'site_url', $site_url );
			return $instance_id;
		}

		return false;
	}

	// @TODO: Generate "unique" code with every run, so check for existing instance id to avoid accidental reuse
	private static function generate_instance_id() : string {
		$characters  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$instance_id = '';
		$length      = 15;
		for ( $i = 0; $i < $length; $i++ ) {
			$instance_id .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
		}
		return $instance_id;
	}

}

new Store();