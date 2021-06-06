<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCPrescription extends KCModel {

	public function __construct()
	{
		parent::__construct('prescription');
	}

}