<?php

namespace DotOrg\FreeMySite\Storage;

class Store {
	const POST_TYPE = 'freemysite_instance';

	public function __construct() {
		add_action(
			'init', function() {
			register_post_type(
				self::POST_TYPE, array(
					'labels'              => array(
						'name'          => __( 'Free My Site Import Instances' ),
						'singular_name' => __( 'Free My Site Import Instance' )
					),
					'public'              => false,
					'has_archive'         => false,
					'show_ui'             => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'exclude_from_search' => true,
					'supports'            => array( 'title', 'editor', 'custom-fields', 'comments' )
				)
			);
		}
		);
	}

	public static function new_instance( $site_url, $cms, $markdown_guide ) {
		$instance_id = self::generate_instance_id();
		$post_id     = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Instance ' . $instance_id,
				'post_content' => $markdown_guide
			)
		);

		if ( $post_id ) {
			update_post_meta( $post_id, 'instance_id', $instance_id );
			update_post_meta( $post_id, 'cms', $cms );
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

	public static function has_instances() : bool {
		return ! empty( self::get_all_instances() );
	}

	public static function get_all_instances() : array {
		$instances = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1
		) );

		$collect = [];
		foreach ( $instances as $instance ) {
			$collect[] = array(
				'id'          => $instance->ID,
				'instance_id' => get_post_meta( $instance->ID, 'instance_id', true ),
				'site_url'    => get_post_meta( $instance->ID, 'site_url', true ),
				'cms'         => get_post_meta( $instance->ID, 'cms', true )
			);
		}

		return $collect;
	}

	public static function get_instance_details( $instance_id ) : array {
		$post_id = self::get_post_id_by_instance_id( $instance_id );
		if ( false === $post_id ) {
			return [];
		}

		return [
			'cms'            => get_post_meta( $post_id, 'cms', true ),
			'site_url'       => get_post_meta( $post_id, 'site_url', true ),
			'markdown_guide' => get_post( $post_id )->post_content
		];
	}

	private static function get_post_id_by_instance_id( $instance_id ) {
		$posts = get_posts( array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'meta_query'  => array(
				array(
					'key'     => 'instance_id',
					'value'   => $instance_id,
					'compare' => '=',
				),
			),
			'fields'      => 'ids', // Return only post IDs
			'numberposts' => 1, // Limit the query to one post
		) );

		if ( empty( $posts ) ) {
			return false;
		}

		return $posts[ 0 ];
	}

	public static function delete_instance_by_id( $instance_id ) {
		$post_id = self::get_post_id_by_instance_id( $instance_id );
		if ( $post_id ) {
			if ( wp_delete_post( $post_id ) ) {
				return true;
			}
		}

		return false;
	}
}

new Store();