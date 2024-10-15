<div class="modal fade edit-modal" id="edit-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
	  	<h4 class="modal-title" id="myModalLabel"><?php esc_html_e('Edit booking', 'seatreg'); ?></h4>
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only"><?php esc_html_e('Close', 'seatreg'); ?></span></button>
      </div>
      <div class="modal-body">
		<form id="booking-edit-form">

			<?php if($calendarDate): ?>
				<div class="edit-modal-input-wrap">
					<label for="edit-date"><h5><?php esc_html_e('Date', 'seatreg'); ?></h5></label><br>
					<input type="text" id="edit-date" name="edit-date" value="<?php echo esc_html($calendarDate); ?>"/> <span id="edit-date-error"></span>
				</div>
			<?php endif; ?>

			<div class="edit-modal-input-wrap">
				<label for="edit-genericseatterm">
					<h5>
						<?php $usingSeats ? esc_html_e('Seat id', 'seatreg') : esc_html_e('Place id', 'seatreg'); ?>
					</h5>
				</label> 
				<i class="fa fa-question-circle seatreg-ui-tooltip" aria-hidden="true" title="<?php $usingSeats ? esc_html_e('ID can be seen in map-editor when hovering genericseatterms', 'seatreg') : esc_html_e('ID can be seen in map-editor when hovering places', 'seatreg'); ?>"></i>
				<br>
				<input type="text" id="edit-genericseatterm" name="genericseatterm-id" autocomplete="off"/></label> <span id="edit-genericseatterm-error"></span>
			</div>
			
			<div class="edit-modal-input-wrap">
				<label for="edit-room"><h5><?php esc_html_e('Room', 'seatreg'); ?></h5></label><br>
				<input type="text" id="edit-room" name="room" autocomplete="off"/> <span id="edit-room-error"></span>
			</div>

			<div class="edit-modal-input-wrap">
				<label for="edit-fname"><h5><?php esc_html_e('First name', 'seatreg'); ?></h5></label><br>
				<input type="text" id="edit-fname" name="first-name" autocomplete="off"/> <span id="edit-fname-error"></span>
			</div>

			<div class="edit-modal-input-wrap">
				<label for="edit-lname"><h5><?php esc_html_e('Last name', 'seatreg'); ?></h5></label><br>
				<input type="text" id="edit-lname" name="last-name" autocomplete="off"/></label> <span id="edit-lname-error"></span>
			</div>
	        <div class="modal-body-custom"></div>
			<input type="hidden" id="modal-code">
			<input type="hidden" id="booking-id">
			<input type="hidden" id="r-id">
			<input type="hidden" id="edit-booking-genericseatterm-nr">
	     </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php esc_html_e('Close', 'seatreg'); ?></button>
        <button type="button" class="btn btn-primary" id="edit-update-btn"><?php esc_html_e('Save changes', 'seatreg'); ?></button>
      </div>
    </div>
  </div>
</div>