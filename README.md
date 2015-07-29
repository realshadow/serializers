# PHP collection of serializers for JSON, JSONP, XML, YAML, INI
Written for PHP 5.3 to solve a specific issue - **singularization/pluralization** of elements when transforming them from and to arrays. The difference between serializers was obvious when we passed XML files between PHP, Python and .NET. Later, more serializers were added to get a complete package.

The goal was to automatically perform singularization of arrays when serializing to XML. E.g.

```php
array(
	'products' => array(
	    array(
	        'brand' => 'Samsung',
	        'model' => 'Galaxy',
	        'price' => 999
	    ),
	    array(
	        'brand' => 'HTC',
	        'model' => 'One',
	        'price' => null
	    )
	)
);
```

Which, when serialized, would become

```xml
 <products>
  <product>
   <brand>Samsung</brand>
   <model>Galaxy</model>
   <price>999</price>
  </product>
  <product>
   <brand>HTC</brand>
   <model>One</model>
   <price xsi:nil="true"></price>
  </product>
 </products>
```

instead of (this is the default behaviour)

```xml
 <products>
   <brand>Samsung</brand>
   <model>Galaxy</model>
   <price>999</price>
 </products>
 <products>
   <brand>HTC</brand>
   <model>One</model>
   <price xsi:nil="true"></price>
 </products>
```

Same rule applies to deserialization which, by using different serializers, would turn into into different arrays. By applying singularization the other way around, it is possible to **get back the same array** as was used for serialization.

### XML Serialization
Support for

* attributes, namespaces, cdata and comments
* singularization of words - products => product
* option to automatically add xsi:nil=true to null elements
* event for manipulation of nodes

```php
$array = array(
	Serializers\Encoders\Xml::ATTRIBUTES => array(
		'xmlns' => 'http://cfh.sk/izmluvnik/xsell',
		Serializers\Encoders\Xml::NS => array(
			array(
				'name' => 'xmlns:xsi',
				'content' => 'http://www.w3.org/2001/XMLSchema-instance'
			),
			array(
				'name' => 'xmlns:xsd',
				'content' => 'http://www.w3.org/2001/XMLSchema'
			)
		)
	),
	'products' => array(
	    array(
	        'brand' => 'Samsung',
	        'model' => 'Galaxy',
	        'price' => 999
	    ),
	    array(
	        'brand' => 'HTC',
	        'model' => 'One',
	        'price' => null
	    )
	)
);

$xml = Serializers\Encode::toXml('root', $array, array(
    'singularize_words' => true,
    'nil_on_null' => true
));

print $xml->withHeaders();
```

Which outputs

```xml
<?xml version="1.0" encoding="UTF-8"?>
<root xmlns="http://cfh.sk/izmluvnik/xsell" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
 <products>
  <product>
   <brand>Samsung</brand>
   <model>Galaxy</model>
   <price>999</price>
  </product>
  <product>
   <brand>HTC</brand>
   <model>One</model>
   <price xsi:nil="true"></price>
  </product>
 </products>
</root>
```

### XML Deserialization
By default every comment, attribute, namespace will be stripped from the result as well as
the root element. Every option can be turned off/on in config

Deserialization is done by SimpleXML coupled with json_encode (in this case provided JSON decoder) with one
simple addition - SimpleXML object will be transformed before being encoded with json_encode (backport of
JSONSerialize interface)

Comments are parsed separately via DOMXpath (since SimpleXML can not handle them) and are
added to a separate array with indexes poiting to their original location, with that, it should
be easy to merge comments with the main result and receive the original array.

By default, transforming elements from their singular counterpart back to plural and thus
flattening the whole array is turned off and must be turned on. Its possible to both - include
new mappings for words and to exclude specific words. This works exactly as in provided XML
encoder.

The whole goal of flattening is to get back **exactly** the same array as the one that was used
to create provided XML.

```php
// using the same XML that we got in serialization
$output = Serializers\Decode::xml($xml->load(), array('singularize_words' => true));
```

Which outputs **exactly the same array** as was used in the example before

```php
print_r($output->toArray());
```

### JSON Deserialization
Support for:
 *  automatic parsing of Microsoft's JSON date format (e.g. `/Date(1425556377427+0100)/`)
 *  backport of `JSON_BIGINT_AS_STRING` available from PHP 5.4.0
 *  isValid method for checking validity of provided JSON string
 *  possible conversion from JSON to:
    - PHP types (string, array, object)
    - XML, YAML, INI

With overriding configuration one can change the default timeformat and timezone settings form MS date conversion, or turn it off completely.

It's possible to register an event callback to be called during escaping of BIGINT, in case said escaping is not good enough, or to turning it off completely.

Callback method must accept one parameter and thats registered JSON string. Callback can be a closure or anything else that will pass as callable.

```php
$json = <<<EOT
    {
        "foo" : "bar",
        "small" : "123456",
        "large" : 200000000000009093302,
        "text" : "Example ratio 1000000000000000:1",
        "date" : "/Date(1425556377427+0100)/"
    }
EOT;

$s = Serializers\Decode::json($json);

print_r($s->toObject());

// transform said json to xml and output it

print Serializers\Decode::json($json)->toXml('root')->withHeaders();

// events

$json = Serializers\Decode::json($json)->on(Serializers\Events::JSON_MSDATE_MATCH, function($date) {
    // matches returned from preg_replace_callback
    list(, $timestamp,,) = $date;

    return date('Y-m-d H:i:s', $timestamp);
});
```

### JSON Serialization
It is possible to register JSON_SERIALIZE event that works exactly like PHP 5.4 `JsonSerializable` interface and thus allows modifying the object before it is converted to JSON.

JSON Serializer also includes a method for creating dates in Microsoft JSON date format, e.g `/Date(1425556377427+0100)/`

```php
$json = Serializers\Encode::toJson(array(
    'foo' => 'bar',
    'foodate' => date('d.m.Y H:i:s')
))->onSerialize(function($data) {
    $data['foodate'] = Serializers\Encoders\Json::toMSDate($data['foodate']);

    return $data;
});

print $json->withHeaders();
```

### JSONP Serialization
Class for easy JSONP serialization, behaves like JSON serializer with additional checks for callback function name validation, which can be changed with custom event

```php
$jsonp = Serializers\Encode::toJsonp('_foo.bar', array(
    'foo' => 'bar',
    'bar' => 'foo'
));

$jsonp->allowCors('*', array('GET', 'POST'));

print $jsonp->withHeaders();
```

### JSONP Deserialization
Class for easy JSONP deserialization, behaves like JSON deserializer

```php
$json = '_foo.bar({"foo":"bar","bar":"foo"})';

$data = Serializers\Decode::jsonp($json);

print_r($data->toObject());

// transform said json to xml with callback name as root element and output it

print Serializers\Decode::jsonp($json)->toXml()->withHeaders();
```

### YAML Serializer
Uses Symfony's YAML component under the hood

```php
$yaml = \Serializers\Encode::toYaml($array);

print_r($yaml->load());

// or

$yaml->toFile('config.yml');
```

### YAML Deserializer
Uses Symfony's YAML component under the hood.

Transformation to `XML`, `JSON`, etc. is possible, but is subjected to the possibilities of the YAML converter.

```php
$yaml = Serializers\Decode::yaml(file_get_contents('config.yml'));

print_r($yaml->toObject());

// transform said json to xml and output it

print Serializers\Decode::yaml(file_get_contents('config.yml'))->toXml('yaml')->withHeaders();
```

### INI Deserializer
Uses INI parser by @austinhyde

```php
$array = array(
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

$encode = Serializers\Encode::toIni($array);
$encode->toFile('config.ini');
```

### INI Serializer
The functionality is limited to basic INI formats, e.g. no support for inheritance. As I can't see a good use case at the moment, this class is here only for keeping a complete stack of encoders/decoders together

```php
$ini = Serializers\Encode::toIni($array);

print_r($ini->load());
```