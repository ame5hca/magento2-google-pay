<?php

namespace GiftGroup\GooglePay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\Method\AbstractMethod;

class CheckoutConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    protected $methodCode = Method::PAYMENT_METHOD_CODE;

    /**
     * @var AbstractMethod[]
     */
    protected $methods = [];

    protected $configProvider;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        ConfigProvider $configProvider
    ) {
        $this->configProvider = $configProvider;
        $this->methods[$this->methodCode] = $paymentHelper->getMethodInstance(
            $this->methodCode
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        if ($this->methods[$this->methodCode]->isAvailable()) {
            $config['payment'][$this->methodCode]['merchant_id'] = $this->configProvider->getGooglePayMerchantId();
            $config['payment'][$this->methodCode]['merchant_name'] = $this->configProvider->getGooglePayMerchantName();
            $config['payment'][$this->methodCode]['environment'] = $this->configProvider->getEnvironment();
            $config['payment'][$this->methodCode]['authnet_merchant_id'] = $this->configProvider->getAuthnetMerchantId();
        }
        return $config;
    }
}
