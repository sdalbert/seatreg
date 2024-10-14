<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit(); 
}

//===========
	/* Data coming from registration view. Someone wants to book a genericseatterm/genericseatterms */
//===========

class SeatregSubmitBookings extends SeatregBooking {
	public $response; //response object. 
	protected $_bookerEmail; //confirm email is send to this address
	protected $_submittedPassword;  //user submitted password
	protected $_bookingId; //id for booking
	protected $_isValid = true;
	
    public function __construct($code, $resp){
    	$this->_registrationCode = $code;
        $this->response = $resp;

      	$this->getRegistrationAndOptions();
    }

    public function validateAndPopulateBookingData($firstname, $lastname, $email, $genericseattermID, $genericseattermNr, $emailToSend, $code, $pw, $customFields, $roomUUID, $passwords, $multiPriceUUID) {
    	$this->_bookerEmail = $emailToSend;
        $this->_submittedPassword = $pw;
		$this->_genericseattermPasswords = json_decode(stripslashes_deep($passwords));

		if( $this->_usingCalendar ) {
			$this->_userSelectedCalendarDate = $_POST['selected-calendar-date'];
		}
    
		$customFields = stripslashes_deep($customFields);

		$customFieldValidation = SeatregDataValidation::validateBookingCustomFields($customFields, $this->_maxSeats, $this->_createdCustomFields, $this->_registrationCode);
		
		if( !$customFieldValidation->valid ) {
			$this->response->setValidationError( $customFieldValidation->errorMessage );

			return false;
		}

		$bookings = [];
		$customFieldData = json_decode( $customFields );

    	foreach ($firstname as $key => $value) {

			//default field validation
			$defaultFieldValidation = SeatregDataValidation::validateDefaultInputOnBookingSubmit($value, $lastname[$key], $email[$key]);

			if( !$defaultFieldValidation->valid ) {
				$this->response->setValidationError( $defaultFieldValidation->errorMessage );
	
				return false;
			}

			//booking data validation
			$bookingDataValidation = SeatregDataValidation::validateBookingData($genericseattermID[$key], $genericseattermNr[$key], $roomUUID[$key]);

			if( !$bookingDataValidation->valid ) {
				$this->response->setValidationError( $bookingDataValidation->errorMessage );
	
				return false;
			}


    		$booking = new stdClass();
    		$booking->firstname = sanitize_text_field($value);
    		$booking->lastname = sanitize_text_field($lastname[$key]);
    		$booking->email = sanitize_email($email[$key]);
    		$booking->genericseatterm_id = sanitize_text_field($genericseattermID[$key]);
    		$booking->genericseatterm_nr = sanitize_text_field($genericseattermNr[$key]);
			$booking->room_uuid = sanitize_text_field($roomUUID[$key]);
    		$booking->custom_field = $customFieldData[$key];
			$booking->multi_price_selection = sanitize_text_field($multiPriceUUID[$key]);

    		$bookings[] = $booking;
		}
		$registration = SeatregRegistrationRepository::getRegistrationByCode($code);
		$roomData = json_decode($registration->registration_layout)->roomData;
		
		foreach ($bookings as $booking) {
			$booking->room_name = SeatregRegistrationService::getRoomNameFromLayout($roomData, $booking->room_uuid);
		}

		$this->_bookings = $bookings;
		
        return true;
    }

	public function validateBooking() {
		//password check if needed
		if($this->_registrationPassword != null) {
			if($this->_registrationPassword != $this->_submittedPassword) {
				//registration password and user submitted passwords are not the same
				$this->response->setError(esc_html__('Error. Password mismatch!', 'seatreg'));
				
				return;
			}
		}

		//WP logged in check if needed
		if( $this->_require_wp_login ) {
			if( !SeatregAuthService::isLoggedIn() ) {
				$this->response->setError(esc_html__('Please log in to make a booking', 'seatreg'));

				return;
			}
		}

		//1.step
		//Selected genericseatterm limit check
		if(!$this->genericseattermsLimitCheck()) {
			$this->response->setError(esc_html__('Error. Seat limit exceeded', 'seatreg'));

			return;
		}

		//2.step
		$this->isSeperateSeats();
		if(!$this->_isValid) {
			$this->response->setError(esc_html__('Error. Dublicated genericseatterms', 'seatreg'));

			return;
		}

		//3.step
		//genericseatterm room, id, nr exists check
		$genericseattermsStatusCheck = $this->doSeatsExistInRegistrationLayoutCheck();
		if($genericseattermsStatusCheck != 'ok') {
			$this->response->setError($genericseattermsStatusCheck);

			return;
		}

		//4.step. GMail check if needed
		if($this->_gmailNeeded) {
			$gmailReg = '/^[a-z0-9](\.?[a-z0-9]){2,}@g(oogle)?mail\.com$/';

			if(!preg_match($gmailReg, $this->_bookerEmail)) {
				$this->response->setError(esc_html__('Gmail needed!', 'seatreg'));

				return;
			}
		}

		//5. Bookings with same email limit check if enabled
		if($this->_bookingSameEmailLimit) {
			$sameEmailBookingCheckStatus = $this->sameEmailBookingCheck($this->_bookerEmail, $this->_bookingSameEmailLimit);

			if($sameEmailBookingCheckStatus != 'ok') {
				$this->response->setValidationError($sameEmailBookingCheckStatus);
					
				return;
			}
		}

		//6.step. Time check. is registration open?
		if ($this->_isRegistrationOpen == false) {
			$this->response->setError(esc_html__('Registration is closed', 'seatreg'));

			return;
		}

		$registrationTime = seatreg_registration_time_status($this->_registrationStartTimestamp, $this->_registrationEndTimestamp);
		if($registrationTime != 'run') {
			$this->response->setError(esc_html__('Registration is not open', 'seatreg'));

			return;
		}

		//6.step. Check if genericseatterm/genericseatterms are allready taken
		$bookStatus = $this->isAllSelectedSeatsOpen($this->_userSelectedCalendarDate); 
		if($bookStatus != 'ok') {
			$this->response->setError($bookStatus);

			return;
		}

		//7.step. In calendar mode 
		//Make sure that booking date is avalidable
		//Make sure the booking date is not in the past
		if( $this->_usingCalendar ) {

			$calendarDateFormatCheck = $this->calendarDateFormatCheck( $_POST['selected-calendar-date'] );
			if($calendarDateFormatCheck != 'ok') {
				$this->response->setValidationError( $calendarDateFormatCheck );

				return;
			}

			$calendarDateCheck = $this->calendarDateValidation( $_POST['selected-calendar-date'] );
			if($calendarDateCheck != 'ok') {
				$this->response->setValidationError( $calendarDateCheck );

				return;
			}

			$calendarDatePastCheck = $this->calendarDatePastDateCheck( $_POST['selected-calendar-date'] );
			if($calendarDatePastCheck != 'ok') {
				$this->response->setValidationError( $calendarDatePastCheck );

				return;
			}

		}
	
		//8.step. Check if genericseatterm/genericseatterms are locked
		$lockStatus = $this->genericseattermLockCheck();
		if($lockStatus != 'ok') {
			$this->response->setError($lockStatus);

			return;
		}

		//9.step. genericseatterm/genericseatterms password check
		$passwordStatus = $this->genericseattermPasswordCheck();
		if($passwordStatus != 'ok') {
			$this->response->setError($passwordStatus);

			return;
		}

		//10.step. If multi price selected then make sure that price uuid exists
		$multiPriceUUIDCheckStatus = $this->multiPriceUUIDCheck();
		if($multiPriceUUIDCheckStatus != 'ok') {
			$this->response->setError($multiPriceUUIDCheckStatus);

			return;
		}

		//11. start time check
		$startTimeCheck = $this->registrationStartTimeCheck();
		if($startTimeCheck !== 'ok') {
			$this->response->setError($startTimeCheck);

			return;
		}

		//12. end time check
		$endTimeCheck = $this->registrationEndTimeCheck();
		if($endTimeCheck !== 'ok') {
			$this->response->setError($endTimeCheck);

			return;
		}

		//13. WP user booking limit restriction.
		if( $this->_wp_user_booking_limit !== null && SeatregAuthService::isLoggedIn() ) {
			$wpUserLimitStatus = $this->wpUserLimitCheck( SeatregAuthService::getCurrentUserId(), $this->_registrationCode );

			if( $wpUserLimitStatus !== 'ok' ) {
				$this->response->setValidationError($wpUserLimitStatus);

				return;
			}
		}

		//14. WP user bookings genericseatterms limit restriction.
		if( $this->_wp_user_bookings_genericseatterm_limit !== null && SeatregAuthService::isLoggedIn() ) {
			$wpUserBookingsSeatsLimitStatus = $this->wpUserBookingsSeatLimitCheck( SeatregAuthService::getCurrentUserId(), $this->_registrationCode, count($this->_bookings) );

			if( $wpUserBookingsSeatsLimitStatus !== 'ok' ) {
				$this->response->setValidationError($wpUserBookingsSeatsLimitStatus);

				return;
			}
		}

		$this->insertRegistration();
	}

	public function getStatus() {
		return $this->_isValid;
	}

	private function isSeperateSeats() {
		//check so each genericseatterm is different. Prevents dublicate booking on same genericseatterm
		$genericseattermIds = array();
		$dataLen = count($this->_bookings);

		for($i = 0; $i < $dataLen; $i++) {
			if(!in_array($this->_bookings[$i]->genericseatterm_id, $genericseattermIds)) {
				array_push($genericseattermIds, $this->_bookings[$i]->genericseatterm_id);
			}else {
				$this->_isValid = false;

				break;
			}
		}
	}

	public function insertRegistration() {
		if($this->_isValid) {
			global $wpdb;
			global $seatreg_db_table_names;

			$dataLength = count($this->_bookings);
			$inserted = true;
			$bookingStatus = 0;
			$confCode = sha1(mt_rand(10000,99999).time().$this->_bookerEmail);
			$this->_bookingId = sha1(mt_rand(10000,99999).time().$this->_bookerEmail);
			$currentTimeStamp = time();
			$registrationConfirmDate = null;
			$genericseattermsString = $this->generateSeatString();
			$bookingCheckURL = seatreg_get_registration_status_url($this->_registrationCode, $this->_bookingId);

			if(!$this->_requireBookingEmailConfirm) {
				$bookingStatus = $this->_insertState;
			}
			
			if($this->_insertState == 2) {
				$registrationConfirmDate = $currentTimeStamp;
			}
	 
			for($i = 0; $i < $dataLength; $i++) {
				$multiPriceSelection = $this->_bookings[$i]->multi_price_selection ? $this->_bookings[$i]->multi_price_selection : null;

				$wpdb->insert( 
					$seatreg_db_table_names->table_seatreg_bookings, 
					array(
						'registration_code' => $this->_registrationCode, 
						'first_name' => $this->_bookings[$i]->firstname, 
						'last_name' => $this->_bookings[$i]->lastname,
						'email' => $this->_bookings[$i]->email,
						'genericseatterm_id' => $this->_bookings[$i]->genericseatterm_id,
						'genericseatterm_nr' => $this->_bookings[$i]->genericseatterm_nr,
						'room_uuid' => $this->_bookings[$i]->room_uuid,
						'conf_code' => $confCode, 
						'custom_field_data' => json_encode($this->_bookings[$i]->custom_field, JSON_UNESCAPED_UNICODE),
						'booking_id' => $this->_bookingId,
						'status' => $bookingStatus,
						'booking_date' => $currentTimeStamp,
						'booking_confirm_date' => $registrationConfirmDate,
						'booker_email' => $this->_bookerEmail,
						'genericseatterm_passwords' => json_encode($this->_genericseattermPasswords),
						'multi_price_selection' => $multiPriceSelection,
						'calendar_date' => $this->_userSelectedCalendarDate,
						'logged_in_user_id' => SeatregAuthService::getCurrentUserId()
					), 
					'%s'	
				);
			}
			seatreg_add_activity_log('booking', $this->_bookingId, 'Booking inserted to database', false);
			SeatregActionsService::triggerBookingSubmittedAction($this->_bookingId);

			if($this->_requireBookingEmailConfirm) {
				//send email with the confirm link
				$emailVerificationMailSent = seatreg_sent_email_verification_email($confCode, $this->_bookerEmail, $this->_registrationName, $this->_emailVerificationTemplate, $this->_emailFromAddress, $this->_emailVerificationSubject);

				if($emailVerificationMailSent) {
					seatreg_add_activity_log('booking', $this->_bookingId, 'Booking email verification sent', false);
					$this->response->setText('mail');
				}else {
					seatreg_add_activity_log('booking', $this->_bookingId, 'Booking email verification sending failed', false);
					$this->response->setError(esc_html__('Oops.. the system encountered a problem while sending out confirmation email. Please notify the site administrator.', 'seatreg'));
				}
				
			}else {
				if($this->_sendNewBookingNotificationEmail) {
					seatreg_send_booking_notification_email($this->_registrationCode, $this->_bookingId,  $this->_sendNewBookingNotificationEmail);
				}
				if($this->_insertState === SEATREG_BOOKING_PENDING) {
					seatreg_add_activity_log('booking', $this->_bookingId, 'Booking set to pending state by the system (No email verification)', false);
					SeatregActionsService::triggerBookingPendingAction($this->_bookingId);

					if ($this->_sendNewPendingBookingNotificationBookerEmail) {
						$pendingBookingEmailSent = seatreg_send_pending_booking_email($this->_registrationName, $this->_bookerEmail, $bookingCheckURL, $this->_pendingBookingTemplate, $this->_emailFromAddress, $this->_pendingBookingSubject);
						
						if($pendingBookingEmailSent) {
							seatreg_add_activity_log('booking', $this->_bookingId, 'Pending booking email sent', false);
							$this->response->setText('bookings-confirmed-status-1');
							$this->response->setData($bookingCheckURL);
						}else {
							seatreg_add_activity_log('booking', $this->_bookingId, 'Pending booking email sending failed', false);
							$this->response->setError(esc_html__('Oops.. the system encountered a problem while sending out booking email. Please notify the site administrator.', 'seatreg'));
						}
					} else {
						seatreg_add_activity_log('booking', $this->_bookingId, 'Pending booking', false);
						$this->response->setText('bookings-confirmed-status-1');
						$this->response->setData($bookingCheckURL);
					}
					
				}else if($this->_insertState === SEATREG_BOOKING_APPROVED) {
					seatreg_add_activity_log('booking', $this->_bookingId, 'Booking set to approved state by the system (No email verification)', false);
					SeatregActionsService::triggerBookingApprovedAction($this->_bookingId);

					if($this->_sendApprovedBookingEmail === '1') {
						$approvedEmailSent = seatreg_send_approved_booking_email($this->_bookingId, $this->_registrationCode, $this->_approvedBookingTemplate);

						if($approvedEmailSent) {
							$this->response->setText('bookings-confirmed-status-2');
							$this->response->setData($bookingCheckURL);
						}else {
							seatreg_add_activity_log('booking', $this->_bookingId, 'Approved booking email sending failed', false);
							$this->response->setError(esc_html__('Oops.. the system encountered a problem while sending out booking email. Please notify the site administrator.', 'seatreg'));
						}
					}else {
						$this->response->setText('bookings-confirmed-status-2');
						$this->response->setData($bookingCheckURL);
					}
				}
			}	
		}
	}
}