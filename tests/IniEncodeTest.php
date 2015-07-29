<?php
	use Serializers\Encode;
	use Serializers\Decode;

	class IniEncodeTest extends PHPUnit_Framework_TestCase {
		protected function arraysAreEqual(array $expected, array $actual) {
			$diff = array_diff_assoc($expected, $actual);

			return empty($diff);
		}

		protected function getData() {
			return array(
				'a' => 'd',
				'b' => array('test' => 'c'),
				'database' => array(
					'default' => array(
						'name' => 'db',
						'host' => 'master.db',
						'ip' => 'dd',
					)
				),
				'array' => array('a', '1', 3),
			);
		}

		public function testInstanceOf() {
			$input = $this->getData();

			$ini = Encode::toIni($input);

			$this->assertInstanceOf('\Serializers\Encoders\Ini', $ini);
		}

		public function testEncode() {
			$iniString = file_get_contents(dirname(__FILE__).'/ini/output.ini');
			$input = $this->getData();

			$ini = Encode::toIni($input);

			$this->assertEquals($iniString, $ini->load());
		}
	}