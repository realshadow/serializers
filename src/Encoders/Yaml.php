<?php
	namespace Serializers\Encoders;

	use Serializers\Serializer;
	use ArrayObject;
	use Symfony\Component\Yaml\Yaml as SymfonyYaml;

	/**
	 * Class for easy YAML serialization
	 *
	 * Uses Symfony's YAML component under the hood
	 *
	 * 		$yaml = \Serializers\Encode::toYaml($array);
	 *
	 *		print_r($yaml->load());
	 *
	 *    	// or
	 *
	 *    	$yaml->toFile('config.yml');
	 *
	 * @package Serializers\Encoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Yaml extends Serializer {
		/** @var mixed|null $data - structure to be encoded */
		protected $data = null;

		/** @var array|\ArrayObject $config - default configuration */
		protected $config = array(
			'inline' => 2,
			'indent' => 4,
			'throw_exceptions' => true,
			'object_support' => false
		);

		/** @var array $headers - default headers */
		protected $headers = array(
			'Content-Type' => 'application/yaml; charset=utf-8'
		);

		/**
		 * Returns decoded data
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function parse() {
			return SymfonyYaml::dump(
				$this->data,
				$this->config->inline,
				$this->config->indent,
				$this->config->throw_exceptions,
				$this->config->object_support
			);
		}

		/**
		 * Constructor
		 *
		 * @param array $input - structure to be encoded
		 *
		 * @returns Yaml
		 *
		 * @since 1.0
		 */
		public function __construct(array $input, array $config = array()) {
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
				return $this->parse();
			} catch(\Exception $e) {
				$previousHandler = set_exception_handler(function () {});

				restore_error_handler();
				call_user_func($previousHandler, $e);

				die();
			}
		}

		/**
		 * Returns encoded data
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function load() {
			return $this->parse();
		}

		/**
		 * Dumps encoded data to file
		 *
		 * @param string $filename - path to output file
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		public function toFile($filename) {
			file_put_contents($filename, $this->parse());
		}

		/**
		 * Returns default content type
		 *
		 * @return mixed
		 *
		 * @since 1.0
		 */
		public function getContentType() {
			return $this->headers['Content-Type'];
		}

		/**
		 * Returns self with headers set. Has to be used in conjuction with print/echo and thus invoking
		 * __toString method
		 *
		 * @param array $headers - additional headers to be set
		 *
		 * @return $this
		 *
		 * @since 1.0
		 */
		public function withHeaders(array $headers = array()) {
			$headers = array_merge($this->headers, $headers);

			foreach($headers as $header => $value) {
				header(sprintf('%s: %s', $header, $value));
			}

			return $this;
		}
	}