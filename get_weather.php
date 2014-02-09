<?php
function getXMLDataByUrl($url){
	$responseXmlData = file_get_contents($url);
	if($responseXmlData === FALSE){
		return FALSE;
	}

	$xmlData = simplexml_load_string($responseXmlData);
	if($xmlData === FALSE){
		return FALSE;
	}

	return $xmlData;
}

function getWoeidArray($loaction = '', $locationType = "City"){
	$urlPrefix = "http://where.yahooapis.com/v1/";
	$urlAppID = ".3ulDM7V34G484oLjMN1KMN67JOstf47t3GK9_n9zCovJLlzkl7X5MSvFwFnRqHFKQ--";
	$url = $urlPrefix;

	if($locationType === "City"){
		$url .= "place\$and(.q('{$loaction}'),.type(7));start=0;count=5?appid=";
	}else{
		$url .="concordance/usps/{$loaction}?appid=";
	}

	$url .= $urlAppID;

	$xmlData = getXMLDataByUrl($url);

	if($xmlData === FALSE){
		return FALSE;
	}

	$woeidArray = array();

	if($locationType === "City"){
		foreach ($xmlData->place as $entry) {
			$woeidArray[] = $entry->woeid;
		}
	}else{
		$woeidArray[] = $xmlData->woeid;
	}

	return $woeidArray;
}

function alterNull(&$ch){
	if($ch == NULL){
		$ch = "N/A";
		return TRUE;
	}
	return FALSE;
}

function getRssData($woeidArray, $tempUnit = "Fahrenheit"){
	if($woeidArray == NULL){
		return FALSE;
	}

	$urlPrefix = "http://weather.yahooapis.com/forecastrss?w=";
	$dataSign = FALSE;

	$rssData = array();

	foreach ($woeidArray as $woeid) {
		$url = $urlPrefix;

		if($tempUnit === "Fahrenheit"){
			$url .= $woeid ."&u=f";
		}else{
			$url .= $woeid ."&u=c";
		}

		$xmlData = getXMLDataByUrl($url);

		if(strval($xmlData->channel->description) === "Yahoo! Weather Error"
			or strval($xmlData->channel->title) === "Yahoo! Weather - Error"){
			continue;
		}

		if($xmlData !== FALSE){
			$dataSign = TRUE;
		}

		$yweather1 = 
		  $xmlData->channel->item->children("http://xml.weather.yahoo.com/ns/rss/1.0");
		$yweather2 = $xmlData->channel->children("http://xml.weather.yahoo.com/ns/rss/1.0");
		$geo = $xmlData->channel->item->children("http://www.w3.org/2003/01/geo/wgs84_pos#");

		$description = $xmlData->channel->item->description;

		$htmlParser = new DOMDocument();
		$htmlParser->loadHTML($description);
		$html = simplexml_import_dom($htmlParser);

		$nullCount = 0;

		$imageUrl = strval($html->body->img->attributes()->src);
		$nullCount += alterNull($imageUrl);

		$rssUrl = $url;
		$nullCount += alterNull($url);

		$conditionText = strval($yweather1->condition->attributes()->text);
		$nullCount += alterNull($conditionText);

		$conditionTemp = strval($yweather1->condition->attributes()->temperature);
		$nullCount += alterNull($conditionTemp);

		$unitsTemp = strval($yweather2->units->attributes()->temperature);
		$nullCount += alterNull($unitsTemp);

		$locationCity = strval($yweather2->location->attributes()->city);
		$nullCount += alterNull($locationCity);

		$locationRegion = strval($yweather2->location->attributes()->region);
		$nullCount += alterNull($locationRegion);

		$locationCountry = strval($yweather2->loaction->attributes()->country);
		$nullCount += alterNull($locationCountry);

		$geoLat = strval($geo->lat);
		$nullCount += alterNull($geoLat);

		$geoLong = strval($geo->long);
		$nullCount += alterNull($geoLong);

		$linkDetail = strval($html->body->a->attributes()->href);
		$nullCount += alterNull($linkDetail);

		$textArray = array(
			$imageUrl,
			$rssUrl,
			$conditionText,
			$conditionTemp,
			$unitsTemp,
			$locationCity,
			$locationRegion,
			$locationCountry,
			$geoLat,
			$geoLong,
			$linkDetail
		);

		if($nullCount === count($textArray)){
			continue;
		}

		$rssData[] = $textArray;
	}

	if($dataSign === FALSE){
		return FALSE;
	}

	return $rssData;
}

function handleEvent(){
	$location = strval($_POST["location"]);
	$locationType = strval($_POST["locationType"]);
	$tempUnit = strval($_POST["tempUnit"]);

	$locationPlus = str_replace(" ", "+", $location);

	$woeidArray = getWoeidArray($locationPlus, $locationType);

	if($woeidArray == FALSE){
		echo "<h1>Zero results found!</h1>";
		return;
	}

	$rssData = getRssData($woeidArray, $tempUnit);

	if($rssData == FALSE){
		echo "<h1>Zero results found!</h1>";
		return;
	}

	$resultsNum = count($rssData);

	echo "<h3>{$resultsNum} result(s) for City {$location}</h3>";

	$html = '
	  <table border="1">
	    <tr>
	      <th>Weather</th>
	      <th>Temperature</th>
	      <th>City</th>
	      <th>Region</th>
	      <th>Country</th>
	      <th>Latitude</th>
	      <th>Longitude</th>
	      <th>Link to Details</th>
	    </tr>';

	foreach ($rssData as $entry) {
		$html .= '
		  <tr>';
		if($entry[0] === "N/A"){
			$html .= '
			  <td>' .$entry[0] . '</td>';
		}else{
			$html .= '
			<td><a href="' . $entry[1] . '"><img src="' . $entry[0] . '"alt="' . $entry[2] . '"title="' . $entry[2] . '" /></a></td>';		
		}

		$html .= '
		  <td>' . $entry[2] . ' ' . $entry[3] . '&deg;' . $entry[4] . '</td>
		  <td>' . $entry[5] . '</td>
		  <td>' . $entry[6] . '</td>
		  <td>' . $entry[7] . '</td>
		  <td>' . $entry[8] . '</td>
		  <td>' . $entry[9] . '</td>';

		if($entry[10] === "N/A"){
			$html .= '
			  <td>' . $entry[10] .'</td>';
		}else{
			$html .= '
			<td><a href="' . $entry[10] . '" />Details</td>';
		}

		$html .= '
		  </tr>';
	}

	$html .='
	  </table>';

	echo $html;
}

?>

<html>
  <head>
  	<title>Weather Search</title>
  	<script type="text/javascript" src="checkForm.js"></script>
  	<link rel="stylesheet" type="text/css" href="style.css" />
  </head>
  <center>
  	<body>
  		<h1>Weather Search</h1>
  		<div>
  		<form action="" method="POST" onsubmit="return checkForm(this)">
  			<table>
  				<tr>
  					<td>Location:</td>
  					<td><input type="text" name="location" size=40 /></td>
  				</tr>
  				<tr>
  					<td>Location Type:</td>
  					<td>
  						<select name="locationType">
  							<option selected>City</option>
  							<option>Zip Code</option>
  						</select>
  					</td>
  				</tr>
  				<tr>
  					<td>Temperature Unit:</td>
  					<td>
  						<input type="radio" name="tempUnit" value="Fahrenheit" checked>
  						  Fahrenheit
  						</input>
  						<input type="radio" name="tempUnit" value="Celsius">
  						  Celsius
  						</input>
  					</td>
  				</tr>
  			</table>
  			<br />
  			<input type="submit" name="search" value="Search" />
  		</form>
  	</div>
  	<?php
  	if($_POST["search"]){
  		handleEvent();
  	}
  	?>
  	</body>
  </center>
</html>