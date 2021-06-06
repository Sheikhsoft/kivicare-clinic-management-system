<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use Exception;
use WP_User;
class KCServiceController extends KCBase {

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

		$request_data      = $this->request->getInputs();
		$condition         = '';
		$service_table     = $this->db->prefix . 'kc_' . 'services';
		$static_data_table = $this->db->prefix . 'kc_' . 'static_data';
		$service_doctor_mapping  = $this->db->prefix . 'kc_' . 'service_doctor_mapping' ;
		$users_table       = $this->db->prefix . 'users';
		
        $login_user = wp_get_current_user();

		$service_count_query = "SELECT count(*) AS count FROM {$service_table}";
		$service_types_query = " SELECT * FROM  {$static_data_table} WHERE type = 'service_type'";

		$services_types = $this->db->get_results( $service_types_query, OBJECT );

		$services_types = collect( $services_types );

		$services_types = $services_types->unique( 'value' );

		$total_services = $this->db->get_results( $service_count_query, OBJECT );

		if ( isset($request_data['searchKey']) && $request_data['searchKey'] !== '' && isset($request_data['searchValue']) && $request_data['searchValue'] !== '') {
			$condition = " WHERE {$service_table}.{$request_data['searchKey']}  LIKE  '%{$request_data['searchValue']}%' ";
		}

		$doctor_condition = "" ;
        $zoom_config_data = "" ;

		if(isset($login_user->roles[0]) && $this->getDoctorRole() === $login_user->roles[0]) {
            
			$doctor_condition = " AND {$service_doctor_mapping}.doctor_id = {$login_user->data->ID} " ;

			$zoom_config_data = get_user_meta($login_user->data->ID, 'zoom_config_data', true);

        	$zoom_config_data = json_decode($zoom_config_data);

        } else if(isset($request_data['doctor_id'])) {
		
			$doctor_condition = " AND {$service_doctor_mapping}.doctor_id = " . $request_data['doctor_id'] ;

            $zoom_config_data = get_user_meta($request_data['doctor_id'], 'zoom_config_data', true);

            $zoom_config_data = json_decode($zoom_config_data);

        }
		
	
		if(isset($zoom_config_data->enableTeleMed) && ($zoom_config_data->enableTeleMed == 1 || $zoom_config_data->enableTeleMed == true)) {
			$query = "SELECT {$service_doctor_mapping}.*, {$service_table}.name AS name, {$service_table}.type AS service_type, {$users_table}.display_name AS doctor_name  FROM {$service_doctor_mapping}
					JOIN {$service_table}
					ON {$service_doctor_mapping}.service_id = {$service_table}.id
					JOIN {$users_table}
					ON {$users_table}.ID = {$service_doctor_mapping}.doctor_id
					WHERE 0 = 0  {$doctor_condition} ORDER BY {$service_table}.id  DESC" ;
		}else{
			$query = "  SELECT {$service_doctor_mapping}.*, {$service_table}.name AS name, {$service_table}.type AS service_type, {$users_table}.display_name AS doctor_name  FROM {$service_doctor_mapping}
			JOIN {$service_table}
			ON {$service_doctor_mapping}.service_id = {$service_table}.id
			JOIN {$users_table}
			ON {$users_table}.ID = {$service_doctor_mapping}.doctor_id
			WHERE 0 = 0 {$doctor_condition} AND {$service_table}.type != 'system_service' ORDER BY {$service_table}.id  DESC" ;
		}

		$services = $this->db->get_results( $query, OBJECT );

		$services = collect( $services );

		$services = $services->map( function ( $services ) use ( $services_types ) {
			$services->service_type = isset( $services->service_type ) ? str_replace( '_', ' ', $services->service_type) : "";
			return $services;
		} );

		if (isset($request_data['searchKey']) && $request_data['searchKey'] !== '' && isset($request_data['searchValue']) && $request_data['searchKey'] !== '' ) {
			$total_rows = count( $services );
		} else {
			$total_rows = $total_services[0]->count;
		}

		if ( ! count( $services ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No services found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		echo json_encode( [
			'status'     => true,
			'message'    => esc_html__('Service list', 'kc-lang'),
			'data'       => $services,
			'total_rows' => $total_rows
		] );

	}

	public function save() {

		if ( ! kcCheckPermission( 'service_add' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();

		$temp = [
			'name'   => $request_data['name'],
			'price'  => $request_data['price'],
			'type'   => $request_data['type']['id'],
			'status' => $request_data['status'],
		];

		$service = new KCService();

		$service_doctor_mapping = new KCServiceDoctorMapping();

		if ( ! isset( $request_data['id'] ) ) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$service_id = $service->insert( $temp );
		
            if ($service_id) {
				$user_id = get_current_user_id();
				$userObj = new WP_User($user_id);
				if($userObj->roles[0] == 'kiviCare_doctor'){
					$service_doctor_mapping->insert([
                        'service_id' => $service_id,
                        'clinic_id'  => kcGetDefaultClinicId(),
						'doctor_id'  => $user_id,
						'charges'    => $request_data['price']
                    ]);

				}else{
					foreach ($request_data['doctor_id'] as $key => $val) {
                        $service_doctor_mapping->insert([
                        'service_id' => $service_id,
                        'clinic_id'  => kcGetDefaultClinicId(),
						'doctor_id'  => $val['id'],
						'charges'    => $request_data['price']
                    ]);
                	}
				}
                
            }

			$message            = esc_html__('Service has been saved successfully', 'kc-lang');

		} else {

			$user = wp_get_current_user();

            $service->update([
				'name' => $request_data['name']
			], array('id' => $request_data['service_id']));

            $service_doctor_mapping->update([
				'service_id' => $request_data['service_id'],
				'doctor_id' => $request_data['doctor_id']['id'],
				'charges'    => $request_data['price'],
                'status'    => $request_data['status']['id'],
			], array('id' => $request_data['id']));

            $service->update( $temp, array( 'id' => $request_data['id'] ) );
            $message = esc_html__('Service has been updated successfully', 'kc-lang');

		}

		echo json_encode( [
			'status'  => true,
			'message' => esc_html__($message, 'kc-lang')
		] );

	}

	public function edit() {

		
		if ( ! kcCheckPermission( 'service_edit' ) || ! kcCheckPermission( 'service_view' ) ) {
			
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

			$edit_id = $request_data['id'];
			$service_table     = $this->db->prefix . 'kc_' . 'services';
			$static_data_table = $this->db->prefix . 'kc_' . 'static_data';
			$service_doctor_mapping  = $this->db->prefix . 'kc_' . 'service_doctor_mapping' ;
			$users_table       = $this->db->prefix . 'users';
			
			$query = " SELECT {$service_doctor_mapping}.id AS mapping_id, {$service_table}.id AS service_id, 
                              {$service_doctor_mapping}.*, {$service_table}.* , {$users_table}.display_name AS doctor_name, 
                              {$service_doctor_mapping}.charges AS doctor_charges, 
                              {$service_doctor_mapping}.status AS mapping_status
                       FROM  {$service_doctor_mapping} 
					   JOIN  {$users_table} ON {$users_table}.ID = {$service_doctor_mapping}.doctor_id  
					   JOIN  {$service_table} ON {$service_table}.id = {$service_doctor_mapping}.service_id  
					   WHERE {$service_doctor_mapping}.id = {$edit_id} ";


			$service = $this->db->get_results( $query, OBJECT );

			if ( count( $service ) ) {

				$service = $service[0];

				$service_category_query = "SELECT * FROM  {$static_data_table} WHERE value = '{$service->type}' LIMIT 1 " ;

                $service_category = $this->db->get_results( $service_category_query, OBJECT );

                $status = '' ;
				$status->id =  0 ;
				$status->label = 'Inactive' ;
				
				if((int) $service->mapping_status === 1) {
					$status->id = 1 ;
					$status->label = 'Active' ;
				}

				$temp = [
					'id'     => $service->mapping_id,
					'service_id' => $service->service_id,
					'name'   => $service->name,
					'price'  => $service->doctor_charges,
                    'doctor_id' =>  [
						'id' 	=>  $service->doctor_id,
						'label' =>  $service->doctor_name
					],
					'type'   => [
						'id'    => $service_category[0]->type,
						'label' => $service_category[0]->label
					],
					'status' => $status,
				];

                echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Service data', 'kc-lang'),
					'data'    => $temp
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

		if ( ! kcCheckPermission( 'service_delete' ) ) {
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

            $service_doctor_mapping = new KCServiceDoctorMapping();

			$id = $request_data['id'];

			$results = $service_doctor_mapping->delete( [ 'id' => $id ] );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Service has been deleted successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception( esc_html__('Service delete failed', 'kc-lang'), 400 );
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

	public function clinicService () {
	    $table = $this->db->prefix  . 'kc_' . 'services' ;
        $query = "SELECT `id`, `type`, `name`, `price` FROM {$table} " ;
        $services = $this->db->get_results( $query, OBJECT );
        echo json_encode([
            'status' => true,
            'message' => esc_html__('Clinic service list', 'kc-lang'),
            'data' => $services
        ]);
    }

}


