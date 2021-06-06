<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCMedicalRecords;
use App\models\KCPatientEncounter;
use Exception;

class KCPatientMedicalRecordsController extends KCBase {

	public $db;

	/**
	 * @var KCRequest
	 */
	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

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

		if ( ! isset( $request_data['encounter_id'] ) ) {
			echo json_encode( [
				'status'  => false,
				'message' =>  esc_html__('Encounter not found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		$encounter_id          = $request_data['encounter_id'];
		$medical_records_table = $this->db->prefix . 'kc_' . 'medical_problems';
		$users_table           = $this->db->prefix . 'users';
		$static_data_table     = $this->db->prefix . 'kc_' . 'static_data';

		$query = "
			SELECT {$medical_records_table}.*,
		       patients.display_name AS patient_name,
		       outcome_table.label as outcome_label,
		       problem_type_table.label as problem_type_label
			FROM  {$medical_records_table}
		       LEFT JOIN {$static_data_table} outcome_table
		              ON {$medical_records_table}.outcome = outcome_table.value
		       LEFT JOIN {$static_data_table} problem_type_table
		              ON {$medical_records_table}.problem_type = problem_type_table.value
		       LEFT JOIN {$users_table} patients
		              ON {$medical_records_table}.patient_id = patients.id
            WHERE {$medical_records_table}.encounter_id = {$encounter_id} GROUP BY {$medical_records_table}.id ";


		$encounters = $this->db->get_results( $query, OBJECT );

		$total_rows = count( $encounters );

		if ( ! count( $encounters ) ) {
			echo json_encode( [
				'status'  => false,
				'message' =>  esc_html__('No medical records found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		echo json_encode( [
			'status'     => true,
			'message'    =>  esc_html__('Medical records', 'kc-lang'),
			'data'       => $encounters,
			'total_rows' => $total_rows
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
			'start_date'   => 'required',
			'problem_type' => 'required',
			'outcome'      => 'required',
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
			'start_date'   => date( 'Y-m-d', strtotime( $request_data['start_date'] ) ),
			'end_date'     => date( 'Y-m-d', strtotime( $request_data['end_date'] ) ),
			'encounter_id' => $request_data['encounter_id'],
			'patient_id'   => $patient_id,
			'problem_type' => $request_data['problem_type']['id'],
			'outcome'      => $request_data['outcome']['id'],
			'description'  => $request_data['description'],
		];

		$medical_records = new KCMedicalRecords();

		if ( ! isset( $request_data['id'] ) ) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$temp['added_by']   = get_current_user_id();
			$status             = $medical_records->insert( $temp );

		} else {
			$status = $medical_records->update( $temp, array( 'id' => $request_data['id'] ) );
		}

		echo json_encode( [
			'status'  => true,
			'message' =>  esc_html__('Medical record has been saved successfully', 'kc-lang')
		] );

	}

	public function edit() {

		if ( ! kcCheckPermission( 'medical_records_edit' ) || ! kcCheckPermission( 'medical_records_view' ) ) {
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

			$medical_records_table = $this->db->prefix . 'kc_' . 'medical_problems';
			$users_table           = $this->db->prefix . 'users';
			$static_data_table     = $this->db->prefix . 'kc_' . 'static_data';

			$query = "
				SELECT {$medical_records_table}.*,
			       patients.display_name AS patient_name,
			       outcome_table.label as outcome_label,
			       problem_type_table.label as problem_type_label
				FROM  {$medical_records_table}
			       LEFT JOIN {$static_data_table} outcome_table
			              ON {$medical_records_table}.outcome = outcome_table.value
			       LEFT JOIN {$static_data_table} problem_type_table
			              ON {$medical_records_table}.problem_type = problem_type_table.value
			       LEFT JOIN {$users_table} patients
			              ON {$medical_records_table}.patient_id = patients.id
	            WHERE {$medical_records_table}.id = {$id} GROUP BY {$medical_records_table}.id ";

			$medical_record = $this->db->get_results( $query, OBJECT );

			if ( count( $medical_record ) ) {
				$medical_record = $medical_record[0];

				$temp = [
					'id'           => $medical_record->id,
					'start_date'   => $medical_record->start_date,
					'end_date'     => $medical_record->end_date,
					'patient_id'   => $medical_record->patient_id,
					'encounter_id' => $medical_record->encounter_id,
					'outcome'      => [
						'id'    => $medical_record->outcome,
						'label' => $medical_record->outcome_label
					],
					'problem_type' => [
						'id'    => $medical_record->problem_type,
						'label' => $medical_record->problem_type_label
					],
					'description'  => $medical_record->description,
					'added_by'     => $medical_record->added_by,
				];


				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Medical records', 'kc-lang'),
					'data'    => $temp
				] );
			} else {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}


		} catch ( Exception $e ) {

			$code    = $e->getCode();
			$message = $e->getMessage();

			header( "Status: $code $message" );

			echo json_encode( [
				'status'  => false,
				'message' => $e->getMessage()
			] );
		}
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

			$results = ( new KCMedicalRecords() )->delete( [ 'id' => $id ] );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' =>  esc_html__('Medical record has been deleted successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception(  esc_html__('Medical record delete failed', 'kc-lang'), 400 );
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