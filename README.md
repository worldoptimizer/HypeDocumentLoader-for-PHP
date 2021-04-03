# HypeDocumentLoader for PHP

Parse Hype generated script files using PHP.

*Works with Tumult Hype 4.x*


## Example

```php
require_once ("HypeDocumentLoader.php");

$hype_generated_script = file_get_contents('test.hyperesources/test_hype_generated_script.js');

$loader = new HypeDocumentLoader($hype_generated_script);

$data = $loader->get_loader_object();

print_r($data);

```
