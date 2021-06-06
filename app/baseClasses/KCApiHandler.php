<?php

namespace App\baseClasses;


class KCApiHandler extends KCBase {

	public function init() {
		$api_folder_path = $this->plugin_path . 'app/controllers/api/';
		$dir = scandir($api_folder_path);

		if (count($dir)) {
			foreach ($dir as $controller_name) {
				if ($controller_name !== "." && $controller_name !== ".." && $controller_name !== ".filters.php") {
					$controller_name = explode( ".", $controller_name)[0];
					$this->call($controller_name);
				}
			}
		}
	}

	public function call($controllerName) {
		$controller = 'App\\controllers\\api\\' . $controllerName;
		(new $controller);
	}

}