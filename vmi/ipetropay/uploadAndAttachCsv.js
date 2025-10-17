/**
 * @NApiVersion 2.x
 * @NScriptType Restlet
 */
define(['N/file', 'N/record', 'N/log', 'N/encode'], function(file, record, log, encode) {

    /**
     * Handles POST requests to upload a CSV file and attach it to a Cash Refund.
     *
     * @param {Object} requestBody - The JSON payload sent from the PHP script.
     * @param {string} requestBody.name - The name of the CSV file (e.g., "Test_CSV_2025-01-23.csv").
     * @param {string} requestBody.content - The Base64-encoded content of the CSV file.
     * @param {number} requestBody.folderId - The internal ID of the folder in the File Cabinet.
     * @param {number} requestBody.cashRefundId - The internal ID of the Cash Refund to attach the file to.
     *
     * @returns {Object} - Success status and relevant IDs or error message.
     */
    function doPost(requestBody) {
        try {
            // Validate input
            if (!requestBody.name || !requestBody.content || !requestBody.folderId || !requestBody.cashRefundId) {
                throw new Error("Missing required fields: 'name', 'content', 'folderId', 'cashRefundId'.");
            }

            // Log incoming data for debugging
            log.audit({
                title: 'Incoming Request',
                details: JSON.stringify(requestBody)
            });

            // Decode Base64 content using N/encode module
            var decodedContent = encode.convert({
                string: requestBody.content,
                inputEncoding: encode.Encoding.BASE_64,
                outputEncoding: encode.Encoding.UTF_8
            });

            // Create the file in the File Cabinet
            var csvFile = file.create({
                name: requestBody.name,
                fileType: file.Type.CSV,
                contents: decodedContent,
                folder: requestBody.folderId
            });

            var fileId = csvFile.save();

            log.audit({
                title: 'File Uploaded',
                details: 'File ID: ' + fileId
            });

            // Corrected Attachment: Attach the File to the Cash Refund
            record.attach({
                record: {
                    type: 'file',                  // The record being attached
                    id: fileId                     // Internal ID of the File
                },
                to: {
                    type: 'cashRefund',            // Target record type
                    id: requestBody.cashRefundId   // Internal ID of the Cash Refund
                }
            });

            log.audit({
                title: 'File Attached',
                details: 'File ID ' + fileId + ' attached to Cash Refund ID ' + requestBody.cashRefundId
            });

            return {
                success: true,
                fileId: fileId
                // Note: attachmentId is not returned since record.attach does not return a value
            };

        } catch (e) {
            log.error({
                title: 'Error in Restlet',
                details: e.message
            });
            return {
                success: false,
                message: e.message
            };
        }
    }

    return {
        post: doPost
    };
});
