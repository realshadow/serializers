<?php
	use Serializers\Decode;

	class XmlDecodeTest extends PHPUnit_Framework_TestCase {
		protected function arraysAreEqual(array $expected, array $actual) {
			$diff = array_diff_assoc($expected, $actual);

			return empty($diff);
		}

		public function testInstanceOf() {
			$input = file_get_contents(dirname(__FILE__).'/xml/encode_simple.xml');

			$xml = Decode::xml($input);

			$this->assertInstanceOf('\Serializers\Decoders\Xml', $xml);
		}

		public function testValidXML() {
			$input = file_get_contents(dirname(__FILE__).'/xml/encode_simple.xml');

			$xml = Decode::xml($input);

			$this->assertEquals($xml->isValid(), true);
		}

		public function testInvalidXML() {
			$input = 'foobar';

			$xml = Decode::xml($input);

			$this->assertEquals($xml->isValid(), false);
		}

		public function testDecode() {
			$input = file_get_contents(dirname(__FILE__).'/xml/encode_simple.xml');

			$xml = Decode::xml($input);

			$this->assertInternalType('array', $xml->toArray());
			$this->assertInternalType('object', $xml->toObject());
			$this->assertInstanceOf('\Serializers\Encoders\Json', $xml->toJSON());
			$this->assertInstanceOf('\Serializers\Encoders\Yaml', $xml->toYaml());

			$compared = $this->arraysAreEqual(
				array(
					'shop' => 'supercars.com',
					'cars' => array(
						array(
							'manufacturer' => 'VW',
							'model' => 'Golf',
							'engine' => '1.9'
						),
						array(
							'manufacturer' => 'Škoda',
							'model' => 'Rapid',
							'engine' => '1.6'
						)
					)
				),
				$xml->toArray()
			);

			$this->assertEquals($compared, true);
		}

		public function testSingularizeWords() {
			$input = file_get_contents(dirname(__FILE__).'/xml/encode_singularize.xml');

			$xml = Decode::xml($input, array(
				'singularize_words' => true,
				'include_words' => array('obrazok' => 'obrazok'),
				'exclude_words' => array('images')
			));

			$this->assertInternalType('array', $xml->toArray());
			$this->assertInternalType('object', $xml->toObject());
			$this->assertInstanceOf('\Serializers\Encoders\Json', $xml->toJSON());

			$compared = $this->arraysAreEqual(
				array(
					'shop' => 'supercars.com',
					'cars' => array(
						array(
							'manufacturer' => 'VW',
							'model' => 'Golf',
							'engine' => '1.9'
						),
						array(
							'manufacturer' => 'Škoda',
							'model' => 'Rapid',
							'engine' => '1.6'
						)
					),
					'images' => array(
						'image' => array(
							array(
								'name' => 'foo',
								'extension' => 'jpg'
							),
							array(
								'name' => 'bar',
								'extension' => 'jpg'
							)
						)
					),
					'obrazky' => array(
						array(
							'name' => 'foo',
							'extension' => 'jpg'
						)
					)
				),
				$xml->toArray()
			);

			$this->assertEquals($compared, true);
		}
	}