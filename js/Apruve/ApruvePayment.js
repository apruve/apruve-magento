var ApruvePayment = Class.create();

ApruvePayment.prototype = {
    initialize: function (hash, pr, updatedShippingUrl) {
        if (!apruve) {
            return false;
        }

        this._checkShipping(pr, updatedShippingUrl);

        apruve.logoSrc = '';
        apruve.secureHash = hash;
        apruve.paymentRequest = pr;
        this._onLoad();
    },

    _onLoad: function () {
        if ($('apruveDiv') && !$('apruveBtn') && typeof(apruve) == 'object') {
            apruve.loadButton();
            this._resetApruveRadio();
            this._prepareApruve();
            this._registerCallbacks();

        }
    },

    _prepareApruve: function () {
        $('p_method_apruvepayment').observe('click', function () {
            apruve.startCheckout();
        });

    },

    _registerCallbacks: function () {
        var self = this;
        apruve.registerApruveCallback(apruve.APRUVE_COMPLETE_EVENT, function () {
            self._resetApruveRadio();
        });

        apruve.registerApruveCallback(apruve.APRUVE_CLOSED_EVENT, function () {
            self._resetApruveRadio();
        });
    },


    _resetApruveRadio: function () {
        if (!apruve.paymentRequestId) {
            document.getElementById("p_method_apruvepayment").checked = false;
            document.getElementById("payment_form_apruvepayment").style.display = 'none';
            document.getElementById("aprt").value = '';
        } else {
            document.getElementById("aprt").value = apruve.paymentRequestId;
            var radio = document.getElementById("p_method_apruvepayment");
            if (!radio.checked) {
                radio.checked = true;
                document.getElementById("payment_form_apruvepayment").style.display = '';
                document.getElementById("aprt").disabled = false;
            }
        }
    },


    _checkShipping: function (pr, updatedShippingUrl) {
        if (apruve.paymentRequestId && !(apruve.paymentRequest.shipping_cents == pr.shipping_cents)) {
            this._setShippingUpdated(updatedShippingUrl);
        }
    },

    _setShippingUpdated: function (updatedShippingUrl) {
        var self = this;
        new Ajax.Request(
            updatedShippingUrl,
            {
                method: 'post',
                onSuccess: function(response) {
                    if (!response.responseText) {
                        apruve.paymentRequestId = '';
                        self._resetApruveRadio();
                        alert('Failed to update shipping cost. Please try to resubmit your order with apruve');
                    }
                },
                onFailure: function() {
                    self._resetApruveRadio();
                }
            }
        )
    }
};
