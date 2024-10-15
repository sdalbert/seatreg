<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit(); 
}

class SeatregBookingRepository {
    /**
     *
     * Return bookings by the booking ID that are confirmed or approved.
     *
     * @param string $bookingId The ID of the booking
     * @return  array|object|null
     *
     */
    public static function getBookingsById($bookingId) {
        global $wpdb;
        global $seatreg_db_table_names;

        return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $seatreg_db_table_names->table_seatreg_bookings
			WHERE booking_id = %s
			AND status != 0",
			$bookingId
		) );
    }
    /**
     *
     * Return confirmed and approved bookings by registration code. 
     * If $filterCalendarDate provided then get bookings made with calendar mode
     * If $filterCalendarDate null then get bookings made with normal mode (not calendar mode)
     * 
     *
     * @param string $registrationCode The code of the registration
     * @param string|null $filterCalendarDate Optional. Filter by calendar date
     *
     */
    public static function getConfirmedAndApprovedBookingsByRegistrationCode($registrationCode, $filterCalendarDate = null) {
        if( $filterCalendarDate ) {
            return self::getCalendarModeBookingsAtDate($registrationCode, $filterCalendarDate);
        }else {
            return self::getNormalModeBookings($registrationCode);
        }
    }
    
    /**
     *
     * Return all confirmed and approved bookings made with normal mode. 
     * 
     * @param string $registrationCode The code of the registration
     *
     */
    public static function getNormalModeBookings($registrationCode) {
        global $wpdb;
        global $seatreg_db_table_names;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $seatreg_db_table_names->table_seatreg_bookings
            WHERE registration_code = %s
            AND (status = '1' OR status = '2')
            AND calendar_date IS NULL",
            $registrationCode,
        ) );
    }

    /**
     *
     * Return all confirmed and approved bookings made with calendar mode. 
     * 
     * @param string $registrationCode The code of the registration
     *
     */
    public static function getCalendarModeBookings($registrationCode) {
        global $wpdb;
        global $seatreg_db_table_names;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $seatreg_db_table_names->table_seatreg_bookings
            WHERE registration_code = %s
            AND (status = '1' OR status = '2')
            AND calendar_date IS NOT NULL",
            $registrationCode,
        ) );
    }

    /**
     *
     * Return all confirmed and approved bookings made with calendar mode and in sepcific date. 
     * 
     * @param string $registrationCode The code of the registration
     * @param string $filterCalendarDate. Filter by calendar date
     *
     */
    public static function getCalendarModeBookingsAtDate($registrationCode, $filterCalendarDate) {
        global $wpdb;
        global $seatreg_db_table_names;
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $seatreg_db_table_names->table_seatreg_bookings
            WHERE registration_code = %s
            AND (status = '1' OR status = '2')
            AND calendar_date = %s",
            $registrationCode,
            $filterCalendarDate
        ) );
    }

    /**
     *
     * Return all confirmed and approved bookings made with both calendar mode and normal mode. 
     * 
     * @param string $registrationCode The code of the registration
     *
     */
    public static function getAllConfirmedAndApprovedBookingsByRegistrationCode($registrationCode) {
        global $wpdb;
	    global $seatreg_db_table_names;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $seatreg_db_table_names->table_seatreg_bookings
            WHERE registration_code = %s
            AND (status = '1' OR status = '2')",
            $registrationCode,
        ) );
    } 

    /**
     *
     * Return bookings by conf code.
     *
     * @param string $confCode The conf code of the booking
     *
     */
    public static function getBookingByConfCode($confCode) {
        global $wpdb;
        global $seatreg_db_table_names;
        
        return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $seatreg_db_table_names->table_seatreg_bookings
			WHERE conf_code = %s
			AND status = 0",
			$confCode
		) );
    }

    /**
     *
     * Return bookings by registration code and booking id
     *
     * @param string $registrationCode The code of registration
     * @param string $bookingId The id of booking
     *
     */
    public static function getBookingsByRegistrationCodeAndBookingId($registrationCode, $bookingId) {
        global $wpdb;
        global $seatreg_db_table_names;

        return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $seatreg_db_table_names->table_seatreg_bookings
			WHERE registration_code = %s
			AND booking_id = %s
			AND status != 0",
			$registrationCode,
			$bookingId
		) );
    }

    /**
     *
     * Return data related to booking (registration, registration options)
     *
     * @param string $bookingId The ID of the booking
     * @return array|object|null|void
     *
     */
    public static function getDataRelatedToBooking($bookingId) {
        global $wpdb;
        global $seatreg_db_table_names;

        $data = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*, b.*
            FROM $seatreg_db_table_names->table_seatreg AS a
            INNER JOIN $seatreg_db_table_names->table_seatreg_options AS b
            ON a.registration_code = b.registration_code
            WHERE a.registration_code = (SELECT registration_code FROM $seatreg_db_table_names->table_seatreg_bookings WHERE booking_id = %s LIMIT 1)",
            $bookingId
        ) );

        if($data) {
            $payment = SeatregPaymentRepository::getPaymentByBookingId($bookingId);
    
            if($payment) {
                $data->payment_status = $payment->payment_status;
            }else {
                $data->payment_status = null;
            }
        }

        return $data;
    }

    /**
     *
     * Return registration pending bookings where registration time is older than expiration time. If booking has some payment related entries then dont include it.
     *
     * @param string $registrationCode The registration code
     * @param int $expirationTimeInMinutes The expiration time set in registration settings. In minutes
     * @return (array|object|null)
     *
     */
    public static function getPendingBookingsThatAreExpired($registrationCode, $expirationTimeInMinutes) {
        global $wpdb;
        global $seatreg_db_table_names;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $seatreg_db_table_names->table_seatreg_bookings AS a
            WHERE a.registration_code = %s
            AND a.status = 1
            AND ((UNIX_TIMESTAMP() - a.booking_date) / 60) > %d
            AND (SELECT COUNT(*) FROM $seatreg_db_table_names->table_seatreg_payments AS b WHERE b.booking_id = a.booking_id) = 0",
            $registrationCode,
            $expirationTimeInMinutes
        ) );
    }

    public static function getBookingsCountWithSameEmail($registrationCode, $bookerEmail) {
        global $wpdb;
        global $seatreg_db_table_names;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $seatreg_db_table_names->table_seatreg_bookings
            WHERE registration_code = %s
            AND (status = '1' OR status = '2')
            AND booker_email = %s",
            $registrationCode,
            $bookerEmail
        ) );
    }

    public static function findIfExistingBookingWasMadeWithCustomFieldValue($registrationCode, $assosiatedCustomField, $personCustomField) {
        $bookings = self::getConfirmedAndApprovedBookingsByRegistrationCode($registrationCode);
        $existingUniqueCustomFieldValue = false;

        foreach($bookings as $booking) {
            $bookingCustomFields = json_decode($booking->custom_field_data);

            if($bookingCustomFields) {
                foreach($bookingCustomFields as $bookingCustomField) {
                    if($bookingCustomField->label === $personCustomField->label && $bookingCustomField->value ===  $personCustomField->value) {
                        $existingUniqueCustomFieldValue = true;

                        break 2;
                    }
                }
            }
        }

        return $existingUniqueCustomFieldValue;
    }

    /**
     *
     * Return bookings data to be used on public registration page
     *
     * @param string $registrationCode The registration code
     * @param bool $selectedShowRegistrationData
     * @param string|null $calendarDateFilter Filter bookings by date
     * @return (array|object|null)
     *
     */
    public static function getBookingsForRegistrationPage($registrationCode, $selectedShowRegistrationData, $calendarDateFilter) {
        global $wpdb;
        global $seatreg_db_table_names;
    
        $bookings = [];
        $showNames = in_array('name', $selectedShowRegistrationData);

        if( $calendarDateFilter ) {
            $bookings = $wpdb->get_results( $wpdb->prepare(
                "SELECT genericseatterm_id, room_uuid, status, custom_field_data, CONCAT(first_name, ' ', last_name) AS reg_name 
                FROM $seatreg_db_table_names->table_seatreg_bookings
                WHERE registration_code = %s
                AND (status = '1' OR status = '2')
                AND calendar_date = %s",
                $registrationCode,
                $calendarDateFilter
            ) );
        }else {
            $bookings = $wpdb->get_results( $wpdb->prepare(
                "SELECT genericseatterm_id, room_uuid, status, custom_field_data, CONCAT(first_name, ' ', last_name) AS reg_name 
                FROM $seatreg_db_table_names->table_seatreg_bookings
                WHERE registration_code = %s
                AND (status = '1' OR status = '2')
                AND calendar_date IS NULL",
                $registrationCode,
            ) );
        }
        
        foreach($bookings as $booking ) {
            if( !$showNames ) {
                unset($booking->reg_name);
            }
            if( $selectedShowRegistrationData ) {
                $bookingCustomFieldData = json_decode( $booking->custom_field_data );
                $bookingCustomFieldData = array_filter($bookingCustomFieldData, function($customField) use($selectedShowRegistrationData) {
                    return in_array($customField->label, $selectedShowRegistrationData);
                });
                $booking->custom_field_data = json_encode(array_values($bookingCustomFieldData));
            }else {
                unset($booking->custom_field_data);
            }
        }
    
        return $bookings;
    }

    /**
     *
     * Return bookings count in registration rooms
     *
     * @param string $registrationCode The registration code
     * @param string|null $filterCalendarDate The calendar date filtering
     * @param int $bookingStatus. 
     * @return array of objects with room info about booking count
     *
     */
    public static function getRoomsBookingsInfo($registrationCode, $bookingStatus, $filterCalendarDate) {
        global $wpdb;
	    global $seatreg_db_table_names;

        if( $filterCalendarDate ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT room_uuid, COUNT(id) AS total
                FROM $seatreg_db_table_names->table_seatreg_bookings
                WHERE registration_code = %s
                AND status = %d
                AND calendar_date = %s
                GROUP BY room_uuid",
                $registrationCode,
                $bookingStatus,
                $filterCalendarDate
            ) );
        }else {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT room_uuid, COUNT(id) AS total
                FROM $seatreg_db_table_names->table_seatreg_bookings
                WHERE registration_code = %s
                AND status = %d
                AND calendar_date IS NULL
                GROUP BY room_uuid",
                $registrationCode,
                $bookingStatus
            ) );
        }
    }

    /**
     *
     * Return count of how many bookings a WP user has made
     *
     * @param int $userId The user ID
     * @param string $registrationCode Code of the registration
     * @return int Number of bookings
     *
     */
    public static function getUserBookings($userId, $registrationCode) {
        global $wpdb;
        global $seatreg_db_table_names;

        return (int)$wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $seatreg_db_table_names->table_seatreg_bookings
            WHERE logged_in_user_id = %s
            AND registration_code = %s
            AND (status = %d OR status = %d)",
            $userId,
            $registrationCode,
            SEATREG_BOOKING_APPROVED,
            SEATREG_BOOKING_PENDING
        ) );
    }
}