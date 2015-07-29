<?php
	namespace Serializers;

	use Serializers\Encoders\Ini;
	use Serializers\Encoders\Json;
	use Serializers\Encoders\Jsonp;
	use Serializers\Encoders\Xml;
	use Serializers\Encoders\Yaml;

	/**
	 * Wrapper class for encoders
	 *
	 * @package Serializers
	 * @author Lukas Homza <lhomza@cfh.sk>
	 * @version 1.0
	 */
	class Encode {
		/**
		 * Serialize to JSON
		 *
		 * @param mixed $input - data to be serialized
		 *
		 * @return \Serializers\Encoders\Json
		 *
		 * @since 1.0
		 */
		public static function toJson($input) {
			return new Json($input);
		}

		/**
		 * Serialize to JSON(P)
		 *
		 * @param string $callback - callback function name
		 * @param mixed $input - data to be serialized
		 *
		 * @return \Serializers\Encoders\Jsonp
		 *
		 * @since 1.0
		 */
		public static function toJsonp($callback, $input) {
			return new Jsonp($callback, $input);
		}

		/**
		 * Serialize to XML
		 *
		 * @param string $root - XML root element
		 * @param array $input - array to be serialized
		 * @param array $config - additional configuration options
		 *
		 * @return \Serializers\Encoders\Xml
		 *
		 * @since 1.0
		 */
		public static function toXml($root, array $input, array $config = array()) {
			return new Xml($root, $input, $config);
		}

		/**
		 * Serialize to YAML
		 *
		 * @param array $input - array to be serialized
		 * @param array $config - additional configuration options
		 *
		 * @return \Serializers\Encoders\Yaml
		 *
		 * @since 1.0
		 */
		public static function toYaml(array $input, array $config = array()) {
			return new Yaml($input, $config);
		}

		/**
		 * Serialize to INI
		 *
		 * @param array $input - INI string to be serialized
		 *
		 * @return \Serializers\Encoders\Yaml
		 *
		 * @since 1.0
		 */
		public static function toIni(array $input) {
			return new Ini($input);
		}
	}