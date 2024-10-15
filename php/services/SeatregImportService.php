<?php

class SeatregImportService {
    private $seatregCode;
    private $registrationData;
    private $roomData;
    private $existingBookings;
    private $failedImports = array();
    private $successfulImports = array();
    private $importCount = 0;

    public function __construct($code) {
        $this->seatregCode = $code;
        $this->registrationData = SeatregRegistrationRepository::getRegistrationByCode($this->seatregCode);
        $this->roomData = json_decode($this->registrationData->registration_layout)->roomData;
        $this->existingBookings = SeatregBookingRepository::getAllConfirmedAndApprovedBookingsByRegistrationCode($this->seatregCode);
    }

    private function validateData($bookingData) {
        $validation = (object) [
            'is_valid' => true,
            'messages' => array()
        ];
        
        $roomName = SeatregRegistrationService::getRoomNameFromLayout($this->roomData, $bookingData->room_uuid);

        if($roomName == null) {
            $validation->is_valid = false;
            $validation->messages[] = 'Invalid room UUID';

            return $validation;
        }

        $genericseattermAndRoomValidation = SeatregLayoutService::validateRoomAndSeatId($this->roomData, $roomName, $bookingData->genericseatterm_id, $bookingData->genericseatterm_nr);
        if( !$genericseattermAndRoomValidation->valid ) {
            $validation->is_valid = false;
            $validation->messages[] = $genericseattermAndRoomValidation->errorText;
        }

        $genericseattermBookedValidation = SeatregBookingService::checkIfSeatAlreadyBooked($bookingData->genericseatterm_id, $bookingData->genericseatterm_nr, $this->existingBookings);
        if( !$genericseattermBookedValidation->is_valid ) {
            $validation->is_valid = false;
            $validation->messages = array_merge($validation->messages, $genericseattermBookedValidation->messages);
        }

        return $validation;
    }

    private function insertData($bookingData) {
        return seatreg_add_booking(
            $bookingData->first_name,
            $bookingData->last_name,
            $bookingData->email,
            json_decode($bookingData->custom_field_data),
            $bookingData->genericseatterm_nr,
            $bookingData->genericseatterm_id,
            $bookingData->room_uuid,
            $this->seatregCode,
            $bookingData->status,
            $bookingData->booking_id,
            SeatregRandomGenerator::generateRandom($bookingData->email),
            null,
            $bookingData->multi_price_selection
        );
    }

    public function importBookings($importedBookingsData) {
        $importedBookings = json_decode(stripslashes($importedBookingsData));
        $this->importCount = count($importedBookings);

        foreach( $importedBookings as $importedBooking ) {
            try {
                $validation = $this->validateData($importedBooking );

                if( $validation->is_valid ) {
                    $inserted = $this->insertData($importedBooking);

                    if( $inserted ) {
                        $this->successfulImports[] = (object)['bookingData' => $importedBooking, 'is_valid' => true, 'messages' => []]; 
                    }else {
                        $this->failedImports[] = (object)['bookingData' => $importedBooking, 'is_valid' => false, 'messages' => ['Failed to insert booking']]; 
                    }
                }else {
                    $this->failedImports[] = (object)['bookingData' => $importedBooking, 'is_valid' => false, 'messages' => $validation->messages]; 
                }
            }catch(Exception $e) {
                $this->failedImports[] = $importedBooking;
            }
        }

        $importFullySUccess = $this->importCount === count($this->successfulImports);

        return (object) [
            'success' => $importFullySUccess,
            'successfulImports' => $this->successfulImports,
            'failedImports' => $this->failedImports
        ];

    }
}