<?php

require_once "sms.php";

function event_notify($ed, $ud, $od, $loc)
{
	global $ms, $gsValues;

	$imei = $loc['imei'];

	if (!checkObjectActive($imei)) {
		return;
	}

	// get current date and time for week days and day time check
	$dt_check = convUserIDTimezone($ud['id'], date("Y-m-d H:i:s", strtotime($loc['dt_server'])));

	if (!check_event_week_days($dt_check, $ed['week_days'])) {
		return;
	}

	if (!check_event_day_time($dt_check, $ed['day_time'])) {
		return;
	}

	$ed = check_event_route_trigger($ed, $ud, $loc);
	if ($ed == false) {
		return;
	}

	$ed = check_event_zone_trigger($ed, $ud, $loc);
	if ($ed == false) {
		return;
	}

	// duration from last event
	if (!check_event_duration_from_last($ed, $imei)) {
		return;
	} else {
		$q = "UPDATE `gs_user_events_status` SET `dt_server`='" . gmdate("Y-m-d H:i:s") . "' WHERE `event_id`='" . $ed['event_id'] . "' AND `imei`='" . $imei . "'";
		$r = mysqli_query($ms, $q);
	}
	if ($ed['alert_level'] == "" || $ed['alert_level'] == '0') {
		$ed['alert_level'] = '1';
	}
	// insert event into list
	$q = "INSERT INTO `last_events_data` (	`user_id`,
								`type`,
								`alert_level`,
								`event_desc`,
								`notify_system`,
								`notify_push`,
								`notify_arrow`,
								`notify_arrow_color`,
								`notify_ohc`,
								`notify_ohc_color`,
								`imei`,
								`name`,
								`dt_server`,
								`dt_tracker`,
								`lat`,
								`lng`,
								`altitude`,
								`angle`,
								`speed`,
								`params`,
								`ack`
								) VALUES (
								'" . $ed['user_id'] . "',
								'" . $ed['type'] . "',
								'" . $ed['alert_level'] . "',
								'" . mysqli_real_escape_string($ms, $ed['event_desc']) . "',
								'" . $ed['notify_system'] . "',
								'" . $ed['notify_push'] . "',
								'" . $ed['notify_arrow'] . "',
								'" . $ed['notify_arrow_color'] . "',
								'" . $ed['notify_ohc'] . "',
								'" . $ed['notify_ohc_color'] . "',
								'" . $od['imei'] . "',
								'" . mysqli_real_escape_string($ms, $od['name']) . "',
								'" . $loc['dt_server'] . "',
								'" . $loc['dt_tracker'] . "',
								'" . $loc['lat'] . "',
								'" . $loc['lng'] . "',
								'" . $loc['altitude'] . "',
								'" . $loc['angle'] . "',
								'" . $loc['speed'] . "',
								'" . json_encode($loc['params']) . "',
								'" . $ed['acknow'] . "')";
	$r = mysqli_query($ms, $q);

	// insert event into list
	$q = "INSERT INTO `events_data` (	`user_id`,
								`type`,
								`alert_level`,
								`event_desc`,
								`imei`,
								`name`,
								`dt_server`,
								`dt_tracker`,
								`lat`,
								`lng`,
								`altitude`,
								`angle`,
								`speed`,
								`params`,
								`ack`
								) VALUES (
								'" . $ed['user_id'] . "',
								'" . $ed['type'] . "',
								'" . $ed['alert_level'] . "',
								'" . mysqli_real_escape_string($ms, $ed['event_desc']) . "',
								'" . $od['imei'] . "',
								'" . mysqli_real_escape_string($ms, $od['name']) . "',
								'" . $loc['dt_server'] . "',
								'" . $loc['dt_tracker'] . "',
								'" . $loc['lat'] . "',
								'" . $loc['lng'] . "',
								'" . $loc['altitude'] . "',
								'" . $loc['angle'] . "',
								'" . $loc['speed'] . "',
								'" . json_encode($loc['params']) . "',
								'" . $ed['acknow'] . "')";
	$r = mysqli_query($ms, $q);

	// send webhook
	if ($ed['webhook_send'] == 'true') {
		$units = explode(",", $ud['units']);

		$speed = $loc['speed'];
		$speed = convSpeedUnits($speed, 'km', $units[0]);

		$driver = getObjectDriver($ud['id'], $od['imei'], $loc['params']);

		$trailer = getObjectTrailer($ud['id'], $od['imei'], $loc['params']);

		$odometer = getObjectOdometer($od['imei']);
		$odometer = floor(convDistanceUnits($odometer, 'km', $units[0]));

		$eng_hours = getObjectEngineHours($od['imei'], false);

		$url = $ed['webhook_url'];
		$url .= '?username=' . urlencode($ud['username']);
		$url .= '&name=' . urlencode($od['name']);
		$url .= '&imei=' . urlencode($od['imei']);
		$url .= '&type=' . urlencode($ed['type']);
		$url .= '&desc=' . urlencode($ed['event_desc']);

		if (isset($ed['zone_name'])) {
			$url .= '&zone_name=' . urlencode($ed['zone_name']);
		}

		if (isset($ed['route_name'])) {
			$url .= '&route_name=' . urlencode($ed['route_name']);
		}

		$url .= '&lat=' . urlencode($loc['lat']);
		$url .= '&lng=' . urlencode($loc['lng']);
		$url .= '&speed=' . urlencode($speed);
		$url .= '&altitude=' . urlencode($loc['altitude']);
		$url .= '&angle=' . urlencode($loc['angle']);
		$url .= '&dt_server=' . urlencode($loc['dt_server']);
		$url .= '&dt_tracker=' . urlencode($loc['dt_tracker']);

		$url .= '&tr_model=' . urlencode($od['model']);
		$url .= '&vin=' . urlencode($od['vin']);
		$url .= '&plate_number=' . urlencode($od['plate_number']);
		$url .= '&sim_number=' . urlencode($od['sim_number']);

		$url .= '&driver_name=' . urlencode($driver['driver_name']);
		$url .= '&trailer_name=' . urlencode($trailer['trailer_name']);
		$url .= '&odometer=' . urlencode($odometer);
		$url .= '&eng_hours=' . urlencode($eng_hours);

		sendWebhookQueue($url);
	}

	// send cmd
	if ($ed['cmd_send'] == 'true') {
		if ($ed['cmd_gateway'] == 'gprs') {
			sendObjectGPRSCommand($ed['user_id'], $imei, mysqli_real_escape_string($ms, $ed['event_desc']), $ed['cmd_type'], mysqli_real_escape_string($ms, $ed['cmd_string']));
		} else if ($ed['cmd_gateway'] == 'sms') {
			sendObjectSMSCommand($ed['user_id'], $imei, mysqli_real_escape_string($ms, $ed['event_desc']), mysqli_real_escape_string($ms, $ed['cmd_string']));
		}
	}

	// send push notification FIREBASE PUSH NOTIFICATION SHOULD BE HERE, NEED CONFIRMATION
	if ($ed['notify_push'] == 'true') {
		// account
		$result = sendPushQueue($ud['push_notify_identifier'], 'event', '');

		// sub accounts
		$q = "SELECT * FROM `gs_users` WHERE `manager_id`='" . $ed['user_id'] . "' AND `privileges` LIKE ('%subuser%')";
		$r = mysqli_query($ms, $q);

		while ($row = mysqli_fetch_array($r)) {
			$privileges = json_decode($row['privileges'], true);
			if (!isset($privileges["imei"])) {
				continue;
			}
			$imeis = explode(",", $privileges["imei"]);
			if (in_array($imei, $imeis)) {
				$result = sendPushQueue($row['push_notify_identifier'], 'event', '');
			}
		}
	}

	// send email notification
	if (checkUserUsage($ed['user_id'], 'email')) {
		if (($ed['notify_email'] == 'true') && ($ed['notify_email_address'] != '')) {
			$email = $ed['notify_email_address'];

			$template = event_notify_template('email', $ed, $ud, $od, $loc);

			$result = sendEmailQueue($email, $template['subject'], $template['message'], true);

			if ($result) {
				updateUserUsage($ed['user_id'], false, $result, false, false);
			}
		}
	}

	// send SMS notification
	if (checkUserUsage($ed['user_id'], 'sms')) {
		if (($ed['notify_sms'] == 'true') && ($ed['notify_sms_number'] != '')) {
			$result = false;

			$number = $ed['notify_sms_number'];

			$template = event_notify_template('sms', $ed, $ud, $od, $loc);

			if ($ud['sms_gateway'] == 'true') {
				if ($ud['sms_gateway_type'] == 'http') {
					$result = sendSMSHTTPQueue($ud['sms_gateway_url'], '', $number, $template['message']);
				} else if ($ud['sms_gateway_type'] == 'app') {
					$result = sendSMSAPP($ud['sms_gateway_identifier'], '', $number, $template['message']);
				}
			} else {
				if (($ud['sms_gateway_server'] == 'true') && ($gsValues['SMS_GATEWAY'] == 'true')) {
					if ($gsValues['SMS_GATEWAY_TYPE'] == 'http') {
						$result = sendSMSHTTPQueue($gsValues['SMS_GATEWAY_URL'], $gsValues['SMS_GATEWAY_NUMBER_FILTER'], $number, $template['message']);
					} else if ($gsValues['SMS_GATEWAY_TYPE'] == 'app') {
						$result = sendSMSAPP($gsValues['SMS_GATEWAY_IDENTIFIER'], $gsValues['SMS_GATEWAY_NUMBER_FILTER'], $number, $template['message']);
					}
				}
			}

			if ($result) {
				//update user usage
				updateUserUsage($ed['user_id'], false, false, $result, false);
			}
		}
	}
}
