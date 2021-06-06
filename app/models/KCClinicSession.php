<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCClinicSession extends KCModel {

	public function __construct()
	{
		parent::__construct('clinic_sessions');
	}

}
