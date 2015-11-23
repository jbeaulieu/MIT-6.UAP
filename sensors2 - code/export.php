<?php

// all possible GET vars
// $_GET["csv"] 
// $_GET["debug"] 
// $_GET['date_from'];
// $_GET['date_to'];
// $_GET['mod']

error_reporting(E_ALL ^ E_NOTICE);

/////////////////////////////////////////////////////////////////////////////////////////////
// SIMPLIFY SOME OF THE GET VARS AND PREPROCESS TIME CONDITION
/////////////////////////////////////////////////////////////////////////////////////////////

$date = $_GET['date'];
$date_from = $_GET['date_from'];
$date_to = $_GET['date_to'];


if ($temp_created == 1) { // this means this file will be included. 

	//$exportfile = readCSV2('temp_export.csv');
	//print_r($exportfile);
	
	// now call rscript to process the temp csv file 
	$command = 'C:\"Program Files"\R\R-3.2.2\bin\Rscript.exe export_rearrange.R'; // later move to relative path and PATH varibale
	//echo "now calling R script to rearrange columns: ".$command;
	exec($command);
		
	header('Content-type: text/csv; Charset=utf-8');
	header("Content-Disposition: attachment; filename=datatest.csv");		
	readfile('processed_temp_export.csv');	

} else { // if this is not included with temp_generated set but called as standalone, then display these links

	echo "<a href='generate_csv.php?csv=1&date_from=".$date_from."&date_to=".$date_to."&mod=".$_GET['mod']."'>download raw csv</a><br>";
	
	$prefix = 'EF9-';
	$suffix = 'A-N7-34';
	$suggestedName =  $prefix . date("Ymd") . $suffix;

	echo "suggested filename: ".$suggestedName;

	echo "<input type='text' name='name' value=".$suggestedName.">";

	echo "<a href='generate_csv.php?csv=1&tofile=1&date_from=".$date_from."&date_to=".$date_to."&mod=".$_GET['mod']."&suggestedName".$suggestedName."'>download cleaned up version</a><br>";
	echo "submit";
}

/*
function readCSV2($csvFile){ // no need to define this again as the function is in the mother function of this included file

	ini_set('auto_detect_line_endings', TRUE);/// (PHP's detection of line endings) write at the top.

	$csvrows = array_map('str_getcsv', file($csvFile));
	$csvheader = array_shift($csvrows);
	$csv = array();
	foreach ($csvrows as $row) {
	   $csv[] = array_combine($csvheader, $row);
	}
	return $csv;
}
*/

// what needs to be done: do this in R for now because we already have everything in place. could be done in php later
//### separate label names and units
//### add row with unitnames
//### change order of columns
//### export to csv

?>