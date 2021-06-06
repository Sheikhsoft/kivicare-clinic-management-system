<?php

namespace App\Controllers;


use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCCustomField;
use Exception;
use WP_User;
class KCCustomFieldController extends KCBase {

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

		if ( ! kcCheckPermission( 'custom_field_list' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$custom_field = ( new KCCustomField )->get_all(' id DESC');

		if ( ! count( $custom_field ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No custom fields found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		echo json_encode( [
			'status'  => true,
			'message' => esc_html__('Custom fields records', 'kc-lang'),
			'data'    => $custom_field
		] );
	}

	public function save() {

		if ( ! kcCheckPermission( 'custom_field_add' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}
        $request_data = $this->request->getInputs();
        $custome_field = new KCCustomField();
        $fields = [];
        $user_id = get_current_user_id();
        $userObj = new WP_User($user_id);
        if ( count( $request_data['fields'] ) ) {
            foreach ( $request_data['fields'] as $key => $field ) {
                $field['name'] = str_replace( ' ', '_', $field['label'] );
                $field['type'] =  $field['type']['id'];
                $field['status'] =  $field['status']['id'];
                array_push( $fields, $field );
            }
        }
        if($userObj->roles[0] == 'kiviCare_doctor' && $request_data['module_type']['id'] == 'patient_module'){
            $request_data['module_id'] = $user_id;
        }else if($userObj->roles[0] == 'kiviCare_doctor' && $request_data['module_type']['id'] == 'patient_encounter_module'){
            $request_data['module_id'] = $user_id;
        }
		else if($userObj->roles[0] == 'kiviCare_doctor' && $request_data['module_type']['id'] == 'appointment_module'){
            $request_data['module_id'] = $user_id;
        }else{
            $request_data['module_id'] = isset($request_data['module_id']['id']) ? $request_data['module_id']['id'] :0;
        }
        $temp = [
            'module_type' => $request_data['module_type']['id'],
            'module_id' => isset($request_data['module_id']['id']) ? $request_data['module_id']['id'] :$request_data['module_id'] ,
            'fields'      => json_encode( $fields[0] ),
            'status'      => $request_data['status']['id']
        ];


        if ( ! isset( $request_data['id'] ) && gettype($request_data['module_id']) !== 'array' ) {

            $temp['created_at'] = current_time( 'Y-m-d H:i:s' );
            $data = $custome_field->insert( $temp );
            $message = esc_html__('Custom fields has been saved successfully', 'kc-lang') ;

        } else {
            $message = esc_html__('Custom fields has been updated successfully', 'kc-lang') ;
            $custome_field->update( $temp, array( 'id' => $request_data['id'] ) );
        }

        echo json_encode( [
            'status'  => true,
            'message' => $message
        ] );


    }

	public function edit() {
		if ( ! kcCheckPermission( 'custom_field_edit' ) || !kcCheckPermission('custom_field_view') ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
		// try {

			if ( ! isset( $request_data['id'] ) ) {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}

			$id = $request_data['id'];

			$custom_field = ( new KCCustomField() )->get_by( [ 'id' => $id ], '=', true );

			if($custom_field->module_id !== null &&  $custom_field->module_id !== '') {
				$user_data = get_userdata( (int) $custom_field->module_id );
				if(isset($user_data->data)) {
					$custom_field->module_id = [
						'id' => $user_data->data->ID,
						'label' => $user_data->data->display_name
					];
				}
			} else {
				$custom_field->module_id = '' ;
			}
		
			$fields = json_decode($custom_field->fields) ;

			$fields->type = [
				'id' => $fields->type, 
				'label' =>  ucfirst($fields->type)
			];

			if ( $custom_field ) {

				$custom_field->status = [
					'id' => 0,
					'label' => 'Inactive'
				] ;

				if( (int) $custom_field->status === 1) {
					$custom_field->status = [
						'id' => 1,
						'label' => 'Active'
					] ;
				}

				$temp = [
					'id'          => $custom_field->id,
					'module_type' => [
						'id'    => $custom_field->module_type,
						'label' => str_replace( "_", " ", $custom_field->module_type )
					],
					'module_id'   => $custom_field->module_id,
					'fields'      => $fields,
					'status' =>  $custom_field->status
				];
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Custom field record', 'kc-lang'),
					'data'    => $temp
				] );

			} else {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}


		// } catch ( Exception $e ) {

		// 	$code    = esc_html__($e->getCode(), 'kc-lang');
		// 	$message = esc_html__($e->getMessage(), 'kc-lang');

		// 	header( "Status: $code $message" );

		// 	echo json_encode( [
		// 		'status'  => false,
		// 		'message' => $e->getMessage()
		// 	] );
		// }
	}

	public function delete() {

		if ( ! kcCheckPermission( 'custom_field_delete' ) ) {
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

			$results = ( new KCCustomField() )->delete( [ 'id' => $id ] );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Custom field has been deleted successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception( esc_html__('Custom field delete failed', 'kc-lang'), 400 );
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
}
