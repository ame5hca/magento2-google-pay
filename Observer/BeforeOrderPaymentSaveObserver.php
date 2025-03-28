<?php

declare(strict_types=1);

namespace GiftGroup\GooglePay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use GiftGroup\GooglePay\Model\Method;

/**
 * Sets payment additional information.
 */
class BeforeOrderPaymentSaveObserver implements ObserverInterface
{
    /**
     * Sets current instructions for remilty payment
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $observer->getEvent()->getPayment();        
        if ($payment->getMethod() == Method::PAYMENT_METHOD_CODE
            && empty($payment->getAdditionalInformation('instructions'))) {
            $payment->setAdditionalInformation(
                'instructions',
                $payment->getMethodInstance()->getConfigData(
                    'instructions',
                    $payment->getOrder()->getStoreId()
                )
            );
        }
    }
}
