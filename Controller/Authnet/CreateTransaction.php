<?php

namespace GiftGroup\GooglePay\Controller\Authnet;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSesssion;
use Magento\Checkout\Model\GuestPaymentInformationManagement;
use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Sales\Api\OrderRepositoryInterface;
use GiftGroup\GooglePay\Model\Authnet\CreateTransaction as CreateTransactionApi;
use Magento\Sales\Api\OrderManagementInterface;
use GiftGroup\GooglePay\Model\OrderTransactionCreator;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;

class CreateTransaction implements HttpPostActionInterface
{
    private $resultJsonFactory;
    private $request;
    private $customerSession;
    private $guestPaymentInfoManagement;
    private $customerPaymentInfoManagement;
    private $orderRepository;
    private $createTransactionApi;
    private $orderManagement;
    private $orderTxnCreator;
    private $cartRepository;
    private $paymentInterfaceFactory;
    private $checkoutSession;

    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        CustomerSession $customerSession,
        GuestPaymentInformationManagement $guestPaymentInfoManagement,
        PaymentInformationManagement $customerPaymentInfoManagement,
        OrderRepositoryInterface $orderRepository,
        CreateTransactionApi $createTransactionApi,
        OrderManagementInterface $orderManagement,
        OrderTransactionCreator $orderTxnCreator,
        CartRepositoryInterface $cartRepository,
        PaymentInterfaceFactory $paymentInterfaceFactory,
        CheckoutSesssion $checkoutSession
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->customerSession = $customerSession;
        $this->guestPaymentInfoManagement = $guestPaymentInfoManagement;
        $this->customerPaymentInfoManagement = $customerPaymentInfoManagement;
        $this->orderRepository = $orderRepository;
        $this->createTransactionApi = $createTransactionApi;
        $this->orderManagement = $orderManagement;
        $this->orderTxnCreator = $orderTxnCreator;
        $this->cartRepository = $cartRepository;
        $this->paymentInterfaceFactory = $paymentInterfaceFactory;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $data = $this->request->getParams();
        if (empty($data['token'])) {
            return $resultJson->setData(
                [
                    'status' => 'false',
                    'responseText' => __('Payment failed, invalid response from google pay. Please try again later.')
                ]
            );
        }
        try {
            $quoteId = $this->orderTxnCreator->getQuoteIdFromMaskedId($data['cartId']);
            if (empty($quoteId)) {
                return $resultJson->setData(
                    [
                        'status' => 'false',
                        'responseText' => __('Something wrong with the order, unable to load the cart.')
                    ]
                );
            }
            $cart = $this->cartRepository->get($quoteId);
        } catch (\Exception $e) {
            return $resultJson->setData(
                [
                    'status' => 'false',
                    'responseText' => __('Something wrong with the order, please contact support.')
                ]
            );
        }
        $payment = $this->paymentInterfaceFactory->create($data['paymentMethod']);
        $payment->setPoNumber($data['paymentMethod']['po_number']);
        $payment->setMethod($data['paymentMethod']['method']);
        $payment->setAdditionalData($data['paymentMethod']['additional_data']);
        $quote = $this->checkoutSession->getQuote();
        $billingaddress = $quote->getBillingAddress();
        $cart->setBillingAddress($billingaddress);
        $this->cartRepository->save($cart);
        
        try {
            if ($this->customerSession->isLoggedIn()) {
                $orderId = $this->customerPaymentInfoManagement->savePaymentInformationAndPlaceOrder(
                    $data['cartId'],
                    $payment,
                    $billingaddress
                );
            } else {
                $orderId = $this->guestPaymentInfoManagement->savePaymentInformationAndPlaceOrder(
                    $data['cartId'],
                    $data['email'],
                    $payment,
                    $billingaddress
                );
            }
        } catch (\Exception $e) {
            return $resultJson->setData(
                [
                    'status' => 'false',
                    'responseText' => $e->getMessage()
                ]
            );
        }        
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            return $resultJson->setData(
                [
                    'status' => 'false',
                    'responseText' => __('Something wrong with the order, please contact support.')
                ]
            );
        }
        try {
            $txnResponse = $this->createTransactionApi->execute($data, $order);
        } catch (\Exception $e) {
            return $resultJson->setData(
                [
                    'status' => 'false',
                    'responseText' => __('Payment failed. Unable to connect to authnet. Please try again.')
                ]
            );
        }
        if (!$txnResponse['status']) {
            $order->addCommentToStatusHistory('Payment failed. Error code = ' . $txnResponse['error_code'] . ', error message = ' . $txnResponse['error_message']);
            $this->orderRepository->save($order);
            $this->orderManagement->cancel($order->getEntityId());

            return $resultJson->setData(
                [
                    'status' => 'false',
                    'responseText' => $txnResponse['error_message']
                ]
            );
        }
        /* $order->addCommentToStatusHistory(
            $txnResponse['txn_description'] . '. Txn id = ' . $txnResponse['txn_id'] . ', amount = ' . $order->getGrandTotal()
        ); */
        unset($txnResponse['staus']);
        $this->orderTxnCreator->addTransaction($order, $txnResponse);

        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/googlepay.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info(print_r($txnResponse, true));

        return $resultJson->setData(
            ['status' => 'true', 'responseText' => 'Order successfull']
        );
    }
}
