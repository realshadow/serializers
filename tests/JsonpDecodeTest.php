<?php
	use Serializers\Decode;

	class JsonpDecodeTest extends PHPUnit_Framework_TestCase {
		protected function arraysAreEqual(array $expected, array $actual) {
			$diff = array_diff_assoc($expected, $actual);

			return empty($diff);
		}

		protected function getJSON() {
			$json = 'foo.bar_ofAwesomness({"foo": "bar", "small": "123456", "large": 200000000000009093302, "text": "Example ratio 1000000000000000:1", "date": "/Date(1425556377427+0100)/"})';

			return $json;
		}

		public function testInstanceOf() {
			$input = $this->getJSON();

			$json = Decode::jsonp($input);

			$this->assertInstanceOf('\Serializers\Decoders\Jsonp', $json);
		}

		public function testDecode() {
			$input = $this->getJSON();

			$json = Decode::jsonp($input);

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
	}