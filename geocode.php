<?php
include "header.php";

// Run this script after new markers have been added to the DB.
// It will look for any markers that are missing latlong values
// and automatically geocode them.

// google maps vars
define("MAPS_HOST", "maps.google.com");
define("KEY", "abcdefg");

// get places that don't have latlong values
$result = mysql_query("SELECT * FROM places WHERE lat=0 OR lng=0") or die(mysql_error());

// geocode and save them back to the db
$delay = 0;
$base_url = "http://" . MAPS_HOST . "/maps/geo?output=xml" . "&key=" . KEY;

// Iterate through the rows, geocoding each address
while ($row = @mysql_fetch_assoc($result)) {
  $geocode_pending = true;

  while ($geocode_pending) {
    $address = $row["address"];
    $id = $row["id"];
    $request_url = $base_url . "&q=" . urlencode($address);
    $ch = curl_init($request_url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $xml_raw = curl_exec($ch);
    $xml = simplexml_load_string($xml_raw) or die("url not loading");
//    $xml = simplexml_load_file($request_url) or die("url not loading");

    $status = $xml->Response->Status->code;
    if (strcmp($status, "200") == 0) {
      // Successful geocode
      $geocode_pending = false;
      $coordinates = $xml->Response->Placemark->Point->coordinates;
      $coordinatesSplit = split(",", $coordinates);
      // Format: Longitude, Latitude, Altitude
      $lat = $coordinatesSplit[1];
      $lng = $coordinatesSplit[0];

      $query = sprintf("UPDATE places " .
             " SET lat = '%s', lng = '%s' " .
             " WHERE id = '%s' LIMIT 1;",
             mysql_real_escape_string($lat),
             mysql_real_escape_string($lng),
             mysql_real_escape_string($id));
      $update_result = mysql_query($query);
      if (!$update_result) {
        die("Invalid query: " . mysql_error());
      }
    } else if (strcmp($status, "620") == 0) {
      // sent geocodes too fast
      $delay += 100000;
    } else {
      // failure to geocode
      $geocode_pending = false;
      //echo "Address " . $address . " failed to geocoded. ";
      //echo "Received status " . $status . " \n";
    }
    usleep($delay);
  }
}


// finish
if(@$hide_geocode_output != true) {
  echo mysql_num_rows($result)." places geocoded";
}

?>
