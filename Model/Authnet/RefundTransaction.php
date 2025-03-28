<?php

namespace GiftGroup\GooglePay\Model\Authnet;

use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\contract\v1\CreditCardType;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\controller\CreateTransactionController;
use GiftGroup\GooglePay\Model\ConfigProvider;
use net\authorize\api\constants\ANetEnvironment;

class RefundTransaction
{
    private $configProvider;

    public function __construct(
        ConfigProvider $configProvider
    ) {
        $this->configProvider = $configProvider;
    }

    public function refund($refTransId, $cardNumber, $amount)
    {
        $resultData = [];
        $merchantAuthentication = new MerchantAuthenticationType();
        $merchantAuthentication->setName($this->configProvider->getAuthnetLoginId());
        $merchantAuthentication->setTransactionKey($this->configProvider->getAuthnetTransactionKey());

        // Set the transaction's refId
        $refId = 'ref' . time();

        // Create the payment data for a credit card
        $creditCard = new CreditCardType();
        $creditCard->setCardNumber($cardNumber);
        // Originally expriration date is not needed.
        // In order to skip the validation a dummy value passed.
        $creditCard->setExpirationDate("XXXX");
        
        $paymentOne = new PaymentType();
        $paymentOne->setCreditCard($creditCard);
        //create a transaction
        $transactionRequest = new TransactionRequestType();
        $transactionRequest->setTransactionType("refundTransaction");
        $transactionRequest->setAmount($amount);
        $transactionRequest->setPayment($paymentOne);
        $transactionRequest->setRefTransId($refTransId);

        $request = new CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequest);
        
        $controller = new CreateTransactionController($request);
        $environment = ANetEnvironment::PRODUCTION;
        if ($this->configProvider->getEnvironment() == 'TEST') {
            $environment = ANetEnvironment::SANDBOX;
        }
        $response = $controller->executeWithApiResponse($environment);

        if ($response != null) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                $tresponse = $response->getTransactionResponse();
                $resultData['status'] = true;   
                if ($tresponse != null && $tresponse->getMessages() != null) {                    
                    $resultData['responseCode'] = $tresponse->getResponseCode();
                    $resultData['authCode'] = $tresponse->getAuthCode();
                    $resultData['txn_id'] = $tresponse->getRefTransID();
                    $resultData['refund_txn_id'] = $tresponse->getTransId();
                    $resultData['accountNumber'] = $tresponse->getAccountNumber();
                    $resultData['accountType'] = $tresponse->getAccountType();                    
                } else {
                    $resultData['status'] = false;
                    if ($tresponse->getErrors() != null) {
                        $resultData['errorCode'] = $tresponse->getErrors()[0]->getErrorCode();
                        $resultData['errorMessage'] = $tresponse->getErrors()[0]->getErrorText();
                    }
                }
            } else {
                $resultData['status'] = false;
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    $resultData['errorCode'] = $tresponse->getErrors()[0]->getErrorCode();
                    $resultData['errorMessage'] = $tresponse->getErrors()[0]->getErrorText();
                } else {
                    $resultData['errorCode'] = $response->getMessages()->getMessage()[0]->getCode();
                    $resultData['errorMessage'] = $response->getMessages()->getMessage()[0]->getText();
                }
            }
        } else {
            $resultData['status'] = false;
            $resultData['errorCode'] = 0;
            $resultData['errorMessage'] = 'No response from payment gateway';
        }

        return $resultData;
    }
}
