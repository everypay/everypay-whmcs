<?php

require_once __DIR__ . '/../../../init.php';
App::load_function('gateway');
App::load_function('invoice');


$gatewaymodule = "everypay";

$params = getGatewayVariables($gatewaymodule);

# Checks gateway module is active before accepting callback
if (!$params["type"]){
    die("Module Not Activated");
}

if (!isset($_POST["everypayToken"])){
    header("Location: " . $params['systemurl']);
}   

# Get Returned Variables
$merchant_order_id = $_POST["merchant_order_id"];
$everypay_token = $_POST["everypayToken"];

# Checks invoice ID is a valid invoice number or ends processing
$merchant_order_id = checkCbInvoiceID($merchant_order_id, $params["name"]);

# Checks transaction number isn't already in the database and ends processing if it does
checkCbTransID($everypay_token);

# Fetch invoice to get the amount
$result = mysql_fetch_assoc(select_query('tblinvoices', 'total', array("id" => $merchant_order_id)));
$amount = $result['total'];

# Check if amount is EURO, convert if not.
$currency = getCurrency();
if ($currency['code'] !== 'EUR') {
    $result = mysql_fetch_array(select_query("tblcurrencies", "id", array("code" => 'EUR')));
    $inr_id = $result['id'];
    $converted_amount = convertCurrency($amount, $currency['id'], $inr_id);
} else {
    $converted_amount = $amount;
}

$converted_amount = 100 * $converted_amount;

$success = true;
$error = "";

try { 

    $theURL = "https://" . ($params['mode'] == 'LIVE' ? '' : 'sandbox-') 
             . "api.everypay.gr/payments";
    $everypayParams = array(
        'token' => $everypay_token,
        'amount' => $converted_amount,
        'description' => $CONFIG['CompanyName'] . " Invoice #" . $merchant_order_id,
        'payee_email' => $_POST["payee_email"],
        'payee_phone' => $_POST["payee_phone"],
    );

    $curl = curl_init();
    $query = http_build_query($everypayParams, null, '&');

    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, $params['SecretKey'] . ':');
    curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
    curl_setopt($curl, CURLOPT_URL, $theURL);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($curl);
    
    $info = curl_getinfo($curl);

    if ($result === false) {
        $success = false;
        $error = 'Curl error: ' . curl_error($curl);
    } else {
        $response_array = json_decode($result, true);
        //Check success response
        if (isset($response_array['error']) === false) {
            $success = true;
        } else {
            $success = false;

            if (!empty($response_array['error']['code'])) {
                $error = $response_array['error']['code'] . ":" . $response_array['error']['message'];
            } else {
                $error = "EVERYPAY_ERROR:Invalid Response <br/>" . $result;
            }
        }
    }
    //close connection
    curl_close($curl);
} catch (Exception $e) {
    $success = false;
    $error = "WHMCS_ERROR:Request to Everypay Failed";
}

if ($success === true) {
    # Successful 
    # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
    addInvoicePayment($merchant_order_id, $everypay_payment_id, $amount, 0, $params["name"]);
    logTransaction($params["name"], json_encode($_POST), "Successful: " . $result); # Save to Gateway Log: name, data array, status
} else {
    # Unsuccessful
    # Save to Gateway Log: name, data array, status
    logTransaction($params["name"], json_encode($_POST), "Unsuccessful: Error" . $error . ". Please check everypay dashboard for Payment token: " . $everypay_token);
}

header("Location: " . $params['systemurl'] . "/viewinvoice.php?id=" . $merchant_order_id);

?>
