<?php

namespace GiftGroup\GooglePay\Model\Authnet;

use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\contract\v1\OpaqueDataType;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\contract\v1\LineItemType;
use net\authorize\api\contract\v1\CustomerAddressType;
use net\authorize\api\contract\v1\ExtendedAmountType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\OrderType;
use GiftGroup\GooglePay\Model\ConfigProvider;

class CreateTransaction
{
    private $merchantAuthType;
    private $opaqueDataType;
    private $paymentType;
    private $lineItemTypeFactory;
    private $extendedAmountType;
    private $transactionReqType;
    private $createTransactionReq;
    private $customerAddressType;
    private $orderType;
    private $configProvider;

    public function __construct(
        MerchantAuthenticationType $merchantAuthType,
        OpaqueDataType $opaqueDataType,
        PaymentType $paymentType,
        LineItemType $lineItemTypeFactory,
        ExtendedAmountType $extendedAmountType,
        TransactionRequestType $transactionReqType,
        CreateTransactionRequest $createTransactionReq,
        CustomerAddressType $customerAddressType,
        OrderType $orderType,
        ConfigProvider $configProvider
    ) {
        $this->merchantAuthType = $merchantAuthType;
        $this->opaqueDataType = $opaqueDataType;
        $this->paymentType = $paymentType;
        $this->lineItemTypeFactory = $lineItemTypeFactory;
        $this->extendedAmountType = $extendedAmountType;
        $this->transactionReqType = $transactionReqType;
        $this->createTransactionReq = $createTransactionReq;
        $this->customerAddressType = $customerAddressType;
        $this->orderType = $orderType;
        $this->configProvider = $configProvider;
    }

    public function execute($token, $order)
    {
        $lineItemsArray = [];
        $merchantAuthentication = $this->merchantAuthType;
        $merchantAuthentication->setName($this->configProvider->getAuthnetLoginId());
        $merchantAuthentication->setTransactionKey($this->configProvider->getAuthnetTransactionKey());

        $refId = 'ref' . time();

        $opaqueData = $this->opaqueDataType;
        $opaqueData->setDataDescriptor("COMMON.GOOGLE.INAPP.PAYMENT");
        $opaqueData->setDataValue($token);

        $paymentType = $this->paymentType;
        $paymentType->setOpaqueData($opaqueData);

        foreach ($order->getAllVisibleItems() as $item) {
            $lineItem = $this->lineItemTypeFactory;
            $lineItem->setItemId($item->getData('product_id'));
            $lineItem->setName(substr($item->getData('name'), 0, 30));
            $lineItem->setDescription($item->getData('name'));
            $lineItem->setQuantity($item->getData('qty'));
            //$lineItem->setUnitPrice($item->getData('price'));
            //$lineItem->setTotalAmount($item->getData('row_total'));
            $lineItemsArray[] = $lineItem;
            unset($lineItem);
        }

        $tax = new ExtendedAmountType();
        $tax->setAmount($order->getData('base_tax_amount'));
        $tax->setName("Tax");
        $tax->setDescription("Tax");
        
        $shippingMethod = new ExtendedAmountType();
        $shippingMethod->setAmount($order->getData('base_shipping_amount'));
        $shippingMethod->setName("Shipping");
        $shippingMethod->setDescription("Shipping");

        $orderType = $this->orderType;
        $orderType->setInvoiceNumber($order->getIncrementId());
        $orderType->setDescription($order->getStore()->getName());

        $billingAddress = $order->getBillingAddress();
        $billing = $this->customerAddressType;
        $billing->setFirstName($billingAddress->getData('firstname'));
        $billing->setLastName($billingAddress->getData('lastname'));
        $billing->setCompany($billingAddress->getData('company'));
        $billing->setAddress($billingAddress->getData('street'));
        $billing->setCity($billingAddress->getData('city'));
        $billing->setState($billingAddress->getData('region'));
        $billing->setZip($billingAddress->getData('postcode'));
        $billing->setCountry($billingAddress->getData('country'));
        
        $shippingAddress = $order->getShippingAddress();
        $shipping = $this->customerAddressType;
        $shipping->setFirstName($shippingAddress->getData('firstname'));
        $shipping->setLastName($shippingAddress->getData('lastname'));
        $shipping->setCompany($shippingAddress->getData('company'));
        $shipping->setAddress($shippingAddress->getData('street'));
        $shipping->setCity($shippingAddress->getData('city'));
        $shipping->setState($shippingAddress->getData('region'));
        $shipping->setZip($shippingAddress->getData('postcode'));
        $shipping->setCountry($shippingAddress->getData('country'));

        $transactionRequestType = $this->transactionReqType;
        $transactionRequestType->setTransactionType("authCaptureTransaction");
        $transactionRequestType->setAmount($order->getGrandTotal());
        $transactionRequestType->setPayment($paymentType);
        $transactionRequestType->setOrder($orderType);
        $transactionRequestType->setBillTo($billing);
        $transactionRequestType->setShipTo($shipping);
        //$transactionRequestType->setLineItems($lineItemsArray);
        $transactionRequestType->setTax($tax);
        $transactionRequestType->setShipping($shippingMethod);

        $request = $this->createTransactionReq;
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);

        $controller = new CreateTransactionController($request);
        $environment = ANetEnvironment::PRODUCTION;
        if ($this->configProvider->getEnvironment() == 'TEST') {
            $environment = ANetEnvironment::SANDBOX;
        }
        $response = $controller->executeWithApiResponse($environment);

        $returnData = [];
        if ($response != null) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    $returnData['status'] = true;
                    $returnData['txn_response_code'] = $tresponse->getResponseCode();
                    $returnData['auth_code'] = $tresponse->getResponseCode();
                    $returnData['txn_id'] = $tresponse->getTransId();
                    $returnData['acc_no'] = substr($tresponse->getAccountNumber(), -4);
                    $returnData['txn_code'] = $tresponse->getMessages()[0]->getCode();
                    $returnData['txn_description'] = $tresponse->getMessages()[0]->getDescription();
                } else {
                    $returnData['status'] = false;
                    $returnData['error_code'] = $tresponse->getErrors()[0]->getErrorCode();
                    $returnData['error_message'] = $tresponse->getErrors()[0]->getErrorText();
                }
            } else {
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    $returnData['status'] = false;
                    $returnData['error_code'] = $tresponse->getErrors()[0]->getErrorCode();
                    $returnData['error_message'] = $tresponse->getErrors()[0]->getErrorText();
                } else {
                    $returnData['status'] = false;
                    $returnData['error_code'] = $response->getMessages()->getMessage()[0]->getCode();
                    $returnData['error_message'] = $response->getMessages()->getMessage()[0]->getText();
                }
            }
        } else {
            $returnData['status'] = false;
            $returnData['error_code'] = 0;
            $returnData['error_message'] = "No response from authnet";
        }

        return $returnData;
    }

    public function fromQuote($token, $quote)
    {
        $lineItemsArray = [];
        $merchantAuthentication = $this->merchantAuthType;
        $merchantAuthentication->setName($this->configProvider->getAuthnetLoginId());
        $merchantAuthentication->setTransactionKey($this->configProvider->getAuthnetTransactionKey());

        $refId = 'ref' . time();

        $opaqueData = $this->opaqueDataType;
        $opaqueData->setDataDescriptor("COMMON.GOOGLE.INAPP.PAYMENT");
        $opaqueData->setDataValue($token);

        $paymentType = $this->paymentType;
        $paymentType->setOpaqueData($opaqueData);

        foreach ($quote->getAllVisibleItems() as $item) {
            $lineItem = $this->lineItemTypeFactory;
            $lineItem->setItemId($item->getData('product_id'));
            $lineItem->setName(substr($item->getData('name'), 0, 30));
            $lineItem->setDescription($item->getData('name'));
            $lineItem->setQuantity($item->getData('qty'));
            //$lineItem->setUnitPrice($item->getData('price'));
            //$lineItem->setTotalAmount($item->getData('row_total'));
            $lineItemsArray[] = $lineItem;
            unset($lineItem);
        }

        $quoteShippingAddress = $quote->getShippingAddress();
        $tax = new ExtendedAmountType();
        $tax->setAmount($quoteShippingAddress->getData('base_tax_amount'));
        $tax->setName("Tax");
        $tax->setDescription("Tax");
        
        $shippingMethod = new ExtendedAmountType();
        $shippingMethod->setAmount($quoteShippingAddress->getData('base_shipping_amount'));
        $shippingMethod->setName("Shipping");
        $shippingMethod->setDescription("Shipping");

        $orderType = $this->orderType;
        $orderType->setInvoiceNumber($quote->getData('reserved_order_id'));
        $orderType->setDescription($quote->getStore()->getName());

        $billingAddress = $quote->getBillingAddress();
        $billing = $this->customerAddressType;
        $billing->setFirstName($billingAddress->getData('firstname'));
        $billing->setLastName($billingAddress->getData('lastname'));
        $billing->setCompany($billingAddress->getData('company'));
        $billing->setAddress($billingAddress->getData('street'));
        $billing->setCity($billingAddress->getData('city'));
        $billing->setState($billingAddress->getData('region'));
        $billing->setZip($billingAddress->getData('postcode'));
        $billing->setCountry($billingAddress->getData('country'));
        
        $shipping = $this->customerAddressType;
        $shipping->setFirstName($quoteShippingAddress->getData('firstname'));
        $shipping->setLastName($quoteShippingAddress->getData('lastname'));
        $shipping->setCompany($quoteShippingAddress->getData('company'));
        $shipping->setAddress($quoteShippingAddress->getData('street'));
        $shipping->setCity($quoteShippingAddress->getData('city'));
        $shipping->setState($quoteShippingAddress->getData('region'));
        $shipping->setZip($quoteShippingAddress->getData('postcode'));
        $shipping->setCountry($quoteShippingAddress->getData('country'));

        $transactionRequestType = $this->transactionReqType;
        $transactionRequestType->setTransactionType("authCaptureTransaction");
        $transactionRequestType->setAmount($quote->getGrandTotal());
        $transactionRequestType->setPayment($paymentType);
        $transactionRequestType->setOrder($orderType);
        $transactionRequestType->setBillTo($billing);
        $transactionRequestType->setShipTo($shipping);
        //$transactionRequestType->setLineItems($lineItemsArray);
        $transactionRequestType->setTax($tax);
        $transactionRequestType->setShipping($shippingMethod);

        $request = $this->createTransactionReq;
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);

        $controller = new CreateTransactionController($request);
        $environment = ANetEnvironment::PRODUCTION;
        if ($this->configProvider->getEnvironment() == 'TEST') {
            $environment = ANetEnvironment::SANDBOX;
        }
        $response = $controller->executeWithApiResponse($environment);

        $returnData = [];
        if ($response != null) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    $returnData['status'] = true;
                    $returnData['txn_response_code'] = $tresponse->getResponseCode();
                    $returnData['auth_code'] = $tresponse->getResponseCode();
                    $returnData['txn_id'] = $tresponse->getTransId();
                    $returnData['acc_no'] = substr($tresponse->getAccountNumber(), -4);
                    $returnData['txn_code'] = $tresponse->getMessages()[0]->getCode();
                    $returnData['txn_description'] = $tresponse->getMessages()[0]->getDescription();
                } else {
                    $returnData['status'] = false;
                    $returnData['error_code'] = $tresponse->getErrors()[0]->getErrorCode();
                    $returnData['error_message'] = $tresponse->getErrors()[0]->getErrorText();
                }
            } else {
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    $returnData['status'] = false;
                    $returnData['error_code'] = $tresponse->getErrors()[0]->getErrorCode();
                    $returnData['error_message'] = $tresponse->getErrors()[0]->getErrorText();
                } else {
                    $returnData['status'] = false;
                    $returnData['error_code'] = $response->getMessages()->getMessage()[0]->getCode();
                    $returnData['error_message'] = $response->getMessages()->getMessage()[0]->getText();
                }
            }
        } else {
            $returnData['status'] = false;
            $returnData['error_code'] = 0;
            $returnData['error_message'] = "No response from authnet";
        }

        return $returnData;
    }
}
