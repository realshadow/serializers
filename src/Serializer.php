<?php
	namespace Serializers;

	use InvalidArgumentException;
	use Closure;
	use ReflectionFunction;

	/**
	 * Serializer base class grouping common methods shared between encoders and decoders
	 *
	 * @package Serializers
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	abstract class Serializer {
		/** @var array|\ArrayObject $config - configuration */
		protected $config = array();
		/** @var \Serializers\Event\Dispatcher $dispatcher - event manager */
		protected $dispatcher = null;

		/**
		 * Constructor
		 *
		 * @return \Serializers\Serializer;
		 *
		 * @since 1.0
		 */
		public function __construct() {
			$this->dispatcher = new Event\Dispatcher();
		}

		/**
		 * Method for registering event callbacks
		 *
		 * Callback must be a Closure or instance of callable
		 *
		 * @param string $event - event name
		 * @param \Closure|callable $callback - event callback
		 *
		 * @return $this
		 *
		 * @since 1.0
		 */
		public function on($event, $callback) {
			if(!$callback instanceof Closure && !is_callable($callback)) {
				throw new InvalidArgumentException('Provided event callback must be callable or a valid instance of Closure.');
			}

			$this->dispatcher->subscribe($event, $callback);

			return $this;
		}

		/**
		 * Method for removing events
		 *
		 * @param string $event - event name
		 *
		 * @return $this
		 *
		 * @since 1.0
		 */
		public function off($event) {
			$this->dispatcher->unsubscribe($event);

			return $this;
		}

		/**
		 * Method for determining if an array is associative (has string keys)
		 *
		 * @param array $array - source array
		 *
		 * @return bool
		 *
		 * @since 1.0
		 */
		protected function isAssoc(array $array) {
			return (bool) count(array_filter(array_keys($array), 'is_string'));
		}

		/**
		 * Method for getting value of a key from array
		 *
		 * @param array $array - source array
		 * @param string $key - key to get
		 *
		 * @return null|mixed
		 *
		 * @since 1.0
		 */
		protected function getOrNull(array $array, $key) {
			return isset($array[$key]) ? $array[$key] : null;
		}

		/**
		 * Method for transforming path separated by delimiter to array structure
		 *
		 * @param array $array - output array
		 * @param string $path - path to set
		 * @param mixed $value - value to be set
		 * @param string $delimiter - delimeter used for keys
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		protected function setArrayPath(&$array, $path, $value, $delimiter = '.') {
			$keys = explode($delimiter, $path);

			while(count($keys) > 1) {
				$key = array_shift($keys);

				if(ctype_digit($key)) {
					$key = (int) $key;
				}

				if(!isset($array[$key])) {
					$array[$key] = array();
				}

				$array =& $array[$key];
			}

			$array[array_shift($keys)] = $value;
		}

		/**
		 * Method for transforming plural words to singular counterparts. Built in support
		 * only for english words, with the option to specify both:
		 *  - map of words that should be ignored
		 *  - key => value map of words and their singular counterparts (mailny for non-english names)
		 *
		 * @param string $string - to be converted to singular
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function toSingular($string) {
			# -- singularization is disabled
			if($this->config->singularize_words === false) {
				return $string;
			}

			# -- clean up
			$string = strtolower(trim($string));

			switch(true) {
				case in_array($string, $this->config->exclude_words):
					break;
				case isset($this->config->include_words[$string]):
					$string = $this->config->include_words[$string];

					break;
				case preg_match('/us$/', $string):
					# -- http://en.wikipedia.org/wiki/Plural_form_of_words_ending_in_-us - thus already singular
					break;
				case preg_match('/[sxz]es$/', $string) || preg_match('/[^aeioudgkprt]hes$/', $string):
					# -- remove es
					$string = substr($string, 0, -2);
					break;
				case preg_match('/[^aeiou]ies$/', $string):
					# -- replace "ies" with "y"
					$string = substr($string, 0, -3).'y';
					break;
				case substr($string, -1) === 's' && substr($string, -2) !== 'ss':
					# -- remove singular "s"
					$string = substr($string, 0, -1);
					break;
			}

			return $string;
		}
	}