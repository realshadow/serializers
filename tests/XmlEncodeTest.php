<?php
	use Serializers\Encode;

	class XmlEncodeTest extends PHPUnit_Framework_TestCase {
		protected function generageArray() {
			$keys = array('a', 'b', 'c', 'd', 'e', 'f', 'g');
			$values = array_map(function() {
				return mt_rand();
			}, $keys);

			return array_combine($keys, $values);
		}

		public function testInstanceOf() {
			$input = $this->generageArray();

			$xml = Encode::toXml('root', $input);

			$this->assertInstanceOf('\Serializers\Encoders\Xml', $xml);
		}

		public function testValidXML() {
			$input = $this->generageArray();

			$xml = Encode::toXml('root', $input);

			libxml_use_internal_errors(true);

			$doc = simplexml_load_string($xml->load());

			libxml_clear_errors();

			$this->assertEquals(!empty($doc), true);
		}

		public function testEncode() {
			$input = array(
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
			);

			$xml = Encode::toXml('root', $input);

			$this->assertXmlStringEqualsXmlFile(dirname(__FILE__).'/xml/encode_simple.xml', $xml->load());
		}

		public function testSingularizeWords() {
			$input = array(
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
					array(
						'name' => 'foo',
						'extension' => 'jpg'
					),
					array(
						'name' => 'bar',
						'extension' => 'jpg'
					)
				),
				'obrazky' => array(
					array(
						'name' => 'foo',
						'extension' => 'jpg'
					)
				)
			);

			$xml = Encode::toXml('root', $input, array(
				'singularize_words' => true,
				'include_words' => array('obrazky' => 'obrazok'),
				'exclude_words' => array('images')
			));

			$this->assertXmlStringEqualsXmlFile(dirname(__FILE__).'/xml/encode_singularize.xml', $xml->load());
		}

		/**
		 * Tests bad element name, e.g numeric indexes in array
		 *
		 * @expectedException RuntimeException
		 */
		public function testEncodeBadInput() {
			$input = array_values($this->generageArray());

			$xml = Encode::toXml('root', $input);

			$xml->load();
		}
	}