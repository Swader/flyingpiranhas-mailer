<?php
    namespace flyingpiranhas\mailer\interfaces;

    /**
     * Defines the look and feel of the Mailer Service.
     *
     * @category      flyingpiranhas
     * @package       Mailer
     * @subpackage    interfaces
     * @license       Apache-2.0
     * @version       0.01
     * @since         2012-06-19
     * @author        Bruno Škvorc <bruno@skvorc.me>
     */
    interface MailRepository
    {

        /**
         * Saves an email message object into the repo for later sending
         * Returns 1 or 0 depending on success
         *
         * @abstract
         *
         * @param \Swift_Message $oMessage
         * @param null           $sDate
         * @param int            $iPriority
         *
         * @return bool
         *
         * @author        Bruno Škvorc <bruno@skvorc.me>
         * @since         2012-06-19
         */
        function queueEmail(\Swift_Message $oMessage, $sDate = null, $iPriority = 0);

        /**
         * Marks an email in the queue as sent.
         * The input param can be an ID in the repo, or the \Swift_Message object itself
         * If the input param is an object, the ID is automatically calculated.
         *
         * @param $mInput
         *
         * @return mixed
         */
        function markAsSent($mInput);

        /**
         * Deletes an email message from the queue
         * Returns 1 or 0 depending on success
         * If input param is \Swift_Message object, ID should automatically be figured out
         *
         * @abstract
         *
         * @param int $mInput ID of the queue entry to remove, or entire \Swift_Message object.
         *
         * @return bool
         *
         * @author        Bruno Škvorc <bruno@skvorc.me>
         * @since         2012-06-19
         */
        function unqueueEmail($mInput);

        /**
         * Fetches queued emails
         *
         * @abstract
         *
         * @param int    $iRange     1 - 1000
         * @param string $sDate      Selects only the messages meant to be sent on $sDate (Y-m-d). If omitted, only messages without a date or those with now()'s date are fetched
         * @param int    $iPriority  Targets messages of a specific priority
         * @param string $sRecipient Targets messages by specific recipient
         * @param string $sSender    Targets messages by specific sender
         * @param array  $aOther     Everything else, like "categories", "tags", "body content" etc.
         *
         * @return array of Swift_Message objects
         *
         * @author        Bruno Škvorc <bruno@skvorc.me>
         * @since         2012-06-19
         */
        function fetchFromQueue($iRange, $sDate = null, $iPriority = null, $sRecipient = null, $sSender = null, $aOther = array());

        /**
         * Returns the info on when the recipient was last contacted.
         * Optionally, slug and sender can be provided to check when he was last contacted with a given email or by a given sender, respectively
         *
         * This method returns the sender, recipient, date of sending, number of days elapsed since last contact and a
         * header_object produced by calling imap_rfc822_parse_headers on the header of the email message.
         *
         * @abstract
         *
         * @param string $sRecipient
         * @param string $sSender
         *
         * @return array
         *
         * @author        Bruno Škvorc <bruno@skvorc.me>
         * @since         2012-06-19
         */
        function lastContact($sRecipient, $sSender = null);
    }
