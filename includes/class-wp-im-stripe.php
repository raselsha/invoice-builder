<?php
/**
 * Stripe: settings, key resolution, amounts, and a minimal REST client
 * (no SDK) for Checkout Sessions, the account and webhook endpoints.
 *
 * Keys: the secret key saved in Settings for the current mode, otherwise the
 * key Easy Digital Downloads holds for its Stripe connection — so a site that
 * already sells through EDD has nothing to set up.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_Stripe {

	const API = 'https://api.stripe.com/v1';

	// Options.
	const OPT_ENABLED     = 'wp_im_stripe_enabled';
	const OPT_MODE        = 'wp_im_stripe_mode';        // auto | test | live
	const OPT_TEST_SECRET = 'wp_im_stripe_test_secret';
	const OPT_LIVE_SECRET = 'wp_im_stripe_live_secret';
	const OPT_WEBHOOKS    = 'wp_im_stripe_webhooks';    // mode => [ id, secret, url ]
	const OPT_NOTIFY      = 'wp_im_payment_notify_email';

	/** Currencies Stripe charges without decimals. */
	const ZERO_DECIMAL = array( 'JPY', 'KRW', 'VND', 'CLP', 'PYG', 'UGX', 'XAF', 'XOF' );

	public static function enabled() {
		return (bool) get_option( self::OPT_ENABLED, false );
	}

	/**
	 * Online payment can actually be taken right now.
	 */
	public static function is_ready() {
		return self::enabled() && '' !== self::secret_key();
	}

	/**
	 * 'test' or 'live'.
	 */
	public static function mode() {
		$mode = get_option( self::OPT_MODE, 'auto' );
		if ( 'test' === $mode || 'live' === $mode ) {
			return $mode;
		}
		if ( function_exists( 'edd_is_test_mode' ) ) {
			return edd_is_test_mode() ? 'test' : 'live';
		}
		return 'live';
	}

	public static function edd_available() {
		return function_exists( 'edd_get_option' );
	}

	public static function secret_key( $mode = null ) {
		$mode = $mode ? $mode : self::mode();
		$own  = (string) get_option( 'test' === $mode ? self::OPT_TEST_SECRET : self::OPT_LIVE_SECRET, '' );
		if ( '' !== $own ) {
			return $own;
		}
		return self::edd_available() ? (string) edd_get_option( $mode . '_secret_key', '' ) : '';
	}

	/**
	 * Where the key for a mode comes from: 'own', 'edd' or ''.
	 */
	public static function key_source( $mode = null ) {
		$mode = $mode ? $mode : self::mode();
		if ( '' !== (string) get_option( 'test' === $mode ? self::OPT_TEST_SECRET : self::OPT_LIVE_SECRET, '' ) ) {
			return 'own';
		}
		if ( self::edd_available() && '' !== (string) edd_get_option( $mode . '_secret_key', '' ) ) {
			return 'edd';
		}
		return '';
	}

	public static function webhook( $mode = null ) {
		$mode = $mode ? $mode : self::mode();
		$all  = get_option( self::OPT_WEBHOOKS, array() );
		return is_array( $all ) && isset( $all[ $mode ] ) && is_array( $all[ $mode ] ) ? $all[ $mode ] : array();
	}

	public static function notify_email() {
		$email = get_option( self::OPT_NOTIFY, '' );
		return is_email( $email ) ? $email : get_option( 'wp_im_company_email', get_option( 'admin_email' ) );
	}

	// ── Amounts ────────────────────────────────────────────────────────────

	public static function is_zero_decimal( $currency ) {
		return in_array( strtoupper( $currency ), self::ZERO_DECIMAL, true );
	}

	public static function to_minor( $amount, $currency ) {
		return self::is_zero_decimal( $currency ) ? (int) round( (float) $amount ) : (int) round( (float) $amount * 100 );
	}

	public static function from_minor( $minor, $currency ) {
		return self::is_zero_decimal( $currency ) ? (float) $minor : ( (int) $minor ) / 100;
	}

	/**
	 * Smallest amount we let a customer pay, in major units. Stripe's floor
	 * is about €0.50 for a EUR-settled account; these stay safely above it.
	 */
	public static function min_amount( $currency ) {
		$mins = array( 'USD' => 0.5, 'EUR' => 0.5, 'GBP' => 0.3, 'CAD' => 0.5, 'AUD' => 0.5, 'BDT' => 100, 'INR' => 50, 'JPY' => 100 );
		$code = strtoupper( $currency );
		return isset( $mins[ $code ] ) ? $mins[ $code ] : 1;
	}

	const MAX_AMOUNT = 999999.99;

	/**
	 * "$200", "$12.50", "৳1,500", "¥5,000".
	 */
	public static function format( $amount, $currency ) {
		$amount   = (float) $amount;
		$decimals = ( self::is_zero_decimal( $currency ) || abs( $amount - round( $amount ) ) < 0.001 ) ? 0 : 2;
		return WP_IM_Invoice::currency_symbol( strtoupper( $currency ) ) . number_format( $amount, $decimals );
	}

	/**
	 * Parse a typed amount ("1,250.50") into a float, or null.
	 */
	public static function parse_amount( $raw ) {
		$raw = trim( str_replace( array( ',', ' ' ), '', (string) $raw ) );
		if ( '' === $raw || ! preg_match( '/^\d+(\.\d{1,2})?$/', $raw ) ) {
			return null;
		}
		return round( (float) $raw, 2 );
	}

	// ── API ─────────────────────────────────────────────────────────────────

	/**
	 * @return array|WP_Error
	 */
	public static function request( $method, $path, array $params = array(), $mode = null ) {
		$key = self::secret_key( $mode );
		if ( '' === $key ) {
			return new WP_Error( 'wim_stripe_no_key', __( 'Stripe is not connected yet.', 'wp-invoice-manager' ) );
		}

		$url  = self::API . $path;
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization'  => 'Bearer ' . $key,
				'Stripe-Version' => '2024-06-20',
			),
		);
		if ( 'GET' === $method ) {
			if ( $params ) {
				$url .= '?' . http_build_query( $params );
			}
		} else {
			$args['body'] = http_build_query( $params );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 || ! is_array( $data ) ) {
			$message = is_array( $data ) && isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf( 'HTTP %d', $code );
			return new WP_Error( 'wim_stripe', $message, array( 'status' => $code ) );
		}
		return $data;
	}

	public static function create_checkout_session( array $params ) {
		return self::request( 'POST', '/checkout/sessions', $params );
	}

	public static function get_checkout_session( $id ) {
		$mode = 0 === strpos( $id, 'cs_test_' ) ? 'test' : 'live';
		return self::request( 'GET', '/checkout/sessions/' . rawurlencode( $id ), array(), $mode );
	}

	public static function get_account( $mode = null ) {
		return self::request( 'GET', '/account', array(), $mode );
	}

	/**
	 * Create (or replace) this site's webhook endpoint for a mode.
	 *
	 * @return array|WP_Error
	 */
	public static function create_webhook( $mode ) {
		$old = self::webhook( $mode );
		if ( ! empty( $old['id'] ) ) {
			self::request( 'DELETE', '/webhook_endpoints/' . rawurlencode( $old['id'] ), array(), $mode );
		}

		$url  = rest_url( 'wim/v1/stripe-webhook' );
		$data = self::request( 'POST', '/webhook_endpoints', array(
			'url'            => $url,
			// Required when the key belongs to a connected account (EDD's Stripe Connect).
			'api_version'    => '2024-06-20',
			'enabled_events' => array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ),
			'description'    => 'WP Invoice Manager (invoices + payment links)',
		), $mode );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$all          = get_option( self::OPT_WEBHOOKS, array() );
		$all          = is_array( $all ) ? $all : array();
		$all[ $mode ] = array( 'id' => $data['id'], 'secret' => $data['secret'], 'url' => $url );
		update_option( self::OPT_WEBHOOKS, $all, false );
		return $data;
	}

	/**
	 * Check a Stripe-Signature header (v1, HMAC-SHA256, 5-minute tolerance).
	 */
	public static function verify_signature( $payload, $header, $secret, $tolerance = 300 ) {
		if ( '' === (string) $secret || '' === (string) $header ) {
			return false;
		}
		$timestamp  = 0;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 === count( $kv ) ) {
				if ( 't' === $kv[0] ) {
					$timestamp = (int) $kv[1];
				} elseif ( 'v1' === $kv[0] ) {
					$signatures[] = $kv[1];
				}
			}
		}
		if ( ! $timestamp || ! $signatures || abs( time() - $timestamp ) > $tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}

	public static function dashboard_url( $payment_intent, $mode ) {
		return 'https://dashboard.stripe.com/' . ( 'test' === $mode ? 'test/' : '' ) . 'payments/' . rawurlencode( $payment_intent );
	}
}
