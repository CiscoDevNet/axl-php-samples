<!DOCTYPE html>
<html>
<?php
/* AXL <add/updatePhone> sample script to add a phone and associate line, then
update it using an <addLines> list.

See the 'Hints' section in README.md for a discussion of how this sample
addresses xsd:choice elements.

Copyright (c) 2021 Cisco and/or its affiliates.
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

// Enable error reporting for troubleshooting
ini_set("display_errors", 1); 
ini_set("display_startup_errors", 1); 
error_reporting(E_ALL);

// Load the vlucas/phpdotenv library and
// load variables from .env
require "vendor/autoload.php";
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv -> load();
$dotenv -> required("CUCM_ADDRESS") -> notEmpty();
$dotenv -> required("USERNAME") -> notEmpty();
$dotenv -> required("PASSWORD") -> notEmpty();
$dotenv -> required("WSDL") -> notEmpty();
$dotenv -> required("DEBUG") -> notEmpty();

// Set to true to enable detailed request/response output
$debug = false;

if (getenv("DEBUG") == "True") { $debug = true; }
else { $debug = false; }

// If debug is on, use DOMDocument to pretty print XML 
if ($debug) {
    $dom = new DOMDocument();
    $dom -> preserveWhiteSpace = false;
    $dom -> formatOutput = true;
}

function printDebug() {

    global $debug, $dom, $client;
    if ( $debug == false ) return;

    // Use DOMDocument to pretty print XML request/response
    $dom -> loadXML($client -> __getLastRequest());
    $prettyRequest = $dom->saveXML();
    $dom -> loadXML($client -> __getLastResponse());
    $prettyResponse = $dom->saveXML();

    echo '<h2>REQUEST</h2>';
    echo '<h3>Headers:</h3>';
    echo '<pre>'.$client -> __getLastRequestHeaders().'</pre>';
    echo '<h3>Body:</h3>';
    echo '<xmp>'.print_r($prettyRequest, true).'</xmp>';
    echo '<br>';
    echo '<h2>RESPONSE</h2>';
    echo '<h3>Headers:</h3>';
    echo '<pre>'.$client -> __getLastResponseHeaders().'</pre>';
    echo '<h3>Body:</h3>';
    echo '<xmp>'.print_r($prettyResponse, true).'</xmp>';
}

// Define a context stream - allows us to control
// CA cert handling - here we disable checking (insecure)
$context = stream_context_create(
    array("ssl" => array(
        "verify_peer_name" => false,
        "allow_self_signed" => true
    ))
);

// To enable secure connection (production) use the below form
// including location of your CUCM's chain .pem file

// $context = stream_context_create(
//     array("ssl" => array(
//         "verify_peer_name" => true,
//         "allow_self_signed" => true,
//         "cafile" => "cucm-chain.pem"
//     ))
// );

// Create the SoapClient object, populated from the 
// configured WSDL file in the schema/ folder
$client = new SoapClient(
        getenv("WSDL"),
        array("trace" => true,
        "exceptions" => true,
        "location" => "https://".getenv("CUCM_ADDRESS").":8443/axl/",
        "login" => getenv("USERNAME"),
        "password" => getenv("PASSWORD"),
        "stream_context" => $context
    )
);

// Create a new test Line
try {
    $resp = $client -> addLine(
        array( "line" =>
            array(
                "pattern" => "1234567890" ,
                "usage" => "Device",
                "routePartition" => null
            )
        )
    );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>test line 1234567890 created!</b><br>';

// Create a new test Phone, associating the test Line
try {
    $resp = $client -> addPhone(
        array( "phone" =>
            array(
                "name" => "CSFTESTPHONE",
                "product" => "Cisco Unified Client Services Framework",
                "model" => "Cisco Unified Client Services Framework",
                "class" => "Phone",
                "protocol" => "SIP",
                "protocolSide" => "User",
                "devicePoolName" => "Default",
                "locationName" => "Hub_None",
                "sipProfileName" => "Standard SIP Profile",
                "lines" => array(
                    "line" => array(
                        "index" => "1",
                        "dirn" => array(
                            "pattern" => "1234567890",
                            "routePartition" => null
                        )
                    )
                ),
                "commonPhoneConfigName" => null,
                "phoneTemplateName" => null,
                "primaryPhoneName" => null,
                "useTrustedRelayPoint" => null,
                "builtInBridgeStatus" => null,
                "packetCaptureMode" => null,
                "certificateOperation" => null,
                "deviceMobilityMode" => null
            )
        )
    );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>test Phone created!</b><br>';

// Create a second test Line
try {
    $resp = $client -> addLine(
        array( "line" =>
            array(
                "pattern" => "9876543210" ,
                "usage" => "Device",
                "routePartition" => null
            )
        )
    );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>test Line 9876543210 created!</b><br>';

// Create a custom class for a updatePhone object using
// the <addLines> choice option

class updatePhone_using_addLines {
    public $name;
    public $addLines;
}

// Instantiate an object of the custom class
$updatePhoneObj = new updatePhone_using_addLines;
$updatePhoneObj -> name = 'CSFTESTPHONE';

// We can set addLines as an array in the usual SoapClient way
$updatePhoneObj -> addLines = array(
    "line" => array(
        "index" => "2",
        "dirn" => array(
            "pattern" => "9876543210",
            "routePartitionName" => null
        )
    )
 );

// Execute the updatePhone request
try {
    $resp = $client -> updatePhone( $updatePhoneObj );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>test Phone updated!</b><br>';


// Delete the objects we just created

try {
    $resp = $client -> removePhone( array( 'name' => 'CSFTESTPHONE' ) );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>test Phone deleted!</b><br>';

try {
    $resp = $client -> removeLine(
        array(
            "pattern" => "1234567890",
            "routePartition" => null
        )
    );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>test Line 1234567890 deleted!</b><br>';

try {
    $resp = $client -> removeLine(
        array(
            "pattern" => "9876543210",
            "routePartition" => null
        )
    );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>test Line 9876543210 deleted!</b><br>';

?>
<html>
