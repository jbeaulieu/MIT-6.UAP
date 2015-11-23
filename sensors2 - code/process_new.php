<?php
error_reporting(E_ALL ^ E_NOTICE);

$con=mysqli_connect("localhost", "fm", "cf2015") or die("can't connect");
mysqli_select_db($con,"sensors") or die("can't select database");

$current_table = "normalized_".date("Y_W");
$current_table_assembled = "assembled_".date("Y_W");
$query = "SHOW TABLES LIKE '".$current_table."'";
$result = mysqli_query($con,$query);
echo $query;
$tableExists = mysqli_num_rows($result) > 0;
if (!$tableExists) { 
	mysqli_query($con,"CREATE TABLE ".$current_table." like normalized_template");
	mysqli_query($con,"CREATE TABLE ".$current_table_assembled." like assembled_template");
	//echo "create TABLE ".$current_table." like normalized_template";
}

// prepare for insert below. first find currently highest id
$query = 'SELECT * FROM '.$current_table.' ORDER BY id DESC LIMIT 1';
$result = mysqli_query($con,$query);
$row = mysqli_fetch_array($result);
$max_id = $row[0];
echo $max_id;
echo "\n<br>";

// delete latecomers
$query = 'DELETE FROM incoming WHERE time < "'.$row[3].'" - INTERVAL 1 MINUTE'; // if there is any new value that is more than a min behind the latest normalized values
$result = mysqli_query($con,$query);
echo $query;
echo "\n<br>"; // happens very rarely but should still log this. if this is not avoided here, it messes up the normalized data --> double entries

// prepare for insert below. first find currently highest id
$query = 'SELECT * FROM archive ORDER BY id DESC LIMIT 1';
$result = mysqli_query($con,$query);
$row = mysqli_fetch_array($result);
$max_id_archive = $row[0];
echo $max_id_archive;
echo "\n<br>";

// prepare for update later. first find currently highest id
$query = 'SELECT id FROM incoming ORDER BY id DESC LIMIT 1'; // WHERE processed = 0 
$result = mysqli_query($con,$query);
$row = mysqli_fetch_array($result);
$unprocessed_id = $row[0];
echo $unprocessed_id;
echo "\n<br>";

// find out which labels we need to go through
$alllabels = array();

echo "<b>".microtime(true)."</b><br><br>";

$query = "SELECT DISTINCT label FROM incoming ORDER BY time";
echo $query;
$result = mysqli_query($con,$query);
while($row = mysqli_fetch_array($result)) {
  // print_r($row);

  $alllabels[] = $row;
}

print_r($alllabels);

echo "<b>".microtime(true)."</b><br><br>";

// then for each label create new data entries

$csv_content = ""; // start with an empty string for temporary csv file for import
$csv2_content = ""; 

for ($l = 0; $l < count($alllabels); ++$l) {

	$thislabel = $alllabels[$l]["label"];

	$allrows = array();

	// get all the ones that were not processed before starting this script (<=unprocessed_id) and the last one of those that did get processed
	// (SELECT time AS ts,incoming.* FROM incoming WHERE processed = 1 AND label = '".$thislabel."' ORDER BY time DESC LIMIT 1)
	$query = "(SELECT time AS ts,incoming.* FROM incoming WHERE id <= ".$unprocessed_id." AND label = '".$thislabel."' ORDER BY time)";
	echo $query; // UNIX_TIMESTAMP(time)
	$result = mysqli_query($con,$query);
	while($row = mysqli_fetch_array($result)) {
	  // print_r($row);

	  $allrows[] = $row;
	}

	echo "<b>".microtime(true)."</b><br><br>";

	print_r($allrows);
	echo "COUNT:".count($allrows);

	if(count($allrows) > 2) { // only proceed if we have at least 3 rows to work with, otherwise leave them for later
		
		$stayids[] = $allrows[count($allrows)-1][id]; // save the last id, this row will have to stay in the table to start the next batch
		echo "ADD TO STAYIDS ".$allrows[count($allrows)-1][id];
		
		// for each row
		for ($i = 0; $i < count($allrows); ++$i) {

			$nexttime = strtotime($allrows[$i+1]["ts"]).".".explode(".",$allrows[$i+1]["ts"])[1]; // adding the millisecs here instead of using the mysql command above in order to not deal with timezone stuff
			$thistime = strtotime($allrows[$i]["ts"]).".".explode(".",$allrows[$i]["ts"])[1];
			$timestep = $nexttime - $thistime;

			echo "nexttime:".$nexttime."\n";
			echo "thistime:".$thistime."\n";
			echo "TIMESTEP:".$timestep."\n";
			
			$nextval2 = $allrows[$i+1]["value"];
			$thisval2 = $allrows[$i]["value"];
			$val2step = $nextval2 - $thisval2;	
			
			$this_daqid = $allrows[$i]["daq_id"]; 
			
			if ($i != count($allrows)-1) { // dont add the last one to the archive yet (will happen in next cycle as it wont be the last one any more)
				$max_id_archive++;
				$csv_content2 .= $max_id_archive.",".$this_daqid.",".$thislabel.",".$allrows[$i]["ts"].",".$thisval2.",1\n";
				
			}
			
			if ($nexttime) { // proceed only if we have a next time and if the timestemp is <10s

				// find next full second
				$targettime = ceil($thistime);

				while ($targettime < $nexttime) {

					$timediff = abs($targettime-$thistime);
					$currentratio = $timediff/$timestep;

					$newtime = $thistime + $timestep * $currentratio;

					$newval2 = $thisval2 + $val2step * $currentratio;

					echo "\n";
					echo $thistime." ".$newtime." ".$targettime." ".$i."\n";
					echo $thisval2." ".$newval2."\n";
					echo "\n";

					$max_id++;

					//$fraction = explode(".",$newtime); // in case one wants to have say half second intervals later
					$mysqltime = date("Y-m-d H:i:s", $newtime); // .$fraction

					$csv_content .= $max_id.",".$this_daqid.",".$thislabel.",".$mysqltime.",".round($newval2,6).",1\n";

					$targettime++; // the 1 here can later be turned into a constant as in step size
				}
			}

			echo "<b>".microtime(true)."</b><br><br>";

		} 

		// update moved down as one query

		echo "<b>".microtime(true)."</b><br><br>";

		echo $csv_content;

		echo "<b>".microtime(true)."</b><br><br>";
	}

}

$stayids_string = implode(",",$stayids);
echo $stayids_string;
echo "\n<br>";

//$query1 = "INSERT INTO archive select * FROM incoming WHERE id <= ".$unprocessed_id." AND id not in (".$stayids_string.")";
$query2 = "DELETE FROM incoming WHERE id <= ".$unprocessed_id." AND id not in (".$stayids_string.")"; 

echo $query1;
echo "\n<br>";
echo $query2;
echo "\n<br>";
//$result = mysqli_query($con,$query1);
$result = mysqli_query($con,$query2);

echo "<b>".microtime(true)."</b><br><br>";

echo "write file<br><br>";

$myfile = fopen("C:/Users/lab3/Dropbox/htdocs/sensors2/temp_normalized.csv", "w") or die("Unable to open file!");
fwrite($myfile, $csv_content);
fclose($myfile);

echo "<b>".microtime(true)."</b><br><br>";

$query = "LOAD DATA INFILE 'C:/Users/lab3/Dropbox/htdocs/sensors2/temp_normalized.csv' INTO TABLE ".$current_table." FIELDS TERMINATED BY ','";
echo $query."\n<br>";
$result = mysqli_query($con,$query);

echo "<b>".microtime(true)."</b><br><br>";

echo "write file<br><br>";

$myfile = fopen("C:/Users/lab3/Dropbox/htdocs/sensors2/temp_archive.csv", "w") or die("Unable to open file!");
fwrite($myfile, $csv_content2);
fclose($myfile);

echo "<b>".microtime(true)."</b><br><br>";

$query = "LOAD DATA INFILE 'C:/Users/lab3/Dropbox/htdocs/sensors2/temp_archive.csv' INTO TABLE archive FIELDS TERMINATED BY ','";
echo $query."\n<br>";
$result = mysqli_query($con,$query);

echo "<b>".microtime(true)."</b><br><br>";

echo $csv_content2;

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// NOW ASSEMBLE TABLE
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$query = "LOAD DATA INFILE 'C:/Users/lab3/Dropbox/htdocs/sensors2/temp_normalized.csv' INTO TABLE normalized_temporary FIELDS TERMINATED BY ','";
echo $query."\n<br>";
$result = mysqli_query($con,$query);

// check whether all the needed columns exist

$query = "SELECT COLUMN_NAME
FROM information_schema.COLUMNS 
WHERE 
    TABLE_SCHEMA = 'sensors' 
AND TABLE_NAME = '".$current_table_assembled."'";
echo $query."\n<br>";
$result = mysqli_query($con,$query);
$existing_columns = array();
while($row = mysqli_fetch_array($result)) {
  $existing_columns[] = $row[0];
}
print_r($existing_columns);

// this code is similar to a section in generate_json.php

$query = "SELECT DISTINCT label FROM normalized_temporary";
echo $query."\n\n";

$result = mysqli_query($con,$query);

$alllabels = array();

while($row = mysqli_fetch_array($result)) {

  //print_r($row);
  $this_label = $row["label"];
  $alllabels[] = $this_label; // this variable is used in display_graph.php to generate the list of variables for changing visibility
  
  if (!in_array($this_label,$existing_columns)) {
  
  	$query = "ALTER TABLE ".$current_table_assembled." ADD `".$this_label."` float";
  	echo $query."\n<br>";
  	mysqli_query($con,$query);
  }
}

$result = mysqli_query($con,"SELECT NOW() - INTERVAL 1 MINUTE AS time"); // # MINUTE
$row = mysqli_fetch_array($result);
$threeminutesago = $row["time"];
// $threeminutesago = date('Y-m-d G:i:s',time() - 5*60); // better get this from mysql in case php and mysql are not in sync

	// BUILD QUERY
        
        $query_insert = "insert into ".$current_table_assembled." (`time`,`".implode ("`,`", $alllabels)."`) ";
        
 	$query_head = "SELECT time";

	for ($i = 0; $i < count($alllabels); ++$i) {

		$thislabel = $alllabels[$i];

		$query_head .= ",sum(case when label = '".$thislabel."' then value else 0 end) '".$thislabel."'";
	}
	
	$query_tail .= " WHERE time <= '".$threeminutesago."' GROUP BY time"; // select only the ones that are older than 5 minutes, thus making sure that the
	// whole row is complete, i.e. every column that has a value at this time has been processed by the script already.
	
	$query = $query_insert.$query_head." FROM normalized_temporary ".$query_tail;	

        echo $query;

mysqli_query($con,$query);


// now clear the inserted ones from the temp table
$query = "DELETE FROM normalized_temporary WHERE time <= '".$threeminutesago."'"; 
$result = mysqli_query($con,$query);

mysqli_close($con);


?>