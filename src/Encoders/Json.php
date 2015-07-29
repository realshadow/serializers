<?php
	namespace Serializers\Encoders;

	use DateTimeZone;
	use DateTime;
	use Serializers\Events;
	use Serializers\Serializer;

	/**
	 * Class for easy JSON serialization
	 *
	 * It is possible to register JSON_SERIALIZE event that works exactly like PHP 5.4 JsonSerializable interface
	 * and thus allows modifying the object before it is converted to JSON
	 *
	 * 			$json = Serializers\Encode::toJson(array(
	 *        		'foo' => 'bar',
	 *				'foodate' => date('d.m.Y H:i:s')
	 *			))->onSerialize(function($data) {
	 *				$data['foodate'] = Serializers\Encoders\Json::toMSDate($data['foodate']);
	 *
	 *				return $data;
	 *			});
	 *
	 *			print $json->withHeaders();
	 *
	 * @package Serializers\Encoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Json extends Serializer {
		/** @var mixed|null $data - structure to be encoded */
		protected $data = null;

		/** @var array $headers - default headers */
		protected $headers = array(
			'Content-Type' => 'application/json; charset=utf-8'
		);

		/**
		 * Conversion of date to MS JSON date format
		 *
		 * @param string $date
		 * @param null $timezone
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public static function toMSDate($date = 'now', $timezone = null) {
			# -- timezone checking
			if(is_null($timezone)) {
				$timezone = new DateTimeZone(date_default_timezone_get());
			} elseif(!$timezone instanceof DateTimeZone) {
				$timezone = new DateTimeZone($timezone);
			}

			$date = new DateTime($date, $timezone);

			return '/Date('.($date->getTimestamp() * 1000).')/';
		}

		/**
		 * Returns decoded data
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function parse() {
			if($this->dispatcher->listensTo(Events::JSON_SERIALIZE)) {
				return json_encode($this->dispatcher->trigger(Events::JSON_SERIALIZE, array($this->data)));
			} else {
				return json_encode($this->data);
			}
		}

		/**
		 * Constructor
		 *
		 * @param mixed $input - structure to be encoded
		 *
		 * @returns Json
		 *
		 * @since 1.0
		 */
		public function __construct($input) {
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