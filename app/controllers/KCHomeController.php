<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCBill;
use App\models\KCServiceDoctorMapping;
use App\models\KCReceptionistClinicMapping;
use App\models\KCService;
use App\models\KCCustomField;
use App\models\KCClinic;
use Exception;
use WP_User;

class KCHomeController extends KCBase
{

    /**
     * @var KCRequest
     */
    public $db;

    private $request;

    public function __construct()
    {
        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();
    }

    public function logout()
    {
        wp_logout();
        echo json_encode([
            'status' => true,
            'message' => esc_html__('Logout successfully.', 'kc-lang'),
        ]);
    }

    public function getStaticData()
    {
        global $wpdb;
        $request_data = $this->request->getInputs();
        $active_domain =$this->getAllActivePlugin();
        if($active_domain === $this->kiviCareProOnName() && $request_data['data_type'] == 'clinic_list' ){
            $table_name = $wpdb->prefix . 'kc_' . 'clinics';
            $response = apply_filters('kcpro_get_all_clinic', []);
            echo json_encode($response);
        }else{
            $data = [
                'status' => false,
                'message' => esc_html__('Datatype not found', 'kc-lang')
            ];

            if (isset($request_data['data_type'])) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'kc_' . 'static_data';
                $type = $request_data['data_type'];

                switch ($type) {
                    case "static_data":
                        $static_data_type = $request_data['static_data_type'];
                        $query = "SELECT id, label FROM $table_name WHERE type = '$static_data_type' AND status = '1' GROUP BY $table_name.`value`";
                        $results = $wpdb->get_results($query, OBJECT);
                        break;

                    case "static_data_with_label":
                        $static_data_type = $request_data['static_data_type'];
                        $query = "SELECT `value` as id, label FROM $table_name WHERE type = '$static_data_type' AND status = '1' GROUP BY $table_name.`value` ";
                        $results = collect($wpdb->get_results($query, OBJECT))->unique('id')->toArray();
                        break;

                    case "static_data_types":
                        $query = "SELECT `type` as id, REPLACE(type, '_' , ' ') AS `type` FROM $table_name GROUP BY `type`";
                        $results = $wpdb->get_results($query, OBJECT);
                        break;

                    case "clinics":
                        $table_name = $wpdb->prefix . 'kc_' . 'clinics';
                        $query = "SELECT `id`, `name` as `label` FROM {$table_name} WHERE `status` = '1'";
                        $results = $wpdb->get_results($query, OBJECT);
                        break;

                    case "doctors":
                        $da = [];
                        $doctors = get_users([
                            'role' => $this->getDoctorRole()
                        ]);
                        $doctorList = collect($doctors)->toArray();
                        foreach ($doctorList as $d) {
                            $da[] = [
                                'id'    => $d->data->ID,
                                'label' => $d->data->display_name
                            ];
                        }
                        $results = $da;
                        break;
                    
                    case "default_clinic":
                        $table_name = $wpdb->prefix  . 'kc_' . 'clinics';
                        $default_clinic = get_option('setup_step_1');
                        $option_data = json_decode($default_clinic, true);
                        if(isset($option_data['id'][0])) {
                            $query = "SELECT * FROM {$table_name} WHERE `status`= '1' AND `id` = '{$option_data['id'][0]}' ";
                            $results = $wpdb->get_results($query, OBJECT);
                            $results = $results[0];
                        } else {
                            $results = [];
                        }
                        break;

                    case "services_with_price":
                        $service_table = $wpdb->prefix . 'kc_' . 'services';
                        $service_doctor_mapping = $wpdb->prefix . 'kc_' . 'service_doctor_mapping';
                        $zoom_config_data = get_user_meta($request_data['doctorId'], 'zoom_config_data', true);
                        $zoom_config_data = json_decode($zoom_config_data);
                        if(isset($request_data['doctorId'])){
                            if($zoom_config_data ->enableTeleMed == 1){
                                $query = "SELECT {$service_table}.id ,{$service_doctor_mapping}.charges AS price,{$service_table}.name AS label FROM  {$service_table} 
                                JOIN {$service_doctor_mapping} ON  {$service_table}.id = {$service_doctor_mapping}.service_id 
                                WHERE {$service_doctor_mapping}.doctor_id =".$request_data['doctorId'];
                            }else{
                                $query = "SELECT {$service_table}.id ,{$service_doctor_mapping}.charges AS price,{$service_table}.name AS label FROM  {$service_table} 
                                JOIN {$service_doctor_mapping} ON  {$service_table}.id = {$service_doctor_mapping}.service_id 
                                WHERE {$service_doctor_mapping}.doctor_id =".$request_data['doctorId']." AND {$service_table}.type != 'system_service' ";
                            }

                        }else{
                            $query = "SELECT `id`, `price`, `name` as `label` FROM {$service_table}";
                        }
                        $results = $wpdb->get_results($query, OBJECT);
                        break;

                    case "prescriptions":
                        $table_name = $wpdb->prefix . 'kc_' . 'prescription';
                        $query = "SELECT `name` as `id`, `name` as `label` FROM {$table_name}";
                        $results = collect($wpdb->get_results($query, OBJECT))->unique('id')->values();
                        break;

                    case "email_template_type":
                        $query = "SELECT `id`, `value`, `label` FROM {$table_name} WHERE `status` = '1' AND `type` = 'email_template' ";
                        $results = $wpdb->get_results($query, ARRAY_A);
                        break;

                    case "email_template_key":
                        $results = ['{{user_name}}', '{{user_email}}', '{{user_contact}}'];
                        break;
                    case "get_users_by_clinic":
                        $table_name = $wpdb->prefix . 'kc_' . 'doctor_clinic_mappings';
                        $query = "SELECT * FROM {$table_name} WHERE `clinic_id` = '{$request_data['clinic_id']}' ";
                        $clinic_data = $wpdb->get_results($query, OBJECT);
                        $results = [];
                        $doctor_ids = [];
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
                        }
                        break;
                    case "clinic_doctors":
                        $table_name = $wpdb->prefix . 'kc_' . 'doctor_clinic_mappings';
                        if($active_domain === $this->kiviCareProOnName()){
                            if($this->getLoginUserRole() == 'kiviCare_receptionist') {
                                $receptionis_id = get_current_user_id();
                                $clinic_id =  (new KCReceptionistClinicMapping())->get_by([ 'receptionist_id' => $receptionis_id]);
                                $clinic_id = $clinic_id[0]->clinic_id;
                            }else if($this->getLoginUserRole() == 'kiviCare_clinic_admin') {
                                $clinic = (new KCClinic())->get_by([ 'clinic_admin_id' => get_current_user_id()]);
                                $clinic_id = $clinic[0]->id;
                            }else{
                                $clinic_id = $request_data['clinic_id'];
                            }
                        }else{
                            $clinic_id = kcGetDefaultClinicId();
                        }
                        $doctor_session_data = [];

                        if (isset($request_data['module_type']) && $request_data['module_type'] === 'appointment') {
                            $clinic_session_table = $wpdb->prefix. 'kc_' . 'clinic_sessions';
                            $doctor_sessions_query = "SELECT * FROM {$clinic_session_table} WHERE `clinic_id` = '{$clinic_id}' ";
                            $doctor_session_data = collect($wpdb->get_results($doctor_sessions_query, ARRAY_A))->pluck('doctor_id')->unique();
                        }
                        if (!current_user_can('administrator')) {
                            $query = "SELECT * FROM {$table_name} WHERE `clinic_id` = '{$clinic_id}' ";
                        }else{
                            $query = "SELECT * FROM {$table_name}";
                        }
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
                                        // dd($clinic_map_data->doctor_id);
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
                                        }
                                        $zoom_config_data = get_user_meta($result->id, 'zoom_config_data', true);

                                        if ($zoom_config_data) {
                                            $zoom_config_data = json_decode($zoom_config_data);
                                            $enableTeleMed = false;
                                            if (isset($zoom_config_data->enableTeleMed) && (bool)$zoom_config_data->enableTeleMed) {
                                                if ($zoom_config_data->api_key !== "" && $zoom_config_data->api_secret !== "") {
                                                    $enableTeleMed = true;
                                                }
                                            }

                                            $result->enableTeleMed = $enableTeleMed;
                                        }
                                    }
                                }

                            }
                        }

                        break;

                    case "users":
                        $results = [];

                        $user_id = get_current_user_id();
                        $userObj = new WP_User($user_id);
                        $table_name = $wpdb->prefix . 'kc_' . 'appointments';

                        $users = get_users([
                            'role' => $request_data['user_type']
                        ]);

                        if ($userObj->roles[0] == 'kiviCare_doctor') {
                            $query = "SELECT `patient_id` FROM {$table_name} WHERE `doctor_id` = $userObj->id ";
                            $result = collect($wpdb->get_results($query))->unique('patient_id')->pluck('patient_id');
                            if(count($result) === 0){
                                $users = collect($users)->toArray();
                            }else{
                                $users = collect($users)->whereIn('ID',$result)->toArray();
                            }
                        }
                        if (count($users)) {
                            $i = 0 ;
                            foreach ($users as $key => $user) {
                                $results[$i]['id'] = $user->ID;
                                $results[$i]['label'] = $user->data->display_name;
                                $user_data = get_user_meta($user->ID, 'basic_data', true);
                                if ($user_data) {
                                    $user_data = json_decode($user_data);
                                    $results[$i]['timeSlot'] = isset($user_data->time_slot) ? $user_data->time_slot : "";
                                }
                                $i++ ;
                            }
                        }

                        break;

                    default:
                        $results = [];
                }

                $data['status'] = true;
                $data['message'] = esc_html__('Datatype found', 'kc-lang');
                $data['data'] = $results;
            }
            echo json_encode($data);
        }

    }

    public function kcGetCustomFields()
    {
        $user_id = get_current_user_id();
        $userObj = new WP_User($user_id);
        $request_data = $this->request->getInputs();
        $custom_field_table =   $this->db->prefix . 'kc_' . 'custom_fields';
        try {
            if (!isset($request_data['module_type'])) {
                throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
            }
            $module_type = $request_data['module_type'];
            $module_id = $request_data['module_id'] ;
            if($request_data['module_type'] !== 'patient_encounter_module' && $request_data['module_type'] == 'patient_module') {
                $module_id = $request_data['module_id'] ;
                $module_id  = " AND module_id = {$module_id} " ;
            }
            if($request_data['module_type'] === 'doctor_module') {
                $module_id = $request_data['module_id'] ;
                $module_id  = " AND module_id = {$module_id} " ;
            }
            if($request_data['module_type'] == 'appointment_module'){
                if($userObj->roles[0] == 'kiviCare_doctor'){
                    $module_id = $user_id ;
                    $module_id  = " AND module_id IN($module_id,0) ";
                }elseif (isset($request_data['doctor_id'])){
                    $doctor_id  = $request_data['doctor_id'];
                    $module_id  = " AND module_id IN($doctor_id,0)" ;
                }
                else{

                    $module_id  = " AND module_id = 0 " ;
                }
            }
            if($request_data['module_type'] == 'patient_module'){
                if($userObj->roles[0] == 'kiviCare_doctor'){
                    $module_id = $user_id ;
                    $module_id  = " AND module_id IN($module_id,0) ";
                }
                else{
                    $module_id  = " AND module_id = 0 " ;
                }
            }
            if( isset($module_id) && $request_data['module_type'] !== 'patient_encounter_module'  ){
                $query = "SELECT * FROM {$custom_field_table} WHERE module_type = '{$module_type}'  $module_id" ;
            }else{
                $query = "SELECT * FROM {$custom_field_table} WHERE module_type = '{$module_type}'" ;
            }
            $custom_module  = $this->db->get_results( $query );

            if($request_data['module_type'] == 'patient_encounter_module'){
                global  $wpdb;
                $type = $request_data['module_type'] ;
                $type = "'$type'";
                $custom_field_table =  $wpdb->prefix.'kc_custom_fields';
                $custom_field_data_table =  $wpdb->prefix.'kc_custom_fields_data';
                $query = "SELECT p.*, u.fields_data " .
                "FROM {$custom_field_table} AS p " .
                "LEFT JOIN (SELECT * FROM {$custom_field_data_table} WHERE module_id=".$module_id." ) AS u ON p.id = u.field_id WHERE p.module_type =" .$type .
                "AND p.module_id IN($module_id,0)"
                ;
                $custom_module =  $wpdb->get_results($query);           
            }
           
            $fields = [] ;
                if(count($custom_module) > 0) {
                    foreach ($custom_module as $key => $value) {
                        $fields[] = array_merge(json_decode($value->fields,true), ['field_data'=> $value->fields_data],['id'=> $value->id]);
                    }
                }

            echo json_encode([
                'status' => true,
                'message' => esc_html__('Custom fields', 'kc-lang'),
                'data' => array_values($fields)
            ]);


        } catch (Exception $e) {

            $code = esc_html__($e->getCode(), 'kc-lang');
            $message = esc_html__($e->getMessage(), 'kc-lang');

            header("Status: $code $message");

            echo json_encode([
                'status' => false,
                'message' => $message
            ]);
        }

    }

    public function getUser() {

        if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

        $plugins = get_plugins();

        $setup_step_count = get_option($this->getSetupSteps());
        $active_domain =$this->getAllActivePlugin();
        $steps = [];

        for ($i = 0; $i < $setup_step_count; $i++) {
            if (get_option('setup_step_' . ($i + 1))) {
                $steps[$i] = json_decode(get_option('setup_step_' . ($i + 1)));
            }
        }
        $user_id = get_current_user_id();
        $userObj = new WP_User($user_id);

        $default_clinic = get_option('setup_step_1');
        $option_data = json_decode($default_clinic, true);
        switch ($this->getLoginUserRole()) {
            case KIVI_CARE_PREFIX.'receptionist':
                $image_attachment_id = get_user_meta($user_id,'receptionist_profile_image',true);
                break;
            case KIVI_CARE_PREFIX.'doctor':
                $image_attachment_id = get_user_meta($user_id,'doctor_profile_image',true);
                break;
            case KIVI_CARE_PREFIX.'patient':
                $image_attachment_id = get_user_meta($user_id,'patient_profile_image',true);
                break;
            case KIVI_CARE_PREFIX.'clinic_admin':
                $clinciAdmindata = get_user_meta($user_id, 'basic_data',true);
                $image_attachment_id = get_user_meta($user_id,'clinic_admin_profile_image',true);
                // $clinciAdmindata = json_decode($clinciAdmindata);
                // $image_attachment_id = $clinciAdmindata->profile_image;
                break;
            default:
                # code...
                break;
        }
      
        $get_admin_language = get_option(KIVI_CARE_PREFIX . 'admin_lang');
        $get_user_language = get_user_meta($user_id, 'defualt_lang');

        if (isset($userObj->data->user_email)) {
            $user = $userObj->data;
            unset($user->user_pass);
	        $zoomConfig = kcGetZoomConfig($user->ID);
	        $zoomWarningStatus = false;
            $enableTeleMed = false;

	        if (isset($zoomConfig->enableTeleMed) && (bool)$zoomConfig->enableTeleMed) {
				if ($zoomConfig->api_key === "" || $zoomConfig->api_secret === "") {
					$zoomWarningStatus = true;
				} else {
                    $enableTeleMed = true;
                }
            }
            if(current_user_can('administrator')){
                $user->get_lang = isset($get_admin_language) ? $get_admin_language :'en';
            }else{
                $user->get_lang = isset($get_user_language[0]) ? $get_user_language[0] :$get_admin_language;
            }
            
            $user->permissions = $userObj->allcaps;
            $user->roles = $userObj->roles;
            $user->profile_photo = wp_get_attachment_url($image_attachment_id);
            $user->steps = $steps;
            $user->module = kcGetModules();
            $user->step_config = kcGetStepConfig();
            $user->enableTeleMed = $enableTeleMed;
            $user->teleMedStatus = isset($zoomConfig->enableTeleMed) ? (bool)$zoomConfig->enableTeleMed : false;
            $user->teleMedWarning = $zoomWarningStatus;
            $telemedPlugin = $this->teleMedAddOnName();
            $isTelemedActive  = false ;

            if($this->isTeleMedActive()) {
                $isTelemedActive = true ;
            }  

            $user->woocommercePayment = 'off' ;

            if($this->isTeleMedActive() && $this->isWooCommerceActive()) {
                $user->woocommercePayment = 'on' ;
            }
            
            if($active_domain === $this->kiviCareProOnName()){
                $get_site_logo = get_option(KIVI_CARE_PREFIX.'site_logo');
                $enableEncounter = json_decode(get_option(KIVI_CARE_PREFIX.'enocunter_modules'));
                $enablePrescription = json_decode(get_option(KIVI_CARE_PREFIX.'prescription_module'));
                $user->encounter_enable_module = isset($enableEncounter->encounter_module_config) ? $enableEncounter->encounter_module_config : 0;
                $user->prescription_module_config = isset($enablePrescription->prescription_module_config) ? $enablePrescription->prescription_module_config : 0;
                $user->encounter_enable_count = $this->getEnableEncounterModule($enableEncounter);
                $user->theme_color = get_option(KIVI_CARE_PREFIX.'theme_color');
                $user->theme_mode = get_option(KIVI_CARE_PREFIX.'theme_mode');
                $user->is_enable_sms = get_user_meta($user_id,'is_enable_sms',true);
                $user->site_logo  = isset($get_site_logo) && $get_site_logo!= null && $get_site_logo!= '' ? wp_get_attachment_url($get_site_logo) : -1;
                $get_googlecal_config= get_option( KIVI_CARE_PREFIX . 'google_cal_setting',true);
                if($get_googlecal_config['enableCal'] == 1){
                    $is_enable ='on';
                }else{
                    $is_enable ='off';
                }
                $user->is_enable_google_cal = $is_enable;

                $get_patient_cal_config= get_option( KIVI_CARE_PREFIX . 'patient_cal_setting',true);
                if($get_patient_cal_config == 1){
                    $is_patient_enable ='on';
                }else{
                    $is_patient_enable ='off';
                }
               
                $user->is_patient_enable = $is_patient_enable;
                $user->google_client_id = trim($get_googlecal_config['client_id']);
                if($this->getLoginUserRole() == 'kiviCare_doctor' || $this->getLoginUserRole() == $this->getReceptionistRole()){
                    $doctor_enable = get_user_meta($user_id, KIVI_CARE_PREFIX.'google_cal_connect',true);
                    if($doctor_enable == 'off' || empty($doctor_enable)){
                        $user->is_enable_doctor_gcal = 'off';
                    }else{
                        $user->is_enable_doctor_gcal = 'on';
                    }
                }
            }

            
            $kiviProPlugin = $this->kiviCareProOnName();
            $isKiviProActive  = false ;

            if(is_plugin_active($kiviProPlugin)) {
                $isKiviProActive = true ;
            }

            $user->addOns = ['telemed' => (bool)$isTelemedActive,'kiviPro' => $isKiviProActive];
            $user_data = get_user_meta($user->ID, 'basic_data', true);

            if ($user_data) {
                $user_data = json_decode($user_data);
                $user->timeSlot = isset($user_data->time_slot) ? $user_data->time_slot : "";
                $user->basicData = $user_data;
            }

        } else {
            $isTelemedActive  = false ;
            $user->woocommercePayment = 'off' ;
            $telemedPlugin = $this->teleMedAddOnName();
            if($this->isTeleMedActive()) {
                $isTelemedActive = true ;
            }  
            if($isTelemedActive) {
                if (class_exists( 'WooCommerce', false )) {
                    $user->woocommercePayment = 'on' ;
                }
            }
            $kiviProPlugin = $this->kiviCareProOnName();
            $isKiviProActive  = false ;
            if(is_plugin_active($kiviProPlugin)) {
                $isKiviProActive = true ;
            }
            $user->addOns = ['telemed' => $isTelemedActive,'kiviPro' => $isKiviProActive];
        }
        $user->default_clinic = $option_data['id'][0];
        echo json_encode([
            'status' => true,
            'message' => esc_html__('User data', 'kc-lang'),
            'data' => $user
        ]);

    }

	public function changePassword () {

		$request_data = $this->request->getInputs();

		$current_user = wp_get_current_user();

		$result = wp_check_password($request_data['currentPassword'], $current_user->user_pass, $current_user->ID);

		if ($result) {
			if(isset($current_user->ID) && $current_user->ID !== null && $current_user->ID !== '') {
				wp_set_password($request_data['newPassword'], $current_user->ID);
				$status = true ;
				$message = 'Password successfully changed' ;
				wp_logout();
			} else {
				$status = false ;
				$message = 'Password change failed.' ;
			}
		} else {
			$status = false ;
			$message = 'Current password is wrong!!' ;
		}

		echo json_encode([
			'status'  => $status,
			'data' => $result,
			'message' => esc_html__($message, 'kc-lang'),
		]);

	}

	public function getDashboard() {
        $active_domain =$this->getAllActivePlugin();
        if($active_domain === $this->kiviCareProOnName()){
            $user_id = get_current_user_id();
            $userObj = new WP_User($user_id);
            $response = apply_filters('kcpro_get_doctor_dashboard_detail', [
                'user_id'=>$user_id,
                'user_detail' => $userObj ,
            ]);
            echo json_encode($response);
        }else{
            $patients = get_users([
                'role' => $this->getPatientRole()
            ]);

            $doctors = get_users([
                'role' => $this->getDoctorRole()
            ]);

            $appointment = collect((new KCAppointment())->get_all())->count();
            $config = kcGetModules();

            $modules = collect($config->module_config)->where('name','billing')->where('status', 1)->count();
            $bills = 0;
            if($modules > 0){
                $bills = collect((new KCBill())->get_all())->sum('actual_amount');
            }

            $change_log = get_option('is_read_change_log');

            $telemed_change_log = get_option('is_telemed_read_change_log');

            $data = [
                'patient_count' => count($patients),
                'doctor_count'  => count($doctors),
                'appointment_count' => $appointment,
                'revenue'   => $bills,
                'change_log' => $change_log == 1,
                'telemed_log' => (($telemed_change_log == 1) ? false : true )
            ];

            echo json_encode([
                'status'  => true,
                'data' => $data,
                'message' => esc_html__('admin dashboard', 'kc-lang'),
            ]);
     }
    }

    public  function getWeeklyAppointment() {

        global $wpdb;

        $appointments_table = $wpdb->prefix . 'kc_' . 'appointments';


	    $sunday = strtotime("last monday");
	    $sunday = date('w', $sunday) === date('w') ? $sunday+7*86400 : $sunday;
        $monday = strtotime(date("Y-m-d",$sunday)." +6 days");

        $week_start = date("Y-m-d",$sunday);
        $week_end = date("Y-m-d",$monday);

        $appointments = "SELECT * FROM {$appointments_table} WHERE appointment_start_date BETWEEN '{$week_start}' AND '{$week_end}' ";

        $results = $wpdb->get_results($appointments, OBJECT);

        $data = [];

        if(count($results) > 0){
            $appointment_data = collect($results)->groupBy('appointment_start_date');

            $group_date_appointment = $appointment_data->map(function ($item){
                return collect($item)->count();
            });

            $datediff = strtotime($week_end) - strtotime($week_start);
            $datediff = floor($datediff/(60*60*24));

            $group_date_appointment = $group_date_appointment->toArray();

            for($i = 0; $i < $datediff + 1; $i++){
                $count_appointment_date = $group_date_appointment[date("Y-m-d", strtotime($week_start . ' + ' . $i . 'day'))];
                $data[] = [
                    "x" => date("l", strtotime($week_start . ' + ' . $i . 'day')),
                    "y" => ( $count_appointment_date !== null ) ? $count_appointment_date : 0
                ];
            }
        }

        echo json_encode([
            'status'  => true,
            'data' => $data,
            'message' => esc_html__('weekly appointment', 'kc-lang'),
        ]);
    }

	public function getTest () {
		echo json_encode([
			'status' => true,
			'message' => 'Test'
		]);
	}

	public function saveZoomConfiguration() {

        $request_data = $this->request->getInputs();
        
        $service_doctor_mapping = new KCServiceDoctorMapping ;
        
		$rules = [
			'api_key' => 'required',
			'api_secret' => 'required',
			'doctor_id' => 'required',
		];

		$errors = kcValidateRequest($rules, $request_data);

		if (count($errors)) {
			echo json_encode([
				'status' => false,
				'message' => esc_html__($errors[0], 'kc-lang')
			]);
			die;
        }
        
        $data['type'] = 'Telemed' ;

        $telemed_service_id = getServiceId($data);
        
        if(isset($telemed_service_id[0])) {

            $telemed_Service = $telemed_service_id[0]->id ;

        } else {

            $service_data = new KCService;

            $services = [[
                'type' => 'system_service',
                'name' => 'Telemed',
                'price' => 0,
                'status' => 1,
                'created_at' => current_time('Y-m-d H:i:s')
            ]];
        
            $telemed_Service =  $service_data->insert($data);

        }

        $doctor_telemed_service  =  $service_doctor_mapping->get_all(['service_id'=> $telemed_Service, 'doctor_id'  => $request_data['doctor_id']]);

        if(count($doctor_telemed_service) == 0) {
            $service_doctor_mapping->insert([
                'service_id' => $telemed_Service,
                'clinic_id'  => kcGetDefaultClinicId(),
                'doctor_id'  => $request_data['doctor_id'],
                'charges'    => $temp['video_price']
            ]);
        }
       
		$user_meta = get_user_meta( $request_data['doctor_id'], 'zoom_config_data', true );

		if ( $user_meta ) {
            $user_meta = json_decode( $user_meta );
		}

        $response = apply_filters('kct_save_zoom_configuration', [
			'user_id' => $request_data['doctor_id'],
			'enableTeleMed' => $request_data['enableTeleMed'],
			'api_key' => $request_data['api_key'],
			'api_secret' => $request_data['api_secret']
		]);

        echo json_encode($response);

		die;

	}

    public function saveCalenderConfiguration(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_save_calender_config', [
			'doctor_id' => $request_data['doctor_id'],
			'enable' => $request_data['enable'],
			'client_id' => $request_data['client_id'],
			'client_secret' => $request_data['client_secret']
		]);

        echo json_encode($response);
    }

	public function getZoomConfiguration() {
		$request_data = $this->request->getInputs();

		$user_meta = get_user_meta( $request_data['user_id'], 'zoom_config_data', true );

		if ( $user_meta ) {
            $user_meta = json_decode( $user_meta );
            $user_meta->enableTeleMed = (bool)$user_meta->enableTeleMed ;
		} else {
			$user_meta = [];
		}

		echo json_encode([
			'status' => true,
			'message' => esc_html__("Configuration data", 'kc-lang'),
			'data' => $user_meta
		]);
		die;

	}

	public function resendUserCredential() {

        $data = $this->request->getInputs();
        $data =  get_userdata($data['id']);

        if(isset($data->data)) {

            if(isset($data->roles[0]) && $data->roles[0] !==  null) {

                $password = kcGenerateString(12);
                $doctorRole = KIVI_CARE_PREFIX . "doctor" ;
                if ($data->roles[0] === $doctorRole) {

                    wp_set_password($password, $data->data->ID);

                    $user_email_param = array(
                        'username' => $data->data->user_login,
                        'user_email' => $data->data->user_email,
                        'password' => $password,
                        'email_template_type' => 'doctor_registration'
                    );

                }

                kcSendEmail($user_email_param);

            }

            echo json_encode([
                'status' => false,
                'data' => $data
            ]);

        } else {
            echo json_encode([
                'status' => false,
                'message' => esc_html__('Requested user not found', 'kc-lang')
            ]) ;
        }
    }

    public function sendTestEmail () {
        $data = $this->request->getInputs();
        $email_status = wp_mail($data['email'], 'Kivicare test mail', $data['content']);
        if($email_status) {
            echo json_encode([
                'status' => true,
                'message' => esc_html__('Test email sent successfully.', 'kc-lang')
            ]) ;
        } else {
            echo json_encode([
                'status' => false,
                'message' => esc_html__('Test email not sent successfully, Please check your SMTP setup.', 'kc-lang')
            ]) ;
        }
    }

    public function getActivePlugin () {

        if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugins = get_plugins();
        $plugin_name = '' ;

        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-clinic-&-patient-management-system') {
                $plugin_name = $key ;
            }
        }

        echo json_encode([
            'status' => true,
            'message' => esc_html__('Test email not sent successfully, Please check your SMTP setup.', 'kc-lang'),
            'data'  =>  $plugins[$plugin_name]
        ]) ;
    }

    public function setChangeLog () {

        $data = $this->request->getInputs();

        if($data['log_type'] === 'version_read_change') {
            $change_log = update_option('is_read_change_log',1);
        } elseif ($data['log_type'] === 'telemed_read_load') {
            $change_log = update_option('is_telemed_read_change_log',1);
        }

        echo json_encode([
            'status'  => true,
            'data' => $change_log,
            'message' => esc_html__('Change Log', 'kc-lang'),
        ]);
    }

	public function changeWooCommercePaymentStatus () {
		$data = $this->request->getInputs();
		$active_domain =$this->getAllActivePlugin();
		if($active_domain === $this->kiviCareProOnName() && $this->isTeleMedActive() && $this->isWooCommerceActive()){
			$response = apply_filters('kct_change_woocommerce_module_status', [
				'status' => $data['status']
			]);
		}else{
			if($active_domain === $this->kiviCareProOnName() && $this->isWooCommerceActive() ){
				$response = apply_filters('kcpro_change_woocommerce_module_status', [
					'status' => $data['status']
				]);
			}
			if($this->isTeleMedActive() && $this->isWooCommerceActive()) {
				$response = apply_filters('kct_change_woocommerce_module_status', [
					'status' => $data['status']
				]);
			}
		}
		echo json_encode([
			'status'  => true,
			'message' => esc_html__('Woocommerce change status.', 'kc-lang'),
		]);
    }

	public function getWooCommercePaymentStatus () {
		$active_domain =$this->getAllActivePlugin();
		if($active_domain === $this->kiviCareProOnName() && $this->isTeleMedActive() && $this->isWooCommerceActive()){
			$response = apply_filters('kct_get_woocommerce_module_status', []);
		}
		else{
			if($active_domain === $this->kiviCareProOnName() && $this->isWooCommerceActive() ){
				$response = apply_filters('kcpro_get_woocommerce_module_status', []);
			}
			if($this->isTeleMedActive() && $this->isWooCommerceActive()) {
				$response = apply_filters('kct_get_woocommerce_module_status', []);
			}
		}
		echo json_encode([
			'status'  => true,
			'data' => $response,
			'message' => esc_html__('Woocommerce status.', 'kc-lang'),
		]);

    }
    public function getEnableEncounterModule($data){
        $encounter = collect($data->encounter_module_config);
        $encounter_enable = $encounter->where('status', 1)->count();
        if($encounter_enable == 1){
            $class = "12";
        }elseif ($encounter_enable == 2) {
            $class = "6";
        }else{
            $class = "4";
        }
        return $class;
    }

    public function  getClinicRevenue(){
        global $wpdb;
        $request_data = $this->request->getInputs();
        $data   = array();

        $getallClinic = collect((new KCClinic())->get_all());
        $bill_table = $wpdb->prefix . 'kc_' . 'bills';

                
        if(isset($request_data['clinic_id']['id']) && $request_data['clinic_id']['id'] != 'all'){
            $getallClinic  = $getallClinic->where('id',$request_data['clinic_id']['id']);
        }
       
        switch ($request_data['filter_id']['id']) {
            case 'weekly':

                $all_weeks = kcGetAllWeeks(date('Y'));
                if($request_data['sub_type'] == ''){
                    $request_data['sub_type'] = date('W');
                }

                $get_dates  = $all_weeks[date('m')][$request_data['sub_type']];

                $week_start = $get_dates['week_start'];
                $week_end   = $get_dates['week_end'];

                foreach($getallClinic as $clinic) {
                    $bill = "SELECT SUM(actual_amount) AS total_revenue FROM {$bill_table} WHERE payment_status = 'paid' 
                                AND clinic_id =".$clinic->id."  AND created_at BETWEEN '{$week_start}' AND '{$week_end}'" ;

                    $results = $wpdb->get_results($bill);
                    $data[] = (int)$results['0']->total_revenue;
                    $labels[] = $clinic->name;
                }

                break;
            case 'monthly':

                $month = ($request_data['sub_type'] == '') ? date('m') : $request_data['sub_type'];
                $year  = date('Y');

                foreach($getallClinic as $clinic) {
                    $bill     = "SELECT SUM(actual_amount) AS total_revenue FROM {$bill_table} WHERE payment_status = 'paid' 
                                    AND clinic_id =".$clinic->id."  AND MONTH(created_at) = {$month} AND YEAR(created_at) = {$year}" ;
                    $results  = $wpdb->get_results($bill);
                    $data[]   = (int)$results['0']->total_revenue;
                    $labels[] = $clinic->name;
                }

                break;
            case 'yearly':

                $year = ($request_data['sub_type'] == '') ? date('Y') : $request_data['sub_type'];

                foreach($getallClinic as $clinic) {
                    $bill     = "SELECT SUM(actual_amount) AS total_revenue FROM {$bill_table} WHERE payment_status = 'paid' 
                    AND clinic_id =".$clinic->id."  AND YEAR(created_at) = {$year} " ;
                    $results  = $wpdb->get_results($bill);
                    $data[]   = (int)$results['0']->total_revenue;
                    $labels[] = $clinic->name;
                }
                break;
        
            default:
                # code...
                break;
        }

        echo json_encode([
            'status'  => true,
            'data' => $data,
            'labels' => $labels,
            'message' => esc_html__('Clinic Revenue', 'kc-lang'),
        ]);
    }
    public function getClinicBarChart(){
        global $wpdb;
        $bill_table = $wpdb->prefix . 'kc_' . 'bills';
        $request_data = $this->request->getInputs();
        $getallClinic = collect((new KCClinic())->get_all());
        
        if(isset($request_data['clinic_id']['id']) && $request_data['clinic_id']['id'] != 'all'){
            $getallClinic  = $getallClinic->where('id',$request_data['clinic_id']['id']);
        }
       
        switch ( $request_data['filter_id']['id']) {
            case 'weekly':
              
                $all_weeks = kcGetAllWeeks(date('Y'));

                if($request_data['sub_type'] == ''){
                    $request_data['sub_type'] = date('W');
                }

                $get_dates  = $all_weeks[date('m')][$request_data['sub_type']];
                $week_start = $get_dates['week_start'];
                $week_end   = $get_dates['week_end'];
                foreach($getallClinic as $key => $clinic){
                    $data = [];
                    for ($i=$week_start; $i<=$week_end; $i++)
                    {
                        if($key == 0){
                            $date[] = $i;
                        }
                        $bill = "SELECT SUM(actual_amount) AS total_revenue FROM {$bill_table} WHERE payment_status = 'paid' 
                                        AND clinic_id =".$clinic->id."  AND created_at LIKE'%{$i}%'" ;
                        $results = $wpdb->get_results($bill);
                        $data[] = (int)$results['0']->total_revenue;
                    } 
                    $revenue[] = [
                        "name" => $clinic->name,
                        "data" => $data
                    ];
                }
                break;
            case 'monthly':
                $month = ($request_data['sub_type'] == '') ? date('m') : $request_data['sub_type'] ;
                $all_weeks = kcGetAllWeeks(date('Y'));
               
                foreach($getallClinic as $key => $clinic) {
                    $data = [];
                    foreach ($all_weeks[$month] as $w)
                    {
                        if($key == 0){
                            $date[]= $w['week_start'] .' to '. $w['week_end'];
                        }

                        $bill = "SELECT SUM(actual_amount) AS total_revenue FROM {$bill_table} WHERE payment_status = 'paid' 
                                    AND clinic_id =".$clinic->id."  AND created_at BETWEEN '{$w['week_start']}' AND '{$w['week_end']}'";

                        $results = $wpdb->get_results($bill);
                        $data[] = (int)$results['0']->total_revenue;
                    } 
                    $revenue[] = [
                        "name" => $clinic->name,
                        "data" => $data
                    ];
                }

                break;
            case 'yearly':

                $year = ($request_data['sub_type'] == '') ? date('Y') : $request_data['sub_type'];
                $get_all_month = kcGetAllMonth($year);

                foreach($getallClinic as $key => $clinic) {
                    $data = [];
                    foreach ($get_all_month as $m_key => $m)
                    {
                        if($key == 0){
                            $date[]= $m;
                        }

                        $bill = "SELECT SUM(actual_amount) AS total_revenue FROM {$bill_table} WHERE payment_status = 'paid' 
                                    AND clinic_id =".$clinic->id." AND YEAR(created_at) = {$year} AND MONTH(created_at) = {$m_key}";

                        $results = $wpdb->get_results($bill);
                        $data[] = (int)$results['0']->total_revenue;
                    } 
                    $revenue[] = [
                        "name" => $clinic->name,
                        "data" => $data
                    ];
                }
                break;
        
            default:
                # code...
                break;
        }

        echo json_encode([
            'status'  => true,
            'date'=> $date,
            'data'=>$revenue,
            'message' => esc_html__('Clinic Revenue', 'kc-lang'),
        ]);
    }

    public function doctorRevenue(){
        global $wpdb;
        $request_data = $this->request->getInputs();

        $service_mapping_table  = $wpdb->prefix . 'kc_' . 'service_doctor_mapping';
        $bill_item_table        = $wpdb->prefix . 'kc_' . 'bill_items';
        $doctor_clinic_mappings = $wpdb->prefix . 'kc_' . 'doctor_clinic_mappings';
        $bill_table = $wpdb->prefix . 'kc_' . 'bills';
        $revenue_data   = array();
        $doctor_revenue = array();
        $doctor_name    = array();
        $date           = array();
      
        if(isset($request_data['clinic_id']['id']) && $request_data['clinic_id']['id'] != 'all'){

            $get_clnic_doctor = "SELECT doctor_id FROM {$doctor_clinic_mappings} WHERE clinic_id=".$request_data['clinic_id']['id'];
            $results          = collect($wpdb->get_results($get_clnic_doctor))->pluck('doctor_id');

            $get_doctors      = collect(get_users(['role' => $this->getDoctorRole()]));
            $doctors          = collect($get_doctors->whereIn('id',$results))->values();
        }else{
            $doctors    = collect(get_users([
                'role' => $this->getDoctorRole()
            ]));
        }

        switch ( $request_data['filter_id']['id']) {
            case 'weekly':
              
                $all_weeks = kcGetAllWeeks(date('Y'));

                if($request_data['sub_type'] == ''){
                    $request_data['sub_type'] = date('W');
                }

                $get_dates  = $all_weeks[date('m')][$request_data['sub_type']];
                $week_start = $get_dates['week_start'];
                $week_end   = $get_dates['week_end'];
                foreach($doctors as $key =>  $value){
                    $doctor_revenue = [];
                 
                    for ($i=$week_start; $i<=$week_end; $i++)
                    {
                        if($key == 0){
                            $date[] = $i;
                        }

                        $items ="SELECT SUM({$bill_item_table}.price) as revenue, {$bill_item_table}.*, {$bill_table}.*
                                   FROM {$bill_item_table} JOIN {$bill_table} ON {$bill_table}.id = {$bill_item_table}.bill_id
                                   WHERE {$bill_item_table}.item_id IN (SELECT DISTINCT service_id FROM {$service_mapping_table} 
                                   WHERE doctor_id =".$value->data->ID.") AND  {$bill_table}.payment_status = 'paid' AND {$bill_table}.created_at LIKE'%{$i}%'";
                        $data = $wpdb->get_results($items);
                        $doctor_revenue[] = (int)$data['0']->revenue;
                    } 

                    $revenue_data[] = [
                        "name" =>  $value->display_name,
                        "data" => $doctor_revenue,
                    ]; 
                }
                break;
            case 'monthly':

                $month = ($request_data['sub_type'] == '') ? date('m') : $request_data['sub_type'] ;
                $year  = date('Y');

                $all_weeks = kcGetAllWeeks($year);

                foreach($doctors as $key =>  $value){
                    $doctor_revenue = [];
                    foreach ($all_weeks[$month] as $w)
                    {
                        if($key == 0){
                            $date[]= $w['week_start'] .' to '. $w['week_end'];
                        }

                        $items ="SELECT SUM({$bill_item_table}.price) as revenue, {$bill_item_table}.*, {$bill_table}.*
                                   FROM {$bill_item_table} JOIN {$bill_table} ON {$bill_table}.id = {$bill_item_table}.bill_id
                                   WHERE {$bill_item_table}.item_id IN (SELECT DISTINCT service_id FROM {$service_mapping_table} 
                                   WHERE doctor_id =".$value->data->ID.") AND  {$bill_table}.payment_status = 'paid' 
                                   AND {$bill_table}.created_at BETWEEN '{$w['week_start']}' AND '{$w['week_end']}'";

                        $data = $wpdb->get_results($items);
                        $doctor_revenue[] = (int)$data['0']->revenue;
                    } 
                    
                    $revenue_data[] = [
                        "name" =>  $value->display_name,
                        "data" => $doctor_revenue,
                    ]; 
                }


                break;
            case 'yearly':

                $year = ($request_data['sub_type'] == '') ? date('Y') : $request_data['sub_type'];
                $get_all_month = kcGetAllMonth($year);

                foreach($doctors as $key =>  $value){
                    $doctor_revenue = [];
                    foreach ($get_all_month as $m_key => $m)
                    {
                        if($key == 0){
                            $date[]= $m;
                        }

                        $items ="SELECT SUM({$bill_item_table}.price) as revenue, {$bill_item_table}.*, {$bill_table}.*
                                    FROM {$bill_item_table} JOIN {$bill_table} ON {$bill_table}.id = {$bill_item_table}.bill_id
                                    WHERE {$bill_item_table}.item_id IN (SELECT DISTINCT service_id FROM {$service_mapping_table} 
                                    WHERE doctor_id =".$value->data->ID.") AND  {$bill_table}.payment_status = 'paid' 
                                    AND YEAR({$bill_table}.created_at) = {$year} AND MONTH({$bill_table}.created_at) = {$m_key}";

                        $data = $wpdb->get_results($items);
                        $doctor_revenue[] = (int)$data['0']->revenue;
                    } 
                    
                    $revenue_data[] = [
                        "name" =>  $value->display_name,
                        "data" => $doctor_revenue,
                    ]; 
                }
              
                break;
        
            default:
                # code...
                break;
        }

        echo json_encode([
            'status'  => true,
            'data'    => $revenue_data,
            'date'    => $date,
            'message' => esc_html__('Clinic Revenue', 'kc-lang'),
        ]);
    }

    public function getAllReportType(){

        $data['years']  = kcGetYears(date('Y'));
        $data['months'] = kcGetAllMonth();
        $data['weeks']  = kcGetAllWeeksInVue(date('Y'));
        $data['default_week']  = date('W');
        $data['default_month'] = date('m');
        $data['default_year']  = date('Y');

        $d[] = $data;

        echo json_encode([
            'status'  => true,
            'data' => $data,
            'message' => esc_html__('Report Type.', 'kc-lang')
        ]);

    }
}

