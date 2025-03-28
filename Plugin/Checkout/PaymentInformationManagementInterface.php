<?php

namespace GiftGroup\GooglePay\Plugin\Checkout;

use Magento\Checkout\Api\PaymentInformationManagementInterface as CorePaymentInformationManagementInterface;
use GiftGroup\GooglePay\Model\Method;
use GiftGroup\GooglePay\Model\PlaceOrder;

class PaymentInformationManagementInterface
{
    private $placeOrder;

    public function __construct(
        PlaceOrder $placeOrder
    ) {
        $this->placeOrder = $placeOrder;
    }

    public function beforeSavePaymentInformationAndPlaceOrder(
        CorePaymentInformationManagementInterface $subject,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
        
    ) {
        if ($paymentMethod->getMethod() == Method::PAYMENT_METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $this->placeOrder->beforeAction($additionalData['token'], $cartId);
        }
        return [$cartId, $paymentMethod, $billingAddress];
    }
    
    public function afterSavePaymentInformationAndPlaceOrder(
        CorePaymentInformationManagementInterface $subject,
        $orderId,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
        
    ) {
        if ($paymentMethod->getMethod() == Method::PAYMENT_METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $this->placeOrder->afterAction($additionalData['token'], $cartId, $orderId);
        }
        return $orderId;
    }
}
