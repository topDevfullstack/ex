<?
function sendSMSHTTPQueue($gateway_url, $filter, $number, $message)
{
  global $ms;

  $q = "INSERT INTO `gs_sms_queue` 	(`dt_sms`,
							`gateway_url`,
							`filter`,
							`number`, 
							`message`)
							VALUES
							('" . gmdate("Y-m-d H:i:s") . "',
							'" . $gateway_url . "',
							'" . $filter . "',
							'" . $number . "',
							'" . mysqli_real_escape_string($ms, $message) . "')";
  $r = mysqli_query($ms, $q);

  if ($r) {
    return true;
  } else {
    return false;
  }
}

function sendSMSHTTP($gateway_url, $filter, $number, $message)
{
  global $ms;

  if (($gateway_url != '') && ($number != '') && ($message != '')) {
    // multiple phone numbers
    $numbers = explode(",", $number);

    // fitler array
    if ($filter != '') {
      $filters = explode(",", $filter);
    }

    for ($i = 0; $i < count($numbers); ++$i) {
      if ($i > 4) {
        break;
      }

      $number = trim($numbers[$i]);

      //IMPORTANT
      $number_encoded = urlencode($number);
      $message_encoded = urlencode($message);
      //IMPORTANT

      $url = str_replace("%NUMBER%", $number_encoded, $gateway_url);
      $url = str_replace("%MESSAGE%", $message_encoded, $url);

      sleep(1);

      $context = stream_context_create(array(
        'http' => array(
          'timeout' => 3
        )
      ));

      if (isset($filters)) {
        foreach ($filters as $value) {
          if (strpos($number, $value) !== false) {
            $result = @file_get_contents($url, false, $context);
          }
        }
      } else {
        $result = @file_get_contents($url, false, $context);
      }

      //// log
      //error_log('URL: '.$url);
      //error_log('RESPONSE: '.$result);
    }

    return count($numbers);
  } else {
    return false;
  }
}

function sendSMSAPP($identifier, $filter, $number, $message)
{
  global $ms;

  if (($identifier != '') && ($number != '') && ($message != '')) {
    $message = substr($message, 0, 160);

    // multiple phone numbers
    $numbers = explode(",", $number);

    // filter array
    if ($filter != '') {
      $filters = explode(",", $filter);
    }

    for ($i = 0; $i < count($numbers); ++$i) {
      if ($i > 4) {
        break;
      }

      $number = trim($numbers[$i]);

      $dt_sms = gmdate("Y-m-d H:i:s");

      if (isset($filters)) {
        foreach ($filters as $value) {
          if (strpos($number, $value) !== false) {
            $q = "INSERT INTO `gs_sms_gateway_app`( `dt_sms`,
                                                    `identifier`,
                                                    `number`,
                                                    `message`
                                                    ) VALUES (
                                                    '" . $dt_sms . "',
                                                    '" . $identifier . "',
                                                    '" . $number . "',
                                                    '" . mysqli_real_escape_string($ms, $message) . "')";
            $r = mysqli_query($ms, $q);
          }
        }
      } else {
        $q = "INSERT INTO `gs_sms_gateway_app`( `dt_sms`,
                                                `identifier`,
                                                `number`,
                                                `message`
                                                ) VALUES (
                                                '" . $dt_sms . "',
                                                '" . $identifier . "',
                                                '" . $number . "',
                                                '" . mysqli_real_escape_string($ms, $message) . "')";
        $r = mysqli_query($ms, $q);
      }
    }

    return count($numbers);
  } else {
    return false;
  }
}

function getSMSAPPTotalInQueue($identifier)
{
  global $ms;

  $q = "SELECT * FROM `gs_sms_gateway_app` WHERE `identifier`='" . $identifier . "'";
  $r = mysqli_query($ms, $q);

  $count = mysqli_num_rows($r);

  return $count;
}

function clearSMSAPPQueue($identifier)
{
  global $ms;

  $q = "DELETE FROM `gs_sms_gateway_app` WHERE `identifier`='" . $identifier . "'";
  $r = mysqli_query($ms, $q);
}
