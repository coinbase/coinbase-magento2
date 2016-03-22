/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'coinbase',
                component: 'Coinbase_Magento2PaymentGateway/js/view/payment/method-renderer/coinbase-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
