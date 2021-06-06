<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCStaticData;
use Exception;

class KCStaticDataController extends KCBase {

	public $db;

	public $table_name;

	public $db_config;

	/**
	 * @var KCRequest
	 */
	private $request;

	public function __construct() {
		global $wpdb;

		$this->db = $wpdb;

		$this->table_name = $wpdb->prefix . 'kc_' . 'static_data';

		$this->db_config = [
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
			'db'   => DB_NAME,
			'host' => DB_HOST
		];

		$this->request = new KCRequest();

	}

	public function index() {

		if ( ! kcCheckPermission( 'static_data_list' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
		$static_data_table = $this->db->prefix . 'kc_' . 'static_data';
		$total_rows = 0 ;
		$condition = '';
		$static_data_count_query = "
			SELECT count(*) AS count FROM {$static_data_table} ";
		$total_static_data = $this->db->get_results( $static_data_count_query, OBJECT );

		if($request_data['searchKey'] && $request_data['searchValue']) {
			$condition = " WHERE {$static_data_table}.{$request_data['searchKey']} LIKE  '%{$request_data['searchValue']}%' " ;
		}

		$static_data_query = "
			SELECT *, REPLACE(type,'_',' ') as type
			FROM  {$static_data_table}  {$condition} ORDER BY id DESC ";

		$static_data = $this->db->get_results( $static_data_query, OBJECT );

		if($request_data['searchKey'] && $request_data['searchValue']) {
			$total_rows = count($static_data);
		} else {
			$total_rows = $total_static_data[0]->count ;
		}

		if ( ! count( $static_data ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No services found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		echo json_encode( [
			'status'  => true,
			'message' => 'Service list',
			'data'    => $static_data,
			'total_rows' =>  $total_rows
		] );
	}

	public function save() {

		if ( ! kcCheckPermission( 'static_data_add' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();

		$value = str_replace(' ', '_', strtolower($request_data['label']));

		$temp = [
			'label' => $request_data['label'],
			'type' => $request_data['type']['id'],
			'value' => $value,
			'status' => $request_data['status']['id'],
		];

		$static_data = new KCStaticData;

		if (!isset($request_data['id'])) {

			$value_records = $static_data->get_by(['type' => $request_data['type']['id'], 'value' => $value]);

			if (count($value_records)) {
				echo json_encode([
					'status' => false,
					'message' => esc_html__('Similar value is already exists in database', 'kc-lang')
				]);
				die;
			}

			$temp['created_at'] = current_time('Y-m-d H:i:s');
			$insert_id = $static_data->insert($temp);
			$message = esc_html__('Static data has been saved successfully', 'kc-lang');

		} else {
			$static_data->update($temp, array( 'id' => $request_data['id'] ));
			$message = esc_html__('Static data has been updated successfully', 'kc-lang');
            $insert_id = '';
		}

		echo json_encode([
			'status' => true,
			'message' => esc_html__($message, 'kc-lang'),
            'insert_id' => $insert_id
		]);

	}

	public function edit() {

		if ( ! kcCheckPermission( 'static_data_edit' ) || ! kcCheckPermission('static_data_view') ) {
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

			if (!isset($request_data['id'])) {
				throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
			}

			$id = $request_data['id'];

			$static_data = new KCStaticData;

			$results = $static_data->get_by(['id' => $id], '=',true);

			$results->status = [
				'id' => 0,
				'label' => 'Inactive'
			] ;

			if( (int) $results->status === 1) {
				$results->status = [
					'id' => 1,
					'label' => 'Active'
				] ;
			}
			
			$results->type = [
				'id' => $results->type,
				'type' => str_replace('_', ' ', $results->type),
			] ;

			if ($results) {
				echo json_encode([
					'status' => true,
					'message' => esc_html__('Static data', 'kc-lang'),
					'data' => $results
				]);
			} else {
				throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
			}


		} catch (Exception $e) {

			$code    = esc_html__($e->getCode(), 'kc-lang');
			$message = esc_html__($e->getMessage(), 'kc-lang');

			header("Status: $code $message");

			echo json_encode([
				'status' => false,
				'message' => $message
			]);
		}
	}

	public function delete() {

		if ( ! kcCheckPermission( 'static_data_delete' ) ) {
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

			if (!isset($request_data['id'])) {
				throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
			}

			$id = $request_data['id'];

			$static_data = new KCStaticData;

			$results = $static_data->delete(['id' => $id]);

			if ($results) {
				echo json_encode([
					'status' => true,
					'tableReload' => true,
					'message' => esc_html__('Static data has been deleted successfully', 'kc-lang'),
				]);
			} else {
				throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
			}


		} catch (Exception $e) {

			$code    = esc_html__($e->getCode(), 'kc-lang');
			$message = esc_html__($e->getMessage(), 'kc-lang');

			header("Status: $code $message");

			echo json_encode([
				'status' => false,
				'message' => $message
			]);
		}
	}

	public function saveTermsCondition () {

		$request_data = $this->request->getInputs();

		delete_option('terms_condition_content');
		delete_option('is_term_condition_visible');

		add_option( 'terms_condition_content', $request_data['content']);
		add_option( 'is_term_condition_visible', $request_data['isVisible']) ;

		echo json_encode([
			'status' => true,
			'message' => esc_html__('Terms & Condition has been saved successfully', 'kc-lang')
		]);
	}

	public function getTermsCondition () {
		$term_condition = get_option( 'terms_condition_content') ;
		$term_condition_status = get_option( 'is_term_condition_visible') ;
		echo json_encode([
			'status' => true,
			'data' => array( 'isVisible' => $term_condition_status, 'content' => esc_html__($term_condition, 'kc-lang'))
		]);
	}

	public function getEmailTemplate () {
		$prefix = KIVI_CARE_PREFIX;
		$args['post_type'] = strtolower($prefix.'mail_tmp');
		$args['nopaging'] = true;
		$args['post_status'] = 'any' ;
		$template_result = get_posts($args);
		$template_result = collect($template_result)->unique('post_title')->sortBy('ID');
		
		if ($template_result) {
			echo json_encode([
				'status' => true,
				'data' => $template_result
			]);
		} else {
			echo json_encode([
				'status' => true,
				'data' => $template_result
			]);
		}
	}
	public function getSMSTemplate () {
		$response = apply_filters('kcpro_get_sms_template', []);
        echo json_encode($response);
	}
	public function saveEmailTemplate () {

		$request_data = $this->request->getInputs();
		foreach ($request_data['data'] as $key => $value) {
			wp_update_post($value);
		}

		echo json_encode([
			'status' => true,
			'message' => esc_html__('Email template not saved successfully.', 'kc-lang')
		]);

	}
	public function saveSMSTemplate () {

		$request_data = $this->request->getInputs();
		$response = apply_filters('kcpro_save_sms_template', [
			'data'=>$request_data['data']
		]);
	}
	public function filterEmailContent() {
		$str = "The rain in SPAIN falls mainly on the plains.";
		$pattern = "/ain/i";
		if(preg_match_all($pattern, $str, $matches)) {
		    echo json_encode([
				'status' => true,
				'data' => $matches
			]);
		}
	}

	public function getOption() {
		$request_data = $this->request->getInputs();
		$option = get_option($request_data['name']);
		echo json_encode([
			'status' => true,
			'data' => $option
		]);
	}

	public function saveCommonSettings() {
		$request_data = $this->request->getInputs();
		$is_updated = update_option($request_data['name'], $request_data['data']);
		if($is_updated) {
			$status = true ;
			$message = esc_html__('Common setting save successfully.', 'kc-lang');
		} else {
			$status = false ;
			$message = esc_html__('Common setting not save successfully.', 'kc-lang');
		}
		echo json_encode([
			'status' => $status,
			'message' => esc_html__($message, 'kc-lang')
		]);
	}

    public function getCountryCurrencyList () {
        $country_currency_list = kcCountryCurrencyList() ;
        echo json_encode([
            'status' => true,
            'data' => $country_currency_list,
            'message' => esc_html__('country list', 'kc-lang')
        ]);
	}

	public function getLangDynamicKeyValue () {

		$request_data = $this->request->getInputs();
		$file = $request_data ;

		//english
		$upload_dir = wp_upload_dir(); 
		$dir_name = KIVI_CARE_PREFIX.'lang';
		$user_dirname = $upload_dir['basedir'] . '/' . $dir_name;
		$current_file = $user_dirname.'/temp.json';
		$str = file_get_contents($current_file);
		$json = json_decode($str, true);
		
		$json = collect($json);

		$data = $json->map( function ( $d ) {
			if(gettype($d) === 'object'){
				$d = json_decode($d,true);
			}
			return $d;
		});

		if ($data !== null && $data !== '') {
			$data = $data->toArray();
		} else {
			$data = [] ;
		}

		wp_send_json([
			'status' => true,
			'data'=> $data,
		]);

	}
}

