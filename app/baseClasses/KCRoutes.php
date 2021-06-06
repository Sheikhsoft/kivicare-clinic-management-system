<?php

namespace App\baseClasses;

class KCRoutes {
	public function routes() {

		return array(

			'get_dashboard'              => [ 'method' => 'get', 'action' => 'KCHomeController@getDashboard' ],
            'set_change_log'             => [ 'method' => 'post', 'action' => 'KCHomeController@setChangeLog' ],

            'get_weekly_appointment'     => [ 'method' => 'get', 'action' => 'KCHomeController@getWeeklyAppointment' ],
            'get_clinic_revenue'     => [ 'method' => 'post', 'action' => 'KCHomeController@getClinicRevenue' ],
            'get_clinic_bar_revenue'     => [ 'method' => 'post', 'action' => 'KCHomeController@getClinicBarChart' ],
            'get_doctor_wise_revenue'     => [ 'method' => 'post', 'action' => 'KCHomeController@doctorRevenue' ],

			'get_static_data'            => [ 'method' => 'post', 'action' => 'KCHomeController@getStaticData' ],
			'get_custom_fields'          => [ 'method' => 'post', 'action' => 'KCHomeController@kcGetCustomFields' ],
			'get_user'                   => [ 'method' => 'post', 'action' => 'KCHomeController@getUser' ],
			'logout'                     => [ 'method' => 'post', 'action' => 'KCHomeController@logout' ],
			'change_password'            => [ 'method' => 'post', 'action' => 'KCHomeController@changePassword' ],
            'resend_credential'          => [ 'method' => 'post', 'action' => 'KCHomeController@resendUserCredential' ],
            'pluginData'                 => [ 'method' => 'post', 'action' => 'KCHomeController@getActivePlugin' ],
            'change_payment_status'      => [ 'method' => 'post', 'action' => 'KCHomeController@changeWooCommercePaymentStatus' ],
            'get_payment_status'         => [ 'method' => 'post', 'action' => 'KCHomeController@getWooCommercePaymentStatus' ],

			// Setup wizard module start here...
			'get_setup_step_status'      => [ 'method' => 'post', 'action' => 'KCSetupController@getSetupStepStatus' ],
			'setup_step_status'          => [ 'method' => 'get', 'action' => 'KCHomeController@step_status' ],
			'setup_clinic'               => [ 'method' => 'post', 'action' => 'KCSetupController@clinic' ],
            'setup_clinic_admin'               => [ 'method' => 'post', 'action' => 'KCSetupController@clinicAdmin' ],
			'setup_doctor'               => [ 'method' => 'post', 'action' => 'KCSetupController@doctor' ],
			'setup_receptionist'         => [ 'method' => 'post', 'action' => 'KCSetupController@receptionist' ],
			'setup_clinic_session'       => [ 'method' => 'post', 'action' => 'KCSetupController@clinicSession' ],
			'setup_finish'               => [ 'method' => 'post', 'action' => 'KCSetupController@setupFinish' ],
			'update_setup_step'          => [ 'method' => 'post', 'action' => 'KCSetupController@updateSetupStep' ],

			// Module configuration start here...
			'module_list'      => [ 'method' => 'post', 'action' => 'KCModuleController@index' ],
			'encounter_module_list'      => [ 'method' => 'post', 'action' => 'KCModuleController@encounterModules' ],
			'prescription_module_list'      => [ 'method' => 'post', 'action' => 'KCModuleController@prescriptionModules' ],
			'module_save'      => [ 'method' => 'post', 'action' => 'KCModuleController@save' ],

			// Static data module routes start here...
			'static_data_list'           => [ 'method' => 'post', 'action' => 'KCStaticDataController@index' ],
			'static_data_save'           => [ 'method' => 'post', 'action' => 'KCStaticDataController@save' ],
			'static_data_edit'           => [ 'method' => 'post', 'action' => 'KCStaticDataController@edit' ],
			'static_data_delete'         => [ 'method' => 'post', 'action' => 'KCStaticDataController@delete' ],
			'get_email_template'         => [ 'method' => 'post', 'action' => 'KCStaticDataController@getEmailTemplate' ],
			'get_sms_template'         => [ 'method' => 'post', 'action' => 'KCStaticDataController@getSMSTemplate' ],
			'get_option'                 => [ 'method' => 'post', 'action' => 'KCStaticDataController@getOption' ],
			'save_common_settings'       => [ 'method' => 'post', 'action' => 'KCStaticDataController@saveCommonSettings' ],
			'save_email_template'        => [ 'method' => 'post', 'action' => 'KCStaticDataController@saveEmailTemplate' ],
			'save_sms_template'        => [ 'method' => 'post', 'action' => 'KCStaticDataController@saveSMSTemplate' ],
			'terms_condition_save'       => [ 'method' => 'post', 'action' => 'KCStaticDataController@saveTermsCondition' ],
			'terms_condition_list'       => [ 'method' => 'get', 'action' => 'KCStaticDataController@getTermsCondition' ],
            'get_country_list'           => [ 'method' => 'get', 'action' => 'KCStaticDataController@getCountryCurrencyList' ],
            'get_lang_dynamic_value'     => [ 'method' => 'post', 'action' => 'KCStaticDataController@getLangDynamicKeyValue' ],

			// Doctor module routes starts here...
			'doctor_list'                => [ 'method' => 'post', 'action' => 'KCDoctorController@index' ],
			'doctor_save'                => [ 'method' => 'post', 'action' => 'KCDoctorController@save' ],
			'doctor_edit'                => [ 'method' => 'post', 'action' => 'KCDoctorController@edit' ],
			'doctor_delete'              => [ 'method' => 'post', 'action' => 'KCDoctorController@delete' ],
			'doctor_change_email'        => [ 'method' => 'post', 'action' => 'KCDoctorController@changeEmail' ],

			// Receptionist module routes starts here...
			'receptionist_list'          => [ 'method' => 'post', 'action' => 'KCReceptionistController@index' ],
			'receptionist_edit'          => [ 'method' => 'post', 'action' => 'KCReceptionistController@edit' ],
			'receptionist_save'          => [ 'method' => 'post', 'action' => 'KCReceptionistController@save' ],
			'receptionist_delete'        => [ 'method' => 'post', 'action' => 'KCReceptionistController@delete' ],

			// Patient module routes starts here...
			'patient_list'               => [ 'method' => 'post', 'action' => 'KCPatientController@index' ],
			'patient_save'               => [ 'method' => 'post', 'action' => 'KCPatientController@save' ],
			'patient_edit'               => [ 'method' => 'post', 'action' => 'KCPatientController@edit' ],
			'patient_delete'             => [ 'method' => 'post', 'action' => 'KCPatientController@delete' ],

			// Clinics module routes starts here...
			'clinic_list'                => [ 'method' => 'post', 'action' => 'KCClinicController@index' ],
			'clinic_save'                => [ 'method' => 'post', 'action' => 'KCClinicController@save' ],
			'clinic_edit'                => [ 'method' => 'post', 'action' => 'KCClinicController@edit' ],
			'clinic_delete'              => [ 'method' => 'post', 'action' => 'KCClinicController@delete' ],
			'clinic_admin_edit'                => [ 'method' => 'post', 'action' => 'KCClinicController@clinicAdminEdit' ],
            // Clinic session routes starts here...
            'clinic_session_save'        => [ 'method' => 'post', 'action' => 'KCClinicController@clinicSessionSave' ],
            'clinic_session_delete'      => [ 'method' => 'post', 'action' => 'KCClinicController@clinicSessionDelete' ],

			// Clinic schedule module routes start here...
			'clinic_schedule_list'       => [ 'method' => 'post', 'action' => 'KCClinicScheduleController@index' ],
			'clinic_schedule_save'       => [ 'method' => 'post', 'action' => 'KCClinicScheduleController@save' ],
			'clinic_schedule_edit'       => [ 'method' => 'post', 'action' => 'KCClinicScheduleController@edit' ],
			'clinic_schedule_delete'     => [ 'method' => 'post', 'action' => 'KCClinicScheduleController@delete' ],


			// Appointment module routes starts here...
			'appointment_list'           => [ 'method' => 'get', 'action' => 'KCAppointmentController@index' ],
			'get_appointment_queue'      => [ 'method' => 'post','action' =>  'KCAppointmentController@getAppointmentQueue'],
			'appointment_save'           => [ 'method' => 'post', 'action' => 'KCAppointmentController@save' ],
			'appointment_delete'         => [ 'method' => 'post', 'action' => 'KCAppointmentController@delete' ],
			'appointment_update_status'  => [ 'method' => 'post', 'action' => 'KCAppointmentController@updateStatus' ],
			'get_appointment_slots'      => [ 'method' => 'post', 'action' => 'KCAppointmentController@getAppointmentSlots'],

			// Appointment module routes starts here...
			'patient_encounter_list'     => [ 'method' => 'post', 'action' => 'KCPatientEncounterController@index' ],
			'patient_encounter_save'     => [ 'method' => 'post', 'action' => 'KCPatientEncounterController@save' ],
			'patient_encounter_edit'     => [ 'method' => 'post', 'action' => 'KCPatientEncounterController@edit' ],
			'patient_encounter_delete'   => [ 'method' => 'post', 'action' => 'KCPatientEncounterController@delete' ],
			'patient_encounter_details'  => [ 'method' => 'post', 'action' => 'KCPatientEncounterController@details' ],
			'save_custom_patient_encounter_field'  => [ 'method' => 'post', 'action' => 'KCPatientEncounterController@saveCustomField' ],
			'patient_encounter_update_status'  => [ 'method' => 'post', 'action' => 'KCPatientEncounterController@updateStatus' ],

			// Medical records routes starts here...
			'prescription_list'          => [ 'method' => 'post', 'action' => 'KCPatientPrescriptionController@index' ],
			'prescription_save'          => [ 'method' => 'post', 'action' => 'KCPatientPrescriptionController@save' ],
			'prescription_edit'          => [ 'method' => 'post', 'action' => 'KCPatientPrescriptionController@edit' ],
			'prescription_delete'        => [
				'method' => 'post',
				'action' => 'KCPatientPrescriptionController@delete'
			],

			// Custom field routes starts here...
			'custom_field_list'          => [ 'method' => 'post', 'action' => 'KCCustomFieldController@index' ],
			'custom_field_save'          => [ 'method' => 'post', 'action' => 'KCCustomFieldController@save' ],
			'custom_field_edit'          => [ 'method' => 'post', 'action' => 'KCCustomFieldController@edit' ],
			'custom_field_delete'        => [
				'method' => 'post',
				'action' => 'KCCustomFieldController@delete'
			],

			// Medical records routes starts here...
			'medical_records_list'       => [ 'method' => 'post', 'action' => 'KCPatientMedicalRecordsController@index' ],
			'medical_records_save'       => [ 'method' => 'post', 'action' => 'KCPatientMedicalRecordsController@save' ],
			'medical_records_edit'       => [ 'method' => 'post', 'action' => 'KCPatientMedicalRecordsController@edit' ],
			'medical_records_delete'     => [
				'method' => 'post',
				'action' => 'KCPatientMedicalRecordsController@delete'
			],

			// Medical history routes starts here...
            'medical_history_list'       => [ 'method' => 'post', 'action' => 'KCPatientMedicalHistoryController@index' ],
            'medical_history_save'       => [ 'method' => 'post', 'action' => 'KCPatientMedicalHistoryController@save' ],
            'medical_history_delete'     => [
                'method' => 'post',
                'action' => 'KCPatientMedicalHistoryController@delete'
            ],

			// Services module routes starts here...
			'service_list'               => [ 'method' => 'post', 'action' => 'KCServiceController@index' ],
			'service_save'               => [ 'method' => 'post', 'action' => 'KCServiceController@save' ],
			'service_edit'               => [ 'method' => 'post', 'action' => 'KCServiceController@edit' ],
			'service_delete'             => [ 'method' => 'post', 'action' => 'KCServiceController@delete' ],
			'get_clinic_service'         => [ 'method' => 'post', 'action' => 'KCServiceController@clinicService' ],

			// Patient bill module routes starts here...
			'patient_bill_list'          => [ 'method' => 'post', 'action' => 'KCPatientBillController@index' ],
			'patient_bill_save'          => [ 'method' => 'post', 'action' => 'KCPatientBillController@save' ],
			'patient_bill_edit'          => [ 'method' => 'post', 'action' => 'KCPatientBillController@edit' ],
			'patient_bill_detail'        => [ 'method' => 'post', 'action' => 'KCPatientBillController@details' ],
			'patient_bill_update_status' => [ 'method' => 'post', 'action' => 'KCPatientBillController@updateStatus' ],
			'patient_bill_delete'        => [ 'method' => 'post', 'action' => 'KCPatientBillController@delete' ],
			'patient_bill_item_delete'   => [ 'method' => 'post', 'action' => 'KCPatientBillController@deleteBillItem' ],

			'save_doctor_zoom_configuration'   => [ 'method' => 'post', 'action' => 'KCHomeController@saveZoomConfiguration' ],
			'get_doctor_zoom_configuration'   => [ 'method' => 'post', 'action' => 'KCHomeController@getZoomConfiguration' ],
			'send_test_email' =>    [ 'method' => 'post', 'action' => 'KCHomeController@sendTestEmail' ],

			///////////////////// Front-end Routes starts here /////////////////////
			'get_doctor_details'   => [ 'method' => 'post', 'action' => 'KCBookAppointmentWidgetController@getDoctors' , 'nonce' => 0 ],
			'get_clinic_detail'   => [ 'method' => 'get', 'action' => 'KCBookAppointmentWidgetController@getClinic' , 'nonce' => 0 ],
			'get_time_slots'   => [ 'method' => 'post', 'action' => 'KCBookAppointmentWidgetController@getTimeSlots' , 'nonce' => 0 ],
			'save_appointment'   => [ 'method' => 'post', 'action' => 'KCBookAppointmentWidgetController@saveAppointment', 'nonce' => 0 ],
			'login'   => [ 'method' => 'post', 'action' => 'KCAuthController@patientLogin' , 'nonce' => 0],
			'register'   => [ 'method' => 'post', 'action' => 'KCAuthController@patientRegister' , 'nonce' => 0],
            'login_user_detail'  =>  [ 'method' => 'get', 'action' => 'KCPatientDashboardWidget@getPatientDetail' , 'nonce' => 0],

			// All Appointment
            'all-appointment'            => [ 'method' => 'post', 'action' => 'KCAppointmentController@allAppointment', 'nonce' => 0],

            // Patient-Dashboard-widget
            'get_logged_in_user'         => [ 'method' => 'get', 'action' => 'KCAuthController@loginPatientDetail' , 'nonce' => 0],
            'patient_logout'             => [ 'method' => 'post', 'action' => 'KCAuthController@logout' , 'nonce' => 0],
            'patient_change_password'    => [ 'method' => 'post', 'action' => 'KCAuthController@changePassword' , 'nonce' => 0],
            'get_patient_appointments'   => [ 'method' => 'post', 'action' => 'KCPatientDashboardWidget@appointmentList', 'nonce' => 0],
			'get_patient_encounters'     => [ 'method' => 'post', 'action' => 'KCPatientDashboardWidget@encounterList', 'nonce' => 0],
			'get_clinic_doctors'         => [ 'method' => 'post', 'action' => 'KCPatientDashboardWidget@getClinicDoctors', 'nonce' => 0],
            'get_doctors_time_slots'     => [ 'method' => 'post', 'action' => 'KCPatientDashboardWidget@getTimeSlots', 'nonce' => 0],
            'patient_book_appointment'   => [ 'method' => 'post', 'action' => 'KCPatientDashboardWidget@bookAppointment', 'nonce' => 0],
            'patient_cancel_appointment' => [ 'method' => 'post', 'action' => 'KCPatientDashboardWidget@cancelAppointment', 'nonce' => 0],
            'patient_save_profile'       => [ 'method' => 'post', 'action' => 'KCPatientDashboardWidget@saveProfile', 'nonce' => 0],
			'patient_dashboard_counts'   => [ 'method' => 'get', 'action' => 'KCPatientDashboardWidget@getDashboardData', 'nonce' => 0],

            //Language Translation
			'update_language'   => [ 'method' => 'post', 'action' => 'KCLanguageController@updateLang', 'nonce' => 0],
			'upload_logo'   => [ 'method' => 'post', 'action' => 'KCLanguageController@uploadLogo', 'nonce' => 0],

			'update_theme_color'   => [ 'method' => 'post', 'action' => 'KCLanguageController@updateThemeColor', 'nonce' => 0],
			'update_theme_rtl'   => [ 'method' => 'post', 'action' => 'KCLanguageController@updateRTLMode', 'nonce' => 0],
			'sms_config_save'   => [ 'method' => 'post', 'action' => 'KCLanguageController@saveSmsConfig', 'nonce' => 0],
			'get_sms_config'   => [ 'method' => 'post', 'action' => 'KCLanguageController@editConfig', 'nonce' => 0],
			'upload_patient_report'   => [ 'method' => 'post', 'action' => 'KCLanguageController@uploadPatientReport', 'nonce' => 0],
			'get_patient_report'   => [ 'method' => 'post', 'action' => 'KCLanguageController@getPatientReport', 'nonce' => 0],
			'view_patient_report'   => [ 'method' => 'post', 'action' => 'KCLanguageController@viewPatientReport', 'nonce' => 0],
			'delete_patient_report'   => [ 'method' => 'post', 'action' => 'KCLanguageController@deletePatientReport', 'nonce' => 0],
			'patient_dashboard_counts'   => [ 'method' => 'get', 'action' => 'KCPatientDashboardWidget@getDashboardData', 'nonce' => 0],
			// woocommerce payment
			'woocommerce_payment'   => [ 'method' => 'post', 'action' => 'KCWoocommerceController@bookAppointment', 'nonce' => 0],

			// prescription print
			'get_prescription_print'           => [ 'method' => 'post', 'action' => 'KCLanguageController@getPrescriptionPrint' ],
			'get_all_report_type'           => [ 'method' => 'post', 'action' => 'KCHomeController@getAllReportType' ],
			'get_user_wise_clinic'           => [ 'method' => 'post', 'action' => 'KCLanguageController@getUserClinic' ],
			'get_json_file' 		=>               [ 'method' => 'post', 'action' => 'KCLanguageController@getJosnFile' ],
			'save_json_data' 		=>               [ 'method' => 'post', 'action' => 'KCLanguageController@saveJsonData' ],
            'enable_sms_config' 		=>               [ 'method' => 'post', 'action' => 'KCLanguageController@unableSMSConfig' ],
            'get_all_lang_option'         =>               [ 'method' => 'post', 'action' => 'KCLanguageController@getAllLang' ],

            // GOOGLE CALENDER ROUTES

            'google_calender_config' => [ 'method' => 'post', 'action' => 'KCGoogleCalenderController@saveConfig'],
            'edit_gcal_data' => [ 'method' => 'post', 'action' => 'KCGoogleCalenderController@editConfigData' ],

            'connect_doctor' => [ 'method' => 'post', 'action' => 'KCGoogleCalenderController@connectDoctor' ],
            'diconnect_doctor' => [ 'method' => 'post', 'action' => 'KCGoogleCalenderController@disconnectDoctor' ],

            'get_google_event_template'        => [ 'method' => 'post', 'action' => 'KCGoogleCalenderController@getGoogleEventTemplate' ],
            'save_google_event_template'        => [ 'method' => 'post', 'action' => 'KCGoogleCalenderController@saveGoogleEventTemplate' ],


            //patient unique id setting
            'patient_id_config' => [ 'method' => 'post', 'action' => 'KCPatientController@savePatientSetting'],
            'edit_patient_id_config' => [ 'method' => 'post', 'action' => 'KCPatientController@editPatientSetting' ],
			'get_unique_id'=>[ 'method' => 'post', 'action' => 'KCPatientController@getPatientUid' ],
			'save_patient_google_cal'=>[ 'method' => 'post', 'action' => 'KCPatientController@googleCalPatient' ],
			'edit_patient_google_cal'=>[ 'method' => 'post', 'action' => 'KCPatientController@googleEditPatient' ],
        );
	}
}


