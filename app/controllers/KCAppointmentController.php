<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCClinicSession;
use App\models\KCPatientEncounter;
use App\models\KCAppointmentServiceMapping;
use App\models\KCBillItem;
use App\models\KCClinic;
use  App\models\KCReceptionistClinicMapping;
use DateTime;
use Exception;

class KCAppointmentController extends KCBase {

	public $db;

	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

	}

	public function index() {

		if ( ! kcCheckPermission( 'appointment_list' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
		$users_table        = $this->db->prefix . 'users';
		$appointments_table = $this->db->prefix . 'kc_' . 'appointments';
		$clinics_table      = $this->db->prefix . 'kc_' . 'clinics';
		$static_data        = $this->db->prefix . 'kc_' . 'static_data';
		$start_date         = $request_data['start_date'];
		$end_date           = $request_data['end_date'];
		$appointments_service_table = $this->db->prefix . 'kc_' . 'appointment_service_mapping';
		$service_table = $this->db->prefix . 'kc_' . 'services';
		$query = "
			SELECT {$appointments_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       static_data.label AS type_label,
		       {$clinics_table}.name AS clinic_name
			FROM  {$appointments_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$appointments_table}.doctor_id = doctors.id
		       LEFT JOIN {$users_table} patients
		              ON {$appointments_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$appointments_table}.clinic_id = {$clinics_table}.id
		       LEFT JOIN {$static_data} static_data
		              ON {$appointments_table}.visit_type = static_data.value
            WHERE {$appointments_table}.appointment_start_date > '{$start_date}' AND {$appointments_table}.appointment_start_date < '{$end_date}' ";

		$user = wp_get_current_user();

		if ( in_array( $this->getDoctorRole(), $user->roles ) ) {
			$query .= " AND {$appointments_table}.doctor_id = " . $user->ID;
		} elseif ( in_array( $this->getPatientRole(), $user->roles ) ) {
			$query .= " AND {$appointments_table}.patient = " . $user->ID;
		}

		$appointments     = $this->db->get_results( $query, OBJECT );
		$new_appointments = [];

		if ( count( $appointments ) ) {
			foreach ( $appointments as $key => $appointment ) {
				
	
				$zoom_config_data = get_user_meta($appointment->doctor_id, 'zoom_config_data', true);
				$get_service =  "SELECT {$appointments_table}.id,{$service_table}.name AS service_name,{$service_table}.id AS service_id FROM {$appointments_table}
				LEFT JOIN {$appointments_service_table} ON {$appointments_table}.id = {$appointments_service_table}.appointment_id JOIN {$service_table} 
				ON {$appointments_service_table}.service_id = {$service_table}.id WHERE 0 = 0";
				$new_appointments[ $key ]['id']                     = $appointment->id;
				$new_appointments[ $key ]['date']                   = $appointment->appointment_start_date . ' ' . $appointment->appointment_start_time;
				$new_appointments[ $key ]['endDate']                = $appointment->appointment_end_date . ' ' . $appointment->appointment_end_time;
				$new_appointments[ $key ]['appointment_start_date'] = $appointment->appointment_start_date;
				$new_appointments[ $key ]['appointment_start_time'] = date( 'h:i A', strtotime( $appointment->appointment_start_time ) );
				$new_appointments[ $key ]['visit_type']             = $appointment->visit_type;
				$new_appointments[ $key ]['description']            = $appointment->description;
				$new_appointments[ $key ]['title']                  = $appointment->patient_name;
				$new_appointments[ $key ]['clinic_id']              = [
					'id'    => $appointment->clinic_id,
					'label' => $appointment->clinic_name
				];
				$new_appointments[ $key ]['doctor_id']              = [
					'id'    => $appointment->doctor_id,
					'label' => $appointment->doctor_name
				];
				$new_appointments[ $key ]['patient_id']             = [
					'id'   => $appointment->patient_id,
					'label' => $appointment->patient_name
				];
				$new_appointments[ $key ]['clinic_name']            = $appointment->clinic_name;
				$new_appointments[ $key ]['doctor_name']            = $appointment->doctor_name;
				$new_appointments[ $key ]['status']                 = $appointment->status;
				$services = collect( $this->db->get_results( $get_service, OBJECT ) )->where('id', $appointment->id);
				$service_array=[];
				foreach ($services as $service) {
					$service_array[] =  $service->service_name;
				}
				$str = implode (", ", $service_array);
				$new_appointments[ $key ]['all_services']= $str;
				if ( $appointment->status === 0 ) {
					$new_appointments[ $key ]['color'] = '#f5365c';
				} elseif ( $appointment->status === '1' || $appointment->status === '0' ) {
					$new_appointments[ $key ]['color'] = '#3490dc';
				}

			}
		}

		// Remove duplicate array...
		$tempArr          = array_unique( array_column( $new_appointments, 'id' ) );
		$new_appointments = array_values( array_intersect_key( $new_appointments, $tempArr ) );
		if ( ! count( $appointments ) ) {

			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No appointments found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		echo json_encode( [
			'status'  => true,
			'message' => esc_html__('Appointment list','kc-lang'),
			'data'    => $new_appointments
		] );
	}

	public function save() {

		global $wpdb;
		$table_name =  $wpdb->prefix . 'users';
		$active_domain =$this->getAllActivePlugin();

		if ( ! kcCheckPermission( 'appointment_add' ) ) {
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
			'appointment_start_date' => 'required',
			'appointment_start_time' => 'required',
			'clinic_id'              => 'required',
			'doctor_id'              => 'required',
			'patient_id'             => 'required',
			'status'                 => 'required',

		];

		$message = [
			'status'     => esc_html__('Status is required', 'kc-lang'),
			'patient_id' => esc_html__('Patient is required','kc-lang'),
			'clinic_id'  => esc_html__('Clinic is required','kc-lang'),
			'doctor_id'  => esc_html__('Doctor is required','kc-lang'),
		];

		$errors = kcValidateRequest( $rules, $request_data, $message );

		if ( count( $errors ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($errors[0], 'kc-lang')
			] );
			die;
		}

        $clinic_session_table = $wpdb->prefix . 'kc_' . 'clinic_sessions';
        $appointment_day = strtolower(date('l', strtotime($request_data['appointment_start_date']))) ;
        $day_short = substr($appointment_day, 0, 3);

        $query = "SELECT * FROM {$clinic_session_table}  WHERE `doctor_id` = ".$request_data['doctor_id']['id']." AND `clinic_id` = ".$request_data['clinic_id']['id']."  AND ( `day` = '{$day_short}' OR `day` = '{$appointment_day}') ";

        $clinic_session = collect($wpdb->get_results($query, OBJECT));
		
		$time_slot             = isset($clinic_session[0]->time_slot) ? $clinic_session[0]->time_slot : 15;

		$end_time             = strtotime( "+" . $time_slot . " minutes", strtotime( $request_data['appointment_start_time'] ) );
		$appointment_end_time = date( 'H:i:s', $end_time );
		$appointment_date     = date( 'Y-m-d', strtotime( $request_data['appointment_start_date'] ) );

		$temp = [
			'appointment_start_date' => $appointment_date,
			'appointment_start_time' => date( 'H:i:s', strtotime( $request_data['appointment_start_time'] ) ),
			'appointment_end_date'   => $appointment_date,
			'appointment_end_time'   => $appointment_end_time,
			'clinic_id'              => $request_data['clinic_id']['id'],
			'doctor_id'              => $request_data['doctor_id']['id'],
			'patient_id'             => $request_data['patient_id']['id'],
			'description'            => $request_data['description'],
			'status'                 => $request_data['status'],
		];
		
		$appointment = new KCAppointment();

        $temp['visit_type'] = str_replace(" ","_", $request_data['visit_type']['id']);

		if ( isset( $request_data['id'] ) && $request_data['id'] !== "" ) {
			$appointment_id = $request_data['id'];
			$appointment->update( $temp, array( 'id' => $request_data['id'] ) );
			( new KCAppointmentServiceMapping() )->delete( [ 'appointment_id' => $appointment_id ] );
			(new KCPatientEncounter())->update([
				'encounter_date' => $appointment_date,
				'patient_id'             => $request_data['patient_id']['id'],
				'doctor_id'              => $request_data['doctor_id']['id'],
				'clinic_id'              => $request_data['clinic_id']['id'],
				'description'            => $request_data['description'],
			], ['appointment_id' => $appointment_id]);
            if (isset($request_data['custom_fields']) && $request_data['custom_fields'] !== []) {
                kvSaveCustomFields('appointment_module',$appointment_id, $request_data['custom_fields']);
            }
			$message = esc_html__('Appointment has been updated successfully', 'kc-lang');

		} else {
			$temp['created_at'] = current_time('Y-m-d H:i:s');
			$appointment_id = $appointment->insert( $temp );
			$query = "SELECT * FROM {$table_name} WHERE `ID` = '{$request_data["patient_id"]["id"]}' ";
			$doctor_query = "SELECT * FROM {$table_name} WHERE `ID` = '{$request_data["doctor_id"]["id"]}'";
			$patient_data = $wpdb->get_results($query, OBJECT);
			$doctor_data = $wpdb->get_results($doctor_query, OBJECT);
			$doctor_name = $doctor_data[0]->display_name;
			$username = kcGenerateUsername( $request_data['first_name'] );

			// email send
			if($request_data['status'] == 1){

				$user_email_param = array(
					'user_email' => $patient_data[0]->user_email,
					'appointment_date' => $appointment_date,
					'doctor_name'=>$doctor_name,
					'appointment_time' => date( 'H:i:s', strtotime( $request_data['appointment_start_time'] ) ),
					'email_template_type' => $this->getPluginPrefix() . 'book_appointment'
				);

				kcSendEmail($user_email_param);

				if(isset($request_data["doctor_id"]["id"])) {
					$doctor_mail_param = array(
						'user_email' => $doctor_data[0]->user_email,
						'appointment_date' => $appointment_date,
						'appointment_time' => date( 'H:i:s', strtotime( $request_data['appointment_start_time'] ) ),
						'patient_name'=> $patient_data[0]->display_name,
						'email_template_type' => $this->getPluginPrefix() . 'doctor_book_appointment'
					);
					kcSendEmail($doctor_mail_param);
				}
				if($active_domain === $this->kiviCareProOnName()){
					$get_sms_config  = get_user_meta(get_current_user_id(),'sms_config_data',true);
					$get_sms_config = json_decode($get_sms_config);
					if( $get_sms_config->enableSMS == 1){
						$response = apply_filters('kcpro_send_sms', [
							'appointment_id' => $appointment_id,
							'current_user' => get_current_user_id(),
						]);
						// echo json_encode($response);
					}
					
				}
			}else{
				echo json_encode([
					'status'  => true,
					'message' => esc_html__('Appointment has been saved successfully', 'kc-lang'),
				]);
			}
			if (isset($request_data['custom_fields']) && $request_data['custom_fields'] !== []) {
                kvSaveCustomFields('appointment_module',$appointment_id, $request_data['custom_fields']);
            }
			
		}
		if ( $request_data['status'] === '2' || $request_data['status'] === '4' ) {
			KCPatientEncounter::createEncounter( $appointment_id );
			KCBillItem::createAppointmentBillItem($appointment_id );
		}

		if (gettype($request_data['visit_type']) === 'array') {
			
			foreach ($request_data['visit_type'] as $key => $value) {

			    $service = strtolower($value['name']);

			    if ($service == 'telemed') {

                    if ($this->isTeleMedActive()) {

                        $request_data['appointment_id'] = $appointment_id;
                        $request_data['time_slot'] = $time_slot;
                        $res_data = apply_filters('kct_create_appointment_meeting', $request_data);

						// send zoom link
						$user_email_param['patient_name'] = $patient_data[0]->display_name;
						$user_email_param['appointment_id'] = $appointment_id;
						$res_data = apply_filters('kct_send_zoom_link', $user_email_param );

                        if (!$res_data['status']) {
                            $message = $res_data['message'];
                        }

                    }
                }

				if(isset($appointment_id)){
					
					(new KCAppointmentServiceMapping())->insert([
						'appointment_id' => $appointment_id,
						'service_id' => $value['service_id'],
						'created_at' => current_time('Y-m-d H:i:s'),
						'status'=> 1
					]);
				}
			}
		}
		$message = 'appointment successfully booked.';
		if($this->getLoginUserRole() === $this->getPatientRole()) {
			if($active_domain === $this->kiviCareProOnName() && $this->isTeleMedActive() && $this->isWooCommerceActive()){
				if( KIVI_CARE_TELEMED_VERSION >= (float) '2.0.0' ) {
					if (get_option( KIVI_CARE_PREFIX . 'woocommerce_payment') === 'on') {
						if($appointment_id) {
							// woocommerce telemed cart response
							$res_data = apply_filters('kct_woocommerce_add_to_cart', [
								'appointment_id' => $appointment_id,
								'doctor_id' => $request_data['doctor_id']['id']
							]);
							$message = 'appointment successfully booked, Please check your email for zoom meeting link.';
							echo json_encode([
								'status'  => true,
								'message' => esc_html__($message, 'kc-lang'),
								'woocommerce_cart_data' => $res_data
							]);
							wp_die();
						}
					} 
				}
			}else{
				if($active_domain === $this->kiviCareProOnName() && $this->isWooCommerceActive() ){
					if (get_option( KIVI_CARE_PREFIX . 'woocommerce_payment') === 'on') {
						if($appointment_id) {
							// woocommerce telemed cart response
							$res_data = apply_filters('kcpro_woocommerce_add_to_cart', [
								'appointment_id' => $appointment_id,
								'doctor_id' => $request_data['doctor_id']['id']
							]);
							$message = 'appointment successfully booked, Please check your email for zoom meeting link.';
							echo json_encode([
								'status'  => true,
								'message' => esc_html__($message, 'kc-lang'),
								'woocommerce_cart_data' => $res_data
							]);
							wp_die();
						}
					} 
				}
				
				if($this->isTeleMedActive() && $this->isWooCommerceActive()) {
					if( KIVI_CARE_TELEMED_VERSION >= (float) '2.0.0' ) {
						if (get_option( KIVI_CARE_PREFIX . 'woocommerce_payment') === 'on') {
							if($appointment_id) {
								// woocommerce telemed cart response
								$res_data = apply_filters('kct_woocommerce_add_to_cart', [
									'appointment_id' => $appointment_id,
									'doctor_id' => $request_data['doctor_id']['id']
								]);
								$message = 'appointment successfully booked, Please check your email for zoom meeting link.';
								echo json_encode([
									'status'  => true,
									'message' => esc_html__($message, 'kc-lang'),
									'woocommerce_cart_data' => $res_data
								]);
								wp_die();
							}
						} 
					}
				}
			}

		}
		if($active_domain === $this->kiviCareProOnName()){
			$response = apply_filters('kcpro_save_appointment_event', [
				'appoinment_id'=>$appointment_id,
			]);
		}

		echo json_encode([
			'status'  => true,
			'message' => esc_html__($message, 'kc-lang'),
		]);

        wp_die();
	}

	public function delete() {

		if ( ! kcCheckPermission( 'appointment_delete' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access','kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
		$appointment_service_mapping_table = $this->db->prefix . 'kc_' . 'appointment_service_mapping';
		$active_domain =$this->getAllActivePlugin();
		try {

			if ( ! isset( $request_data['id'] ) ) {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}

			if (is_plugin_active($this->teleMedAddOnName())) {
				apply_filters('kct_delete_appointment_meeting', $request_data);
			}

			$id = $request_data['id'];

			if(isset($request_data['id'])) {
				$count_query      = "SELECT count(*) AS count from {$appointment_service_mapping_table} WHERE appointment_id = {$request_data['id']} ";
				$appointment_count = $this->db->get_results( $count_query, OBJECT );
				if(isset($appointment_count[0]->count) && $appointment_count[0]->count > 0 && $appointment_count[0]->count!= null  ){
					( new KCAppointmentServiceMapping() )->delete( [ 'appointment_id' => $id ] );
				}
				if($active_domain === $this->kiviCareProOnName()){
					apply_filters('kcpro_remove_appointment_event', ['appoinment_id'=>$id]);
				}
				$results = ( new KCAppointment() )->delete( [ 'id' => $id ] );
			}
			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Appointment has been deleted successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}


		} catch ( Exception $e ) {

			$code    = $e->getCode();
			$message = $e->getMessage();

			$code = esc_html__($code, 'kc-lang');
			$message = esc_html__($message, 'kc-lang');

			header( "Status: $code $message" );

			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($e->getMessage(), 'kc-lang')
			] );
		}
	}

	public function updateStatus() {

		if ( ! kcCheckPermission( 'appointment_edit' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}


		$request_data = $this->request->getInputs();

		$rules  = [
			'appointment_id'     => 'required',
			'appointment_status' => 'required',

		];
		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($errors[0], 'kc-lang')
			] );
			die;
		}


		try {

			( new KCAppointment() )->update( [ 'status' => $request_data['appointment_status'] ], array( 'id' => $request_data['appointment_id'] ) );

			if ( (string) $request_data['appointment_status'] === '2' || (string)$request_data['appointment_status'] === '4' ) {
				KCPatientEncounter::createEncounter( $request_data['appointment_id'] );
				KCBillItem::createAppointmentBillItem($request_data['appointment_id']);
			}
			if ((string)$request_data['appointment_status'] === '3' || (string)$request_data['appointment_status'] === '0' ) {
				KCPatientEncounter::closeEncounter( $request_data['appointment_id'] );
			}

			echo json_encode( [
				'status'  => true,
				'message' => esc_html__('Appointment status has been updated successfully', 'kc-lang')
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

	public function getAppointmentSlots() {

		$request_data = $this->request->getInputs();

		$rules = [
			'date'      => 'required',
			'clinic_id' => 'required',
			'doctor_id' => 'required',

		];

		$message = [
			'clinic_id' => esc_html__('Clinic is required', 'kc-lang'),
			'doctor_id' => esc_html__('Doctor is required', 'kc-lang'),
		];

		$errors = kcValidateRequest( $rules, $request_data, $message );

		if ( count( $errors ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($errors[0], 'kc-lang')
			] );
			die;
		}

		try {
			$active_domain =$this->getAllActivePlugin();
			$user_id = get_current_user_id();
			if($active_domain === $this->kiviCareProOnName()){
				if($this->getLoginUserRole() == 'kiviCare_receptionist') {
					$receptionis_id = get_current_user_id();
					$clinic_id =  (new KCReceptionistClinicMapping())->get_by([ 'receptionist_id' => $user_id]);
					$request_data['clinic_id'] = $clinic_id[0]->clinic_id;
				}
				if($this->getLoginUserRole() == 'kiviCare_clinic_admin'){
					$clinic_id = (new KCClinic())->get_by([ 'clinic_admin_id' => $user_id]);
					$clinic_id = $clinic[0]->id;
				}
			}
			
			$slots = kvGetTimeSlots( $request_data );

			echo json_encode( [
				'status'  => true,
				'message' => esc_html__('Appointment slots', 'kc-lang'),
				'data'    => $slots
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

	public function getAppointmentQueue() {
		global $wpdb;
		$request_data = $this->request->getInputs();
		$active_domain =$this->getAllActivePlugin();
		$appointments_table = $this->db->prefix . 'kc_' . 'appointments';
		$appointments_service_table = $this->db->prefix . 'kc_' . 'appointment_service_mapping';
		$service_table = $this->db->prefix . 'kc_' . 'services';

		$filterData = isset( $request_data['filterData'] ) ? $request_data['filterData'] : [];
		$users_table   = $this->db->prefix . 'users';
		$clinics_table = $this->db->prefix . 'kc_' . 'clinics';
		$static_data   = $this->db->prefix . 'kc_' . 'static_data';

        $data_filter = "";
		if (isset( $request_data['start']) && isset( $request_data['end'])) {
            $start_date = ( new DateTime( $request_data['start'] ) )->format( 'Y-m-d' );
            $end_date = ( new DateTime( $request_data['end'] ) )->format( 'Y-m-d' );
    		$data_filter  = " AND {$appointments_table}.appointment_start_date BETWEEN '{$start_date}' AND '{$end_date}' "  ;
        } elseif (isset($request_data['filterData']['date']) && $request_data['filterData']['date']!= null && $request_data['filterData']['status'] !== 'all') {
			if($request_data['filterData']['status'] == 1){
				$date = ( new DateTime( $request_data['filterData']['date'] ) )->format( 'Y-m-d' );
				$data_filter  = " AND {$appointments_table}.appointment_start_date >= '{$date}' " ;
			}else{
				$date = ( new DateTime( $request_data['filterData']['date'] ) )->format( 'Y-m-d' );
				$data_filter  = " AND {$appointments_table}.appointment_start_date = '{$date}' " ;
			}
        }
		else{
            $data_filter = '';
        }

		$query = "
            SELECT {$appointments_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       static_data.label AS type_label,
		       {$clinics_table}.name AS clinic_name
			FROM  {$appointments_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$appointments_table}.doctor_id = doctors.id
		       LEFT JOIN {$users_table} patients
		              ON {$appointments_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$appointments_table}.clinic_id = {$clinics_table}.id
		       LEFT JOIN {$static_data} static_data
		              ON {$appointments_table}.visit_type = static_data.value
			WHERE 0 = 0 " . $data_filter;
		$user = wp_get_current_user();
		if(!current_user_can('administrator')){
			$user_id = get_current_user_id();
			$clinic_id =  (new KCClinic())->get_by([ 'clinic_admin_id' => $user_id]);
			if(isset($clinic_id[0]->id)) {
				$clinic = $clinic_id[0]->id ;
				$query .= " AND {$appointments_table}.clinic_id = " . $clinic;
			}
		}

		if ( in_array( $this->getDoctorRole(), $user->roles ) ) {
			$query .= " AND {$appointments_table}.doctor_id = " . $user->ID;
		} elseif ( in_array( $this->getPatientRole(), $user->roles ) ) {
			$query .= " AND {$appointments_table}.patient_id = " . $user->ID;
		}

		if ( isset( $filterData['patient_id']['id'] ) && $filterData['patient_id']['id'] !== null ) {
			$query = $query . " AND {$appointments_table}.patient_id = " . $filterData['patient_id']['id'];
		}
		if ( isset( $request_data['filterData']['visit_type'] ) && $request_data['filterData']['visit_type']['id'] !== null ) {
			$query = $query . " AND {$appointments_table}.visit_type = '{$request_data['filterData']['visit_type']['id']}' ";
		}
		if(isset($request_data['filterData']['clinic_id']['id']) && $active_domain === $this->kiviCareProOnName() ){
			if($request_data['filterData']['clinic_id']['id'] == 0){
				$query = $query;

			}else{
				$query = $query . " AND {$appointments_table}.clinic_id = '{$request_data['filterData']['clinic_id']['id']}' ";
			}
		}
        if ( isset( $filterData['status'] )  ) {

            if ( (int)$filterData['status'] === -1 ) {
                $time  = current_time('H:i:s');
				$query = $query . " AND {$appointments_table}.appointment_start_time > '" . $time . "' ORDER BY  {$appointments_table}.appointment_start_time ASC";
			} elseif ( $filterData['status'] === "all" ) {
                $query = $query . " ORDER BY  {$appointments_table}.appointment_start_time ASC";

			} elseif ( isset($filterData['status']['value'])) {
                $query = $query . " AND {$appointments_table}.status = {$filterData['status']['value']} ORDER BY {$appointments_table}.appointment_start_time ASC";

            } else {
				if($filterData['status'] == 1){
					$query = $query . " AND {$appointments_table}.status  IN(1,4) ORDER BY {$appointments_table}.appointment_start_time ASC";
				}else{
					$query = $query . " AND {$appointments_table}.status = {$filterData['status']} ORDER BY {$appointments_table}.appointment_start_time ASC";
				}
			}
		}else{
            $query = $query . " ORDER BY  {$appointments_table}.appointment_start_time ASC";
        }
		$appCollection = collect( $this->db->get_results( $query, OBJECT ) )->unique( 'id' );

		$encounters = collect([]);

		if (count($appCollection)) {
			$appointment_ids = $appCollection->pluck('id')->implode(',');
			$encounter_table = $this->db->prefix . 'kc_' . 'patient_encounters';
			$encounter_query = " SELECT * FROM $encounter_table WHERE appointment_id IN ($appointment_ids) ";
			$encounters = collect($this->db->get_results($encounter_query, OBJECT));

			$zoom_mappings = apply_filters('kct_get_meeting_list', [
				'appointment_ids' => $appointment_ids
			]);

			if (isset($zoom_mappings['appointment_ids'])) {
				$zoom_mappings = collect([]);
			}
		}
		
		
		$appointments = $appCollection->map( function ( $appointment ) use ($encounters, $zoom_mappings) {
			$appointments_table = $this->db->prefix . 'kc_' . 'appointments';
			$appointments_service_table = $this->db->prefix . 'kc_' . 'appointment_service_mapping';
			$service_table = $this->db->prefix . 'kc_' . 'services';

			$zoom_config_data = get_user_meta($appointment->doctor_id, 'zoom_config_data', true);
			$get_service =  "SELECT {$appointments_table}.id,{$service_table}.name AS service_name,{$service_table}.id AS service_id FROM {$appointments_table}
			LEFT JOIN {$appointments_service_table} ON {$appointments_table}.id = {$appointments_service_table}.appointment_id JOIN {$service_table} 
			ON {$appointments_service_table}.service_id = {$service_table}.id WHERE 0 = 0";
			$enableTeleMed = false;
			if ($zoom_config_data) {
				$zoom_config_data = json_decode($zoom_config_data);
				if (isset($zoom_config_data->enableTeleMed) && (bool)$zoom_config_data->enableTeleMed) {
					if ($zoom_config_data->api_key !== "" && $zoom_config_data->api_secret !== "") {
						$enableTeleMed = true;
					}
				}
			}
			$appointment->appointment_start_time = date( 'h:i A', strtotime( $appointment->appointment_start_time ) );
			$appointment->appointment_end_time   = date( 'h:i A', strtotime( $appointment->appointment_end_time ) );
			$appointment->clinic_id  = [
				'id'    => $appointment->clinic_id,
				'label' => $appointment->clinic_name
			];
			$appointment->doctor_id  = [
				'id'    => $appointment->doctor_id,
				'label' => $appointment->doctor_name,
				'enableTeleMed' => $enableTeleMed
			];
			$appointment->patient_id = [
				'id'   => $appointment->patient_id,
				'label' => $appointment->patient_name
			];
		
			$appointment->encounter = $encounters->where('appointment_id', $appointment->id)->first();

			$zoom_data = $zoom_mappings->where('appointment_id', $appointment->id)->first();
			$appointment->zoom_data = $zoom_data;

			$video_consultation = false;
			if (count($zoom_data)) {
				$video_consultation = true;
			}
			$appointment->video_consultation = $video_consultation;
			$services = collect( $this->db->get_results( $get_service, OBJECT ) )->where('id', $appointment->id);
			$service_array=$service_list=[];
			foreach ($services as $service) {
				$service_array[] =  $service->service_name;
				$service_list[] = [
					'service_id'    => $service->service_id,
					'name' => $service->service_name
				];
			}
			$str = implode (", ", $service_array);
			$appointment->all_services = $str;
            $appointment->visit_type_old = $appointment->visit_type ;
			$appointment->visit_type = $service_list;
			$appointment->custom_fields = kcGetCustomFields('appointment_module', $appointment->id,$appointment->doctor_id['id']);
			if( $appointment->status == '1'){
				global $wpdb;
				$post_table_name = $wpdb->prefix . 'posts';
				$clinic_session_table = $wpdb->prefix . 'kc_' . 'clinic_sessions';
		
				$args['post_name'] = strtolower(KIVI_CARE_PREFIX.'default_event_template');
				$args['post_type'] = strtolower(KIVI_CARE_PREFIX.'gcal_tmp') ;
			
				$query = "SELECT * FROM $post_table_name WHERE `post_name` = '" . $args['post_name'] . "' AND `post_type` = '".$args['post_type']."' AND post_status = 'publish' ";
				$check_exist_post = $wpdb->get_results($query, ARRAY_A);
	
				$clinicData = (new KCClinic())->get_by(['id'=>$appointment->clinic_id['id']] ,'=',true);
				$clinicAddress = $clinicData->address.','.$clinicData->city.','.$clinicData->country;
	
				$appointment_day = strtolower(date('l', strtotime($appointment->appointment_start_date))) ;
				$day_short = substr($appointment_day, 0, 3);
				
				$query = "SELECT * FROM {$clinic_session_table}  
				WHERE `doctor_id` = ".$appointment->doctor_id['id']." AND `clinic_id` = ".$appointment->clinic_id['id']."  
				AND ( `day` = '{$day_short}' OR `day` = '{$appointment_day}') ";
	
				$clinic_session = collect($wpdb->get_results($query, OBJECT));
				
				$time_slot             = isset($clinic_session[0]->time_slot) ? $clinic_session[0]->time_slot : 15;
				$end_time             = strtotime( "+" . $time_slot . " minutes", strtotime($appointment->appointment_start_time ) );
	
				$appointment_end_time = date( 'H:i:s', $end_time );
				$calender_title = $check_exist_post[0]['post_title'];
				$calender_content = $check_exist_post[0]['post_content'];
				$key  =  ['{{service_name}}','{{clinic_name}}'];
				foreach($key as $item => $value ){
					switch ($value) {
						case '{{service_name}}':
							$calender_title = str_replace($value, $appointment->all_services, $calender_title);
							break;
						case '{{clinic_name}}':
							$calender_content = str_replace($value, $clinicData->name, $calender_content);
							break;
					}
				}
			  $appointment->calender_title = $calender_title;
			  $appointment->calender_content = $calender_content;
			  $appointment->clinic_address =$clinicAddress;
			  $appointment->start = date("c", strtotime( $appointment->appointment_start_date.$appointment->appointment_start_time));
			  $appointment->end =  date("c", strtotime( $appointment->appointment_start_date.$appointment_end_time));
			   
			}
			return $appointment;

		} )->sortBy('appointment_start_time')->values();

	
		echo json_encode( [
			'status'  => true,
			'message' => esc_html__('Appointments', 'kc-lang'),
			'data'    => $appointments,
			'nextPage' => $request_data['page'] + 1,
		] );

	}

	public function allAppointment() {

		$request_data = $this->request->getInputs();
		$condition    = '';
		$appointments_table = $this->db->prefix . 'appointments';

		$filterData = isset( $request_data['filterData'] ) ? $request_data['filterData'] : [];

		$users_table   = $this->db->prefix . 'users';
		$static_data_table  = $this->db->prefix . 'static_data';

		if ( $request_data['searchKey'] && $request_data['searchValue']) {
			$condition = " WHERE {$request_data['searchKey']} LIKE  '%{$request_data['searchValue']}%' ";
		}

		$query = "
			SELECT {$appointments_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name
		       
			FROM  {$appointments_table}
		       LEFT JOIN {$users_table} doctors
		            ON {$appointments_table}.doctor_id = doctors.id
		       LEFT JOIN {$users_table} patients
		            ON {$appointments_table}.patient_id = patients.id
		       {$condition}  
		       ORDER BY {$appointments_table}.appointment_start_date DESC LIMIT {$request_data['limit']} OFFSET {$request_data['offset']}";

		$appointments = collect( $this->db->get_results( $query, OBJECT ) )->unique( 'id' );

		$appointment_count_query = "SELECT count(*) AS count FROM {$appointments_table}";

		$total_appointment = $this->db->get_results( $appointment_count_query, OBJECT );

		if ( $request_data['searchKey'] && $request_data['searchValue'] ) {
			$total_rows = count( $appointments );
		} else {
			$total_rows = $total_appointment[0]->count;
		}

		if ( $total_rows < 0 ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No appointment found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		$visit_type_data =  $appointments->pluck('visit_type')->unique()->implode("','");

		$static_data_query = " SELECT * FROM $static_data_table WHERE value IN ('$visit_type_data') ";

		$static_data = collect($this->db->get_results( $static_data_query, OBJECT ))->pluck('label','value')->toArray();

		foreach ($appointments as $key => $appointment) {
			$appointment->type_label = $static_data[$appointment->visit_type];
		}

		echo json_encode( [
			'status'  => true,
			'message' => esc_html__('Appointments', 'kc-lang'),
			'data'    => $appointments,
			'total_rows' => $total_rows
		] );
	}
}
