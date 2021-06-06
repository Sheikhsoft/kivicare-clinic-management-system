<?php

namespace App\controllers;
use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCAppointmentServiceMapping;
use App\models\KCClinic;


class KCPatientDashboardWidget extends KCBase {

    public $db;
    private $request;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->request = new KCRequest();
    }

    public function appointmentData($data) {

        $appointments_table = $this->db->prefix . 'kc_' . 'appointments';
        $users_table   = $this->db->prefix .'users';
        $condition = '' ;

        if(isset($data['user_id']) && $data['user_id'] !== '' &&$data['user_id'] !== 0) {
            $condition = ' WHERE ' . $appointments_table.'.patient_id = '.$data['user_id'];
        }

        if(isset($data['limit']) && isset($data['offset']) && $data['limit'] !== '' && $data['offset'] !== '') {
            $offset = $data['offset'] ;
            $pagination =  " LIMIT {$data['limit']} OFFSET {$offset} " ;
        } else {
            $pagination = " LIMIT 10 OFFSET 0 " ;
        }

        $appointment_list_query = "
            SELECT {$appointments_table}.*,
               doctors.display_name  AS doctor_name,
               patients.display_name AS patient_name
            FROM  {$appointments_table}
               LEFT JOIN {$users_table} doctors
                    ON {$appointments_table}.doctor_id = doctors.id
               LEFT JOIN {$users_table} patients
                    ON {$appointments_table}.patient_id = patients.id
               {$condition}
               ORDER BY {$appointments_table}.appointment_start_date DESC {$pagination} ";

         return $this->db->get_results( $appointment_list_query, OBJECT ) ;

    }

    public function appointmentList() {

        global $wpdb;
        $active_domain =$this->getAllActivePlugin();
        $request_data = $this->request->getInputs();

        if(is_user_logged_in()) {
            $data['user_id'] = get_current_user_id();
        } else {
            $data['user_id'] = 0 ;
        }

        if(isset($request_data['limit']) && $request_data['limit'] !== '' ) {
            $data['limit'] = $request_data['limit'] ;
        } else {
            $data['limit'] = 5 ;
        }

        if(isset($request_data['offset']) && $request_data['offset'] !== '') {
            $data['offset'] = $request_data['offset'] ;
        } else {
            $data['offset'] = 0 ;
        }

        $appointments_table = $this->db->prefix . 'kc_' . 'appointments';
        $appointment_list_query = " SELECT COUNT(id) AS total_row FROM {$appointments_table}  WHERE patient_id = {$data['user_id']} " ;
        $appointments = $this->appointmentData($data);
        $appointments[0]->custom_fields = kcGetCustomFields('appointment_module', $appointments[0]->id,$appointments[0]->doctor_id['id']);
        if($active_domain === $this->kiviCareProOnName() && $appointments[0]->status == '1'){
            
                $appointments = collect($appointments);
                $appointments = $appointments->map(function($appointment) {
                    global $wpdb;
                    $post_table_name = $wpdb->prefix . 'posts';
                    $clinic_session_table = $wpdb->prefix . 'kc_' . 'clinic_sessions';
            
                    $args['post_name'] = strtolower(KIVI_CARE_PREFIX.'default_event_template');
                    $args['post_type'] = strtolower(KIVI_CARE_PREFIX.'gcal_tmp') ;

                    $query = "SELECT * FROM $post_table_name WHERE `post_name` = '" . $args['post_name'] . "' AND `post_type` = '".$args['post_type']."' AND post_status = 'publish' ";
                    $check_exist_post = $wpdb->get_results($query, ARRAY_A);

                    $clinicData = (new KCClinic())->get_by(['id'=>$appointment->clinic_id] ,'=',true);
                    $clinicAddress = $clinicData->address.','.$clinicData->city.','.$clinicData->country;

                    $appointment_day = strtolower(date('l', strtotime($appointment->appointment_start_date))) ;
                    $day_short = substr($appointment_day, 0, 3);

                      
                    $query = "SELECT * FROM {$clinic_session_table}  
                    WHERE `doctor_id` = ".$appointment->doctor_id." AND `clinic_id` = ".$appointment->clinic_id."  
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
                                $calender_title = str_replace($value, $appointment->visit_type, $calender_title);
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
                    $appointment->end = date("c", strtotime($appointment->appointment_start_date.$appointment_end_time));
                    return $appointment ;
                });
        }
        if (is_plugin_active($this->teleMedAddOnName())) { 

            $appointments = collect($appointments);
            $appointments_ids = $appointments->where('status', '4')->pluck('id')->toArray();
            $zoom_appointment_table = $this->db->prefix . 'kc_' . 'appointment_zoom_mappings' ;

            if(count($appointments) > 0) {
                $appointmnet_ids = "'" . implode($appointments_ids, "','") . "'" ;
                $zoom_link_query = "SELECT * FROM {$zoom_appointment_table}  WHERE appointment_id IN ( {$appointmnet_ids} ) " ;
                $zoom_join_link  = $this->db->get_results($zoom_link_query);
                $zoom_join_link = collect($zoom_join_link);
                
                $appointments = $appointments->map(function($appointment) use ($zoom_join_link) {
                   
                    $zoom_data = $zoom_join_link->where('appointment_id', $appointment->id)->first() ;
                    if(isset($zoom_data->id)) {
                        $appointment->join_url = $zoom_data->join_url ;
                    } else {
                        $appointment->join_url = '' ;
                    }
                    return $appointment ;
                });
               
            }

        }
       
        $total_rows = $this->db->get_results( $appointment_list_query, OBJECT );
        
        if($total_rows !== null) {
            $total_rows = collect($total_rows)->first()->total_row ;
        } else {
            $total_rows = 0 ;
        }

        if ( $total_rows < 0 ) {
            echo json_encode( [
                'status'  => false,
                'message' => esc_html__('No appointment found', 'kc-lang'),
                'data'    => []
            ] );
            wp_die();
        }
        echo json_encode( [
            'status'  => true,
            'message' => esc_html__('Appointments', 'kc-lang'),
            'data'    => $appointments,
            'total_rows' => $total_rows
        ] );
    }

    public function getPatientDetail() {
        $userDetail = wp_get_current_user();
        echo json_encode( [
            'status'  => true,
            'message' => esc_html__('Appointments', 'kc-lang'),
            'data'    => $userDetail
        ] );
    }

    public function encounterList() {

        if(is_user_logged_in()) {
            $data['user_id'] = get_current_user_id();
        } else {
            $data['user_id'] = 0 ;
        }

        $request_data['limit'] = 10 ;
        $request_data['offset'] = 0 ;
        $patient_encounter_table = $this->db->prefix . 'kc_' . 'patient_encounters';
        $clinics_table           = $this->db->prefix . 'kc_' . 'clinics';
        $users_table             = $this->db->prefix . 'users';

        $count_query      = "SELECT count(*) AS count from {$patient_encounter_table} WHERE patient_id = {$data['user_id']} ";
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
            ORDER BY id DESC LIMIT {$request_data['limit']} OFFSET {$request_data['offset']} ";
        $encounters = $this->db->get_results( $encounters, OBJECT );

        echo json_encode( [
            'status'     => true,
            'message'    => esc_html__('Encounter list', 'kc-lang'),
            'data'       => $encounters,
            'total_rows' => (int) $encounters_count[0]->count
        ] );
    }

    public function getClinicDoctors () {

        global $wpdb;

        $table_name = $wpdb->prefix . 'kc_' . 'doctor_clinic_mappings';
        $clinic_id = kcGetDefaultClinicId();

        $doctor_session_data = [];

        if (isset($request_data['module_type']) && $request_data['module_type'] === 'appointment') {
            $clinic_session_table = $wpdb->prefix. 'kc_' . 'clinic_sessions';
            $doctor_sessions_query = "SELECT * FROM {$clinic_session_table} WHERE `clinic_id` = '{$clinic_id}' ";
            $doctor_session_data = collect($wpdb->get_results($doctor_sessions_query, ARRAY_A))->pluck('doctor_id')->unique();
        }

        $query = "SELECT * FROM {$table_name} WHERE `clinic_id` = '{$clinic_id}' ";
        $clinic_data = $wpdb->get_results($query, OBJECT);
        $results = [];
        $doctor_ids = [];

        if (count($clinic_data)) {

            foreach ($clinic_data as $clinic_map_data) {
                if (isset($clinic_map_data->doctor_id)) {
                    if(isset($request_data['module_type']) && $request_data['module_type'] === 'appointment') {
                        $doctor_session_data = collect($doctor_session_data)->toArray();
                        if(in_array($clinic_map_data->doctor_id, $doctor_session_data)) {
                            $doctor_ids[] = $clinic_map_data->doctor_id;
                        }
                    } else {
                        $doctor_ids[] = $clinic_map_data->doctor_id;
                    }
                }
            }


            if (count($doctor_ids)) {

                $users_table = $wpdb->prefix . 'users';
                $new_query = "SELECT `ID` as `id`, `display_name` as `label`  FROM {$users_table} WHERE `ID` IN (" . implode(',', $doctor_ids) . ") AND `user_status` = '0'";
                $results = $wpdb->get_results($new_query, OBJECT);

                if (count($results)) {
                    foreach ($results as $result) {
                        $user_data = get_user_meta($result->id, 'basic_data', true);
                        if ($user_data) {
                            $user_data = json_decode($user_data);
                            $result->timeSlot = isset($user_data->time_slot) ? $user_data->time_slot : "";
                            $specialties = collect($user_data->specialties)->pluck('label')->toArray();
                            $result->label = $result->label ." (". implode( ',',$specialties).")";
                            $result->enableTeleMed = (bool) false;
                            if (is_plugin_active($this->teleMedAddOnName())) { 
                                // get doctor zoom api keydata
                                $zoom_config_data = get_user_meta($result->id, 'zoom_config_data', true);
                                if($zoom_config_data) {
                                    $zoom_config_data = json_decode($zoom_config_data);
                                    $enableTeleMed = false;
                                    if (isset($zoom_config_data->enableTeleMed) && (bool)$zoom_config_data->enableTeleMed) {
                                        if ($zoom_config_data->api_key !== "" && $zoom_config_data->api_secret !== "") {
                                            $result->enableTeleMed = (bool) true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

        }
        $data['status'] = true;
        $data['message'] = esc_html__('Datatype found', 'kc-lang');
        $data['data'] = $results;
        echo json_encode($data);
        wp_die();
    }

    public function getTimeSlots() {

        $formData = $this->request->getInputs();;

        $clinic_id = kcGetDefaultClinicId();

        $timeSlots = kvGetTimeSlots([
            'date' => $formData['date'],
            'doctor_id' => $formData['doctor_id'],
            'clinic_id' => $clinic_id
        ], "", true);

        if (count($timeSlots)) {
            $status = true;
            $message = esc_html__('Time slots', 'kc-lang' );
        } else {
            $status = false;
            $message = esc_html__('Doctor is not available for this date', 'kc-lang' );
        }

        echo json_encode( [
            'status'      => $status,
            'message'     => $message,
            'data'     => $timeSlots,
        ] );

    }

    public function bookAppointment() {

        $formData = $this->request->getInputs();

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

            $patient_id = get_current_user_id();

            $patient_appointment_id = (new KCAppointment())->insert([
                'appointment_start_date' => $appointment_date,
                'appointment_start_time' => date( 'H:i:s', strtotime( $formData['appointment_start_time'] ) ),
                'appointment_end_date'   => $appointment_date,
                'appointment_end_time'   => $appointment_end_time,
                'visit_type'             => $formData['visit_type'],
                'clinic_id'              => $clinic_id,
                'doctor_id'              => $formData['doctor_id']['id'],
                'patient_id'             => $patient_id,
                'description'            => $formData['description'],
                'status'                 => $formData['status'],
                'created_at'             => current_time('Y-m-d H:i:s')
            ]);
            $doctor_name  = $formData['visit_type'][0]['doctor_name'];
            $user_email_param = array(
                'user_email' => $userObj->data->user_email,
                'appointment_date' => $appointment_date,
                'appointment_time' => date( 'H:i:s', strtotime( $formData['appointment_start_time'] ) ),
                'doctor_name'=>$doctor_name,
                'email_template_type' => $this->getPluginPrefix() . 'book_appointment'
            );

            if (gettype($formData['visit_type']) === 'array') {

                foreach ($formData['visit_type'] as $key => $value) {

                    $service = strtolower($value['name']);

                    if ($service === 'telemed') {

                        if (is_plugin_active($this->teleMedAddOnName())) {

                            $request_data['appointment_id'] = $patient_appointment_id;
                            $request_data['time_slot'] = $time_slot;

                            $res_data = apply_filters('kct_create_appointment_meeting', $request_data);

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

            kcSendEmail($user_email_param);

            if($patient_appointment_id) {
                $message = esc_html__('Appointment has been booked successfully', 'kc-lang');
                $status  = true ;
            } else {
                $message = esc_html__('Appointment has not been booked', 'kc-lang');
                $status  = false ;
            }

            echo json_encode( [
                'status'      => (bool) $status,
                'message'     => $message,
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

    public function cancelAppointment () {

        global $wpdb;

        $apmnt_table_name = $wpdb->prefix . 'kc_' . 'appointments';

        $request = $this->request->getInputs();

        if (isset($request['id'])) {

            $cancel_appointment_query = "UPDATE {$apmnt_table_name} SET status =  0 WHERE id = {$request['id']}" ;
            $wpdb->query($cancel_appointment_query);

            echo json_encode( [
                'status'  => true,
                'message' => esc_html__('Appointment canceled successfully.', 'kc-lang')
            ] );
            wp_die();

        }

        echo json_encode( [
            'status'  => false,
            'message' => esc_html__('Appointment not canceled successful.', 'kc-lang')
        ] );
        wp_die();

    }

    public function saveProfile() {

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

        wp_update_user(
            array(
                'ID'           => $request_data['ID'],
                'user_login'   => $request_data['username'],
                'user_email'   => $request_data['user_email'],
                'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
            )
        );

        update_user_meta( $request_data['ID'], 'basic_data', json_encode( $temp ) );

        $message = esc_html__('Profile has been updated successfully', 'kc-lang');

        echo json_encode( [
            'status'  => true,
            'message' => $message
        ] );

    }

    public function getDashboardData () {

        if(is_user_logged_in()) {
            $user_id = get_current_user_id();
        } else {
            $user_id = 0 ;
        }

        $clinic_id = kcGetDefaultClinicId();
        $clinic_doctor_mapping_table = $this->db->prefix . 'kc_' . 'doctor_clinic_mappings';
        $clinic_doctor_query = "SELECT count(*) AS total_count FROM {$clinic_doctor_mapping_table} WHERE clinic_id = {$clinic_id}" ;
        $clinic_doctors = $this->db->get_results( $clinic_doctor_query, OBJECT );

        if($clinic_doctors !== null) {
            $data['total_doctors'] = (int) $clinic_doctors[0]->total_count ;
        } else {
            $data['total_doctors'] = 0;
        }

        $patient_encounter_table = $this->db->prefix . 'kc_' . 'appointments';
        $count_query      = "SELECT count(*) AS count from {$patient_encounter_table} WHERE patient_id = {$user_id} ";
        $encounters_count = $this->db->get_results( $count_query, OBJECT );

        if($encounters_count !== null) {
            $data['total_visits'] = (int) $encounters_count[0]->count ;
        } else {
            $data['total_visits'] = 0;
        }

        $upcoming_appointment = $this->db->prefix . 'kc_' . 'appointments';
        $upcoming_appointment_query = "SELECT count(*) AS total_count from {$upcoming_appointment} WHERE patient_id = {$user_id}  AND status = 1";
        $upcoming_count = $this->db->get_results( $upcoming_appointment_query, OBJECT );

        if($encounters_count !== null) {
            $data['total_upcoming_visits'] = (int) $upcoming_count[0]->total_count ;
        } else {
            $data['total_upcoming_visits'] = 0;
        }

        $message = esc_html__('Dashboard data get successfully', 'kc-lang');

        echo json_encode( [
            'status'  => true,
            'data' => $data,
            'message' => $message
        ] );

    }

}


