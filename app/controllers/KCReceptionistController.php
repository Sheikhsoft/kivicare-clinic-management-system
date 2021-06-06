<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCReceptionistClinicMapping;
use App\models\KCClinic;
use Exception;
use WP_User;

class KCReceptionistController extends KCBase {

	public $db;

	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

	}

	public function index() {

		if ( ! kcCheckPermission( 'receptionist_list' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
		$table_name = $this->db->prefix . 'kc_' . 'receptionist_clinic_mappings';
		$receptionistsCount = get_users( [
			'role' => $this->getReceptionistRole(),
		] );

		$receptionistsCount = count( $receptionistsCount );

		$args['role']           = $this->getReceptionistRole();
		$args['number']         = $request_data['limit'];
		$args['orderby']        = 'ID';
		$args['order']          = 'DESC';
		if(current_user_can('administrator')){
			$receptionists = get_users( $args );
		}else{
			$user_id = get_current_user_id();
			$clinic_id =  (new KCClinic())->get_by([ 'clinic_admin_id' => $user_id]);
			$query = "SELECT `receptionist_id` FROM {$table_name} WHERE `clinic_id` =". $clinic_id[0]->id ;
			$result = collect($this->db->get_results($query))->unique('receptionist_id')->pluck('receptionist_id');
			$receptionists = get_users( $args );
			$receptionists = collect($receptionists)->whereIn('ID',$result)->toArray();
		}

		if ( ! count( $receptionists ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No receptionist found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		$data = [];

		foreach ( $receptionists as $key => $receptionist ) {

			$user_meta = get_user_meta( $receptionist->ID, 'basic_data', true );
			$clinic_mapping = (new KCReceptionistClinicMapping())->get_by([ 'receptionist_id' => $receptionist->ID]);
            $clinic_name =  (new KCClinic())->get_by([ 'id' => $clinic_mapping[0]->clinic_id]);
			$data[ $key ]['ID']              = $receptionist->ID;
			$data[ $key ]['display_name']    = $receptionist->data->display_name;
			$data[ $key ]['user_email']      = $receptionist->data->user_email;
			$data[ $key ]['user_status']     = $receptionist->data->user_status;
			$data[ $key ]['user_registered'] = $receptionist->data->user_registered;
			$data[$key]['clinic_id'] = $clinic_mapping[0]->clinic_id;
            $data[$key]['clinic_name'] = $clinic_name[0]->name;

			if ( $user_meta != null ) {
				$basic_data                    = json_decode( $user_meta );
				$data[ $key ]['mobile_number'] = $basic_data->mobile_number;
				$data[ $key ]['gender']        = $basic_data->gender;
				$data[ $key ]['dob']           = $basic_data->dob;
				$data[ $key ]['address']       = $basic_data->address;
			}
		}

		echo json_encode( [
			'status'     => true,
			'message'    => esc_html__('Receptionist list', 'kc-lang'),
			'data'       => array_values($data),
			'total_rows' => $receptionistsCount
		] );

	}

	public function save() {

		$is_permission = false;
		$active_domain = $this->getAllActivePlugin();
		if ( kcCheckPermission( 'receptionist_profile' ) || kcCheckPermission( 'receptionist_add' ) || kcCheckPermission( 'receptionist_edit' ) ) {
			$is_permission = true;
		}

		if ( ! $is_permission ) {
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
				'message' => $errors[0]
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
		];
		$request_data['clinic_id'] = json_decode(stripslashes( $request_data['clinic_id']));
		if ( ! isset( $request_data['ID'] ) ) {

			$user = wp_create_user( $username, $password, $request_data['user_email'] );

			$u               = new WP_User( $user );
			$u->display_name = $request_data['first_name'] . ' ' . $request_data['last_name'];
			wp_insert_user( $u );

			$u->set_role( $this->getReceptionistRole() );

			$user_id = $u->ID;
			update_user_meta($user, 'first_name',$request_data['first_name'] );
			update_user_meta($user, 'last_name', $request_data['last_name'] );
			update_user_meta( $user, 'basic_data', json_encode( $temp, JSON_UNESCAPED_UNICODE ) );

			// Insert Doctor Clinic mapping...
			$receptionist_mapping = new KCReceptionistClinicMapping;

			$new_temp = [
				'receptionist_id' => $user_id,
				'clinic_id'       => isset($request_data['clinic_id']->id) ? $request_data['clinic_id']->id :kcGetDefaultClinicId(),
				'created_at'      =>   current_datetime('Y-m-d H:i:s' )
			];

			$receptionist_mapping->insert( $new_temp );

			$user_email_param = array(
				'username'            => $username,
				'user_email'          => $request_data['user_email'],
				'password'            => $password,
				'email_template_type' => 'receptionist_register'
			);

			kcSendEmail($user_email_param);

			$message = esc_html__('Receptionist has been saved successfully', 'kc-lang');

		} else {
			$receptionist_mapping = new KCReceptionistClinicMapping;

			wp_update_user(
				array(
					'ID'           => $request_data['ID'],
					'user_login'   => $request_data['username'],
					'user_email'   => $request_data['user_email'],
					'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
				)
			);

			$user_id = $request_data['ID'];
			update_user_meta($request_data['ID'], 'first_name',$request_data['first_name'] );
			update_user_meta($request_data['ID'], 'last_name', $request_data['last_name'] );
			update_user_meta( $request_data['ID'], 'basic_data', json_encode( $temp, JSON_UNESCAPED_UNICODE ) );
			if ($active_domain === $this->kiviCareProOnName()) {
                (new KCReceptionistClinicMapping())->delete(['receptionist_id' => $request_data['ID']]);
                foreach ($request_data['clinic_id'] as $value) {
                    $new_temp = [
                        'receptionist_id' => $user_id,
                        'clinic_id' => $value,
                        'created_at' => current_time('Y-m-d H:i:s')
                    ];
                    $receptionist_mapping->insert($new_temp);
                }
            }
			$message = esc_html__('Receptionist has been updated successfully', 'kc-lang');

		}

		if ( $user_id ) {
			$user_table_name = $this->db->prefix . 'users';
			$user_status     = $request_data['user_status'];
			$this->db->update( $user_table_name, [ 'user_status' => $user_status ], [ 'ID' => $user_id ] );
		}
		if($request_data['profile_image'] != '' && isset($request_data['profile_image']) && $request_data['profile_image'] != null ){
            $attachment_id = media_handle_upload('profile_image', 0);
            update_user_meta( $user_id, 'receptionist_profile_image',  $attachment_id  );
        }
		if ( $user->errors ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($user->get_error_message() ? $user->get_error_message() : 'Receptionist data save operation has been failed', 'kc-lang')
			] );
		} else {
			echo json_encode( [
				'status'  => true,
				'message' => $message
			] );
		}

	}

	public function edit() {

		$is_permission = false;

		if ( kcCheckPermission( 'receptionist_profile' ) || kcCheckPermission( 'receptionist_edit' ) ) {
			$is_permission = true;
		}

		if ( ! $is_permission ) {
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

		$clinic_id =  (new KCReceptionistClinicMapping())->get_by([ 'receptionist_id' =>$request_data['id']]);
		$clinics = collect((new KCReceptionistClinicMapping)->get_by(['receptionist_id' =>$request_data['id']]))->pluck('clinic_id')->toArray();
		$clinics = $table_name->whereIn('id', $clinics);
		$image_attachment_id = get_user_meta($request_data['id'],'receptionist_profile_image',true);
		$user_image_url = wp_get_attachment_url($image_attachment_id);
		try {

			if ( !isset( $request_data['id'] ) ) {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}

			$id = $request_data['id'];

			$user = get_userdata( $id );
			unset( $user->user_pass );

			$full_name = explode( ' ', $user->display_name );

			$user_data  = get_user_meta( $id, 'basic_data', true );
			$first_name = get_user_meta( $id, 'first_name', true );
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
			$data->user_profile =$user_image_url;
			if ( $data ) {
				echo json_encode( [
					'status'    => true,
					'message'   => 'Receptionist data found',
					'id'        => $id,
					'user_data' => $user_data,
					'data'      => $data
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

		if ( ! kcCheckPermission( 'receptionist_delete' ) ) {
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

			$results = wp_delete_user( $id );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Receptionist has been deleted successfully', 'kc-lang'),
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

	public function changePassword() {

		$request_data = $this->request->getInputs();

		$current_user = wp_get_current_user();

		$result = wp_check_password( $request_data['currentPassword'], $current_user->user_pass, $current_user->ID );

		if ( $result ) {

			if ( isset( $current_user->ID ) && $current_user->ID !== null && $current_user->ID !== '' ) {
				wp_set_password( $request_data['newPassword'], $current_user->ID );
				$status          = true;
				$message         = 'Password successfully changed';
				wp_logout();
			} else {
				$status  = false;
				$message = 'Password change failed.';
			}

		} else {

			$status  = false;
			$message = 'Current password is wrong!!';

		}

		echo json_encode( [
			'status'  => $status,
			'data'    => $result,
			'message' => esc_html__($message, 'kc-lang'),
		] );

	}

	public function changeEmail() {

		$request_data = $this->request->getInputs();

		echo json_encode( [
			'status'  => true,
			'data'    => $request_data,
			'message' => esc_html__('Email has been changed', 'kc-lang'),
		] );

	}

}