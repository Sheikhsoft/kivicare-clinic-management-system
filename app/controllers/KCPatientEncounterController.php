<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCBill;
use App\models\KCCustomField;
use App\models\KCPatientEncounter;
use App\models\KCReceptionistClinicMapping;
use Exception;

class KCPatientEncounterController extends KCBase {

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

		$isPermission = false;

		if ( kcCheckPermission( 'patient_encounter_list' ) || kcCheckPermission( 'patient_encounters' ) ) {
			$isPermission = true;
		}

		if ( ! $isPermission ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
		if ( ! isset( $request_data['login_id'] ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => 'Patient id not found',
				'data'    => []
			] );
			wp_die();
		}

        $login_id              = $request_data['login_id'];
		$patient_encounter_table = $this->db->prefix . 'kc_' . 'patient_encounters';
		$clinics_table           = $this->db->prefix . 'kc_' . 'clinics';
		$users_table             = $this->db->prefix . 'users';

		$login_user = wp_get_current_user();
        $patient_condition = '';
        $doctor_condition = '';
        $patient_user_condition = '' ;
        $doctor_user_condition = '' ;

        if ($this->getPatientRole() === $login_user->roles[0]) {
            $patient_condition  = " AND patient_id = {$login_id} " ;
            $patient_user_condition =  " AND {$patient_encounter_table}.patient_id = {$login_id} " ;
		}
		if($request_data['patient_id'] > 0){
			$patient_user_condition =  " AND {$patient_encounter_table}.patient_id = {$request_data['patient_id']} " ;
		}

        if ($this->getDoctorRole() === $login_user->roles[0]) {
            $doctor_condition  = " AND doctor_id = {$login_id} " ;
            $doctor_user_condition =  " AND {$patient_encounter_table}.doctor_id = {$login_id} " ;
        }

		$count_query      = "SELECT count(*) AS count from {$patient_encounter_table} WHERE 0 = 0  {$patient_condition} {$doctor_condition} ";

		$encounters_count = $this->db->get_results( $count_query, OBJECT );

		$encounters = "SELECT {$patient_encounter_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       {$clinics_table}.name AS clinic_name
			FROM  {$patient_encounter_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$patient_encounter_table}.doctor_id = doctors.id
		       LEFT JOIN {$users_table} patients
		              ON {$patient_encounter_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$patient_encounter_table}.clinic_id = {$clinics_table}.id
            WHERE 0 = 0  {$patient_user_condition}  {$doctor_user_condition}  ORDER BY id DESC  ";
			$encounters = $this->db->get_results( $encounters, OBJECT );
		if ( ! count( $encounters ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No encounter found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		echo json_encode( [
			'status'     => true,
			'message'    => esc_html__('Encounter list', 'kc-lang'),
			'data'       => $encounters,
			'total_rows' => (int) $encounters_count[0]->count
		] );
	}

	public function save() {

		$isPermission = false;

		if ( kcCheckPermission( 'patient_encounter_add' ) || kcCheckPermission( 'patient_encounters' ) ) {
			$isPermission = true;
		}

		if ( ! $isPermission ) {
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
			'date'       => 'required',
			'clinic_id'  => 'required',
			'patient_id' => 'required',
			'status'     => 'required',

		];

		$message = [
			'status'     => esc_html__('Status is required', 'kc-lang'),
			'patient_id' => esc_html__('Patient is required', 'kc-lang'),
			'clinic_id'  => esc_html__('Clinic is required', 'kc-lang'),
			'doctor_id'  => esc_html__('Doctor is required', 'kc-lang'),
		];

		$errors = kcValidateRequest( $rules, $request_data, $message );

		if (count( $errors )) {
			echo json_encode( [
				'status'  => false,
				'message' => $errors[0]
			] );
			die;
		}
		if($this->getLoginUserRole() == 'kiviCare_receptionist') {
			$receptionis_id = get_current_user_id();
			$clinic_id =  (new KCReceptionistClinicMapping())->get_by([ 'receptionist_id' => $receptionis_id]);
			$request_data['clinic_id']['id'] = $clinic_id[0]->clinic_id;
		}
		$temp = [
			'encounter_date' => date( 'Y-m-d', strtotime( $request_data['date'] ) ),
			'patient_id'     => isset($request_data['patient_id']['id']) ? $request_data['patient_id']['id']: $request_data['patient_id'],
			'clinic_id'      => isset($request_data['clinic_id']['id']) ? $request_data['clinic_id']['id']:1,
			'doctor_id'      => isset($request_data['doctor_id']['id'])?$request_data['doctor_id']['id'] : $request_data['doctor_id'] ,
			'description'    => $request_data['description'],
			'status'         => $request_data['status'],
		];
		$patientEncounter = new KCPatientEncounter();


		if ( ! isset( $request_data['id'] ) ) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$temp['added_by']   = get_current_user_id();
			$encounter_id       = $patientEncounter->insert( $temp );
			$message            = esc_html__('Patient encounter has been saved successfully', 'kc-lang');

		} else {
			$encounter_id = $request_data['id'];
			$status       = $patientEncounter->update( $temp, array( 'id' => $request_data['id'] ) );
			$message      = esc_html__('Patient encounter has been updated successfully', 'kc-lang');
		}

		echo json_encode( [
			'status'  => true,
			'message' => $message,
			'data'=>$encounter_id
		] );

	}

	public function edit() {

		$isPermission = false;

		if ( kcCheckPermission( 'patient_encounter_edit' ) || kcCheckPermission( 'patient_encounter_view' ) || kcCheckPermission( 'patient_encounters' ) ) {
			$isPermission = true;
		}

		if ( ! $isPermission ) {
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

			$patient_encounter_table = $this->db->prefix . 'kc_' . 'patient_encounters';
			$clinics_table           = $this->db->prefix . 'kc_' . 'clinics';
			$users_table             = $this->db->prefix . 'users';

			$query = "
			SELECT {$patient_encounter_table}.*,
			   doctors.display_name  AS doctor_name,
			   patient.display_name  AS patient_name,
		       {$clinics_table}.name AS clinic_name
			FROM  {$patient_encounter_table}
		       LEFT JOIN {$users_table} doctors
					  ON {$patient_encounter_table}.doctor_id = doctors.id
			   LEFT JOIN {$users_table} patient
					ON {$patient_encounter_table}.patient_id = patient.id
		       LEFT JOIN {$clinics_table}
		              ON {$patient_encounter_table}.clinic_id = {$clinics_table}.id
            WHERE {$patient_encounter_table}.id = {$id} LIMIT 1";

			$encounter = $this->db->get_results( $query, OBJECT );
			if ( count( $encounter ) ) {
				$encounter = $encounter[0];

				$temp = [
					'id'            => $encounter->id,
					'date'          => $encounter->encounter_date,
					'patient_id'    => [
						'id'    => $encounter->patient_id,
						'label' => $encounter->patient_name
					],
					'clinic_id'     => $encounter->clinic_id,
					'doctor_id'     => [
						'id'    => $encounter->doctor_id,
						'label' => $encounter->doctor_name
					],
					'description'   => $encounter->description,
					'status'        => $encounter->status,
					'added_by'      => $encounter->added_by,
				];
				echo json_encode( [
					'status'  => true,
					'message' => 'Encounter data',
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

		$isPermission = false;

		if ( kcCheckPermission( 'patient_encounter_delete' ) || kcCheckPermission( 'patient_encounters' ) ) {
			$isPermission = true;
		}

		if ( ! $isPermission ) {
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
			$results = ( new KCPatientEncounter() )->delete( [ 'id' => $id ] );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Encounter has been deleted successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception( 'Patient encounter delete failed', 400 );
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

	public function details() {

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}

			$id = $request_data['id'];

			$encounter = $this->getEncounterData( $id );

			if ( $encounter ) {

				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Encounter details', 'kc-lang'),
					'data'    => $encounter
				] );

			} else {
				throw new Exception(  esc_html__('Encounter not found', 'kc-lang'), 400 );
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

	public function saveCustomField() {

		if ( ! kcCheckPermission( 'patient_encounter_add' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
		if ( ! isset( $request_data['id'] ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 404,
				'message'     =>  esc_html__('encounter id not found', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$custom_fields = KCCustomField::getRequiredFields( 'patient_encounter_module' );
		
		if ( $request_data['custom_fields'] !== [] ) {
			kvSaveCustomFields( 'patient_encounter_module', $request_data['doctor_id'], $request_data['custom_fields'] );
		}

		echo json_encode( [
			'status'  => true,
			'message' =>  esc_html__('Encounter data has been saved', 'kc-lang'),
		] );
	}

	public function getEncounterData( $id ) {

		$patient_encounter_table = $this->db->prefix . 'kc_' . 'patient_encounters';
		$clinics_table           = $this->db->prefix . 'kc_' . 'clinics';
		$users_table             = $this->db->prefix . 'users';

		$query = "
			SELECT {$patient_encounter_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       patients.user_email AS patient_email,
		       {$clinics_table}.name AS clinic_name
			FROM  {$patient_encounter_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$patient_encounter_table}.doctor_id = doctors.id
              LEFT JOIN {$users_table} patients
		              ON {$patient_encounter_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$patient_encounter_table}.clinic_id = {$clinics_table}.id
            WHERE {$patient_encounter_table}.id = {$id} LIMIT 1";

		$encounter = $this->db->get_results( $query, OBJECT );

		if ( count( $encounter ) ) {
			$encounter[0]->custom_fields = kcGetCustomFields('patient_encounter_module', $encounter[0]->doctor_id);
			return  $encounter[0];
		} else {
			return null;
		}
	}


	public function updateStatus() {

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}

			$id                = $request_data['id'];
			$patient_encounter = new KCPatientEncounter();
			$encounter         = $patient_encounter->get_by( [ 'id' => $id ], '=', true );

			if ( $encounter === [] ) {
				throw new Exception(  esc_html__('Encounter not found', 'kc-lang'), 400 );
			}

			if ( (string) $request_data['status'] === '0' ) {
				( new KCAppointment() )->update( [ 'status' => '3' ], [ 'id' => $encounter->appointment_id ] );
				( new KCBill() )->update( [ 'status' => 1 ], [ 'encounter_id' => $encounter->id ] );
			}

			$patient_encounter->update( [ 'status' => $request_data['status'] ], [ 'id' => $id ] );

			echo json_encode( [
				'status'  => true,
				'message' =>  esc_html__('Encounter status has been updated', 'kc-lang')
			] );

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