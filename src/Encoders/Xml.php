<?php
	namespace Serializers\Encoders;

	use XMLWriter;
	use Serializers\Serializer;
	use InvalidArgumentException;
	use RuntimeException;
	use ArrayObject;

	/**
	 * Class for easy XML serialization. Support for:
	 *  - attributes, namespaces, cdata and comments
	 *  - singularization of words - products => product
	 *  - event for manipulation of nodes
	 *  - option to automatically add xsi:nil=true to null elements
	 *
	 * 		$xml = Serializers\Encode::toXml('root', $array, array('singularize_words' => true));
	 *
	 * 		print $xml->withHeaders();
	 *
	 * @package Serializers\Encoders
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Xml extends Serializer {
		/** @var string ATTRIBUTES - special attributes key */
		const ATTRIBUTES = '@attributes';
		/** @var string NS - special namespaces key */
		const NS = '@namespaces';
		/** @var string TEXT - special content key */
		const TEXT = '@text';
		/** @var string CDATA - special cdata key */
		const CDATA = '@cdata';
		/** @var string COMMENT - special comment key */
		const COMMENT = '@comment';

		/** @var array|null $data - structure to be encoded */
		protected $data = null;
		/** @var string|null $root - root element name */
		protected $root = null;
		/** @var \XMLWriter $writer - writer instance */
		protected $writer = null;
		/** @var \ArrayObject|array - config  */
		protected $config = array(
			'singularize_words' => false,
			'nil_on_null' => false,
			'exclude_words' => array(),
			'include_words' => array(),
			'document_version' => '1.0',
			'indent_string' => ' '
		);

		/** @var array $headers - default headers */
		protected $headers = array(
			'Content-Type' => 'application/xml; charset=utf-8'
		);

		/**
		 * Detects if potentional node name is a valid XML element name
		 *
		 * @param string $node - potentional node name
		 *
		 * @return bool
		 *
		 * @throws \RuntimeException
		 *
		 * @see http://www.w3.org/TR/2008/REC-xml-20081126/#NT-Name
		 * @since 1.0
		 */
		protected function assertElementName($node) {
			$regex = '~^[:A-Z_a-z\\xC0-\\xD6\\xD8-\\xF6\\xF8-\\x{2FF}\\x{370}-\\x{37D}\\x{37F}-\\x{1FFF}\\x{200C}-\\x{200D}\\x{2070}-\\x{218F}\\x{2C00}-\\x{2FEF}\\x{3001}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFFD}\\x{10000}-\\x{EFFFF}][:A-Z_a-z\\xC0-\\xD6\\xD8-\\xF6\\xF8-\\x{2FF}\\x{370}-\\x{37D}\\x{37F}-\\x{1FFF}\\x{200C}-\\x{200D}\\x{2070}-\\x{218F}\\x{2C00}-\\x{2FEF}\\x{3001}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFFD}\\x{10000}-\\x{EFFFF}.\\-0-9\\xB7\\x{0300}-\\x{036F}\\x{203F}-\\x{2040}]*$~u';

			if(empty($node) || !is_string($node) || !preg_match($regex, $node)) {
				# -- find a better way to handle this
				#throw new RuntimeException('Node "'.(string) $node.'" is not a valid XML element name.');
			}

			return true;
		}

		/**
		 * Method for working with namespaces
		 *
		 * @param array $ns - namespace definition
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		protected function handleNamespace($ns) {
			$name = $prefix = null;
			if(($name = $this->getOrNull($ns, 'name'))) {
				$temp = explode(':', $name);

				if(count($temp) === 2) {
					list($name, $prefix) = $temp;
				} else {
					$name = current($temp);
				}
			}

			$this->writer->writeAttributeNs(
				$name,
				$prefix,
				$this->getOrNull($ns, 'uri'),
				$this->getOrNull($ns, 'content')
			);
		}

		/**
		 * Method for parsing attributes
		 *
		 * @param array $attributes - list of attributes
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		protected function parseAttributes($attributes) {
			foreach($attributes as $attribute => $item) {
				if($attribute === self::NS) {
					if($this->isAssoc($item) === false) {
						foreach($item as $ns) {
							$this->handleNamespace($ns);
						}
					} else {
						$this->handleNamespace($item);
					}
				} else {
					$this->writer->writeAttribute($attribute, $item);
				}
			}
		}

		/**
		 * Serialization of array to XML
		 *
		 * XMLWriter supports only UTF-8 and thus the encoding can not be changed
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		protected function serialize() {
			$this->writer = new XMLWriter;

			$this->writer->openMemory();
			$this->writer->setIndent(true);
			$this->writer->setIndentString($this->config->indent_string);

			# -- has to be UTF-8
			$this->writer->startDocument($this->config->document_version, 'UTF-8');

			if($this->assertElementName($this->root)) {
				$this->writer->startElement($this->root);
			}

			if(!empty($this->data[self::ATTRIBUTES])) {
				$this->parseAttributes($this->data[self::ATTRIBUTES]);

				unset($this->data[self::ATTRIBUTES]);
			}

			$this->rotate($this->data);

			$this->writer->endElement();
			$this->writer->endDocument();

			return $this->writer->outputMemory();
		}

		/**
		 * Method for handling nested nodes
		 *
		 * @param string $node - current node
		 * @param array $content - content of current node
		 * @param array $attributes - list of attributes
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		protected function handleNested($node, $content, $attributes = array()) {
			if($this->assertElementName($node)) {
				$this->writer->startElement($node);
			}

			if(!empty($attributes)) {
				$this->parseAttributes($attributes);
			}

			$this->rotate($content);

			$this->writer->endElement();
		}

		/**
		 * Method for handling elements that have children and can be transformed to singular
		 *
		 * @param string $node - node name
		 * @param array $item - node content
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		protected function handleSingular($node, array $item) {
			$singular = $this->toSingular($node);
			$isSingular = $this->config->singularize_words === true && $singular !== $node;

			if($isSingular && $this->assertElementName($node)) {
				$this->writer->startElement($node);
			}

			foreach($item as $key => $subitem) {
				if($isSingular) {
					$item[$key] = array($singular => $subitem);

					$this->rotate($item[$key]);
				} else {
					$method = 'handle'.(is_array($item[$key]) ? 'Nested' : 'Element');

					$this->{$method}($node, $item[$key]);
				}
			}

			if($isSingular) {
				$this->writer->endElement();
			}
		}

		/**
		 * Method for parsing of elements
		 *
		 * @param string $node - node name
		 * @param string $item - node content
		 * @param bool $wrap - should be wrapped in array for singularization
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		protected function handleElement($node, $item, $wrap = true) {
			if($this->dispatcher->listensTo($node)) {
				$this->dispatcher->trigger($node, array($this->writer, $node, $item));
			}	else {
				if($node === self::COMMENT) {
					if(!is_array($item)) {
						$item = array($item);
					}

					foreach($item as $comment) {
						$this->writer->writeComment($comment);
					}
				} elseif($node === self::CDATA) {
					$this->writer->writeCdata($item);
				} else {
					if($wrap === true) {
						if($this->assertElementName($node)) {
							if($this->config->nil_on_null === true && is_null($item)) {
								$this->writer->startElement($node);

								$this->writer->writeAttribute('xsi:nil', 'true');

								$this->writer->writeRaw($item);

								$this->writer->endElement();
							} else {
								$this->writer->writeElement($node, $item);
							}
						}
					} else {
						$this->writer->writeRaw($item);
					}
				}
			}
		}

		/**
		 * Mail loop for handling the provided array
		 *
		 * @param array $data - array to be serialized
		 *
		 * @return void
		 *
		 * @since 1.0
		 */
		protected function rotate($data) {
			foreach($data as $node => $item) {
				if(is_array($item)) {
					if(count($item) === 2 && array_key_exists(self::TEXT, $item)) {
						if(isset($item[self::TEXT]) && is_array($item[self::TEXT])) {
							if($this->isAssoc($item[self::TEXT])) {
								$this->handleNested($node, $item[self::TEXT], $item[self::ATTRIBUTES]);
							} else {
								$this->handleSingular($node, $item);
							}
						} else {
							if($this->assertElementName($node)) {
								$this->writer->startElement($node);
							}

							$this->parseAttributes($item[self::ATTRIBUTES]);
							$this->handleElement($node, $item[self::TEXT], false);

							$this->writer->endElement();
						}
					} else if($this->isAssoc($item)) {
						$this->handleNested($node, $item);
					} else {
						$this->handleSingular($node, $item);
					}
				} else {
					$this->handleElement($node, $item);
				}
			}
		}

		/**
		 * Constructor
		 *
		 * @param string $root - root element
		 * @param mixed $input - structure to be encoded
		 * @param array $config - additional configuration options
		 *
		 * @returns Xml
		 *
		 * @throws \InvalidArgumentException
		 *
		 * @since 1.0
		 */
		public function __construct($root, array $input, array $config = array()) {
			if(!is_string($root) || empty($root)) {
				throw new InvalidArgumentException('Root element must be a valid string.');
			}

			parent::__construct();

			$this->root = $root;
			$this->data = $input;

			$config = array_merge($this->config, $config);

			$this->config = new ArrayObject($config, ArrayObject::ARRAY_AS_PROPS);
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
				return $this->serialize();
			} catch(\Exception $e) {
				$previousHandler = set_exception_handler(function () {});

				restore_error_handler();
				call_user_func($previousHandler, $e);

				die();
			}
		}

		/**
		 * Returns serialized XML
		 *
		 * @return string
		 *
		 * @since 1.0
		 */
		public function load() {
			return $this->serialize();
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