<?php
	namespace Serializers;

	use Serializers\Decoders\Ini;
	use Serializers\Decoders\Json;
	use Serializers\Decoders\Jsonp;
	use Serializers\Decoders\Xml;
	use Serializers\Decoders\Yaml;

	/**
	 * Wrapper class for decoders
	 *
	 * @package Serializers
	 * @author Lukas Homza <lhomza@cfh.sk>
	 * @version 1.0
	 */
	class Decode {
		/**
		 * Deserialize to JSON
		 *
		 * @param string $input - JSON string
		 * @param array $config - additional configuration options
		 *
		 * @return \Serializers\Decoders\Json
		 *
		 * @since 1.0
		 */
		public static function json($input, array $config = array()) {
			return new Json($input, $config);
		}

		/**
		 * Deserialize to JSONP (remove callback)
		 *
		 * @param string $input - JSON(P) string
		 * @param array $config - additional configuration options
		 *
		 * @return \Serializers\Decoders\Jsonp
		 *
		 * @since 1.0
		 */
		public static function jsonp($input, array $config = array()) {
			return new Jsonp($input, $config);
		}

		/**
		 * Deserialize to XML
		 *
		 * @param string $input - XML string
		 * @param array $config - additional configuration options
		 *
		 * @return \Serializers\Decoders\Xml
		 *
		 * @since 1.0
		 */
		public static function xml($input, array $config = array()) {
			return new Xml($input, $config);
		}

		/**
		 * Deserialize YAML file or string
		 *
		 * @param string $input - YAML string (from file, or string)
		 * @param array $config - additional configuration options
		 *
		 * @return \Serializers\Decoders\Yaml
		 *
		 * @since 1.0
		 */
		public static function yaml($input, array $config = array()) {
			return new Yaml($input, $config);
		}

		/**
		 * Deserialize INI file
		 *
		 * @param string $input - YAML string (from file, or string)
		 *
		 * @return \Serializers\Decoders\Ini
		 *
		 * @since 1.0
		 */
		public static function ini($input) {
			return new Ini($input);
		}
	}