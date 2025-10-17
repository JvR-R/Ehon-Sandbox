/**
 * @NScriptName CashRefundRestlet
 * @NApiVersion 2.x
 * @NScriptType Restlet
 */
define(['N/record', 'N/error', 'N/log'], function(record, error, log) {

    /**
     * Function to handle POST requests
     * @param {Object} requestBody - The JSON payload sent in the request
     * @returns {Object} - Response object indicating success or failure
     */
    function doPost(requestBody) {
        try {
            // Validate required fields
            var requiredFields = ['cashSaleId', 'entityId', 'items'];
            for (var i = 0; i < requiredFields.length; i++) {
                if (!requestBody[requiredFields[i]]) {
                    throw error.create({
                        name: 'MISSING_REQUIRED_FIELDS',
                        message: 'Missing required field: ' + requiredFields[i],
                        notifyOff: false
                    });
                }
            }

            // Log the received request body
            log.debug({
                title: 'Received Request Body',
                details: JSON.stringify(requestBody)
            });

            // Transform the Cash Sale into a Refund
            var refundRecord = record.transform({
                fromType: record.Type.CASH_SALE,
                fromId: requestBody.cashSaleId,
                toType: record.Type.CASH_REFUND,
                isDynamic: true
            });

            // Set additional fields if provided
            if (requestBody.tranDate) {
                var parts = requestBody.tranDate.split('/');
                if (parts.length !== 3) {
                    throw error.create({
                        name: 'INVALID_DATE_FORMAT',
                        message: 'tranDate is not in DD/MM/YYYY format',
                        notifyOff: false
                    });
                }
                var day = parseInt(parts[0], 10);
                var month = parseInt(parts[1], 10) - 1; // JavaScript months are 0-based
                var year = parseInt(parts[2], 10);
                var dateObj = new Date(year, month, day);
                if (isNaN(dateObj.getTime())) {
                    throw error.create({
                        name: 'INVALID_DATE_VALUE',
                        message: 'Invalid date object created from tranDate',
                        notifyOff: false
                    });
                }
                // Log the date object
                log.debug({
                    title: 'Parsed tranDate',
                    details: dateObj.toString()
                });

                refundRecord.setValue({
                    fieldId: 'trandate',
                    value: dateObj
                });
            }

            if (requestBody.memo) {
                refundRecord.setValue({
                    fieldId: 'memo',
                    value: requestBody.memo
                });
            }

            // Link to Cash Sale using 'createdfrom'
            refundRecord.setValue({
                fieldId: 'createdfrom',
                value: requestBody.cashSaleId
            });
            log.debug({
                title: 'Created From Field Set To',
                details: requestBody.cashSaleId
            });

            // Add items
            requestBody.items.forEach(function(item, index) {
                // Validate required fields in each item
                if (!item.itemId || typeof item.quantity === 'undefined' || typeof item.rate === 'undefined') {
                    throw error.create({
                        name: 'INVALID_ITEM_FIELDS',
                        message: 'Each item must have itemId, quantity, and rate',
                        notifyOff: false
                    });
                }

                // Ensure quantity is negative
                if (item.quantity >= 0) {
                    throw error.create({
                        name: 'INVALID_QUANTITY',
                        message: 'Quantity must be negative for refunds. Item ID: ' + item.itemId,
                        notifyOff: false
                    });
                }

                // Ensure rate is positive
                if (item.rate < 0) {
                    throw error.create({
                        name: 'INVALID_RATE',
                        message: 'Rate must be positive for refunds. Item ID: ' + item.itemId,
                        notifyOff: false
                    });
                }

                // Use refundRecord instead of cashRefund
                refundRecord.selectNewLine({
                    sublistId: 'item'
                });

                refundRecord.setCurrentSublistValue({
                    sublistId: 'item',
                    fieldId: 'item',
                    value: item.itemId
                });

                refundRecord.setCurrentSublistValue({
                    sublistId: 'item',
                    fieldId: 'quantity',
                    value: item.quantity
                });

                refundRecord.setCurrentSublistValue({
                    sublistId: 'item',
                    fieldId: 'rate',
                    value: item.rate
                });

                if (item.units) {
                    refundRecord.setCurrentSublistValue({
                        sublistId: 'item',
                        fieldId: 'units',
                        value: item.units
                    });
                }

                refundRecord.commitLine({
                    sublistId: 'item'
                });

                // Log each item added
                log.debug({
                    title: 'Added Item to Refund',
                    details: 'Item ' + (index + 1) + ': ' + JSON.stringify(item)
                });
            });

            // Save the Refund
            var refundId = refundRecord.save({
                enableSourcing: true,
                ignoreMandatoryFields: false
            });

            // Log the Refund creation
            log.debug({
                title: 'Cash Refund Created',
                details: 'ID: ' + refundId
            });

            return { success: true, id: refundId };

        } catch (e) {
            // Log and return error details
            log.error({
                title: 'Error creating Cash Refund',
                details: e.message
            });
            return { success: false, message: e.message };
        }
    }

    return {
        post: doPost
    };

});
