<?php

namespace GiftGroup\GooglePay\Model;

use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class OrderTransactionCreator
{
    private $transactionBuilder;

    private $maskedQuoteIdToId;

    public function __construct(
        BuilderInterface $transactionBuilder,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToId
    ) {
        $this->transactionBuilder = $transactionBuilder;
        $this->maskedQuoteIdToId = $maskedQuoteIdToId;
    }

    public function addTransaction($order, $paymentData)
    {
        try {
            //get payment object from order object
            $payment = $order->getPayment();
            $payment->setMethod(Method::PAYMENT_METHOD_CODE); 
            $payment->setLastTransId($paymentData['txn_id']);
            $payment->setTransactionId($paymentData['txn_id']);
            $payment->setIsTransactionClosed(0);
            $payment->setAdditionalInformation(
                [Transaction::RAW_DETAILS => $paymentData]
            );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );
            $message = __('Captured amount is %1.', $formatedPrice);
            //get the object of builder class
            $trans = $this->transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentData['txn_id'])
                ->setAdditionalInformation(
                    [Transaction::RAW_DETAILS => $paymentData]
                )
                ->setFailSafe(true)
                //build method creates the transaction and returns the object
                ->build(Transaction::TYPE_CAPTURE);
            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->setPayment($payment);
            $order->save();
            return  $transaction->save()->getTransactionId();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function addRefundTransaction($order, $payment, $paymentData)
    {
        try {
            $payment->setMethod(Method::PAYMENT_METHOD_CODE); 
            //get the object of builder class
            $trans = $this->transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentData['txn_id'])
                ->setAdditionalInformation(
                    [Transaction::RAW_DETAILS => $paymentData]
                )
                ->setFailSafe(true)
                //build method creates the transaction and returns the object
                ->build(Transaction::TYPE_REFUND);            
            $payment->save();
            $order->save();
            return  $transaction->save()->getTransactionId();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getQuoteIdFromMaskedId($maskedId)
    {
        try {
            $id = $this->maskedQuoteIdToId->execute($maskedId);
        } catch (\Exception $exception) {
            return null;
        }
        return $id;
    }
}
