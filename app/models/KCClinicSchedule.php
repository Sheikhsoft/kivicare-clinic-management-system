<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCClinicSchedule extends KCModel {

	public function __construct()
	{
		parent::__construct('clinic_schedule');
	}

}