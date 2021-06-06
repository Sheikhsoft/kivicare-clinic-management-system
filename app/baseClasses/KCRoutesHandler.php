<?php

namespace App\baseClasses;

use Exception;

class KCRoutesHandler extends KCBase {

	public  $routes;

	public  $controllerPath;

	function __construct($routes, $controllerPath) {
	    $this->routes = $routes;
	    $this->controllerPath = $controllerPath;
    }

    public function init() {

		// Action to handle routes...
		add_action( "wp_ajax_ajax_post", [ $this, 'ajaxPost' ] );
		add_action( "wp_ajax_nopriv_ajax_post", [ $this, 'ajaxPost' ] );
		add_action( "wp_ajax_ajax_get", [ $this, 'ajaxGet' ] );
		add_action( "wp_ajax_nopriv_ajax_get", [ $this, 'ajaxGet' ] );
	}

	public function ajaxPost() {

		$request = new KCRequest();

		$_REQUEST = $request->getInputs();

		try {

			if ( strtolower( $_SERVER['REQUEST_METHOD'] ) !== 'post' ) {
				$error = 'Method is not allowed';
				throw new Exception( $error, 405 );
			}

			if ( ! isset( $_REQUEST['route_name'] ) ) {
				$error = 'Route not found';
				throw new Exception( $error, 404 );
			}

			if ( isset($this->routes[ $_REQUEST['route_name'] ]) ) {

				$route = $this->routes[ $_REQUEST['route_name'] ];

				if ( strtolower( $route['method'] ) !== 'post' ) {
					$error = 'Method is not allowed';
					throw new Exception( $error, 405 );
				}

				if (!isset($route['nonce'])) {
					$route['nonce'] = 1;
				}

				if ($route['nonce'] === 1) {
					if ( ! wp_verify_nonce( $_REQUEST['_ajax_nonce'], 'ajax_post' ) ) {
						$error = 'Invalid nonce in request';
						throw new Exception( $error, 419 );
					}
				}

				$this->call( $route );
			}

		} catch ( Exception $e ) {

			$code    = $e->getCode();
			$message = $e->getMessage();

			header( "Status: $code $message" );

			echo json_encode( [
				'status'  => false,
				'message' => $e->getMessage()
			] );

		}
		wp_die();
	}


	public function ajaxGet() {

		$request = new KCRequest();

		$_REQUEST = $request->getInputs();

		if ( $_REQUEST === '' ) {
			$_REQUEST = json_decode( file_get_contents( "php://input" ), true );
		}

		try {
			if ( strtolower( $_SERVER['REQUEST_METHOD'] ) !== 'get' ) {
				$error = 'Method is not allowed';
				throw new Exception( $error, 405 );
			}

			if ( ! isset( $_REQUEST['route_name'] ) ) {
				$error = 'Route not found';
				throw new Exception( $error, 404 );
			}

			if (isset($this->routes[ $_REQUEST['route_name'] ])) {
				$route = $this->routes[ $_REQUEST['route_name'] ];

				if ( strtolower( $route['method'] ) !== 'get' ) {
					$error = 'Method is not allowed';
					throw new Exception( $error, 405 );
				}

				$this->call( $route );
			}



		} catch ( Exception $e ) {

			$code    = $e->getCode();
			$message = $e->getMessage();

			header( "Status: $code $message" );

			echo json_encode( [
				'status'  => false,
				'message' => $e->getMessage()
			] );
		}
		//wp_die();
	}

	public function call( $route ) {

		$cluster = explode( '@', $route['action'] );

		$controller = $this->controllerPath . $cluster[0];
		$function   = $cluster[1];

		( new $controller )->$function();

		die;
	}

}