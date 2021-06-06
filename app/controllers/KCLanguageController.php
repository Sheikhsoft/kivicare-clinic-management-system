<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use WP_User;

class KCLanguageController extends KCBase
{
    /**
     * @var KCRequest
     */
    private $request;

    public function __construct()
    {
        $this->request = new KCRequest();
    }
    
    public function updateLang(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_update_language', [
			'user_id' => $request_data['user_id'],
			'lang' => $request_data['lang'],
        ]);
        echo json_encode($response);
    }
    public function getPrescriptionPrint(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_get_prescription_print', [
			'encounter_id' => $request_data['id'],
        ]);
        echo json_encode($response);
	}
    public function updateThemeColor(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_change_themecolor', [
			'color' => $request_data['color'],
        ]);
        echo json_encode($response);
    }
    public function updateRTLMode(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_change_mode', [
			'mode' => $request_data['rtl'],
        ]);
        echo json_encode($response);
    }
    public function uploadLogo(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_upload_logo', [
			'site_logo' => $request_data['site_logo'],
        ]);
        echo json_encode($response);
    }
    public function saveSmsConfig(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_save_sms_config', [
            'current_user' => get_current_user_id(),
			'config_data' => $request_data,
        ]);
        echo json_encode($response);
    }
    public function editConfig(){
        $response = apply_filters('kcpro_edit_sms_config', [
            'current_user' => get_current_user_id(),
        ]);
        echo json_encode($response);
    }
    public function uploadPatientReport(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_upload_patient_report', [
            'upload_data' => $request_data 
        ]);
        echo json_encode($response);

    }
    public function getPatientReport(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_get_patient_report', [
            'pid'=>$request_data['patinet']
        ]);
        echo json_encode($response);
    }
    public function viewPatientReport(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_view_patient_report', [
            'pid'=>$request_data['patient_id'],
            'docid'=>$request_data['doc_id']
        ]);
        echo json_encode($response);
    }
    public function deletePatientReport(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_delete_patient_report', [
            'report_id'=>$request_data['id'],
        ]);
        echo json_encode($response);
    }
    public function getUserClinic(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_get_user_clinic', [
            'requestData'=>$request_data
        ]);
        echo json_encode($response);
    }
    public function getJosnFile(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_get_json_file_data', [
            'fileUrl'=> $request_data['filePath'],
            'currentFile'=> $request_data['current']
        ]);
        echo json_encode($response);
    }
    public function saveJsonData(){
        $request_data = $this->request->getInputs();
        if(count($request_data['data']) == 0) {
            $upload_dir = wp_upload_dir();
            $dir_name = KIVI_CARE_PREFIX.'lang';
            $user_dirname = $upload_dir['basedir'] . '/' . $dir_name;
            $current_file = $user_dirname.'/temp.json';
            $request_data['data'] = json_decode(file_get_contents($current_file), TRUE);
        }
        $response = apply_filters('kcpro_save_json_file_data', [
            'jsonData'=> $request_data['data'],
            'filename'=>$request_data['file_name'],
            'langName'=>$request_data['langTitle']
        ]);
        echo json_encode($response);
    }
    public function unableSMSConfig(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_unable_sms_config', [
            'current_user' => get_current_user_id(),
            'status' =>$request_data['state']
        ]);
        echo json_encode($response);
    }
    public function getAllLang(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_get_all_lang', []);
        echo json_encode($response);
    }
}
