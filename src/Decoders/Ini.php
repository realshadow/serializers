<?php
	namespace Serializers\Decoders;

	use Serializers\Encode;
	use Serializers\Serializer;
	use Symfony\Component\Yaml\Yaml as SymfonyYaml;
	use InvalidArgumentException;

	/**
	 * Class for easy INI deserialization
	 *
	 * Uses INI parser by @austinhyde
	 *
	 *		$array = array(
	 * 			'a' => 'd',
	 * 			'b' => array('test' => 'c'),
	 * 			'database' => array(
	 * 				'default' => array(
	 * 					'name' => 'db',
	 * 					'host' => 'master.db',
	 * 					'ip' => 'dd'
	 * 				)
	 * 			),
	 * 			'array' => array('a', '1', 3)
	 * 		);
	 *
	 * 		$encode = SerializersEncode::toIni($array);
	 * 		$encode->toFile('config.ini');
	 *
	 * @package Serializers\Decoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Ini extends Serializer {
		/** @var null|string $data - provided JSON  */
		protected $data = null;

		/** @var array|\ArrayObject $config - default configuration */
		protected $config = array(
			'use_array_object' => false,
			'include_original_sections' => false,
			'property_nesting' => true
		);

		/**
		 * Method for converting JSON to PHP array or object data type
		 *
		 * @param bool $toArray - force assoc array
		 *
		 * @return null|\ArrayObject
		 *
		 * @since 1.0
		 */
		protected function toPrimitive($toArray = false) {
			$output = $this->parser->process($this->data);

			if($toArray === false && $this->config->use_array_object === false) {
				$output = (object) $output;
			}

			return $output;
		}

		/**
		 * Constructor
		 *
		 * @param string $input - filename
		 *
		 * @returns Ini
		 *
		 * @since 1.0
		 */
		public function __construct($input) {
			parent::__construct();

			$this->data = $input;

			$this->parser = new \IniParser();

			foreach($this->config as $property => $value) {
				$this->parser->{$property} = $value;
			}
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
		 * Returns original IniParser object
		 *
		 * @return \IniParser
		 *
		 * @since 1.0
		 */
		public function object() {
			return $this->parser;
		}

		/**
		 * Parse to array
		 *
		 * @return array|\ArrayObject
		 *
		 * @since 1.0
		 */
		public function toArray() {
			return $this->toPrimitive(true);
		}

		/**
		 * Parse to object
		 *
		 * @return array|\ArrayObject
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
	}