<?php
/**
 * Room type custom post type and free room data editor.
 *
 * Free version uses simple capacity per room/unit.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the room post type and room editor meta boxes.
 */
class CNWSHOTEL_Room_CPT {

	/**
	 * Registers hooks.
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_cnwshotel_room', array( $this, 'save_room' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_media' ) );
		add_filter( 'single_template', array( $this, 'load_single_template' ) );
	}

	/**
	 * Loads plugin single template for rooms.
	 *
	 * @param string $single_template Current template.
	 * @return string
	 */
	public function load_single_template( $single_template ) {

		global $post;

		if ( isset( $post->post_type ) && 'cnwshotel_room' === $post->post_type ) {
			$plugin_template = CNWSHOTEL_PATH . 'templates/single-room.php';

			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $single_template;
	}

	/**
	 * Registers room type post type.
	 */
	public function register_cpt() {

		register_post_type(
			'cnwshotel_room',
			array(
				'labels'             => array(
					'name'               => __( 'Room Types', 'wsh-hotel' ),
					'singular_name'      => __( 'Room Type', 'wsh-hotel' ),
					'add_new'            => __( 'Add Room Type', 'wsh-hotel' ),
					'add_new_item'       => __( 'Add New Room Type', 'wsh-hotel' ),
					'edit_item'          => __( 'Edit Room Type', 'wsh-hotel' ),
					'new_item'           => __( 'New Room Type', 'wsh-hotel' ),
					'view_item'          => __( 'View Room Type', 'wsh-hotel' ),
					'search_items'       => __( 'Search Room Types', 'wsh-hotel' ),
					'not_found'          => __( 'No room types found.', 'wsh-hotel' ),
					'not_found_in_trash' => __( 'No room types found in Trash.', 'wsh-hotel' ),
				),
				'public'             => true,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-admin-home',
				'show_in_menu'       => false,
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'taxonomies'         => array( 'post_tag' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'rewrite'            => array(
					'slug'       => 'rooms',
					'with_front' => false,
				),
				'publicly_queryable' => true,
				'has_archive'        => false,
			)
		);
	}

	/**
	 * Adds room meta boxes.
	 */
	public function add_meta_boxes() {

		add_meta_box(
			'cnwshotel_room_settings',
			__( 'Room Data', 'wsh-hotel' ),
			array( $this, 'room_settings_box' ),
			'cnwshotel_room',
			'normal',
			'high'
		);

		add_meta_box(
			'cnwshotel_room_units',
			__( 'Rooms / Units', 'wsh-hotel' ),
			array( $this, 'room_units_box' ),
			'cnwshotel_room',
			'normal',
			'default'
		);

		add_meta_box(
			'cnwshotel_room_gallery',
			__( 'Room Gallery', 'wsh-hotel' ),
			array( $this, 'room_gallery_box' ),
			'cnwshotel_room',
			'side',
			'default'
		);

		add_meta_box(
			'cnwshotel_room_seo_help',
			__( 'SEO', 'wsh-hotel' ),
			array( $this, 'seo_help_box' ),
			'cnwshotel_room',
			'side',
			'default'
		);
	}

	/**
	 * Gets room row for post.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	private function get_room_row( $post_id ) {

		global $wpdb;

		$table = $wpdb->prefix . 'cnwshotel_rooms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Room editor reads plugin table.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE post_id = %d LIMIT 1',
				$table,
				absint( $post_id )
			)
		);
	}

	/**
	 * Renders room settings meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function room_settings_box( $post ) {

		wp_nonce_field( CNWSHOTEL_Frontend_Request::ROOM_NONCE_ACTION, CNWSHOTEL_Frontend_Request::ROOM_NONCE_FIELD );

		$room          = $this->get_room_row( $post->ID );
		$price         = $room->price ?? 0;
		$pricing_model = $room->pricing_model ?? 'per_room';
		$max_persons   = $room->max_persons ?? 1;
		$quantity      = $room->quantity ?? 0;
		$intro         = get_post_meta( $post->ID, 'cnwshotel_room_intro', true );
		$room_size     = get_post_meta( $post->ID, 'cnwshotel_room_size', true );

		echo '<div class="cnwshotel_roomdata_wrap">';
		echo '<div class="cnwshotel_roomdata_tabs">';
		echo '<button type="button" class="active" data-tab="cnwshotel_tab_general">' . esc_html__( 'General', 'wsh-hotel' ) . '</button>';
		echo '<button type="button" data-tab="cnwshotel_tab_content">' . esc_html__( 'Content', 'wsh-hotel' ) . '</button>';
		echo '</div>';

		echo '<div class="cnwshotel_roomdata_content">';
		echo '<div id="cnwshotel_tab_general" class="cnwshotel_roomdata_panel active">';
		echo '<div class="cnwshotel_roomdata_grid">';

		echo '<div class="cnwshotel_roomdata_field">';
		echo '<label for="cnwshotel_price">' . esc_html__( 'Price per night', 'wsh-hotel' ) . '</label>';
		echo '<input type="number" step="0.01" min="0" id="cnwshotel_price" name="cnwshotel_price" value="' . esc_attr( $price ) . '" class="widefat">';
		echo '</div>';

		echo '<div class="cnwshotel_roomdata_field">';
		echo '<label for="cnwshotel_pricing_model">' . esc_html__( 'Pricing model', 'wsh-hotel' ) . '</label>';
		echo '<select id="cnwshotel_pricing_model" name="cnwshotel_pricing_model" class="widefat">';
		echo '<option value="per_room" ' . selected( $pricing_model, 'per_room', false ) . '>' . esc_html__( 'Per room', 'wsh-hotel' ) . '</option>';
		echo '<option value="per_person" ' . selected( $pricing_model, 'per_person', false ) . '>' . esc_html__( 'Per guest', 'wsh-hotel' ) . '</option>';
		echo '</select>';
		echo '</div>';

		echo '<div class="cnwshotel_roomdata_field">';
		echo '<label for="cnwshotel_max_persons">' . esc_html__( 'Capacity per room/unit', 'wsh-hotel' ) . '</label>';
		echo '<input type="number" min="1" id="cnwshotel_max_persons" name="cnwshotel_max_persons" value="' . esc_attr( max( 1, absint( $max_persons ) ) ) . '" class="widefat">';
		echo '<p class="description">' . esc_html__( 'The same capacity is used for each active room or unit below.', 'wsh-hotel' ) . '</p>';
		echo '</div>';

		echo '<div class="cnwshotel_roomdata_field">';
		echo '<label for="cnwshotel_total_units_display">' . esc_html__( 'Active rooms/units', 'wsh-hotel' ) . '</label>';
		echo '<input type="text" id="cnwshotel_total_units_display" value="' . esc_attr( absint( $quantity ) ) . '" class="widefat" readonly>';
		echo '<p class="description">' . esc_html__( 'Calculated from the active unit rows below.', 'wsh-hotel' ) . '</p>';
		echo '</div>';

		echo '<div class="cnwshotel_roomdata_field">';
		echo '<label for="cnwshotel_room_size">' . esc_html__( 'Room size (sq m)', 'wsh-hotel' ) . '</label>';
		echo '<input type="number" min="0" id="cnwshotel_room_size" name="cnwshotel_room_size" value="' . esc_attr( absint( $room_size ) ) . '" class="widefat">';
		echo '</div>';

		echo '</div>';
		echo '</div>';

		echo '<div id="cnwshotel_tab_content" class="cnwshotel_roomdata_panel">';
		echo '<div class="cnwshotel_roomdata_field">';
		echo '<label for="cnwshotel_room_intro">' . esc_html__( 'Room intro', 'wsh-hotel' ) . '</label>';
		echo '<textarea id="cnwshotel_room_intro" name="cnwshotel_room_intro" rows="5" class="widefat">' . esc_textarea( $intro ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Short intro used on cards and summaries.', 'wsh-hotel' ) . '</p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Renders room/unit rows without bed setup.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function room_units_box( $post ) {

		global $wpdb;

		$room           = $this->get_room_row( $post->ID );
		$units_table    = $wpdb->prefix . 'cnwshotel_room_units';
		$existing_units = array();

		if ( $room ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Room editor reads plugin unit table.
			$existing_units = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE room_type_id = %d ORDER BY floor ASC, unit_number ASC',
					$units_table,
					absint( $room->id )
				)
			);
		}

		echo '<p class="description">' . esc_html__( 'Add each physical room/unit that can be booked for this room type. Each unit uses the capacity set above.', 'wsh-hotel' ) . '</p>';
		echo '<p><button type="button" class="button" id="add-cnwshotel_room_unit">' . esc_html__( 'Add room/unit', 'wsh-hotel' ) . '</button></p>';
		echo '<div id="cnwshotel_room_units_container" class="cnwshotel_room_units_container">';

		if ( ! empty( $existing_units ) ) {
			foreach ( $existing_units as $unit ) {
				$this->render_unit_row( $unit->unit_number, $unit->floor, $unit->status );
			}
		}

		echo '</div>';
	}

	/**
	 * Renders one unit row.
	 *
	 * @param string $unit_number Unit number/name.
	 * @param int    $floor Floor.
	 * @param string $status Status.
	 */
	private function render_unit_row( $unit_number = '', $floor = 0, $status = 'active' ) {

		echo '<div class="cnwshotel_room_unit">';
		echo '<div class="cnwshotel_room_unit_row">';
		echo '<input type="text" name="room_number[]" value="' . esc_attr( $unit_number ) . '" placeholder="' . esc_attr__( 'Room number or unit name', 'wsh-hotel' ) . '" class="widefat">';
		echo '<input type="number" name="floor[]" value="' . esc_attr( (int) $floor ) . '" placeholder="' . esc_attr__( 'Floor', 'wsh-hotel' ) . '" class="widefat">';
		echo '<select name="status[]" class="widefat">';
		echo '<option value="active" ' . selected( $status, 'active', false ) . '>' . esc_html__( 'Active', 'wsh-hotel' ) . '</option>';
		echo '<option value="inactive" ' . selected( $status, 'inactive', false ) . '>' . esc_html__( 'Inactive', 'wsh-hotel' ) . '</option>';
		echo '</select>';
		echo '<button type="button" class="button-link-delete cnwshotel_remove_room">' . esc_html__( 'Remove room', 'wsh-hotel' ) . '</button>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Renders gallery box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function room_gallery_box( $post ) {

		$images = get_post_meta( $post->ID, 'cnwshotel_room_gallery', true );

		if ( ! is_array( $images ) ) {
			$images = array();
		}

		echo '<div id="cnwshotel_gallery_preview">';

		foreach ( $images as $id ) {
			$thumb = wp_get_attachment_image_url( absint( $id ), 'thumbnail' );
			if ( ! $thumb ) {
				continue;
			}

			echo '<div class="cnwshotel_admin_thumb" data-id="' . esc_attr( absint( $id ) ) . '">';
			echo '<img src="' . esc_url( $thumb ) . '" alt="">';
			echo '<span class="remove" role="button" tabindex="0" aria-label="' . esc_attr__( 'Remove image', 'wsh-hotel' ) . '">&times;</span>';
			echo '</div>';
		}

		echo '</div>';
		echo '<input type="hidden" id="cnwshotel_room_gallery" name="cnwshotel_room_gallery" value=\'' . esc_attr( wp_json_encode( $images ) ) . '\'>';
		echo '<p><button type="button" class="button cnwshotel_add_gallery">' . esc_html__( 'Add images', 'wsh-hotel' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Use the gallery field for room images. Featured image can still be used as the main image.', 'wsh-hotel' ) . '</p>';
	}

	/**
	 * Renders SEO help.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function seo_help_box( $post ) {
		unset( $post );

		echo '<p><strong>' . esc_html__( 'SEO plugins supported', 'wsh-hotel' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Room Types are real WordPress content, so SEO plugins can use their normal fields on this edit screen.', 'wsh-hotel' ) . '</p>';
		echo '<ul class="cnwshotel_admin_list">';
		echo '<li>' . esc_html__( 'Use the title as the room type name.', 'wsh-hotel' ) . '</li>';
		echo '<li>' . esc_html__( 'Use the main content editor for the room description.', 'wsh-hotel' ) . '</li>';
		echo '<li>' . esc_html__( 'Use Excerpt for a short intro.', 'wsh-hotel' ) . '</li>';
		echo '</ul>';
	}

	/**
	 * Saves room data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 */
	public function save_room( $post_id, $post ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
				return;
		}

		if ( ! $post || 'cnwshotel_room' !== $post->post_type ) {
			return;
		}

		if ( ! class_exists( 'CNWSHOTEL_Frontend_Request' ) || ! CNWSHOTEL_Frontend_Request::verify_room_save_request( $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verify_room_save_request() verifies capability and nonce immediately above; fields are sanitized by sanitize_room_save_submission().
		$post_data  = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$submission = CNWSHOTEL_Frontend_Request::sanitize_room_save_submission( $post_data );

		global $wpdb;

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';
		$units_table = $wpdb->prefix . 'cnwshotel_room_units';
		$beds_table  = $wpdb->prefix . 'cnwshotel_room_unit_beds';

		$room_numbers = (array) $submission['room_numbers'];
		$floors       = (array) $submission['floors'];
		$statuses     = (array) $submission['statuses'];

		$total_units = 0;

		foreach ( $room_numbers as $index => $room_number ) {
			$status = isset( $statuses[ $index ] ) ? $statuses[ $index ] : 'active';

			if ( '' !== $room_number && 'active' === $status ) {
				++$total_units;
			}
		}

		$max_persons   = absint( $submission['max_persons'] );
		$price         = (float) $submission['price'];
		$pricing_model = (string) $submission['pricing_model'];

		$data = array(
			'room_number'     => sanitize_text_field( $post->post_title ),
			'quantity'        => $total_units,
			'max_persons'     => $max_persons,
			'price'           => $price,
			'pricing_model'   => $pricing_model,
			'allocation_mode' => 'exclusive_units',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Room save checks plugin table.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE post_id = %d LIMIT 1',
				$rooms_table,
				absint( $post_id )
			)
		);

		if ( $exists ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional room save.
			$wpdb->update(
				$rooms_table,
				$data,
				array( 'post_id' => absint( $post_id ) ),
				array( '%s', '%d', '%d', '%f', '%s', '%s' ),
				array( '%d' )
			);
			$room_type_id = absint( $exists );
		} else {
				$data['post_id'] = absint( $post_id );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional room save.
			$wpdb->insert(
				$rooms_table,
				$data,
				array( '%s', '%d', '%d', '%f', '%s', '%s', '%d' )
			);
			$room_type_id = absint( $wpdb->insert_id );
		}

		if ( $room_type_id ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads rows before deleting related bed rows.
			$existing_unit_ids = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE room_type_id = %d',
					$units_table,
					$room_type_id
				)
			);

			if ( ! empty( $existing_unit_ids ) ) {
				foreach ( $existing_unit_ids as $unit_id ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Free version removes bed relations when saving simplified units.
					$wpdb->delete( $beds_table, array( 'room_unit_id' => absint( $unit_id ) ), array( '%d' ) );
				}
			}

			   // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Rebuilds units from submitted rows.
			$wpdb->delete( $units_table, array( 'room_type_id' => $room_type_id ), array( '%d' ) );

			foreach ( $room_numbers as $index => $room_number ) {
				$room_number = sanitize_text_field( $room_number );

				if ( '' === $room_number ) {
						continue;
				}

				$floor  = isset( $floors[ $index ] ) ? absint( $floors[ $index ] ) : 0;
				$status = sanitize_key( $statuses[ $index ] ?? 'active' );

				if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
					$status = 'active';
				}

				 // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional unit save.
					$wpdb->insert(
						$units_table,
						array(
							'room_type_id' => $room_type_id,
							'unit_number'  => $room_number,
							'floor'        => $floor,
							'status'       => $status,
						),
						array( '%d', '%s', '%d', '%s' )
					);
			}
		}

		$room_intro = (string) $submission['room_intro'];
		$room_size  = absint( $submission['room_size'] );

		update_post_meta( $post_id, 'cnwshotel_room_intro', $room_intro );
		update_post_meta( $post_id, 'cnwshotel_room_size', $room_size );

		$gallery = (array) $submission['gallery'];

		update_post_meta( $post_id, 'cnwshotel_room_gallery', $gallery );
		wp_cache_delete( 'cnwshotel_availability_' . absint( $post_id ), 'cnwshotel' );
	}

	/**
	 * Enqueues media and admin scripts for room editor.
	 *
	 * @param string $hook Current hook.
	 */
	public function load_media( $hook ) {

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		global $post;

		if ( isset( $post ) && 'cnwshotel_room' === $post->post_type ) {
				wp_enqueue_media();

				$gallery_path = CNWSHOTEL_PATH . 'assets/js/cnwshotel-gallery.js';

			if ( file_exists( $gallery_path ) ) {
				wp_enqueue_script(
					'cnwshotel-gallery-admin',
					CNWSHOTEL_URL . 'assets/js/cnwshotel-gallery.js',
					array( 'jquery' ),
					filemtime( $gallery_path ),
					true
				);
			}
		}
	}
}
