<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\models\KCClinic;
use App\models\KCClinicSession;
use App\models\KCDoctorClinicMapping;
use App\models\KCReceptionistClinicMapping;
use App\baseClasses\KCRequest;
use Exception;
use WP_User;

class KCClinicController extends KCBase {

	public $db;

	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

	}

	public function index() {

		if ( ! kcCheckPermission( 'clinic_list' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     =>  esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
		$condition = '' ;

		if ($request_data['searchKey'] && $request_data['searchValue']) {
			$condition = ' where '.$request_data['searchKey']. ' LIKE  "%'.$request_data['searchValue'].'%" ' ;
		}

		$clinics_query = 'select * from '.$this->db->prefix  . 'kc_' .'clinics '.$condition ;

		$clinics = $this->db->get_results($clinics_query);

		if (!count($clinics)) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No clinics found', 'kc-lang'),
				'data' => []
			]);
			wp_die();
		}

		echo json_encode( [
			'status'  => true,
			'message' => esc_html__('Doctors list', 'kc-lang'),
			'data' => $clinics
		]);
	}

	public function save() {
        $is_permission = false ;
        $active_domain =$this->getAllActivePlugin();

        if (kcCheckPermission( 'clinic_add' )  || kcCheckPermission( 'doctor_session_add' ) ) {
            $is_permission = true ;
        }


        if($active_domain === $this->kiviCareProOnName()){

            $requestData = $this->request->getInputs();
            $requestData['specialties'] = json_decode(stripslashes($requestData['specialties']));

            // check image  type is binary or not
            if(!is_string($requestData['clinic_profile']) && !is_int($requestData['clinic_profile']) && $requestData['clinic_profile'] !== false) {
                if($requestData['clinic_profile'] !== '' && isset($requestData['clinic_profile']) && $requestData['clinic_profile'] !== null ){
                    $requestData['clinic_profile'] = media_handle_upload('clinic_profile', 0);
                }
            }

            // check image  type is binary or not
            if(!is_string($requestData['profile_image']) && !is_int($requestData['profile_image']) && $requestData['profile_image'] !== false) {
                if($requestData['profile_image'] !== '' && isset($requestData['profile_image']) && $requestData['profile_image'] !== null){
                    $requestData['profile_image'] = media_handle_upload('profile_image', 0);
                }
            }

            // print_r($requestData);
            // die;

            $clinicData = array(
                'name'=>$requestData['name'],
                'email'=>$requestData['email'],
                'specialties'=> json_encode($requestData['specialties']),
                'status'=>$requestData['status'],
                'profile_image'=>$requestData['clinic_profile'],
                'telephone_no'=>$requestData['telephone_no'],
                'address'=>$requestData['address'],
                'city'=>$requestData['city'],
                'country'=>$requestData['country'],
                'postal_code'=>$requestData['postal_code'],
            );

            $currency = [
                'currency_prefix' =>$requestData['currency_prefix'],
                'currency_postfix' =>$requestData['currency_postfix'],
                'decimal_point' =>$requestData['decimal_point'],
            ];

            $clinicData['extra'] = json_encode($currency);
            $clinicAdminData = array(
                'first_name'=>$requestData['first_name'],
                'last_name'=>$requestData['last_name'],
                'user_email'=>$requestData['user_email'],
                'mobile_number'=>$requestData['mobile_number'],
                'gender'=>$requestData['gender'],
                'dob'=>$requestData['dob'],
                'profile_image'=>$requestData['profile_image'],
            );
            $response = apply_filters('kcpro_save_clinic', [
                'clinicData' =>  $clinicData,
                'clinicAdminData'=>$clinicAdminData,
                'id'=>$requestData['id']
            ]);
            echo json_encode($response);
        }else{
            try {
                if (!$is_permission) {
                    echo json_encode( [
                        'status'      => false,
                        'status_code' => 403,
                        'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
                        'data'        => []
                    ] );
                    wp_die();
                }

                $request_data = $this->request->getInputs();

                $request_data['specialties'] = json_decode(stripslashes($request_data['specialties']));

                $rules = [
                    'name' => 'required',
                    'email' => 'required|email',
                    'telephone_no' => 'required',
                ];

                $errors = kcValidateRequest($rules, $request_data);

                if (count($errors)) {

                    echo json_encode([
                        'status' => false,
                        'message' => esc_html__($errors[0], 'kc-lang')
                    ]);
                    die;

                }

                $temp = [
                    'name' => $request_data['name'],
                    'email' => $request_data['email'],
                    'telephone_no' => $request_data['telephone_no'],
                    'address' => $request_data['address'],
                    'city' => $request_data['city'],
                    'state' => $request_data['state'],
                    'country' => $request_data['country'],
                    'postal_code' => $request_data['postal_code'],
                    'specialties' => json_encode($request_data['specialties']),
                    'status' => $request_data['status'],
                ];

                $clinic = new KCClinic;

                $currency = [
                    'currency_prefix' => $request_data['currency_prefix'],
                    'currency_postfix' => $request_data['currency_postfix'],
                    'decimal_point' => $request_data['decimal_point'],
                ];

                $temp['extra'] = json_encode($currency);

                if (!isset($request_data['id'])) {
                    $temp['created_at'] = current_time('Y-m-d H:i:s');
                    $status = $clinic->insert($temp);
                } else {
                    if($request_data['profile_image'] != '' && isset($request_data['profile_image']) && $request_data['profile_image'] != null ){
                        $request_data['profile_image'] = media_handle_upload('profile_image', 0);
                        $temp['profile_image']= $request_data['profile_image'];
                    }
                    $status = $clinic->update($temp, array( 'id' => $request_data['id'] ));
                }

                if (isset($request_data['id'])) {
                    $clinic_id = $request_data['id'];
                } else {
                    $clinic_id = $status;
                }

                // Insert clinic session...
                $clinic_session = new KCClinicSession();
                $clinic_session->delete(['clinic_id' => $clinic_id]);

                if (count($request_data['clinic_sessions'])) {
                    foreach ($request_data['clinic_sessions'] as $key => $session) {
                        $parent_id = 0;
                        foreach ($session['days'] as $day) {

                            $start_time = date('H:i:s', strtotime($session['s_one_start_time']['HH'] . ':' . $session['s_one_start_time']['mm']));
                            $end_time = date('H:i:s', strtotime($session['s_one_end_time']['HH'] . ':' . $session['s_one_end_time']['mm']));

                            $session_temp = [
                                'clinic_id' => $clinic_id,
                                'doctor_id' => $session['doctors']['id'],
                                'day' => substr($day, 0, 3),
                                'start_time' => $start_time,
                                'end_time' => $end_time,
                                'time_slot' => $session['time_slot'],
                                'created_at' => current_time('Y-m-d H:i:s'),
                                'parent_id' => (int) $parent_id === 0 ? null : (int) $parent_id
                            ];

                            if ($parent_id === 0) {
                                $parent_id = $clinic_session->insert($session_temp);
                            } else {
                                $clinic_session->insert($session_temp);
                            }

                            if ($session['s_two_start_time']['HH'] !== null && $session['s_two_end_time']['HH'] !== null) {

                                $session_temp['start_time'] = date('H:i:s', strtotime($session['s_two_start_time']['HH'] . ':' . $session['s_two_start_time']['mm']));
                                $session_temp['end_time'] = date('H:i:s', strtotime($session['s_two_end_time']['HH'] . ':' . $session['s_two_end_time']['mm']));
                                $session_temp['parent_id'] = $parent_id;

                                $clinic_session->insert($session_temp);
                            }

                        }
                    }
                }


                if ($status) {
                    echo json_encode([
                        'status' => true,
                        'message' => esc_html__('Clinic has been saved successfully', 'kc-lang')
                    ]);
                    wp_die();
                } else {
                    echo json_encode([
                        'status' => false,
                        'message' => esc_html__('Clinic has not been saved successfully', 'kc-lang')
                    ]);
                    wp_die();
                }
            } catch (Exception $e) {

                $code =  esc_html__($e->getCode(), 'kc-lang');
                $message =  esc_html__($e->getMessage(), 'kc-lang');

                header("Status: $code $message");

                echo json_encode([
                    'status' => false,
                    'message' => $message
                ]);
                wp_die();
            }
        }
	}

	public function edit() {

		$is_permission = false ;
        $active_domain =$this->getAllActivePlugin();
        
		if (kcCheckPermission( 'clinic_edit' ) || kcCheckPermission( 'clinic_view' ) || kcCheckPermission( 'clinic_profile' ) || kcCheckPermission( 'settings' )) {
			$is_permission = true ;
		}

		if (!$is_permission) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
        if($active_domain === $this->kiviCareProOnName()) {

            $response = apply_filters('kcpro_edit_clinic', [
                'clinic_id' =>  $request_data['id'],
            ]);
            echo json_encode($response);

        }else{
            try {

                if (!isset($request_data['id'])) {
                    throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
                }

                $id = $request_data['id'];

                $clinic = new KCClinic;

                $results = $clinic->get_by(['id' => $id], '=',true);

                if ($results) {

                    $results->specialties = json_decode($results->specialties);
                    $extra = json_decode($results->extra);
                    $results->currency_prefix = $extra->currency_prefix;
                    $results->currency_postfix = $extra->currency_postfix;
                    $results->decimal_point = $extra->decimal_point;
                    $results->clinic_profile = wp_get_attachment_url($results->profile_image);
                    $results->profile_image = wp_get_attachment_url(get_user_meta($results->clinic_admin_id, 'clinic_admin_profile_image'));
                    $doctor_mapping = (new KCDoctorClinicMapping())->get_by([ 'clinic_id' => $results->id]);

                    $doctors = [];

                    if (count($doctor_mapping)) {
                        $inactive_doctors = [] ;
                        foreach ($doctor_mapping as $key => $doctor) {
                            if((int) $doctor->doctor_id !== 0 ) {

                                $doctor_ids[$key] = $doctor->doctor_id;

                                $doctor = WP_User::get_data_by('ID', $doctor->doctor_id);

                                if((int)$doctor->user_status === 0) {

                                    $user_data  = get_user_meta( $doctor->ID, 'basic_data', true );
                                    $user_data = json_decode($user_data);

                                    $specialties = collect($user_data->specialties)->pluck('label')->toArray();

                                    $temp = [
                                        'id' => $doctor->ID,
                                        'label' => $doctor->display_name ."(". implode( ',',$specialties).")"
                                    ];

                                    $temp['timeSlot'] = isset($user_data->time_slot) ? $user_data->time_slot : "";

                                    array_push($doctors, $temp);
                                } else {
                                    array_push($inactive_doctors, $doctor->ID);
                                }
                            }
                        }
                    }

                    if(count($doctors) > 0) {

                        $results->doctors = $doctors;

                    } else {

                        $results->clinic_sessions = [];
                        $results->profile_image = wp_get_attachment_url(332);
                        $results->profile_card_image = wp_get_attachment_url(359);
                        echo json_encode([
                            'status' => true,
                            'message' => esc_html__('Clinic data', 'kc-lang'),
                            'data' => $results
                        ]);
                        wp_die();

                    }

                    $user = new WP_User( get_current_user_id() );
                    if ($user->roles[0] === 'administrator' ) {
                        $clinic_sessions = (new KCClinicSession())->get_all();
                    } elseif($user->roles[0] === 'kiviCare_doctor'){
                        $clinic_sessions = (new KCClinicSession())->get_by(['doctor_id' => $user->ID]);
                    }else {
                        $clinic_sessions = (new KCClinicSession())->get_by([ 'clinic_id' => $results->id]);
                    }
                    $clinic_sessions = collect($clinic_sessions);

                    $clinic_sessions = $clinic_sessions->map(function ($session) {
                        $session->day = substr($session->day, 0, 3);
                        return  $session ;
                    });

                    $sessions = [];

                    if (count($clinic_sessions)) {
                        foreach ($clinic_sessions as $session) {
                            $table_clinic = $this->db->prefix . 'kc_' . 'clinics';
                            $clinic = "SELECT name FROM {$table_clinic} WHERE id =". $session->clinic_id;
                            $clinics = $this->db->get_results( $clinic);
                            if(!in_array($session->doctor_id, $inactive_doctors)) {
                                if ($session->parent_id === null || $session->parent_id === "" ) {
                                $days = [];
                                $session_doctors = [];
                                $sec_start_time = "";
                                $sec_end_time = "";

                                array_push($days, substr($session->day, 0, 3));

                                $all_clinic_sessions  = collect($clinic_sessions);

                                $child_session = $all_clinic_sessions->where('parent_id', $session->id);

                                $child_session->all();

                                if(count($child_session) > 0) {

                                    foreach ($clinic_sessions as $child_session) {

                                        if ($child_session->parent_id !== null && (int) $session->id === (int) $child_session->parent_id) {

                                            array_push($session_doctors, $child_session->doctor_id);
                                            array_push($days, substr($child_session->day, 0, 3));

                                            if ($session->start_time !== $child_session->start_time) {
                                                $sec_start_time = $child_session->start_time;
                                                $sec_end_time = $child_session->end_time;
                                            }

                                        }

                                    }

                                } else {

                                    array_push($session_doctors, $session->doctor_id);
                                    array_push($days, substr($session->day, 0, 3));

                                }

                                $start_time = explode(":",date('H:i',strtotime($session->start_time)));

                                $end_time = explode(":",date('H:i',strtotime($session->end_time)));

                                $session_doctors = array_unique($session_doctors);

                                if (count($session_doctors) === 0 && count($days) === 0) {
                                    $session_doctors[] = $session->doctor_id;
                                    $days[] = substr($session->day, 0, 3);
                                } else {
                                    $sec_start_time = $sec_start_time !== "" ? explode(":",date('H:i',strtotime($sec_start_time))) : "";
                                    $sec_end_time = $sec_end_time !== "" ?explode(":",date('H:i',strtotime($sec_end_time))) : "";
                                }

                                $new_doctors = [];

                                foreach ($session_doctors as $doctor_id) {
                                    foreach ($doctors as $doctor) {
                                        if ((int) $doctor['id'] === (int) $doctor_id) {
                                            $new_doctors = $doctor;
                                        }
                                    }
                                }

                                $new_session = [
                                    'id' => $session->id,
                                    'clinic_id' => $session->clinic_id,
                                    'clinic_name'=>$clinics[0]->name,
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

                                array_push($sessions, $new_session);

                            }
                            }
                        }
                    }

                    $results->clinic_sessions = $sessions;
                   
                    echo json_encode([
                        'status' => true,
                        'message' => esc_html__('Clinic data', 'kc-lang'),
                        'data' => $results
                    ]);
                    wp_die();

                } else {
                    throw new Exception( esc_html__('Data not found', 'kc-lang'), 400);
                }


            } catch (Exception $e) {

                $code =  esc_html__($e->getCode(), 'kc-lang');
                $message =  esc_html__($e->getMessage(), 'kc-lang');

                header("Status: $code $message");

                echo json_encode([
                    'status' => false,
                    'message' => $message
                ]);
                wp_die();
            }
        }
	}

	public function delete() {

		if ( ! kcCheckPermission( 'clinic_delete' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
        $id = $request_data['id'];
		try {

			if ( ! isset( $request_data['id'] ) ) {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}
            
            if (kcGetDefaultClinicId() == $id) {
                echo json_encode( [
					'status'      => true,
					'message'     => esc_html__('You can not delete the default clinic.', 'kc-lang' ),
				] );
                die;
            }else{
                (new KCDoctorClinicMapping())->delete([ 'clinic_id' => $id]);

                $results = (new KCClinic())->delete([ 'id' => $id]);
    
                if ( $results ) {
                    echo json_encode( [
                        'status'      => true,
                        'message'     => esc_html__('Clinic has been deleted successfully', 'kc-lang' ),
                    ] );
                } else {
                    throw new Exception( esc_html__('Data not found', 'kc-lang') , 400 );
                }
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

	public function clinicSessionSave () {

        $request_data = $this->request->getInputs();
        $user_id = get_current_user_id();

        // Insert clinic session...
        switch ($this->getLoginUserRole()) {
            
            case 'kiviCare_receptionist':
                $clinic_id =  (new KCReceptionistClinicMapping())->get_by([ 'receptionist_id' => $user_id]);
                $clinic_id = $clinic_id[0]->clinic_id;
                break;
            case 'kiviCare_clinic_admin':
                $clinic_id =  (new KCClinic())->get_by([ 'clinic_admin_id' => $user_id]);
                $clinic_id = $clinic_id[0]->id;
                break;
            default:
                $clinic_id =  isset($request_data['clinic_id']['clinic_id']) ? $request_data['clinic_id']['clinic_id'] :kcGetDefaultClinicId();
                break;
        }
       
        $clinic_session = new KCClinicSession();

        if (isset($request_data['id']) && $request_data['id'] !== '' ) {

            // delete parent session
            $clinic_session->delete(['id' => $request_data['id']]);

            // delete child session
            $clinic_session->delete(['parent_id' => $request_data['id']]);

        }

        $session = $request_data ;
        $parent_id = 0;

        foreach ($session['days'] as $day) {

            $result = true ;

            $start_time = date('H:i:s', strtotime($session['s_one_start_time']['HH'] . ':' . $session['s_one_start_time']['mm']));
            $end_time = date('H:i:s', strtotime($session['s_one_end_time']['HH'] . ':' . $session['s_one_end_time']['mm']));
            $session_temp = [
                'clinic_id' => $clinic_id,
                'doctor_id' => $session['doctors']['id'],
                'day' =>  substr($day, 0, 3),
                'start_time' => $start_time,
                'end_time' => $end_time,
                'time_slot' => $session['time_slot'],
                'created_at' => current_time('Y-m-d H:i:s'),
                'parent_id' => (int) $parent_id === 0 ? null : (int) $parent_id
            ];

            if ($parent_id === 0) {
                $parent_id = $clinic_session->insert($session_temp);
            } else {
                $result =  $clinic_session->insert($session_temp);
            }

            if ($session['s_two_start_time']['HH'] !== null && $session['s_two_end_time']['HH'] !== null) {

                $session_temp['start_time'] = date('H:i:s', strtotime($session['s_two_start_time']['HH'] . ':' . $session['s_two_start_time']['mm']));
                $session_temp['end_time'] = date('H:i:s', strtotime($session['s_two_end_time']['HH'] . ':' . $session['s_two_end_time']['mm']));
                $session_temp['parent_id'] = $parent_id;
                $result =  $clinic_session->insert($session_temp);

            }

            if(!$result) {
                echo json_encode([
                    'status' => false,
                    'message' => esc_html__('Clinic session has not been saved successfully', 'kc-lang')
                ]);
                wp_die();
            }

        }

        echo json_encode([
            'status' => true,
            'message' => esc_html__('Clinic session has been saved successfully', 'kc-lang')
        ]);

        wp_die();
    }

    public function clinicSessionDelete () {

        $request_data = $this->request->getInputs();

        $clinic_session = new KCClinicSession();

        if (isset($request_data['session_id']) && $request_data['session_id'] !== '' ) {

            // delete parent session
            $clinic_session->delete(['id' => $request_data['session_id']]);

            // delete child session
            $clinic_session->delete(['parent_id' => $request_data['session_id']]);

            echo json_encode([
                'status' => true,
                'message' => esc_html__('Clinic session has been deleted successfully', 'kc-lang')
            ]);

            wp_die();

        }
    }

}
