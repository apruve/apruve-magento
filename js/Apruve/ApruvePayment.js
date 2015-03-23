var ApruvePayment = Class.create();

ApruvePayment.prototype = {
    initialize: function (hash, pr) {
        if (!apruve) {
            return false;
        }

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
    }
};
