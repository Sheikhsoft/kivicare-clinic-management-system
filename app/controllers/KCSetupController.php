<?php

namespace App\Controllers;

use App\baseClasses\KCActivate;
use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCClinic;
use App\models\KCClinicSession;
use App\models\KCDoctorClinicMapping;
use App\models\KCReceptionistClinicMapping;
use WP_User;

class KCSetupController extends KCBase {

	public $db;

	private $request ;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();
	}

    public function clinic() {
		$request_data = $this->request->getInputs();
		$request_data['specialties'] = json_decode(stripslashes($request_data['specialties']));
		if($request_data['profile_image'] != '' && isset($request_data['profile_image']) && $request_data['profile_image'] != null ){
			$request_data['profile_image'] = media_handle_upload('profile_image', 0);
        }
        $temp1 = [
            'name' => $request_data['name'],
            'email' => $request_data['email'],
            'telephone_no' => $request_data['telephone_no'],
            'address' => $request_data['address'],
            'city' => $request_data['city'],
            'state' => $request_data['state'],
            'country' => $request_data['country'],
            'postal_code' => $request_data['postal_code'],
            'specialties' => json_encode($request_data['specialties']),
            'status' => 1,
			'profile_image'=> $request_data['profile_image']
		];

		$clinic = new KCClinic;

        $currency = [
            'currency_prefix' => $request_data['currency_prefix'],
            'currency_postfix' => $request_data['currency_postfix']
        ];

        $temp1['extra'] = json_encode($currency);

        if ( !isset( $request_data['id']) || $request_data['id'] === null || $request_data['id'] === '') {
            $temp1['created_at'] = current_time('Y-m-d H:i:s');
            $insert_id = $clinic->insert($temp1);
            if ($insert_id) {
                $step_detail = array( 'step' => 1, 'name' => 'clinic', 'id' => [$insert_id], 'status' => true);
                $encoded_status_data = json_encode($step_detail);
                add_option('setup_step_1', $encoded_status_data);
                update_option('clinic_setup_wizard', 1);
            }

            $message =  esc_html__('Clinic has been saved successfully', 'kc-lang');

        } else {
	        $insert_id = 0 ;
            $status = $clinic->update($temp1, array( 'id' => $request_data['id'] ));
	        $message = esc_html__('Clinic has been updated successfully', 'kc-lang');
        }

        echo json_encode([
            'status' => true,
            'message' => esc_html__($message, 'kc-lang'),
	        'data' => array('insert_id' => $insert_id )
        ]);

    }
    public function clinicAdmin() {
        
        $request_data = $this->request->getInputs();
        $clinic_id = kcGetDefaultClinicId();
        if($request_data['profile_image'] != '' && isset($request_data['profile_image']) && $request_data['profile_image'] != null ){
            $request_data['profile_image'] = media_handle_upload('profile_image', 0);
        }
        $rules = [
            'first_name' => 'required',
            'last_name'   => 'required',
            'user_email' => 'required|email',
            'mobile_number' => 'required',
            'gender' => 'required',
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
            'first_name'  => $request_data['first_name'],
            'last_name'         => $request_data['last_name'],
            'user_email'            => $request_data['user_email'],
            'mobile_number'        => $request_data['mobile_number'],
            'gender'           => $request_data['gender'],
            'profile_image'=> $request_data['profile_image']
        ];
        if ( ! isset( $request_data['ID'] ) || $request_data['ID'] === null || $request_data['ID'] === '') {

            $request_data['username'] = kcGenerateUsername($request_data['first_name']) ;

            $request_data['user_pass'] = kcGenerateString(12);

            $user = wp_create_user($request_data['username'], $request_data['user_pass'], $request_data['user_email']);

            $u    = new WP_User( $user );

            $u->display_name = $request_data['first_name'] . ' ' .  $request_data['last_name'];

            wp_insert_user($u);

            $u->set_role($this->getClinicAdminRole());

            if($user) {
                $clinic_mapping = new KCClinic;

                $new_temp = [
                    'clinic_admin_id' => $user,
                    'created_at'=> current_time('Y-m-d H:i:s')
                ];

                $clinic_mapping->update($new_temp,array( 'id' => $clinic_id ));

                $user_email_param = array (
                    'username' => $request_data['username'],
                    'user_email' => $request_data['user_email'],
                    'password' => $request_data['user_pass'],
                    'email_template_type' => 'clinic_admin_registration'
                );

                kcSendEmail($user_email_param);
            }
            update_user_meta( $user, 'first_name', $request_data['first_name'] );
            update_user_meta( $user, 'last_name', $request_data['last_name'] );
            update_user_meta( $user, 'basic_data', json_encode( $temp ) );

        } else {

            wp_update_user(
                array(
                    'ID'         => $request_data['ID'],
                    'user_login' => $request_data['username'],
                    'user_email' => $request_data['user_email'],
                    'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
                )
            );

            update_user_meta( $request_data['ID'], 'basic_data', json_encode( $temp ) );

        }

        $step_detail = array( 'step' => 4, 'name' => 'clinic_admin', 'status' => true);
        $encoded_status_data = json_encode($step_detail);
        add_option('setup_step_4', $encoded_status_data);

        if ( $user->errors ) {

            echo json_encode( [
                'status'  => false,
                'message' => esc_html__($user->get_error_message() ? $user->get_error_message() : 'Clinic Admin data save operation has been failed', 'kc-lang')
            ] );

        } else {

            echo json_encode( [
                'status'  => true,
                'message' => esc_html__('Clinic Admin has been saved successfully', 'kc-lang'),
            ] );

        }

    }
    public function doctor() {

	    $request_data = $this->request->getInputs();

	    $rules = [
		    'first_name' => 'required',
		    'last_name'   => 'required',
		    'user_email' => 'required|email',
		    'mobile_number' => 'required',
		    'dob' => 'required',
		    'gender' => 'required',
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
            'mobile_number'  => $request_data['mobile_number'],
            'gender'         => $request_data['gender'],
            'dob'            => $request_data['dob'],
            'address'        => $request_data['address'],
            'city'           => $request_data['city'],
            'state'          => $request_data['state'],
            'country'        => $request_data['country'],
            'postal_code'    => $request_data['postal_code'],
            'qualifications' => $request_data['qualifications'],
            'specialties'    => $request_data['specialties'],
            'price'          => $request_data['price'],
            'price_type'     => $request_data['price_type'],
            'time_slot'      => $request_data['time_slot'],
        ];

	    if ( isset( $request_data['price_type'] ) && $request_data['price_type'] === "range" ) {
		    $temp['price'] = $request_data['minPrice'] . '-' . $request_data['maxPrice'];
	    }

	    if ( ! isset( $request_data['ID'] ) || $request_data['ID'] === null || $request_data['ID'] === '') {

		    $request_data['username'] = kcGenerateUsername($request_data['first_name']) ;

            $request_data['password'] = kcGenerateString(12);

            $user = wp_create_user($request_data['username'], $request_data['password'], $request_data['user_email']);

	        $u    = new WP_User( $user );
	        $u->display_name = $request_data['first_name'] . ' ' .  $request_data['last_name'];

	        wp_insert_user($u);

	        $u->set_role( $this->getDoctorRole() );

	         if($user) {

	             $user_email_param = array (
	                 'username' => $request_data['username'],
		             'user_email' => $request_data['user_email'],
		             'password' => $request_data['password'],
		             'email_template_type' => 'doctor_registration'
	             );

		        kcSendEmail($user_email_param);

	         }

	        update_user_meta( $user, 'basic_data', json_encode( $temp ) );

		    $doctor_ids = [];

		    foreach ( $u as $key => $val ) {
			    if ( $val->ID !== '' && $val->ID !== null ) {
				    array_push( $doctor_ids, (int) $val->ID );
			    }
		    }

		    $current_step_status = get_option( 'setup_step_2' );

		    $clinic  =  get_option('setup_step_1');

		    $clinic = json_decode($clinic);

		    // Insert Doctor Clinic mapping...
		    $doctor_mapping = new KCDoctorClinicMapping;

		    $new_temp = [
			    'doctor_id' => $u->ID,
			    'clinic_id' => $clinic->id[0],
			    'created_at'=> current_time('Y-m-d H:i:s')
		    ];

		    $doctor_mapping->insert($new_temp);

		    if ( $current_step_status ) {

			    $step_detail = json_decode( $current_step_status );

			    delete_option( 'setup_step_2' );

			    array_push( $step_detail->id, $doctor_ids[0]);

		    } else {

			    $step_detail = array( 'step' => 2, 'name' => 'doctor', 'id' => $doctor_ids, 'status' => true );

		    }

		    $encoded_status_data = json_encode($step_detail);

		    add_option('setup_step_2', $encoded_status_data);

		    $message = 'Doctor has been saved successfully' ;

	    } else {

		    wp_update_user(
			    array(
				    'ID'         => $request_data['ID'],
				    'user_login' => $request_data['username'],
				    'user_email' => $request_data['user_email'],
				    'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
			    )
		    );

		    $user_meta_data  = get_user_meta($request_data['ID'], 'basic_data', true);
		    $basic_data = json_decode($user_meta_data);

		    if (isset($basic_data->time_slot)) {
		    	if($basic_data->time_slot !== $request_data['time_slot']) {
				    $this->resetDoctorSession($request_data['ID']);
			    }
		    }

		    update_user_meta($request_data['ID'], 'basic_data', json_encode( $temp ) ) ;

		    $message = esc_html__('Doctor has been updated successfully', 'kc-lang');

	    }

        if ( $user->errors ) {

            echo json_encode( [
                'status'  => false,
                'message' => esc_html__($user->get_error_message() ? $user->get_error_message() : 'Doctor data save operation has been failed', 'kc-lang')
            ] );

        } else {

	        echo json_encode( [
		        'status'  => true,
		        'message' => esc_html__($message, 'kc-lang'),
	        ] );

        }

    }

    public function clinicSession() {
		$clinic  =  get_option('setup_step_1');

		$clinic = json_decode($clinic);

	    $session_parent_ids = [] ;

	    $request_data = $this->request->getInputs();

	    $clinic_session = new KCClinicSession();

        $clinic_id = $clinic->id[0];
	    $clinic_session->delete(['clinic_id' => $clinic->id[0] ]);

        if (count($request_data['clinic_sessions'])){
            foreach ($request_data['clinic_sessions'] as $key => $session) {
                $parent_id = 0;
                foreach ($session['days'] as $day) {

                    $start_time = date('H:i:s', strtotime($session['s_one_start_time']['HH'] . ':' . $session['s_one_start_time']['mm']));
                    $end_time = date('H:i:s', strtotime($session['s_one_end_time']['HH'] . ':' . $session['s_one_end_time']['mm']));

                    $session_temp = [
                        'clinic_id' => $clinic_id,
                        'doctor_id' => $session['doctors']['id'],
                        'day' => $day,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'time_slot' => $session['time_slot'],
                        'created_at' => current_time('Y-m-d H:i:s'),
                        'parent_id' => (int)$parent_id === 0 ? null : (int)$parent_id
                    ];

                    if ((int)$parent_id === 0) {
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

            $step_detail = array( 'step' => 3, 'name' => 'clinic_session', 'id' => $session_parent_ids, 'status' => true);
            $encoded_status_data = json_encode($step_detail);
            add_option('setup_step_3', $encoded_status_data);

        }


	    echo json_encode([
		    'status' => true,
		    'message' => esc_html__('Clinic session has been saved successfully', 'kc-lang'),
	    ]);
    }

    public function getSetupStepStatus () {

	    $request_data = $this->request->getInputs();

	    $step = $request_data['step'];
        $setup_wizard_status = get_option('setup_step_'.$step);

        if ($setup_wizard_status) {
            switch ($step) {
                case 1:
	                $clinic = new KCClinic();
                    $clinic_setup_data =  json_decode($setup_wizard_status);
                    $clinic_setup_detail = $clinic->get_by( [ 'id' => $clinic_setup_data->id ], '=', true );
                    $status = true;
                    $step_detail = $clinic_setup_detail;
                    break;
                case 2:
                    $step_detail = get_option('setup_step_2');
                    if(!$step_detail) {
                        $doctors = [] ;
                        echo json_encode( [
                            'status'  => false,
                            'message' => esc_html__('No doctors found', 'kc-lang'),
                            'data' => []
                        ]);
                        wp_die();
                    } else {
                        $step_detail = json_decode($step_detail);
                        $args = [
                            'include' => $step_detail->id
                        ];

                        $doctors = get_users( $args );

                    }

                    $data = [];

                    if (!count($doctors)) {
                        echo json_encode( [
                            'status'  => false,
                            'message' => esc_html__('No doctors found', 'kc-lang'),
                            'data' => []
                        ]);
                        wp_die();
                    }

                    foreach ($doctors as $key => $doctor) {

                        $user_meta = get_user_meta( $doctor->ID, 'basic_data', true );

                        $data[$key]['ID'] = $doctor->ID;
                        $data[$key]['display_name'] = $doctor->data->display_name;
                        $data[$key]['user_email'] = $doctor->data->user_email;
                        $data[$key]['user_status'] = $doctor->data->user_status;
                        $data[$key]['user_registered'] = $doctor->data->user_registered;

                        if ($user_meta != null) {
                            $basic_data = json_decode($user_meta);
                            $data[$key]['mobile_number'] = $basic_data->mobile_number;
                            $data[$key]['gender'] = $basic_data->gender;
                            $data[$key]['dob'] = $basic_data->dob;
                            $data[$key]['address'] = $basic_data->address;
                            $data[$key]['specialties'] = $basic_data->specialties;
                            $data[$key]['time_slot'] = $basic_data->time_slot;

	                        if (isset($basic_data->price_type)) {
		                        if ( $basic_data->price_type === "range" ) {
			                        $price          = explode( "-", $basic_data->price);
			                        $data[$key]['minPrice'] = isset( $price[0] ) ? $price[0] : 0;
			                        $data[$key]['maxPrice'] = isset( $price[1] ) ? $price[1] : 0;
			                        $data[$key]['price']    = 0;
		                        } else {
			                        $data[$key]['price']    = $basic_data->price ;
		                        }
	                        } else {
		                        $data[$key]['price_type'] = "range";

	                        }
                        }

                    }

	                $clinic_id = kcGetDefaultClinicId();
	                $doctor_data['doctors'] = $data ;
	                $doctor_data['clinic_session'] = kcGetClinicSessions($clinic_id);
                    $status = true;
                    $step_detail = $doctor_data ;
                    break;

                case 3:
                    $status = true;
                    $clinic_id = kcGetDefaultClinicId();
                    $step_detail = kcGetClinicSessions($clinic_id);
                    break;
                case 4:
                    $data = [];

                    $receptionists = get_users([
                        'role' => 'receptionist'
                    ]);

                    if (!count($receptionists)) {
                        echo json_encode( [
                            'status'  => false,
                            'message' => esc_html__('No receptionist found', 'kc-lang'),
                            'data' => []
                        ]);
                        wp_die();
                    }


                    foreach ($receptionists as $key => $receptionist) {

                        $user_meta = get_user_meta( $receptionist->ID, 'basic_data', true );
                        $first_name = get_user_meta( $receptionist->ID, 'first_name', true );
                        $last_name = get_user_meta( $receptionist->ID, 'last_name', true );

                        $data[$key]['ID'] = $receptionist->ID;
                        $data[$key]['display_name'] = $receptionist->data->display_name;
                        $data[$key]['user_email'] = $receptionist->data->user_email;
                        $data[$key]['user_status'] = $receptionist->data->user_status;
                        $data[$key]['user_registered'] = $receptionist->data->user_registered;
                        $data[$key]['username'] = $receptionist->data->user_login;


                        if($first_name !== null) {
                            $data[$key]['first_name'] = $first_name;
                        }

                        if($last_name !== null) {
                            $data[$key]['last_name'] = $last_name;
                        }

                        if ($user_meta !== null) {
                            $basic_data = json_decode($user_meta);
                            $data[$key]['mobile_number'] = $basic_data->mobile_number;
                            $data[$key]['gender'] = $basic_data->gender;
                            $data[$key]['dob'] = $basic_data->dob;
                            $data[$key]['address'] = $basic_data->address;
                            $data[$key]['state'] = $basic_data->state;
                            $data[$key]['city'] = $basic_data->city;
                            $data[$key]['postal_code'] = $basic_data->postal_code;
                            $data[$key]['country'] = $basic_data->country;
                        }

                    }

                    $status = true;
                    $step_detail = $data ;
                    break;

                default:
                    $status = false;
                    $step_detail = [];
            }

        } else {
            $status = false;
            $step_detail = [];
        }

	    $data = [
		    'status' => $status,
		    'message' => esc_html__('Setup step found', 'kc-lang'),
		    'data' => $step_detail
	    ];

	    echo json_encode($data);

    }

	public function receptionist () {

		$request_data = $this->request->getInputs();

		$rules = [
			'first_name' => 'required',
			'last_name'   => 'required',
			'user_email' => 'required|email',
			'mobile_number' => 'required',
			'dob' => 'required',
			'gender' => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($errors[0], 'kc-lang')
			] );
			die;
		}

		$step = 'setup_step_4' ;

		$temp = [
			'mobile_number'  => $request_data['mobile_number'],
			'gender'         => $request_data['gender'],
			'dob'            => $request_data['dob'],
			'address'        => $request_data['address'],
			'city'           => $request_data['city'],
			'state'          => $request_data['state'],
			'country'        => $request_data['country'],
			'postal_code'    => $request_data['postal_code']
		];

		if ( ! isset( $request_data['ID'] ) || $request_data['ID'] === null || $request_data['ID'] === '') {

			$request_data['username'] = kcGenerateUsername($request_data['first_name']) ;

            $request_data['user_pass'] = kcGenerateString(12);

			$user = wp_create_user($request_data['username'], $request_data['user_pass'], $request_data['user_email']);

			$u    = new WP_User( $user );

			$u->display_name = $request_data['first_name'] . ' ' .  $request_data['last_name'];

			wp_insert_user($u);

			$u->set_role( $this->getReceptionistRole() );

			if($user) {

				$receptionist_mapping = new KCReceptionistClinicMapping;

				$new_temp = [
					'receptionist_id' => $user,
					'clinic_id' => kcGetDefaultClinicId(),
					'created_at'=> current_time('Y-m-d H:i:s')
				];

				$receptionist_mapping->insert($new_temp);

				$user_email_param = array (
					'username' => $request_data['username'],
					'user_email' => $request_data['user_email'],
					'password' => $request_data['user_pass'],
					'email_template_type' => 'receptionist_registration'
				);

				kcSendEmail($user_email_param);
			}

			update_user_meta( $user, 'first_name', $request_data['first_name'] );
			update_user_meta( $user, 'last_name', $request_data['last_name'] );
			update_user_meta( $user, 'basic_data', json_encode( $temp ) );

			$doctor_ids = [];

			foreach ( $u as $key => $val ) {
				if ( $val->ID !== '' && $val->ID !== null ) {
					array_push( $doctor_ids, (int) $val->ID );
				}
			}

			$current_step_status = get_option( $step );

			if ( $current_step_status ) {

				$step_detail = json_decode( $current_step_status );

				delete_option( $step );

				array_push( $step_detail->id, $doctor_ids[0] );

			} else {

				$step_detail = array( 'step' => 4, 'name' => 'receptionist', 'id' => $doctor_ids, 'status' => true );

			}

			$encoded_status_data = json_encode($step_detail);

			add_option($step, $encoded_status_data);

		} else {

			wp_update_user(
				array(
					'ID'         => $request_data['ID'],
					'user_login' => $request_data['username'],
					'user_email' => $request_data['user_email'],
					'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
				)
			);

			update_user_meta( $request_data['ID'], 'basic_data', json_encode( $temp ) );
		}

		if ( $user->errors ) {

			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($user->get_error_message() ? $user->get_error_message() : 'Receptionist data save operation has been failed', 'kc-lang')
			] );

		} else {

			echo json_encode( [
				'status'  => true,
				'message' => esc_html__('Receptionist has been saved successfully', 'kc-lang'),
			] );

		}

	}

	public function setupFinish () {

		$role_activate = new KCActivate();

		$is_role_activated = $role_activate->migratePermissions();

		if($is_role_activated) {
			echo json_encode( [
				'status'  => true,
				'message' => esc_html__('Clinic Setup steps is completed successfully.', 'kc-lang'),
			] );

		} else {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('Clinic Setup is not completed successfully.', 'kc-lang'),
			] );
		}

	}

	public function updateSetupStep() {

        $request_data = $this->request->getInputs();
        $setup_config = collect(kcGetStepConfig());

        $setup_config = $setup_config->map(function ($step) use ($request_data) {
            if ($request_data['name'] === $step->name) {
                $step->completed = true;
            }
            return $step;
        });

        update_option($this->getSetupConfig(), json_encode($setup_config->toArray()));

        echo json_encode( [
            'status'  => true,
            'message' => esc_html__('Completed step.', 'kc-lang'),
            'data' => $request_data
        ] );

    }

    public function resetDoctorSession ($doctor_id) {

	    $clinic_id = kcGetDefaultClinicId();
	    $clinic_session = new KCClinicSession();
	    $clinic_session->delete(['clinic_id' => $clinic_id, 'doctor_id' => $doctor_id]);

	    $setup_config = collect(kcGetStepConfig());

	    $setup_config = $setup_config->map(function ($step) {
		    if ($step->name === 'clinic_session') {
			    $step->completed = false;
		    }
		    return $step;
	    });

	    update_option($this->getSetupConfig(), json_encode($setup_config->toArray()));

    }

}

