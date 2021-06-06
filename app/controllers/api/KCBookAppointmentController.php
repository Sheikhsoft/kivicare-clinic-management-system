<?php

namespace App\Controllers\Api;

use App\baseClasses\KCBase;
use App\models\KCAppointment;
use WP_REST_Response;
use WP_REST_Server;
use Exception;
use WP_User;

class KCBookAppointmentController extends KCBase {

	public $module = 'book-appointment';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVI_CARE_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-doctors-details', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getDoctors' ],
				'permission_callback' => '__return_true'
			) );


			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-time-slots', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'getTimeSlots' ],
				'permission_callback' => '__return_true'
			) );

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/save-appointment', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'saveAppointment' ],
				'permission_callback' => '__return_true'
			) );

		} );
	}

    public function saveAppointment($request) {

	    $formData = $request->get_params();

        try {

            if(!is_user_logged_in()) {
                throw new Exception( esc_html__('Sign in to book appointment', 'kc-lang'), 401 );
            }

            $userObj = wp_get_current_user();

            if (!in_array($this->getPatientRole(),$userObj->roles)) {
                throw new Exception( esc_html__('User must be patient to book appointment', 'kc-lang'), 401 );
            }

            $time_slot             = $formData['doctor_id']['timeSlot'];

            $end_time             = strtotime( "+" . $time_slot . " minutes", strtotime( $formData['appointment_start_time'] ) );
            $appointment_end_time = date( 'H:i:s', $end_time );
            $appointment_date     = date( 'Y-m-d', strtotime( $formData['appointment_start_date'] ) );

            $clinic_id = kcGetDefaultClinicId();

            (new KCAppointment())->insert([
                'appointment_start_date' => $appointment_date,
                'appointment_start_time' => date( 'H:i:s', strtotime( $formData['appointment_start_time'] ) ),
                'appointment_end_date'   => $appointment_date,
                'appointment_end_time'   => $appointment_end_time,
                'visit_type'             => $formData['visit_type'],
                'clinic_id'              => $clinic_id,
                'doctor_id'              => $formData['doctor_id']['id'],
                'patient_id'             => get_current_user_id(),
                'description'            => $formData['description'],
                'status'                 => $formData['status'],
                'created_at'             => current_time('Y-m-d H:i:s')
            ]);

            $response = new WP_REST_Response( [
                'status'  => true,
                'message' => esc_html__('Appointment has been booked successfully', 'kc-lang')
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

}
