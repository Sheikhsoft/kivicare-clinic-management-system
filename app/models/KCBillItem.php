<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCBillItem extends KCModel {

	public function __construct()
	{
		parent::__construct('bill_items');
	}
	public static function createAppointmentBillItem($appointment_id) {
		$appointment = (new KCAppointment())->get_by([ 'id' => $appointment_id], '=', true);
		if($appointment){
			$paitent_encounter = (new KCPatientEncounter())->get_by([ 'appointment_id' => $appointment_id], '=', true);
			$appointment_service = (new KCAppointmentServiceMapping())->get_by([ 'appointment_id' => $appointment_id], '=', true);
			if(isset($appointment_service)){
				$total_amount = 0;
				if( gettype($appointment_service) === 'array'){
					foreach ( $appointment_service  as $data ) {
						$get_services = (new KCService())->get_by([ 'id' => $data->service_id], '=', true);
						$total_amount = $total_amount + $get_services->price;
					}
				}
				else{
					$get_services = (new KCService())->get_by([ 'id' => $appointment_service->service_id], '=', true);
					$total_amount = $total_amount + $get_services->price;
				}
			
				$patient_bill = (new KCBill())->insert([
					'encounter_id' => $paitent_encounter->id,
					'appointment_id'=>$appointment_id,
					'total_amount'=>$total_amount,
					'discount'=>0,
					'actual_amount'=>$total_amount,
					'status'=>0,
					'payment_status'=>'unpaid',
					'created_at'=>current_time( 'Y-m-d H:i:s' )
				]);
				if($patient_bill){
					if( gettype($appointment_service) === 'array'){
						foreach ( $appointment_service as $key => $data ) {
							$get_services = (new KCService())->get_by([ 'id' => $data->service_id], '=', true);
							 (new self())->insert([
								'bill_id' => $patient_bill,
								'price'   => $get_services->price,
								'qty'     => 1,
								'item_id' => $get_services->id,
								'created_at' => current_time( 'Y-m-d H:i:s' )
							]);
						}
					}else{
						$get_services = (new KCService())->get_by([ 'id' => $appointment_service->service_id], '=', true);
						return (new self())->insert([
							'bill_id' => $patient_bill,
							'price'   => $get_services->price,
							'qty'     => 1,
							'item_id' => $get_services->id,
							'created_at' => current_time( 'Y-m-d H:i:s' )
						]);
					}
					
				}
			
			}
		}
	}
}