<?php
	use Serializers\Encode;

	class JsonpEncodeTest extends PHPUnit_Framework_TestCase {
		protected function generageArray() {
			$keys = array('a', 'b', 'c', 'd', 'e', 'f', 'g');
			$values = array_map(function() {
				return mt_rand();
			}, $keys);

			return array_combine($keys, $values);
		}

		public function testInstanceOf() {
			$input = $this->generageArray();

			$json = Encode::toJsonp('foo', $input);

			$this->assertInstanceOf('\Serializers\Encoders\Jsonp', $json);
		}

		public function testEncode() {
			$input = $this->generageArray();

			$json = Encode::toJsonp('foo', $input);

			$this->assertEquals('foo('.json_encode($input).')', $json->load(true));
		}

		/**
		 * @expectedException InvalidArgumentException
		 */
		public function testCallbackJSKeyword() {
			$input = $this->generageArray();

			$json = Encode::toJsonp('break', $input);

			$json->load(true);
		}

		/**
		 * @expectedException InvalidArgumentException
		 */
		public function testCallbackInvalidCharacter() {
			$input = $this->generageArray();

			$json = Encode::toJsonp('foo|bar§', $input);

			$json->load(true);
		}

		/**
		 * @expectedException InvalidArgumentException
		 */
		public function testCallbackEvent() {
			$input = $this->generageArray();

			$json = Encode::toJsonp('foo|bar', $input)->on(\Serializers\Events::JSONP_VALID_CALLBACK, function($callback) {
				return false;
			});

			$json->load(true);
		}
	}