<?php

    namespace flyingpiranhas\mailer\fp\mysql;

    use flyingpiranhas\common\utils\Debug;
    use flyingpiranhas\mailer\exceptions\MailerException as MailerException;
    use flyingpiranhas\mailer\interfaces\MailRepository as MailRepository;

    /**
     * The Mailer Repo demo class, MySQL/MariaDB version
     *
     * @category      flyingpiranhas
     * @package       Mailer
     * @license       Apache-2.0
     * @version       0.01
     * @since         2012-06-19
     * @author        Bruno Škvorc <bruno@skvorc.me>
     */
    class RepoMySQL extends \flyingpiranhas\common\database\adapters\MysqlAdapter implements MailRepository
    {

        protected $sQueue = '`emails_queue`';
        protected $sTemplates = '`email_templates`';

        /**
         * Saves an email message object into the Database for later sending
         * Returns 1 or 0 depending on success
         *
         * @param \Swift_Message $oMessage
         * @param null           $sDate
         * @param int            $iPriority
         *
         * @return bool
         */
        function queueEmail(\Swift_Message $oMessage, $sDate = null, $iPriority = 0)
        {

            $sQuery = '
				INSERT INTO ' . $this->sQueue . '
				(message_id, created_on, to_be_sent_on, priority, serialized_recipient, serialized_sender, headers, sent, email_object, slug)
				VALUES
				(:mi, :co, :tbso, :p, :sr, :ss, :head, :sent, :eo, :slug) ';

            $oStatement = $this->prepare($sQuery);

            return (int)$oStatement->execute(
                array(
                    'sr'   => serialize($oMessage->getTo()),
                    'ss'   => serialize($oMessage->getFrom()),
                    'eo'   => base64_encode(serialize($oMessage)),
                    'pr'   => $iPriority,
                    'date' => ($sDate) ? $this->toMysqlDate($sDate) : null
                )
            );
        }





        protected $sQueue = '`queue`';
        protected $sArchive = '`archive`';
        protected $sTemplates = '`templates`';

        /**
         * Saves an email message object into the Database for later sending
         * Returns 1 or 0 depending on success
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
        function queueEmail(\Swift_Message $oMessage, $sDate = null, $iPriority = 0)
        {
            $sQuery = '
				INSERT INTO ' . $this->sQueue . '
				(serialized_recipient, serialized_sender, email_object, priority, date)
				VALUES
				(:sr, :ss, :eo, :pr, :date) ';

            $oStatement = $this->prepare($sQuery);

            return (int)$oStatement->execute(
                array(
                    'sr'   => serialize($oMessage->getTo()),
                    'ss'   => serialize($oMessage->getFrom()),
                    'eo'   => base64_encode(serialize($oMessage)),
                    'pr'   => $iPriority,
                    'date' => ($sDate) ? $this->toMysqlDate($sDate) : null
                )
            );
        }

        /**
         * Deletes an email message from the queue
         * Returns 1 or 0 depending on success
         *
         * @param int $id ID of the queue entry to remove
         *
         * @return bool
         *
         * @author        Bruno Škvorc <bruno@skvorc.me>
         * @since         2012-06-19
         */
        function unqueueEmail($id)
        {
            $sQuery = 'DELETE FROM ' . $this->sQueue . ' WHERE id = :id ';
            $oStatement = $this->prepare($sQuery);
            return (int)$oStatement->execute(array('id' => $id));
        }

        /**
         * Fetches queued emails
         *
         * @param int    $iRange     1 - 1000
         * @param string $sDate      Selects only the messages meant to be sent on $sDate (Y-m-d). If omitted, only messages without a date or those with now()'s date are fetched
         * @param int    $iPriority  Targets messages of a specific priority
         * @param string $sRecipient Targets messages by specific recipient
         * @param string $sSender    Targets messages by specific sender
         *
         * @return array of Swift_Message objects
         *
         * @author        Bruno Škvorc <bruno@skvorc.me>
         * @since         2012-06-19
         */
        function fetchFromQueue($iRange, $sDate = null, $iPriority = null, $sRecipient = null, $sSender = null)
        {
            $sQuery = '
				SELECT id, email_object
				FROM ' . $this->sQueue . '
				WHERE 1 ';

            $bind = array();

            if ($sDate) {
                $sQuery .= ' AND (date = :date OR date IS NULL)';
                $bind['date'] = $sDate;
            }

            if ($iPriority) {
                $sQuery .= ' AND priority >= :pr ';
                $bind['pr'] = $iPriority;
            }

            if ($sRecipient) {
                $sQuery .= ' AND serialized_recipient LIKE \'%' . $sRecipient . '%\' ';
            }

            if ($sSender) {
                $sQuery .= ' AND serialized_sender LIKE \'%' . $sSender . '%\' ';
            }

            $sQuery .= ' ORDER BY date_added ASC LIMIT ' . $iRange;
            $aResult = $this->fetchAll($sQuery, $bind);
            $aReturn = array();

            if (!empty($aResult)) {
                foreach ($aResult as $oMessageBinary) {
                    $aReturn[$oMessageBinary['id']] = unserialize(base64_decode($oMessageBinary['email_object']));
                }
            }
            return $aReturn;
        }

        /**
         * Archives sent email.
         *
         * @param \Swift_Message $oMessage
         *
         * @return mixed
         *
         * @author        Bruno Škvorc <bruno@skvorc.me>
         * @since         2012-06-19
         */
        function archiveSentEmail(\Swift_Message $oMessage)
        {
            $sQuery = '
				INSERT INTO ' . $this->sArchive . '
				(serialized_recipient, serialized_sender, email_object, headers)
				VALUES
				(:sr, :ss, :eo, :hs) ';
            $oStatement = $this->prepare($sQuery);

            return
                (int)$oStatement->execute(array(
                    'sr' => serialize($oMessage->getTo()),
                    'ss' => serialize($oMessage->getFrom()),
                    'eo' => base64_encode(serialize($oMessage)),
                    'hs' => $oMessage->getHeaders()->toString(),
                ));
        }

        /**
         * Returns the info on when the recipient was last contacted.
         * Optionally, slug and sender can be provided to check when he was last contacted with a given email or by a given sender, respectively
         *
         * This method returns the sender, recipient, date of sending, number of days elapsed since last contact and a
         * header_object produced by calling imap_rfc822_parse_headers on the header of the email message.
         *
         * @param string $sRecipient
         * @param string $sSender
         *
         * @return array
         *
         * @author        Bruno Škvorc <bruno@skvorc.me>
         * @since         2012-06-19
         */
        function lastContact($sRecipient, $sSender = null)
        {
            $sQuery = ' SELECT headers FROM ' . $this->sArchive . ' WHERE 1 ';
            $sQuery .= ' AND serialized_recipient LIKE "%' . $sRecipient . '%" ';
            if ($sSender) {
                $sQuery .= ' AND serialized_sender LIKE "%' . $sSender . '%" ';
            }
            $sQuery .= 'ORDER BY date_sent DESC LIMIT 1';

            return $this->fetchOne($sQuery);
        }

        /**
         * Returns an email from a database.
         * Must return well formed associative array with AT LEAST
         * body and subject. body_html and signature are optional,
         * but recommended.
         *
         * @param string $sSlug
         *
         * @return array
         *
         * @author        Bruno Škvorc <bruno@skvorc.me>
         * @since         2012-08-30
         */
        function getEmailBySlug($sSlug) {
            $sQuery = ' SELECT * FROM '.$this->sTemplates. ' WHERE slug = :slug';
            return $this->fetchRow($sQuery, array('slug' => $sSlug));
        }

    }
