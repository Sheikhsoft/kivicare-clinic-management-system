<?php

namespace App\Controllers\Api;

use App\baseClasses\KCBase;
use App\models\KCAppointment;
use App\models\KCDoctorClinicMapping;
use WP_REST_Response;
use WP_REST_Server;
use Exception;

class KCPatientAppointmentBooking extends KCBase {

	public $module = 'patient';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = 'wp-medical';

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/book-appointment', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'patientAppointmentBooking' ],
				'permission_callback' => '__return_true'
			) );

		} );
	}

	public function patientAppointmentBooking( $request ) {

		$postData = $request->get_params();

		$rules = [
			'appointment_start_date' => 'required',
			'appointment_start_time' => 'required',
			'visit_type'             => 'required',
			'clinic_id'              => 'required',
			'doctor_id'              => 'required',
			'patient_id'             => 'required',
			'status'                 => 'required',

		];

		$message = [
			'status'     => esc_html__('Status is required', 'kc-lang'),
			'patient_id' => esc_html__('Patient is required', 'kc-lang'),
			'clinic_id'  => esc_html__('Clinic is required', 'kc-lang'),
			'doctor_id'  => esc_html__('Doctor is required', 'kc-lang'),
		];

		$errors = kcValidateRequest( $rules, $postData, $message );

		if ( count( $errors ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($errors[0], 'kc-lang')
			] );
			die;
		}

		$end_time             = strtotime( "+15 minutes", strtotime( $postData['appointment_start_time'] ) );
		$appointment_end_time = date( 'h:i:s', $end_time );
		$appointment_date     = date( 'Y-m-d', strtotime( $postData['appointment_start_date'] ) );

		$temp = [
			'appointment_start_date' => $appointment_date,
			'appointment_start_time' => date( 'H:i:s', strtotime( $postData['appointment_start_time'] ) ),
			'appointment_end_date'   => $appointment_date,
			'appointment_end_time'   => $appointment_end_time,
			'visit_type'             => $postData['visit_type'],
			'clinic_id'              => $postData['clinic_id'],
			'doctor_id'              => $postData['doctor_id'],
			'patient_id'             => $postData['patient_id'],
			'description'            => $postData['description'],
			'status'                 => $postData['status']
		];

		$appointment = new KCAppointment();

		$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
		$appointment->insert( $temp );
		$message = 'Appointment has been added successfully';

		$response = new WP_REST_Response( [
			'status'  => true,
			'message' => esc_html__($message, 'kc-lang'),
			'data'    => $postData
		] );

		$response->set_status( 200 );

		return $response;
	}

}


