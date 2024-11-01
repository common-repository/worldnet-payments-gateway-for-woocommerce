<?php
/*
    Copyright 2020 Worldnet Payments

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if(basename(__FILE__) == basename($_SERVER["REQUEST_URI"])) die("<b>ERROR: You cannot display this file directly.</b>");

/**
 * Base WorldnetPaymentsGatewayRequest Class holding common functionality for Request Types.
 */
abstract class WorldnetPaymentsGatewayRequest
{
        protected $terminalId;
        protected $hash;

        public function __construct($terminalId){ $this->terminalId = $terminalId; }
        
        public function createDOMElement($requestXML, $requestString, $elementName, $data = NULL)
        {
                $node = $requestXML->createElement($elementName);
                $requestString->appendChild($node);
                $node->appendChild($requestXML->createTextNode($data));
        }
        protected function GetResponseString($serverUrl, $XMLBody)
        {
            $headers = array();
            $headers['terminal'] = $this->terminalId;
            $headers['Shopping-Cart-Version'] = 'WooCommerce ' . WC_VERSION . ', PHP ' . phpversion() . ', Worldnet Payments Gateway for WooCommerce ' . WorldnetPayments_Gateway_for_WC_VERSION;
            $responseString = $this->SendRequestToGateway($XMLBody, $serverUrl, $headers);
            return $responseString;
        }

        public abstract function SetHash($sharedSecret);

        protected static function GetRequestHash($plainString){ return md5($plainString); }

        protected static function GetFormattedDate(){ return date('d-m-Y:H:i:s:000'); }

        protected static function SendRequestToGateway($requestString, $serverUrl, $headers = array())
        {
                $args = array(
                    'body' => $requestString,
                    'timeout' => '61',
                    'redirection' => '5',
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => $headers,
                    'cookies' => array()
                );
                $response = wp_remote_post( $serverUrl, $args );

                if ( is_wp_error( $response ) ) {
                    error_log(json_encode($response));

                    return '<ERROR><ERRORSTRING>' . $response->get_error_message() . '</ERRORSTRING></ERROR>';
                } else {
                    return $response['body'];
                }
        }
}

/**
 *  Used for processing XML Authorisations through the Worldnet Payments XML Gateway.
 *
 *  Basic request is configured on initialisation and optional fields can be configured.
 */
class WorldnetPaymentsGatewayXmlAuthRequest extends WorldnetPaymentsGatewayRequest
{
    private $orderId;
    private $currency;
    private $amount;
    private $dateTime;
    private $autoReady;
    private $description;
    private $email;
    private $applePayLoad;
    private $cardNumber;
    private $trackData;
    private $cardType;
    private $cardExpiry;
    private $cardHolderName;
    private $cvv;
    private $issueNo;
    private $address1;
    private $address2;
    private $postCode;
    private $cardCurrency;
    private $cardAmount;
    private $conversionRate;
    private $terminalType = "2";
    private $transactionType = "7";
    private $avsOnly;
    private $mpiRef;
    private $xid;
    private $cavv;
    private $mobileNumber;
    private $deviceId;
    private $phone;
    private $city;
    private $region;
    private $country;
    private $ipAddress;

    private $multicur = false;
    private $foreignCurInfoSet = false;

    /**
     *  Creates the standard request less optional parameters for processing an XML Transaction
     *  through the Worldnet Payments XML Gateway
     *
     *  @param terminalId Terminal ID provided by Worldnet Payments
     *  @param orderId A unique merchant identifier. Alpha numeric and max size 12 chars.
     *  @param currency ISO 4217 3 Digit Currency Code, e.g. EUR / USD / GBP
     *  @param amount Transaction Amount, Double formatted to 2 decimal places.
     *  @param description Transaction Description
     *  @param email Cardholder e-mail
     *  @param cardNumber A valid Card Number that passes the Luhn Check.
     *  @param cardType
     *  Card Type (Accepted Card Types must be configured in the Merchant Selfcare System.)
     *
     *  Accepted Values :
     *
     *  VISA
     *  MASTERCARD
     *  LASER
     *  SWITCH
     *  SOLO
     *  AMEX
     *  DINERS
     *  MAESTRO
     *  DELTA
     *  ELECTRON
     *
     */
    public function __construct($terminalId,
            $orderId,
            $currency,
            $amount,
            $cardNumber,
            $cardType)
    {
            $this->dateTime = $this->GetFormattedDate();
            parent::__construct($terminalId);
            $this->orderId = $orderId;
            $this->currency = $currency;
            $this->amount = $amount;
            $this->cardNumber = $cardNumber;
            $this->cardType = $cardType;
    }

    public function Amount(){ return $this->amount; }
   /**
     *  Setter for Auto Ready Value
     *
     *  @param autoReady
     *  Auto Ready is an optional parameter and defines if the transaction should be settled automatically.
     *
     *  Accepted Values :
     *
     *  Y   -   Transaction will be settled in next batch
     *  N   -   Transaction will not be settled until user changes state in Merchant Selfcare Section
     */
    public function SetAutoReady($autoReady){ $this->autoReady = $autoReady; }
   /**
     *  Setter for Email Address Value
     *
     *  @param email Alpha-numeric field.
     */
    public function SetEmail($email){ $this->email = $email; }
   /**
     *  Setter for Email Address Value
     *
     *  @param email Alpha-numeric field.
     */
    public function SetDescription($description){ $this->description = $description; }
   /**
     *  Setter for Card Expiry and Card Holder Name values
     *  These are mandatory for non-SecureCard transactions
     *
     *  @param cardExpiry Card Expiry formatted MMYY
     *  @param cardHolderName Card Holder Name
     */
    public function SetNonSecureCardCardInfo($cardExpiry, $cardHolderName)
    {
            $this->cardExpiry = $cardExpiry;
            $this->cardHolderName = $cardHolderName;
    }

    /**
     *  Setter for applePayLoad
     *
     *  @param $applePayLoad
     */
    public function SetApplePayLoad($applePayLoad)
    {
        $this->cardNumber = "";
        $this->applePayLoad = $applePayLoad;
    }

   /**
     *  Setter for Card Verification Value
     *
     *  @param cvv Numeric field with a max of 4 characters.
     */
    public function SetCvv($cvv){ $this->cvv = $cvv; }

   /**
     *  Setter for Issue No
     *
     *  @param issueNo Numeric field with a max of 3 characters.
     */
    public function SetIssueNo($issueNo){ $this->issueNo = $issueNo; }

   /**
     *  Setter for Address Verification Values
     *
     *  @param address1 First Line of address - Max size 20
     *  @param address2 Second Line of address - Max size 20
     *  @param postCode Postcode - Max size 9
     */
    public function SetAvs($address1, $address2, $postCode)
    {
            $this->address1 = $address1;
            $this->address2 = $address2;
            $this->postCode = $postCode;
    }
   /**
     *  Setter for Foreign Currency Information
     *
     *  @param cardCurrency ISO 4217 3 Digit Currency Code, e.g. EUR / USD / GBP
     *  @param cardAmount (Amount X Conversion rate) Formatted to two decimal places
     *  @param conversionRate Converstion rate supplied in rate response
     */
    public function SetForeignCurrencyInformation($cardCurrency, $cardAmount, $conversionRate)
    {
            $this->cardCurrency = $cardCurrency;
            $this->cardAmount = $cardAmount;
            $this->conversionRate = $conversionRate;

            $this->foreignCurInfoSet = true;
    }
   /**
     *  Setter for AVS only flag
     *
     *  @param avsOnly Only perform an AVS check, do not store as a transaction. Possible values: "Y", "N"
     */
    public function SetAvsOnly($avsOnly)
    {
            $this->avsOnly = $avsOnly;
    }
   /**

     *  Setter forPO_SCHEMA_FILE MPI Reference code
     *
     *  @param mpiRef MPI Reference code supplied by Worldnet Payments MPI redirect
     */
    public function SetMpiRef($mpiRef)
    {
            $this->mpiRef = $mpiRef;
    }

    public function SetXid($xid)
    {
        $this->xid = $xid;
    }

    public function SetCavv($cavv)
    {
        $this->cavv = $cavv;
    }

   /**
     *  Setter for Mobile Number
     *
     *  @param mobileNumber Mobile Number of cardholder. If sent an SMS receipt will be sent to them
     */
    public function SetMobileNumber($mobileNumber)
    {
            $this->mobileNumber = $mobileNumber;
    }
   /**
     *  Setter for Device ID
     *
     *  @param deviceId Device ID to identify this source to the XML gateway
     */
    public function SetDeviceId($deviceId)
    {
            $this->deviceId = $deviceId;
    }
   /**
     *  Setter for Phone number
     *
     *  @param phone Phone number of cardholder
     */
    public function SetPhone($phone)
    {
            $this->phone = preg_replace('/\xEE[\x80-\xBF][\x80-\xBF]|\xEF[\x81-\x83][\x80-\xBF]/', '', preg_replace('/\s+/', '', $phone));
    }
   /**
     *  Setter for the cardholders IP address
     *
     *  @param ipAddress IP Address of the cardholder
     */
    public function SetIPAddress($ipAddress)
    {
        $this->ipAddress = $ipAddress;
    }
    /**
     *  Setter for City
     *
     *  @param city Cardholders City
     */
    public function SetCity($city)
    {
        $this->city = $city;
    }
    /**
     *  Setter for Region
     *
     *  @param region Cardholders Region
     */
    public function SetRegion($region)
    {
        $this->region = $region;
    }
    /**
     *  Setter for Country
     *
     *  @param country Cardholders Country
     */
    public function SetCountry($country)
    {
        $this->country = $country;
    }
   /**
     *  Setter for multi-currency value
     *  This is required to be set for multi-currency terminals because the Hash is calculated differently.
     */
    public function SetMultiCur()
    {
            $this->multicur = true;
    }
   /**
     *  Setter to flag transaction as a Mail Order. If not set the transaction defaults to eCommerce
     */
    public function SetMotoTrans()
    {
            $this->terminalType = "1";
            $this->transactionType = "4";
    }
   /**
     *  Setter to flag transaction as a Mail Order. If not set the transaction defaults to eCommerce
     */
    public function SetTrackData($trackData)
    {
            $this->terminalType = "3";
            $this->transactionType = "0";
            $this->cardNumber = "";
            $this->trackData = $trackData;
    }
    /**
     *  Setter for transactionType
     *
     *  @param transactionType
     */
    public function SetTransactionType($transactionType)
    {
        $this->transactionType = $transactionType;
    }
   /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.
     */
    public function SetHash($sharedSecret)
    {
            if(isset($this->multicur) && $this->multicur == true) $this->hash = $this->GetRequestHash($this->terminalId . $this->orderId . $this->currency . $this->amount . $this->dateTime . $sharedSecret);
            else $this->hash = $this->GetRequestHash($this->terminalId . $this->orderId . $this->amount . $this->dateTime . $sharedSecret);
    }
    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
            $this->SetHash($sharedSecret);
            $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
            $response = new WorldnetPaymentsGatewayXmlAuthResponse($responseString);
            return $response;
    }
    public function GenerateXml()
    {
            $requestXML = new DOMDocument("1.0");
            $requestXML->formatOutput = true;
            
            $requestString = $requestXML->createElement("PAYMENT");
            $requestXML->appendChild($requestString);
            
            $this->createDOMElement($requestXML,$requestString,"ORDERID",$this->orderId);
            
            $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);
            
            $this->createDOMElement($requestXML,$requestString,"AMOUNT",$this->amount);
    
            $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);
        

            if($this->applePayLoad !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"APPLEPAYLOAD",$this->applePayLoad);
            } else if($this->trackData !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"TRACKDATA",$this->trackData);
            } else {
                $this->createDOMElement($requestXML,$requestString,"CARDNUMBER",$this->cardNumber);
            }

            $this->createDOMElement($requestXML,$requestString,"CARDTYPE",$this->cardType);

            if($this->cardExpiry !== NULL && $this->cardHolderName !== NULL && $this->trackData == NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"CARDEXPIRY",$this->cardExpiry);

                $this->createDOMElement($requestXML,$requestString,"CARDHOLDERNAME",$this->cardHolderName);
            }

            $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);
            
            $this->createDOMElement($requestXML,$requestString,"CURRENCY",$this->currency);

            if($this->foreignCurInfoSet)
            {
                $dcNode = $requestXML->createElement("FOREIGNCURRENCYINFORMATION");
                $requestString->appendChild($dcNode );

                $this->createDOMElement($requestXML,$dcNode,"CARDCURRENCY",$this->cardCurrency);

                $this->createDOMElement($requestXML,$dcNode,"CARDAMOUNT",$this->cardAmount);

                $this->createDOMElement($requestXML,$dcNode,"CONVERSIONRATE",$this->conversionRate);
            }

            $node = $requestXML->createElement("TERMINALTYPE");
            $requestString->appendChild($node);
            $nodeText = $requestXML->createTextNode($this->terminalType);
            $node->appendChild($nodeText);

            $node = $requestXML->createElement("TRANSACTIONTYPE");
            $requestString->appendChild($node);
            $nodeText = $requestXML->createTextNode($this->transactionType);
            $node->appendChild($nodeText);

            if($this->autoReady !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"AUTOREADY",$this->autoReady);
            }

            if($this->email !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"EMAIL",$this->email);
            }

            if($this->cvv !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"CVV",$this->cvv);
            }

            if($this->issueNo !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"ISSUENO",$this->issueNo);
            }

            if($this->postCode !== NULL && $this->postCode !== "")
            {
                if($this->address1 !== NULL)
                {
                    $this->createDOMElement($requestXML,$requestString,"ADDRESS1",$this->address1);
                }
                if($this->address2 !== NULL)
                {
                    $this->createDOMElement($requestXML,$requestString,"ADDRESS2",$this->address2);
                }

                $this->createDOMElement($requestXML,$requestString,"POSTCODE",$this->postCode);
           
            }

            if($this->avsOnly !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"AVSONLY",$this->avsOnly);
            }

            if($this->description !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"DESCRIPTION",$this->description);
            }

            if($this->mpiRef !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"MPIREF",$this->mpiRef);
            }

            if($this->xid !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"XID",$this->xid);
            }

            if($this->cavv !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"CAVV",$this->cavv);
            }

            if($this->mobileNumber !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"MOBILENUMBER",$this->mobileNumber);
            }

            if($this->deviceId !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"DEVICEID",$this->deviceId);
            }

            if($this->phone !== NULL && strlen($this->phone) >= 6 && strlen($this->phone) <= 20)
            {
                $this->createDOMElement($requestXML,$requestString,"PHONE",$this->phone);
            }

            if($this->city !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"CITY",$this->city);
            }

            if($this->region !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"REGION",$this->region);
            }

            if($this->country !== NULL && $this->country !== "")
            {
                $this->createDOMElement($requestXML,$requestString,"COUNTRY",$this->country);
            }

            if($this->ipAddress !== NULL)
            {
                $this->createDOMElement($requestXML,$requestString,"IPADDRESS",$this->ipAddress);
            }

            return $requestXML->saveXML();
    }
}

/**
  *  Holder class for parsed response. If there was an error there will be an error string
  *  otherwise all values will be populated with the parsed payment response values.
  *
  *  IsError should be checked before accessing any fields.
  *
  *  ErrorString will contain the error if one occurred.
  */
class WorldnetPaymentsGatewayXmlAuthResponse
{
    private $isError = false;
    private $errorString;
    private $responseCode;
    private $responseText;
    private $approvalCode;
    private $authorizedAmount;
    private $dateTime;
    private $avsResponse;
    private $cvvResponse;
    private $uniqueRef;
    private $hash;
    
    public function __construct($responseXml)
    {
            $doc = new DOMDocument();
            $doc->loadXML($responseXml);
            try
            {
                    if (strpos($responseXml, "ERROR"))
                    {
                            $responseNodes = $doc->getElementsByTagName("ERROR");
                            foreach( $responseNodes as $node )
                            {
                                $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                            }
                            $this->isError = true;
                    }
                    else if (strpos($responseXml, "PAYMENTRESPONSE"))
                    {
                            $responseNodes = $doc->getElementsByTagName("PAYMENTRESPONSE");

                            foreach( $responseNodes as $node )
                            {
                                $this->uniqueRef = $node->getElementsByTagName('UNIQUEREF')->item(0)->nodeValue;
                                $this->responseCode = $node->getElementsByTagName('RESPONSECODE')->item(0)->nodeValue;
                                $this->responseText = $node->getElementsByTagName('RESPONSETEXT')->item(0)->nodeValue;
                                $this->approvalCode = $node->getElementsByTagName('APPROVALCODE')->item(0)->nodeValue;
                                $this->authorizedAmount = $node->getElementsByTagName('AUTHORIZEDAMOUNT')->item(0) !== null ? $node->getElementsByTagName('AUTHORIZEDAMOUNT')->item(0)->nodeValue : null;
                                $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                                $this->avsResponse = $node->getElementsByTagName('AVSRESPONSE')->item(0)->nodeValue;
                                $this->cvvResponse = $node->getElementsByTagName('CVVRESPONSE')->item(0)->nodeValue;
                                $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                            }
                    }
                    else
                    {
                            throw new Exception("Invalid Response");
                    }
            }
            catch (Exception $e)
            {
                    $this->isError = true;
                    $this->errorString = $e->getMessage();
            }
    }

    public function IsError(){ return $this->isError; }

    public function ErrorString(){ return $this->errorString; }

    public function ResponseCode(){	return $this->responseCode;	}

    public function ResponseText(){	return $this->responseText;	}

    public function ApprovalCode(){	return $this->approvalCode;	}

    public function AuthorizedAmount(){	return $this->authorizedAmount;	}

    public function DateTime(){	return $this->dateTime;	}

    public function AvsResponse(){ return $this->avsResponse; }

    public function CvvResponse(){ return $this->cvvResponse; }

    public function UniqueRef(){ return $this->uniqueRef; }
    
    public function Hash(){ return $this->hash; }

}


/**
 *  Used for processing XML Refund Authorisations through the Worldnet Payments XML Gateway.
 *
 *  Basic request is configured on initialisation. There are no coptional fields.
 */
class WorldnetPaymentsGatewayXmlRefundRequest extends WorldnetPaymentsGatewayRequest
{
    private $orderId;
    private $uniqueRef;
    private $amount;
    public function Amount()
    {
        return $this->amount;
    }
    private $dateTime;
    private $operator;
    private $reason;
    private $autoReady;

    /**
     *  Creates the refund request for processing an XML Transaction
     *  through the Worldnet Payments XML Gateway
     *
     *  @param terminalId Terminal ID provided by Worldnet Payments
     *  @param orderId A unique merchant identifier. Alpha numeric and max size 12 chars.
     *  @param currency ISO 4217 3 Digit Currency Code, e.g. EUR / USD / GBP
     *  @param amount Transaction Amount, Double formatted to 2 decimal places.
     *  @param operator An identifier for who executed this transaction
     *  @param reason The reason for the refund
     */
    public function __construct($terminalId,
                                $orderId,
                                $amount,
                                $operator,
                                $reason)
    {
        $this->dateTime = $this->GetFormattedDate();
        $this->amount = $amount;
        parent::__construct($terminalId);
        $this->orderId = $orderId;
        $this->operator = $operator;
        $this->reason = $reason;
    }
    /**
     *  Setter for UniqueRef

     *
     *  @param uniqueRef
     *  Unique Reference of transaction returned from gateway in authorisation response
     */
    public function SetUniqueRef($uniqueRef)
    {
        $this->uniqueRef = $uniqueRef;
        $this->orderId = "";
    }
    /**
     *  Setter for Auto Ready Value

     *
     *  @param autoReady
     *  Auto Ready is an optional parameter and defines if the transaction should be settled automatically.
     *
     *  Accepted Values :

     *
     *  Y   -   Transaction will be settled in next batch
     *  N   -   Transaction will not be settled until user changes state in Merchant Selfcare Section
     */
    public function SetAutoReady($autoReady)
    {
        $this->autoReady = $autoReady;
    }
    /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.
     */
    public function SetHash($sharedSecret)
    {
        if($this->uniqueRef !== NULL)
        {
            $this->hash = $this->GetRequestHash($this->terminalId . $this->uniqueRef . $this->amount . $this->dateTime . $sharedSecret);
        } else {
            $this->hash = $this->GetRequestHash($this->terminalId . $this->orderId . $this->amount . $this->dateTime . $sharedSecret);
        }
    }

    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsGatewayXmlRefundResponse($responseString);
        return $response;
    }
    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("REFUND");
        $requestXML->appendChild($requestString);

        if($this->uniqueRef !== NULL)
        {   
            $this->createDOMElement($requestXML,$requestString,"UNIQUEREF",$this->uniqueRef);
        } else {
            $this->createDOMElement($requestXML,$requestString,"ORDERID",$this->orderId);
        }

        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);

        $this->createDOMElement($requestXML,$requestString,"AMOUNT",$this->amount);

        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);

        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        $this->createDOMElement($requestXML,$requestString,"OPERATOR",$this->operator);

        $this->createDOMElement($requestXML,$requestString,"REASON",$this->reason);

        if($this->autoReady !== NULL)
        {
            $this->createDOMElement($requestXML,$requestString,"AUTOREADY",$this->autoReady);
        }

        return $requestXML->saveXML();

    }
}

/**
 *  Holder class for parsed response. If there was an error there will be an error string
 *  otherwise all values will be populated with the parsed payment response values.
 *
 *  IsError should be checked before accessing any fields.
 *
 *  ErrorString will contain the error if one occurred.
 */
class WorldnetPaymentsGatewayXmlRefundResponse
{
    private $isError = false;
    public function IsError()
    {
        return $this->isError;
    }

    private $errorString;
    public function ErrorString()
    {
        return $this->errorString;
    }

    private $responseCode;
    public function ResponseCode()
    {
        return $this->responseCode;
    }

    private $responseText;
    public function ResponseText()
    {
        return $this->responseText;
    }

    private $approvalCode;
    public function OrderId()
    {
        return $this->orderId;
    }

    private $dateTime;
    public function DateTime()
    {
        return $this->dateTime;
    }

    private $uniqueRef;
    public function UniqueRef()
    {
        return $this->uniqueRef;
    }

    private $hash;
    public function Hash()
    {
        return $this->hash;
    }

    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "REFUNDRESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("REFUNDRESPONSE");

                foreach( $responseNodes as $node )
                {
                    $this->responseCode = $node->getElementsByTagName('RESPONSECODE')->item(0)->nodeValue;
                    $this->responseText = $node->getElementsByTagName('RESPONSETEXT')->item(0)->nodeValue;
                    $this->uniqueRef = $node->getElementsByTagName('UNIQUEREF')->item(0)->nodeValue;
                    $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                    $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}


class WorldnetPaymentsGatewayXmlTerminalFeaturesRequest extends WorldnetPaymentsGatewayRequest
{
    private $customFieldLanguage;
    private $dateTime;
    
    public function __construct($terminalId, $customFieldLanguage)
    {
        parent::__construct($terminalId);
        $this->customFieldLanguage = $customFieldLanguage;
        $this->dateTime = $this->GetFormattedDate();
    }

    public function SetHash($sharedSecret)
    {
        $this->hash = $this->GetRequestHash($this->terminalId . $this->customFieldLanguage . $this->dateTime . $sharedSecret);
    }

    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsGatewayXmlTerminalFeaturesResponse($responseString);
        return $response;
    }

    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("TERMINAL_CONFIGURATION");
        $requestXML->appendChild($requestString);

        $this->createDOMElement($requestXML,$requestString,"APP_VERSION","7.10.0");

        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);

        $this->createDOMElement($requestXML,$requestString,"CUSTOM_FIELD_LANGUAGE",$this->customFieldLanguage);
   
        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);

        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        return $requestXML->saveXML();
    }
}

class WorldnetPaymentsGatewayXmlTerminalFeaturesResponse
{
    private $isError = false;
    public function IsError()
    {
        return $this->isError;
    }

    private $errorString;
    public function ErrorString()
    {
        return $this->errorString;
    }

    private $hash;
    public function Hash()
    {
        return $this->hash;
    }

    private $settings;
    public function getSettings()
    {
        return $this->settings;
    }

    public function getNodeTree($node) {
        $settings = array();

        foreach ($node->childNodes as $child) {
            if (!$this->hasChild($child)) {
                if (!isset($settings[$child->nodeName]))
                    $settings[$child->nodeName] = $child->nodeValue;
                else {
                    if(!is_array($settings[$child->nodeName])) {
                        $settings[$child->nodeName] = [$settings[$child->nodeName]];
                        array_push($settings[$child->nodeName], $child->nodeValue);
                    }
                    else
                        array_push($settings[$child->nodeName], $child->nodeValue);
                }
            }
            else {
                if (!isset($settings[$child->nodeName])) {
                    $settings[$child->nodeName] = array();

                    $settings[$child->nodeName] = $this->getNodeTree($child);
                } else {
                    if(!isset($settings[$child->nodeName][0]))
                        $settings[$child->nodeName] = [$settings[$child->nodeName]];

                    array_push($settings[$child->nodeName], $this->getNodeTree($child));
                }
            }
        }

        return $settings;
    }


    public function hasChild($p)
    {
        if ($p->hasChildNodes()) {
            foreach ($p->childNodes as $c) {
                if ($c->nodeType == XML_ELEMENT_NODE)
                    return true;
            }
        }
    }

    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "TERMINAL_CONFIGURATION_RESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("TERMINAL_CONFIGURATION_RESPONSE");

                foreach( $responseNodes as $node )
                {
                    foreach($node->childNodes as $child) {
                        if(!$this->hasChild($child))
                            $this->settings[$child->nodeName] = $child->nodeValue;
                        else {
                            if(!isset($this->settings[$child->nodeName]))
                                $this->settings[$child->nodeName] = array();

                            $this->settings[$child->nodeName] = $this->getNodeTree($child);
                        }
                    }
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}


/**
 *  Used for processing XML SecureCard Registrations through the Worldnet Payments XML Gateway.
 *
 *  Basic request is configured on initialisation and optional fields can be configured.
 */
class WorldnetPaymentsXmlSecureCardRegRequest extends WorldnetPaymentsGatewayRequest
{
    private $merchantRef;
    private $cardNumber;
    private $cardExpiry;
    private $cardHolderName;
    private $dateTime;
    private $dontCheckSecurity;
    private $cvv;
    private $issueNo;
    private $address1;
    private $postcode;
    private $email;
    private $phone;
    private $mpiRef;

    /**
     *  Creates the SecureCard Registration/Update request for processing
     *  through the Worldnet Payments XML Gateway
     *
     *  @param merchantRef A unique card identifier. Alpha numeric and max size 48 chars.
     *  @param terminalId Terminal ID provided by Worldnet Payments
     *  @param cardNumber A valid Card Number that passes the Luhn Check.
     *  @param cardType
     *  Card Type (Accepted Card Types must be configured in the Merchant Selfcare System.)
     *
     *  Accepted Values :

     *
     *  VISA
     *  MASTERCARD
     *  LASER
     *  SWITCH
     *  SOLO
     *  AMEX
     *  DINERS



     *  MAESTRO
     *  DELTA
     *  ELECTRON
     *
     *  @param cardExpiry Card Expiry formatted MMYY
     *  @param cardHolderName Card Holder Name
     */
    public function __construct($merchantRef,
                                $terminalId,
                                $cardNumber,
                                $cardExpiry,
                                $cardType,
                                $cardHolderName)
    {
        $this->dateTime = $this->GetFormattedDate();

        $this->merchantRef = $merchantRef;
        parent::__construct($terminalId);
        $this->cardNumber = $cardNumber;
        $this->cardExpiry = $cardExpiry;
        $this->cardType = $cardType;
        $this->cardHolderName = $cardHolderName;
    }
    /**
     *  Setter for dontCheckSecurity setting
     *
     *  @param dontCheckSecurity can be either "Y" or "N".
     */
    public function SetDontCheckSecurity($dontCheckSecurity)
    {
        $this->dontCheckSecurity = $dontCheckSecurity;
    }

    /**
     *  Setter for Card Verification Value
     *
     *  @param cvv Numeric field with a max of 4 characters.
     */
    public function SetCvv($cvv)
    {
        $this->cvv = $cvv;
    }

    /**
     *  Setter for Issue No
     *
     *  @param issueNo Numeric field with a max of 3 characters.
     */
    public function SetIssueNo($issueNo)
    {
        $this->issueNo = $issueNo;
    }

    /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.
     */
    public function SetHash($sharedSecret)
    {
        $this->hash = $this->GetRequestHash($this->terminalId . $this->merchantRef . $this->dateTime . $this->cardNumber . $this->cardExpiry . $this->cardType . $this->cardHolderName . $sharedSecret);
    }

    /**
     *  Setter for Address1
     *
     *  @param Address1
     */
    public function SetAddress1($address1)
    {
        $this->address1 = $address1;
    }
    /**
     *  Setter for Postcode
     *
     *  @param Postcode
     */
    public function SetPostcode($postcode)
    {
        $this->postcode = $postcode;
    }
    /**
     *  Setter for Email
     *
     *  @param Email
     */
    public function SetEmail($email)
    {
        $this->email = $email;
    }
    /**
     *  Setter for Phone
     *
     *  @param Phone
     */
    public function SetPhone($phone)
    {
        $this->phone = preg_replace('/\xEE[\x80-\xBF][\x80-\xBF]|\xEF[\x81-\x83][\x80-\xBF]/', '', preg_replace('/\s+/', '', $phone));
    }
    /**
     *  Setter for mpiRef
     *
     *  @param MPIRef
     */
    public function SetMpiRef($mpiRef)
    {
        $this->mpiRef = $mpiRef;
    }

    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsXmlSecureCardRegResponse($responseString);
        return $response;
    }

    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("SECURECARDREGISTRATION");
        $requestXML->appendChild($requestString);

        $this->createDOMElement($requestXML,$requestString,"MERCHANTREF",$this->merchantRef);

        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);

        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);

        $this->createDOMElement($requestXML,$requestString,"CARDNUMBER",$this->cardNumber);

        $this->createDOMElement($requestXML,$requestString,"CARDEXPIRY",$this->cardExpiry);

        $this->createDOMElement($requestXML,$requestString,"CARDTYPE",$this->cardType);

        $this->createDOMElement($requestXML,$requestString,"CARDHOLDERNAME",$this->cardHolderName);

        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        if($this->dontCheckSecurity !== NULL)
        {
            $this->createDOMElement($requestXML,$requestString,"DONTCHECKSECURITY",$this->dontCheckSecurity);
        }

        if($this->cvv !== NULL)
        {
            $this->createDOMElement($requestXML,$requestString,"CVV",$this->cvv);
        }

        if($this->issueNo !== NULL)
        {
            $this->createDOMElement($requestXML,$requestString,"ISSUENO",$this->issueNo);
        }

        if($this->address1 !== NULL && $this->address1 !== "")
        {
            $this->createDOMElement($requestXML,$requestString,"ADDRESS1",$this->address1);
        }

        if($this->postcode !== NULL && $this->postcode !== "")
        {
            $this->createDOMElement($requestXML,$requestString,"POSTCODE",$this->postcode);
        }

        if($this->email !== NULL && $this->email !== "")
        {
            $this->createDOMElement($requestXML,$requestString,"EMAIL",$this->email);
        }

        if($this->phone !== NULL && strlen($this->phone) >= 6 && strlen($this->phone) <= 20)
        {
            $this->createDOMElement($requestXML,$requestString,"PHONE",$this->phone);
        }

        if($this->mpiRef !== NULL) { 
            $this->createDOMElement($requestXML,$requestString,"MPIREF",$this->mpiRef); 
        }

        return $requestXML->saveXML();
    }
}



/**
 *  Base holder class for parsed SecureCard response. If there was an error there will be an error string
 *  otherwise all values will be populated with the parsed payment response values.
 */
class WorldnetPaymentsXmlSecureCardResponse
{
    protected $isError = false;
    protected $errorString;
    protected $errorCode;
    protected $merchantRef;
    protected $cardRef;
    protected $dateTime;
    protected $hash;

    public function IsError(){ return $this->isError; }
    public function ErrorString(){ return $this->errorString; }
    public function ErrorCode(){ return $this->errorCode; }
    public function MerchantReference(){ return $this->merchantRef; }
    public function CardReference(){ return $this->cardRef; }
    public function DateTime(){ return $this->dateTime; }
    public function Hash(){ return $this->hash; }
}

/**
 *  Holder class for parsed SecureCard registration response.
 */
class WorldnetPaymentsXmlSecureCardRegResponse extends WorldnetPaymentsXmlSecureCardResponse
{
    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorCode = $node->getElementsByTagName('ERRORCODE')->item(0)->nodeValue;
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "SECURECARDREGISTRATIONRESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("SECURECARDREGISTRATIONRESPONSE");

                foreach( $responseNodes as $node )
                {
                    $this->merchantRef = $node->getElementsByTagName('MERCHANTREF')->item(0)->nodeValue;
                    $this->cardRef = $node->getElementsByTagName('CARDREFERENCE')->item(0)->nodeValue;
                    $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                    $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}



/**
 *  Used for processing XML SecureCard deletion through the Worldnet Payments XML Gateway.
 *
 *  Basic request is configured on initialisation and optional fields can be configured.
 */
class WorldnetPaymentsXmlSecureCardDelRequest extends WorldnetPaymentsGatewayRequest
{
    private $merchantRef;
    private $secureCardCardRef;
    private $dateTime;

    /**
     *  Creates the SecureCard searche request for processing
     *  through the Worldnet Payments XML Gateway
     *
     *  @param merchantRef A unique card identifier. Alpha numeric and max size 48 chars.
     *  @param terminalId Terminal ID provided by Worldnet Payments
     */
    public function __construct($merchantRef,
                                            $terminalId,
                                            $secureCardCardRef)
    {
        $this->dateTime = $this->GetFormattedDate();

        $this->merchantRef = $merchantRef;
        parent::__construct($terminalId);
        $this->secureCardCardRef = $secureCardCardRef;
    }
    /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.

     */
    public function SetHash($sharedSecret)
    {
        $this->hash = $this->GetRequestHash($this->terminalId . $this->merchantRef . $this->dateTime . $this->secureCardCardRef . $sharedSecret);
    }

    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsXmlSecureCardDelResponse($responseString);
        return $response;
    }

    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("SECURECARDREMOVAL");
        $requestXML->appendChild($requestString);

        $this->createDOMElement($requestXML,$requestString,"MERCHANTREF",$this->merchantRef);

        $this->createDOMElement($requestXML,$requestString,"CARDREFERENCE",$this->secureCardCardRef);

        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);

        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);

        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        return $requestXML->saveXML();
    }
}

/**
 *  Holder class for parsed SecureCard search response.
 */
class WorldnetPaymentsXmlSecureCardDelResponse extends WorldnetPaymentsXmlSecureCardResponse
{
    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorCode = $node->getElementsByTagName('ERRORCODE')->item(0)->nodeValue;
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "SECURECARDREMOVALRESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("SECURECARDREMOVALRESPONSE");

                foreach( $responseNodes as $node )
                {
                    $this->merchantRef = $node->getElementsByTagName('MERCHANTREF')->item(0)->nodeValue;
                    $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                    $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}

/**
 *  Used for processing XML Stored Subscription Registrations through the Worldnet Payments XML Gateway.
 *
 *  Basic request is configured on initialisation and optional fields can be configured.
 */
class WorldnetPaymentsXmlStoredSubscriptionRegRequest extends WorldnetPaymentsGatewayRequest
{
    private $merchantRef;
    private $name;
    private $description;
    private $periodType;
    private $length;
    private $recurringAmount;
    private $initialAmount;
    private $type;
    private $onUpdate;
    private $onDelete;
    private $dateTime;

    /**
     *  Creates the SecureCard Registration/Update request for processing
     *  through the Worldnet Payments XML Gateway
     *
     *  @param merchantRef A unique subscription identifier. Alpha numeric and max size 48 chars.
     *  @param terminalId Terminal ID provided by Worldnet Payments
     *  @param secureCardMerchantRef A valid, registered SecureCard Merchant Reference.
     *  @param name Name of the subscription
     *  @param description Card Holder Name
     */
    public function __construct($merchantRef,
                                                    $terminalId,
                                                    $name,
                                                    $description,

                                                    $periodType,
                                                    $length,
                                                    $currency,
                                                    $recurringAmount,
                                                    $initialAmount,
                                                    $type,
                                                    $onUpdate,
                                                    $onDelete)
    {
        $this->dateTime = $this->GetFormattedDate();


        $this->merchantRef = $merchantRef;
        parent::__construct($terminalId);
        $this->name = $name;
        $this->description = $description;
        $this->periodType = $periodType;
        $this->length = $length;
        $this->currency = $currency;
        $this->recurringAmount = $recurringAmount;
        $this->initialAmount = $initialAmount;
        $this->type = $type;
        $this->onUpdate = $onUpdate;
        $this->onDelete = $onDelete;
    }
    /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.
     */
    public function SetHash($sharedSecret)
    {
        $this->hash = $this->GetRequestHash($this->terminalId . $this->merchantRef . $this->dateTime . $this->type . $this->name . $this->periodType . $this->currency . $this->recurringAmount . $this->initialAmount . $this->length . $sharedSecret);
                                            //TERMINALID:MERCHANTREF:DATETIME:TYPE:NAME:PERIODTYPE:CURRENCY:RECURRINGAMOUNT:INITIALAMOUNT:LENGTH:SECRET
    }

    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsXmlStoredSubscriptionRegResponse($responseString);
        return $response;
    }

    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("ADDSTOREDSUBSCRIPTION");
        $requestXML->appendChild($requestString);

        $this->createDOMElement($requestXML,$requestString,"MERCHANTREF",$this->merchantRef);

        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);
        
        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);
        
        $this->createDOMElement($requestXML,$requestString,"NAME",$this->name);

        $this->createDOMElement($requestXML,$requestString,"DESCRIPTION",$this->description);
        
        $this->createDOMElement($requestXML,$requestString,"PERIODTYPE",$this->periodType);

        $this->createDOMElement($requestXML,$requestString,"LENGTH",$this->length);

        $this->createDOMElement($requestXML,$requestString,"CURRENCY",$this->currency);
 
        if($this->type != "AUTOMATIC (WITHOUT AMOUNTS)")
        {
            $this->createDOMElement($requestXML,$requestString,"RECURRINGAMOUNT",$this->recurringAmount);
     
            $this->createDOMElement($requestXML,$requestString,"INITIALAMOUNT",$this->initialAmount);
        }

        $this->createDOMElement($requestXML,$requestString,"TYPE",$this->type);

        $this->createDOMElement($requestXML,$requestString,"ONUPDATE",$this->onUpdate);

        $this->createDOMElement($requestXML,$requestString,"ONDELETE",$this->onDelete);

        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        return $requestXML->saveXML();
    }
}



/**
 *  Used for processing XML Stored Subscription Registrations through the Worldnet Payments XML Gateway.
 *
 *  Basic request is configured on initialisation and optional fields can be configured.
 */
class WorldnetPaymentsXmlStoredSubscriptionUpdRequest extends WorldnetPaymentsGatewayRequest
{
    private $merchantRef;
    private $name;
    private $description;
    private $length;
    private $currency;
    private $recurringAmount;
    private $initialAmount;
    private $type;
    private $onUpdate;
    private $onDelete;
    private $dateTime;

    /**
     *  Creates the SecureCard Registration/Update request for processing
     *  through the Worldnet Payments XML Gateway
     *
     *  @param merchantRef A unique subscription identifier. Alpha numeric and max size 48 chars.
     *  @param terminalId Terminal ID provided by Worldnet Payments
     *  @param secureCardMerchantRef A valid, registered SecureCard Merchant Reference.
     *  @param name Name of the subscription
     *  @param description Card Holder Name
     */
    public function __construct($merchantRef,
                                                    $terminalId,
                                                    $name,
                                                    $description,
                                                    $length,
                                                    $currency,
                                                    $recurringAmount,
                                                    $initialAmount,
                                                    $type,
                                                    $onUpdate,
                                                    $onDelete)
    {
        $this->dateTime = $this->GetFormattedDate();

        $this->merchantRef = $merchantRef;
        parent::__construct($terminalId);
        $this->name = $name;
        $this->description = $description;
        $this->length = $length;
        $this->currency = $currency;
        $this->recurringAmount = $recurringAmount;
        $this->initialAmount = $initialAmount;
        $this->type = $type;
        $this->onUpdate = $onUpdate;
        $this->onDelete = $onDelete;
    }
    /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.
     */
    public function SetHash($sharedSecret)
    {
        $this->hash = $this->GetRequestHash($this->terminalId . $this->merchantRef . $this->dateTime . $this->type . $this->name . $this->currency . $this->recurringAmount . $this->initialAmount . $this->length . $sharedSecret);
    }

    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsXmlStoredSubscriptionUpdResponse($responseString);
        return $response;
    }

    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("UPDATESTOREDSUBSCRIPTION");
        $requestXML->appendChild($requestString);

        $this->createDOMElement($requestXML,$requestString,"MERCHANTREF",$this->merchantRef);

        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);

        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);

        $this->createDOMElement($requestXML,$requestString,"NAME",$this->name);
 
        $this->createDOMElement($requestXML,$requestString,"DESCRIPTION",$this->description);

        $this->createDOMElement($requestXML,$requestString,"LENGTH",$this->length);

        $this->createDOMElement($requestXML,$requestString,"CURRENCY",$this->currency);

        if($this->type != "AUTOMATIC (WITHOUT AMOUNTS)")
        {
            $this->createDOMElement($requestXML,$requestString,"RECURRINGAMOUNT",$this->recurringAmount);
 
            $this->createDOMElement($requestXML,$requestString,"INITIALAMOUNT",$this->initialAmount);
        }

        $this->createDOMElement($requestXML,$requestString,"TYPE",$this->type);

        $this->createDOMElement($requestXML,$requestString,"ONUPDATE",$this->onUpdate);

        $this->createDOMElement($requestXML,$requestString,"ONDELETE",$this->onDelete);

        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        return $requestXML->saveXML();
    }
}

/**
 *  Used for processing XML SecureCard Registrations through the Worldnet Payments XML Gateway.
 *
 *  Basic request is configured on initialisation and optional fields can be configured.
 */
class WorldnetPaymentsXmlStoredSubscriptionDelRequest extends WorldnetPaymentsGatewayRequest
{
    private $merchantRef;
    private $dateTime;

    /**
     *  Creates the SecureCard Registration/Update request for processing
     *  through the Worldnet Payments XML Gateway
     *
     *  @param merchantRef A unique subscription identifier. Alpha numeric and max size 48 chars.
     *  @param terminalId Terminal ID provided by Worldnet Payments
     */
    public function __construct($merchantRef, $terminalId)
    {
        $this->dateTime = $this->GetFormattedDate();
        $this->merchantRef = $merchantRef;
        parent::__construct($terminalId);
    }
    /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.
     */
    public function SetHash($sharedSecret)
    {
        $this->hash = $this->GetRequestHash($this->terminalId . $this->merchantRef . $this->dateTime . $sharedSecret);
    }

    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsXmlStoredSubscriptionDelResponse($responseString);
        return $response;
    }

    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("DELETESTOREDSUBSCRIPTION");
        $requestXML->appendChild($requestString);

        $this->createDOMElement($requestXML,$requestString,"MERCHANTREF",$this->merchantRef);
     
        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);
    
        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);

        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        return $requestXML->saveXML();
    }
}

/**
 *  Base holder class for parsed Subscription response. If there was an error there will be an error string
 *  otherwise all values will be populated with the parsed payment response values.
 */
class WorldnetPaymentsXmlSubscriptionResponse
{
    protected $isError = false;
    protected $errorString;
    protected $errorCode;
    protected $merchantRef;
    protected $dateTime;
    protected $hash;
    
    public function IsError(){ return $this->isError; }

    public function ErrorString(){ return $this->errorString; }
    
    public function ErrorCode(){ return $this->errorCode; }

    public function MerchantReference(){ return $this->merchantRef; }
 
    public function DateTime(){ return $this->dateTime; }

    public function Hash(){ return $this->hash; }
}

/**
 *  Holder class for parsed Stored Subscription registration response.
 */
class WorldnetPaymentsXmlStoredSubscriptionRegResponse extends WorldnetPaymentsXmlSubscriptionResponse
{
    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorCode = $node->getElementsByTagName('ERRORCODE')->item(0)->nodeValue;
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "ADDSTOREDSUBSCRIPTIONRESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("ADDSTOREDSUBSCRIPTIONRESPONSE");

                foreach( $responseNodes as $node )
                {
                    $this->merchantRef = $node->getElementsByTagName('MERCHANTREF')->item(0)->nodeValue;
                    $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                    $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}


/**
 *  Holder class for parsed Stored Subscription update response.
 */
class WorldnetPaymentsXmlStoredSubscriptionUpdResponse extends WorldnetPaymentsXmlSubscriptionResponse
{
    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorCode = $node->getElementsByTagName('ERRORCODE')->item(0)->nodeValue;
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "UPDATESTOREDSUBSCRIPTIONRESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("UPDATESTOREDSUBSCRIPTIONRESPONSE");

                foreach( $responseNodes as $node )
                {
                    $this->merchantRef = $node->getElementsByTagName('MERCHANTREF')->item(0)->nodeValue;
                    $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                    $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}

/**
 *  Holder class for parsed Stored Subscription deletion response.
 */
class WorldnetPaymentsXmlStoredSubscriptionDelResponse extends WorldnetPaymentsXmlSubscriptionResponse
{
    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorCode = $node->getElementsByTagName('ERRORCODE')->item(0)->nodeValue;
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "DELETESTOREDSUBSCRIPTIONRESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("DELETESTOREDSUBSCRIPTIONRESPONSE");

                foreach( $responseNodes as $node )
                {
                    $this->merchantRef = $node->getElementsByTagName('MERCHANTREF')->item(0)->nodeValue;
                    $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                    $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}


class WorldnetPaymentsXmlSubscriptionRegRequest extends WorldnetPaymentsGatewayRequest
{
    private $merchantRef;
    private $storedSubscriptionRef;
    private $secureCardMerchantRef;
    private $name;
    private $description;
    private $periodType;
    private $length;
    private $currency;
    private $recurringAmount;
    private $initialAmount;
    private $type;
    private $startDate;
    private $endDate;
    private $onUpdate;
    private $onDelete;
    private $dateTime;
    private $eDCCDecision;

    private $newStoredSubscription = false;

    private $level2Data = false;
    private $shippingAddressFullname;
    private $shippingAddressAddress1;
    private $shippingAddressAddress2;
    private $shippingAddressCity;
    private $shippingAddressRegion;
    private $shippingAddressPostcode;
    private $shippingAddressCountry;

    public function SetNewStoredSubscriptionValues($name,
                                                   $description,
                                                   $periodType,
                                                   $length,
                                                   $currency,
                                                   $recurringAmount,
                                                   $initialAmount,
                                                   $type,
                                                   $onUpdate,
                                                   $onDelete)
    {
        $this->name = $name;
        $this->description = $description;
        $this->periodType = $periodType;
        $this->length = $length;
        $this->currency = $currency;
        $this->recurringAmount = $recurringAmount;
        $this->initialAmount = $initialAmount;
        $this->type = $type;
        $this->onUpdate = $onUpdate;
        $this->onDelete = $onDelete;

        $this->newStoredSubscription = true;
    }
    public function SetSubscriptionAmounts($recurringAmount,
                                           $initialAmount)
    {
        $this->recurringAmount = $recurringAmount;
        $this->initialAmount = $initialAmount;
    }
    /**
     *  Setter for end date
     *
     *  @param endDate End Date of subscription
     */
    public function SetEndDate($endDate)
    {
        $this->endDate = $endDate;
    }
    /**
     *  Setter for Level 2 Data
     *
     *  @param $shippingAddressFullname
     *  @param $shippingAddressAddress1
     *  @param $shippingAddressAddress2
     *  @param $shippingAddressCity
     *  @param $shippingAddressRegion
     *  @param $shippingAddressPostcode
     *  @param $shippingAddressCountry
     */
    public function SetLevel2Data($shippingAddressFullname, $shippingAddressAddress1, $shippingAddressAddress2, $shippingAddressCity, $shippingAddressRegion, $shippingAddressPostcode, $shippingAddressCountry)
    {
        $this->level2Data = true;
        $this->shippingAddressFullname = $shippingAddressFullname;
        $this->shippingAddressAddress1 = $shippingAddressAddress1;
        $this->shippingAddressAddress2 = $shippingAddressAddress2;
        $this->shippingAddressCity = $shippingAddressCity;
        $this->shippingAddressRegion = $shippingAddressRegion;
        $this->shippingAddressPostcode = $shippingAddressPostcode;
        $this->shippingAddressCountry = $shippingAddressCountry;
    }
    /**
     *  Setter for when the cardholder has accepted the eDCC offering
     *
     *  @param eDCCDecision eDCC decision ("Y" or "N")
     */
    public function EDCCDecision($eDCCDecision)
    {
        $this->eDCCDecision = $eDCCDecision;
    }
    /**
     *  Creates the SecureCard Registration/Update request for processing
     *  through the Worldnet Payments XML Gateway
     *
     *  @param merchantRef A unique subscription identifier. Alpha numeric and max size 48 chars.
     *  @param terminalId Terminal ID provided by Worldnet Payments

     *  @param storedSubscriptionRef Name of the Stored subscription under which this subscription should run
     *  @param secureCardMerchantRef A valid, registered SecureCard Merchant Reference.
     *  @param startDate Card Holder Name
     */
    public function __construct($merchantRef,
                                  $terminalId,
                                  $storedSubscriptionRef,
                                  $secureCardMerchantRef,
                                  $startDate)
    {
        $this->dateTime = $this->GetFormattedDate();

        $this->storedSubscriptionRef = $storedSubscriptionRef;
        $this->secureCardMerchantRef = $secureCardMerchantRef;
        $this->merchantRef = $merchantRef;
        parent::__construct($terminalId);
        $this->startDate = $startDate;
    }
    /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.
     */
    public function SetHash($sharedSecret)
    {
        if($this->newStoredSubscription) $this->hash = $this->GetRequestHash($this->terminalId . $this->merchantRef . $this->secureCardMerchantRef . $this->dateTime . $this->startDate . $sharedSecret);
        else $this->hash = $this->GetRequestHash($this->terminalId . $this->merchantRef . $this->storedSubscriptionRef . $this->secureCardMerchantRef . $this->dateTime . $this->startDate . $sharedSecret);

    }


    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsXmlSubscriptionRegResponse($responseString);
        return $response;
    }

    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("ADDSUBSCRIPTION");
        $requestXML->appendChild($requestString);

        $this->createDOMElement($requestXML,$requestString,"MERCHANTREF",$this->merchantRef);
    
        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);

        if(!$this->newStoredSubscription)
        {
            $this->createDOMElement($requestXML,$requestString,"STOREDSUBSCRIPTIONREF",$this->storedSubscriptionRef);
        }

        $this->createDOMElement($requestXML,$requestString,"SECURECARDMERCHANTREF",$this->secureCardMerchantRef);

        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);

        if($this->recurringAmount != null && $this->recurringAmount != null && !$this->newStoredSubscription)
        {
            $this->createDOMElement($requestXML,$requestString,"RECURRINGAMOUNT",$this->recurringAmount);

            $this->createDOMElement($requestXML,$requestString,"INITIALAMOUNT",$this->initialAmount);
        }

        $this->createDOMElement($requestXML,$requestString,"STARTDATE",$this->startDate);
  
        if($this->endDate != null)
        {
            $this->createDOMElement($requestXML,$requestString,"ENDDATE",$this->endDate);
        }

        if($this->eDCCDecision !== NULL)
        {
            $this->createDOMElement($requestXML,$requestString,"EDCCDECISION",$this->eDCCDecision);
        }
        
        if($this->newStoredSubscription)
        {
            $ssNode = $requestXML->createElement("NEWSTOREDSUBSCRIPTIONINFO");
            $requestString->appendChild($ssNode );

            $this->createDOMElement($requestXML,$ssNode,"MERCHANTREF",$this->storedSubscriptionRef);

            $this->createDOMElement($requestXML,$ssNode,"HASH",$this->hash);

            $this->createDOMElement($requestXML,$ssNode,"NAME",$this->name);

            $this->createDOMElement($requestXML,$ssNode,"DESCRIPTION",$this->description);

            $this->createDOMElement($requestXML,$ssNode,"PERIODTYPE",$this->periodType);

            $this->createDOMElement($requestXML,$ssNode,"LENGTH",$this->length);

            $this->createDOMElement($requestXML,$ssNode,"CURRENCY",$this->currency);
            
            if($this->type != "AUTOMATIC (WITHOUT AMOUNTS)")
            {
                $this->createDOMElement($requestXML,$ssNode,"RECURRINGAMOUNT",$this->recurringAmount);
               
                $this->createDOMElement($requestXML,$ssNode,"INITIALAMOUNT",$this->initialAmount);
          
            }

            $this->createDOMElement($requestXML,$ssNode,"TYPE",$this->type);

            $this->createDOMElement($requestXML,$ssNode,"ONUPDATE",$this->onUpdate);

            $this->createDOMElement($requestXML,$ssNode,"ONDELETE",$this->onDelete);
        }

        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        if($this->level2Data)
        {
            $level2Data = $requestXML->createElement("LEVEL_2_DATA");
            $requestString->appendChild($level2Data);

            $shippingAddress = $requestXML->createElement("SHIPPING_ADDRESS");
            $level2Data->appendChild($shippingAddress);

            $this->createDOMElement($requestXML,$shippingAddress,"FULL_NAME",$this->shippingAddressFullname);

            $this->createDOMElement($requestXML,$shippingAddress,"ADDRESS1",$this->shippingAddressAddress1);

            if($this->shippingAddressAddress2 !== NULL && $this->shippingAddressAddress2 !== "")
            {
                $this->createDOMElement($requestXML,$shippingAddress,"ADDRESS2",$this->shippingAddressAddress2);
            }

            $this->createDOMElement($requestXML,$shippingAddress,"CITY",$this->shippingAddressCity);

            if($this->region !== NULL && $this->region !== "")
            {
                $this->createDOMElement($requestXML,$shippingAddress,"REGION",$this->shippingAddressRegion);
            }

            if($this->shippingAddressPostcode !== NULL && $this->shippingAddressPostcode !== "")
            {
                $this->createDOMElement($requestXML,$shippingAddress,"POSTCODE",$this->shippingAddressPostcode);
            }

            $this->createDOMElement($requestXML,$shippingAddress,"COUNTRY",$this->shippingAddressCountry);
        }
      
        return $requestXML->saveXML();
    }
}


/**
 *  Holder class for parsed Subscription registration response.
 */
class WorldnetPaymentsXmlSubscriptionRegResponse extends WorldnetPaymentsXmlSubscriptionResponse
{
    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorCode = $node->getElementsByTagName('ERRORCODE')->item(0)->nodeValue;
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "ADDSUBSCRIPTIONRESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("ADDSUBSCRIPTIONRESPONSE");

                foreach( $responseNodes as $node )
                {
                    $this->merchantRef = $node->getElementsByTagName('MERCHANTREF')->item(0)->nodeValue;
                    $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                    $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}



/**
 *  Used for processing XML SecureCard Registrations through the Worldnet Payments XML Gateway.
 *
 *  Basic request is configured on initialisation and optional fields can be configured.
 */
class WorldnetPaymentsXmlSubscriptionDelRequest extends WorldnetPaymentsGatewayRequest
{
    private $merchantRef;
    private $dateTime;

    /**
     *  Creates the SecureCard Registration/Update request for processing
     *  through the Worldnet Payments XML Gateway
     *
     *  @param merchantRef A unique subscription identifier. Alpha numeric and max size 48 chars.
     *  @param terminalId Terminal ID provided by Worldnet Payments
     */
    public function __construct($merchantRef, $terminalId)
    {
        $this->dateTime = $this->GetFormattedDate();
        $this->merchantRef = $merchantRef;
        parent::__construct($terminalId);
    }
    /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.
     */
    public function SetHash($sharedSecret)
    {
        $this->hash = $this->GetRequestHash($this->terminalId . $this->merchantRef . $this->dateTime . $sharedSecret);
    }

    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsXmlSubscriptionDelResponse($responseString);
        return $response;
    }

    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("DELETESUBSCRIPTION");
        $requestXML->appendChild($requestString);

        $this->createDOMElement($requestXML,$requestString,"MERCHANTREF",$this->merchantRef);

        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);

        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);

        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        return $requestXML->saveXML();
    }
}

/**
 *  Holder class for parsed Subscription deletion response.
 */
class WorldnetPaymentsXmlSubscriptionDelResponse extends WorldnetPaymentsXmlSubscriptionResponse
{
    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorCode = $node->getElementsByTagName('ERRORCODE')->item(0)->nodeValue;
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "DELETESUBSCRIPTIONRESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("DELETESUBSCRIPTIONRESPONSE");

                foreach( $responseNodes as $node )
                {
                    $this->merchantRef = $node->getElementsByTagName('MERCHANTREF')->item(0)->nodeValue;
                    $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                    $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}



class WorldnetPaymentsXmlSubscriptionCancelRequest extends WorldnetPaymentsGatewayRequest
{
    private $merchantRef;
    private $dateTime;

    public function __construct($merchantRef, $terminalId)
    {
        $this->dateTime = $this->GetFormattedDate();
        $this->merchantRef = $merchantRef;
        parent::__construct($terminalId);
    }
    /**
     *  Setter for hash value
     *
     *  @param sharedSecret
     *  Shared secret either supplied by Worldnet Payments or configured under
     *  Terminal Settings in the Merchant Selfcare System.
     */
    public function SetHash($sharedSecret)
    {
        $this->hash = $this->GetRequestHash($this->terminalId . $this->merchantRef . $this->dateTime . $sharedSecret);
    }

    public function ProcessRequestToGateway($sharedSecret, $serverUrl)
    {
        $this->SetHash($sharedSecret);
        $responseString = $this->GetResponseString($serverUrl, $this->GenerateXml());
        $response = new WorldnetPaymentsXmlSubscriptionCancelResponse($responseString);
        return $response;
    }

    public function GenerateXml()
    {
        $requestXML = new DOMDocument("1.0");
        $requestXML->formatOutput = true;

        $requestString = $requestXML->createElement("CANCELSUBSCRIPTION");
        $requestXML->appendChild($requestString);

        $this->createDOMElement($requestXML,$requestString,"MERCHANTREF",$this->merchantRef);

        $this->createDOMElement($requestXML,$requestString,"TERMINALID",$this->terminalId);

        $this->createDOMElement($requestXML,$requestString,"DATETIME",$this->dateTime);
   
        $this->createDOMElement($requestXML,$requestString,"HASH",$this->hash);

        return $requestXML->saveXML();
    }
}

/**
 *  Holder class for parsed Subscription deletion response.
 */
class WorldnetPaymentsXmlSubscriptionCancelResponse extends WorldnetPaymentsXmlSubscriptionResponse
{
    public function __construct($responseXml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($responseXml);
        try
        {
            if (strpos($responseXml, "ERROR"))
            {
                $responseNodes = $doc->getElementsByTagName("ERROR");
                foreach( $responseNodes as $node )
                {
                    $this->errorCode = $node->getElementsByTagName('ERRORCODE')->item(0)->nodeValue;
                    $this->errorString = $node->getElementsByTagName('ERRORSTRING')->item(0)->nodeValue;
                }
                $this->isError = true;
            }
            else if (strpos($responseXml, "CANCELSUBSCRIPTIONRESPONSE"))
            {
                $responseNodes = $doc->getElementsByTagName("CANCELSUBSCRIPTIONRESPONSE");

                foreach( $responseNodes as $node )
                {
                    $this->merchantRef = $node->getElementsByTagName('MERCHANTREF')->item(0)->nodeValue;
                    $this->dateTime = $node->getElementsByTagName('DATETIME')->item(0)->nodeValue;
                    $this->hash = $node->getElementsByTagName('HASH')->item(0)->nodeValue;
                }
            }
            else
            {
                throw new Exception("Invalid Response");
            }
        }
        catch (Exception $e)
        {
            $this->isError = true;
            $this->errorString = $e->getMessage();
        }
    }
}

?>