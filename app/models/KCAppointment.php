<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCAppointment extends KCModel {

	public function __construct()
	{
		parent::__construct('appointments');
	}

	public static function getDoctorAppointments($doctor_id) {
		return collect(( new self() )->get_by(['doctor_id' => $doctor_id]));
	}

}