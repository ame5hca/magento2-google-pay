<?php

namespace GiftGroup\GooglePay\Model;

use GiftGroup\GooglePay\Model\Authnet\CreateTransaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order;
use Magento\Quote\Api\CartRepositoryInterface;
use GiftGroup\GooglePay\Logger\Logger;
use Magento\Checkout\Model\Session as CheckoutSession;

class PlaceOrder
{
    private $createTransaction;

    private $orderRepository;

    private $orderTxnCreator;

    protected $invoiceService;

    protected $invoiceSender;

    protected $transaction;

    protected $cartRepository;

    protected $logger;

    protected $checkoutSession;

    public function __construct(
        CreateTransaction $createTransaction,
        OrderRepositoryInterface $orderRepository,
        OrderTransactionCreator $orderTxnCreator,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Transaction $transaction,
        CartRepositoryInterface $cartRepository,
        Logger $logger,
        CheckoutSession $checkoutSession
    ) {
        $this->createTransaction = $createTransaction;
        $this->orderRepository = $orderRepository;
        $this->orderTxnCreator = $orderTxnCreator;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transaction = $transaction;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
    }

    public function beforeAction($googlePayToken, $quoteId)
    {
        if (empty($googlePayToken) || empty($quoteId)) {
            $this->logger->info('Empty token or quoteid. Quote id = ' . $quoteId);
            throw new LocalizedException(
                __('Google pay error: Payment failed, missing token or quote id.')
            );
        }
        try {
            $quote = $this->cartRepository->get($quoteId);
        } catch (\Exception $e) {
            $this->logger->info('Error loading quote. QuoteId = ' . $quoteId . ', Error = ' . $e->getMessage());
            throw new LocalizedException(
                __("Order couldn't complete, error in cart.")
            );
        }
        $txnResponse = [];
        try {
            $txnResponse = $this->createTransaction->fromQuote($googlePayToken, $quote);
        } catch (\Exception $e) {
            $this->logger->info('Error authnet create trasaction. QuoteId = ' . $quoteId . ', Error = ' . $e->getMessage() . ', Txn response below.');
            $this->logger->info(print_r($txnResponse, true));
            throw new LocalizedException(
                __('Failed to complete payment, error while processing with authnet.')
            );
        }
        if (!$txnResponse['status']) {
            $this->logger->info('Authnet payment failed. QuoteId = ' . $quoteId . '. Txn response is below');
            $this->logger->info(print_r($txnResponse, true));
            throw new LocalizedException(
                __('Payment failed. ' . $txnResponse['error_message'])
            );
        }
        $txnResponse['quote_id'] = $quoteId;
        $txnResponse['token'] = $googlePayToken;
        $this->checkoutSession->setAuthnetTxnResponse($txnResponse);

        return true;
    }

    public function afterAction($googlePayToken, $quoteId, $orderId)
    {
        if (empty($googlePayToken) || empty($orderId)) {
            $this->unsetTxnData();
            $this->logger->info('AfterAction : Payment failed, missing token or order id. OrderId = ' . $orderId);
            throw new LocalizedException(
                __('Payment failed, missing token or order id.')
            );
        }
        $txnResponse = $this->checkoutSession->getAuthnetTxnResponse();
        if (empty($txnResponse)) {
            $this->logger->info('AfterAction : empty txn details array. OrderId = ' . $orderId);
            throw new LocalizedException(
                __('Authnet payment response missing.')
            );
        }
        if (($txnResponse['quote_id'] != $quoteId) || ($googlePayToken != $txnResponse['token'])) {
            $this->unsetTxnData();
            $this->logger->info('AfterAction : Data mismatch OrderId = ' . $orderId . ', quoteId = ' . $quoteId . ', gpaytoken = ' . $googlePayToken . '. Txn response below');
            $this->logger->info(print_r($txnResponse, true));
            throw new LocalizedException(
                __('Payment success, but failed to create order due to data mismatch. Please contact customer support. Payment txn id = ' . $txnResponse['txn_id'])
            );
        }
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            $this->unsetTxnData();
            $this->logger->info('AfterAction : Failed to load order OrderId = ' . $orderId . ', Error = ' . $e->getMessage());
            throw new LocalizedException(
                __('Payment success, but failed to complete the order. Please contact support. Order id = ' . $orderId)
            );
        }
        if ($order->getState() != 'new') {
            $this->unsetTxnData();
            $this->logger->info('AfterAction : Order is not new status OrderId = ' . $orderId);
            throw new LocalizedException(
                __('Order already paid or completed. Order id = ' . $order->getIncrementId())
            );
        }
        unset($txnResponse['staus']);
        unset($txnResponse['token']);
        try {
            $this->orderTxnCreator->addTransaction($order, $txnResponse);
        } catch (\Exception $e) {
            $this->unsetTxnData();
            $this->logger->info('AfterAction : error creating order transaction OrderId = ' . $orderId . ', Error = ' . $e->getMessage());
            return false;
        }
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setTransactionId($txnResponse['txn_id']);
            $invoice->register();
            $invoice->save();

            $transactionSave =
                $this->transaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
            $this->invoiceSender->send($invoice);

            $order->addCommentToStatusHistory(
                __('Notified customer about invoice creation #%1.', $invoice->getId())
            )->setIsCustomerNotified(true);
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus(Order::STATE_PROCESSING);
            $this->orderRepository->save($order);
        }
        $this->unsetTxnData();
    }

    private function unsetTxnData()
    {
        $this->checkoutSession->unsAuthnetTxnResponse();
        return;
    }
}
