<?php

namespace App\Controllers;


use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCClinicSchedule;
use Exception;

class KCClinicScheduleController extends KCBase {

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

		$this->table_name = $wpdb->prefix . 'kc_' . 'clinic_schedule';

		$this->request = new KCRequest();

	}

	public function index() {

        $is_permission = false ;

        if ( kcCheckPermission( 'static_data_list' )  || kcCheckPermission( 'clinic_schedule' ) ) {
            $is_permission = true ;
        }

        if (!$is_permission) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang') ,
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();
		$clinic_schedule_table = $this->db->prefix . 'kc_' . 'clinic_schedule';

		$clinic_schedule_count_query = "SELECT count(*) AS count FROM {$clinic_schedule_table}";
		$total_clinic_schedule_data = $this->db->get_results( $clinic_schedule_count_query, OBJECT );
		$total_rows = $total_clinic_schedule_data['0'];
		if($this->getLoginUserRole() === 'kiviCare_doctor'){
			$clinic_schedule_query = "SELECT * FROM  {$clinic_schedule_table} WHERE module_id=".get_current_user_id()." ORDER BY id DESC LIMIT {$request_data['limit']} OFFSET {$request_data['offset']} ";
		}else{
			$clinic_schedule_query = "SELECT * FROM  {$clinic_schedule_table} ORDER BY id DESC LIMIT {$request_data['limit']} OFFSET {$request_data['offset']} ";
		}
		$clinic_schedule = collect($this->db->get_results( $clinic_schedule_query));

		$doctor_ids = $clinic_schedule->where('module_type', 'doctor')->pluck('module_id')->unique()->toArray();

		$user_table = $this->db->prefix. 'users';
		$doctor_ids = implode(',', $doctor_ids);
		$user_query = "SELECT * FROM $user_table WHERE `ID` IN ($doctor_ids) ";
		$users = collect($this->db->get_results($user_query));

		$clinic_schedule = $clinic_schedule->map(function ($schedule) use ($users) {
			$doctor =  " - ";
			if ($schedule->module_type === 'doctor') {
				$user = $users->where('ID', $schedule->module_id)->first();
				$doctor = isset($user->display_name) ? $user->display_name : "";
			}
			$schedule->doctor_name = $doctor;
			return $schedule;
		});

		if ( ! count( $clinic_schedule ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('No clinic schedule found', 'kc-lang') ,
				'data'    => []
			] );
			wp_die();
		}

		echo json_encode( [
			'status'  => true,
			'message' => esc_html__('Service list', 'kc-lang'),
			'data'    => $clinic_schedule,
			'total_rows' =>  $total_rows->count
		] );

	}

	public function save() {

		$request_data = $this->request->getInputs();

		$temp = [
			'module_type' => $request_data['module_type']['id'],
			'start_date' => date('Y-m-d', strtotime($request_data['scheduleDate']['start'])),
			'end_date' => date('Y-m-d', strtotime($request_data['scheduleDate']['end'])),
			'module_id' => $request_data['module_id']['id'],
			'description' => $request_data['description'],
			'status' => 1
		];

		$data = [
			'start_date' => $temp['start_date'],
			'end_date' => $temp['end_date']
		];

		if ($temp['module_type'] === 'doctor') {
			$data['doctor_id'] = $temp['module_id'];
		} else {
			$data['clinic_id'] = $temp['module_id'];
		}

		// Cancel appointment if exist...
		kcCancelAppointments($data);

		$clinic_schedule = new KCClinicSchedule;

		if (!isset($request_data['id'])) {
			$temp['created_at'] = current_time('Y-m-d H:i:s');
			$clinic_schedule->insert($temp);
			$message = esc_html__("Clinic holiday scheduled successfully.", 'kc-lang');

		} else {
			$clinic_schedule->update($temp, array( 'id' => $request_data['id'] ));
			$message = esc_html__("Clinic holiday schedule updated successfully.", 'kc-lang');
		}

		echo json_encode([
			'status' => true,
			'message' => $message
		]);

	}

	public function edit() {

		$request_data = $this->request->getInputs();

		try {

			if (!isset($request_data['id'])) {
				throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
			}

			$id = $request_data['id'];

			$clinic_schedule = (new KCClinicSchedule)->get_by([ 'id' => $id], '=', true);

			if ($clinic_schedule !== []) {

				if(isset($clinic_schedule->module_type) && isset($clinic_schedule->module_id)) {
					if (  $clinic_schedule->module_type === 'doctor') {
						$clinic_schedule->module_id = kcGetDoctorOption($clinic_schedule->module_id);
					} elseif ($clinic_schedule->module_type === 'clinic') {
						$clinic_schedule->module_id = [
							'id' => kcGetDefaultClinicId(),
							'label' => 'Clinic'
						];
					}

					$clinic_schedule->module_type = [
						'id' => $clinic_schedule->module_type,
						'label' => $clinic_schedule->module_type === 'doctor' ? 'Doctor' : 'Clinic'
					];

				} else {
					$clinic_schedule->module_type = [
						'id' => '',
						'label' => ''
					];
				}

			}

			$clinic_schedule->scheduleDate = [
				'start' => $clinic_schedule->start_date,
				'end' => $clinic_schedule->end_date
			];

			if ($clinic_schedule) {
				echo json_encode([
					'status' => true,
					'message' => esc_html__('Static data', 'kc-lang'),
					'data' => $clinic_schedule
				]);
			} else {
				throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
			}


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

	public function delete() {

		$request_data = $this->request->getInputs();

		try {

			if (!isset($request_data['id'])) {
				throw new Exception('Data not found', 400);
			}

			$id = $request_data['id'];

			$clinic_schedule = new KCClinicSchedule;

			$results = $clinic_schedule->delete(['id' => $id]);

			if ($results) {
				echo json_encode([
					'status' => true,
					'tableReload' => true,
					'message' => esc_html__('Clinic schedule has been deleted successfully', 'kc-lang'),
				]);
			} else {
				throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
			}


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
		$term_condition = esc_html__(get_option( 'terms_condition_content'), 'kc-lang') ;
		$term_condition_status = get_option( 'is_term_condition_visible') ;
		echo json_encode([
			'status' => true,
			'data' => array( 'isVisible' => $term_condition_status,
			                 'content' => $term_condition)
		]);
	}
}
