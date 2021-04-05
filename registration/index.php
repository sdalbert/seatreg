<?php
	if(empty($_GET['c'])){
		exit();
	}
	require_once('../php/util/load_wp.php');
	require_once('./../php/util/registration_time_status.php');
	require_once('php/reg_functions.php');
	require_once('./../php/seatreg_strings.php');

	$data = seatreg_get_options_reg($_GET['c']);

	if(!$data) {
		esc_html_e('Registration not found', 'seatreg');

		exit();
	}

	$showPwdForm = false;

	if($data->registration_password != null ) {
		//view password is set

		if(empty($_POST['reg_pwd'])) {
			//need to ask pwd
			$showPwdForm = true;

		}else if(!empty($_POST['reg_pwd'])) {
			//ok pwd is entered
			if($_POST['reg_pwd'] != $data->registration_password ) {
				$showPwdForm = true;
			}
		}
	}

 	if(!$showPwdForm) {
		$seatsInfo = json_encode( seatreg_stats_for_registration_reg($data->registration_layout, $data->registration_code) );
	
		if($data->show_bookings == '1') {
			$registrations = json_encode(seatreg_get_registration_bookings_reg($_GET['c'], true)); //also names
		}else {
			$registrations = json_encode(seatreg_get_registration_bookings_reg($_GET['c'], false));  //no names
		}

		$registrationTime = seatreg_registration_time_status( $data->registration_start_timestamp,  $data->registration_end_timestamp );
	}

	$manifestFileContents = file_get_contents("../rev-manifest.json");
	$manifest = json_decode($manifestFileContents, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?php echo htmlspecialchars( $data->registration_name ); ?></title>
	<meta name="viewport" content="width=device-width, user-scalable=no">
	<link rel="icon" href="<?php echo get_site_icon_url(); ?>" />
	<link href='//fonts.googleapis.com/css?family=Open+Sans:400,700' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" href="css/<?php echo $manifest['registration.min.css']; ?>">

	<?php if($data->registration_open == 1) : ?>	
		<script src="js/modernizr.custom.89593.min.js"></script>
	<?php else : ?>
		
		<style>
			html, body {
				height: 100%;
			}
			#center-wrap {
				text-align: center;
				height: 100%;
			}
			#center-wrap:before {
				 content: '';
				 display: inline-block;
				 height: 100%;
				 vertical-align: middle;
			}
			#center-wrap .center-header {
				display: inline-block;
	  			vertical-align: middle;
			}
		</style>
	<?php endif; ?>
</head>
<body>
<?php include('noscript.html'); ?>	

	<?php if(!$showPwdForm) : ?>
		<?php if($data->registration_open == 0) : ?>
	    	<div id="center-wrap">
				<h2 class="center-header">
					<?php
						printf(
							/* translators: %s: Name of the registration */
							esc_html__( '%s is closed at the moment', 'seatreg' ),
							esc_html($data->registration_name)
						);
					?>
				</h2>
	    	</div>
		<?php else : ?>

		<?php if($data->registration_layout != null && $data->registration_layout !== '{}'): ?>
			<header id="main-header">
				<?php esc_html_e($data->registration_name); ?>
			</header>
			<div id="room-nav-wrap" class="border-box no-select">
				<div id="room-nav">
					<div id="room-nav-items">
						<div id="room-nav-close" class="close-btn">
							<div class="close-btn-bg"></div>
							<i class="fa fa-times-circle"></i>
						</div>
					</div>
				</div>

				<div id="room-nav-info" class="border-box">
					<div id="room-nav-info-inner"></div>
				</div>

				<div id="room-nav-btn-wrap" class="border-box">
					<div id="current-room-name"></div>
					<div id="room-nav-btn">
						<?php esc_html_e('Change room', 'seatreg'); ?>
					</div>
					<div class="room-nav-extra-info-btn">
						<i class="fa fa-info-circle"></i>
					</div>
				</div>
			</div>
			<?php if($data->info): ?>
				<div class="top-info-bar">
					<?php esc_html_e($data->info); ?>
				</div>
			<?php endif; ?>
			<div id="middle" class="no-select">
				<div id="view-wrap">
					<div id="middle-section">
						<div id="box-wrap">
							<div id="boxes">
					
							</div>
						</div>
					</div>
					<div id="room-is-empty" class="dont-display">
						<p class="room-is-empty-text">
							<?php esc_html_e('Room is empty', 'seatreg'); ?>
						</p>
					</div>		
				</div>

				<div id="legend-wrapper" class="border-box">
					<div id="legends"></div>
				</div>

				<div id="seat-cart" class="border-box no-select">
					<div class="seat-cart-left">
						<div id="cart-text">
							<div class="seats-in-cart">0</div>
							<div><?php esc_html_e('seats selected', 'seatreg'); ?></div> 
							<div class="max-seats">
								(<?php 
									esc_html_e('Max', 'seatreg');
									if($data->seats_at_once > 1) {
										esc_html_e( $data->seats_at_once ); 
									}else {
										esc_html_e( $data->seats_at_once ); 
									}
								?>)
							</div>
						</div>
					</div>

					<div class="seat-cart-right">
						<div id="cart-checkout-btn" class="border-box">
							<?php 
								esc_html_e('Open', 'seatreg');
							?>
						</div>
					</div>
				</div>

				<div id="zoom-controller" class="no-select">
					<i class="fa fa-arrow-circle-up move-action" data-move="up"></i><br>
					<i class="fa fa-arrow-circle-left move-action" data-move="left"></i>
					<i class="fa fa-arrow-circle-right move-action" data-move="right"></i><br>
					<i class="fa fa-arrow-circle-down move-action" data-move="down"></i><br><br>
					<i class="fa fa-plus zoom-action" data-zoom="in"></i><br>
					<i class="fa fa-minus zoom-action" data-zoom="out"></i>
				</div>

				<div class="room-nav-extra-info-btn big-display-btn">
					<i class="fa fa-info-circle"></i>
				</div>
			</div>

			<div id="extra-info" class="dialog-box">
						
				<div id="extra-info-inner" class="border-box dialog-box-inner">
					<div id="info-close-btn" class="close-btn">
						<div class="close-btn-bg"></div>
						<i class="fa fa-times-circle"></i>
					</div>
					<h3>
						<?php 
							esc_html_e('Info', 'seatreg');
						?>
					</h3>
					<?php
						if($data->info != null) {
							echo '<div>',esc_html__($data->info),'</div><br>';
						}

						if($data->registration_start_timestamp != null) {
							?>
								<div>
									<div class="flag1"></div>
									<?php 
										esc_html_e('Registration starts', 'seatreg');
									?>
									<span class="time">
										<?php
										    esc_html_e($data->registration_start_timestamp);
										?>
									</span>
								</div>
							<?php
						}

						if($data->registration_end_timestamp != null) {
							?>
								<div>
									<div class="flag2"></div>
									<?php 
										esc_html_e('Registration ends', 'seatreg');
									?>
									<span class="time">
										<?php
											esc_html_e($data->registration_end_timestamp);
										?>
									</span>
								</div>
							<?php
						}
					?>
					<div>
						<?php esc_html_e('Total rooms', 'seatreg'); ?>: <span class="total-rooms"></span>
					</div>
					<div>
						<?php esc_html_e('Total open seats', 'seatreg'); ?>: <span class="total-open"></span>
					</div>
					<div>
						<?php esc_html_e('Total pending seats', 'seatreg'); ?>: <span class="total-bron"></span>
					</div>
					<div>
						<?php esc_html_e('Total confirmed seats', 'seatreg'); ?>: <span class="total-tak"></span>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<div id="modal-bg"></div>

		<div id="legend-popup-dialog" class="dialog-box">
			<div id="legend-popup-dialog-inner" class="dialog-box-inner border-box">
				<div class="close-btn">
					<div class="close-btn-bg"></div>
					<i class="fa fa-times-circle"></i>
				</div>
				
				<h2>
					<?php
						esc_html_e('Legends', 'seatreg');
					?>
				</h2>
				<div class="legend-popup-legends">
				</div>
			</div>
		</div>

		<div id="confirm-dialog-mob" class="dialog-box">
			<div id="confirm-dialog-mob-inner" class="dialog-box-inner border-box">

				<div id="dialog-close-btn" class="close-btn">
					<div class="close-btn-bg"></div>
					<i class="fa fa-times-circle"></i>
				</div>

				<div id="confirm-dialog-mob-legend" class="confirm-dialog-mob-block"></div>
				<div id="confirm-dialog-mob-hover" class="confirm-dialog-mob-block"></div>
				<div id="confirm-dialog-mob-text"></div>

				<?php if($registrationTime == 'run') : ?>

					<div id="confirm-dialog-bottom">
						<div id="confirm-dialog-mob-ok" class="seatreg-btn green-btn">
							<?php 
								esc_html_e('Add to booking', 'seatreg');
							?>
						</div>
						<div id="confirm-dialog-mob-cancel" class="seatreg-btn red-btn">
							<?php 
								esc_html_e('Close', 'seatreg');
							?>
						</div>
					</div>

				<?php endif; ?>

				<input type="hidden" id="selected-seat">
				<input type="hidden" id="selected-seat-room">
				<input type="hidden" id="selected-seat-nr">
				<input type="hidden" id="selected-room-uuid">
			</div>
		</div>

		<div id="seat-cart-popup" class="dialog-box">	
			<div class="cart-popup-inner dialog-box-inner border-box">

				<div id="cart-popup-close" class="close-btn">
					<div class="close-btn-bg"></div>
					<i class="fa fa-times-circle"></i>
				</div>

				<div id="seat-cart-info">
					<?php 
						esc_html_e('Cart', 'seatreg');
					?>
				</div>
				<?php if($registrationTime == 'run') : ?>
					<div id="seat-cart-rows">
						<div class="row-nr">
							<?php
								esc_html_e('NR', 'seatreg');
							?>
						</div>
						<div class="row-room">
							<?php 
								esc_html_e('Room', 'seatreg');
							?>
						</div>
					</div>
					
					<div id="seat-cart-items"></div>
					
					<div id="checkout" class="seatreg-btn green-btn">
						<?php
							esc_html_e('Next', 'seatreg');
						?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div id="checkout-area" class="dialog-box">
			<form id="checkoput-area-inner" class="dialog-box-inner border-box">
				<div id="checkout-close" class="close-btn">
					<div class="close-btn-bg"></div>
					<i class="fa fa-times-circle"></i>
				</div>
				<div class="checkout-header">
					<?php
						esc_html_e('Booking data', 'seatreg');
					?>
				</div>
				<div id="checkout-input-area"></div>
				<div id="captchaWrap">
					<div style="justify-content:center">
						<label for="captcha-val" style="vertical-align:middle">
							<span id="captcha-text">
								<?php
									esc_html_e('Enter code', 'seatreg');
								?>:
							</span>
						</label>
						<img src="php/image.php" id="captcha-img" alt="captcha image"/>
						<div id="captcha-ref" class="refresh1">
							<i class="fa fa-refresh"></i>
						</div><br>
						
						<input type="text" id="captcha-val" name="capv" />	
					</div>
				</div>
				<button type="submit" id="checkout-confirm-btn" class="seatreg-btn green-btn">
					<?php 
						esc_html_e('OK', 'seatreg');
					?>
				</button>
				<img src="css/ajax_loader.gif" alt="Loading" class="ajax-load">
				<div id="request-error"></div>
				<?php seatrag_generate_nonce_field('seatreg-booking-submit'); ?>
			</form>
		</div>

		<input type="hidden" name="pw" id="sub-pwd" value="<?php if(!empty($_POST['reg_pwd'])) { echo esc_attr($_POST['reg_pwd']); } ?>" />

		<div id="bottom-wrapper">
			<div class="mobile-cart">
				<div class="cart-icon-text">
					<span class="seats-in-cart">0</span> 
					<?php 
						esc_html_e('seats selected', 'seatreg');
					?>
					<span class="max-seats">
						(<?php
							esc_html_e('Max', 'seatreg');
						?>
						<?php 
							if($data->seats_at_once > 1) {
								echo esc_html__($data->seats_at_once),')<br>'; 
							}else {
								echo esc_html__($data->seats_at_once),')<br>'; 
							}	
						?>
					</span>
				</div>
			</div>
			<div class="mobile-legend">
				<?php 
					esc_html_e('Show legends', 'seatreg');
				?>
			</div>
		</div>

		<div id="email-conf" class="dialog-box">
			<div id="email-conf-inner" class="dialog-box-inner border-box animated zoomIn">
				<h2>
					<?php 
						esc_html_e('Confirm email sent to', 'seatreg'); 
					?>
				<span id="email-send"></span></h2>
				<p>
				<?php 
					esc_html_e('You need to confirm your booking by following email instructions. Make sure you check your junk folders', 'seatreg');
				?>.
				</p>
				<button class="refresh-btn">
					<?php 
						esc_html_e('OK', 'seatreg');
					?>
				</button>
			</div>
		</div>

		<div id="bookings-confirmed" class="dialog-box">
			<div id="bookings-confirmed-inner" class="dialog-box-inner border-box animated zoomIn">
				<h2 class="booking-confirmed-header">
					<?php 
						esc_html_e('You Bookings are confirmed', 'seatreg');
					?>		
				</h2>
				<p>
					<?php esc_html_e('You can look your bookings status at the following link'); ?><br>
					<a href="" class="booking-check-url" target="_blank"></a>
				</p>
				<p>
					<?php esc_html_e('Save the link for future reference', 'seatreg'); ?>
				</p>
				<button class="refresh-btn">
					<?php 
						esc_html_e('OK', 'seatreg');
					?>
				</button>
			</div>
		</div>

		<div id="error" class="dialog-box">
			<div id="error-inner" class="dialog-box-inner border-box animated zoomIn">
				<h2>
					<?php 
						esc_html_e('Error', 'seatreg');
					?>
				</h2>
				<p id="error-text"></p>
				<button class="refresh-btn">
					<?php 
						esc_html_e('OK', 'seatreg');
					?>
				</button>
			</div>
		</div>

		<?php if($registrationTime == 'wait' || $registrationTime == 'end') : ?>
				<div class="modal-bg"></div>

				<div id="time-notify" class="dialog-box" style="display:block">
					<div class="dialog-box-inner border-box">
						<div id="close-time" class="close-btn">
							<div class="close-btn-bg"></div>
							<i class="fa fa-times-circle"></i>
						</div>
				
						<?php
							if($registrationTime == 'wait') {
								echo '<h3>', esc_html__('Not open yet', 'seatreg'), '</h3>';
								echo '<h4>', esc_html__('Registration starts', 'seatreg'), ': <span class="time">', esc_html__($data->registration_start_timestamp), '</span></h4>';
							}else if($registrationTime == 'end') {
								echo '<h3>', esc_html__('Closed', 'seatreg'), '</h3>';
								echo '<h4>', esc_html__('Registration ended', 'seatreg'), ': <span class="time">', esc_html__($data->registration_end_timestamp), '</span></h4>';
							}
						?>
					</div>
				</div>

		<?php endif; ?>

		<script src="js/jquery.3.5.1.min.js"></script>
		<script>
			try {
				var seatregTranslations = $.parseJSON('<?php echo wp_json_encode( seatreg_generate_registration_strings() ); ?>');
				var seatLimit = <?php echo esc_js($data->seats_at_once); ?>;
				var gmail = <?php echo esc_js($data->gmail_required); ?>;
				var dataReg = $.parseJSON(<?php echo wp_json_encode($data->registration_layout); ?>);
				var roomsInfo = $.parseJSON(<?php echo wp_json_encode($seatsInfo); ?>);
				var custF = $.parseJSON(<?php echo wp_json_encode($data->custom_fields); ?>);
				var regTime = '<?php echo esc_js($registrationTime); ?>';
				var registrations = $.parseJSON(<?php echo wp_json_encode($registrations); ?>);
			} catch(err) {
				alert('Data initialization failed');
				console.log(err);
			}
		</script>
	<!--
		<script src="js/date.format.js"></script>
		<script src="js/iscroll-zoom-5-1-3.js"></script>
		<script src="js/jquery.powertip.js"></script>
		<script src="js/registration.js"></script>
	-->	
	
		<script src="js/<?php echo $manifest['registration.min.js']; ?>"></script>
	
		<?php endif; //end of is registration open ?>  

	<?php else : ?>
		<form method="post" id="pwd-form">
			<h2>
				<?php 
					esc_html_e('Password protected', 'seatreg');
				?>
			</h2>
			<label for="reg-pwd">
				<?php 
					esc_html_e('Please enter password', 'seatreg');
				?>
			</label>
			<input type="password" name="reg_pwd" id="reg-pwd" /><br><br>
			<input type="submit" value="<?php esc_attr_e('OK', 'seatreg'); ?>" />
		</form>			
	<?php endif; ?>
</body>
</html>