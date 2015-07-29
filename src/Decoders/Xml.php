<?php
	namespace Serializers\Decoders;

	use InvalidArgumentException;
	use RuntimeException;
	use Serializers\Encode;
	use Serializers\Decode;
	use Serializers\Encoders;
	use Serializers\Events;
	use Serializers\Serializer;
	use ArrayObject;
	use SimpleXMLElement;

	/**
	 * Class for easy XML deserialization
	 *
	 * By default every comment, attribute, namespace will be stripped from the result as well as
	 * the root element. Every option can be turned off/on in config
	 *
	 * Deserialization is done by SimpleXML coupled with json_encode (in this case provided JSON decoder) with one
	 * simple addition - SimpleXML object will be transformed before being encoded with json_encode (backport of
	 * JSONSerialize interface)
	 *
	 * Comments are parsed separately via DOMXpath (since SimpleXML can not handle them) and are
	 * added to a separate array with indexes poiting to their original location, with that, it should
	 * be easy to merge comments with the main result and receive the original array.
	 *
	 * By default, transforming elements from their singular counterpart back to plural and thus
	 * flattening the whole array is turned off and must be turned on. Its possible to both - include
	 * new mappings for words and to exclude specific words. This works exactly as in provided XML
	 * encoder.
	 *
	 * The whole goal of flattening is to get back "exactly" the same array as the one that was used
	 * to create provided XML.
	 *
	 * 		$xml = <<<EOT
	 *			<?xml version="1.0" encoding="UTF-8"?>
	 *			<root>
	 *				<foo>bar</foo>
	 *				<bars>
	 *					<bar>yes</bar>
	 *					<bar>no</bar>
	 *				</bars>
	 *			</root>
	 *		EOT;
	 *
	 *    	$output = Serializers\Decode::xml($xml);
	 *
	 *    	print_r($output->toArray());
	 *
	 * @package Serializers\Decoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Xml extends Serializer {
		/** @var array|\ArrayObject $config */
		protected $config = array(
			'strip_comments' => true,
			'strip_attributes' => true,
			'strip_namespaces' => true,
			'strip_root' => true,
			'singularize_words' => false,
			'exclude_words' => array(),
			'include_words' => array(),
		);

		/** @var null|string - provided XML */
		protected $data = null;

		/** @var int $maxRecursionDepth - maximum allowed recursion depth */
		protected $maxRecursionDepth = 500;

		/**
		 * Processing of the whole tree
		 *
		 * @param \SimpleXMLElement $xml - provided XML
		 * @param int $depth - current recursion depth
		 *
		 * @return array
		 *
		 * @since 1.0
		 */
		protected function process(SimpleXMLElement $xml, $depth = 0) {
			if($depth > $this->maxRecursionDepth) {
				throw new RuntimeException('Maximum recursion depth of processing XML has been exceeded.');
			}

			$children = $xml->children();
			$name = $xml->getName();

			$value = trim(strval($xml));
			$attributes = $xml->attributes();

			if($children->count() === 0) {
				# -- parse attributes
				if($attributes->count() > 0 && $this->config->strip_attributes === false) {
					$attrs[Encoders\Xml::ATTRIBUTES] = array_map('strval', iterator_to_array($attributes));

					if(!empty($value)) {
						$attrs[Encoders\Xml::TEXT] = $value;
					}

					return array($name => $attrs);
				}

				return array($name => $value);
			}

			$output = $nested = array();
			foreach($children as $child) {
				/** @var SimpleXMLElement $child */
				$childName = $child->getName();

				$element = $this->process($child, $depth++);

				if($this->toSingular($name) === $childName) {
					# -- flatten
					$element = $this->getOrNull($element, $childName);

					$output[] = $element;
				} else {
					if(isset($output[$childName])) {
						if(empty($nested[$childName])) {
							# -- this will avoid making broken arrays (first element is broken, second starts at [1])
							$output[$childName] = array($output[$childName]);

							$nested[$childName] = true;
						}

						$output[$childName][] = $element[$childName];
					} else {
						$output[$childName] = $element[$childName];
					}
				}
			}

			if(!empty($value)) {
				$output[Encoders\Xml::TEXT] = $value;
			}

			return array($name => $output);
		}

		/**
		 * Callback used by JSON serializer
		 *
		 * Will parse root attributes, namespaces and merge the tree with
		 * root if enabled
		 *
		 * @note has to be public because it has to be callable by dispatcher (so I am hiding it
		 * as deprecated)
		 *
		 * @param \SimpleXMLElement $xml - provided XML
		 * @param int $depth - current recursion depth
		 *
		 * @return array
		 *
		 * @deprecated
		 * @since 1.0
		 */
		public function serialize(SimpleXMLElement $xml, $depth = 0) {
			$output = array();
			# -- parse Attributes
			if($this->config->strip_attributes === false) {
				if(($attributes = $xml->attributes())) {
					$output[Encoders\Xml::ATTRIBUTES] = array_map('strval', iterator_to_array($attributes));
				}
			}

			# -- parse namespaces
			if($this->config->strip_namespaces === false) {
				if(($namespaces = $xml->getDocNamespaces()) && $depth === 0) {
					$output[Encoders\Xml::ATTRIBUTES][Encoders\Xml::NS] = $namespaces;
				}
			}

			# -- parse comments
			if($this->config->strip_comments === false && $depth === 0) {
				$doc = new \DOMDocument();
				$doc->loadXML($this->data);

				$xpath = new \DOMXPath($doc);

				foreach($xpath->query('//comment()') as $comment) {
					/** @var \DOMNode $comment */

					$path = preg_replace_callback('#(/comment\(\)|\/|\[|\])#', function($matches) {
						$start = current($matches);
						$end = end($matches);

						if($start === '[' || $end === '/') {
							return '.';
						}

						return '';
					}, $comment->getNodePath());

					if($this->config->strip_root === true) {
						$path = str_replace('.'.$xml->getName().'.', '', $path);
					} else {
						$path = ltrim($path, '.');
					}

					if(empty($path)) {
						$path = 0;
					}

					$this->setArrayPath($output[Encoders\Xml::COMMENT], $path, $comment->textContent);
				}
			}

			# -- process the tree
			$result = $this->process($xml, $depth);

			# -- merge with root?
			if($this->config->strip_root === true) {
				$result = $this->getOrNull($result, $xml->getName());
			} else {
				$output = array(
					$xml->getName() => $output
				);
			}

			$output = array_merge_recursive($output, $result);

			return $output;
		}

		/**
		 * Method for converting JSON to PHP array or object data type
		 *
		 * @param bool $toArray - force assoc array
		 *
		 * @return null|array|object
		 *
		 * @throws RuntimeException
		 *
		 * @since 1.0
		 */
		protected function toPrimitive($toArray = false) {
			if(!$this->isValid()) {
				throw new RuntimeException('Provided XML is not valid.');
			}

			$xml = new SimpleXMLElement($this->data, LIBXML_NOCDATA);

			$json = Encode::toJson($xml)
				->on(Events::JSON_SERIALIZE, array($this, 'serialize'));

			$method = ($toArray === true ? 'toArray' : 'toObject');

			return Decode::json($json->load())->{$method}();
		}

		/**
		 * Constructor
		 *
		 * @param string $input - JSON string
		 * @param array $config - possible configuration changes
		 *
		 * @returns Xml
		 *
		 * @throws InvalidArgumentException
		 *
		 * @since 1.0
		 */
		public function __construct($input, array $config = array()) {
			if(!is_string($input)) {
				throw new InvalidArgumentException('XML serializer accepts only strings.');
			}

			parent::__construct();

			$config = array_merge($this->config, $config);

			$this->config = new ArrayObject($config, ArrayObject::ARRAY_AS_PROPS);

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
				return $this->data;
			} catch(\Exception $e) {
				$previousHandler = set_exception_handler(function () {});

				restore_error_handler();
				call_user_func($previousHandler, $e);

				die();
			}
		}

		/**
		 * Check to see if provided JSON string is valid
		 *
		 * @return bool
		 *
		 * @since 1.0
		 */
		public function isValid() {
			libxml_use_internal_errors(true);

			$doc = simplexml_load_string($this->data);

			libxml_clear_errors();

			return !empty($doc);
		}

		/**
		 * Parse to array
		 *
		 * @return array
		 *
		 * @since 1.0
		 */
		public function toArray() {
			return $this->toPrimitive(true);
		}

		/**
		 * Parse to object
		 *
		 * @return \stdClass
		 *
		 * @since 1.0
		 */
		public function toObject() {
			return $this->toPrimitive();
		}

		/**
		 * Parse to JSON and return instance of JSON Encoder
		 *
		 * @param array $config - configuration
		 *
		 * @return \Serializers\Encoders\Json
		 *
		 * @since 1.0
		 */
		public function toJSON(array $config = array()) {
			$json = Encode::toJson($this->toArray(), $config);

			return $json;
		}

		/**
		 * Transform to JSONP
		 *
		 * @param string $callback - callback name
		 *
		 * @return \Serializers\Encoders\Jsonp
		 *
		 * @since 1.0
		 */
		public function toJsonp($callback) {
			$jsonp = Encode::toJsonp($callback, $this->toArray());

			return $jsonp;
		}

		/**
		 * Transform to YAML
		 *
		 * @param array $config - additional configuration
		 *
		 * @return \Serializers\Encoders\Yaml
		 *
		 * @since 1.0
		 */
		public function toYaml(array $config = array()) {
			$yaml = Encode::toYaml($this->toArray(), $config);

			return $yaml;
		}

		/**
		 * Transform to INI
		 *
		 * @return \Serializers\Encoders\Ini
		 *
		 * @since 1.0
		 */
		public function toIni() {
			$ini = Encode::toIni($this->toArray());

			return $ini;
		}
	}