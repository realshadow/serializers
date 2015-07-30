<?php
	namespace Serializers\Decoders;

	use InvalidArgumentException;
	use RuntimeException;
	use Serializers\Encode;
	use Serializers\Serializer;
	use Serializers\Events;
	use DateTimeZone;
	use DateTime;
	use ArrayObject;

	/**
	 * Class for easy JSON deserialization with:
	 *  - automatic parsing of Microsoft's JSON date format (e.g. /Date(1425556377427+0100)/)
	 *  - backport of JSON_BIGINT_AS_STRING available from PHP 5.4.0
	 *  - isValid method for checking validity of provided JSON string
	 *  -  possible conversion from json to:
	 *    - PHP types (string, array, object)
	 *    - XML
	 *
	 * With overriding configuration one can change the default timeformat and timezone settings form
	 * MS date conversion, or turn it off completely.
	 *
	 * It's possible to register an event callback to be called during escaping of BIGINT, in case said
	 * escaping is not good enough, or to turning it off completely.
	 *
	 * Callback method must accept one parameter and thats registered JSON string. Callback can be a closure
	 * or anything else that will pass as callable.
	 *
	 *    	$json = <<<EOT
	 *      	{
	 *				"foo" : "bar",
	 *				"small" : "123456",
	 *				"large" : 200000000000009093302,
	 *				"text" : "Example ratio 1000000000000000:1",
	 *				"date" : "/Date(1425556377427+0100)/"
	 *			}
	 *    	EOT;
	 *
	 *		$s = Serializers\Decode::json($json);
	 *
	 *		print_r($s->toObject());
	 *
	 *    	// transform said json to xml and output it
	 *
	 *    	print Serializers\Decode::json($json)->toXml('root')->withHeaders();
	 *
	 *    	// events
	 *
	 *    	$json = Serializers\Decode::json($json)->on(Serializers\Events::JSON_MSDATE_MATCH, function($date) {
	 *			// matches returned from preg_replace_callback
	 *      	list(, $timestamp,,) = $date;
	 *
	 *      	return date('Y-m-d H:i:s', $timestamp);
	 *		});
	 *
	 * @package Serializers\Decoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Json extends Serializer {
		/** @var string $data - provided JSON  */
		protected $data = '{}';

		/** @var array $messages - JSON error map */
		protected $messages = array(
			JSON_ERROR_NONE => 'No error has occurred.',
			JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded.',
			JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON.',
			JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded.',
			JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded.',
			JSON_ERROR_SYNTAX => 'Syntax error.'
		);

		/** @var array|\ArrayObject $config - default configuration */
		protected $config = array(
			# -- null == date_default_timezone_get()
			'timezone' => null,
			'timeformat' => 'Y-m-d H:i:s',
			'format_ms_date' => true
		);

		protected static $conf = array();

		/** @var string $msDateRegex - regex for parsing MS JSON date format */
		protected static $msDateRegex = '(\d{10})(\d{3})([+-]\d{4})?';

		/**
		 * Backport of JSON_BIGINT_AS_STRING for "casting" BIGINT to string
		 *
		 * @param string $json - JSON string
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function escapeBigint($json) {
			if($this->dispatcher->listensTo(Events::JSON_BIGINT)) {
				return $this->dispatcher->trigger(Events::JSON_BIGINT, array($json));
			}

			return preg_replace('#\"([\w]+)\"\s?:\s?(\d{14,})#', '"${1}":"${2}"', $json);
		}

		/**
		 * Parsing and formatting of MS JSON date format
		 *
		 * @param string $json - JSON string
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function formatMSDate($json) {
			if($this->dispatcher->listensTo(Events::JSON_MSDATE)) {
				return $this->dispatcher->trigger(Events::JSON_MSDATE, array($json));
			}

			$callback = 'self::parseMSDate';
			if($this->dispatcher->listensTo(Events::JSON_MSDATE_MATCH)) {
				$callback = $this->dispatcher->getCallback(Events::JSON_MSDATE_MATCH);
			}

			self::$conf = $this->config;

			return preg_replace_callback('#(\\\/|\/)?Date\('.self::$msDateRegex.'\)(\\\/|\/)?#', $callback, $json);
		}

		/**
		 * Method for converting JSON to PHP array or object data type
		 *
		 * @param bool $toArray - force assoc array
		 *
		 * @return null|array|object
		 *
		 * @throws RuntimeException
		 *
		 * @since 1.0
		 */
		protected function toPrimitive($toArray = false) {
			if(!$this->isValid()) {
				return null;
			}

			if($this->config->format_ms_date === true) {
				$this->data = $this->formatMSDate($this->data);
			}

			if(version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
				$result = json_decode($this->data, $toArray, 512, JSON_BIGINT_AS_STRING);
			} else {
				$result = json_decode($this->escapeBigint($this->data), $toArray);
			}

			if(empty($result)) {
				throw new RuntimeException($this->messages[json_last_error()]);
			}

			return $result;
		}

		/**
		 * Method for parsing MS JSON date format, e.g. /Date(1425556377427+0100)/. Usable as standalone method
		 * as well
		 *
		 * @param string $date - date to be parsed
		 * @param string $format - output format
		 * @param null $timezone - timezone, if no timezone is provided, date_default_timezone_get is used
		 *
		 * @return bool|string
		 *
		 * @since 1.0
		 */
		public static function parseMSDate($date, $format = 'Y-m-d H:i:s', $timezone = null) {
			if(empty($date)) {
				return '';
			}

			# -- if called from preg_replace_callback
			if(!is_array($date)) {
				preg_match('#'.self::$msDateRegex.'#', $date, $matches);
			} else {
				$matches = $date;

				# -- if the date delimiters are escaped \/Date instead of /Date
				$escapedSequence = '\/';
				if(in_array($escapedSequence, $matches)) {
					$matches = array_values(array_filter($matches, function($value) use($escapedSequence) {
						return ($value !== $escapedSequence);
					}));
				}
			}

			if(!empty(self::$conf['timeformat'])) {
				$format = self::$conf['timeformat'];
			}

			if(!empty(self::$conf['timezone'])) {
				$timezone = self::$conf['timezone'];
			}

			# -- timezone checking
			if(is_null($timezone)) {
				$timezone = new DateTimeZone(date_default_timezone_get());
			} elseif(!$timezone instanceof DateTimeZone) {
				$timezone = new DateTimeZone($timezone);
			}

			if(count($matches) > 3) {
				$dt = DateTime::createFromFormat('U.u.O', vsprintf('%2$s.%3$s.%4$s', $matches));

				# -- $timezone is ignored due to unix timestamp and/or specified timezone
				$offset = $timezone->getOffset($dt);
			} else {
				$dt = DateTime::createFromFormat('U.u', vsprintf('%2$s.%3$s', $matches), $timezone);
				$offset = 0;
			}

			return date($format, (int) $dt->format('U') + $offset);
		}

		/**
		 * Constructor
		 *
		 * @param string $input - JSON string
		 * @param array $config - possible configuration changes
		 *
		 * @returns Json
		 *
		 * @throws InvalidArgumentException
		 *
		 * @since 1.0
		 */
		public function __construct($input, array $config = array()) {
			if(!is_string($input)) {
				throw new InvalidArgumentException('JSON serializer accepts only strings.');
			}

			parent::__construct();

			$config = array_merge($this->config, $config);

			$this->config = new ArrayObject($config, ArrayObject::ARRAY_AS_PROPS);
			$this->data = $input;
		}

		/**
		 * Self explanatory
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function __toString() {
			try {
				return $this->load();
			} catch(\Exception $e) {
				$previousHandler = set_exception_handler(function () {});

				restore_error_handler();
				call_user_func($previousHandler, $e);

				die();
			}
		}

		/**
		 * Check to see if provided JSON string is valid
		 *
		 * @return bool
		 *
		 * @since 1.0
		 */
		public function isValid() {
			json_decode($this->data);

			return (json_last_error() === JSON_ERROR_NONE);
		}

		/**
		 * Returns unchanged, original data
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function load() {
			return $this->data;
		}

		/**
		 * Parse to array
		 *
		 * @return array
		 *
		 * @since 1.0
		 */
		public function toArray() {
			return $this->toPrimitive(true);
		}

		/**
		 * Parse to object
		 *
		 * @return \stdClass
		 *
		 * @since 1.0
		 */
		public function toObject() {
			return $this->toPrimitive();
		}

		/**
		 * Transform to XML
		 *
		 * @param string $root - root element
		 * @param array $config - additional configuration
		 *
		 * @return \Serializers\Encoders\Xml
		 *
		 * @since 1.0
		 */
		public function toXml($root, array $config = array()) {
			$xml = Encode::toXml($root, $this->toArray(), $config);

			return $xml;
		}

		/**
		 * Transform to JSONP
		 *
		 * @param string $callback - callback name
		 *
		 * @return \Serializers\Encoders\Jsonp
		 *
		 * @since 1.0
		 */
		public function toJsonp($callback) {
			$jsonp = Encode::toJsonp($callback, $this->toArray());

			return $jsonp;
		}

		/**
		 * Transform to YAML
		 *
		 * @param array $config - additional configuration
		 *
		 * @return \Serializers\Encoders\Yaml
		 *
		 * @since 1.0
		 */
		public function toYaml(array $config = array()) {
			$yaml = Encode::toYaml($this->toArray(), $config);

			return $yaml;
		}

		/**
		 * Transform to INI
		 *
		 * @return \Serializers\Encoders\Ini
		 *
		 * @since 1.0
		 */
		public function toIni() {
			$ini = Encode::toIni($this->toArray());

			return $ini;
		}
	}