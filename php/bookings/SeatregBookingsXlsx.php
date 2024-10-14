<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}
require_once( SEATREG_PLUGIN_FOLDER_DIR . 'php/bookings/SeatregBookingsFile.php' );
require_once( SEATREG_PLUGIN_FOLDER_DIR . 'php/libs/xlsxwriter.class.php' );

class SeatregBookingsXlsx extends SeatregBookingsFile {
    public function __construct($showPending, $showConfirmed, $separateFirstandLastName, $registrationCode, $calendarDate) {
        parent::__construct($showPending, $showConfirmed, $separateFirstandLastName, $registrationCode, $calendarDate);

        $this->setupXlsx();
	}

    private function customFieldWithValueXlsx($customField, $customData) {
        $customData = is_array($customData) ? $customData : [];
        $foundIt = false;
        $string = '';
        
        foreach ($customData as $custom) {
            $dataLabel = trim($custom['label']);

            if($dataLabel == $customField['label']) {

                if($customField['type'] === 'check') {
                    if($custom['value'] === '1') {
                        $string = esc_html__('Checked', 'seatreg');
                    }else if($custom['value'] === '0') {
                        $string = esc_html__('Unchecked', 'seatreg');
                    }
                }else {
                    $string .= esc_html($custom['value']);
                }
    
                $foundIt = true;

                break;
            }
        }
    
        if(!$foundIt) {
            $string = esc_html__('not set', 'seatreg');
        }
    
        return $string;
    }

    private function setupXlsx() {
        $filename =  esc_html($this->_registrationName) . ' ' . $this->getFileName() . ".xlsx";
        header('Content-disposition: attachment; filename="'.XLSXWriter::sanitize_filename($filename).'"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        $this->printXlsx();
    }
    
    public function printXlsx() {
        $placeNumberText = $this->_usingSeats ? esc_html__('Seat number', 'seatreg') : esc_html__('Place number', 'seatreg');
        
        $nameHeader = $this->_separateFirstandLastName
            ? array(
                esc_html__('First name', 'seatreg') => 'string',
                esc_html__('Last name', 'seatreg') => 'string',
              )
            : array(
                esc_html__('Name', 'seatreg') => 'string',
              );

        $header = array_merge(
            array(
                $placeNumberText => 'string',
                esc_html__('Room name', 'seatreg') => 'string',
            ),
            $nameHeader,
            array(
                esc_html__('Email', 'seatreg') => 'string',
                esc_html__('Registration date', 'seatreg') => 'string',
                esc_html__('Calendar date', 'seatreg') => 'string',
                esc_html__('Booking id', 'seatreg') => 'string',
                esc_html__('Booking status', 'seatreg') => 'string',
                esc_html__('Booking approval date', 'seatreg') => 'string',
                esc_html__('Payment status', 'seatreg') => 'string',
                esc_html__('Price', 'seatreg') => 'string',
            )
        );

        $customFieldsLength = count($this->_customFields);
        $data = array();

        foreach ($this->_registrations as $registration) {
            $registrantCustomData = json_decode($registration->custom_field_data, true);
            $status = $this->getStatus($registration->status);
            $bookingDate = $this->getBookingDate($registration->booking_date);
            $seatPrice = SeatregLayoutService::getSeatPriceFromLayout($registration, $this->_roomData);
            $priceDescription = $seatPrice->description ? '('. $seatPrice->description . ')' : '';
            $priceText = $seatPrice->price . ' ' . $this->_registrationInfo->paypal_currency_code .  ' ' . $priceDescription;

            $nameField = $this->_separateFirstandLastName 
                ? array(esc_html($registration->first_name), esc_html($registration->last_name))
                : array(esc_html($registration->first_name) . ' ' . esc_html($registration->last_name));

            $registrationData = array_merge(
                array(
                    esc_html($registration->seat_nr),
                    esc_html($registration->room_name),
                ),
                $nameField,
                array(
                    esc_html($registration->email),
                    $bookingDate,
                    esc_html($this->_calendarDate),
                    $registration->booking_id,
                    $status,
                )
            );

            if($status === "Approved") {
                $confirmDate = $this->getBookingDate($registration->booking_confirm_date);
                $registrationData[] = $confirmDate;
            }else {
                $registrationData[] = '';
            }

            if($registration->payment_status != null) {
                $registrationData[] = $registration->payment_status;
            }else {
                $registrationData[] = '';
            }

            $registrationData[] = $priceText;
        
            foreach ($this->_customFields as $customField) {
                $header[$customField['label']] = 'string';
                $registrationData[] = $this->customFieldWithValueXlsx($customField, $registrantCustomData);
            }
            $data[] = $registrationData;
        }

        $writer = new XLSXWriter();
        $writer->setAuthor('SeatReg WordPress');
        $writer->writeSheet($data,'Sheet1',$header);
        $writer->writeToStdOut();

        die();
    }
}