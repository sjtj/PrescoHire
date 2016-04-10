<?php

    // For 4.3.0 <= PHP <= 5.4.0
    if (!function_exists('http_response_code'))
    {
        function http_response_code($newcode = NULL)
        {
            static $code = 200;
            if($newcode !== NULL)
            {
                header('X-PHP-Response-Code: '.$newcode, true, $newcode);
                if(!headers_sent())
                    $code = $newcode;
            }       
            return $code;
        }
                                             }

cors ();

header("Accept: application/pdf");  // Does not enforce

if ($_FILES["invoice_pdf"]["type"] == "application/pdf") {
    echo "Response text: ";
    var_dump($_FILES);
    var_dump($_POST);
    var_dump($_FILES["invoice_pdf"]["type"]);
}

if ($_POST["account_number"] == "") {
    $cust_acct = false;
} else {
    $cust_acct = $_POST["account_number"];
}

$api_key = login();

echo "<br>Logged in<br>";

$customer = find_customer($api_key, $cust_acct);
$pdf_files = $_FILES["invoice_pdf"];

if ($customer) file_invoice($api_key, $customer, $pdf_files);



function file_invoice ($session_key, $customer, $files) {
    /* If the web framework requires, we could always store these
       files in a database table with fields a bit like:
       ID (optional), Customer RecID, filename, file size, and file content
       with primary key either ID or filename/Customer RecID composite.
       We wouldn't really have to keep track of file type becuase they're all
       all PDFs.

       But, that's just a musing.  In this implementation, I'm using files
       in the OS directory structure.
    */

    
    $path = getcwd() . "/invoices/" . $customer;
    
    if (!file_exists($path)) {
        mkdir ($path, 0777, true);
    }

    echo "<br><br>";
    var_dump($files);
    echo "<br>";

    $t_n = $files["tmp_name"];
    
     move_uploaded_file($t_n, "$path/text.pdf");
}

/* The log in function is a quick and dirty workaround for the inspHire API's
 * session key authentication.  The authentication is implemented in plain text,
 * therefore useless.  The login function effectively bypasses this redundant
 * security measure.  We do not log out because the web server may be making
 * another request concurrently with the same session key. */
function login () {
    $url = "http://termserver/insphire.office/api/sessions/logon";

    $auth = '{"USERNAME":"SETH","PASSWORD":"seth7raj","DEPOT":"100"}';

    $content_length = "content-length: " . strlen($auth);

    echo $auth . "<br>";

    // Get cURL resource
    $login = curl_init();
   
    // Set some options - we are passing in a useragent too here
    curl_setopt_array($login, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_USERAGENT => 'Presco inspHire',
        CURLOPT_POST => 1,
        CURLOPT_HTTPHEADER => array($content_length,
                                    "Accept: application/json,
            text/javascript, */*; q=0.01",
                                    "Content-Type: application/json",
                                    "Accept-Encoding: gzip, deflate"
        ),
        CURLOPT_POSTFIELDS => $auth
    )
    );

    // Send the request & save response to $resp
    $resp = curl_exec($login);

    // Close request to clear up some resources
    curl_close($login);

    return findSessionID ($resp);
}

function findSessionID($json) {
    $session_id_key = "\"SESSIONID\":\"";
    $username_id_key = "\",\"USERNAME\"";
    $start_index = strpos($json, $session_id_key) +
                 strlen($session_id_key);
    $end_index = strpos($json, $username_id_key);
    $length = $end_index - $start_index;

    return substr($json, $start_index, $length);	 
}

function find_customer ($session_key, $customer) {
    $url_base = "http://termserver/insphire.office/api/customers/";
    $url_ext = $customer . "?api_key=" . $session_key;
    $url = $url_base . $url_ext;

    echo "<br>". $url . "<br>";

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL => $url,
      CURLOPT_USERAGENT => 'Presco inspHire'
   ));

    //    $response = json_decode ("[" . curl_exec($curl) . "]", true);
    $response = json_decode (curl_exec($curl), true);
    $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (!$response["RECID"]) {
        return false;
    }
    
    return $response["RECID"];

}


    
/**
 *  An example CORS-compliant method.  It will allow any GET, POST, or OPTIONS requests from any
 *  origin.
 *
 *  In a production environment, you probably want to be more restrictive, but this gives you
 *  the general idea of what is involved.  For the nitty-gritty low-down, read:
 *
 *  - https://developer.mozilla.org/en/HTTP_access_control
 *  - http://www.w3.org/TR/cors/
 *
 */
function cors() {

    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }
}

?>