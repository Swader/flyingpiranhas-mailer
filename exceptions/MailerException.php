<?php

	namespace flyingpiranhas\Mailer\Exceptions;

	/**
	 * The Mailer Exception class is thrown when
	 * errors occur during the sending, preparation
	 * or queueing of email messages. Generally,
	 * whenever an email operation goes wrong, a
	 * MailerException will be thrown with additional
	 * information on the error. See below for accessible
	 * properties and which values they might contain.
	 *
	 * @category      flyingpiranhas
	 * @package       Api
	 * @subpackage    Exceptions
	 * @license       BSD License
	 * @version       0.01
	 * @since         2012-06-19
	 * @author        Bruno Škvorc <bruno@skvorc.me>
	 */
	class MailerException extends \flyingpiranhas\common\exceptions\FpException
	{

	}