<!DOCTYPE html>
<html>
<?php
/* AXL <addCss> sample script to add a Calling Search Space, then
update it using an <addMembers> list.

See the 'Hints' section in README.md for a discussion of how this sample
addresses xsd:choice elements.

Copyright (c) 2018 Cisco and/or its affiliates.
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

// Set to true to enable detailed request/response output
$debug = false;

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

// Create a Route Partition named 'testPartition1'
try {
    $resp = $client -> addRoutePartition(
        array( "routePartition" => 
            array( "name" => "testPartition1" )
        )
    );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>testPartition1 created!</b><br>';

// Create a second Route Partition named 'testPartition2'
try {
    $resp = $client -> addRoutePartition(
        array( "routePartition" => 
            array( "name" => "testPartition2" )
        )
    );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>testPartition2 created!</b><br>';

// Create a Call Search space

// Create a custom class for a CSS object with only 
// a <members> element
class css_using_members {
    public $members;
    public $name;
}

// Instantiate an object of the custom class
$css = new css_using_members;
$css -> name = 'testCss';

// We can set members as an array in the usual SoapClient way
$css -> members = array( "member" => array(
    "routePartitionName" => "testPartition1",
    "index" => 1
));

try {
    // Create Calling Search Space named 'testCss' with one partition
    $resp = $client -> addCss(
        array( "css" => $css )
    );  
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>testCss created!</b><br>';

// Update the Calling Search space

// Create a custom class for a CSS object with only 
// an <addMembers> element
class css_using_addMembers {
    public $addMembers;
    public $name;
}

$updateCss = new css_using_addMembers;
$updateCss -> name = 'testCss';

$updateCss -> addMembers = array( "member" => array(
    "routePartitionName" => "testPartition2",
    "index" => 2
));

try {
    // Update Calling Search Space with <addMembers>
    $resp = $client -> updateCss( $updateCss );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>testPartition1 updated!</b><br>';

// Delete the objects we just created

try {
    $resp = $client -> removeCss( array( 'name' => 'testCss' ) );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>testCss deleted!</b><br>';

try {
    $resp = $client -> removeRoutePartition( array( 'name' => 'testPartition1' ) );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>testPartition1 deleted!</b><br>';

try {
    $resp = $client -> removeRoutePartition( array( 'name' => 'testPartition2' ) );
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

printDebug();
echo '<b>testPartition2 deleted!</b><br>';
?>
<html>
