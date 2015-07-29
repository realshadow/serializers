<?php
	namespace Serializers\Decoders;

	use InvalidArgumentException;
	use RuntimeException;
	use Serializers\Encode;
	use Serializers\Serializer;
	use DateTimeZone;
	use DateTime;
	use ArrayObject;

	/**
	 * Class for easy JSONP deserialization, behaves like JSON deserializer
	 *
	 *    	$json = '_foo.bar({"foo":"bar","bar":"foo"})';
	 *
	 *		$data = Serializers\Decode::jsonp($json);
	 *
	 *    	print_r($data->toObject());
	 *
	 *    	// transform said json to xml with callback name as root element and output it
	 *
	 *    	print Serializers\Decode::jsonp($json)->toXml()->withHeaders();
	 *
	 * @package Serializers\Decoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Jsonp extends Json {
		/** @var string $unfilteredData - JSON with callback removed */
		protected $unfilteredData = null;

		/**
		 * Attempts to parse callback name from provided JSONP string
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function getCallback() {
			$callback = '';

			preg_match('#[^\(]*#', $this->unfilteredData, $match);

			if(!empty($match)) {
				$callback = current($match);
			}

			return $callback;
		}

		/**
		 * Method will remove callback and trim brackets so only clean json will
		 * will be returned
		 *
		 * @param string $string - jsonp string
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function removeCallback($string) {
			$found = strpos($string, '(');

			if($found) {
				$string = rtrim(substr($string, ++$found, strlen($string)), ')');
			}

			return $string;
		}

		/**
		 * Constructor
		 *
		 * @param string $input - JSONP string
		 * @param array $config - possible configuration changes
		 *
		 * @returns Jsonp
		 *
		 * @throws InvalidArgumentException
		 *
		 * @since 1.0
		 */
		public function __construct($input, array $config = array()) {
			parent::__construct($input, $config);

			$this->unfilteredData = $this->data;
			$this->data = $this->removeCallback($this->data);
		}

		/**
		 * Removes callback from provided JSONP string
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function load() {
			return $this->unfilteredData;
		}

		/**
		 * Transform to XML. Root element in this case is not required, callback name
		 * will be parsed from provided JSONP string and it will be used as the root
		 * element name
		 *
		 * @param string $root - root element
		 * @param array $config - configuration
		 *
		 * @return \Serializers\Encoders\Xml
		 *
		 * @since 1.0
		 */
		public function toXml($root = null, array $config = array()) {
			if(is_null($root)) {
				$root = $this->getCallback();
			}

			return parent::toXml($root, $config);
		}

		/**
		 * Parse to JSON and return instance of JSON Encoder
		 *
		 * @param array $config - configuration
		 *
		 * @return \Serializers\Encoders\Json
		 *
		 * @since 1.0
		 */
		public function toJSON(array $config = array()) {
			$json = Encode::toJson($this->data, $config);

			return $json;
		}
	}