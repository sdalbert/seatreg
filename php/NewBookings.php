<?php

//===========
	/*data coming from registration. Someone wants to book a seat*/
//===========

require_once('Booking.php');

class NewBookings extends Booking {
	public $response; //response object. 
	protected $_isValid = true;
	protected $_bookerEmail; //confirm email is send to this address
	protected $_bookings; //seat bookings 
	protected $_registrationLayout;
	protected $_registrationCode;
	protected $_gmailNeeded = false;  //require gmail address from registrants
	protected $_registrationStartTimestamp;
	protected $_registrationEndTimestamp; //when registration ends
	protected $_submittedPassword;  //user submitted password
	protected $_registrationPassword = null;  //registration password if set. null default
	protected $_isRegistrationOpen = true; //is registration open
	protected $_bookingId; //id for booking
	protected $_maxSeats = 1;  //how many seats per booking can be booked
	protected $_sendNewBookingNotification = false; //send notification to registration owner that someone has booked a seat
	
    public function __construct( $code, $resp){
    	$this->_registrationCode = $code;
        $this->response = $resp;

      	$this->getRegistrationAndOptions();
    }

    public function validateBookingData($firstname, $lastname, $email, $seatID, $seatNr, $seatRoom, $emailToSend, $code, $pw, $customFields) {
    	$this->_bookerEmail = $emailToSend;
        $this->_submittedPassword = $pw;
    
		$customFields = stripslashes_deep($customFields);
		
		if( !$this->seatreg_validate_custom_fields( $customFields ) ) {
			$this->response->setError( __('Custom field validation error','seatreg') );
			return false;
		}

		$bookings = [];
		$customFieldData = json_decode( $customFields );

    	foreach ($firstname as $key => $value) {
    		$booking = new stdClass();
    		$booking->firstname = $value;
    		$booking->lastname = $lastname[$key];
    		$booking->email = $email[$key];
    		$booking->seat_id = $seatID[$key];
    		$booking->seat_nr = $seatNr[$key];
    		$booking->room_name = $seatRoom[$key];
    		$booking->custom_field = $customFieldData[$key];

    		$bookings[] = $booking;
    	}
        $this->_bookings = $bookings;

        return true;
    }

    public function seatreg_validate_custom_fields($customFields) {
		$customFieldsReg = '/^\[(\[({"label":[0-9a-zA-ZÜÕÖÄüõöä\s@."]{1,102},"value":[0-9a-zA-ZÜÕÖÄüõöä\s@."-]{1,52}},?){0,6}\],?){1,3}\]$/';

		if( !preg_match($customFieldsReg, $customFields) ) {
			return false;
		}

		return true;
	}
	public function validateBooking() {

		//1.step
		$this->isSeperateSeats();

		if(!$this->_isValid) {
			$this->response->setError('Error. Dublicated seats');
			return;
		}

		//password check if needed
		if($this->_registrationPassword != null) {
			if($this->_registrationPassword != $this->_submittedPassword) {
				//registration password and user submitted passwords are not the same
				$this->response->setError('Error. Password mismatch!');
				return;
			}
		}

		//2.step
		//seat room, id, nr and is availvable check.
		$seatsStatusCheck = $this->doSeatsExistInRegistrationLayoutCheck();
		if($seatsStatusCheck != 'ok') {
			$this->response->setError($seatsStatusCheck);
			return;
		}

		//3.step. Email check if needed
		if($this->_gmailNeeded) {
			$gmailReg = '/^[a-z0-9](\.?[a-z0-9]){2,}@g(oogle)?mail\.com$/';

			if(!preg_match($gmailReg, $this->_bookerEmail)) {
				$this->response->setError('Gmail needed!');
				return;
			}
		}

		//4.step. Time check. is registration open.
		if ($this->_isRegistrationOpen == false) {
			$this->response->setError('Registration is closed');
			return;
		}

		$registrationTime = $this->registrationTimeStatus($this->_registrationStartTimestamp, $this->_registrationEndTimestamp);
		if($registrationTime != 'run') {
			$this->response->setError('Registration is not open (time)');
			return;
		}

		//5.step. Check if seat/seats are allready taken
		$bookStatus = $this->isAllSelectedSeatsOpen(); 
		if($bookStatus != 'ok') {
			$this->response->setError($bookStatus);
			return;
		}

		$this->insertRegistration();
	}

	public function getStatus() {
		return $this->_isValid;
	}

	private function isSeperateSeats() {
		//check so each seat is different. Prevents dublicate booking on same seat
		$seatIds = array();
		$dataLen = count($this->_bookings);

		for($i = 0; $i < $dataLen; $i++) {
			if(!in_array($this->_bookings[$i]->seat_id, $seatIds)) {
				//print_r($seatIds);
				//echo $this->_data[$i][0], ' not in array. insert it--------';
				array_push($seatIds, $this->_bookings[$i]->seat_id);
			}else {
				//echo $this->_data[$i][0], ' is already in. return false--------';
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
			$registrationConfirmDate = null;

			if(!$this->_requireBookingEmailConfirm) {
				$bookingStatus = $this->_insertState;
				$registrationConfirmDate = current_time( 'mysql' );
			}
	 
			for($i = 0; $i < $dataLength; $i++) {
				$wpdb->insert( 
					$seatreg_db_table_names->table_seatreg_bookings, 
					array(
						'seatreg_code' => $this->_registrationCode, 
						'first_name' => $this->_bookings[$i]->firstname, 
						'last_name' => $this->_bookings[$i]->lastname,
						'email' => $this->_bookings[$i]->email,
						'seat_id' => $this->_bookings[$i]->seat_id,
						'seat_nr' => $this->_bookings[$i]->seat_nr,
						'room_name' => $this->_bookings[$i]->room_name,
						'conf_code' => $confCode, 
						'custom_field_data' => json_encode($this->_bookings[$i]->custom_field, JSON_UNESCAPED_UNICODE),
						'booking_id' => $this->_bookingId,
						'status' => $bookingStatus,
						'registration_confirm_date' => $registrationConfirmDate
					), 
					'%s'	
				);
			}

			if($this->_requireBookingEmailConfirm) {
				$this->changeCaptcha(3);
				$seatsString = $this->generateSeatString();
				$confirmationURL = WP_PLUGIN_URL . '/seatreg_wordpress/php/booking_confirm.php?confirmation-code='. $confCode;
				$bookingCheckURL = WP_PLUGIN_URL . '/seatreg_wordpress/php/booking_check.php?registration=' . $this->_registrationCode . '&id=' . $this->_bookingId;

				$message = 'Your selected seats are: <br/><br/>' . $seatsString . '
							<p>Click on the link below to confirm your booking</p>
							<a href="' .  $confirmationURL .'" >'. $confirmationURL .'</a><br/>
							(If you can\'t click then copy and paste it into your web browser)<br/><br/>After confirmation you can look your booking at<br> <a href="'. $bookingCheckURL .'" >'. $bookingCheckURL .'</a>';

				$mailSent = wp_mail($this->_bookerEmail, 'Booking confirmation', $message, array(
					"Content-type: text/html"
				));

				if($mailSent) {
					$this->response->setText('mail');
				}else {
					$this->response->setError('Oops.. the system encountered a problem while sending out confirmation email');
				}
				
			}else {
				$this->response->setText('bookings-confirmed');
			}	
		}

	}

	private function changeCaptcha($length) {		
		$chars = "abcdefghijklmnprstuvwzyx23456789";
		$str = "";
		$i = 0;
		
		while($i < $length){
			$num = rand() % 33;
			$temp = substr($chars, $num, 1);
			$str = $str.$temp;
			$i++;
		}
		
		$_SESSION['captcha'] = $str;
	}	
}