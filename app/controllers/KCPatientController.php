<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCPatientEncounter;
use App\models\KCMedicalHistory;
use App\models\KCMedicalRecords;
use App\models\KCPatientClinicMapping;
use App\models\KCReceptionistClinicMapping;
use App\models\KCDoctorClinicMapping;
use App\models\KCClinic;
use Exception;
use WP_User;
use WP_User_Query;

class KCPatientController extends KCBase {

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

		if ( ! kcCheckPermission( 'patient_list' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}
		$active_domain = $this->getAllActivePlugin();
		$userObj = wp_get_current_user();

		$user_id = get_current_user_id();
		$request_data = $this->request->getInputs();

		$table_name = $this->db->prefix . 'kc_' . 'patient_clinic_mappings';
		$user_table = $this->db->prefix.'usermeta';
        $patientCount = collect(get_users( [
			'role' => $this->getPatientRole(),
		] ));

		$args['role']           = $this->getPatientRole();
		$args['number']         = $request_data['limit'];
		$args['offset']         = $request_data['offset'];
		$args['search_columns'] = [$request_data['searchKey'] ];
		$args['search']         = '*' . $request_data['searchValue'] . '*';
		$args['orderby']        = 'ID';
		$args['order']          = 'DESC';
		if(current_user_can('administrator') || $this->getLoginUserRole() === 'kiviCare_doctor'){
			$patients = collect(get_users( $args ));
		}else{
			$user_id = get_current_user_id();
            switch ($this->getLoginUserRole()) {
                case 'kiviCare_receptionist':
					if($active_domain === $this->kiviCareProOnName()){
                    	$clinic_id =  (new KCReceptionistClinicMapping())->get_by([ 'receptionist_id' => $user_id]);
						$clinic_id = $clinic_id[0]->clinic_id;
						$query = "SELECT DISTINCT `patient_id` FROM {$table_name} WHERE `clinic_id` =". $clinic_id ;

					}else{
						$clinic_id = kcGetDefaultClinicId();
					}
                    break;
                case 'kiviCare_clinic_admin':
					if($active_domain === $this->kiviCareProOnName()){
                    	$clinic_id =  (new KCClinic())->get_by([ 'clinic_admin_id' => $user_id]);
						$clinic_id = $clinic_id[0]->id;
						$query = "SELECT DISTINCT `patient_id` FROM {$table_name} WHERE `clinic_id` =". $clinic_id;

					}else{
						$clinic_id = kcGetDefaultClinicId();
					}
                    break;
                default:
                    # code...
                    break;
            }
			$args['patient_added_by'] = $user_id;
			
			$result = collect($this->db->get_results($query))->pluck('patient_id');

			$patients = get_users( $args );
			$patients = collect($patients)->whereIn('ID',$result)->values();

		}
		
	
		if (in_array($this->getDoctorRole(), $userObj->roles)) {
			$appointments = collect((new KCAppointment)->get_by(['doctor_id' => $userObj->ID]))->pluck('patient_id')->toArray();
			$get_doctor_patient = collect($this->db->get_results("SELECT *  FROM $user_table WHERE `meta_value` = ".get_current_user_id()." AND
			 `meta_key` LIKE 'patient_added_by'"))->pluck('user_id')->toArray();
			$all_user = array_merge($get_doctor_patient,$appointments);
			$patients = $patients->whereIn('ID', $all_user);
			$patientCount = $patientCount->whereIn('ID', $appointments)->count();
		} else {
			$patients = collect(get_users( $args ));
			$patientCount = $patientCount->count();	
		}
		if ( ! count( $patients ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No patient found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		$data = [];

		foreach ( $patients as $key => $patient ) {
			$user_meta = get_user_meta( $patient->ID, 'basic_data', true );
			if($active_domain === $this->kiviCareProOnName()){
				$clinic_mapping = (new KCPatientClinicMapping())->get_by([ 'patient_id' => $patient->ID]);
				$clinic_name =  (new KCClinic())->get_by([ 'id' => $clinic_mapping[0]->clinic_id]);
				if(count($clinic_name) === 0){
					$clinic_name =  (new KCClinic())->get_by([ 'id' => kcGetDefaultClinicId()]);
				}
				
			}else{
				$clinic_name =  (new KCClinic())->get_by([ 'id' => kcGetDefaultClinicId()]);
			}

			$data[ $key ]['ID']              = $patient->ID;
			$data[ $key ]['display_name']    = $patient->data->display_name;
			$data[ $key ]['user_email']      = $patient->data->user_email;
			$data[ $key ]['user_status']     = $patient->data->user_status;
			$data[ $key ]['user_registered'] = $patient->data->user_registered;
			$data[$key]['clinic_id'] = isset($clinic_mapping[0]->clinic_id) ? $clinic_mapping[0]->clinic_id: kcGetDefaultClinicId();
			$data[$key]['clinic_name'] = $clinic_name[0]->name;

			if ( $user_meta !== null ) {
				$basic_data                    = json_decode( $user_meta );
				$data[ $key ]['mobile_number'] = $basic_data->mobile_number;
				$data[ $key ]['gender']        = $basic_data->gender;
				$data[ $key ]['dob']           = $basic_data->dob;
				$data[ $key ]['address']       = $basic_data->address;
				$data[ $key ]['blood_group']   = $basic_data->blood_group;
					$get_uid = get_user_meta( $patient->ID, 'patient_unique_id',true);
					if(!empty($get_uid)){
						$data[ $key ]['u_id']   = $get_uid;

					}else{
						$get_unique_id =  get_option(KIVI_CARE_PREFIX . 'patient_id_setting',true);
						$data[ $key ]['u_id']   =$get_unique_id['prefix_value'].kcGenerateString(6).$get_unique_id['postfix_value'];
					}
					
			}
		}

		echo json_encode( [
			'status'     => true,
			'message'    => esc_html__('Patient list', 'kc-lang'),
			'data'       => array_values($data),
			'total_rows' => $patientCount
		] );
	}

	public function save() {
		$isPermission = false;
		$active_domain = $this->getAllActivePlugin();
		if ( kcCheckPermission( 'patient_add' ) || kcCheckPermission( 'patient_profile' ) ) {
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
			'first_name'    => 'required',
			'last_name'     => 'required',
			'user_email'    => 'required|email',
			'mobile_number' => 'required',
			'dob'           => 'required',
			'gender'        => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		$username = kcGenerateUsername( $request_data['first_name'] );
		

		$password = kcGenerateString( 12 );

		if ( count( $errors ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($errors[0], 'kc-lang')
			] );
			die;
		}

		$temp = [
			'mobile_number' => $request_data['mobile_number'],
			'gender'        => $request_data['gender'],
			'dob'           => $request_data['dob'],
			'address'       => $request_data['address'],
			'city'          => $request_data['city'],
			'state'         => $request_data['state'],
			'country'       => $request_data['country'],
			'postal_code'   => $request_data['postal_code'],
			'blood_group'   => $request_data['blood_group'],
		];
		
		if ( ! isset( $request_data['ID'] ) ) {

			$user            = wp_create_user( $username, $password, $request_data['user_email'] );
			$u               = new WP_User( $user );
			$u->display_name = $request_data['first_name'] . ' ' . $request_data['last_name'];
			wp_insert_user( $u );
			$u->set_role( $this->getPatientRole() );

			if ( $user ) {

				$user_email_param = array(
					'username'            => $username,
					'user_email'          => $request_data['user_email'],
					'password'            => $password,
					'email_template_type' => 'patient_register',
				);

				kcSendEmail($user_email_param);
			}
			$request_data['custom_fields'] = json_decode(stripslashes( $request_data['custom_fields']));
            if (isset($request_data['custom_fields']) && $request_data['custom_fields'] !== []) {
                kvSaveCustomFields('patient_module', $user, $request_data['custom_fields']);
            }

            // Insert Patient Clinic mapping...
			if($active_domain === $this->kiviCareProOnName()){
				$patient_mapping = new KCPatientClinicMapping;

				$user_id = get_current_user_id();
				if($this->getLoginUserRole() == 'kiviCare_clinic_admin'){
					$clinic_id = (new KCClinic())->get_by([ 'clinic_admin_id' => $user_id]);
					$clinic_id = $clinic_id[0]->id;
				}elseif ($this->getLoginUserRole() == 'kiviCare_receptionist') {
					$clinic_id =  (new KCReceptionistClinicMapping())->get_by([ 'receptionist_id' => $user_id]);
					$clinic_id = $clinic_id[0]->clinic_id;
				}else{
					$request_data['clinic_id'] = json_decode(stripslashes( $request_data['clinic_id']));
					$clinic_id =isset($request_data['clinic_id']->id)?$request_data['clinic_id']->id: 1;
				}
				$new_temp = [
					'patient_id' => $user,
					'clinic_id' => $clinic_id,
					'created_at' => current_time('Y-m-d H:i:s')
				];
	
				$patient_mapping->insert($new_temp);
			}
			update_user_meta($user, 'first_name',$request_data['first_name'] );
			update_user_meta($user, 'last_name', $request_data['last_name'] );
			
			update_user_meta( $user, 'basic_data', json_encode( $temp, JSON_UNESCAPED_UNICODE ));
			update_user_meta( $user, 'patient_added_by', get_current_user_id() );
				update_user_meta( $user, 'patient_unique_id',$request_data['u_id']) ;
			$message = esc_html__('Patient has been saved successfully', 'kc-lang');
			$user_id = $user ;
		} else {
			if($active_domain === $this->kiviCareProOnName()){
				$patient_mapping = new KCPatientClinicMapping;
				( new KCPatientClinicMapping() )->delete( [ 'patient_id' => $request_data['ID'] ] );
			}
			wp_update_user(
				array(
					'ID'           => $request_data['ID'],
					'user_login'   => $request_data['username'],
					'user_email'   => $request_data['user_email'],
					'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
				)
			);
			$user_id = get_current_user_id();
			if($this->getLoginUserRole() == 'kiviCare_clinic_admin'){
				$clinic = (new KCClinic())->get_by([ 'clinic_admin_id' => $user_id]);
				$clinic = $clinic[0]->id;
			}elseif ($this->getLoginUserRole() == 'kiviCare_receptionist') {
				$clinic_id =  (new KCReceptionistClinicMapping())->get_by([ 'receptionist_id' => $user_id]);
				$clinic_id = $clinic_id[0]->clinic_id;
			}else{
				$request_data['clinic_id'] = json_decode(stripslashes( $request_data['clinic_id']));
				$clinic_id =isset($request_data['clinic_id']->id)?$request_data['clinic_id']->id: 1;
			}
			$new_temp = [
				'patient_id' => $request_data['ID'],
				'clinic_id' => $clinic_id,
				'created_at' => current_time('Y-m-d H:i:s')
			];
			if($active_domain === $this->kiviCareProOnName()){
				$patient_mapping->insert($new_temp);
			}
			update_user_meta( $request_data['ID'], 'basic_data', json_encode( $temp, JSON_UNESCAPED_UNICODE ) );
			update_user_meta($request_data['ID'], 'first_name',$request_data['first_name'] );
			update_user_meta($request_data['ID'], 'last_name', $request_data['last_name'] );
            $request_data['custom_fields'] = json_decode(stripslashes( $request_data['custom_fields']));
            if (isset($request_data['custom_fields']) && $request_data['custom_fields'] !== []) {
                kvSaveCustomFields('patient_module', $request_data['ID'], $request_data['custom_fields']);

            }
			$user_id = $request_data['ID'] ;
			$message = esc_html__('Patient has been updated successfully', 'kc-lang');
		}

		if($request_data['profile_image'] != '' && isset($request_data['profile_image']) && $request_data['profile_image'] != null ){
            $attachment_id = media_handle_upload('profile_image', 0);
            update_user_meta( $user_id , 'patient_profile_image',  $attachment_id  );
        }

		if ( $user->errors ) {
			echo json_encode( [
				'status'  => false,
				'message' => $user->get_error_message() ? $user->get_error_message() : 'Patient save operation has been failed'
			] );
		} else {
			echo json_encode( [
				'status'  => true,
				'message' => $message
			] );
		}
	}

	public function edit() {

		$isPermission = false;
		$active_domain = $this->getAllActivePlugin();
		global $wpdb;
		if ( kcCheckPermission( 'patient_edit' ) || kcCheckPermission( 'patient_view' ) || kcCheckPermission( 'patient_profile' ) ) {
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
		$table_name = collect((new KCClinic)->get_all());
		
		try {

			if ( ! isset( $request_data['id'] ) ) {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}

			$id = $request_data['id'];
			if($active_domain === $this->kiviCareProOnName()){
				$clinic_id =  (new KCPatientClinicMapping())->get_by([ 'patient_id' => $id]);
			}else{
				$clinic_id = kcGetDefaultClinicId();
			}
			if($active_domain === $this->kiviCareProOnName()){
				$clinics = collect((new KCPatientClinicMapping)->get_by(['patient_id' => $id]))->pluck('clinic_id')->toArray();
				$clinics = $table_name->whereIn('id', $clinics);
			}
			$user = get_userdata( $id );
			unset( $user->user_pass );
			$image_attachment_id = get_user_meta($id,'patient_profile_image',true);
			$user_image_url = wp_get_attachment_url($image_attachment_id);

			$full_name = explode( ' ', $user->display_name );
			$user_data  = get_user_meta( $id, 'basic_data', true );
			$first_name =  	get_user_meta( $id, 'first_name', true );
			$last_name  = get_user_meta( $id, 'last_name', true );

			$data             = (object) array_merge( (array) $user->data, (array) json_decode( $user_data ) );
			$data->first_name = $first_name;
			$data->username   = $data->user_login;
			$data->last_name  = $last_name;
			foreach($clinics as $d ){

                $list[] = [
                    'id'    => $d->id,
                     'label' => $d->name,
                 ];
            }
            $data->clinic_id = $list;
			
			$custom_filed = kcGetCustomFields('patient_module', $id);
			$data->user_profile =$user_image_url;
				$data->u_id   = $request_data['p_id'];
					if ( $data ) {
				echo json_encode( [
					'status'  => true,
					'message' => 'Patient data',
					'data'    => $data,
                    'custom_filed'=>$custom_filed
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

		if ( ! kcCheckPermission( 'patient_delete' ) ) {
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

			delete_user_meta( $id, 'basic_data' );
			delete_user_meta( $id, 'first_name' );
			delete_user_meta( $id, 'last_name' );

			if (is_plugin_active($this->teleMedAddOnName())) {
				apply_filters('kct_delete_patient_meeting', ['patient_id' => $id]);
			}

            (new KCPatientEncounter())->delete(['patient_id' => $id]);
            (new KCMedicalHistory())->delete(['patient_id' => $id]);
            (new KCMedicalRecords())->delete(['patient_id' => $id]);
            (new KCAppointment())->delete(['patient_id' => $id]);
			$results = wp_delete_user( $id );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' =>  esc_html__('Patient has been deleted successfully', 'kc-lang'),
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
	public function savePatientSetting(){
		$setting = $this->request->getInputs();
        try{
            if(isset($setting)){
                $config = array(
                    'prefix_value' =>$setting['prefix_value'],
                    'postfix_value'=>$setting['postfix_value'],
                    'enable'=>$setting['enable']
                );
                update_option( KIVI_CARE_PREFIX . 'patient_id_setting',$config );
				echo json_encode( [
                    'status' => true,
                    'message' => esc_html__('Unique id setting successfully', 'kcp-lang')
				] );
            }
        }catch (Exception $e) {
			echo json_encode( [
                'status' => false,
                'message' => esc_html__('Unique id setting not saved', 'kcp-lang')
			] );
        }

    }
    public function editPatientSetting(){
        $get_patient_data = get_option(KIVI_CARE_PREFIX . 'patient_id_setting',true);

        if ( gettype($get_patient_data) != 'boolean' ) {
			echo json_encode( [
              'data'=> $get_patient_data,
              'status' => true,
			  ] );
        } else {
			echo json_encode( [
              'data'=> [],
              'status' => false,
			  ] );
        }
    }
    public function getPatientUid(){
        $get_unique_id =  get_option(KIVI_CARE_PREFIX . 'patient_id_setting',true);
        $patient_uid = $get_unique_id['prefix_value'].kcGenerateString(6).$get_unique_id['postfix_value'];
		echo json_encode( [
            'data'=> $patient_uid,
            'status' => true,
		] );
    }
	public function googleCalPatient(){
		$request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_patient_google_cal', [
			'data'=>$request_data
		]);
        echo json_encode($response);
	}
	public function googleEditPatient(){
		$request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_patient_edit_google_cal', []);
        echo json_encode($response);
	}
}
