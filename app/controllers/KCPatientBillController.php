<?php

namespace App\Controllers;


use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCBill;
use App\models\KCBillItem;
use App\models\KCClinic;
use App\models\KCPatientEncounter;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\models\KCAppointmentServiceMapping;
use Exception;

class KCPatientBillController extends KCBase {

	public $db;

	public $bill;
	/**
	 * @var KCRequest
	 */
	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->bill = new KCBill();

		$this->request = new KCRequest();

	}

	public function index() {

		if ( ! kcCheckPermission( 'patient_bill_list' ) ) {
			echo json_encode( [
				'status'      => false,
				'status_code' => 403,
				'message'     => esc_html__('You don\'t have a permission to access', 'kc-lang'),
				'data'        => []
			] );
			wp_die();
		}

		$request_data = $this->request->getInputs();

		$total_rows = count( $request_data );
		if ( ! isset( $request_data['encounter_id'] ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__('Encounter not found', 'kc-lang'),
				'data'    => []
			] );
			wp_die();
		}

		$encounter_id = $request_data['encounter_id'];
		$bills        = $this->bill->get_by( [ 'encounter_id' => $encounter_id ] );

		if ( ! count( $bills ) ) {
			echo json_encode( [
				'status'     => false,
				'message'    => esc_html__('No bills records found', 'kc-lang'),
				'data'       => [],
				'total_rows' => $total_rows
			] );
			wp_die();
		}

		echo json_encode( [
			'status'  => true,
			'message' => esc_html__('Medical records', 'kc-lang'),
			'data'    => $bills
		] );
	}

	public function save() {
		if ( ! kcCheckPermission( 'patient_bill_add' ) ) {
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
			'encounter_id'  => 'required',
			'total_amount'  => 'required',
			'discount'      => 'required',
			'actual_amount' => 'required',
			'billItems'     => 'required'
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__($errors[0], 'kc-lang')
			] );
			die;
		}

		$patient_encounter = ( new KCPatientEncounter )->get_by( [ 'id' => $request_data['encounter_id'] ], '=', true );
		if ( empty( $patient_encounter ) ) {
			echo json_encode( [
				'status'  => false,
				'message' => esc_html__("No encounter found", 'kc-lang')
			] );
			die;
		}
		$user_id = get_current_user_id();
		$clinic_mapping = (new KCClinic())->get_by([ 'clinic_admin_id' => $user_id]);
		$temp = [
			'title'         => $request_data['title'],
			'encounter_id'  => $request_data['encounter_id'],
			'clinic_id'  => isset($patient_encounter->clinic_id) ? $patient_encounter->clinic_id : 1 ,
			'total_amount'  => $request_data['total_amount'],
			'discount'      => $request_data['discount'],
			'actual_amount' => $request_data['actual_amount'],
			'status'        => $request_data['status'],
			'payment_status' => isset($request_data['payment_status']['id'])? $request_data['payment_status']['id'] : 'unpaid',
			'appointment_id' => isset($request_data['appointment_id']) ? $request_data['appointment_id']: 0,
		];

		if ( ! isset( $request_data['id'] ) ) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$status             = $this->bill->insert( $temp );
			$bill_id            = $status;

		} else {

			$bill_id = $request_data['id'];
			$status  = $this->bill->update( $temp, array( 'id' => $request_data['id'] ) );

		}
		if ( isset( $request_data['billItems'] ) && count( $request_data['billItems'] ) ) {
			// insert bill items
            foreach ( $request_data['billItems'] as $key => $bill_item ) {
				if ((int) $bill_item['item_id']['price'] === 0 ) {
					$service_object = new KCService();
					
					$service        = $service_object->get_by( [
						'type' => 'bill_service',
						'name' => strtolower( $bill_item['item_id']['label'] )
					], '=', true );
					// here if service not exist then add into service table
					if ( $service ) {
						$bill_item['item_id']['id'] = $service->id;
					} else {
                        $new_service['type']        = 'bill_service';
						$new_service['name']        = strtolower( $bill_item['item_id']['id'] );
						$new_service['status']      = 1;
						$new_service['created_at'] = current_time( 'Y-m-d H:i:s' );
						$service_id                 = $service_object->insert( $new_service );
						$bill_item['item_id']['id'] = $service_id;
						$service_doctor_mapping = new KCServiceDoctorMapping();
						$service_appoiment_mapping = new KCAppointmentServiceMapping();
						if ( isset( $request_data['doctor_id'] ) && $request_data['doctor_id'] > 0 ) {
							if ($service_id) {
								$service_doctor_mapping->insert([
									'service_id' => $service_id,
									'clinic_id'  => kcGetDefaultClinicId(),
									'doctor_id'  => $request_data['doctor_id'],
									'charges'    => $bill_item['price']
								]);
							}
							$message            = 'Service has been saved successfully';
						}
						if(isset( $request_data['appointment_id'] ) && $request_data['appointment_id'] > 0 && $request_data['appointment_id']!= null){
							if ($service_id) {
								$service_appoiment_mapping->insert([
									'service_id' => $service_id,
									'appointment_id'=> $request_data['appointment_id'],
									'status'=> 1
								]);
							}
						}
					}

				}
                $_temp = [
					'bill_id' => $bill_id,
					'price'   => $bill_item['price'],
					'qty'     => $bill_item['qty'],
					'item_id' => $bill_item['item_id']['id'],
				];

				$bill_item_object = new KCBillItem();
				
				if ( ! isset( $bill_item['id'] ) ) {
					$_temp['created_at'] = current_time( 'Y-m-d H:i:s' );
					$bill_item_object->insert( $_temp );
				} else {
					$bill_item_object->update( $_temp, array( 'id' => $bill_item['id'] ) );
				}
			}
		}
		if($request_data['payment_status']['id'] == 'paid'){
			( new KCPatientEncounter() )->update( [ 'status' => '0' ], [ 'id' => $request_data['encounter_id'] ] );
				$get_sms_config  = get_user_meta(get_current_user_id(),'sms_config_data',true);
				$get_sms_config = json_decode($get_sms_config);
				if( $get_sms_config->enableSMS == 1){
					$response = apply_filters('kcpro_send_sms', [
						'type' => 'encounter',
						'encounter_id' => $request_data['encounter_id'],
						'current_user' => get_current_user_id(),
						'patient_id'=>$patient_encounter->patient_id
					]);
				}

		}else{
			( new KCPatientEncounter() )->update( [ 'status' => '1' ], [ 'id' => $request_data['encounter_id'] ] );
		}
		echo json_encode( [
			'status'  => true,
			'message' => esc_html__('Bill has been generated successfully', 'kc-lang')
		] );

	}

	public function edit() {

		if ( ! kcCheckPermission( 'patient_bill_edit' ) || ! kcCheckPermission( 'patient_bill_view' ) ) {
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

			$results = $this->bill->get_by( [ 'id' => $id ], '=', true );

			if ( $results ) {

				$temp = [
					'id'            => $results->id,
					'title'         => $results->title,
					'encounter_id'  => $results->encounter_id,
					'total_amount'  => $results->total_amount,
					'discount'      => $results->discount,
					'actual_amount' => $results->actual_amount,
					'status'        => $results->status,
					'billItems'     => []
				];

				$billItems = ( new KCBillItem )->get_by( [ 'bill_id' => $results->id ], '=', false );

				if ( count( $billItems ) ) {
					foreach ( $billItems as $item ) {
						$service = ( new KCService )->get_by( [ 'id' => $item->item_id ], '=', true );

						$temp['billItems'][] = [
							'bill_id' => $item,
							'id'      => $item->id,
							'price'   => $item->price,
							'qty'     => $item->qty,
							'item_id' => [
								'id'    => $item->item_id,
								'label' => $service->name,
								'price' => $service->price,
							],
						];
					}
				}


				echo json_encode( [
					'status'  => true,
					'message' => 'Bill item',
					'data'    => $temp
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


	public function details() {

		if ( ! kcCheckPermission( 'patient_bill_view' ) ) {
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

			$id           = isset( $request_data['id'] ) ? $request_data['id'] : 0;
			$encounter_id = isset( $request_data['encounter_id'] ) ? $request_data['encounter_id'] : 0;

			if ( $encounter_id !== 0 ) {
				$results = $this->bill->get_by( [ 'encounter_id' => $encounter_id ], '=', true );
			} else {
				$results = $this->bill->get_by( [ 'id' => $id ], '=', true );
			}
			$patient_encounter = ( new KCPatientEncounter )->get_by( [ 'id' => $results->encounter_id ], '=', true );
			$clinic            = ( new KCClinic )->get_by( [ 'id' => $patient_encounter->clinic_id ], '=', true );

			$patient_data = kcGetUserData($patient_encounter->patient_id);

			if ( $results ) {
				$temp = [
					'id'               => $results->id,
					'title'            => $results->title,
					'encounter_id'     => $results->encounter_id,
					'total_amount'     => $results->total_amount,
					'discount'         => $results->discount,
					'actual_amount'    => $results->actual_amount,
					'status'           => $results->status,
					'payment_status'=>$results->payment_status,
			        // 'payment_status'   => [
					// 	'id'=>$results->payment_status,
					// 	'label'=>ucfirst($results->payment_status)
					// ],
					'created_at'       => $results->created_at,
					'billItems'        => [],
					'patientEncounter' => $patient_encounter,
					'clinic'           => $clinic,
					'patient'          => [
						'id' => $patient_data->ID,
						'display_name' => $patient_data->display_name,
						'gender' => isset($patient_data->basicData->gender) ? $patient_data->basicData->gender : "",
						'dob' => isset($patient_data->basicData->dob) ? $patient_data->basicData->dob : ""
					]
				];

				$billItems = ( new KCBillItem )->get_by( [ 'bill_id' => $results->id ], '=', false );
				if ( count( $billItems ) ) {
					foreach ( $billItems as $item ) {
						$service = ( new KCService )->get_by( [ 'id' => $item->item_id ], '=', true );
						
						$temp['billItems'][] = [
							'bill_id' => $item,
							'id'      => $item->id,
							'price'   => $item->price,
							'qty'     => $item->qty,
							'item_id' => [
								'id'    => $item->item_id,
								'label' => $service->name,
								'price' => $service->price,
							],
						];
					}
				}
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Bill item', 'kc-lang'),
					'data'    => $temp
				] );

			} else {
				if ( $encounter_id !== 0 ) {
					echo json_encode( [
						'status'  => true,
						'message' => esc_html__('Bill not found', 'kc-lang'),
						'data'    => []
					] );
				} else {
					throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
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

	public function delete() {

		if ( ! kcCheckPermission( 'patient_bill_delete' ) ) {
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

			( new KCBillItem() )->delete( [ 'bill_id' => $id ] );

			$results = ( new KCBill() )->delete( [ 'id' => $id ] );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Bill item has been deleted successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception( esc_html__('Bill item delete failed', 'kc-lang'), 400 );
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

	public function deleteBillItem() {

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['bill_item_id'] ) ) {
				throw new Exception( esc_html__('Data not found', 'kc-lang'), 400 );
			}

			$id = $request_data['bill_item_id'];

			$results = ( new KCBillItem() )->delete( [ 'id' => $id ] );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Bill item has been deleted successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception( esc_html__('Bill item delete failed', 'kc-lang'), 400 );
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

	public function updateStatus() {

		if ( ! kcCheckPermission( 'patient_bill_edit' ) ) {
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

			$results = $this->bill->update( [ 'status' => 1 ], array( 'id' => $request_data['id'] ) );

			if ( $results ) {
				echo json_encode( [
					'status'  => true,
					'message' => esc_html__('Payment status has been updated successfully', 'kc-lang'),
				] );
			} else {
				throw new Exception( esc_html__('update status process failed', 'kc-lang'), 400 );
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