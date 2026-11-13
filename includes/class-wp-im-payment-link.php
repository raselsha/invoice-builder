<?php
/**
 * Payment links: a shareable /pay/{token}/ page where the customer pays a
 * fixed amount, picks one of several, or types their own. Each paid link
 * creates a paid invoice, so every payment lands in the same books.
 *
 * Stored as the `wim_payment_link` post type (settings in post meta).
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_Payment_Link {

	const POST_TYPE = 'wim_payment_link';

	const MODES = array( 'fixed', 'choices', 'open' );
	const NOTES = array( 'off', 'optional', 'required' );

	/** Meta on an invoice created from a link. */
	const INVOICE_META_LINK = '_invoice_payment_link';

	public static function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels'       => array(
				'name'          => __( 'Payment Links', 'wp-invoice-manager' ),
				'singular_name' => __( 'Payment Link', 'wp-invoice-manager' ),
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_rest' => false,
			'supports'     => array( 'title' ),
			'rewrite'      => false,
			'query_var'    => false,
		) );
	}

	// ── Read ────────────────────────────────────────────────────────────────

	/**
	 * @param int|WP_Post $post
	 * @return array|null
	 */
	public static function get( $post ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$id   = $post->ID;
		$meta = static function ( $key, $default = '' ) use ( $id ) {
			$value = get_post_meta( $id, $key, true );
			return ( '' === $value || null === $value ) ? $default : $value;
		};

		$mode     = $meta( '_wim_link_mode', 'fixed' );
		$currency = strtoupper( $meta( '_wim_link_currency', get_option( 'wp_im_default_currency', 'USD' ) ) );
		$choices  = $meta( '_wim_link_choices', array() );
		$note     = $meta( '_wim_link_note', 'off' );
		$token    = $meta( '_wim_link_token' );

		return array(
			'id'           => $id,
			'title'        => get_the_title( $post ),
			'created'      => get_post_time( 'U', true, $post ),
			'token'        => $token,
			'url'          => $token ? home_url( 'pay/' . $token . '/' ) : '',
			'mode'         => in_array( $mode, self::MODES, true ) ? $mode : 'fixed',
			'currency'     => array_key_exists( $currency, WP_IM_Invoice::get_currencies() ) ? $currency : 'USD',
			'amount'       => (float) $meta( '_wim_link_amount', 0 ),
			'choices'      => is_array( $choices ) ? array_values( array_map( 'floatval', $choices ) ) : array(),
			'allow_custom' => (bool) $meta( '_wim_link_allow_custom', 0 ),
			'min'          => '' === $meta( '_wim_link_min' ) ? null : (float) $meta( '_wim_link_min' ),
			'max'          => '' === $meta( '_wim_link_max' ) ? null : (float) $meta( '_wim_link_max' ),
			'description'  => (string) $meta( '_wim_link_description' ),
			'note'         => in_array( $note, self::NOTES, true ) ? $note : 'off',
			'note_label'   => (string) $meta( '_wim_link_note_label' ),
			'customer_id'  => (int) $meta( '_wim_link_customer', 0 ),
			'expires'      => (string) $meta( '_wim_link_expires' ),
			'single_use'   => (bool) $meta( '_wim_link_single_use', 0 ),
			'active'       => (bool) $meta( '_wim_link_active', 1 ),
			'used_at'      => (int) $meta( '_wim_link_used_at', 0 ),
		);
	}

	/**
	 * @return WP_Post[]
	 */
	public static function get_all() {
		return get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
	}

	public static function find_by_token( $token ) {
		if ( ! preg_match( '/^[a-z0-9]{6,32}$/', (string) $token ) ) {
			return null;
		}
		$ids = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_wim_link_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $token,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		) );
		return $ids ? self::get( $ids[0] ) : null;
	}

	// ── Write ───────────────────────────────────────────────────────────────

	/**
	 * Create or update a link from the admin form.
	 *
	 * @param int   $id   0 for a new link.
	 * @param array $data Raw $_POST.
	 * @return int|WP_Error Link ID.
	 */
	public static function save( $id, array $data ) {
		$title = sanitize_text_field( wp_unslash( $data['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'wim_link_title', __( 'Please give the link a name — what is the payment for?', 'wp-invoice-manager' ) );
		}

		if ( $id ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
		} else {
			$id = wp_insert_post( array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			), true );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		}

		$text     = static function ( $key ) use ( $data ) {
			return sanitize_text_field( wp_unslash( $data[ $key ] ?? '' ) );
		};
		$mode     = in_array( $text( 'mode' ), self::MODES, true ) ? $text( 'mode' ) : 'fixed';
		$currency = strtoupper( $text( 'currency' ) );
		$currency = array_key_exists( $currency, WP_IM_Invoice::get_currencies() ) ? $currency : 'USD';
		$note     = in_array( $text( 'note' ), self::NOTES, true ) ? $text( 'note' ) : 'off';
		$amount   = WP_IM_Stripe::parse_amount( $text( 'amount' ) );
		$min      = WP_IM_Stripe::parse_amount( $text( 'min' ) );
		$max      = WP_IM_Stripe::parse_amount( $text( 'max' ) );
		$expires  = $text( 'expires' );
		$choices  = array();
		foreach ( explode( ',', $text( 'choices' ) ) as $raw ) {
			$value = WP_IM_Stripe::parse_amount( $raw );
			if ( null !== $value && $value > 0 ) {
				$choices[ (string) $value ] = $value;
			}
		}
		ksort( $choices, SORT_NUMERIC );

		$meta = array(
			'_wim_link_mode'         => $mode,
			'_wim_link_currency'     => $currency,
			'_wim_link_amount'       => null === $amount ? '' : $amount,
			'_wim_link_choices'      => array_values( $choices ),
			'_wim_link_allow_custom' => empty( $data['allow_custom'] ) ? 0 : 1,
			'_wim_link_min'          => null === $min ? '' : $min,
			'_wim_link_max'          => null === $max ? '' : $max,
			'_wim_link_description'  => sanitize_textarea_field( wp_unslash( $data['description'] ?? '' ) ),
			'_wim_link_note'         => $note,
			'_wim_link_note_label'   => $text( 'note_label' ),
			'_wim_link_customer'     => absint( $data['customer_id'] ?? 0 ),
			'_wim_link_expires'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ? $expires : '',
			'_wim_link_single_use'   => empty( $data['single_use'] ) ? 0 : 1,
			'_wim_link_active'       => empty( $data['active'] ) ? 0 : 1,
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		if ( ! empty( $data['reopen'] ) ) {
			delete_post_meta( $id, '_wim_link_used_at' );
		}
		if ( ! get_post_meta( $id, '_wim_link_token', true ) ) {
			update_post_meta( $id, '_wim_link_token', self::new_token() );
		}

		return $id;
	}

	public static function delete( $id ) {
		$post = get_post( $id );
		if ( $post && self::POST_TYPE === $post->post_type ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * Random, unguessable token: 10 lowercase letters/digits (no look-alikes).
	 */
	public static function new_token() {
		$chars = 'abcdefghijkmnpqrstuvwxyz23456789';
		do {
			$token = '';
			for ( $i = 0; $i < 10; $i++ ) {
				$token .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
			}
		} while ( self::find_by_token( $token ) );
		return $token;
	}

	// ── Rules ───────────────────────────────────────────────────────────────

	/**
	 * 'active', 'disabled', 'expired' or 'used'.
	 */
	public static function status( array $link ) {
		if ( ! $link['active'] ) {
			return 'disabled';
		}
		if ( $link['single_use'] && $link['used_at'] ) {
			return 'used';
		}
		if ( $link['expires'] && current_time( 'Y-m-d' ) > $link['expires'] ) {
			return 'expired';
		}
		return 'active';
	}

	public static function status_label( $status ) {
		$labels = array(
			'active'   => __( 'Active', 'wp-invoice-manager' ),
			'disabled' => __( 'Turned off', 'wp-invoice-manager' ),
			'expired'  => __( 'Expired', 'wp-invoice-manager' ),
			'used'     => __( 'Paid (one-time)', 'wp-invoice-manager' ),
		);
		return $labels[ $status ] ?? $status;
	}

	public static function min_amount( array $link ) {
		$floor = WP_IM_Stripe::min_amount( $link['currency'] );
		return null !== $link['min'] ? max( $floor, $link['min'] ) : $floor;
	}

	public static function max_amount( array $link ) {
		return null !== $link['max'] ? min( WP_IM_Stripe::MAX_AMOUNT, $link['max'] ) : WP_IM_Stripe::MAX_AMOUNT;
	}

	/**
	 * "$200" / "$100 · $200 · $300 + any amount" / "Customer decides (min $5)".
	 */
	public static function summary( array $link ) {
		$c = $link['currency'];
		if ( 'choices' === $link['mode'] ) {
			$parts = array_map( static function ( $a ) use ( $c ) {
				return WP_IM_Stripe::format( $a, $c );
			}, $link['choices'] );
			$text  = $parts ? implode( ' · ', $parts ) : '—';
			return $link['allow_custom'] ? $text . ' ' . __( '+ any amount', 'wp-invoice-manager' ) : $text;
		}
		if ( 'open' === $link['mode'] ) {
			$bits = array();
			if ( null !== $link['min'] ) {
				/* translators: %s: amount */
				$bits[] = sprintf( __( 'min %s', 'wp-invoice-manager' ), WP_IM_Stripe::format( $link['min'], $c ) );
			}
			if ( null !== $link['max'] ) {
				/* translators: %s: amount */
				$bits[] = sprintf( __( 'max %s', 'wp-invoice-manager' ), WP_IM_Stripe::format( $link['max'], $c ) );
			}
			return __( 'Customer decides', 'wp-invoice-manager' ) . ( $bits ? ' (' . implode( ', ', $bits ) . ')' : '' ) . ' · ' . $c;
		}
		return WP_IM_Stripe::format( $link['amount'], $c );
	}

	/**
	 * What the customer is paying, from the form — checked against the link.
	 *
	 * @return float|WP_Error Major units.
	 */
	public static function resolve_amount( array $link, $choice, $custom ) {
		$c = $link['currency'];

		if ( 'fixed' === $link['mode'] ) {
			$amount = $link['amount'];
		} elseif ( 'choices' === $link['mode'] && 'custom' !== $choice ) {
			$picked = WP_IM_Stripe::parse_amount( $choice );
			$amount = null;
			foreach ( $link['choices'] as $option ) {
				if ( null !== $picked && abs( $option - $picked ) < 0.001 ) {
					$amount = $option;
				}
			}
			if ( null === $amount ) {
				return new WP_Error( 'wim_amount', __( 'Please choose an amount.', 'wp-invoice-manager' ) );
			}
		} else {
			if ( 'choices' === $link['mode'] && ! $link['allow_custom'] ) {
				return new WP_Error( 'wim_amount', __( 'Please choose an amount.', 'wp-invoice-manager' ) );
			}
			$amount = WP_IM_Stripe::parse_amount( $custom );
			if ( null === $amount ) {
				return new WP_Error( 'wim_amount', __( 'Please enter a valid amount, like 150 or 99.50.', 'wp-invoice-manager' ) );
			}
			$min = 'open' === $link['mode'] ? self::min_amount( $link ) : WP_IM_Stripe::min_amount( $c );
			$max = 'open' === $link['mode'] ? self::max_amount( $link ) : WP_IM_Stripe::MAX_AMOUNT;
			if ( $amount < $min ) {
				/* translators: %s: smallest amount */
				return new WP_Error( 'wim_amount', sprintf( __( 'The smallest amount you can pay is %s.', 'wp-invoice-manager' ), WP_IM_Stripe::format( $min, $c ) ) );
			}
			if ( $amount > $max ) {
				/* translators: %s: largest amount */
				return new WP_Error( 'wim_amount', sprintf( __( 'The largest amount you can pay is %s.', 'wp-invoice-manager' ), WP_IM_Stripe::format( $max, $c ) ) );
			}
		}

		if ( $amount < WP_IM_Stripe::min_amount( $c ) ) {
			return new WP_Error( 'wim_amount', __( 'This link is not set up with a valid amount. Please contact us.', 'wp-invoice-manager' ) );
		}
		return $amount;
	}

	/**
	 * Problem with the link's own setup that would stop anyone paying, or ''.
	 */
	public static function setup_problem( array $link ) {
		$floor = WP_IM_Stripe::min_amount( $link['currency'] );
		if ( 'fixed' === $link['mode'] && $link['amount'] < $floor ) {
			/* translators: %s: smallest amount */
			return sprintf( __( 'Set an amount of at least %s — the link can’t take payments without one.', 'wp-invoice-manager' ), WP_IM_Stripe::format( $floor, $link['currency'] ) );
		}
		if ( 'choices' === $link['mode'] && ( ! $link['choices'] || min( $link['choices'] ) < $floor ) ) {
			/* translators: %s: smallest amount */
			return sprintf( __( 'Add at least one amount option of %s or more.', 'wp-invoice-manager' ), WP_IM_Stripe::format( $floor, $link['currency'] ) );
		}
		if ( 'open' === $link['mode'] && null !== $link['min'] && null !== $link['max'] && $link['min'] > $link['max'] ) {
			return __( 'The minimum is higher than the maximum, so nobody can pay. Please fix one of them.', 'wp-invoice-manager' );
		}
		return '';
	}

	/**
	 * Invoices created by payments on this link: [ count, total ].
	 */
	public static function totals( $link_id ) {
		$ids   = get_posts( array(
			'post_type'      => WP_IM_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => self::INVOICE_META_LINK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => (int) $link_id,          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		) );
		$total = 0.0;
		foreach ( $ids as $invoice_id ) {
			$total += ( new WP_IM_Invoice( $invoice_id ) )->calculate_totals()['paid'];
		}
		return array( count( $ids ), $total );
	}
}
