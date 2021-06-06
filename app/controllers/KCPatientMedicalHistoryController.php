<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCMedicalHistory;
use App\models\KCPatientEncounter;
use Exception;

class KCPatientMedicalHistoryController extends KCBase {

	public $db;

	/**
	 * @var KCRequest
	 */
	private $request;

	private $medical_history;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

		$this->medical_history = new KCMedicalHistory();

	}

	public function index() {

		if ( ! kcCheckPermission( 'medical_records_list' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();

		if ( ! isset( $request_data['encounter_id'] ) || ! isset( $request_data['type'] ) ) {
			echo json_encode( [
				'status'  => false,
				'message' =>  esc_html__('Encounter not found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		$encounter_id = $request_data['encounter_id'];
		$type         = $request_data['type'];

		$medical_history = collect( $this->medical_history->get_by( [
			'encounter_id' => $encounter_id,
			'type'         => $type
		] ) );

		if ( ! count( $medical_history ) ) {
			echo json_encode( [
				'status'  => false,
				'message' =>  esc_html__('No medical history found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		echo json_encode( [
			'status'  => true,
			'message' =>  esc_html__('Medical history', 'kc-lang'),
			'data'    => $medical_history,
		] );
	}

	public function save() {

		if ( ! kcCheckPermission( 'medical_records_add' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();

		$rules = [
			'encounter_id' => 'required',
			'type'         => 'required',
			'title'        => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => $errors[0]
			] );
			die;
		}

		$patient_encounter = ( new KCPatientEncounter )->get_by( [ 'id' => $request_data['encounter_id'] ], '=', true );
		$patient_id        = $patient_encounter->patient_id;

		if ( empty( $patient_encounter ) ) {
			echo json_encode( [
				'status'  => false,
				'message' =>  esc_html__("No encounter found", 'kc-lang')
			] );
			die;
		}

		$temp = [
			'encounter_id' => $request_data['encounter_id'],
			'patient_id'   => $patient_id,
			'type'         => $request_data['type'],
			'title'        => $request_data['title'],
		];

		if ( ! isset( $request_data['id'] ) ) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$temp['added_by']   = get_current_user_id();
			$id                 = $this->medical_history->insert( $temp );

		} else {
			$id     = $request_data['id'];
			$this->medical_history->update( $temp, array( 'id' => $request_data['id'] ) );
		}

		$data = $this->medical_history->get_by( [ 'id' => $id ], '=', true );


		echo json_encode( [
			'status'  => true,
			'message' =>  esc_html__('Medical history has been saved successfully', 'kc-lang'),
			'data'    => $data
		] );

	}

	public function delete() {


		if ( ! kcCheckPermission( 'medical_records_delete' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}

			$id = $request_data['id'];

			$results = $this->medical_history->delete( [ 'id' => $id ] );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' =>  esc_html__('Medical history has been deleted successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception( esc_html__('Medical history delete failed', 'kc-lang'), 400 );
			}


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