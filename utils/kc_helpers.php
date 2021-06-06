<?php

use App\models\KCAppointment;
use App\models\KCClinicSession;
use App\models\KCCustomField;
use App\models\KCCustomFieldData;
use App\models\KCDoctorClinicMapping;

function kcUpdateFields($table_name,$new_fields){
    foreach ($new_fields as $key => $nf){
        $new_field = "ALTER TABLE `{$table_name}` ADD `{$key}` {$nf};";
        maybe_add_column($table_name,$key,$new_field);
    }
}

function kcValidateRequest($rules, $request, $message = [])
{
    $error_messages = [];
    $required_message = ' field is required';
    $email_message =  ' has invalid email address';

    if (count($rules)) {
        foreach ($rules as $key => $rule) {
            if (strpos($rule, '|') !== false) {
                $ruleArray = explode('|', $rule);
                foreach ($ruleArray as $r) {
                    if ($r === 'required') {
                        if (!isset($request[$key])) {
                            $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $required_message;
                        }
                    } elseif ($r === 'email') {
                        if (isset($request[$key])) {
                            if (!filter_var($request[$key], FILTER_VALIDATE_EMAIL)) {
                                $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $email_message;
                            }
                        }
                    }
                }
            } else {
                if ($rule === 'required') {
                    if (!isset($request[$key])) {
                        $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', '', $key) . $required_message;
                    }
                } elseif ($r === 'email') {
                    if (isset($request[$key])) {
                        if (!filter_var($request[$key], FILTER_VALIDATE_EMAIL)) {
                            $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $email_message;
                        }
                    }
                }
            }

        }
    }

    return $error_messages;
}

function kcRecursiveSanitizeTextField($array)
{
    $filterParameters = [];
    foreach ($array as $key => $value) {

        if ($value === '') {
            $filterParameters[$key] = null;
        } else {
            if (is_array($value)) {
                $filterParameters[$key] = kcRecursiveSanitizeTextField($value);
            } else {
                if (preg_match("/<[^<]+>/", $value, $m) !== 0) {
                    $filterParameters[$key] = $value;
                } else {
                    $filterParameters[$key] = sanitize_text_field($value);
                }
            }
        }

    }

    return $filterParameters;
}

function kcGetDoctorTimeSlot($doctor_id)
{
    $timeSlot = "";
    $user_data = get_user_meta($doctor_id, 'basic_data', true);

    if ($user_data) {
        $user_data = json_decode($user_data);
        $timeSlot = isset($user_data->time_slot) ? $user_data->time_slot : "";
    }

    return $timeSlot;
}

/**
 * // Data param required date, clinic_id, doctor_id
 *
 * @param $data
 *
 * @param string $new_time_slot
 * @param $only_available_slots
 * @return array
 */

function kvGetTimeSlots($data, $new_time_slot = "", $only_available_slots = false)
{
    global $wpdb;
    $slots = [];

    $clinic_session_table = $wpdb->prefix . 'kc_' . 'clinic_sessions';

    if (!isset($data['date']) || !isset($data['doctor_id']) || !isset($data['clinic_id'])) {
        return $slots;
    }

    $appointment_day = strtolower(date('l', strtotime($data['date'])));

    // old version unused code
    // if ($new_time_slot === "") {
    //     $time_slot = kcGetDoctorTimeSlot($data['doctor_id']);
    // } else {
    //     $time_slot = $new_time_slot;
    // }
    // if (!$time_slot) {
    //     return $slots;
    // }

    $day_short = substr($appointment_day, 0, 3) ;

    $query = "SELECT * FROM {$clinic_session_table}  WHERE `doctor_id` = ".$data['doctor_id']." AND `clinic_id` = ".$data['clinic_id']."  AND ( `day` = '{$day_short}' OR `day` = '{$appointment_day}') ";
    $clinic_session = collect($wpdb->get_results($query, OBJECT));

    if (count($clinic_session)) {

        $appointmentModel = new KCAppointment();
        $slot_date = $data['date'];
        // $appointments = $appointmentModel->get_by([
        //     'appointment_start_date' => date('Y-m-d', strtotime($data['date'])),
        // ]);
        $appointment_table = $wpdb->prefix . 'kc_appointments' ;

        $appointment_query = "SELECT * FROM " . $appointment_table . " WHERE appointment_start_date = '" . date("Y-m-d", strtotime($data["date"])) . "' AND status != 0 "  ;
    
        $appointments = $wpdb->get_results($appointment_query) ;

        $table_name = $wpdb->prefix . 'kc_clinic_schedule';
        $query = "SELECT * FROM $table_name WHERE `start_date` <= '$slot_date' AND `end_date` >= '$slot_date'  AND `status` = 1";
        $results = collect($wpdb->get_results($query, OBJECT));

        $leaves = $results->filter(function ($result) use ($data) {

            if ($result->module_type === "clinic") {
                if ((int) $result->module_id === (int) $data['clinic_id']) {
                    return true;
                }
            } elseif ($result->module_type === "doctor") {
                if ((int)$result->module_id === (int) $data['doctor_id']) {
                    return true;
                }
            } else {
                return false;
            }

            return false;
        });

        if (count($leaves)) {
            return $slots;
        }

        foreach ($clinic_session as $key => $session) {

            $newTimeSlot = "";
            $time_slot = $session->time_slot ;
            $start_time = new DateTime($session->start_time);
            $time_diff = $start_time->diff(new DateTime($session->end_time));

            if ($time_diff->h !== 0) {
                $time_diff_min = round(($time_diff->h * 60) / $time_slot);
            } else {
                $time_diff_min = round($time_diff->i / $time_slot);
            }

            for ($i = 0; $i <= $time_diff_min; $i++) {

                if ($i === 0) {
                    $newTimeSlot = date('H:i', strtotime($session->start_time));
                } else {
                    $newTimeSlot = date('H:i', strtotime('+' . $time_slot . ' minutes', strtotime($newTimeSlot)));
                }

                if (strtotime($newTimeSlot) < strtotime($session->end_time)) {

                    $temp = [
                        'time' => date('h:i A', strtotime($newTimeSlot)),
                        'available' => true
                    ];

	                $isAvailable = array_filter($appointments, function ($appointment) use ($newTimeSlot, $data) {
                        if ($appointment->appointment_start_time === date('H:i:s', strtotime($newTimeSlot))
                            && (int) $appointment->id !== (int) $data['appointment_id']
                            && (int) $appointment->clinic_id === (int) $data['clinic_id']
                            && (int) $appointment->doctor_id === (int) $data['doctor_id']) {
                            return true;
                        } else {
                            return false;
                        }
                    });

                    if (count($isAvailable)) {
	                    (bool) $temp['available'] = false;
                    }

                    $currentDateTime = current_time('Y-m-d H:i:s');
                    $newDateTime = date('Y-m-d', strtotime($data['date'])) . ' ' . $newTimeSlot . ':00';

                    if (strtotime($newDateTime) < strtotime($currentDateTime)) {
	                    (bool) $temp['available'] = false;
                    }

                    // following condition is for get only available slots
                    if($only_available_slots !== false) {
                        if($temp['available'] !== false) {
                             $slots[$key][] = $temp;
                        }
                    } else {
                        $slots[$key][] = $temp;
                    }
                }
            }
        }
    }
    return array_values($slots);
}

function kvSaveCustomFields($module_type, $module_id, $data)
{
    $customFieldData = new KCCustomFieldData();
    $data = kcRemoveBlankKeyFromArray($data);
    foreach($data as $key => $value) {
        $field_id = str_replace("custom_field_","",$key);
       
        $fieldObj = $customFieldData->get_by(['module_type' => $module_type, 'module_id' => $module_id,'field_id'=>$field_id], '=', true);
        if(gettype($value) === 'array'){
           $value  = json_encode($value);
        }
        $temp = [
            'module_type' => $module_type,
            'module_id' => $module_id,
            'fields_data' => $value,
            'field_id'=>$field_id
        ];
        if ($fieldObj === []) {
            $customFieldData->insert($temp);
        } else {
            $customFieldData->update($temp, ['id' => $fieldObj->id]);
        }
    }
}

function kcRemoveBlankKeyFromArray($data)
{
    foreach($data as $key => $value) {
        if($key === null || $key === '' ) {
            unset($data[$key]);
        }
    }
    return  $data ;
}

function kcGetSetupWizardOptions()
{
    return collect([
        [
            'icon' => "fa fa-info fa-lg",
            'name' => "getting_started",
            'title' => "Welcome",
            'subtitle' => "",
            'prevStep' => '',
            'routeName' => 'setup.step1',
            'nextStep' => 'setup.step3',
            'completed' => false
        ],
        [
            'icon' => "fa fa-clinic-medical fa-lg",
            'name' => "clinic",
            'title' => "Clinic Detail",
            'prevStep' => 'setup.step1',
            'routeName' => 'setup.step3',
            'nextStep' => 'setup.clinic.admin',
            'subtitle' => "",
            'completed' => false
        ],
        [
            'icon' => "fa fa-user fa-lg",
            'name' => "clinic_admin",
            'title' => "Clinic Admin",
            'prevStep' => 'setup.step3',
            'routeName' => 'setup.clinic.admin',
            'nextStep' => 'setup.step6',
            'subtitle' => "",
            'completed' => false
        ]
    ]);
}

function kcGetCustomFields($module_type, $module_id, $data_module_id = 0)
{
    global  $wpdb;
    $user_id = get_current_user_id();
    $userObj = new WP_User($user_id);
    $data = [];
    $custom_field_table =  $wpdb->prefix.'kc_custom_fields';
    $custom_field_data_table =  $wpdb->prefix.'kc_custom_fields_data';
    $type = "'$module_type'";

    if($module_type === 'doctor_module'){
        $query = "SELECT p.*, u.fields_data " .
            "FROM {$custom_field_table} AS p " .
            "LEFT JOIN (SELECT * FROM {$custom_field_data_table} WHERE module_id=".$module_id." ) AS u ON p.id = u.field_id WHERE p.module_type =" .$type;
    }
    if($module_type === 'patient_module'){
        if(current_user_can('administrator')){
            $id = "AND p.module_id =0";
        }if($userObj->roles[0] == 'kiviCare_doctor'){
            $id = "AND p.module_id IN($user_id,0)";
        }
        $query = "SELECT p.*, u.fields_data " .
        "FROM {$custom_field_table} AS p " .
        "LEFT JOIN (SELECT * FROM {$custom_field_data_table} WHERE module_id=".$module_id." ) AS u ON p.id = u.field_id WHERE p.module_type =" .$type . $id;
    }
    if($module_type === 'appointment_module'){
        if(current_user_can('administrator')){
            $id = "AND p.module_id =0";
        }if($userObj->roles[0] == 'kiviCare_doctor'){
            $id = "AND p.module_id IN($user_id,0)";
        }
        $query = "SELECT p.*, u.fields_data " .
            "FROM {$custom_field_table} AS p " .
            "LEFT JOIN (SELECT * FROM {$custom_field_data_table} WHERE module_id=".$module_id." ) AS u ON p.id = u.field_id WHERE p.module_type =" .$type . 
            " AND p.module_id IN($data_module_id,0)";
    }
    if($module_type === 'patient_encounter_module'){
        
        if($userObj->roles[0] == 'kiviCare_doctor'){
            $id = " AND p.module_id IN($user_id,0)";
        }
        $query = "SELECT p.*, u.fields_data " .
            "FROM {$custom_field_table} AS p " .
            "LEFT JOIN (SELECT * FROM {$custom_field_data_table} WHERE module_id=".$module_id." ) AS u ON p.id = u.field_id WHERE p.module_type =" .$type .$id;
    }
    $customData =  $wpdb->get_results($query);

    $fields= [];
    if ($customData !== []) {
        foreach ($customData as $value){
            $fields[] = array_merge(json_decode($value->fields,true), ['field_data'=> $value->fields_data],['id'=> $value->id]);
        }
        $data = $fields;
    }
    if ($data === [] || count($customData) === 0) {
        $customField = (new KCCustomField())->get_by([ 'module_type' => $module_type], '=', true);
        if ($customField !== []) {
            $fields = $customField;
            foreach ($fields as $key => $field) {
                $field_detail = json_decode($field->fields) ;
                if ($field_detail->type === "checkbox") {
                    $data[][$field_detail->name] = [];
                } else {
                    $data[][$field_detail->name] = "";
                }
            }
        }
    }
    return $data;
}

function kcCheckSetupStatus()
{
    // return false is setup is not complete
    $prefix = KIVI_CARE_PREFIX;
    $modules = get_option($prefix . 'modules');
    $total_steps = get_option('total_setup_steps');
    for ($i = 1; $i <= $total_steps; $i++) {
        $current_step_json = get_option('setup_step_' . $i);
        $current_step_array = json_decode($current_step_json);
        if ($modules['module_config']['name'] === 'receptionist' && $modules['module_config']['status'] === '1') {
            continue;
        }

        if ($current_step_array->status === false && $current_step_array->status === null || !$current_step_array->status === '') {
            return false;
        }
    }

    return true;
}

function kcGetAdminPermissions()
{

    $prefix = KIVI_CARE_PREFIX;

    return collect([

	    'read' => ['name' => 'read', 'status' => 1],
        'dashboard' => ['name' => $prefix . 'dashboard', 'status' => 1],
        'setting' => ['name' => $prefix . 'settings', 'status' => 1],
        'doctor_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],
        'patient_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],

        'doctor_list' => ['name' => $prefix . 'doctor_list', 'status' => 1],
        'doctor_add' => ['name' => $prefix . 'doctor_add', 'status' => 1],
        'doctor_edit' => ['name' => $prefix . 'doctor_edit', 'status' => 1],
        'doctor_view' => ['name' => $prefix . 'doctor_view', 'status' => 1],
        'doctor_delete' => ['name' => $prefix . 'doctor_delete', 'status' => 1],

        'receptionist_list' => ['name' => $prefix . 'receptionist_list', 'status' => 1],
        'receptionist_add' => ['name' => $prefix . 'receptionist_add', 'status' => 1],
        'receptionist_edit' => ['name' => $prefix . 'receptionist_edit', 'status' => 1],
        'receptionist_view' => ['name' => $prefix . 'receptionist_view', 'status' => 1],
        'receptionist_delete' => ['name' => $prefix . 'receptionist_delete', 'status' => 1],

        'patient_list' => ['name' => $prefix . 'patient_list', 'status' => 1],
        'patient_add' => ['name' => $prefix . 'patient_add', 'status' => 1],
        'patient_edit' => ['name' => $prefix . 'patient_edit', 'status' => 1],
        'patient_view' => ['name' => $prefix . 'patient_view', 'status' => 1],
        'patient_delete' => ['name' => $prefix . 'patient_delete', 'status' => 1],

        'clinic_list' => ['name' => $prefix . 'clinic_list', 'status' => 1],
        'clinic_add' => ['name' => $prefix . 'clinic_add', 'status' => 1],
        'clinic_edit' => ['name' => $prefix . 'clinic_edit', 'status' => 1],
        'clinic_view' => ['name' => $prefix . 'clinic_view', 'status' => 1],
        'clinic_delete' => ['name' => $prefix . 'clinic_delete', 'status' => 1],
        'clinic_profile' => ['name' => $prefix . 'clinic_profile', 'status' => 1],

        'appointment_list' => ['name' => $prefix . 'appointment_list', 'status' => 1],
        'appointment_add' => ['name' => $prefix . 'appointment_add', 'status' => 1],
        'appointment_edit' => ['name' => $prefix . 'appointment_edit', 'status' => 1],
        'appointment_view' => ['name' => $prefix . 'appointment_view', 'status' => 1],
        'appointment_delete' => ['name' => $prefix . 'appointment_delete', 'status' => 1],

        'service_list' => ['name' => $prefix . 'service_list', 'status' => 1],
        'service_add' => ['name' => $prefix . 'service_add', 'status' => 1],
        'service_edit' => ['name' => $prefix . 'service_edit', 'status' => 1],
        'service_view' => ['name' => $prefix . 'service_view', 'status' => 1],
        'service_delete' => ['name' => $prefix . 'service_delete', 'status' => 1],

        'static_data_list' => ['name' => $prefix . 'static_data_list', 'status' => 1],
        'static_data_add' => ['name' => $prefix . 'static_data_add', 'status' => 1],
        'static_data_edit' => ['name' => $prefix . 'static_data_edit', 'status' => 1],
        'static_data_view' => ['name' => $prefix . 'static_data_view', 'status' => 1],
        'static_data_delete' => ['name' => $prefix . 'static_data_delete', 'status' => 1],

        'patient_encounters'     => [ 'name' => $prefix . 'patient_encounters', 'status' => 1 ],
        'patient_encounter_list' => ['name' => $prefix . 'patient_encounter_list', 'status' => 1],
        'patient_encounter_add' => ['name' => $prefix . 'patient_encounter_add', 'status' => 1],
        'patient_encounter_edit' => ['name' => $prefix . 'patient_encounter_edit', 'status' => 1],
        'patient_encounter_view' => ['name' => $prefix . 'patient_encounter_view', 'status' => 1],
        'patient_encounter_delete' => ['name' => $prefix . 'patient_encounter_delete', 'status' => 1],

        'patient_appointment_status_change' => ['name' => $prefix . 'patient_appointment_status_change', 'status' => 1],

        'medical_records_list' => ['name' => $prefix . 'medical_records_list', 'status' => 1],
        'medical_records_add' => ['name' => $prefix . 'medical_records_add', 'status' => 1],
        'medical_records_edit' => ['name' => $prefix . 'medical_records_edit', 'status' => 1],
        'medical_records_view' => ['name' => $prefix . 'medical_records_view', 'status' => 1],
        'medical_records_delete' => ['name' => $prefix . 'medical_records_delete', 'status' => 1],

        'prescription_list' => ['name' => $prefix . 'prescription_list', 'status' => 1],
        'prescription_add' => ['name' => $prefix . 'prescription_add', 'status' => 1],
        'prescription_edit' => ['name' => $prefix . 'prescription_edit', 'status' => 1],
        'prescription_view' => ['name' => $prefix . 'prescription_view', 'status' => 1],
        'prescription_delete' => ['name' => $prefix . 'prescription_delete', 'status' => 1],

        'patient_bill_list' => ['name' => $prefix . 'patient_bill_list', 'status' => 1],
        'patient_bill_add' => ['name' => $prefix . 'patient_bill_add', 'status' => 1],
        'patient_bill_edit' => ['name' => $prefix . 'patient_bill_edit', 'status' => 1],
        'patient_bill_view' => ['name' => $prefix . 'patient_bill_view', 'status' => 1],
        'patient_bill_delete' => ['name' => $prefix . 'patient_bill_delete', 'status' => 1],

        'custom_field_list' => ['name' => $prefix . 'custom_field_list', 'status' => 1],
        'custom_field_add' => ['name' => $prefix . 'custom_field_add', 'status' => 1],
        'custom_field_edit' => ['name' => $prefix . 'custom_field_edit', 'status' => 1],
        'custom_field_view' => ['name' => $prefix . 'custom_field_view', 'status' => 1],
        'custom_field_delete' => ['name' => $prefix . 'custom_field_delete', 'status' => 1],

        'terms_condition' => ['name' => $prefix . 'terms_condition', 'status' => 1],
        'clinic_schedule' => ['name' => $prefix . 'clinic_schedule', 'status' => 1],
        'common_settings' => ['name' => $prefix . 'common_settings', 'status' => 1],
        'notification_setting' => ['name' => $prefix . 'notification_setting', 'status' => 1],
        'change_password'=>['name' => $prefix . 'change_password', 'status' => 1],

    ]);
}

function kcGetDoctorPermission() {

    $prefix = KIVI_CARE_PREFIX;

    return collect([

        'read' => ['name' => 'read', 'status' => 1],

        'dashboard' => ['name' => $prefix . 'dashboard', 'status' => 1],

        'settings' => ['name' => $prefix . 'settings', 'status' => 1],

        'doctor_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],
        'doctor_profile' => ['name' => $prefix . 'doctor_profile', 'status' => 1],
        'change_password' => ['name' => $prefix . 'change_password', 'status' => 1],

        'appointment_list' => ['name' => $prefix . 'appointment_list', 'status' => 1],
        'appointment_add' => ['name' => $prefix . 'appointment_add', 'status' => 1],
        'appointment_edit' => ['name' => $prefix . 'appointment_edit', 'status' => 1],
        'appointment_view' => ['name' => $prefix . 'appointment_view', 'status' => 1],
        'appointment_delete' => ['name' => $prefix . 'appointment_delete', 'status' => 1],

        'doctor_session_add' => ['name' => $prefix . 'doctor_session_add', 'status' => 1],

        'clinic_schedule' => ['name' => $prefix . 'clinic_schedule', 'status' => 1],

        'service_list' => ['name' => $prefix . 'service_list', 'status' => 1],
        'service_add' => ['name' => $prefix . 'service_add', 'status' => 1],
        'service_edit' => ['name' => $prefix . 'service_edit', 'status' => 1],
        'service_view' => ['name' => $prefix . 'service_view', 'status' => 1],
        'service_delete' => ['name' => $prefix . 'service_delete', 'status' => 1],

        'custom_field_list' => ['name' => $prefix . 'custom_field_list', 'status' => 1],
        'custom_field_add' => ['name' => $prefix . 'custom_field_add', 'status' => 1],
        'custom_field_edit' => ['name' => $prefix . 'custom_field_edit', 'status' => 1],
        'custom_field_view' => ['name' => $prefix . 'custom_field_view', 'status' => 1],
        'custom_field_delete' => ['name' => $prefix . 'custom_field_delete', 'status' => 1],

        'patient_encounters'     => [ 'name' => $prefix . 'patient_encounters', 'status' => 1 ],
        'patient_encounter_list' => ['name' => $prefix . 'patient_encounter_list', 'status' => 1],
        'patient_encounter_add' => ['name' => $prefix . 'patient_encounter_add', 'status' => 1],
        'patient_encounter_edit' => ['name' => $prefix . 'patient_encounter_edit', 'status' => 1],
        'patient_encounter_view' => ['name' => $prefix . 'patient_encounter_view', 'status' => 1],
        'patient_encounter_delete' => ['name' => $prefix . 'patient_encounter_delete', 'status' => 1],

        'patient_appointment_status_change' => ['name' => $prefix . 'patient_appointment_status_change', 'status' => 1],

        'patient_list' => ['name' => $prefix . 'patient_list', 'status' => 1],
        'patient_add' => ['name' => $prefix . 'patient_add', 'status' => 1],
        'patient_edit' => ['name' => $prefix . 'patient_edit', 'status' => 1],
        'patient_view' => ['name' => $prefix . 'patient_view', 'status' => 1],
        'patient_delete' => ['name' => $prefix . 'patient_delete', 'status' => 1],
      
        'medical_records_list' => ['name' => $prefix . 'medical_records_list', 'status' => 1],
        'medical_records_add' => ['name' => $prefix . 'medical_records_add', 'status' => 1],
        'medical_records_edit' => ['name' => $prefix . 'medical_records_edit', 'status' => 1],
        'medical_records_view' => ['name' => $prefix . 'medical_records_view', 'status' => 1],
        'medical_records_delete' => ['name' => $prefix . 'medical_records_delete', 'status' => 1],

        'prescription_list' => ['name' => $prefix . 'prescription_list', 'status' => 1],
        'prescription_add' => ['name' => $prefix . 'prescription_add', 'status' => 1],
        'prescription_edit' => ['name' => $prefix . 'prescription_edit', 'status' => 1],
        'prescription_view' => ['name' => $prefix . 'prescription_view', 'status' => 1],
        'prescription_delete' => ['name' => $prefix . 'prescription_delete', 'status' => 1],

        'patient_bill_list' => ['name' => $prefix . 'patient_bill_list', 'status' => 1],
        'patient_bill_add' => ['name' => $prefix . 'patient_bill_add', 'status' => 1],
        'patient_bill_edit' => ['name' => $prefix . 'patient_bill_edit', 'status' => 1],
        'patient_bill_view' => ['name' => $prefix . 'patient_bill_view', 'status' => 1],
        'patient_bill_delete' => ['name' => $prefix . 'patient_bill_delete', 'status' => 1],

    ]);


}

function kcGetPatientPermissions()
{

    $prefix = KIVI_CARE_PREFIX;

    return collect([

        'read' => ['name' => 'read', 'status' => 1],
        'dashboard' => ['name' => $prefix . 'dashboard', 'status' => 1],
        'patient_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],
        'patient_profile' => ['name' => $prefix . 'patient_profile', 'status' => 1],
        'change_password' => ['name' => $prefix . 'change_password', 'status' => 1],

        'service_list' => ['name' => $prefix . 'service_list', 'status' => 1],
        
        'appointment_list' => ['name' => $prefix . 'appointment_list', 'status' => 1],
        'appointment_add' => ['name' => $prefix . 'appointment_add', 'status' => 1],
        'appointment_edit' => ['name' => $prefix . 'appointment_edit', 'status' => 1],
        'appointment_view' => ['name' => $prefix . 'appointment_view', 'status' => 1],
        'appointment_delete' => ['name' => $prefix . 'appointment_delete', 'status' => 1],

		'patient_encounters'     => [ 'name' => $prefix . 'patient_encounters', 'status' => 1 ],
		'patient_encounter_list'   => [ 'name' => $prefix . 'patient_encounter_list', 'status' => 1 ],
		'patient_encounter_add'    => [ 'name' => $prefix . 'patient_encounter_add', 'status' => 1 ],
		'patient_encounter_edit'   => [ 'name' => $prefix . 'patient_encounter_edit', 'status' => 1 ],
		'patient_encounter_view'   => [ 'name' => $prefix . 'patient_encounter_view', 'status' => 1 ],
		'patient_encounter_delete' => [ 'name' => $prefix . 'patient_encounter_delete', 'status' => 1 ],

        'medical_records_list' => ['name' => $prefix . 'medical_records_list', 'status' => 1],
        'medical_records_view' => ['name' => $prefix . 'medical_records_view', 'status' => 1],

        'prescription_list' => ['name' => $prefix . 'prescription_list', 'status' => 1],
        'prescription_view' => ['name' => $prefix . 'prescription_view', 'status' => 1],

        'patient_bill_list' => ['name' => $prefix . 'patient_bill_list', 'status' => 1],
        'patient_bill_view' => ['name' => $prefix . 'patient_bill_view', 'status' => 1],

	] );

}

function kcGetReceptionistPermission()
{

    $prefix = KIVI_CARE_PREFIX;

    return collect([

        'read' => ['name' => 'read', 'status' => 1],

        'settings' => ['name' => $prefix . 'settings', 'status' => 1],

        'dashboard' => ['name' => $prefix . 'dashboard', 'status' => 1],
        'doctor_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],
        'receptionist_profile' => ['name' => $prefix . 'receptionist_profile', 'status' => 1],
        'change_password' => ['name' => $prefix . 'change_password', 'status' => 1],

        'doctor_list' => ['name' => $prefix . 'doctor_list', 'status' => 1],
        'doctor_add' => ['name' => $prefix . 'doctor_add', 'status' => 1],
        'doctor_edit' => ['name' => $prefix . 'doctor_edit', 'status' => 1],
        'doctor_view' => ['name' => $prefix . 'doctor_view', 'status' => 1],
        'doctor_delete' => ['name' => $prefix . 'doctor_delete', 'status' => 1],

        'patient_list' => ['name' => $prefix . 'patient_list', 'status' => 1],
        'patient_add' => ['name' => $prefix . 'patient_add', 'status' => 0],
        'patient_edit' => ['name' => $prefix . 'patient_edit', 'status' => 1],
        'patient_view' => ['name' => $prefix . 'patient_view', 'status' => 1],
        'patient_delete' => ['name' => $prefix . 'patient_delete', 'status' => 1],

        'clinic_list' => ['name' => $prefix . 'clinic_list', 'status' => 1],
        'clinic_add' => ['name' => $prefix . 'clinic_add', 'status' => 1],
        'clinic_edit' => ['name' => $prefix . 'clinic_edit', 'status' => 1],
        'clinic_view' => ['name' => $prefix . 'clinic_view', 'status' => 1],
        'clinic_delete' => ['name' => $prefix . 'clinic_delete', 'status' => 1],
        'clinic_profile' => ['name' => $prefix . 'clinic_profile', 'status' => 1],

        'service_list' => ['name' => $prefix . 'service_list', 'status' => 1],
        'service_add' => ['name' => $prefix . 'service_add', 'status' => 1],
        'service_edit' => ['name' => $prefix . 'service_edit', 'status' => 1],
        'service_view' => ['name' => $prefix . 'service_view', 'status' => 1],
        'service_delete' => ['name' => $prefix . 'service_delete', 'status' => 1],

        'appointment_list' => ['name' => $prefix . 'appointment_list', 'status' => 1],
        'appointment_add' => ['name' => $prefix . 'appointment_add', 'status' => 1],
        'appointment_edit' => ['name' => $prefix . 'appointment_edit', 'status' => 1],
        'appointment_view' => ['name' => $prefix . 'appointment_view', 'status' => 1],
        'appointment_delete' => ['name' => $prefix . 'appointment_delete', 'status' => 1],

        'patient_encounters'     => [ 'name' => $prefix . 'patient_encounters', 'status' => 1 ],
        'patient_encounter_list' => ['name' => $prefix . 'patient_encounter_list', 'status' => 1],
        'patient_encounter_add' => ['name' => $prefix . 'patient_encounter_add', 'status' => 1],
        'patient_encounter_edit' => ['name' => $prefix . 'patient_encounter_edit', 'status' => 1],
        'patient_encounter_view' => ['name' => $prefix . 'patient_encounter_view', 'status' => 1],
        'patient_encounter_delete' => ['name' => $prefix . 'patient_encounter_delete', 'status' => 1],

        'patient_appointment_status_change' => ['name' => $prefix . 'patient_appointment_status_change', 'status' => 1],

        'medical_records_list' => ['name' => $prefix . 'medical_records_list', 'status' => 1],
        'medical_records_add' => ['name' => $prefix . 'medical_records_add', 'status' => 1],
        'medical_records_edit' => ['name' => $prefix . 'medical_records_edit', 'status' => 1],
        'medical_records_view' => ['name' => $prefix . 'medical_records_view', 'status' => 1],
        'medical_records_delete' => ['name' => $prefix . 'medical_records_delete', 'status' => 1],

        'prescription_list' => ['name' => $prefix . 'prescription_list', 'status' => 1],
        'prescription_add' => ['name' => $prefix . 'prescription_add', 'status' => 1],
        'prescription_edit' => ['name' => $prefix . 'prescription_edit', 'status' => 1],
        'prescription_view' => ['name' => $prefix . 'prescription_view', 'status' => 1],
        'prescription_delete' => ['name' => $prefix . 'prescription_delete', 'status' => 1],

        'patient_bill_list' => ['name' => $prefix . 'patient_bill_list', 'status' => 1],
        'patient_bill_add' => ['name' => $prefix . 'patient_bill_add', 'status' => 1],
        'patient_bill_edit' => ['name' => $prefix . 'patient_bill_edit', 'status' => 1],
        'patient_bill_view' => ['name' => $prefix . 'patient_bill_view', 'status' => 1],
        'patient_bill_delete' => ['name' => $prefix . 'patient_bill_delete', 'status' => 1],

        'clinic_schedule' => ['name' => $prefix . 'clinic_schedule', 'status' => 1],

    ]);
}

function kcCheckPermission($permission_name)
{

    $user_id = get_current_user_id();

    $userObj = (new WP_User($user_id));

    if (in_array(KIVI_CARE_PREFIX . "doctor", $userObj->roles)) {
        $permissions = kcGetDoctorPermission()->toArray();
    } elseif (in_array(KIVI_CARE_PREFIX . "clinic_admin", $userObj->roles)) {
        $permissions = kcGetAdminPermissions()->toArray();
    } elseif (in_array(KIVI_CARE_PREFIX . "patient", $userObj->roles)) {
        $permissions = kcGetPatientPermissions()->toArray();
    } elseif (in_array(KIVI_CARE_PREFIX . "receptionist", $userObj->roles)) {
        $permissions = kcGetReceptionistPermission()->toArray();
    } elseif (in_array("administrator", $userObj->roles)) {
        $permissions = kcGetAdminPermissions()->toArray();
    } else {
        $permissions = collect([])->toArray();
    }

    if (isset($permissions[$permission_name]['name'])) {
        if (current_user_can($permissions[$permission_name]['name'])) {
            return true;
        }
    }

    return false;
}

function kcGetPermission($permission_name)
{
    $permissions = kcGetAdminPermissions()->toArray();

    return $permissions[$permission_name]['name'];
}

function kcGetEmailTemplateKey()
{
    return [
        '{{user_name}}',
        '{{user_password}}',
        '{{user_email}}',
        '{{user_contact}}',
        '{{appointment_date}}',
        '{{appointment_time}}',
        '{{patient_name}}',
        '{{doctor_name}}',
        '{{zoom_link}}'
    ];
}

/**
 * // Data param required date
 *
 * @param $data
 *
 * @return bool
 */

function kcSendEmail($data)
{
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'posts';
    $args['post_name'] = strtolower(KIVI_CARE_PREFIX.$data['email_template_type']);
    $args['post_type'] = strtolower(KIVI_CARE_PREFIX.'mail_tmp') ;

    $query = "SELECT * FROM $table_name WHERE `post_name` = '" . $args['post_name'] . "' AND `post_type` = '".$args['post_type']."' AND post_status = 'publish' ";
    $check_exist_post = $wpdb->get_results($query, ARRAY_A);

    if (count($check_exist_post) > 0) {

        $email_content = $check_exist_post[0]['post_content'];

        $email_content = kcEmailContentKeyReplace($email_content, $data);
        $small_prefix = strtolower(KIVI_CARE_PREFIX);

        switch ($args['post_name']) {
            case $small_prefix.'doctor_registration':
                $email_title = 'Doctor Registration';
                break;
            case $small_prefix.'patient_registration':
                $email_title = 'Patient Registration';
                break;
            case $small_prefix.'receptionist_registration':
                $email_title = 'Receptionist Registration';
                break;
            case $small_prefix.'book_appointment':
                $email_title = 'Appointment Booking';
                break;
            case $small_prefix.'doctor_book_appointment':
                $email_title = 'New Appointment Booking';
                break;
            case $small_prefix.'zoom_link':
                $email_title = 'Telemed Appointment Booking';
                break;
            default:
                $email_title = 'Welcome To Clinic ';
        }

        $email_status = wp_mail($data['user_email'], $email_title, $email_content);

        if ($email_status) {
            return true;
        } else {
            return false;
        }
    }
    else {
        return false ;
    }

}

/**
 * // Data param required content
 *
 * @param $content - email content for replace email template key
 *
 * @return string
 *
 */

function kcEmailContentKeyReplace($content, $data)
{
    $email_template_key = kcGetEmailTemplateKey();
    $email_content = $content;

    if (count($email_template_key) > 0) {
        foreach ($email_template_key as $item => $value) {
            switch ($value) {
                case '{{user_name}}':
                    if(isset($data['username'])) {
                        $email_content = str_replace($value, $data['username'], $email_content);
                    }
                    break;
                case '{{user_password}}':
                    if(isset($data['password'])) {
                        $email_content = str_replace($value, $data['password'], $email_content);
                    }
                    break;
                case '{{user_email}}':
                    if(isset($data['user_email'])) {
                        $email_content = str_replace($value, $data['user_email'], $email_content);
                    }
                    break;
                case '{{appointment_date}}':
                    if(isset($data['appointment_date'])) {
                        $email_content = str_replace($value, $data['appointment_date'], $email_content);
                    }
                    break;
                case '{{appointment_time}}':
                    if(isset($data['appointment_time'])) {
                        $email_content = str_replace($value, $data['appointment_time'], $email_content);
                    }
                    break;
                case '{{patient_name}}':
                    if(isset($data['patient_name'])) {
                        $email_content = str_replace($value, $data['patient_name'], $email_content);
                    }                  
                    break;
                case '{{doctor_name}}':
                    if(isset($data['doctor_name'])) {
                        $email_content = str_replace($value, $data['doctor_name'], $email_content);
                    }                  
                    break;
                case '{{zoom_link}}':
                    if(isset($data['zoom_link'])) {
                        $email_content = str_replace($value, $data['zoom_link'], $email_content);
                    }
                    break;
                default:
	                $email_content = $email_content ;
            }
        }
    }
    return $email_content;

}

function kcGetUserData($user_id) {

	$userObj = new WP_User($user_id);
	$user = $userObj->data;
	$user_data = get_user_meta($userObj->ID, 'basic_data', true);
	if ($user_data) {
		$user_data = json_decode($user_data);
		$user->basicData = $user_data;
	}

	unset($user->user_pass);
	return $user;
}

function kcGenerateString($length_of_string = 10)
{
    // String of all alphanumeric character
    $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    return substr(str_shuffle($str_result),0, $length_of_string);
}

function kcGenerateUsername($first_name)
{

    if (!$first_name || $first_name === "") {
        return "";
    }
    $randomString = kcGenerateString(6);
    $first_name = str_replace(' ', '_', $first_name);
    return $first_name . '_' . $randomString;

}

function kcGetDefaultClinicId()
{
    $option = get_option('setup_step_1');
    if ($option) {
        $option = json_decode($option);
        return $option->id[0];
    } else {
        return 0;
    }
}

function kcGetDefaultClinic() {
    global $wpdb;
    $clinic_table_name = $wpdb->prefix . 'kc_' . 'clinics';
    $clinic_id = kcGetDefaultClinicId();
    $clinic_query = "SELECT * FROM {$clinic_table_name}  WHERE `id` = {$clinic_id} ";
    $wpdb->get_results($clinic_query, 'OBJECT');
}

function kcGetServiceCharges ($service) {
    global $wpdb;
    $service_doctor_mapping_table = $wpdb->prefix . 'kc_' . 'service_doctor_mapping';
    $service_id =  $service['service_id'];
    $doctor_id =  $service['doctor_id'];
    $service_query = "SELECT * FROM  {$service_doctor_mapping_table}  WHERE service_id = {$service_id}  AND doctor_id = {$doctor_id} " ;
    $service_charges = $wpdb->get_results($service_query, 'OBJECT');
    if(count($service_charges)) {
        return $service_charges[0];
    }
    return [];
}

function kcGetServiceById ($service_id) {
    global $wpdb;
    $service = $wpdb->prefix . 'kc_' . 'services';
    $service_query = "SELECT * FROM  {$service} WHERE id = {$service_id} " ;
    $service = $wpdb->get_results($service_query, 'OBJECT');
    if(count($service)) {
        return $service[0];
    }
    return [];
}

function kcCancelAppointments ($data) {

	$start_date = $data['start_date'];
	$end_date = $data['end_date'];
	global $wpdb;

    $app_table_name = $wpdb->prefix . 'kc_' . 'appointments';
    $user_tabel = $wpdb->prefix . 'users' ;

	$appointment_condition  = " `appointment_start_date` >= '$start_date' AND `appointment_start_date` <= '$end_date' " ;

    $query = "UPDATE {$app_table_name} SET `status` = 0  WHERE  {$appointment_condition} AND `status` = 1 " ;

    $select_recepients_query = "SELECT CONCAT(\"'\", GROUP_CONCAT(DISTINCT patient_id SEPARATOR \",'\" ), \"'\") AS patient_id FROM {$app_table_name} WHERE {$appointment_condition}" ;

	if (isset($data['doctor_id'])) {
        $query = $query . " AND doctor_id = " . $data['doctor_id'];
        $select_recepients_query =  $select_recepients_query . " AND doctor_id = " . $data['doctor_id'];
	}

	if (isset($data['clinic_id'])) {
        $query = $query . " AND clinic_id = " . $data['clinic_id'];
        $select_recepients_query =  $select_recepients_query . " AND clinic_id = " . $data['clinic_id'];
	}


    $receptionist = $wpdb->query($select_recepients_query);

    $wpdb->query($query);

}

function kcGetClinicSessions($clinic_id)
{

    $clinic_sessions = collect((new KCClinicSession())->get_by([ 'clinic_id' => $clinic_id]));
    $doctors = collect((new KCDoctorClinicMapping())->get_by([ 'clinic_id' => $clinic_id]))->map(function ($mapping) {
        $doctor = WP_User::get_data_by('ID', $mapping->doctor_id);
        $mapping->doctor_id = [
            'id' => (int)$doctor->ID,
            'label' => $doctor->display_name,
        ];

        $user_data = get_user_meta($doctor->ID, 'basic_data', true);
        $user_data = json_decode($user_data);

        $mapping->doctor_id['timeSlot'] = isset($user_data->time_slot) ? $user_data->time_slot : "";

        return $mapping;
    })->pluck('doctor_id')->toArray();

    $sessions = collect([]);
    if (count($clinic_sessions)) {
        foreach ($clinic_sessions as $session) {
            if ($session->parent_id === null || $session->parent_id === "") {
                $days = [];
                $session_doctors = [];
                $sec_start_time = "";
                $sec_end_time = ""  ;

                $all_clinic_sessions  = collect($clinic_sessions);

				$child_session = $all_clinic_sessions->where('parent_id', $session->id);

				$child_session->all();

	            if(count($child_session) > 0) {
		            foreach ( $clinic_sessions as $child_session ) {
			            if ( $child_session->parent_id !== null && $session->id === $child_session->parent_id ) {
				            array_push( $days, substr($child_session->day, 0, 3) );
				            array_push( $session_doctors, $child_session->doctor_id );

				            if ( $session->start_time !== $child_session->start_time ) {
					            $sec_start_time = $child_session->start_time;
					            $sec_end_time   = $child_session->end_time;
				            }
			            }
		            }
	            } else {

		            array_push($session_doctors, $session->doctor_id);
		            array_push($days, substr($session->day, 0, 3));

	            }


                $start_time = explode(":", date('H:i', strtotime($session->start_time)));
                $end_time = explode(":", date('H:i', strtotime($session->end_time)));


                $session_doctors = array_unique($session_doctors);

                if (count($session_doctors) === 0 && count($days) === 0) {
                    $session_doctors[] = $session->doctor_id;
                    $days[] = substr($session->day, 0, 3);
                } else {
                    $sec_start_time = $sec_start_time !== "" ? explode(":", date('H:i', strtotime($sec_start_time))) : "";
                    $sec_end_time = $sec_end_time !== "" ? explode(":", date('H:i', strtotime($sec_end_time))) : "";
                }

                $new_doctors = [];

                foreach ($session_doctors as $doctor_id) {
                    foreach ($doctors as $doctor) {
                        if ((int)$doctor['id'] === (int)$doctor_id) {
                            $new_doctors = $doctor;
                        }
                    }
                }

                $new_session = [
                    'id' => $session->id,
                    'clinic_id' => $session->clinic_id,
                    'doctor_id' => $session->doctor_id,
                    'days' => array_values(array_unique($days)),
                    'doctors' => $new_doctors,
                    'time_slot' => $session->time_slot,
                    's_one_start_time' => [
                        "HH" => $start_time[0],
                        "mm" => $start_time[1],
                    ],
                    's_one_end_time' => [
                        "HH" => $end_time[0],
                        "mm" => $end_time[1],
                    ],
                    's_two_start_time' => [
                        "HH" => isset($sec_start_time[0]) ? $sec_start_time[0] : "",
                        "mm" => isset($sec_start_time[1]) ? $sec_start_time[1] : "",
                    ],
                    's_two_end_time' => [
                        "HH" => isset($sec_end_time[0]) ? $sec_end_time[0] : "",
                        "mm" => isset($sec_end_time[1]) ? $sec_end_time[1] : "",
                    ]
                ];

                $sessions->push($new_session);

            }
        }
    }

    return $sessions;
}

function getServiceId($data) {

    global $wpdb;
    $service = $wpdb->prefix . 'kc_' . 'services';
    if($data['type'] === 'Telemed') {
        $condition  = " AND type = 'system_service' AND name = '{$data['type']}' " ;
    } else {
        $condition  = " AND name = '{$data['type']}' " ;
    }
    
    $service_query = "SELECT * FROM {$service} WHERE 0 = 0 " . $condition ;
    $service_id = $wpdb->get_results($service_query, 'OBJECT');
    if($service_id) {
        return $service_id ;
    } else {
        $data->id = 0 ;
    }
    
}

function kcGetModules()
{
    $prefix = KIVI_CARE_PREFIX;
    $modules = get_option($prefix . 'modules');
    if ($modules) {
        return json_decode($modules);
    } else {
        return '';
    }
}

function kcGetStepConfig()
{
    $prefix = KIVI_CARE_PREFIX;
    $modules = get_option($prefix . 'setup_config');
    if ($modules) {
        return json_decode($modules);
    } else {
        return '';
    }
}

function kcGetZoomConfig($user_id) {
	$user_meta = get_user_meta( $user_id, 'zoom_config_data', true );

	if ($user_meta) {
		return json_decode($user_meta);
	} else {
		return [];
	}
}

function kcGetDoctorOption($doctor_id)
{
    $temp = [];
    $doctor = WP_User::get_data_by('ID', $doctor_id);

    if ($doctor) {
        $temp = [
            'id' => (int)$doctor->ID,
            'label' => $doctor->display_name,
        ];

        $user_data = get_user_meta($doctor->ID, 'basic_data', true);
        $user_data = json_decode($user_data);

        $temp['timeSlot'] = isset($user_data->time_slot) ? $user_data->time_slot : "";
    }

    return $temp;

}

function kcGetAppointments($data) {
    global $wpdb;
    $appointment_table = $wpdb->prefix . 'kc_' . 'appointments';
    $doctor_appointments_query = "SELECT * FROM {$appointment_table}  WHERE  doctor_id = {$data['doctor_id']}  AND  clinic_id = {$data['clinic_id']} AND status = 1" ;
    $appointments = $wpdb->get_results($doctor_appointments_query);
    if($appointments) {
        return $appointments ;
    } else {
        return [] ;
    }
}

function kcCountryCurrencyList ($search = '') {
    return array(
        'AED' =>  'United Arab Emirates dirham' ,
        'AFN' =>  'Afghan afghani' ,
        'ALL' =>  'Albanian lek' ,
        'AMD' =>  'Armenian dram' ,
        'ANG' =>  'Netherlands Antillean guilder' ,
        'AOA' =>  'Angolan kwanza' ,
        'ARS' =>  'Argentine peso' ,
        'AUD' =>  'Australian dollar' ,
        'AWG' =>  'Aruban florin' ,
        'AZN' =>  'Azerbaijani manat' ,
        'BAM' =>  'Bosnia and Herzegovina convertible mark' ,
        'BBD' =>  'Barbadian dollar' ,
        'BDT' =>  'Bangladeshi taka' ,
        'BGN' =>  'Bulgarian lev' ,
        'BHD' =>  'Bahraini dinar' ,
        'BIF' =>  'Burundian franc' ,
        'BMD' =>  'Bermudian dollar' ,
        'BND' =>  'Brunei dollar' ,
        'BOB' =>  'Bolivian boliviano' ,
        'BRL' =>  'Brazilian real' ,
        'BSD' =>  'Bahamian dollar' ,
        'BTN' =>  'Bhutanese ngultrum' ,
        'BWP' =>  'Botswana pula' ,
        'BYR' =>  'Belarusian ruble (old)' ,
        'BYN' =>  'Belarusian ruble' ,
        'BZD' =>  'Belize dollar' ,
        'CAD' =>  'Canadian dollar' ,
        'CDF' =>  'Congolese franc' ,
        'CHF' =>  'Swiss franc' ,
        'CLP' =>  'Chilean peso' ,
        'CNY' =>  'Chinese yuan' ,
        'COP' =>  'Colombian peso' ,
        'CRC' =>  'Costa Rican col&oacute;n' ,
        'CUC' =>  'Cuban convertible peso' ,
        'CUP' =>  'Cuban peso' ,
        'CVE' =>  'Cape Verdean escudo' ,
        'CZK' =>  'Czech koruna' ,
        'DJF' =>  'Djiboutian franc' ,
        'DKK' =>  'Danish krone' ,
        'DOP' =>  'Dominican peso' ,
        'DZD' =>  'Algerian dinar' ,
        'EGP' =>  'Egyptian pound' ,
        'ERN' =>  'Eritrean nakfa' ,
        'ETB' =>  'Ethiopian birr' ,
        'EUR' =>  'Euro' ,
        'FJD' =>  'Fijian dollar' ,
        'FKP' =>  'Falkland Islands pound' ,
        'GBP' =>  'Pound sterling' ,
        'GEL' =>  'Georgian lari' ,
        'GGP' =>  'Guernsey pound' ,
        'GHS' =>  'Ghana cedi' ,
        'GIP' =>  'Gibraltar pound' ,
        'GMD' =>  'Gambian dalasi' ,
        'GNF' =>  'Guinean franc' ,
        'GTQ' =>  'Guatemalan quetzal' ,
        'GYD' =>  'Guyanese dollar' ,
        'HKD' =>  'Hong Kong dollar' ,
        'HNL' =>  'Honduran lempira' ,
        'HRK' =>  'Croatian kuna' ,
        'HTG' =>  'Haitian gourde' ,
        'HUF' =>  'Hungarian forint' ,
        'IDR' =>  'Indonesian rupiah' ,
        'ILS' =>  'Israeli new shekel' ,
        'IMP' =>  'Manx pound' ,
        'INR' =>  'Indian rupee' ,
        'IQD' =>  'Iraqi dinar' ,
        'IRR' =>  'Iranian rial' ,
        'IRT' =>  'Iranian toman' ,
        'ISK' =>  'Icelandic kr&oacute;na' ,
        'JEP' =>  'Jersey pound' ,
        'JMD' =>  'Jamaican dollar' ,
        'JOD' =>  'Jordanian dinar' ,
        'JPY' =>  'Japanese yen' ,
        'KES' =>  'Kenyan shilling' ,
        'KGS' =>  'Kyrgyzstani som' ,
        'KHR' =>  'Cambodian riel' ,
        'KMF' =>  'Comorian franc' ,
        'KPW' =>  'North Korean won' ,
        'KRW' =>  'South Korean won' ,
        'KWD' =>  'Kuwaiti dinar' ,
        'KYD' =>  'Cayman Islands dollar' ,
        'KZT' =>  'Kazakhstani tenge' ,
        'LAK' =>  'Lao kip' ,
        'LBP' =>  'Lebanese pound' ,
        'LKR' =>  'Sri Lankan rupee' ,
        'LRD' =>  'Liberian dollar' ,
        'LSL' =>  'Lesotho loti' ,
        'LYD' =>  'Libyan dinar' ,
        'MAD' =>  'Moroccan dirham' ,
        'MDL' =>  'Moldovan leu' ,
        'MGA' =>  'Malagasy ariary' ,
        'MKD' =>  'Macedonian denar' ,
        'MMK' =>  'Burmese kyat' ,
        'MNT' =>  'Mongolian t&ouml;gr&ouml;g' ,
        'MOP' =>  'Macanese pataca' ,
        'MRU' =>  'Mauritanian ouguiya' ,
        'MUR' =>  'Mauritian rupee' ,
        'MVR' =>  'Maldivian rufiyaa' ,
        'MWK' =>  'Malawian kwacha' ,
        'MXN' =>  'Mexican peso' ,
        'MYR' =>  'Malaysian ringgit' ,
        'MZN' =>  'Mozambican metical' ,
        'NAD' =>  'Namibian dollar' ,
        'NGN' =>  'Nigerian naira' ,
        'NIO' =>  'Nicaraguan c&oacute;rdoba' ,
        'NOK' =>  'Norwegian krone' ,
        'NPR' =>  'Nepalese rupee' ,
        'NZD' =>  'New Zealand dollar' ,
        'OMR' =>  'Omani rial' ,
        'PAB' =>  'Panamanian balboa' ,
        'PEN' =>  'Sol' ,
        'PGK' =>  'Papua New Guinean kina' ,
        'PHP' =>  'Philippine peso' ,
        'PKR' =>  'Pakistani rupee' ,
        'PLN' =>  'Polish z&#x142;oty' ,
        'PRB' =>  'Transnistrian ruble' ,
        'PYG' =>  'Paraguayan guaran&iacute;' ,
        'QAR' =>  'Qatari riyal' ,
        'RON' =>  'Romanian leu' ,
        'RSD' =>  'Serbian dinar' ,
        'RUB' =>  'Russian ruble' ,
        'RWF' =>  'Rwandan franc' ,
        'SAR' =>  'Saudi riyal' ,
        'SBD' =>  'Solomon Islands dollar' ,
        'SCR' =>  'Seychellois rupee' ,
        'SDG' =>  'Sudanese pound' ,
        'SEK' =>  'Swedish krona' ,
        'SGD' =>  'Singapore dollar' ,
        'SHP' =>  'Saint Helena pound' ,
        'SLL' =>  'Sierra Leonean leone' ,
        'SOS' =>  'Somali shilling' ,
        'SRD' =>  'Surinamese dollar' ,
        'SSP' =>  'South Sudanese pound' ,
        'STN' =>  'S&atilde;o Tom&eacute; and Pr&iacute;ncipe dobra' ,
        'SYP' =>  'Syrian pound' ,
        'SZL' =>  'Swazi lilangeni' ,
        'THB' =>  'Thai baht' ,
        'TJS' =>  'Tajikistani somoni' ,
        'TMT' =>  'Turkmenistan manat' ,
        'TND' =>  'Tunisian dinar' ,
        'TOP' =>  'Tongan pa&#x2bb;anga' ,
        'TRY' =>  'Turkish lira' ,
        'TTD' =>  'Trinidad and Tobago dollar' ,
        'TWD' =>  'New Taiwan dollar' ,
        'TZS' =>  'Tanzanian shilling' ,
        'UAH' =>  'Ukrainian hryvnia' ,
        'UGX' =>  'Ugandan shilling' ,
        'USD' =>  'United States (US) dollar' ,
        'UYU' =>  'Uruguayan peso' ,
        'UZS' => 'Uzbekistani som' ,
        'VEF' => 'Venezuelan bol&iacute;var' ,
        'VES' => 'Bol&iacute;var soberano' ,
        'VND' => 'Vietnamese &#x111;&#x1ed3;ng' ,
        'VUV' => 'Vanuatu vatu' ,
        'WST' => 'Samoan t&#x101;l&#x101;' ,
        'XAF' => 'Central African CFA franc' ,
        'XCD' => 'East Caribbean dollar' ,
        'XOF' => 'West African CFA franc' ,
        'XPF' => 'CFP franc' ,
        'YER' => 'Yemeni rial' ,
        'ZAR' => 'South African rand' ,
        'ZMW' => 'Zambian kwacha' ,
    ) ;
}

function kcCountryCurrencySymbolsList() {
    return array(
        'AED' => '&#x62f;.&#x625;',
        'AFN' => '&#x60b;',
        'ALL' => 'L',
        'AMD' => 'AMD',
        'ANG' => '&fnof;',
        'AOA' => 'Kz',
        'ARS' => '&#36;',
        'AUD' => '&#36;',
        'AWG' => 'Afl.',
        'AZN' => 'AZN',
        'BAM' => 'KM',
        'BBD' => '&#36;',
        'BDT' => '&#2547;&nbsp;',
        'BGN' => '&#1083;&#1074;.',
        'BHD' => '.&#x62f;.&#x628;',
        'BIF' => 'Fr',
        'BMD' => '&#36;',
        'BND' => '&#36;',
        'BOB' => 'Bs.',
        'BRL' => '&#82;&#36;',
        'BSD' => '&#36;',
        'BTC' => '&#3647;',
        'BTN' => 'Nu.',
        'BWP' => 'P',
        'BYR' => 'Br',
        'BYN' => 'Br',
        'BZD' => '&#36;',
        'CAD' => '&#36;',
        'CDF' => 'Fr',
        'CHF' => '&#67;&#72;&#70;',
        'CLP' => '&#36;',
        'CNY' => '&yen;',
        'COP' => '&#36;',
        'CRC' => '&#x20a1;',
        'CUC' => '&#36;',
        'CUP' => '&#36;',
        'CVE' => '&#36;',
        'CZK' => '&#75;&#269;',
        'DJF' => 'Fr',
        'DKK' => 'DKK',
        'DOP' => 'RD&#36;',
        'DZD' => '&#x62f;.&#x62c;',
        'EGP' => 'EGP',
        'ERN' => 'Nfk',
        'ETB' => 'Br',
        'EUR' => '&euro;',
        'FJD' => '&#36;',
        'FKP' => '&pound;',
        'GBP' => '&pound;',
        'GEL' => '&#x20be;',
        'GGP' => '&pound;',
        'GHS' => '&#x20b5;',
        'GIP' => '&pound;',
        'GMD' => 'D',
        'GNF' => 'Fr',
        'GTQ' => 'Q',
        'GYD' => '&#36;',
        'HKD' => '&#36;',
        'HNL' => 'L',
        'HRK' => 'kn',
        'HTG' => 'G',
        'HUF' => '&#70;&#116;',
        'IDR' => 'Rp',
        'ILS' => '&#8362;',
        'IMP' => '&pound;',
        'INR' => '&#8377;',
        'IQD' => '&#x639;.&#x62f;',
        'IRR' => '&#xfdfc;',
        'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
        'ISK' => 'kr.',
        'JEP' => '&pound;',
        'JMD' => '&#36;',
        'JOD' => '&#x62f;.&#x627;',
        'JPY' => '&yen;',
        'KES' => 'KSh',
        'KGS' => '&#x441;&#x43e;&#x43c;',
        'KHR' => '&#x17db;',
        'KMF' => 'Fr',
        'KPW' => '&#x20a9;',
        'KRW' => '&#8361;',
        'KWD' => '&#x62f;.&#x643;',
        'KYD' => '&#36;',
        'KZT' => '&#8376;',
        'LAK' => '&#8365;',
        'LBP' => '&#x644;.&#x644;',
        'LKR' => '&#xdbb;&#xdd4;',
        'LRD' => '&#36;',
        'LSL' => 'L',
        'LYD' => '&#x644;.&#x62f;',
        'MAD' => '&#x62f;.&#x645;.',
        'MDL' => 'MDL',
        'MGA' => 'Ar',
        'MKD' => '&#x434;&#x435;&#x43d;',
        'MMK' => 'Ks',
        'MNT' => '&#x20ae;',
        'MOP' => 'P',
        'MRU' => 'UM',
        'MUR' => '&#x20a8;',
        'MVR' => '.&#x783;',
        'MWK' => 'MK',
        'MXN' => '&#36;',
        'MYR' => '&#82;&#77;',
        'MZN' => 'MT',
        'NAD' => 'N&#36;',
        'NGN' => '&#8358;',
        'NIO' => 'C&#36;',
        'NOK' => '&#107;&#114;',
        'NPR' => '&#8360;',
        'NZD' => '&#36;',
        'OMR' => '&#x631;.&#x639;.',
        'PAB' => 'B/.',
        'PEN' => 'S/',
        'PGK' => 'K',
        'PHP' => '&#8369;',
        'PKR' => '&#8360;',
        'PLN' => '&#122;&#322;',
        'PRB' => '&#x440;.',
        'PYG' => '&#8370;',
        'QAR' => '&#x631;.&#x642;',
        'RMB' => '&yen;',
        'RON' => 'lei',
        'RSD' => '&#1088;&#1089;&#1076;',
        'RUB' => '&#8381;',
        'RWF' => 'Fr',
        'SAR' => '&#x631;.&#x633;',
        'SBD' => '&#36;',
        'SCR' => '&#x20a8;',
        'SDG' => '&#x62c;.&#x633;.',
        'SEK' => '&#107;&#114;',
        'SGD' => '&#36;',
        'SHP' => '&pound;',
        'SLL' => 'Le',
        'SOS' => 'Sh',
        'SRD' => '&#36;',
        'SSP' => '&pound;',
        'STN' => 'Db',
        'SYP' => '&#x644;.&#x633;',
        'SZL' => 'L',
        'THB' => '&#3647;',
        'TJS' => '&#x405;&#x41c;',
        'TMT' => 'm',
        'TND' => '&#x62f;.&#x62a;',
        'TOP' => 'T&#36;',
        'TRY' => '&#8378;',
        'TTD' => '&#36;',
        'TWD' => '&#78;&#84;&#36;',
        'TZS' => 'Sh',
        'UAH' => '&#8372;',
        'UGX' => 'UGX',
        'USD' => '&#36;',
        'UYU' => '&#36;',
        'UZS' => 'UZS',
        'VEF' => 'Bs F',
        'VES' => 'Bs.S',
        'VND' => '&#8363;',
        'VUV' => 'Vt',
        'WST' => 'T',
        'XAF' => 'CFA',
        'XCD' => '&#36;',
        'XOF' => 'CFA',
        'XPF' => 'Fr',
        'YER' => '&#xfdfc;',
        'ZAR' => '&#82;',
        'ZMW' => 'ZK',
    ) ;
}

function kc_resend_credentials ($user_id) {
   $user_data = get_userdata($user_id );
   print_r($user_data);
   die;
}

function dd($data) {
	echo "<pre>";
	print_r($data);die;
	echo "</pre>";
}

function getPluginData () {
    
    if (!function_exists('get_plugins')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = get_plugins();
    echo '<pre>';
    print_r($plugins);
    die;
}

function kcGetTimeZone() {
	$current_offset = get_option( 'gmt_offset' );
	$tzstring       = get_option( 'timezone_string' );



	$check_zone_info = true;

	// Remove old Etc mappings. Fallback to gmt_offset.
	if ( false !== strpos( $tzstring, 'Etc/GMT' ) ) {
		$tzstring = '';
	}

	if ( empty( $tzstring ) ) { // Create a UTC+- zone if no timezone string exists.
		$check_zone_info = false;
		if ( 0 == $current_offset ) {
			$tzstring = 'UTC+0';
		} elseif ( $current_offset < 0 ) {
			$tzstring = 'UTC' . $current_offset;
		} else {
			$tzstring = 'UTC+' . $current_offset;
		}
	}

	return $tzstring;
}

function kcAppointmentServiceMapping ($patient_id,$appointment_id) {
}

function kcGetYears($end_year = ''){
    $start_year = 2020;
    for ($i = $start_year; $i <= $end_year; $i++)
        $years[$i] = $i;
    return $years;
}

function kcGetAllWeeks($year){

    $date = new DateTime;
    $date->setISODate($year, 53);

    $weeks = ($date->format("W") === "53" ? 53 : 52);
    $data = [];

    for($x=1; $x<=$weeks; $x++){
        $dto = new DateTime();
        $dates['week_start'] = $dto->setISODate($year, $x)->format('Y-m-d');
        $dates['week_end']   = $dto->modify('+6 days')->format('Y-m-d');
        if($x<10) {
            $x = '0'.$x;  
        }
        $data[date('m', strtotime($dates['week_start']))][$x] =  $dates;
    }
    return $data;
}

function kcGetAllWeeksInVue($year){

    $date = new DateTime;
    $date->setISODate($year, 53);

    $weeks = ($date->format("W") === "53" ? 53 : 52);
    $data = [];

    for($x=1; $x<=$weeks; $x++){
        $dto = new DateTime();
        $dates['week_start'] = $dto->setISODate($year, $x)->format('Y-m-d');
        $dates['week_end']   = $dto->modify('+6 days')->format('Y-m-d');

        if($x<10) {
            $x = '0'.$x;  
        }
        $data[$x] = 'week-'.$x.' ('.$dates['week_start'] .' to '. $dates['week_end'].')';
    }

    return $data;
}

function kcGetAllMonth() {
    $month    = [];
    for($i=1;$i<13;$i++) {
        $date = strtotime('2021-'.$i.'-01');
        $month[date('m',$date)] = date('F',$date);
    }

    return $month;
}