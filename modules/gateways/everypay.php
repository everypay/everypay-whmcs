<?php

function everypay_config()
{

    $configarray = array(
        "FriendlyName" => array("Type" => "System", "Value" => "Everypay (Visa/Mastercard/Maestro)"),
        "PublicKey" => array("FriendlyName" => "Public Key", "Type" => "text", "Size" => "50", "Description" => "Enter your Everypay Public Key here",),
        "SecretKey" => array("FriendlyName" => "Secret Key", "Type" => "text", "Size" => "50", "Description" => "Enter your Everypay Secret Key here",),
        "mode" => array("FriendlyName" => "Mode", "Type" => "dropdown", 'Options' => 'LIVE,SANDBOX', "Description" => "Choose the environment (live or sandbox). Note that in sandbox mode no charges will be applied to any card",),
    );
    return $configarray;
}

function everypay_link($params)
{
    # Gateway Specific Variables
    $public_key = $params['PublicKey'];

    # Invoice Variables
    $order_id = $params['invoiceid'];
    $description = $params["description"];  
    
    $amount = $params['amount'];
    
    $currency = getCurrency();
    # Check if amount is EURO, convert if not.     
    if ($currency['code'] !== 'EUR') {
        $result = mysql_fetch_array(select_query("tblcurrencies", "id", array("code" => 'EUR')));
        $inr_id = $result['id'];
        $converted_amount = convertCurrency($amount, $currency['id'], $inr_id);
    } else {
        $converted_amount = $amount;
    }

    $converted_amount = 100 * $converted_amount;


    # System Variables
    $callbackURL = $params['systemurl'] . '/modules/gateways/callback/everypay.php';


    $html = '<form action="' . $callbackURL . '" method="POST" id="everypay-payment-form">
        <div class="button-holder" style="margin-top:10px"></div>
        <input type="hidden" name="merchant_order_id" id="order_id" value="' . $order_id . '"/>
        <input type="hidden" name="payee_email" value="' . $params['clientdetails']['email'] . '"/>
        <input type="hidden" name="payee_phone" value="' . $params['clientdetails']['phonenumber'] . '"/>
    </form>
    <style type="text/css">
    .everypay-button{
    
    }
    </style>';

    $js = '<script src="https://button.everypay.gr/js/button.js"></script>';

    $js .= '<script type="text/javascript">
//<![CDATA[        
    var EVERYPAY_OPC_BUTTON = {
        amount: ' . $converted_amount . ',
        description: "' . $description . '",
        key: "' . $public_key . '",
        locale: "en",
        sandbox: ' . ($params['mode'] == 'LIVE' ? 0 : 1) . '
    }    
    
    var autoPressButton = true;    
    try {
        //case there is jquery
        if ($(\'select[name="gateway"]\').length){
            autoPressButton = false;
        }
        
        $("#everypay-payment-form").unbind(\'submit\').bind(\'submit\', function(e){
            e.preventDefault();
            if ($(this).find(\'everypayToken\').length){
                $(this).unbind(\'submit\').submit();
            }
        });        
    } catch(err) {        
        //probably no jquery - it is the standalone invoice  and do not autopress
        autoPressButton = false;
    }
    
    var loadButton = setInterval(function () { 
        try {                  
            var $everypayForm = document.getElementById("everypay-payment-form"); 
            EverypayButton.jsonInit(EVERYPAY_OPC_BUTTON, $everypayForm);
            if (autoPressButton){
                setTimeout(function(){ 
                    var x = document.getElementsByClassName("everypay-button");
                    x[0].click();
                }, 300);                                
            }
            clearInterval(loadButton);
        } catch (err){}
    }, 100);
    
//]]>
</script>';
    return $html . $js;
}

?>