<?php
/**
 * Class WC_Stripe_REST_Balance_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing Stripe payment intents data to the admin UI.
 *
 * The controller acts as a proxy that forwards the received parameters to the remote Stripe API and returns the received response.
 *
 * @since 10.9.0
 */
class WC_Stripe_REST_Balance_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'balance';

	/**
	 * Endpoint args.
	 *
	 * @var array
	 */
	protected $rest_args = [];

	/**
	 * Endpoint query args.
	 *
	 * @var array
	 */
	protected static $rest_query_args = [];

	protected array $stripe_response_allowed_fields = [];

	protected array $stripe_details_response_allowed_fields = [];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace . '/wc_stripe',
			'/balance',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_balance' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [],
			]
		);
	}

	/**
	 * Retrieve a paginated list of Stripe payment intents.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_balance( $request ) {
		$response = WC_Stripe_API::retrieve( 'balance' );

		if ( null === $response ) {
			return new WP_Error(
				'wc_stripe_balance_error',
				__( 'Unable to retrieve payment intents from Stripe.', 'woocommerce-gateway-stripe' ),
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

		return rest_ensure_response( $response );
	}
}
