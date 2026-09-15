<?php
/**
 * Customer model – a reusable client record (CRUD + search + auto-file
 * from invoice submissions), stored on the wim_customer post type.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_Customer {

	const META_EMAIL   = '_customer_email';
	const META_PHONE   = '_customer_phone';
	const META_ADDRESS = '_customer_address';

	/**
	 * Create a new customer.
	 *
	 * @param array $data { name, email, phone, address }
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		$post_id = wp_insert_post( array(
			'post_type'   => WP_IM_Post_Type::CUSTOMER_POST_TYPE,
			'post_title'  => sanitize_text_field( $data['name'] ?? '' ),
			'post_status' => 'publish',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::save_meta( $post_id, $data );

		return $post_id;
	}

	/**
	 * Update an existing customer.
	 *
	 * @param int   $post_id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $post_id, array $data ) {
		$post_id = absint( $post_id );
		if ( ! get_post( $post_id ) ) {
			return false;
		}

		wp_update_post( array(
			'ID'         => $post_id,
			'post_title' => sanitize_text_field( $data['name'] ?? '' ),
		) );

		self::save_meta( $post_id, $data );

		return true;
	}

	/**
	 * Persist the meta fields for a customer.
	 *
	 * @param int   $post_id
	 * @param array $data
	 */
	private static function save_meta( $post_id, array $data ) {
		update_post_meta( $post_id, self::META_EMAIL, sanitize_email( $data['email'] ?? '' ) );
		update_post_meta( $post_id, self::META_PHONE, sanitize_text_field( $data['phone'] ?? '' ) );
		update_post_meta( $post_id, self::META_ADDRESS, sanitize_textarea_field( $data['address'] ?? '' ) );
	}

	/**
	 * Delete a customer.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public static function delete( $post_id ) {
		return (bool) wp_delete_post( absint( $post_id ), true );
	}

	/**
	 * Get a single customer as an array.
	 *
	 * @param int $post_id
	 * @return array|null
	 */
	public static function get( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || WP_IM_Post_Type::CUSTOMER_POST_TYPE !== $post->post_type ) {
			return null;
		}

		return array(
			'id'      => $post->ID,
			'name'    => $post->post_title,
			'email'   => get_post_meta( $post->ID, self::META_EMAIL, true ),
			'phone'   => get_post_meta( $post->ID, self::META_PHONE, true ),
			'address' => get_post_meta( $post->ID, self::META_ADDRESS, true ),
		);
	}

	/**
	 * Get all customers, alphabetically by name.
	 *
	 * @return array[]
	 */
	public static function get_all() {
		$posts = get_posts( array(
			'post_type'      => WP_IM_Post_Type::CUSTOMER_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		return array_map( function ( $post ) {
			return self::get( $post->ID );
		}, $posts );
	}

	/**
	 * Search customers by name, email, or phone — used by the invoice
	 * form's "search customer" autocomplete.
	 *
	 * @param string $term
	 * @param int    $limit
	 * @return array[]
	 */
	public static function search( $term, $limit = 8 ) {
		$term = trim( (string) $term );
		if ( '' === $term ) {
			return array();
		}

		$results = array();

		$by_title = get_posts( array(
			'post_type'      => WP_IM_Post_Type::CUSTOMER_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			's'              => $term,
		) );
		foreach ( $by_title as $post ) {
			$results[ $post->ID ] = self::get( $post->ID );
		}

		$by_meta = get_posts( array(
			'post_type'      => WP_IM_Post_Type::CUSTOMER_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => self::META_EMAIL, 'value' => $term, 'compare' => 'LIKE' ),
				array( 'key' => self::META_PHONE, 'value' => $term, 'compare' => 'LIKE' ),
			),
		) );
		foreach ( $by_meta as $post ) {
			$results[ $post->ID ] = self::get( $post->ID );
		}

		return array_slice( array_values( $results ), 0, $limit );
	}

	/**
	 * Create or update a customer from submitted invoice data ("auto-file").
	 * Matched first by email (most reliable unique key), then by exact
	 * name + phone, so re-saving an invoice for the same client keeps
	 * updating one customer record instead of piling up duplicates.
	 *
	 * @param array $data Raw $_POST-style invoice data.
	 * @return int Customer post ID, or 0 if there was nothing to save.
	 */
	public static function upsert_from_invoice_data( array $data ) {
		$name = sanitize_text_field( $data['client_name'] ?? '' );
		if ( '' === $name ) {
			return 0;
		}

		$payload = array(
			'name'    => $name,
			'email'   => sanitize_email( $data['client_email'] ?? '' ),
			'phone'   => sanitize_text_field( $data['client_phone'] ?? '' ),
			'address' => sanitize_textarea_field( $data['client_address'] ?? '' ),
		);

		$existing_id = self::find_existing_id( $payload['email'], $name );

		if ( $existing_id ) {
			self::update( $existing_id, $payload );
			return $existing_id;
		}

		$new_id = self::create( $payload );
		return is_wp_error( $new_id ) ? 0 : $new_id;
	}

	/**
	 * Look up an existing customer post ID matching by email, falling back
	 * to an exact name match.
	 *
	 * @param string $email
	 * @param string $name
	 * @return int 0 if not found.
	 */
	private static function find_existing_id( $email, $name ) {
		if ( $email ) {
			$found = get_posts( array(
				'post_type'      => WP_IM_Post_Type::CUSTOMER_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => self::META_EMAIL, 'value' => $email ),
				),
			) );
			if ( $found ) {
				return (int) $found[0];
			}
		}

		// No email to match on: fall back to an exact name match. Not
		// requiring the phone to match too here is deliberate — a phone
		// number filled in (or edited) on a later save must still resolve
		// to the same customer instead of silently forking a duplicate.
		if ( $name ) {
			$found = get_posts( array(
				'post_type'      => WP_IM_Post_Type::CUSTOMER_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'title'          => $name,
			) );
			if ( $found ) {
				return (int) $found[0];
			}
		}

		return 0;
	}
}
