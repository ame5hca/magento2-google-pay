<?php

namespace GiftGroup\GooglePay\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'TEST',
                'label' => 'Sandbox'
            ],
            [
                'value' => 'PRODUCTION',
                'label' => 'Production'
            ]
        ];
    }
}
