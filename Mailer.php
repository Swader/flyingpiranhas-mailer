<?php

    namespace flyingpiranhas\mailer;

    use flyingpiranhas\common\utils\Debug;
    use flyingpiranhas\Mailer\Exceptions\MailerException as MailerException;
    use flyingpiranhas\mailer\interfaces\MailRepository as MailRepository;

    /**
     * The Mailer class
     *
     * Unhealthy references:
     *     transport host in setTransport
     *     APPLICATION_ENV in constructor during setTransport
     *     Better field names in DB (plain_content, html_content ...), to be changed in all references (prepareEmail)
     *
     * @category      flyingpiranhas
     * @package       Mailer
     * @license       Apache-2.0
     * @version       0.01
     * @since         2012-06-19
     * @author        Bruno Škvorc <bruno@skvorc.me>
     */
    class Mailer
    {

        /**
         * @see setDeveloperRecipient and sendMail
         * @var string
         */
        protected $sDeveloperRecipient = '';

        /**
         * How many emails to take from the queue and send
         *
         * @var int
         */
        protected $iQueueRange;

        /**
         * The transport to be used for sending
         *
         * @var \Swift_Transport
         */
        protected $oTransport;

        /**
         * Defines the default sender.
         * Can be single-element assoc. array ( name => email ) or just an email address
         *
         * @var array|string
         */
        protected static $defaultSender;

        /**
         * Swift Mailer object
         *
         * @var \Swift_Mailer
         */
        protected $oMailer;

        /**
         * The array holding $emails - each email is an object in itself, with all required data.
         *
         * @var array
         */
        protected $aPreparedEmails;

        /**
         * Array of failed recipients per sent email.
         * This is an assoc. array where keys are email IDs,
         * while values are arrays of failed recipients per sent email
         *
         * @var array
         */
        protected $aFailedRecipients;

        /**
         * Number of queued emails during last queueing
         *
         * @var int
         */
        protected $iQueued = 0;

        /**
         * The service that allows the mailer to have a database connection
         *
         * @var null
         */
        protected $oRepo = null;

        /**
         * The location of static attachments
         *
         * @var string
         */
        protected $sStaticAttachmentsFolder;

        /**
         * Whether or not to send to the archive inbox as well, in the format of a BCC email
         *
         * @var bool
         */
        protected $bDefaultBccActive = true;

        /**
         * Whether or not to archive the sent emails
         *
         * @var null
         */
        protected $bArchiving = null;

        /**
         * Number of successfully sent emails
         *
         * @var int
         */
        protected $iSent = 0;

        public function __construct(MailRepository $oRepo = null)
        {
            $this->aPreparedEmails = array();

            // Set up defaults
            $this->bDefaultBccActive = true;
            $this->setQueueRange();
            $this->archiving(true);
            $this->setTransport((defined('APPLICATION_ENV') && constant('APPLICATION_ENV') == 'production') ? 2 : 3);
            $this->setStaticAttachmentsFolderPath();

            if ($oRepo) {
                $this->setMailRepo($oRepo);
            }

            $this->oMailer = \Swift_Mailer::newInstance($this->getTransport());
        }

        /**
         * Sets the default sender for the expressMail method.
         * Can be a single-element array (name => email) or pure email string
         *
         * @param $mInput
         */
        public static function setDefaultSender($mInput)
        {
            self::$defaultSender = $mInput;
        }

        /**
         * Sets the number of queued emails. Automatic when queuePreparedEmails is called.
         *
         * @param $iQueued
         *
         * @return Mailer
         */
        protected function setNumberOfQueued($iQueued)
        {
            $this->iQueued = $iQueued;

            return $this;
        }

        /**
         * Returns the number of queued emails during last queueing.
         *
         * @return int
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function getNumberOfQueued()
        {
            return $this->iQueued;
        }

        /**
         * Sets the queue range of the Mailer instance
         *
         * @param int $iValue
         *
         * @return Mailer
         * @throws MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function setQueueRange($iValue = 500)
        {
            if ($iValue > 0 && $iValue < 1000) {
                $this->iQueueRange = $iValue;
            } else {
                throw new MailerException('Wrong queueRange value given - value must be between 0 and 1000, you gave ' . $iValue);
            }

            return $this;
        }

        /**
         * Sets the developer recipient.
         * Leave default of pass false to deactivate debugging send mode
         * and send to normal recipients. Pass email to define a custom
         * email address which should receive all the emails.
         *
         * @param bool|string $sEmail
         *
         * @return Mailer
         *
         * @since         2012-09-01
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function setDeveloperRecipient($sEmail = false)
        {
            $this->sDeveloperRecipient = $sEmail;

            return $this;
        }

        /**
         * Returns the defined developer recipient
         * Will be empty string if no developer recipient was set
         *
         * @return string|bool
         *
         * @since         2012-09-01
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function getDeveloperRecipient()
        {
            return $this->sDeveloperRecipient;
        }

        /**
         * Retrieves the queueRange of the given Mailer instance
         *
         * @return int
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function getQueueRange()
        {
            return $this->iQueueRange;
        }

        /**
         * Returns the number of successfully sent emails
         *
         * @return int
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function getNumberOfSent()
        {
            return $this->iSent;
        }

        /**
         * Sets the transport. Defaults to localhost
         *
         * @param int|\Swift_Transport $mTransport
         *
         * @return Mailer
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function setTransport($mTransport = 3)
        {
            if (is_a($mTransport, '\Swift_Transport')) {
                $this->oTransport = $mTransport;
            } else {
                if (is_int($mTransport)) {
                    switch ($mTransport) {
                        default:
                        case 1:
                            $this->oTransport = new \Swift_SmtpTransport();
                            break;
                        case 2:
                            $this->oTransport = new \Swift_SmtpTransport('127.0.0.1');
                            break;
                        case 3:
                            $this->oTransport = \Swift_MailTransport::newInstance();
                            break;
                    }
                } else {
                    $this->oTransport = new \Swift_SmtpTransport($mTransport);
                }
            }
            $this->oMailer = \Swift_Mailer::newInstance($this->getTransport());

            return $this;
        }

        /**
         * Returns the transport in use on the current Mailer interface
         *
         * @return \Swift_Transport
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function getTransport()
        {
            return $this->oTransport;
        }

        /**
         * Sends a prepared message
         *
         * @param \Swift_Message $oMessage
         *
         * @return int Number of recipients accepted for delivery
         * @throws MailerException
         *
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        protected function sendMail(\Swift_Message $oMessage)
        {

            if ($this->getDeveloperRecipient() !== false) {
                $sEmail = filter_var($this->getDeveloperRecipient(), FILTER_VALIDATE_EMAIL);
                if (!empty($sEmail)) {
                    $oMessage->setTo($sEmail);
                } else {
                    throw new MailerException('Developer recipient is set but invalid. Sending will not happen');
                }
            }

            // ===================================

            return $this->oMailer->send($oMessage, $this->aFailedRecipients[$oMessage->getId()]);
        }

        /**
         * Pass in true/false to activate/deactivate archiving.
         * Defaults to true.
         * Omit parameter to use as getter.
         *
         * @param bool $bVal
         *
         * @return Mailer|bool
         * @throws MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function archiving($bVal = null)
        {
            if ($bVal !== null) {
                if ($bVal === true || $bVal === false) {
                    $this->bArchiving = $bVal;

                    return $this;
                } else {
                    throw new MailerException('Cannot set archiving mode to ' . $bVal . ' You must provide a boolean value.');
                }
            }

            return (bool)$this->bArchiving;
        }

        /**
         * Saves the sent message into the archive via the Mailer's registered service.
         * This method will throw an exception is the service is not set or is invalid.
         *
         * @param \Swift_Message $oMessage
         *
         * @return Mailer
         * @throws MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        protected function archiveEmail(\Swift_Message $oMessage)
        {

            $this->checkService();

            if (!$this->oRepo->archiveSentEmail($oMessage)) {
                throw new MailerException('Unable to archive email.');
            }

            return $this;
        }

        /**
         * Creates and sends an instant message
         *
         * @param array $aSettings Needs to have body and from, to and subject are optional values.
         * @param bool  $bArchive  Set to true if you wish the message to be saved into the archive after sending
         *
         * @return Mailer
         * @throws MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function expressMail($aSettings, $bArchive = false)
        {
            $aSettings['subject'] = (isset($aSettings['subject'])) ? $aSettings['subject'] : 'Express message via FlyingPiranhas Mailer and Swift';

            if (!isset($aSettings['from'])) {
                if (!empty(self::$defaultSender)) {
                    throw new MailerException(
                        'Please define defaultSender.
                    See http://www.flyingpiranhas.net/docs/mailer/defaultsender for more information.'
                    );
                } else {
                    $aSettings['from'] = self::$defaultSender;
                }
            }

            if (!isset($aSettings['to']) || !isset($aSettings['body'])) {
                throw new MailerException('Both the "to" and "body" values need to be provided.');
            } else {
                $message = \Swift_Message::newInstance($aSettings['subject'])->setFrom($aSettings['from'])->setTo(
                    $aSettings['to']
                )->setBody($aSettings['body']);
                $this->sendMail($message);
                if ($bArchive) {
                    $this->archiveEmail($message);
                }

                return $this;
            }
        }

        /**
         * Returns the array of failed recipients. This is an assoc. array which has message IDs as keys and an array of failed recipients per given email as the value
         *
         * @param mixed $id The message id. If provided, only fails for the given email are retrieved, otherwise, everything is retrieved.
         *
         * @return array
         *
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function getFailedRecipients($id = 0)
        {
            if ($id) {
                return $this->aFailedRecipients[$id];
            } else {
                return $this->aFailedRecipients;
            }
        }

        /**
         * Sends and optionally archives the prepared emails
         *
         * @param string $sDevelopmentRecipient Enter custom email address if you want the email sent to this address instead (good for testing)
         *
         * @return Mailer
         * @throws MailerException
         *
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function sendPreparedEmails($sDevelopmentRecipient = null)
        {
            if ($this->hasPreparedEmails()) {
                foreach ($this->getPreparedEmails() as $i => $aEmail) {
                    if ($sDevelopmentRecipient) {
                        $aEmail->setTo($sDevelopmentRecipient);
                    }
                    if ($this->sendMail($aEmail)) {
                        $this->iSent++;
                        if ($this->archiving()) {
                            $this->archiveEmail($aEmail);
                        }
                        unset($this->aPreparedEmails[$i]);
                    } else {
                        throw new MailerException('Failed to send email.');
                    }
                }
            }

            return $this;
        }

        /**
         * Returns the array of prepared email objects
         *
         * @return array
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function getPreparedEmails()
        {
            return $this->aPreparedEmails;
        }

        /**
         * Returns whether or not there are any prepared emails in the instance
         *
         * @return bool
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function hasPreparedEmails()
        {
            return (bool)(count($this->getPreparedEmails()));
        }

        /**
         * Verifies that all the data required for proper email content has been send.<br />
         * This checks for the body and subject, and throws exceptions if any of those aren't found
         *
         * This method takes a reference to the data array and as such might make some minor changes to
         * it (i.e. inserting an empty array key if the 'signature' key is not present etc)
         *
         * @param array $aData
         *
         * @return boolean|MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        protected function verifySendableContent(&$aData)
        {
            $e = true;
            if (!isset($aData['body'])) {
                $e = new MailerException('Content Verification Failed: An email MUST have plain text content.');
            }
            if (!isset($aData['subject'])) {
                $e = new MailerException('Content Verification Failed: An email MUST have a subject.');
            }
            if (!isset($aData['signature'])) {
                $aData['signature'] = '';
            }

            return $e;
        }

        /**
         * Sets the Mail Repository with which this Mailer class can access repo/db functionality
         *
         * @param MailRepository $oRepo
         *
         * @return Mailer
         */
        public function setMailRepo(MailRepository $oRepo)
        {
            $this->oRepo = $oRepo;

            return $this;
        }

        /**
         * Returns the defined Mailer Service for Mailer's database access or null if not defined
         *
         * @return MailRepository
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function getMailerService()
        {
            return $this->oRepo;
        }

        /**
         * Prepares a new message for sending
         *
         * @param array|string $mTo
         * @param array|string $mFrom
         * @param array        $aData
         * @param array        $aHeaders
         * @param array        $aAttachments
         *
         * @return Mailer
         * @throws bool|MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function prepareEmail($mTo, $mFrom, $aData, $aHeaders = array(), $aAttachments = null)
        {

            $mVerification = $this->verifySendableContent($aData);
            if ($mVerification === true) {

                $message = \Swift_Message::newInstance();
                $message->setSubject($aData['subject']);
                $message->setBody($aData['body'] . $aData['signature']);

                if (isset($aData['bodyHtml']) && !empty($aData['bodyHtml'])) {
                    $aData['body_html'] = $aData['bodyHtml'];
                }
                if (isset($aData['body_html']) && !empty($aData['body_html'])) {
                    $message->addPart($aData['body_html'], 'text/html');
                }
                $message->setTo($mTo);
                $message->setFrom($mFrom);

                if ($this->bDefaultBccActive) {
                    $message->addBcc('mail-archive@intechopen.com');
                }

                if (!empty($aHeaders)) {
                    foreach ($aHeaders as $sHeaderType => $aHeader) {
                        switch ($sHeaderType) {
                            case 'cc':
                                foreach ($aHeader as $aCc) {
                                    $message->addCc($aCc);
                                }
                                break;
                            case 'bcc':
                                foreach ($aHeader as $aBcc) {
                                    $message->addBcc($aBcc);
                                }
                                break;
                            case 'x-smtpapi':
                                foreach ($aHeader as $sType => $aArray) {
                                    switch ($sType) {
                                        case 'categories':
                                            $sCategoryString = '"category":' . json_encode($aArray);
                                            break;
                                        case 'unique_args':
                                            $oObject = new \stdClass();
                                            foreach ($aArray as $k => $v) {
                                                $oObject->$k = $v;
                                            }
                                            $sArgumentsString = '"unique_args":' . json_encode($oObject);
                                            break;
                                        default:
                                            throw new MailerException('X-SMTPAPI header type ' . $sType . ' not supported.');
                                            break;
                                    }
                                }

                                $sTextHeader = '{' . trim(
                                    implode(',', array($sArgumentsString, $sCategoryString)),
                                    ','
                                ) . '}';
                                $message->getHeaders()->addTextHeader('X-SMTPAPI', $sTextHeader);

                                break;
                            default:
                                if (is_string($sHeaderType) && is_string($aHeader)) {
                                    $message->getHeaders()->addTextHeader($sHeaderType, $aHeader);
                                } else {
                                    throw new MailerException('Invalid header format. Both header key and value need to be string.');
                                }
                                break;
                        }
                    }
                }

                if (!empty($aAttachments)) {
                    foreach ($aAttachments as $mAtt) {

                        if (is_a($mAtt, '\Swift_Attachment')) {
                            $message->attach($mAtt);
                        } else {

                            if (is_string($mAtt)) {
                                $mAtt = array('file' => $mAtt);
                            }

                            $mAtt = (array)$mAtt;

                            if (isset($mAtt['file'])) {

                                if (is_readable($mAtt['file'])) {
                                    $sFilePath = $mAtt['file'];
                                } else {
                                    $sFilePath = rtrim(
                                        $this->getStaticAttachmentsFolderPath(),
                                        '/'
                                    ) . '/' . $mAtt['file'];
                                }

                                if (!is_readable($sFilePath)) {
                                    throw new MailerException('Static file attachment ' . $sFilePath . ' not found or is not readable.');
                                }

                                $mAtt['name'] = (isset($mAtt['name'])) ? $mAtt['name'] : $mAtt['file'];

                                $message->attach(\Swift_Attachment::fromPath($sFilePath)->setFilename($mAtt['name']));
                            } else {
                                if (!isset($mAtt['mime'])) {
                                    throw new MailerException('When building attachments, mimetype must be provided via the mime key in the attachment\'s array. Either provide the mimetype, or attach the file by passing a filename through the \'file\' key.');
                                }

                                if (!isset($mAtt['content'])) {
                                    throw new MailerException('No content or filename given. Attachment would be empty. The attachment array must have either a \'file\' key or \'content\' key.');
                                }

                                $mAtt['name'] = (isset($mAtt['name'])) ? $mAtt['name'] : 'Attachment';

                                $message->attach(
                                    \Swift_Attachment::newInstance($mAtt['content'], $mAtt['name'], $mAtt['mime'])
                                );
                            }
                        }
                    }
                }
                $this->aPreparedEmails[] = $message;
            } else {
                throw $mVerification;
            }

            if ($this->getNumberOfSent() > 0) {
                $this->iSent = 0;
            }

            return $this;
        }

        /**
         * Sets the static attachments folder for the given Mailer instance
         *
         * @param string $sValue
         *
         * @return Mailer
         * @throws MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function setStaticAttachmentsFolderPath($sValue = '')
        {
            $sDefault = __DIR__ . '/../../../data/uploads';
            if (empty($sValue) && is_readable($sDefault)) {
                $this->sStaticAttachmentsFolder = $sDefault;
            } else {
                if (is_readable($sValue)) {
                    $this->sStaticAttachmentsFolder = $sValue;
                } else {
                    throw new MailerException(
                        'Specified folder does not exist or is not readable
                        and cannot be used as static attachments folder: ' . $sValue
                    );
                }
            }

            return $this;
        }

        /**
         * Gets the static attachments folder path
         *
         * @return string
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function getStaticAttachmentsFolderPath()
        {
            return $this->sStaticAttachmentsFolder;
        }

        /**
         * Queues prepared emails for later sending
         *
         * @param string $sDate Y-m-d format
         * @param int    $iPriority
         *
         * @return Mailer
         * @throws MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function queuePreparedEmails($sDate = null, $iPriority = 0)
        {

            if ($sDate) {
                if (strpos($sDate, '+') === 0) {
                    $sTrimmed = trim($sDate, '+ ');
                    if (is_numeric($sTrimmed)) {
                        $sTrimmed = '+' . $sTrimmed . ' day';
                    } else {
                        throw new MailerException('Failed to queue - date is invalid: ' . $sDate);
                    }
                    $sDate = date('Y-m-d', strtotime($sTrimmed));
                } else {
                    if (strtotime($sDate) < time()) {
                        throw new MailerException('Queue date cannot be in the past!');
                    }
                }
            }

            $this->checkService();

            $iQueued = 0;
            if ($this->hasPreparedEmails()) {
                /** @var $oEmail \Swift_Message */
                foreach ($this->aPreparedEmails as $i => &$oEmail) {
                    if ($this->oRepo->queueEmail($oEmail, $sDate, $iPriority)) {
                        $iQueued++;
                        unset($this->aPreparedEmails[$i]);
                    } else {
                        throw new MailerException('Could not queue email');
                    }
                }
            }
            $this->setNumberOfQueued($iQueued);

            return $this;
        }

        /**
         * Sends emails from queue. If options are provided, fetches non-default set.
         * Available filter options are: range, date, priority, recipient and sender.
         *
         * @param array  $aOptions
         * @param string $sDevelopmentRecipient Provide if you want to force-send the emails to a specific recipient (for testing purposes)
         *
         * @return Mailer
         * @throws MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public function sendQueuedEmails($aOptions = array(), $sDevelopmentRecipient = null)
        {

            $this->checkService();

            $iRange     = (!isset($aOptions['range']) || $aOptions['range'] < 1 || $aOptions['range'] > 1000) ? $this->getQueueRange() : $aOptions['range'];
            $sDate      = (isset($aOptions['date'])) ? $aOptions['date'] : null;
            $iPriority  = (isset($aOptions['priority'])) ? $aOptions['priority'] : null;
            $sRecipient = (isset($aOptions['recipient'])) ? $aOptions['recipient'] : null;
            $sSender    = (isset($aOptions['sender'])) ? $aOptions['sender'] : null;

            if ($sDate == 'today') {
                $sDate = date('Y-m-d');
            }

            $aQueuedEmails = $this->oRepo->fetchFromQueue($iRange, $sDate, $iPriority, $sRecipient, $sSender);

            if (!empty($aQueuedEmails)) {
                foreach ($aQueuedEmails as $id => $oEmail) {
                    if ($sDevelopmentRecipient) {
                        $oEmail->setTo($sDevelopmentRecipient);
                    }
                    if ($this->sendMail($oEmail)) {
                        $this->iSent++;
                        if ($this->archiving()) {
                            $this->archiveEmail($oEmail);
                        }
                        if (!$this->oRepo->unqueueEmail($id)) {
                            throw new MailerException('Email ID ' . $id . ' was sent, but could not be unqueued.');
                        }
                    } else {
                        throw new MailerException('Failed to send queued email with ID ' . $id);
                    }
                }
            }

            return $this;
        }

        /**
         * Parses an email header into an array of readable and usable values.
         * The array will contain sub arrays of email addresses in keys "to", "from", "reply_to", "sender", "cc", "bcc" and other data.
         *
         * @param $sHeader
         *
         * @return array
         * @throws MailerException
         *
         * @since         2012-06-20
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        public static function processTextHeader($sHeader)
        {

            $aResult = array();
            if (!empty($sHeader) && is_string($sHeader)) {

                if (!function_exists('imap_rfc822_parse_headers')) {
                    throw new MailerException('No IMAP RFC822 function available. Did you install the IMAP extension into php?');
                }
                $oHeader = imap_rfc822_parse_headers($sHeader);
                $aResult = array();

                $aHeaderBits = array('to', 'from', 'reply_to', 'sender', 'cc', 'bcc');
                foreach ($aHeaderBits as &$sHeaderBit) {
                    $aResult[$sHeaderBit] = array();
                    if (isset($oHeader->$sHeaderBit)) {
                        foreach ($oHeader->$sHeaderBit as &$sHeaderBitObject) {
                            $aResult[$sHeaderBit][] = $sHeaderBitObject->mailbox . '@' . $sHeaderBitObject->host;
                        }
                    }
                }

                $aResult['date']     = $oHeader->date;
                $oDateTime           = new \DateTime($oHeader->date);
                $aResult['days_ago'] = $oDateTime->diff(new \DateTime())->days;

                $aResult['subject']     = $oHeader->subject;
                $aResult['message_id']  = $oHeader->message_id;
                $aResult['unique_args'] = array();
                $aResult['categories']  = array();

                unset($oHeader, $oDateTime);

                foreach (explode("\n", $sHeader) as $sHeaderEntry) {
                    if (strpos($sHeaderEntry, 'X-SMTPAPI') !== false) {
                        $x_smtpapi = json_decode(str_replace('X-SMTPAPI: ', '', $sHeaderEntry));
                        if (isset($x_smtpapi->unique_args) && !empty($x_smtpapi->unique_args) && is_a(
                            $x_smtpapi->unique_args,
                            '\stdClass'
                        )
                        ) {
                            $aResult['unique_args'] = get_object_vars($x_smtpapi->unique_args);
                        }
                        if (isset($x_smtpapi->category) && !empty($x_smtpapi->category)) {
                            $aResult['categories'] = $x_smtpapi->category;
                        }
                        break;
                    }
                }
            }

            return $aResult;
        }

        /**
         * Checks if the service is properly set and throws exception if not
         *
         * @throws MailerException
         * @since         2012-06-19
         * @author        Bruno Škvorc <bruno@skvorc.me>
         */
        protected function checkService()
        {
            if ($this->oRepo === null) {
                throw new MailerException('The Mailer Service needs to be provided, and must implement the MailerService Interface.');
            }
        }
    }
