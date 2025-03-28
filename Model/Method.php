<?php

namespace GiftGroup\GooglePay\Model;

use GiftGroup\GooglePay\Block\Form;
use Magento\Payment\Block\Info\Instructions;
use Magento\Payment\Model\InfoInterface;
use GiftGroup\GooglePay\Model\Authnet\RefundTransaction;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\Transaction;

class Method extends \Magento\Payment\Model\Method\AbstractMethod
{
    public const PAYMENT_METHOD_CODE = 'googlepay';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /**
     * GooglePay payment block paths
     *
     * @var string
     */
    protected $_formBlockType = Form::class;

    /**
     * Instructions block path
     *
     * @var string
     */
    protected $_infoBlockType = Instructions::class;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;

    protected $_canRefund = true;
    
    protected $_canRefundInvoicePartial = true;

    protected $refundTxnCreator;

    protected $orderTxnCreator;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        RefundTransaction $refundTxnCreator,
        OrderTransactionCreator $orderTxnCreator,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->refundTxnCreator = $refundTxnCreator;
        $this->orderTxnCreator = $orderTxnCreator;
    }

    /**
     * Get instructions text from config
     *
     * @return string
     */
    public function getInstructions()
    {
        $instructions = $this->getConfigData('instructions');
        return $instructions !== null ? trim($instructions) : '';
    }

    public function refund(InfoInterface $payment, $amount)
    {
        $additionalInfo = $payment->getAdditionalInformation()['raw_details_info'];
        try {
            $response = $this->refundTxnCreator->refund(
                $additionalInfo['txn_id'], 
                $additionalInfo['acc_no'], 
                $amount
            );
            if (!$response['status']) {
                throw new LocalizedException(__($response['errorMessage']));                
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__("Refund failed. error = %1", $e->getMessage()));            
        }        
         /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $order = $payment->getOrder();
        $creditmemo = $payment->getData('creditmemo');
        $payment->setAdditionalInformation(
            array_replace_recursive($payment->getAdditionalInformation(), $response)
        );
        $payment->setIsTransactionClosed(1);
        $payment->setTransactionAdditionalInfo(
            Transaction::RAW_DETAILS,
            $response
        );
        $payment->setTransactionId($response['refund_txn_id']);
        $payment->setParentTransactionId(null);
        if ($creditmemo
            && $creditmemo->getInvoice()
            && $creditmemo->getInvoice()->getBaseTotalRefunded() < $creditmemo->getInvoice()->getBaseGrandTotal()) {
            $payment->setShouldCloseParentTransaction(0);
        } else {
            $payment->setShouldCloseParentTransaction(1);
        } 
        $this->orderTxnCreator->addRefundTransaction($order, $payment, $response);               
        
        return $this;
    }
}
