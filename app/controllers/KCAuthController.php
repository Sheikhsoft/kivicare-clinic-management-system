<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use Exception;
use WP_User;

class KCAuthController extends KCBase
{


    public $db;

    private $request;

    public function __construct()
    {

        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();
    }

    public function loginPatientDetail()
    {

        if (is_user_logged_in()) {

            $patient_id = get_current_user_id();

            $user = get_userdata($patient_id);
            $image_attachment_id = get_user_meta($patient_id, 'patient_profile_image', true);
            $user_image_url = wp_get_attachment_url($image_attachment_id);
            unset($user->user_pass);

            $full_name = explode(' ', $user->display_name);

            $user_data  = get_user_meta($patient_id, 'basic_data', true);
            $first_name = isset($full_name[0]) ? $full_name[0] : "";
            $last_name  = isset($full_name[1]) ? $full_name[1] : "";

            $data             = (object) array_merge((array) $user->data, (array) json_decode($user_data));
            $data->first_name = $first_name;
            $data->username   = $data->user_login;
            $data->last_name  = $last_name;
            $data->user_profile = $user_image_url;
            if ($data) {
                echo json_encode([
                    'status'  => true,
                    'message' => esc_html__('Patient profile data', 'kc-lang'),
                    'data'    => $data
                ]);
            } else {
                throw new Exception(esc_html__('Data not found', 'kc-lang'), 400);
            }
        } else {

            echo json_encode([
                'status'  => false,
                'data' => '',
                'message'    => esc_html__('Patient not logged in', 'kc-lang')
            ]);

            wp_die();
        }
    }

    public function patientLogin()
    {

        $parameters = $this->request->getInputs();

        try {

            $errors = kcValidateRequest([
                'username' => 'required',

            ], $parameters);


            if (count($errors)) {
                throw new Exception($errors[0], 422);
            }

            $auth_success = wp_authenticate($parameters['username'], $parameters['username']);

            if (isset($auth_success->errors) && count($auth_success->errors)) {
                throw new Exception(esc_html__('Incorrect username and password', 'kc-lang'), 401);
            }

            $user_meta = get_userdata($auth_success->data->ID);

            if ($this->getPatientRole() !== $user_meta->roles[0]) {
                echo json_encode([
                    'status'  => false,
                    'message' => esc_html__('User not found user must be a patient.', 'kc-lang'),
                ]);
                wp_die();
            }

            wp_set_current_user($auth_success->data->ID, $auth_success->data->user_login);
            wp_set_auth_cookie($auth_success->data->ID);
            do_action('wp_login', $auth_success->data->user_login, $auth_success);

            echo json_encode([
                'status'  => true,
                'message' => esc_html__('Logged in successfully', 'kc-lang'),
                'data'    => $auth_success
            ]);
        } catch (Exception $e) {

            $code    = esc_html__($e->getCode(), 'kc-lang');
            $message = esc_html__($e->getMessage(), 'kc-lang');

            header("Status: $code $message");

            echo json_encode([
                'status'  => false,
                'message' => $message
            ]);
        }
    }

    public function patientRegister()
    {

        $parameters = $this->request->getInputs();
        $email = $parameters['mobile_number'] . "@bestaidbd.com";

        $isEmailExist = email_exists($email);

        if ($isEmailExist) {
            $status = false;
            echo json_encode([
                'status'  => (bool) $status,
                'message' => esc_html__("user is already exist", 'kc-lang')
            ]);
            wp_die();
        }

        try {

            $temp = ['mobile_number'  => $parameters['mobile_number']];

            $username = $parameters['mobile_number'];

            $password = $parameters['mobile_number'];


            $user = wp_create_user($username, $password, $email);

            $u               = new WP_User($user);

            $u->display_name = $parameters['first_name'] . ' ' . $parameters['last_name'];

            wp_insert_user($u);

            $u->set_role($this->getPatientRole());

            update_user_meta($user, 'basic_data', json_encode($temp));

            if ($user) {

                $user_email_param = array(
                    'username'            => $parameters['mobile_number'],
                    'user_email'          => $email,
                    'password'            => $password,
                    'email_template_type' => 'patient_register'
                );

                kcSendEmail($user_email_param);
            }

            if ($user) {
                $status = true;
                $message = esc_html__("Patient registration successfully.", 'kc-lang');
            } else {
                $status = false;
                $message = esc_html__("Patient registration not successfully.", 'kc-lang');
            }

            echo json_encode([
                'status'  => (bool) $status,
                'message' => $message
            ]);
        } catch (Exception $e) {

            $code    = esc_html__($e->getCode(), 'kc-lang');
            $message = esc_html__($e->getMessage(), 'kc-lang');

            header("Status: $code $message");

            echo json_encode([
                'status'  => false,
                'message' => $message
            ]);
        }
    }

    public function changePassword()
    {

        $request_data = $this->request->getInputs();

        $current_user = wp_get_current_user();

        $result = wp_check_password($request_data['currentPassword'], $current_user->user_pass, $current_user->ID);

        if ($result) {

            if (isset($current_user->ID) && $current_user->ID !== null && $current_user->ID !== '') {
                wp_set_password($request_data['newPassword'], $current_user->ID);
                $status = true;
                $message = 'Password successfully changed';
                wp_logout();
            } else {
                $status = false;
                $message = 'Password change failed.';
            }
        } else {

            $status = false;
            $message = 'Current password is wrong!!';
        }

        echo json_encode([
            'status'  => $status,
            'data' => $result,
            'message' => esc_html__($message, 'kc-lang'),
        ]);
    }

    public function logout()
    {

        wp_logout();
        echo json_encode([
            'status' => true,
            'message' => esc_html__('Logout successfully.', 'kc-lang'),
        ]);
    }
}
