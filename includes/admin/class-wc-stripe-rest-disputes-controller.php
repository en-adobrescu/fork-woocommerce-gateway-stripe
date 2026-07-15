<?php
/**
 * Class WC_Stripe_REST_Disputes_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing Stripe disputes data to the admin UI.
 *
 * The controller acts as a proxy that forwards the received parameters to the remote Stripe API and returns the received response.
 *
 * @since 10.9.0
 */
class WC_Stripe_REST_Disputes_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'disputes';

	/**
	 * Endpoint args.
	 *
	 * @var array
	 */
	protected $rest_args = [
		'limit'          => [
			'type'              => 'integer',
			'default'           => 25,
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'starting_after' => [
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'ending_before'  => [
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'created'        => [
			'required'          => false,
			'sanitize_callback' => [ WC_Stripe_REST_Validator::class, 'sanitize_timestamp' ],
			'validate_callback' => [ WC_Stripe_REST_Validator::class, 'validate_timestamp' ],
		],
		'charge'         => [
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'payment_intent' => [
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
	];

	/**
	 * Endpoint query args.
	 *
	 * @var array
	 */
	protected static $rest_query_args = [];

	protected array $stripe_response_allowed_fields = [
		'object'              => '',
		'has_more'            => '',
		'data.id'             => '',
		'data.amount'         => [ WC_Stripe_REST_Response_Filter::class, 'money_format' ],
		'data.currency'       => 'strtoupper',
		'data.status'         => '',
		'data.created'        => [ WC_Stripe_REST_Response_Filter::class, 'date_format' ],
		'data.charge'         => '',
		'data.payment_intent' => '',
	];

	protected array $stripe_details_response_allowed_fields = [
		'object'                   => '',
		'id'                       => '',
		'amount'                   => [ WC_Stripe_REST_Response_Filter::class, 'money_format' ],
		'currency'                 => 'strtoupper',
		'status'                   => '',
		'description'              => '',
		'balance_transactions.fee' => [ WC_Stripe_REST_Response_Filter::class, 'money_format' ],
	];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace . '/wc_stripe',
			'/' . $this->rest_base . '(?:/(?P<id>.+))?',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_disputes' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => $this->get_disputes_route_args(),
			]
		);
	}

	/**
	 * Return route args.
	 *
	 * @return array
	 */
	public function get_disputes_route_args(): array {
		return $this->rest_args;
	}

	/**
	 * Retrieve a paginated list of Stripe  disputes.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_disputes( $request ) {
		$search_params = $request->get_params();

		if ( isset( $search_params['id'] ) ) {
			$stripe_url_ending = '/' . $search_params['id'];
		} else {
			$stripe_url_ending = '?' . WC_Stripe_REST_Helper::build_http_query_string_from_request( $request, $this->get_disputes_route_args() );
		}

		$response = WC_Stripe_API::retrieve( 'disputes' . $stripe_url_ending );

		if ( null === $response ) {
			return new WP_Error(
				'wc_stripe_disputes_error',
				__( 'Unable to retrieve disputes from Stripe.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 401 ]
			);
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( is_object( $response ) && isset( $response->error ) ) {
			$error_code    = isset( $response->error->code ) ? (string) $response->error->code : 'wc_stripe_api_error';
			$error_message = isset( $response->error->message ) ? (string) $response->error->message : __( 'Stripe API returned an error.', 'woocommerce-gateway-stripe' );

			return new WP_Error( $error_code, $error_message, [ 'status' => 400 ] );
		}

		if ( ! isset( $search_params['id'] ) ) {
			$allowed_fields = $this->stripe_response_allowed_fields;
		} else {
			$allowed_fields = $this->stripe_details_response_allowed_fields;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, $allowed_fields );

		return rest_ensure_response( $filtered_response );
	}

	/**
	 * Validate a 'query' parameter value.
	 *
	 * @param string $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_query_field( $value, WP_REST_Request $request, string $param ) {
		return WC_Stripe_REST_Validator::validate_query( $value, $request, $param, self::$rest_query_args );
	}

	/**
	 * Sanitize a 'query' parameter value.
	 *
	 * @param array $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 *
	 * @return mixed
	 */
	public static function sanitize_query_field( $value, WP_REST_Request $request, string $param ) {
		return WC_Stripe_REST_Validator::sanitize_query( $value, $request, $param, self::$rest_query_args );
	}
}
