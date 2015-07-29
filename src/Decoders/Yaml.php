<?php
	namespace Serializers\Decoders;

	use Serializers\Encode;
	use Serializers\Serializer;
	use ArrayObject;
	use Symfony\Component\Yaml\Yaml as SymfonyYaml;

	/**
	 * Class for easy YAML deserialization
	 *
	 * Uses Symfony's YAML component under the hood
	 *
	 *		$yaml = Serializers\Decode::yaml(file_get_contents('config.yml'));
	 *
	 *		print_r($yaml->toObject());
	 *
	 *    	// transform said json to xml and output it
	 *
	 *    	print Serializers\Decode::yaml(file_get_contents('config.yml'))->toXml('yaml')->withHeaders();
	 *
	 * @package Serializers\Decoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Yaml extends Serializer {
		/** @var null|string $data - provided JSON  */
		protected $data = null;

		/** @var array|\ArrayObject $config - default configuration */
		protected $config = array(
			'throw_exceptions' => true,
			'object_support' => false
		);

		/**
		 * Method for converting YAML to PHP array or object data type
		 *
		 * @param bool $toArray - force assoc array
		 *
		 * @return null|array|object
		 *
		 * @since 1.0
		 */
		protected function toPrimitive($toArray = false) {
			$output = SymfonyYaml::parse(
				$this->data,
				$this->config->throw_exceptions,
				$this->config->object_support
			);

			if($toArray === true) {
				return $output;
			} else {
				return (object) $output;
			}
		}

		/**
		 * Constructor
		 *
		 * @param string $input - YAML string
		 * @param array $config - possible configuration changes
		 *
		 * @returns Yaml
		 *
		 * @since 1.0
		 */
		public function __construct($input, array $config = array()) {
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
		 * @param array $config - configuration
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
		 * Transform to JSON
		 *
		 * @param array $config - configuration
		 *
		 * @return \Serializers\Encoders\Json
		 *
		 * @since 1.0
		 */
		public function toJson(array $config = array()) {
			$jsonp = Encode::toJson($this->toArray(), $config);

			return $jsonp;
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