<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCUser extends KCModel {

	public function __construct()
	{
		parent::__construct('users');
	}


}