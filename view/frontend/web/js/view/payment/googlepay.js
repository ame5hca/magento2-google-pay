define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push(
        {
            type: 'googlepay',
            component: 'GiftGroup_GooglePay/js/view/payment/method-renderer/googlepay'
        }
    );

    return Component.extend({});
});
