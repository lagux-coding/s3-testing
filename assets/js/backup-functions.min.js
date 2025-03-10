window.S3 = window.S3 || {};
window.S3.Functions = window.S3.Functions || {};

(
    function iife (S3, $)
    {
        'use strict'

        S3.Functions = {
            removeMessages: function() {},
            printMessageError: function (message, container) {},
            printMessageSuccess: function (message, container) {},
            makeConstant: function (value) {
                return {
                    value: value,
                    writable: false,
                    configurable: false,
                    enumerable: false,
                };
            }
        };

    } (window.S3, window.jQuery)
)
