<?php

namespace App\baseClasses;

class WidgetHandler extends KCBase {

	public function init() {
        add_shortcode('bookAppointment', [$this, 'bookAppointmentWidget']);
        add_shortcode('patientDashboard', [$this, 'patientDashboardWidget']);
    }

    public function bookAppointmentWidget () {
        if(is_user_logged_in()) {
            $user_id = get_current_user_id();
        } else {
            $user_id = 0;
        }
        ob_start();
        echo "<div id='app' class='kivi-care-appointment-booking-container kivi-widget' ><book-appointment-widget v-bind:user_id='$user_id' ></book-appointment-widget></div>";
        return ob_get_clean();
    }

    public function patientDashboardWidget () {
        ob_start();
        echo "<div id='app' class='kivi-care-patient-dashboard-container kivi-widget' ><patient-dashboard-widget></patient-dashboard-widget></div>";
        return ob_get_clean();
    }

}


