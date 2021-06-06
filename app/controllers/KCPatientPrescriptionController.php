<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCPatientEncounter;
use App\models\KCPrescription;
use Exception;

class KCPatientPrescriptionController extends KCBase {

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

		if ( ! kcCheckPermission( 'prescription_list' ) ) {
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
				'message' => esc_html__('Encounter not found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		$encounter_id       = $request_data['encounter_id'];
		$prescription_table = $this->db->prefix . 'kc_' . 'prescription';

		$query = "SELECT * FROM  {$prescription_table} WHERE encounter_id = {$encounter_id}";

		$prescriptions = collect( $this->db->get_results( $query, OBJECT ) )->map( function ( $data ) {
			$data->name = [
				'id'    => $data->name,
				'label' => $data->name
			];
            $data->frequency = [
                'id'    => $data->frequency,
                'label' => $data->frequency
            ];
			return $data;
		} );

		$total_rows = count( $prescriptions );

		if ( ! count( $prescriptions ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No prescription found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		echo json_encode( [
			'status'     => true,
			'message'    => esc_html__('Prescription records', 'kc-lang'),
			'data'       => $prescriptions,
			'total_rows' => $total_rows
		] );
	}

	public function save() {

		if ( ! kcCheckPermission( 'prescription_add' ) ) {
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
			'name'         => 'required',
			'frequency'    => 'required',
			'duration'     => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($errors[0], 'kc-lang')
			] );
			die;
		}

		$patient_encounter = ( new KCPatientEncounter )->get_by( [ 'id' => $request_data['encounter_id'] ], '=', true );
		$patient_id        = $patient_encounter->patient_id;

		if ( empty( $patient_encounter ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__("No encounter found", 'kc-lang')
			] );
			die;
		}

		$temp = [
			'encounter_id' => $request_data['encounter_id'],
			'patient_id'   => $patient_id,
			'name'         => $request_data['name']['id'],
			'frequency'    => $request_data['frequency']['id'],
			'duration'     => $request_data['duration'],
			'instruction'  => $request_data['instruction'],
		];

		$prescription = new KCPrescription();

		if ( ! isset( $request_data['id'] ) ) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$temp['added_by']   = get_current_user_id();
			$prescription_id    = $prescription->insert( $temp );
			$message            = esc_html__('Prescription has been saved successfully', 'kc-lang');

		} else {
			$prescription_id = $request_data['id'];
			$status          = $prescription->update( $temp, array( 'id' => $request_data['id'] ) );
			$message         = esc_html__('Prescription has been updated successfully', 'kc-lang');
		}

		$data = $prescription->get_by( [ 'id' => $prescription_id ], '=', true );
		$data->name = [
			'id'    => $data->name,
			'label' => $data->name
		];
		$data->frequency = [
			'id'    => $data->frequency,
			'label' => $data->frequency
		];

		echo json_encode( [
			'status'  => true,
			'message' => `$message`,
			'data'    => $data
		] );

	}

	public function edit() {

		if ( ! kcCheckPermission( 'prescription_edit' ) || ! kcCheckPermission( 'prescription_view' ) ) {
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

			$prescription_table = $this->db->prefix . 'kc_' . 'prescription';

			$query = "SELECT * FROM  {$prescription_table} WHERE id = {$id}";

			$prescription = $this->db->get_results( $query, OBJECT );

			if ( count( $prescription ) ) {
				$prescription = $prescription[0];

				$temp = [
					'id'           => $prescription->id,
					'patient_id'   => $prescription->patient_id,
					'encounter_id' => $prescription->encounter_id,
					'title'        => $prescription->title,
					'notes'        => $prescription->notes,
					'added_by'     => $prescription->added_by,
				];


				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Prescription record', 'kc-lang'),
					'data'    => $temp
				] );
			} else {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
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

	public function delete() {

		if ( ! kcCheckPermission( 'prescription_delete' ) ) {
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

			$results = ( new KCPrescription() )->delete( [ 'id' => $id ] );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Prescription has been deleted successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception( esc_html__('Prescription delete failed', 'kc-lang'), 400 );
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
