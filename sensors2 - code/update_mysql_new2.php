<?php

$daylightsaving = 1;

$offset_h["TempE (C)"] = 0; // use this as reference
$offset_h["TempF (C)"] = 0.111746102; // had to change signs to make it work
$offset_h["TempG (C)"] = 0.034702835;
$offset_h["TempH (C)"] = 0.166985266;
$offset_h["TempI (C)"] = 0.015387097; // use this as reference
$offset_h["TempJ (C)"] = -0.011787675; // had to change signs to make it work
$offset_h["TempK (C)"] = 0.034837766;
$offset_h["TempL (C)"] = 0.024625387;
$offset_h["TempHeatflow (C)"] = 4.092538343;	

//$offset_h["TempB (C)"] = 0.07;

$highref = 40.66842793; 

$offset_l["TempE (C)"] = 0; // use this as reference
$offset_l["TempF (C)"] = 0.09847393; // had to change signs to make it work
$offset_l["TempG (C)"] = 0.00206171;
$offset_l["TempH (C)"] = 0.161586679;
$offset_l["TempI (C)"] = 0.011091359; // use this as reference
$offset_l["TempJ (C)"] = -0.022830009; // had to change signs to make it work
$offset_l["TempK (C)"] = 0.026010297;
$offset_l["TempL (C)"] = 0.021144009;
$offset_l["TempHeatflow (C)"] = 3.96141599;		

//$offset_l["TempB (C)"] = 0.07;

$lowref = 28.53504134;

$calib_range = $highref-$lowref;

//exec("ps auxwww|grep update_mysql.php|grep -v grep", $output);
//echo $output;
//$execute_stuck_script = 0;

$con=mysqli_connect("localhost", "fm", "cf2015") or die("can't connect");
mysqli_select_db($con,"sensors") or die("can't select database");

$insert_query = "INSERT INTO incoming (daq_id, label, time, value,secs,millis,finalsecs) VALUES ";

// turn secs and millisecs into a mysql timestamp
for ($i = 1; $i <= 4; $i++) {

	if ($_POST['l'.$i]) {

		if ($i != 1)
		$insert_query .= ",";

		$secs = $_POST['s'];
		
		if ($daylightsaving) $secs = $secs-3600;
		
		$millis = $_POST['t'.$i];
		$finalsecs = $secs+($millis/1000);
		$now = DateTime::createFromFormat('U.u', $finalsecs);
		$timestring =  $now->format("Y-m-d H:i:s.u");

		$label = $_POST['l'.$i];
		$value = $_POST['v'.$i];
		//$insert_query = "INSERT INTO sensor_buffer_test (time1, daq_id, val1, val2, val3, val4, val5, val6) VALUES ('".$timestring."',".$_POST['id'].",".$_POST['v1'].",".$_POST['v2'].",".$_POST['v3'].",".$_POST['v4'].",".$_POST['v5'].",".$_POST['v6'].")";

		if (isset($offset_l[$label])) { //

		  $this_offset_l = $offset_l[$label];
		  $this_offset_h = $offset_h[$label];
		  echo $value."<br><br><br>\n\n\n";
		  $value = ($value-($lowref-$this_offset_l))   *   ($calib_range/($calib_range-($this_offset_h-$this_offset_l)))   +   $lowref; // see adafruit for reference
                  echo $value."<br><br><br>\n\n\n";
                  // (y difference from measurement point * scaling factor: reference range/this sensor range ) + from reference point
		}

		$insert_query .= "(".$_POST['id'].",'".$label."','".$timestring."',".$value.",".$secs.",".$millis.",".$finalsecs.")";
	}
}

//if(isset($_POST['v1']))
mysqli_query($con,$insert_query);
echo $insert_query;

mysqli_close($con);

?>
