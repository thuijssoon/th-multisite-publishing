<?php
/**
 * Wordpress Multisite broadcast API
 *
 * Contains the TH_Multisite_Broadcast_API class. Requires WordPress version 3.5 or greater.
 *
 * @version   0.1.0
 * @package   TH Multisite Broadcast
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL 2.0+ - http://www.gnu.org/licenses/gpl.txt
 * @link      https://github.com/thuijssoon/
 * @copyright Copyright (c) 2013 - Thijs Huijssoon
 */

if ( !class_exists( 'TH_Multisite_Broadcast_API' ) ) {

	class TH_Multisite_Broadcast_API {

		// ======================================
		// Private variables
		// ======================================

		/**
		 * Cache of blog ids
		 *
		 * @var array | false
		 */
		private $blog_ids = false;

		private $options = null;

		private static $instance = null;


		// ======================================
		// Static methods
		// ======================================

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new TH_Multisite_Broadcast_API();
			}

			return self::$instance;
		}


		// ======================================
		// Constructor
		// ======================================

		private function __construct( $args = array() ) {
			$this->options = wp_parse_args(
				$args,
				$defaults = array(
					'copydate'                   => true,
					'meta_blacklist'             => array(),
					'copy_attachments'           => true,
					'copy_children'              => true,
					'taxonomies_blacklist'       => array(),
					'only_propagate_when_parent' => false,
					'only_propagate_from_site'   => false,
				)
			);

			// Include meta data in post broadcast data
			add_filter( 'th_mba_create_post_broadcast_data', array( $this, 'create_post_meta_broadcast_data' ), 10, 2 );

			// Include post taxonomies in post broadcast data
			add_filter( 'th_mba_create_post_broadcast_data', array( $this, 'create_post_taxonomies_broadcast_data' ), 10, 2 );

			// Include post children in post broadcast data
			add_filter( 'th_mba_create_post_broadcast_data', array( $this, 'create_post_children_broadcast_data' ), 10, 3 );

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			if ( is_plugin_active( 'taxonomy-metadata/taxonomy-metadata.php' ) ) {
				// Include meta data in term broadcast data
				add_filter( 'th_mba_create_term_broadcast_data', array( $this, 'create_term_meta_broadcast_data' ), 10, 2 );
			}

			// Check whether a term should be updated
			add_filter( 'th_mba_term_publish_status', array( $this, 'check_term_publish_status' ), 10, 2 );

			// Publish term meta data
			add_action( 'th_mba_publish_term_data_to_blog', array( $this, 'publish_term_meta_to_blog' ), 10, 2 );

			add_action( 'th_mba_post_publish_status', array( $this, 'check_post_publish_status' ), 10, 2 );

			// Database hooks
			add_action( 'init', array( $this, 'register_tables' ), 1 );
		}


		// ======================================
		// Public methods
		// ======================================

		/**
		 * Create an array containing all the post information
		 * of the source post to be broadcasted to the network.
		 * Heavily based on the duplicate_post_create_duplicate
		 * function in the Duplicate Post plugin.
		 *
		 * @param object  $post           The post object you want to broadcast.
		 * @param string  $status         The post status of the broadcasted post
		 * @param int     $linked_post_id The post id for the linked (parent) post if any
		 * @return array                   The broadcast data
		 */
		public function create_post_broadcast_data( $post, $status = '', $linked_post_id = false ) {
			// We don't want to clone revisions
			if ( $post->post_type == 'revision' ) return;

			// Assign posts to the current user
			$new_post_author = $this->get_current_user();

			$new_post = array(
				'post_author'         => $new_post_author->ID,
				'post_content'        => $post->post_content,
				'post_title'          => $post->post_title,
				'post_excerpt'        => $post->post_excerpt,
				'post_status'         => $new_post_status = ( empty( $status ) )? $post->post_status: $status,
				'comment_status'      => $post->comment_status,
				'ping_status'         => $post->ping_status,
				'post_password'       => $post->post_password,
				'post_name'           => $post->post_name,
				'post_parent'         => $post->post_parent,
				'menu_order'          => $post->menu_order,
				'guid'                => $post->guid,
				'post_type'           => $post->post_type,
				'post_mime_type'      => $post->post_mime_type,
				'publishing_group_id' => $this->get_publishing_group_id(
					array(
						'blog_id'     => get_current_blog_id(),
						'object_id'   => $post->ID,
						'object_type' => 'post',
						'object_name' => $post->post_type,
					)
				)
			);

			// Handle attachment data
			if ( 'attachment' === $post->post_type ) {
				$attachment_meta             = wp_get_attachment_metadata( $post->ID );
				$url = untrailingslashit( dirname( $post->guid ) );
				preg_match( "/[0-9]{4}\/[0-9]{2}$/", $url, $output_array );
				$time = isset( $output_array[0] ) ? $output_array[0] : 0;
				$new_post['attachment_meta'] = $attachment_meta;
				$upload_dir                  = wp_upload_dir( $time );
				$new_post['upload_dir']      = $upload_dir;
			}

			// if ( get_option( 'duplicate_post_copydate' ) == 1 ) {
			$new_post['post_date'] = $new_post_date =  $post->post_date ;
			$new_post['post_date_gmt'] = get_gmt_from_date( $new_post_date );
			// }

			$post_broadcast_data['post'] = $new_post;

			// Recursively call this function to get the post parent
			// if the post parent id !== post id and $is_child is false
			if ( $post->post_parent && $post->post_parent !== $post->ID && $post->post_parent !== $linked_post_id ) {
				$post_parent = get_post( absint( $post->post_parent ) );

				if ( !is_null( $post_parent ) ) {
					$post_broadcast_data['parent'] = $this->create_post_broadcast_data( $post_parent, '', $post->ID );
				}
			}

			// $new_post_id = wp_insert_post( $new_post );

			// If you have written a plugin which uses non-WP database tables to save
			// information about a post you can hook this action to dupe that data.
			// if ( $post->post_type == 'page' || ( function_exists( 'is_post_type_hierarchical' ) && is_post_type_hierarchical( $post->post_type ) ) )
			//  do_action( 'dp_duplicate_page', $new_post_id, $post );
			// else
			//  do_action( 'dp_duplicate_post', $new_post_id, $post );

			// delete_post_meta( $new_post_id, '_dp_original' );
			// add_post_meta( $new_post_id, '_dp_original', $post->ID );

			// If the copy is published or scheduled, we have to set a proper slug.
			// if ( $new_post_status == 'publish' || $new_post_status == 'future' ) {
			//  $post_name = wp_unique_post_slug( $post->post_name, $new_post_id, $new_post_status, $post->post_type, $new_post_parent );

			//  $new_post = array();
			//  $new_post['ID'] = $new_post_id;
			//  $new_post['post_name'] = $post_name;

			//  // Update the post into the database
			//  wp_update_post( $new_post );
			// }

			// Filter the returned data to include:
			// 1. meta data
			// 2. post children
			// 3. post taxonomies
			// and allow other plugins to make changes.
			$post_broadcast_data = apply_filters( 'th_mba_create_post_broadcast_data', $post_broadcast_data, $post->ID, $linked_post_id );
			return $post_broadcast_data;
		}

		/**
		 * Add post meta to the broadcast data.
		 * Heavily based on the duplicate_post_copy_post_meta_info
		 * function in the Duplicate Post plugin.
		 *
		 * @param array   $post    the post broadcast data
		 * @param int     $post_id the post id
		 * @return array            the post broadcast data
		 */
		public function create_post_meta_broadcast_data( $post_broadcast_data, $post_id ) {
			$post_meta_keys = get_post_custom_keys( $post_id );
			if ( empty( $post_meta_keys ) ) {
				return $post_broadcast_data;
			}
			// $meta_blacklist = explode( ",", get_option( 'duplicate_post_blacklist' ) );
			// if ( $meta_blacklist == "" ) $meta_blacklist = array();
			// $meta_keys = array_diff( $post_meta_keys, $meta_blacklist );

			$post_meta = array();
			foreach ( $post_meta_keys as $meta_key ) {
				$meta_values = get_post_custom_values( $meta_key, $post_id );
				foreach ( $meta_values as $meta_value ) {
					$post_meta[$meta_key] = maybe_unserialize( $meta_value );
				}
			}

			$post_broadcast_data['meta'] = apply_filters( 'th_mba_create_post_meta_broadcast_data', $post_meta, $post_id );
			return $post_broadcast_data;
		}

		/**
		 * Add post children to the broadcast data.
		 * Heavily based on the duplicate_post_copy_post_meta_info
		 * function in the Duplicate Post plugin.
		 *
		 * @param array   $post    the post broadcast data
		 * @param int     $post_id the post id
		 * @return array            the post broadcast data
		 */
		public function create_post_children_broadcast_data( $post_broadcast_data, $post_id, $linked_post_id = false ) {
			// $copy_attachments = get_option( 'duplicate_post_copyattachments' );
			// $copy_children = get_option( 'duplicate_post_copychildren' );

			$children_args = array( 'post_type' => 'any', 'numberposts' => -1, 'post_status' => 'any', 'post_parent' => $post_id );
			if ( $linked_post_id ) {
				$children_args['exclude'] = $linked_post_id;
			}

			$children = get_posts( $children_args );

			$children_broadcast_data = array();

			// Get attachment data
			foreach ( $children as $child ) {
				// if ( $copy_attachments == 0 && $child->post_type == 'attachment' ) continue;
				// if ( $copy_children == 0 && $child->post_type != 'attachment' ) continue;
				$children_broadcast_data[] = $this->create_post_broadcast_data( $child, '', $post_id );
			}

			$post_broadcast_data['children'] = apply_filters( 'th_mba_create_post_children_broadcast_data', $children_broadcast_data );

			return $post_broadcast_data;
		}

		/**
		 * Add post taxonomy terms to the broadcast data.
		 * Heavily based on the duplicate_post_copy_post_taxonomies
		 * function in the Duplicate Post plugin.
		 *
		 * @param array   $post    the post broadcast data
		 * @param int     $post_id the post id
		 * @return array            the post broadcast data
		 */
		public function create_post_taxonomies_broadcast_data( $post_broadcast_data, $post_id ) {
			global $wpdb;

			if ( isset( $wpdb->terms ) ) {
				$post_taxonomies = get_object_taxonomies( $post_broadcast_data['post']['post_type'] );

				// $taxonomies_blacklist = get_option( 'duplicate_post_taxonomies_blacklist' );
				// if ( $taxonomies_blacklist == "" ) $taxonomies_blacklist = array();
				// $taxonomies = array_diff( $post_taxonomies, $taxonomies_blacklist );

				$term_broadcast_data = array();

				foreach ( $post_taxonomies as $taxonomy ) {
					$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'orderby' => 'term_order' ) );
					foreach ( $post_terms as $post_term ) {
						$term_broadcast_data[] = $this->create_term_broadcast_data( $post_term );
					}
				}

				$post_broadcast_data['terms'] = $term_broadcast_data;
			}

			return $post_broadcast_data;
		}

		/**
		 * Create an array containing all the term information
		 * of the source term to be broadcasted to the network.
		 * Heavily based on the duplicate_post_create_duplicate
		 * function in the Duplicate Post plugin.
		 *
		 * @param object  $term The term object you want to broadcast.
		 * @return array             The broadcast data
		 */
		public function create_term_broadcast_data( $term, $synchronized = true ) {
			$new_term = array(
				'term_id'             => $term->term_id,
				'name'                => $term->name,
				'taxonomy'            => $term->taxonomy,
				'slug'                => $term->slug,
				'description'         => $term->description,
				'blog_id'             => get_current_blog_id(),
				'publishing_group_id' => $this->get_publishing_group_id(
					array(
						'blog_id'      => get_current_blog_id(),
						'object_id'    => $term->term_id,
						'object_type'  => 'term',
						'object_name'  => $term->taxonomy,
						'synchronized' => $synchronized,
					)
				)
			);
			$term_broadcast_data['term'] = $new_term;

			// Recursively call this function to get the post parent
			// if the post parent id !== post id
			if ( $term->parent && $term->parent !== $term->term_id ) {
				$term_parent = get_term( absint( $term->parent ), $term->taxonomy );

				if ( !is_null( $term_parent ) && !is_wp_error( $term_parent ) ) {
					$term_broadcast_data['parent'] = $this->create_term_broadcast_data( $term_parent, $synchronized );
				}
			}

			return apply_filters( 'th_mba_create_term_broadcast_data', $term_broadcast_data, $term );
		}

		/**
		 * Add term meta to the broadcast data.
		 * Heavily based on the duplicate_post_copy_post_meta_info
		 * function in the Duplicate Post plugin.
		 *
		 * @param array   $term_broadcast_data the term broadcast data
		 * @param int     $term_id             the term id
		 * @return array                        the term broadcast data
		 */
		public function create_term_meta_broadcast_data( $term_broadcast_data, $term ) {
			$term_meta_keys = get_term_custom_keys( $term->term_id );
			if ( empty( $term_meta_keys ) ) {
				$term_broadcast_data['meta'] = array();
				return $term_broadcast_data;
			}
			// $meta_blacklist = explode( ",", get_option( 'duplicate_term_blacklist' ) );
			// if ( $meta_blacklist == "" ) $meta_blacklist = array();
			// $meta_keys = array_diff( $term_meta_keys, $meta_blacklist );

			$term_meta = array();
			foreach ( $term_meta_keys as $meta_key ) {
				$meta_values = get_term_custom_values( $meta_key, $term->term_id );
				foreach ( $meta_values as $meta_value ) {
					$term_meta[$meta_key] = maybe_unserialize( $meta_value );
				}
			}

			$term_broadcast_data['meta'] = apply_filters( 'th_mba_create_term_meta_broadcast_data', $term_meta, $term );
			return $term_broadcast_data;
		}

		// ======================================
		// Publishing methods
		// ======================================

		public function publish_terms( array $terms, array $taxonomy_mappings, $synchronize = true ) {
			if ( class_exists( 'TH_Term_Meta' ) ) {
				TH_Term_Meta::suppress_hooks( true );
			}
			if ( class_exists( 'WPTP_Error' ) ) {
				WPTP_Error::suppress_hooks( true );
			}

			// 1. Create term broadcast data
			unset( $taxonomy_mappings[get_current_blog_id()] );
			$term_broadcast_data = array();
			foreach ( $terms as $term ) {
				$term_broadcast_data[$term->term_id] = $this->create_term_broadcast_data( $term, $synchronize );
			}

			// 2. Loop through the blogs
			foreach ( $taxonomy_mappings as $blog_id => $taxonomies ) {
				// 3. Switch to blog
				switch_to_blog( $blog_id );
				$updated_taxonomies = array();

				// 4. Loop through the term_data
				foreach ( $term_broadcast_data as $parent_term_id => $term_data ) {
					// 5. Publish the term_data if linked
					if ( in_array( $term_data['term']['taxonomy'], $taxonomies ) ) {
						$updated_taxonomies[] = $term_data['term']['taxonomy'];
						$term_data['term']['synchronized'] = $synchronize;
						$this->publish_term_data_to_blog( $term_data );
					}
				}

				// 6. Update taxonomy children option
				$updated_taxonomies = array_unique( $updated_taxonomies );
				foreach ( $updated_taxonomies as $tax ) {
					delete_option( $tax . '_children' );
				}
			}

			// 7. Restore current blog
			restore_current_blog();

			if ( class_exists( 'TH_Term_Meta' ) ) {
				TH_Term_Meta::suppress_hooks( false );
			}
			if ( class_exists( 'WPTP_Error' ) ) {
				WPTP_Error::suppress_hooks( false );
			}
		}

		public function update_term( $term, $synchronize = true ) {
			if ( class_exists( 'TH_Term_Meta' ) ) {
				TH_Term_Meta::suppress_hooks( true );
			}
			if ( class_exists( 'WPTP_Error' ) ) {
				WPTP_Error::suppress_hooks( true );
			}

			// 1. Get term broadcast data
			$term_broadcast_data = $this->create_term_broadcast_data( $term, $synchronize );

			// 2. Get all terms in the same group
			$publishings = $this->get_publishings(
				array(
					'object_type'  => 'term',
					'group_id'     => $term_broadcast_data['term']['publishing_group_id'],
					'synchronized' => 1,
				)
			);

			foreach ( $publishings as $publishing ) {
				// 3. Switch to blog
				switch_to_blog( $publishing->blog_id );

				// 4. Publish the term_data if linked
				$this->publish_term_data_to_blog( $term_broadcast_data );
			}

			// 5. Deleta term children option
			delete_option( $term_broadcast_data['term']['taxonomy'] . '_children' );

			// 6. Restore current blog
			restore_current_blog();

			if ( class_exists( 'TH_Term_Meta' ) ) {
				TH_Term_Meta::suppress_hooks( false );
			}
			if ( class_exists( 'WPTP_Error' ) ) {
				WPTP_Error::suppress_hooks( false );
			}
		}

		public function delete_term( $term ) {
			if ( class_exists( 'TH_Term_Meta' ) ) {
				TH_Term_Meta::suppress_hooks( true );
			}
			if ( class_exists( 'WPTP_Error' ) ) {
				WPTP_Error::suppress_hooks( true );
			}

			$group_id    = $this->get_publishing_group_id(
				array(
					'object_id'    => $term->term_id,
					'blog_id'      => get_current_blog_id(),
					'object_type'  => 'term',
					'insert_group' => false
				)
			);

			if ( ! $group_id ) {
				return;
			}

			$publishings  = $this->get_publishings(
				array(
					'object_type'  => 'term',
					'group_id'     => $group_id,
					'synchronized' => 1,
				)
			);
			$current_blog = get_current_blog_id();

			foreach ( $publishings as $publishing ) {
				switch_to_blog( $publishing->blog_id );
				wp_delete_term( $publishing->object_id, $term->taxonomy );
				$this->delete_publishings(
					array(
						'object_id'    => $publishing->object_id,
						'blog_id'      => $publishing->blog_id,
						'object_type'  => 'term',
					)
				);
			}

			$this->delete_publishing_group(
				array(
					'group_id' => $group_id
				)
			);

			switch_to_blog( $current_blog );

			if ( class_exists( 'TH_Term_Meta' ) ) {
				TH_Term_Meta::suppress_hooks( false );
			}
			if ( class_exists( 'WPTP_Error' ) ) {
				WPTP_Error::suppress_hooks( false );
			}
		}

		/**
		 * Insert a new term based on the provided term broadcast data.
		 * Will also insert parent terms if a term with the given name
		 * does not exist.
		 *
		 * @todo   don't update parent terms if they exist
		 *
		 * @param array   $term_broadcast_data the term data
		 * @return array                       array( 'term_id' => ..., 'term_taxonomy_id' => ... );
		 */
		public function publish_term_data_to_blog( $term_broadcast_data ) {
			/*
			 * 1. Check if term should be broadcasted:
			 *    Three options: Create, Update, Ignore.
			 * 2. Check if the term has a parent and if so goto step 1.
			 * 3. Insert the term data.
			 * 4. Insert term meta. (do_action)
			 */

			// 1. Check if term should be broadcasted:
			$term_id             = array( 'term_id'=> -1, 'term_taxonomy_id'=> -1 );
			$term_publish_status = apply_filters( 'th_mba_term_publish_status', array( 'action' => 'ignore' ), $term_broadcast_data['term'] );

			if ( 'ignore' !== $term_publish_status['action'] ) {
				$term_data = apply_filters( 'th_mba_term_publish_data', $term_broadcast_data['term'] );
				$parent_id = '0';

				// 2. Check if the term has a parent and if so publish it
				if ( isset( $term_broadcast_data['parent'] ) ) {
					$parent = $this->publish_term_data_to_blog( $term_broadcast_data['parent'] );
					$parent_id = $parent['term_id'];
				}

				$args      = array(
					'slug'        => $term_data['slug'],
					'description' => $term_data['description'],
					'parent'      => $parent_id
				);

				// 3. Insert the term data.
				if ( 'create' === $term_publish_status['action'] ) {
					$term_id = wp_insert_term( $term_data['name'], $term_data['taxonomy'], $args );
					// 3.1 Insert publishing data
					$this->insert_publising(
						array(
							'blog_id'       => get_current_blog_id(),
							'object_id'     => $term_id['term_id'],
							'object_type'   => 'term',
							'group_id'      => $term_data['publishing_group_id'],
							'parent'        => 0,
							'synchronized'  => 1,
						)
					);
				} elseif ( 'update' === $term_publish_status['action'] ) {
					$args['name'] = $term_data['name'];
					$term_id      = wp_update_term( $term_publish_status['term_id'], $term_data['taxonomy'], $args );
				}

				$term = get_term( $term_id['term_id'], $term_data['taxonomy'] );

				// 4. Insert term meta. (do_action)
				do_action( 'th_mba_publish_term_data_to_blog', $term_broadcast_data, $term );
			}

			return $term_id;
		}

		/**
		 * Publish the term meta data. This function had a dependency on
		 * the taxonomy meta plugin.
		 *
		 * @param array   $term_broadcast_data the term data
		 * @param array   $term_id             array( 'term_id' => ..., 'term_taxonomy_id' => ... );
		 */
		public function publish_term_meta_to_blog( $term_broadcast_data, $term ) {
			// Allow meta filtering to update attachment IDs
			$term_meta = apply_filters( 'th_mba_term_publish_meta', $term_broadcast_data['meta'], $term );

			foreach ( $term_meta as $meta_key => $meta_value ) {
				update_term_meta( $term->term_id, $meta_key, $meta_value );
			}
		}

		/**
		 * Check whether a term should be published.
		 *
		 * @param array   $term_publish_status should we publish this term
		 * @param array   $term_data           the data for this term
		 * @return array                       array containing action (update|create|ignore) and term_id
		 */
		public function check_term_publish_status( $term_publish_status, $term_data ) {
			// Ignore if taxonomy does not exist
			if ( !taxonomy_exists( $term_data['taxonomy'] ) ) {
				return array( 'action' => 'ignore', 'term_id' => -1 );
			}

			// Check if publishing exists
			$publishing_data = $this->get_publishings(
				array(
					'group_id'    => $term_data['publishing_group_id'],
					'blog_id'     => get_current_blog_id(),
					'object_type' => 'term'
				)
			);
			if ( $publishing_data ) {
				$publishing_data = $publishing_data[0];
				$object_id = $publishing_data->object_id;
				if ( $publishing_data->synchronized ) {
					return array( 'action' => 'update', 'term_id' => $object_id );
				} else {
					return array( 'action' => 'ignore', 'term_id' => $object_id );
				}
			}

			// Check if term with the same name exists
			if ( $term_info = term_exists( $term_data['name'], $term_data['taxonomy'] ) ) {
				// Connect the term by inserting a publishing
				$args = array(
					'blog_id'       => get_current_blog_id(),
					'object_id'     => $term_info['term_id'],
					'object_type'   => 'term',
					'group_id'      => $term_data['publishing_group_id'],
					'synchronized'  => isset( $term_data['term']['synchronized'] ) ? $term_data['synchronized'] : false,
				);
				$this->insert_publising( $args );
				return array( 'action' => 'update', 'term_id' => $term_info['term_id'] );
			} else {
				return array( 'action' => 'create', 'term_id' => -1 );
			}
		}

		/**
		 * Insert post  into the current blog based on provided
		 * post data.
		 *
		 * @param array   $post_broadcast_data the post data
		 * @return int|false                   the post id or false if the post type does not exist.
		 */
		public function publish_post_data_to_blog( array $post_broadcast_data ) {
			$post_data = apply_filters( 'th_mba_post_publish_data', $post_broadcast_data['post'] );

			$post_id             = false;
			$post_publish_status = apply_filters( 'th_mba_post_publish_status', array( 'action' => 'ignore' ), $post_data );

			if ( 'ignore' !== $post_publish_status['action'] ) {
				if ( 'create' === $post_publish_status['action'] ) {
					if ( 'attachment' === $post_data['post_type'] ) {
						$post_id = $this->publish_attachment_data_to_blog( $post_broadcast_data );
					} else {
						$post_id = wp_insert_post( $post_data, false );
					}
					do_action( 'th_mba_published_post_to_blog', $post_broadcast_data, $post_id );
				} elseif ( 'update' === $post_publish_status['action'] ) {
					$post_data['ID'] = $post_publish_status['post_id'];
					$post_id  = wp_update_post( $post_data );
					do_action( 'th_mba_updated_post_to_blog', $post_broadcast_data, $post_id );
				}
			}

			return $post_id;
		}

		/**
		 * Filter the post data before it is inserted to remove unwanted
		 * attributes.
		 *
		 * @param array   $post_data the unfiltered post data
		 * @return array             the filtered post data
		 */
		public function filter_post_publish_data( array $post_data ) {
			unset( $post_data['guid'] );
			unset( $post_data['post_parent'] );
			unset( $post_data['post_name'] );
			unset( $post_data['upload_dir'] );
			unset( $post_data['attachment_meta'] );

			return $post_data;
		}

		/**
		 * Check whether a post should be published.
		 *
		 * @param array   $post_publish_status should we publish this post
		 * @param array   $post_data           the data for this post
		 * @return array                       array containing action (update|create|ignore) and post_id
		 */
		public function check_post_publish_status( $post_publish_status, $post_data ) {
			if ( !post_type_exists( $post_data['post_type'] ) ) {
				return array( 'action' => 'ignore', 'post_id' => -1 );
			}

			// Check if publishing exists
			$publishing_data = $this->get_publishings(
				array(
					'group_id' => $post_data['publishing_group_id'],
					'blog_id'  => get_current_blog_id()
				)
			);
			if ( $publishing_data ) {
				$object_id = $publishing_data[0];
				$object_id = $object_id->object_id;
				return array( 'action' => 'update', 'post_id' => $object_id );
			}

			// Check if term with the same name exists
			if ( $post_id = post_exists( $post_data['post_title'], '', $post_data['post_date'] ) ) {
				// Connect the term by inserting a publishing
				$args = array(
					'blog_id'       => get_current_blog_id(),
					'object_id'     => $post_id,
					'object_type'   => 'post',
					'group_id'      => $post_data['publishing_group_id'],
				);
				$this->insert_publising( $args );
				return array( 'action' => 'update', 'post_id' => $post_id );
			} else {
				return array( 'action' => 'create', 'post_id' => -1 );
			}
		}

		/**
		 * Insert an attachment into the current blog based on provided
		 * attachment data.
		 *
		 * @todo  do more checking on inserting when copying files.
		 *
		 * @param array   $attachment_broadcast_data the attachment data
		 * @return int|false                         the attachment id or false if the post type does not exist or the attachment is ignored.
		 */
		public function publish_attachment_data_to_blog( array $attachment_broadcast_data, $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'insert_only' => false,
				)
			);

			$post_data = apply_filters( 'th_mba_post_publish_data', $attachment_broadcast_data['post'] );

			$attach_id           = false;
			$post_publish_status = apply_filters( 'th_mba_post_publish_status', array( 'action' => 'ignore' ), $post_data );

			if ( 'ignore' !== $post_publish_status['action'] ) {
				if ( 'create' === $post_publish_status['action'] ) {

					// construct file name
					$source_file_name  = trailingslashit( $attachment_broadcast_data['post']['upload_dir']['basedir'] );
					$source_file_name .= $attachment_broadcast_data['meta']['_wp_attached_file'];

					// check if the file exists
					if ( !file_exists( $source_file_name ) ) {
						return false;
					}

					// copy the file to the new location
					$wp_upload_dir    = wp_upload_dir( ltrim( $attachment_broadcast_data['post']['upload_dir']['subdir'], '/' ) );
					$target_file_name = $wp_upload_dir['path'] . DIRECTORY_SEPARATOR . basename( $attachment_broadcast_data['meta']['_wp_attached_file'] );
					$target_file_url  = $wp_upload_dir['url'] . '/' . $attachment_broadcast_data['meta']['_wp_attached_file'];
					copy( $source_file_name, $target_file_name );

					// more or less a direct copy of:
					// http://codex.wordpress.org/Function_Reference/wp_insert_attachment#Example
					$wp_filetype = wp_check_filetype( basename( $target_file_name ), null );
					$attachment = $post_data;
					$attachment['guid']           = $target_file_url;
					$attachment['post_mime_type'] = $wp_filetype['type'];

					$attach_id = wp_insert_attachment( $attachment, $target_file_name );
					// you must first include the image.php file
					// for the function wp_generate_attachment_metadata() to work
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$attach_data = wp_generate_attachment_metadata( $attach_id, $target_file_name );
					wp_update_attachment_metadata( $attach_id, $attach_data );

				} elseif ( 'update' === $post_publish_status['action'] ) {
					if ( $args['insert_only'] ) {
						$attach_id = $post_publish_status['post_id'];
					} else {
						$post_data['ID'] = $post_publish_status['post_id'];
						$post_id  = wp_update_post( $post_data );
					}
				}
			} else {
				// Ignored
			}

			return $attach_id;
		}

		// ======================================
		// Database function
		// ======================================

		/**
		 * Store our table name in $wpdb with correct prefix.
		 */
		public function register_tables() {
			global $wpdb;
			$wpdb->th_msp_publishing       = $wpdb->base_prefix . 'th_msp_publishing';
			$wpdb->th_msp_publishing_group = $wpdb->base_prefix . 'th_msp_publishing_group';
		}

		/**
		 * Creates our table
		 * Hooked onto activate_[plugin] (via register_activation_hook)
		 *
		 * @since 1.0
		 */
		public function create_table() {
			global $wpdb;
			global $charset_collate;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			//Call this manually as we may have missed the init hook
			$this->register_tables();

			$sql_create_table_publishing = "CREATE TABLE IF NOT EXISTS {$wpdb->th_msp_publishing} (
				publishing_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				blog_id bigint(20) unsigned NOT NULL,
				object_id bigint(20) unsigned NOT NULL,
				object_type varchar(20) NOT NULL DEFAULT 'post',
				group_id bigint(20) unsigned NOT NULL,
				parent tinyint(1) unsigned NOT NULL DEFAULT '0',
				synchronized tinyint(1) unsigned NOT NULL DEFAULT '0',
				PRIMARY KEY (publishing_id),
				KEY blog_object_type (blog_id,object_id,object_type)
			) $charset_collate; ";

			$sql_create_table_publishing_group = "CREATE TABLE IF NOT EXISTS {$wpdb->th_msp_publishing_group} (
				group_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				group_name varchar(20) NOT NULL,
				PRIMARY KEY (group_id)
			) $charset_collate; ";

			dbDelta( $sql_create_table_publishing );
			dbDelta( $sql_create_table_publishing_group );
		}

		public function is_publishing_synchronized( $data = array() ) {
			global $wpdb;

			//Set default values
			$supported_object_types = array( 'post', 'term' );
			$data = wp_parse_args(
				$data,
				array(
					'blog_id'       => 0,
					'object_id'     => 0,
					'object_type'   => 'post',
				)
			);

			//Check object_type validity
			if ( !in_array( $data['object_type'], $supported_object_types ) ) {
				return false;
			}

			if (
				0 === $data['object_id'] ||
				0 === $data['blog_id']
			) {
				return false;
			}

			$publishing = $this->get_publishings( $data );

			if ( ! $publishing ) {
				return false;
			}

			$publishing = reset( $publishing );
			return $publishing->synchronized ? true : false;
		}

		public function set_publishing_synchronized( $data = array(), $synchronized = true ) {
			global $wpdb;

			//Set default values
			$supported_object_types = array( 'post', 'term' );
			$data = wp_parse_args(
				$data,
				array(
					'blog_id'       => 0,
					'object_id'     => 0,
					'object_type'   => 'post',
				)
			);

			//Check object_type validity
			if ( !in_array( $data['object_type'], $supported_object_types ) ) {
				return false;
			}

			if (
				0 === $data['object_id'] ||
				0 === $data['blog_id']
			) {
				return false;
			}

			$publishing = $this->get_publishings( $data );

			if ( ! $publishing ) {
				return false;
			}

			$publishing = reset( $publishing );

			return $this->update_publishing( $publishing->publishing_id, array( 'synchronized' => $synchronized ) );
		}

		private function get_publishing_table_columns() {
			return array(
				'publishing_id' => '%d',
				'blog_id'       => '%d',
				'object_id'     => '%d',
				'object_type'   => '%s',
				'group_id'      => '%d',
				'parent'        => '%d',
				'synchronized'  => '%d',
			);
		}

		private function get_publishing_group_table_columns() {
			return array(
				'group_id'   => '%d',
				'group_name' => '%s',
			);
		}

		/**
		 * Inserts a publising into the database
		 *
		 * @param unknown $data array An array of key => value pairs to be inserted
		 * @return int The publishing ID of the created publishing. Or WP_Error or false on failure.
		 */
		private function insert_publising( $data = array() ) {
			global $wpdb;

			//Set default values
			$supported_object_types = array( 'post', 'term' );
			$data = wp_parse_args(
				$data,
				array(
					'blog_id'       => 0,
					'object_id'     => 0,
					'object_type'   => 'post',
					'group_id'      => 0,
					'parent'        => 0,
					'synchronized'  => 0,
				)
			);

			//Check object_type validity
			if ( !in_array( $data['object_type'], $supported_object_types ) ) {
				return 0;
			}

			//Initialise column format array
			$column_formats = $this->get_publishing_table_columns();

			//Force fields to lower case
			$data = array_change_key_case( $data );

			//White list columns
			$data = array_intersect_key( $data, $column_formats );

			//Reorder $column_formats to match the order of columns given in $data
			$data_keys = array_keys( $data );
			$column_formats = array_merge( array_flip( $data_keys ), $column_formats );
			$wpdb->insert( $wpdb->th_msp_publishing, $data, $column_formats );

			return $wpdb->insert_id;
		}

		/**
		 * Retrieves publishings from the database matching $query.
		 * $query is an array which can contain the following keys:
		 *
		 * 'object_id'     - object ID to match
		 * 'blog_id'       - blog ID to match
		 * 'object_type'   - object type to match
		 * 'group_id'      - the publishing group id
		 * 'parent'        - whether this is a base object
		 * 'synchronized'  - whether this object is synchronized
		 *
		 * @param unknown $query Query array
		 * @return array Array of matching publishings. False on error.
		 */
		private function get_publishings( $query = array() ) {
			global $wpdb;

			// Parse defaults
			$defaults = array(
				'object_id'    => 0,
				'blog_id'      => 0,
				'object_type'  => 'post',
				'group_id'     => 0,
				'parent'       => -1,
				'synchronized' => -1,
			);
			$query = wp_parse_args( $query, $defaults );

			// Sanitize values
			$supported_object_types  = array( 'post', 'term' );
			if ( ! in_array( $query['object_type'], $supported_object_types ) ) {
				return false;
			}

			if (
				( 0 === $query['object_id'] || 0 === $query['blog_id'] ) &&
				0 === $query['group_id']
			) {
				return false;
			}

			// Form a cache key from the query
			$cache_key = 'th_mba_publishings:'.md5( serialize( $query ) );
			$cache     = wp_cache_get( $cache_key );
			// if ( false !== $cache ) {
			//  $cache = apply_filters( 'th_mba_get_publishings', $cache, $query );
			//  return $cache;
			// }
			extract( $query );

			// SQL Select
			$select_sql = "SELECT* FROM {$wpdb->th_msp_publishing}";

			// SQL Where
			$where_sql = 'WHERE 1=1';

			// SQL Where object_id
			if ( 0 !== $object_id ) {
				$where_sql .= $wpdb->prepare( ' AND object_id=%d', $object_id );
			}

			// SQL Where blog_id
			if ( 0 !== $blog_id ) {
				$where_sql .= $wpdb->prepare( ' AND blog_id=%d', $blog_id );
			}

			// SQL Where group_id
			if ( 0 !== $group_id ) {
				$where_sql .= $wpdb->prepare( ' AND group_id=%d', $group_id );
			}

			// SQL Where object_type
			$where_sql .= $wpdb->prepare( ' AND object_type=%s', $object_type );

			// SQL Where parent
			if ( -1 !== $parent ) {
				$parent = ( 0 < $parent ) ? 0 : 1;
				$where_sql .= $wpdb->prepare( ' AND parent=%d', $parent );
			}

			// SQL Where synchronized
			if ( -1 !== $synchronized ) {
				$synchronized = ( 0 < $synchronized ) ? 1 : 0;
				$where_sql .= $wpdb->prepare( ' AND synchronized=%d', $synchronized );
			}

			// Form SQL statement
			$sql = "$select_sql $where_sql";

			// Perform query
			$publishings = $wpdb->get_results( $sql );

			// Add to cache and filter
			wp_cache_add( $cache_key, $publishings, 24*60*60 );
			$publishings = apply_filters( 'th_mba_get_publishings', $publishings, $query );

			return $publishings;
		}

		/**
		 * Updates a publishing with supplied data
		 *
		 * @param unknown $publishing_id int ID of the publishing to be updated
		 * @param unknown $data   array An array of column=>value pairs to be updated
		 * @return bool Whether the publishing was successfully updated.
		 */
		private function update_publishing( $publishing_id, $data = array() ) {
			global $wpdb;

			//Log ID must be positive integer
			$publishing_id = absint( $publishing_id );
			if ( empty( $publishing_id ) )
				return false;

			//Initialise column format array
			$column_formats = $this->get_publishing_table_columns();

			//Force fields to lower case
			$data = array_change_key_case( $data );

			//White list columns
			$data = array_intersect_key( $data, $column_formats );

			//Reorder $column_formats to match the order of columns given in $data
			$data_keys = array_keys( $data );
			$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

			if ( false === $wpdb->update( $wpdb->th_msp_publishing, $data, array( 'publishing_id' => $publishing_id ), $column_formats ) ) {
				return false;
			}

			return true;
		}


		private function delete_publishings( $query = array() ) {
			global $wpdb;

			// Parse defaults
			$defaults = array(
				'object_id'    => 0,
				'blog_id'      => 0,
				'object_type'  => 'post',
				'group_id'     => 0,
			);
			$query = wp_parse_args( $query, $defaults );

			// Sanitize values
			$supported_object_types  = array( 'post', 'term' );
			if ( ! in_array( $query['object_type'], $supported_object_types ) ) {
				return false;
			}

			if (
				( 0 === $query['object_id'] || 0 === $query['blog_id'] ) &&
				0 === $query['group_id']
			) {
				return false;
			}

			extract( $query );

			do_action( 'th_mba_delete_publishings', $object_id, $blog_id, $object_type );

			// SQL Delete
			$delete_sql = "DELETE from {$wpdb->th_msp_publishing}";

			// SQL Where
			$where_sql = 'WHERE 1=1';

			// SQL Where object_id && blog_id
			if ( 0 !== $object_id ) {
				$where_sql .= $wpdb->prepare( ' AND object_id=%d AND blog_id=%d', $object_id, $blog_id );
			}

			// SQL Where group_id
			if ( 0 !== $group_id ) {
				$where_sql .= $wpdb->prepare( ' AND group_id=%d', $group_id );
			}

			// SQL Where object_type
			$where_sql .= $wpdb->prepare( ' AND object_type=%s', $object_type );

			// Form SQL statement
			$sql = "$delete_sql $where_sql";

			if ( !$wpdb->query( $sql ) )
				return false;

			do_action( 'th_mba_deleted_publishings', $object_id, $blog_id, $object_type );

			return true;
		}

		private function get_publishing_group_id( $query = array() ) {
			global $wpdb;

			// Parse defaults
			$defaults = array(
				'blog_id'      => 0,
				'object_id'    => 0,
				'object_type'  => 'post',
				'object_name'  => 0,
				'synchronized' => 0,
				'insert_group' => true,
			);
			$query = wp_parse_args( $query, $defaults );

			// Sanitize values
			$supported_object_types  = array( 'post', 'term' );
			if ( ! in_array( $query['object_type'], $supported_object_types ) ) {
				return false;
			}

			if (
				0 === $query['object_id'] || 0 === $query['blog_id']
			) {
				return false;
			}

			// Form a cache key from the query
			// $cache_key = 'th_mba_publishing_group_id:'.md5( serialize( $query ) );
			// $cache     = wp_cache_get( $cache_key );
			// if ( false !== $cache ) {
			//  return $cache;
			// }

			extract( $query );

			// SQL Select
			$select_sql = $wpdb->prepare(
				"SELECT
					*
				FROM
					{$wpdb->th_msp_publishing}
				WHERE
					object_id=%d AND blog_id=%d AND object_type=%s",
				$object_id, $blog_id, $object_type
			);

			$row = $wpdb->get_row( $select_sql );

			if ( ! is_null( $row ) ) {
				// Add to cache and filter
				// wp_cache_add( $cache_key, $row->group_id, 24*60*60 );
				return $row->group_id;
			}

			if ( 0 === $object_name ) {
				return false;
			}

			// Don't proceed if we shouldn't insert a new group if it doesn't exist
			if ( ! $insert_group ) {
				return false;
			}

			//Initialise column format array
			$column_formats = $this->get_publishing_group_table_columns();

			$data = array( 'group_name' => $object_name );

			//Force fields to lower case
			$data = array_change_key_case( $data );

			//White list columns
			$data = array_intersect_key( $data, $column_formats );

			//Reorder $column_formats to match the order of columns given in $data
			$data_keys = array_keys( $data );
			$column_formats = array_merge( array_flip( $data_keys ), $column_formats );
			$wpdb->insert( $wpdb->th_msp_publishing_group, $data, $column_formats );

			$id = $wpdb->insert_id;

			// wp_cache_add( $cache_key, $wpdb->insert_id, 24*60*60 );

			$query['parent']       = 1;
			$query['group_id']     = $wpdb->insert_id;

			$this->insert_publising( $query );
			return $id;
		}

		private function delete_publishing_group( $query = array() ) {
			global $wpdb;

			// Parse defaults
			$defaults = array(
				'group_id' => 0,
			);
			$query = wp_parse_args( $query, $defaults );

			// Sanitize values
			if (
				0 === $query['group_id']
			) {
				return false;
			}

			extract( $query );

			do_action( 'th_mba_delete_publishing_group', $group_id );

			// SQL Delete
			$delete_sql = $wpdb->prepare( "DELETE from {$wpdb->th_msp_publishing_group} WHERE group_id=%d", $group_id );

			if ( !$wpdb->query( $delete_sql ) )
				return false;

			do_action( 'th_mba_deleted_publishing_group', $group_id );

			return true;
		}


		// ======================================
		// Private methods
		// ======================================

		/**
		 * Get the current user
		 * Copy of the duplicate_post_get_current_user
		 * function in the Duplicate Post plugin.
		 *
		 * @return object user data
		 */
		private function get_current_user() {
			if ( function_exists( 'wp_get_current_user' ) ) {
				return wp_get_current_user();
			} else if ( function_exists( 'get_currentuserinfo' ) ) {
					global $userdata;
					get_currentuserinfo();
					return $userdata;
				} else {
				$user_login = $_COOKIE[USER_COOKIE];
				$current_user = $wpdb->get_results( "SELECT * FROM $wpdb->users WHERE user_login='$user_login'" );
				return $current_user;
			}
		}

		/**
		 * Get all the blog ids of blogs in the current network that are:
		 * - not archived
		 * - not spam
		 * - not deleted
		 *
		 * @param boolean $exclude_current Exclude the blog_id of the current blog
		 * @return array|false    The blog ids, false if no matches.
		 */
		public function get_blog_ids( $exclude_current = false ) {
			global $wpdb;

			$result = false;

			if ( $this->blog_ids ) {
				$result = $this->blog_ids;
				if ( $exclude_current ) {
					$result =  array_diff( $result, (array) $wpdb->blogid );
				}
				return $result;
			}

			// get an array of blog ids
			$sql = "SELECT blog_id
			FROM $wpdb->blogs
			WHERE archived = '0'
			AND spam = '0'
			AND deleted = '0'";
			$this->blog_ids = $wpdb->get_col( $sql );

			$result = $this->blog_ids;
			if ( $exclude_current ) {
				$result = array_diff( $result, (array) $wpdb->blogid );
			}
			return $result;
		}

	}
}
