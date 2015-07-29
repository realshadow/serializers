<?php
	use Serializers\Encode;

	class JsonEncodeTest extends PHPUnit_Framework_TestCase {
		protected function generageArray() {
			$keys = array('a', 'b', 'c', 'd', 'e', 'f', 'g');
			$values = array_map(function() {
				return mt_rand();
			}, $keys);

			return array_combine($keys, $values);
		}

		public function testInstanceOf() {
			$input = $this->generageArray();

			$json = Encode::toJson($input);

			$this->assertInstanceOf('\Serializers\Encoders\Json', $json);
		}

		public function testArrayEncode() {
			$input = $this->generageArray();

			$json = Encode::toJson($input);

			json_decode($json->load());

			$this->assertEquals(json_last_error(), JSON_ERROR_NONE);
		}

		public function testObjectEncode() {
			$input = (object) $this->generageArray();

			$json = Encode::toJson($input);

			json_decode($json->load());

			$this->assertEquals(json_last_error(), JSON_ERROR_NONE);
		}

		public function testSerialize() {
			$input = $this->generageArray();

			$json = Encode::toJson($input)->on(\Serializers\Events::JSON_SERIALIZE, function($input) {
				$input['a'] = 'foo';
				$input['g'] = 'bar';

				return $input;
			});

			json_decode($json->load());

			$this->assertEquals(json_last_error(), JSON_ERROR_NONE);
		}
	}