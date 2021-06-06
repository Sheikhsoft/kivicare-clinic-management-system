<?php

namespace App\Controllers\Api;

use App\baseClasses\KCBase;
use WP_REST_Response;
use WP_REST_Server;
use App\baseClasses\KCRequest;
use Exception;
use WP_User;


class KCAuthController extends KCBase{

	public $module = 'patient-auth';

	public $nameSpace = 'wp-medical';

	function __construct() {

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace. '/api/v1/' .$this->module , '/login', array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [$this, 'patient_login'],
				'permission_callback' => '__return_true'
			));

			register_rest_route( $this->nameSpace. '/api/v1/' .$this->module , '/register', array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [$this, 'patient_register'],
				'permission_callback' => '__return_true'
			));

		} );

	}

	public function patient_login ($request) {

		$parameters = $request->get_params();

        $errors = kcValidateRequest([
            'username' => 'required',
            'password' => 'required',
        ], $parameters);

        if (count($errors)) {
            throw new Exception( esc_html__($errors[0], 'kc-lang'), 422 );
        }


        try {

            $errors = kcValidateRequest([
                'username' => 'required',
                'password' => 'required',
            ], $parameters);

            if (count($errors)) {
                throw new Exception( $errors[0], 422 );
            }

            $auth_success = wp_authenticate($parameters['username'], $parameters['password']);

            if (isset($auth_success->errors) && count($auth_success->errors)) {
                throw new Exception( esc_html__('Incorrect username and password', 'kc-lang'), 401 );
            }

            wp_set_current_user( $auth_success->ID, $auth_success->user_login );
            wp_set_auth_cookie( $auth_success->ID );
            do_action( 'wp_login', $auth_success->user_login );
            $wp_nonce = wp_create_nonce( 'wp_rest' );

            $response = new WP_REST_Response( [
                'status'  => true,
                'message' => esc_html__('Logged in successfully', 'kc-lang'),
                'data' => $wp_nonce
            ] );

            $response->set_status( 200 );


        } catch ( Exception $e ) {

	        $code    = esc_html__($e->getCode(), 'kc-lang');
	        $message = esc_html__($e->getMessage(), 'kc-lang');

            header( "Status: $code $message" );

            $response = new WP_REST_Response( [
                'status'  => true,
                'message' => $message,
                'data' => []
            ] );

            $response->set_status( $code );
        }

        return $response;

	}

	public function patient_register ($request) {

		try {

			$request_data = $request->get_params();

			$request_data['username'] = $request_data['first_name'] . rand( 111, 1000 );

			$user = wp_create_user( $request_data['username'], $request_data['mobile_number'], $request_data['user_email'] );

			$u               = new WP_User( $user );
			$u->display_name = $request_data['first_name'] . ' ' . $request_data['last_name'];
			wp_insert_user( $u );
			$u->set_role( $this->getPatientRole() );

			if($user) {

				$user_email_param = array (
					'username' => $request_data['username'],
					'user_email' => $request_data['user_email'],
					'password' => $request_data['password'],
					'email_template_type' => 'patient_register'
				);

				kcSendEmail($user_email_param);

			}

			if ( $user ) {
				$data    = $user;
				$status  = true;
				$message = 'Register successfully completed';
			} else {
				$data    = [];
				$status  = false;
				$message = 'Registration not successfully completed';
			}

			return [
				'data'    => $data,
				'status'  => $status,
				'message' => esc_html__($message, 'kc-lang')
			];

		} catch ( Exception $e ) {

			$code    = esc_html__($e->getCode(), 'kc-lang');
			$message = esc_html__($e->getMessage(), 'kc-lang');

			header( "Status: $code $message" );

			echo json_encode( [
				'status'  => false,
				'message' => $message
			] );
		}
	}

}

