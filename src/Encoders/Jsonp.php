<?php
	namespace Serializers\Encoders;

	use InvalidArgumentException;
	use Serializers\Events;

	/**
	 * Class for easy JSONP serialization, behaves like JSON serializer with additional checks for
	 * callback function name validation, which can be changed with custom event
	 *
	 * 		$jsonp = Serializers\Encode::toJsonp('_foo.bar', array(
	 *      	'foo' => 'bar',
	 *			'bar' => 'foo'
	 *		));
	 *
	 *      $jsonp->allowCors('*', array('GET', 'POST'));
	 *
	 *		print $jsonp->withHeaders();
	 *
	 * @package Serializers\Encoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Jsonp extends Json {
		/** @var array $headers - default headers */
		protected $headers = array(
			# -- as per http://www.rfc-editor.org/rfc/rfc4329.txt
			'Content-Type' => 'application/javascript; charset=utf-8'
		);

		/**
		 * Method for checking the validity of provided callback. Callback must conform
		 * to ECMA's specification of valid javascript callback
		 *
		 * @param string $callback - callback name
		 *
		 * @return bool
		 *
		 * @see http://www.ecma-international.org/publications/files/ECMA-ST/Ecma-262.pdf (chapter 13, 7.6)
		 * @see http://stackoverflow.com/questions/3128062/is-this-safe-for-providing-jsonp
		 * @since 1.0
		 */
		protected function isCallbackValid($callback) {
			if(!is_string($callback)) {
				return false;
			}

			$syntax = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}\.]*+$/u';

			$reservedWords = array(
				'break', 'do', 'instanceof', 'typeof', 'case',
				'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue',
				'for', 'switch', 'while', 'debugger', 'function', 'this', 'with',
				'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum',
				'extends', 'super', 'const', 'export', 'import', 'implements', 'let',
				'private', 'public', 'yield', 'interface', 'package', 'protected',
				'static', 'null', 'true', 'false'
			);

			return preg_match($syntax, $callback) && !in_array(mb_strtolower($callback, 'UTF-8'), $reservedWords);
		}

		protected function parse($includeCallback = true) {
			if($includeCallback === true) {
				if($this->dispatcher->listensTo(Events::JSONP_VALID_CALLBACK)) {
					$callbackValid = $this->dispatcher->trigger(Events::JSONP_VALID_CALLBACK, array($this->callback));
				} else {
					$callbackValid = $this->isCallbackValid($this->callback);
				}

				if($callbackValid === false) {
					throw new InvalidArgumentException('Provided callback name is not valid.');
				}
			}

			if($this->dispatcher->listensTo(Events::JSON_SERIALIZE)) {
				$json = json_encode($this->dispatcher->trigger(Events::JSON_SERIALIZE, array($this->data)));
			} else {
				$json = json_encode($this->data);
			}

			if($includeCallback === true) {
				return sprintf('%s(%s)', $this->callback, $json);
			} else {
				return $json;
			}
		}

		/**
		 * Constructor
		 *
		 * @param string $callback - callback name
		 * @param mixed $input - structure to be encoded
		 *
		 * @returns Jsonp
		 *
		 * @since 1.0
		 */
		public function __construct($callback, $input) {
			parent::__construct($input);

			$this->callback = $callback;
			$this->data = $input;
		}

		/**
		 * Self explanatory
		 *
		 * Checks if callback is valid before outputting it
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function __toString() {
			try {
				return $this->load(true);
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
		 * @param bool $includeCallback - if data should return as simple JSON or JSON with callback
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function load($includeCallback = false) {
			return $this->parse($includeCallback);
		}

		/**
		 * Shortcut method for setting Access-Control-Allow-Origin and Access-Control-Allow-Methods
		 * headers. Additional headers can be set via withHeaders() method
		 *
		 * @param string $origin - origin domain
		 * @param string $allowMethods - allowed HTTP methods
		 *
		 * @return $this
		 *
		 * @since 1.0
		 */
		public function allowCors($origin = '*', $allowMethods = 'GET') {
			if(!is_null($origin)) {
				$this->headers['Access-Control-Allow-Origin'] = $origin;
			}

			if(!is_null($allowMethods)) {
				if(is_array($allowMethods)) {
					$allowMethods = array_map('strtoupper', $allowMethods);

					$header = join(', ', $allowMethods);
				} else {
					$header = $allowMethods;
				}

				$this->headers['Access-Control-Allow-Methods'] = $header;
			}

			return $this;
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