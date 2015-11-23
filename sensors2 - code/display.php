<?php
//include("manage_annotations.php");

error_reporting(E_ALL ^ E_NOTICE);

?>
 
<html>
<head>
<script type="text/javascript" src="dygraphs/dygraph-dev.js"></script>
<script type="text/javascript" src="dygraphs/src/extras/synchronizer.js"></script>


    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
    <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/jquery-ui.min.js"></script>

    <!--
    <script src="jquery-1.6.2.min.js"></script>
    <script src="jquery-1.8.3.min.js"></script>
    <script src="jquery-ui-1.8.14.custom.min.js"></script>
    -->

    <link rel='stylesheet' href='http://code.jquery.com/ui/1.10.1/themes/base/jquery-ui.css' />

    <script type="text/javascript" src="dygraphs/src/extras/hairlines.js"></script>
    <script type="text/javascript" src="dygraphs/src/extras/super-annotations.js"></script>

    <script src="strftime-min-1.3.js"></script>

    <style>
      #demodiv1 {
        position: absolute;
        left: 10px;
        right: 300px;
        height: 300px;
        display: inline-block;
      }
      #demodiv2 {
        position: absolute;
        left: 10px;
        top: 400px;
        right: 300px;
        height: 300px;
        display: inline-block;
      }      
      #controls {
        position: absolute;
        right: 10px;
        width: 250px;
        height: 400px;
        display: inline-block;
      }      

      .annotation-info {
        background: white;
        border-width: 1px;
        border-style: solid;
        padding: 4px;
        display: table;  /* shrink to fit */
        box-shadow: 0 0 4px gray;
        cursor: move;

        min-width: 20px;  /* prevents squishing at the right edge of the chart */
      }
      .annotation-info.editable {
        min-width: 180px;  /* prevents squishing at the right edge of the chart */
      }

      .dygraph-annotation-line {
        box-shadow: 0 0 4px gray;
      }
    </style>

</head>
<body>

<h3>
<?php 
/*
if (isset($_GET["date"])) {
	echo "Day: ".$_GET["date"];
}
else {
	if (isset($_GET["id_to"])) {
		$this_annotations = GetAnnotationsInRange($_GET[id_from],$_GET[id_to]);
		echo "Range: ".$this_annotations[0][annotation_short]." (".$this_annotations[0][annotation_long].")";
	}
	else {
		echo "Live update";
	}
}
*/
?>

<script src="combodate/moment.js"></script> 
<script src="combodate/combodate.js"></script> 

<?php 
if (isset($_GET["date_from"])) {
	$date_from = $_GET["date_from"];
	$date_from = urldecode($date_from);
	$date_from = str_replace(" ",",",$date_from); // have user use comma instaead of space in URL
	//echo $date_from;
} else {
	$date_from = date('Y-m-d,H:i:s', strtotime('-12 hour'));
}

if (isset($_GET["date_to"])) {
	$date_to = $_GET["date_to"];
	$date_to = urldecode($date_to);
	$date_to = str_replace(" ",",",$date_to);
	//echo $date_to;
} else {
	$date_to = date('Y-m-d,H:i:s');
}

if (isset($_GET["mod"])) {
	$mod = $_GET["mod"];
} else {
	$mod = 2;
}

?>

<form action="display.php" method="get">
mod <input type="text" id="mod" name="mod" value="<?php echo $mod ?>" size="1">
from <input type="text" id="datetime24" data-format="YYYY-MM-DD,HH:mm:ss" data-template="YYYY / MM / DD  HH : mm" name="date_from" value="<?php echo $date_from ?>">
to <input type="text" id="datetime24b" data-format="YYYY-MM-DD,HH:mm:ss" data-template="YYYY / MM / DD  HH : mm" name="date_to" value="<?php echo $date_to ?>">
<input type="submit">
</form> 

<script>
$(function(){
    $('#datetime24').combodate();  
});
$(function(){
    $('#datetime24b').combodate();  
});
</script>


</h3>

<div id="demodiv1"></div>
<div id="demodiv2"></div>
<div id="controls">

	<div id="status1"></div>
	<div id="status2"></div>	

	<div id="data_point" style="margin-top:20px;">
		<b>Last data point selected:</b><br>
		<div id="list">none</div>
	</div>
	
	    <br>
	    
	    <div id="csvlink"></div><br>
    	    <div id="average"></div><br>
	</div>
</div>

<script type="text/javascript">

var adjust_time = 60*60*(-4)*1000; // in milliseconds - for some reason dygraphs gives us a strange time value back in milliseconds, let's just adjust it this way to match the real time

  function getUrlVars() {
    var vars = {};
    var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
        vars[key] = value;
    });
    return vars;
  }


  data1 = "generate_csv.php?panelid=1&csv=1&date=<?php echo $date; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&mod=<?php echo $_GET['mod']; ?>";
  data2 = "generate_csv.php?panelid=2&csv=1&date=<?php echo $date; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&mod=<?php echo $_GET['mod']; ?>";  


var charts = {};


	charts['demodiv1'] = new Dygraph(
		    document.getElementById("demodiv1"),
		    data1,
		    {          // options
			   digitsAfterDecimal: 4, 
			   connectSeparatedPoints: true,
			   zoomCallback: function(minX, maxX, yRanges) { // update CSV generating link when zooming
			     //console.log("Zoomed to [", minX, ", ", maxX, "]");

			     var from_date = minX+adjust_time;
			     var to_date = maxX+adjust_time;

			               
			   },           
			   labelsDiv: document.getElementById('status1'), // separate y-axes
			   labelsSeparateLines: true,
			   legend : 'always',  
			   series : {
			     'U_heatflow (uV)': {
			       axis : 'y2',
			     },
			     'U_heatflow2 (uV)': {
			       axis : 'y2',
			     },   
			     'Heatflow2 (mW/cm^2)': {
			       axis : 'y2',
			     }       
			   },
			   axes : {       
			      y : {
				valueRange: [15, 70], // 75 // 40 // 90 // 30
			      },               
			      y2 : {
				valueRange: [-20, 200], // 2500 // 20
				labelsKMB: true,
				drawGrid: true,
				independentTicks: true,
				gridLinePattern: [2,2]        
			      }          
			    },
			    ylabel: 'all others', // Temp in C; I_in in mA
			    y2label: 'U_heatflow | U_heatflow2 | Heatflow2', // Voltage (mV for U_in; uV for U_hf) // 'I_in (uA), U_in (V)'
			    showRangeSelector: true,
			    interactionModel: Dygraph.Interaction.defaultModel,

			    pointClickCallback: function(event, p) { // change the last data point information field 

			      console.log(p);

			      var html = "";
			      html += new Date(p.xval).strftime("%Y/%m/%d %H:%M:%S") + "<br>";
			      html += "<span id='" + p.name + "'>" + p.name + " : ";
			      html += p.yval + "</span><br/>";
			      document.getElementById("list").innerHTML = html; // += html;
			    }
		     }
		  );

		  charts['demodiv2'] =  new Dygraph(
		    document.getElementById("demodiv2"),
		    data2,
		    {          // options
			   digitsAfterDecimal: 4, 
			   connectSeparatedPoints: true,
			   zoomCallback: function(minX, maxX, yRanges) { // update CSV generating link when zooming
			     //console.log("Zoomed to [", minX, ", ", maxX, "]");

			     var from_date = minX+adjust_time;
			     var to_date = maxX+adjust_time;

			               
			   },           
			   labelsDiv: document.getElementById('status2'), // separate y-axes
			   labelsSeparateLines: true,
			   legend : 'always',  
			   series : {
			     'Control': {
			       axis : 'y2',
			     },
			     'I_ps (mA)': {
			       axis : 'y2',
			     },   
			     'P_sample (mW)': {
			       axis : 'y2',
			     },
			     'Target (mA)': {
			       axis : 'y2',
			     },                     
			     'U_sample_pre (kV)': {
			       axis : 'y2',
			     },
			     'R_sample (MOhm)': {
			       axis : 'y2',
			     } 			     
			   },
			   axes : {       
			      y : {
				valueRange: [-1, 4000], // 75 // 40 // 90 // 900
			      },               
			      y2 : {
				valueRange: [-0.1, 60000], // 2500 // 1.2
				labelsKMB: true,
				drawGrid: true,
				independentTicks: true,
				gridLinePattern: [2,2]        
			      }          
			    },
			    ylabel: 'all others', // Temp in C; I_in in mA
			    y2label: 'I_ps | P_sample | U_sample_pre', // Voltage (mV for U_in; uV for U_hf) // 'I_in (uA), U_in (V)'
			    showRangeSelector: true,
			    interactionModel: Dygraph.Interaction.defaultModel,

			    pointClickCallback: function(event, p) { // change the last data point information field 

			      console.log(p);

			      var html = "";
			      html += new Date(p.xval).strftime("%Y/%m/%d %H:%M:%S") + "<br>";
			      html += "<span id='" + p.name + "'>" + p.name + " : ";
			      html += p.yval + "</span><br/>";
			      document.getElementById("list").innerHTML = html; // += html;
			    }
		     }
		  );


    //    gs2.push(
    //      new Dygraph(
    //        document.getElementById("div2"),
    //        NoisyData, {
    //          rollPeriod: 7,
    //          errorBars: true,
    //        }
    //      )
    //    );

       var sync = Dygraph.synchronize(charts['demodiv1'], charts['demodiv2'], {
          zoom: true,
          selection: true,
          range: false
        });

  function change(graphid,el) {
    //var graphid = "demodiv1";
    graph = charts[graphid];
    var this_id = parseInt(el.id);
    console.log(this_id);
    var labels = graph.getLabels();
    console.log(labels[el.id]);
    var series = graph.getPropertiesForSeries(labels[el.id]);
    //if (!series.visible) continue; // EDIT FM      
    console.log(series.visible);
    if (!series.visible) {
	    graph.setVisibility((this_id-1), true);
    }
    else {
	    graph.setVisibility((this_id-1), false);
    }
    //console.log(el.checked);
    
    //for (i = 1; i < labels.length; i++) {
  
    
    //thisinner = document.getElementById('status').children[0].innerHTML;
    //console.log(thisinner);
    //document.getElementById('status').children[0].innerHTML = thisinner + "HALLO";
  }

  setInterval(function() {
    charts['demodiv1'].updateOptions( { 'file': data1 } );
  }, (60000*1)); // update every 1 minute s

  setInterval(function() {
    charts['demodiv2'].updateOptions( { 'file': data2 } );
  }, (60000*1)); // update every 1 minute s


  charts['demodiv1'].ready(function() {
        
        var hide = getUrlVars()["hide"]; // in case I want to hide a certain variable through the URL
	if (hide) {
	  charts['demodiv1'].setVisibility(hide, 0);
	  document.getElementById(hide).checked = false;
	}   

	var from_date = charts['demodiv1'].xAxisRange()[0]+adjust_time;
	var to_date = charts['demodiv1'].xAxisRange()[1]+adjust_time;
	//alert(from_date);

        var link = "";
        link += "<a href='export.php?csv=1&download=1";
        link += "&date_from=" + from_date + "&date_to=" + to_date;
        link += "' target='_blank'>export2</a><br>";  
        //link += "<a href='show_graphs.php?csv=1&tofile=1";
        //link += "&date_from=" + from_date + "&date_to=" + to_date;
        //link += "'>show graphs</a><br>";                   
        document.getElementById("csvlink").innerHTML = link;	
        
        //alert(g2.numColumns());
        
        //for (i = 0; i < (g2.numColumns()*2); i+=2) {  // made change in .js file instead
            //console.log(g2.getValue(0, i));
	    //thisinner = document.getElementById('status').children[i].innerHTML;
	    //console.log(thisinner);		           
        //    checkbox = ""; //<input type=checkbox id='"+i+"' checked onClick='change(this)'>";
        //    document.getElementById('status').children[i].innerHTML = checkbox + document.getElementById('status').children[i].innerHTML + "HALLO";   
        //} 
          
  });


</script>

</body>
</html>