<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCPatientEncounter extends KCModel {

	public function __construct()
	{
		parent::__construct('patient_encounters');
	}

	public static function createEncounter($appointment_id) {
		$appointment = (new KCAppointment())->get_by([ 'id' => $appointment_id], '=', true);

		$encounter = (new self())->get_by(['appointment_id' => $appointment_id], '=', true);

		if ($encounter === []) {
			return (new self())->insert([
				'encounter_date' => date('Y-m-d'),
				'clinic_id' => $appointment->clinic_id,
				'doctor_id' => $appointment->doctor_id,
				'patient_id' => $appointment->patient_id,
				'appointment_id' => $appointment_id,
				'description' => $appointment->description,
				'added_by' => get_current_user_id(),
				'status' => 1,
				'created_at' => current_time( 'Y-m-d H:i:s' )
			]);
		} else {
			return $encounter;
		}

	}
	public static function closeEncounter($appointment_id) {

		$encounter = (new self())->get_by(['appointment_id' => $appointment_id], '=', true);

		if ($encounter !== []) {
			return (new self())->update( [ 'status' => '0' ], array( 'id' => $encounter->id ) );
		}

	}

}