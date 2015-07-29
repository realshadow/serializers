<?php
	use Serializers\Decode;

	class JsonDecodeTest extends PHPUnit_Framework_TestCase {
		protected function arraysAreEqual(array $expected, array $actual) {
			$diff = array_diff_assoc($expected, $actual);

			return empty($diff);
		}

		protected function getJSON() {
			$json = '{"foo": "bar", "small": "123456", "large": 200000000000009093302, "text": "Example ratio 1000000000000000:1", "date": "/Date(1425556377427+0100)/"}';

			return $json;
		}

		public function testInstanceOf() {
			$input = $this->getJSON();

			$json = Decode::json($input);

			$this->assertInstanceOf('\Serializers\Decoders\Json', $json);
		}

		public function testValidJSON() {
			$input = $this->getJSON();

			$json = Decode::json($input);

			$this->assertEquals($json->isValid(), true);
		}

		public function testInvalidJSON() {
			$input = 'foobar';

			$json = Decode::json($input);

			$this->assertEquals($json->isValid(), false);
		}

		public function testDecode() {
			$input = $this->getJSON();

			$json = Decode::json($input);

			$this->assertInternalType('array', $json->toArray());
			$this->assertInternalType('object', $json->toObject());
			$this->assertInstanceOf('\Serializers\Encoders\Xml', $json->toXml('root'));
			$this->assertInstanceOf('\Serializers\Encoders\Yaml', $json->toYaml());

			$output = $json->toArray();
			$this->assertInternalType('string', $output['large']);

			$compared = $this->arraysAreEqual(
				array(
					'foo' => 'bar',
					'small' => 123456,
					'large' => '200000000000009093302',
					'text' => 'Example ratio 1000000000000000:1',
					'date' => '2015-03-05 12:52:57'
				),
				$output
			);

			$this->assertEquals($compared, true);
		}

		public function testDecodeWithDisabledMSDateConversion() {
			$input = $this->getJSON();

			$json = Decode::json($input, array('format_ms_date' => false));

			$compared = $this->arraysAreEqual(
				array(
					'foo' => 'bar',
					'small' => 123456,
					'large' => '200000000000009093302',
					'text' => 'Example ratio 1000000000000000:1',
					'date' => '/Date(1425556377427+0100)/'
				),
				$json->toArray()
			);

			$this->assertEquals($compared, true);
		}

		public function testEvents() {
			$input = $this->getJSON();

			$json = Decode::json($input)
				->on(\Serializers\Events::JSON_MSDATE_MATCH, function($date) {
					list(, $timestamp,,) = $date;

					return date('d.m.Y H:i:s', $timestamp);
				})->on(Serializers\Events::JSON_BIGINT, function($json) {
					# -- don't do anything
					return $json;
				});

			$compared = $this->arraysAreEqual(
				array(
					'foo' => 'bar',
					'small' => 123456,
					'large' => 2.0000000000001E+20,
					'text' => 'Example ratio 1000000000000000:1',
					'date' => '05.03.2015 12:52:57'
				),
				$json->toArray()
			);

			$this->assertEquals($compared, true);
		}
	}