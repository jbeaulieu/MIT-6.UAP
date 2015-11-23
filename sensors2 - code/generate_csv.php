<?php

// all possible GET vars
// $_GET["csv"] 
// $_GET["debug"] 
// $_GET['date_from'];
// $_GET['date_to'];
// $_GET['mod']

error_reporting(E_ALL ^ E_NOTICE);


$con=mysqli_connect("18.82.0.82", "fm", "cf2015") or die("can't connect");
mysqli_select_db($con,"sensors") or die("can't select database");

// read needed csv files
$panelfile = readCSV2('panels.csv');
$mappingfile = readCSV2('current_mapping.csv');


/////////////////////////////////////////////////////////////////////////////////////////////
// SIMPLIFY SOME OF THE GET VARS AND PREPROCESS TIME CONDITION
/////////////////////////////////////////////////////////////////////////////////////////////

$date = $_GET['date'];
$date_from = $_GET['date_from'];
$date_to = $_GET['date_to'];

debug_print_time;

// if dates are given in unixtimestamp then convert to mysql timestamp

if ($date_from && strpos($date_from, '-') == FALSE) {

	$date_from = gmdate("Y-m-d H:i:s", $date_from/1000);
	//echo $date_from."\n";
}
if ($date_to && strpos($date_to, '-') == FALSE) {

	$date_to = gmdate("Y-m-d H:i:s", $date_to/1000);
	//echo $date_to."\n";
}

if ($date_from) { // i think at this point this should be always true

	$date_from = urldecode($date_from);
	$date_to = urldecode($date_to);
	$date_from = str_replace(","," ",$date_from); // have user use comma instaead of space in URL
	$date_to = str_replace(","," ",$date_to); // this is a leftover from when dates were entered via url
        
	if (!$date_to) {
		$date_to = date("Y-m-d H:i:s"); // if no date is given, assume that the current time is meant
	} 

	$timecondition = "time >= '".$date_from."'"; // get timecondition for sql query
	$timecondition .= " AND time <= '".$date_to."'";
	
	$week_from = date("Y_W",strtotime($date_from)); // so far this only works with two weeks. but that should be enough. gives 1 week span max
	$week_to = date("Y_W",strtotime($date_to)); // change it if we have to		
}		

/////////////////////////////////////////////////////////////////////////////////////////////
// WHAT FOLLOWS IS THE ACTUAL MAIN PROGRAM THAT IDENTIFIES THE RELEVANT LABELS/COLUMNS, READS THE DATABASE AND PRINTS THE CSV FILE
/////////////////////////////////////////////////////////////////////////////////////////////


// get two arrays: 1) labels for all the columns that are non-zero in the chosen timeframe, and 2) all the columns in a given week's table

if ($week_from == $week_to) {
	
	list($nonzerolabels,$allcolumns) = getLabelsInWeek($con,$timecondition,$week_from);
}
else {
        list($nonzerolabels1,$allcolumns1) = getLabelsInWeek($con,$timecondition,$week_from);
        list($nonzerolabels2,$allcolumns2) = getLabelsInWeek($con,$timecondition,$week_to);
        $nonzerolabels = array_unique(array_merge($nonzerolabels1, $nonzerolabels2));
        $allcolumns = array_unique(array_merge($allcolumns1, $allcolumns2));
}

debug_print_time($query);

// choose which fields need to be read and displayed for this particular panel

if ($_GET["panelid"]) {

	global $panelfile;
	
	$panel1_labelnames_array = array_filter($panelfile , "choosepanel1"); // this callback function just filters then label names for panel number 1
	$panel1_labelnames = array_column($panel1_labelnames_array, 'DAQName');
	
	if ($_GET["panelid"] == 1) {
		$thispanel_labelnames = $panel1_labelnames;
	} else {
		$thispanel_labelnames = array_diff($nonzerolabels, $panel1_labelnames); // if this is panel2 then take all the non-zero labels that are not in panel1
	}

} else { // if no panel is selected (i.e. display everything)
	$thispanel_labelnames = $nonzerolabels; // if no panel is selected, then proceed with all relevant labels
	
	// in this case, make sure the order of the labels corresponds to the one in current mapping such that we get the right column order for the csv export
}

//array_unshift($thispanel_labelnames,"time");
$thispanel_labelnames = array_unique($thispanel_labelnames); // just in case: get rid of duplicates
$thispanel_labelnames = array_intersect($thispanel_labelnames, $allcolumns); // make sure whatever was found in the panel file, actually exists as a column

//print_r($thispanel_labelnames);

// IF A CSV FILE NEEDS TO BE GENERATED

global $mappingfile;
$nameMap = array_combine(array_column($mappingfile, 'DAQName'),array_column($mappingfile, 'ExportName')); // get an array that maps daqnames to exportnames/printnames
//print_r($nameMap);
	

if ($_GET["csv"] == 1) {

	if ($_GET["debug"] != 1 && $_GET["tofile"] != 1) { // if not debugging prepare the headers for the downloadable file
		header('Content-type: text/csv; Charset=utf-8');
		header("Content-Disposition: attachment; filename=data ".$date_from." to ".$date_to.".csv");
	}

	// PRINT LABELS as first row of csv file
	$thispanel_printlabelnames = array_map('getPrintName',$thispanel_labelnames);
	$csv_string = "DateTime,".implode(",",$thispanel_printlabelnames)."\n" ; // CSV top row
	

        $query .= buildQueryForWeek($week_from,$thispanel_labelnames,$allcolumns,$timecondition);
        
	if ($week_from <> $week_to) {
		
		$query .= " UNION ";
		$query .= buildQueryForWeek($week_to,$thispanel_labelnames,$allcolumns2,$timecondition); // allcolumns2 is different. here we're only 
		// interested in columns that EXIST in this week's table and that have data for the given timeconditon/selection
	}

	debug_print_time($query);
	$result = mysqli_query($con,$query);
	debug_print_time();		
	
	while($row = mysqli_fetch_array($result,MYSQLI_NUM)) { // now print actual csv data into file

		$csv_string .= implode(",",$row)."\n" ;
	}
	
	if ($_GET["tofile"] == 1) {
		//file_put_contents("temp", $current); // use this function instead?	
		$myfile = fopen("C:/Users/lab3/Dropbox/htdocs/sensors2/temp_export.csv", "w") or die("Unable to open file!");
		fwrite($myfile, $csv_string);
		fclose($myfile);
		
		//echo "written raw csv file to temp_export.csv<br>";
		//echo "<a href='export.php?temp_created=1'>click here to download cleaned up version</a><br>";
		$temp_created = 1;
		include("export.php");
	} else {
		echo $csv_string;
	}
}

mysqli_close($con); // DONE!



/////////////////////////////////////////////////////////////////////////////////////////////
// FUNCTIONS
/////////////////////////////////////////////////////////////////////////////////////////////

// good to have this as function, so it can be called for each week

function getLabelsInWeek($con,$timecondition,$week){

	$current_table_assembled = "assembled_".$week;
	$query = "SHOW COLUMNS FROM ".$current_table_assembled;		
	
	debug_print_time($query);
	
	$result = mysqli_query($con,$query);
	while($row = mysqli_fetch_array($result)) {

	  if ($row["Field"] != "time") // add all columns except for time which is not a regular data column, we'll deal with it separately
	  	$allcolumns[] = $row["Field"]; // this variable is used in display_graph.php to generate the list of variables for changing visibility
	}	

	$query = "SELECT";	
	foreach ($allcolumns as $column) {

		$query .= " COUNT(`".$column."`) AS `".$column."`,";
	}
	$query = rtrim($query,","); // remove last comma
	$query .= " FROM ".$current_table_assembled;
	$query .= " WHERE ".$timecondition;
	
	debug_print_time($query);

	$result = mysqli_query($con,$query);
	$row = mysqli_fetch_array($result,MYSQL_ASSOC); // this is always just one row // 
	$nonzerolabels = $row; // this variable is used in display_graph.php to generate the list of variables for changing visibility

	$nonzerolabels = array_keys(array_filter($nonzerolabels)); // this variable is used in display_graph.php to generate the list of variables for changing visibility
	
	return [$nonzerolabels,$allcolumns];
}


function readCSV2($csvFile){

	ini_set('auto_detect_line_endings', TRUE);/// (PHP's detection of line endings) write at the top.

	$csvrows = array_map('str_getcsv', file($csvFile));
	$csvheader = array_shift($csvrows);
	$csv = array();
	foreach ($csvrows as $row) {
	   $csv[] = array_combine($csvheader, $row);
	}
	return $csv;
}

function choosepanel1($var) {
    return ($var[Panelname] == 1);
}


function getPrintName($origname) {
	
	global $nameMap;
	
	// truncate unit
	$origname_trunc = explode(" ", $origname)[0];
	$origname_unit = explode(" ", $origname)[1];
	
	if($nameMap[$origname_trunc]) {
		$printname = $nameMap[$origname_trunc];
	} else {
		$printname = $origname_trunc;
	}
	
	$finalname = $printname;
	if ($origname_unit) {
		$finalname .= " ".$origname_unit;
	}
	
	return $finalname;
}


function buildQueryForWeek($week,$thispanel_labelnames,$allcolumns,$timecondition) {

 	$query = "SELECT time"; // always have this column
	
	foreach($thispanel_labelnames as $thislabel) { // for each label for this panel in both week tables
				
		$key = array_search($thislabel,$allcolumns); // check if it exists in THIS week table
		if ($key !== FALSE) {
			$query .= ",`".$thislabel."` AS `".getPrintName($thislabel)."`"; // this is not even necessary here, just for the top row needed
		} else {
			$query .= ",0 as `".getPrintName($thislabel)."`"; // otherwise include the column as zero
		}
	}	
 	
 	$query .= " FROM assembled_".$week." WHERE ".$timecondition; //  finish the select

	if ($_GET['mod']) {
		$query .= " and MOD(time,".$_GET['mod'].")=0";
	}
	
	return $query;
}


function debug_print_time($query = "") {

	if ($_GET["debug"] == 1) {
		if ($query) 
			echo "<br><br>".$query."<br><br>";
		echo "<b>".microtime(true)."</b><br><br>";
	}
}


?>

