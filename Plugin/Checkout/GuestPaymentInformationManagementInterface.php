<?php

namespace GiftGroup\GooglePay\Plugin\Checkout;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface as CoreGuestPaymentInformationManagementInterface;
use GiftGroup\GooglePay\Model\Method;
use GiftGroup\GooglePay\Model\PlaceOrder;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GuestPaymentInformationManagementInterface
{
    private $placeOrder;

    private $maskedQuoteIdToQuoteId;

    public function __construct(
        PlaceOrder $placeOrder,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
    ) {
        $this->placeOrder = $placeOrder;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
    }

    public function beforeSavePaymentInformationAndPlaceOrder(
        CoreGuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
        
    ) {
        if ($paymentMethod->getMethod() == Method::PAYMENT_METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
            $this->placeOrder->beforeAction($additionalData['token'], $quoteId);
        }
        return [$cartId, $email, $paymentMethod, $billingAddress];
    }
    
    public function afterSavePaymentInformationAndPlaceOrder(
        CoreGuestPaymentInformationManagementInterface $subject,
        $orderId,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
        
    ) {
        if ($paymentMethod->getMethod() == Method::PAYMENT_METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
            $this->placeOrder->afterAction($additionalData['token'], $quoteId, $orderId);
        }
        return $orderId;
    }
}
