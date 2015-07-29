<?php
	namespace Serializers;

	/**
	 * Event name shortcuts
	 *
	 * @package Serializers
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Events {
		/** @var string JSON_BIGINT - event called before all bigint values are escaped as strings  */
		const JSON_BIGINT = 'json.bigint';
		/** @var string JSON_MSDATE - event called before all MS specific dates are coverted to dates  */
		const JSON_MSDATE = 'json.msdate';
		/** @var string JSON_MSDATE_MATCH - event called on each MS specific date match during replace sequence  */
		const JSON_MSDATE_MATCH = 'json.msdate.match';
		/** @var string JSON_SERIALIZE - event called before serialization/deserialization of an object occurs  */
		const JSON_SERIALIZE = 'json.serialize';

		/** @var string JSONP_VALID_CALLBACK - event called during validation of used callback name */
		const JSONP_VALID_CALLBACK = 'jsonp.valid.callback';

		/** @var string JSON_BIGINT - event called on every end node before its added to XML */
		const XML_WRITE_ELEMENT = 'xml.write.element';
	}