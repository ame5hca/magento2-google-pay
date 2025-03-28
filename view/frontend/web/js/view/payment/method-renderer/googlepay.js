define([
    'ko',
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote'
], function (ko, $, Component, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GiftGroup_GooglePay/payment/googlepay'
        },
        googlePaymentObject: null,
        googlePayPaymetToken: null,
        initializedGooglePay: false,
        allowedCardNetworks: [],
        allowedCardAuthMethods: [], 
        initialize: function () {
            var data = this._super();
            self.allowedCardNetworks = ["AMEX", "DISCOVER", "INTERAC", "JCB", "MASTERCARD", "VISA"];
            self.allowedCardAuthMethods = ["PAN_ONLY", "CRYPTOGRAM_3DS"];
            self.googlePaymentObject = new google.payments.api.PaymentsClient({ environment: this.getEnviornment() });
            setTimeout(() => {
                this.onGooglePayLoaded();
            }, 1000);            
            return data;
        },       
        /**
         * Get value of instruction field.
         * @returns {String}
         */
        getInstructions: function () {
            return '';
        },
        getMerchantId: function () {
            return window.checkoutConfig.payment[this.item.method].merchant_id;
        },
        getMerchantName: function () {
            return window.checkoutConfig.payment[this.item.method].merchant_name;
        },
        getEnviornment: function () {
            return window.checkoutConfig.payment[this.item.method].environment;
        },
        getAuthnetMerchantId: function () {
            return window.checkoutConfig.payment[this.item.method].authnet_merchant_id;
        },        
        getBaseRequest: function () {
            console.log('----getBaseRequest-----');
            return {
                apiVersion: 2,
                apiVersionMinor: 0
            };
        },
        getTokenizationSpecification: function () {
            console.log('----getTokenizationSpecification-----');
            return {
                type: 'PAYMENT_GATEWAY',
                parameters: {
                    'gateway': 'authorizenet',
                    'gatewayMerchantId': this.getAuthnetMerchantId()
                }
            };
        },
        getBaseCardPaymentMethod: function () {
            console.log('----getBaseCardPaymentMethod-----');
            console.log(self.allowedCardAuthMethods);
            return {
                type: 'CARD',
                parameters: {
                    allowedAuthMethods: self.allowedCardAuthMethods,
                    allowedCardNetworks: self.allowedCardNetworks
                }
            };
        },
        getCardPaymentMethhod: function () {
            console.log('----getCardPaymentMethhod-----');
            return Object.assign(
                {},
                this.getBaseCardPaymentMethod(),
                {
                    tokenizationSpecification: this.getTokenizationSpecification()
                }
            );
        },
        getGoogleIsReadyToPayRequest: function () {
            console.log('----getGoogleIsReadyToPayRequest-----');
            return Object.assign(
                {},
                this.getBaseRequest(),
                {
                    allowedPaymentMethods: [this.getBaseCardPaymentMethod()]
                }
            );

        },
        getGooglePaymentDataRequest: function () {
            console.log('----getGooglePaymentDataRequest-----');
            const paymentDataRequest = Object.assign({}, this.getBaseRequest());
            paymentDataRequest.allowedPaymentMethods = [this.getCardPaymentMethhod()];
            paymentDataRequest.transactionInfo = this.getGoogleTransactionInfo();
            paymentDataRequest.merchantInfo = {
                // @todo a merchant ID is available for a production environment after approval by Google
                // See {@link https://developers.google.com/pay/api/web/guides/test-and-deploy/integration-checklist|Integration checklist}
                merchantId: this.getMerchantId(),
                merchantName: this.getMerchantName()
            };
            return paymentDataRequest;
        },
        getGooglePaymentsClient: function () {
            return self.googlePaymentObject;
        },
        onGooglePayLoaded: function () {
            let self = this;
            console.log('----onGooglePayLoaded-----');
            const paymentsClient = this.getGooglePaymentsClient();
            paymentsClient.isReadyToPay(this.getGoogleIsReadyToPayRequest())
                .then(function (response) {
                    console.log('----onGooglePayLoaded--then---');
                    console.log(response);
                    if (response.result) {
                        self.addGooglePayButton();
                        // @todo prefetch payment data to improve performance after confirming site functionality
                        // prefetchGooglePaymentData();
                    }
                })
                .catch(function (err) {
                    // show error in developer console for debugging
                    console.error(err);
                    return false;
                });

        },
        addGooglePayButton: function () {
            console.log('----addGooglePayButton-----');
            const paymentsClient = this.getGooglePaymentsClient();
            const button =
                paymentsClient.createButton({
                    onClick: () => { this.onGooglePaymentButtonClicked() },
                    allowedPaymentMethods: [this.getBaseCardPaymentMethod()]
                });
                $('#google_pay_container').empty().append(button);
        },
        getGoogleTransactionInfo: function () {
            console.log('----getGoogleTransactionInfo-----');
            var cartTotals = quote.getTotals();
            return {
                countryCode: quote.billingAddress().countryId,
                currencyCode: cartTotals().quote_currency_code,
                totalPriceStatus: 'FINAL',
                // set to cart total
                totalPrice: String(cartTotals().base_grand_total)
            };
        },
        selectPaymentMethod: function () {
            this.onGooglePayLoaded();
            return this._super();
        },
        prefetchGooglePaymentData: function () {
            var cartTotals = quote.getTotals();
            const paymentDataRequest = this.getGooglePaymentDataRequest();
            // transactionInfo must be set but does not affect cache
            paymentDataRequest.transactionInfo = {
                totalPriceStatus: 'NOT_CURRENTLY_KNOWN',
                currencyCode: cartTotals().base_currency_code
            };
            const paymentsClient = this.getGooglePaymentsClient();
            paymentsClient.prefetchPaymentData(paymentDataRequest);

        },
        onGooglePaymentButtonClicked: function () {
            let self = this;
            console.log('----onGooglePaymentButtonClicked-----');
            const paymentDataRequest = this.getGooglePaymentDataRequest();
            paymentDataRequest.transactionInfo = this.getGoogleTransactionInfo();

            const paymentsClient = this.getGooglePaymentsClient();
            paymentsClient.loadPaymentData(paymentDataRequest)
                .then(function (paymentData) {
                    // handle the response
                    self.processPayment(paymentData);
                })
                .catch(function (err) {
                    // show error in developer console for debugging
                    console.error(err);
                    return false;
                });
            return true;
        },
        processPayment: function (paymentData) {
            var self = this;
            var paymentToken = paymentData.paymentMethodData.tokenizationData.token;
            this.googlePayPaymetToken = window.btoa(paymentToken);
            return self.placeOrder();            
        },
        getData: function () {
            var data = this._super();
            $.extend(
                true,
                data,
                {
                    'additional_data': {
                        'token': this.googlePayPaymetToken
                    }
                }
            );

            return data;
        }
    });
});
