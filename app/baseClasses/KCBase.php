<?php

/**
 * @package  KiviCarePlugin
 */

namespace App\baseClasses;
use WP_User;

class KCBase {

	public $plugin_path;

	public $nameSpace;

	public $plugin_url;

	public $plugin;

	public $dbConfig;

	private $pluginPrefix;

	public $doctorRole;

    public $patientRole;

    public $pluginVersion ;

	public function __construct() {

		require_once( ABSPATH . "wp-includes/pluggable.php" );

		$this->plugin_path = plugin_dir_path( dirname( __FILE__, 2 ) );
		$this->plugin_url  = plugin_dir_url( dirname( __FILE__, 2 ) );

		if  (defined( 'KIVI_CARE_NAMESPACE' )) {
			$this->nameSpace    = KIVI_CARE_NAMESPACE;
		}

		if  (defined( 'KIVI_CARE_PREFIX' )) {
			$this->pluginPrefix    = KIVI_CARE_PREFIX;
			$this->doctorRole  = KIVI_CARE_PREFIX . "doctor";
			$this->patientRole = KIVI_CARE_PREFIX . "patient";
		}

		$this->plugin = plugin_basename( dirname( __FILE__, 3 ) ) . '/kivicare-clinic-management-system.php';

		$this->dbConfig = [
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
			'db'   => DB_NAME,
			'host' => DB_HOST
		];

	}

	public function get_namespace() {
		return $this->nameSpace;
	}

	protected function getPrefix() {
		return KIVI_CARE_PREFIX;
	}

	protected function getSetupSteps() {
	    return KIVI_CARE_PREFIX . 'setup_steps';
    }

	protected function getClinicAdminRole() {
		return KIVI_CARE_PREFIX . "clinic_admin";
	}

	protected function getDoctorRole() {
		return KIVI_CARE_PREFIX . "doctor";
	}

	protected function getPatientRole() {
		return KIVI_CARE_PREFIX . "patient";
	}

	protected function teleMedAddOnName() {

		if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$plugins = get_plugins();
		
        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-telemed-addon') {
                return $key ;
            }
        }
		return 'kivicare-telemed-addon/kivicare-telemed-addon.php';
	}
	protected function kiviCareProOnName() {
        $plugins = get_plugins();
        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-clinic-&-patient-management-system-pro') {
                return $key ;
            }
        }
	}
    protected function isTeleMedActive () {

		if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
        foreach ($plugins as $key => $value) {

			if($value['TextDomain'] === 'kiviCare-telemed-addon') {
	
				return (is_plugin_active($key) ? true : false);
	
			}
		}
		
        return false ;
	}
	protected function isKiviProActive () {
		$plugins = get_plugins();
        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-clinic-&-patient-management-system-pro') {
                return true ;
            }
        }
        return false ;
	}
	protected function isWooCommerceActive () {

		if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		
        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'woocommerce') {
                return (is_plugin_active($key) ? true : false);
            }
        }
        return false ;
	}

	protected function isPaymentAvailable() {
		return true;
	}

    protected function isWoocommerceIsEnabled() {
        return class_exists( 'WooCommerce', false );
    }

	protected function getPluginVersion () {
        return KIVI_CARE_VERSION;
    }

	protected function getReceptionistRole() {
		return KIVI_CARE_PREFIX . "receptionist";
	}

	protected function getSetupConfig() {
		return KIVI_CARE_PREFIX . 'setup_config';
	}

	protected function getPluginPrefix() {
		return $this->pluginPrefix;
	}

	protected function getLoginUserRole() {

		$user_id = get_current_user_id();

		$userObj = new WP_User($user_id);

		return $userObj->roles[0];

	}
	protected function getAllActivePlugin(){
		$activePlugins = get_option('active_plugins');

		$activated_plugins=array();
		foreach (get_plugins() as $key => $value) {
			if($value['TextDomain'] === 'kiviCare-clinic-&-patient-management-system-pro'){
				if(in_array($key,$activePlugins)){
					$activated_plugins = array(
						'text-domain'=> $key
					);
				}
			}
		}
		
		if($activated_plugins){
			return $activated_plugins['text-domain'];
		}else{
			return [];
		}
	}
    protected function doctor_enable_calender($doctor_id, $verify_token = false){
        $enable = get_user_meta($doctor_id,'google_cal_access_token');
        if(count($enable) > 0){
            return true;
        }else{
            return false;
        }
    }

}

