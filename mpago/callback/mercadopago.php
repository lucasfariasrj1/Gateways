<?php 
require "../../../init.php";
$whmcs->load_function("gateway");
$whmcs->load_function("invoice");

$gatewaymodule = "mercadopago";
$GATEWAY = getGatewayVariables($gatewaymodule);

if( !$GATEWAY["type"] ) 
{
    exit( "Module Not Activated" );
}

if( isset($_REQUEST["id"]) && isset($_REQUEST["topic"]) ) 
{
    $mcore = new mpCore($GATEWAY["client_id"], $GATEWAY["client_secret"]);
    $dado = $mcore->GetStatus($_REQUEST["id"]);
    if( $dado != NULL ) 
    {
        $invoiceid = checkCbInvoiceID($dado["collection"]["external_reference"], $GATEWAY["name"]);
        checkCbTransID($dado["collection"]["id"]);
        switch( $dado["collection"]["status"] ) 
        {
            case "approved":

                if ( $GATEWAY["taxa_auxiliar"] > 0 ) {
                    $taxa_auxiliar = $GATEWAY["taxa_auxiliar"];
                    $valor_pagamento1 = $dado["collection"]["transaction_amount"] - $taxa_auxiliar;
                } else {
                    $valor_pagamento1 = $dado["collection"]["transaction_amount"];
                }

                if ( $GATEWAY["taxa_percentual"] > 0 ) {
                    $taxa_percentual = $GATEWAY["taxa_percentual"] / 100;
                    $valor_pagamento2 = $valor_pagamento1 / (1 + $taxa_percentual);
                } else {
                    $valor_pagamento2 = $valor_pagamento1;
                }

                $fee = $dado["collection"]["transaction_amount"] - $dado["collection"]["net_received_amount"];
                addInvoicePayment($dado["collection"]["external_reference"], $dado["collection"]["id"], $valor_pagamento2, $fee, $gatewaymodule);
                $log["status"] = 1;
                $log["invoiceid"] = $dado["collection"]["external_reference"];
                $log["transid"] = $dado["collection"]["id"];
                $log["amount"] = $dado["collection"]["net_received_amount"];
                $log["fee"] = $fee;
                logTransaction($GATEWAY["name"], $log, "Successful");
                break;
            case "pending":
                break;
            case "in_process":
                break;
            case "reject":
                break;
            case "refunded":
                break;
            case "cancelled":
                break;
            case "in_metiation":
                break;
        }
    }
}
