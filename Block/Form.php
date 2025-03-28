<?php

namespace GiftGroup\GooglePay\Block;

use Magento\OfflinePayments\Block\Form\AbstractInstruction;

class Form extends AbstractInstruction
{
    /**
     * GooglePay transfer template
     *
     * @var string
     */
    protected $_template = 'GiftGroup_GooglePay::form.phtml';
}
