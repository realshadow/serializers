<?php
	namespace Serializers\Encoders;

	use Serializers\Serializer;

	/**
	 * Class for easy INI serialization
	 *
	 * The functionality is limited to basic INI formats, e.g. no support for inheritance.
	 * As I can't see a good use case at the moment, this class is here only for keeping
	 * a complete stack of encoders/decoders together
	 *
	 * 		$ini = \Serializers\Encode::toIni($array);
	 *
	 *		print_r($ini->load());
	 *
	 * @package Serializers\Encoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Ini extends Serializer {
		/** @var mixed|null $data - structure to be encoded */
		protected $data = null;

		/** @var array $sections - list of sections */
		protected $sections = array();

		/**
		 * Generates of list of first hand keys that should be parsed as sections
		 *
		 * @param mixed $value - current value
		 * @param string $key - current key
		 *
		 * @since 1.0
		 */
		protected function parseSections(&$value, $key) {
			if(is_array($value) && $this->isAssoc($value)) {
				$this->sections[] = $key;
			}
		}

		/**
		 * Method for escaping characters that can not be set in values
		 *
		 * For now, supports only "="
		 *
		 * @param array|string $value - value to be escaped
		 *
		 * @return array|string
		 *
		 * @since 1.0
		 */
		protected function escapeValue($value) {
			if(is_array($value)) {
				$value = array_map(array($this, 'escapeValue'), $value);
			} else {
				if(is_bool($value)) {
					$value = (bool) $value ? 'true' : 'false';
				}

				if(strpos($value, '=')) {
					$value = sprintf('"%s"', $value);
				}
			}

			return $value;
		}

		/**
		 * Parses provided array to INI string format
		 *
		 * @param array $data - provided data
		 * @param string $prefix - current prefix
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function process(array $data, $prefix = '') {
			$output = '';
			foreach($data as $key => $value) {
				if(in_array($key, $this->sections)) {
					$output .= PHP_EOL.sprintf('[%s]', $key).PHP_EOL;

					$output .= $this->process($value);

					continue;
				}

				if(is_array($value)) {
					if($this->isAssoc($value)) {
						if(!empty($prefix)) {
							$_prefix = $prefix.$key;
						} else {
							$_prefix = $key;
						}

						$output .= $this->process($value, $_prefix.'.');
					} else {
						$output .= sprintf('%s%s = [%s]', $prefix, $key, join(',', $this->escapeValue($value))).PHP_EOL;
					}
				} else {
					$output .= sprintf('%s%s = %s', $prefix, $key, $this->escapeValue($value)).PHP_EOL;
				}
			}

			return $output;
		}

		/**
		 * Returns decoded data
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function parse() {
			$this->sections = array();

			# -- closure binding is not available in 5.3 so...
			array_walk($this->data, array($this, 'parseSections'), $this->data);

			$output = $this->process($this->data);

			return $output;
		}

		/**
		 * Constructor
		 *
		 * @param array $input - structure to be encoded
		 *
		 * @returns Ini
		 *
		 * @since 1.0
		 */
		public function __construct(array $input) {
			parent::__construct();

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
	}