<?php

namespace GiftGroup\GooglePay\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider
{
    public $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function isActive()
    {
        return $this->scopeConfig->getValue(
            'payment/googlepay/active',
            ScopeInterface::SCOPE_STORE,
        );
    }
    
    public function getEnvironment()
    {
        return $this->scopeConfig->getValue(
            'payment/googlepay/environment',
            ScopeInterface::SCOPE_STORE,
        );
    }
    
    public function getAuthnetMerchantId()
    {
        return $this->scopeConfig->getValue(
            'payment/googlepay/authnet_merchant_id',
            ScopeInterface::SCOPE_STORE,
        );
    }
    
    public function getAuthnetLoginId()
    {
        return $this->scopeConfig->getValue(
            'payment/googlepay/authnet_login_id',
            ScopeInterface::SCOPE_STORE,
        );
    }
    
    public function getAuthnetTransactionKey()
    {
        return $this->scopeConfig->getValue(
            'payment/googlepay/authnet_transaction_key',
            ScopeInterface::SCOPE_STORE,
        );
    }
    
    public function getGooglePayMerchantId()
    {
        return $this->scopeConfig->getValue(
            'payment/googlepay/googlepay_merchant_id',
            ScopeInterface::SCOPE_STORE,
        );
    }
    
    public function getGooglePayMerchantName()
    {
        return $this->scopeConfig->getValue(
            'payment/googlepay/googlepay_merchant_name',
            ScopeInterface::SCOPE_STORE,
        );
    }
}
