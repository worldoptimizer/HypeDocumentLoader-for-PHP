# HypeDocumentLoader for PHP

Parse Hype generated script files using PHP (*works with Tumult Hype 4.x*). To understand what the shorthand notation in Hype stands for please check:
https://github.com/worldoptimizer/HypeCookBook/wiki/Hype-generated-Shorthands




## Examples

Load the generated script from a string

```php
require_once ("HypeDocumentLoader.php");

$hype_generated_script = file_get_contents('test.hyperesources/test_hype_generated_script.js');

$loader = new HypeDocumentLoader($hype_generated_script);

$data = $loader->get_loader_object();

print_r($data);

```

or using the file directly in the constructor (since v1.0.2)

```php
require_once ("HypeDocumentLoader.php");

$loader = new HypeDocumentLoader('test.hyperesources/test_hype_generated_script.js');

$data = $loader->get_loader_object();

print_r($data);

```

---

This snippet does a quick compression on symbols (50%+ file size reduction)
```php

require_once ("HypeDocumentLoader.php");
$loader = new HypeDocumentLoader('XYZ.hyperesources/XYZ_hype_generated_script.js');
$data = $loader->get_loader_object();

$sym = [];
$sym_encoded = [];
// loop over scenes
for ($i = 0; $i < count($data->scenes); $i++) {
	// loop over objects (ids)
	for ($j = 0; $j < count($data->scenes[$i]->O); $j++) {
		// lookup id
		$id = $data->scenes[$i]->O[$j];
		//get actual object and encode it to string
		$bF = $data->scenes[$i]->v->{$id}->bF;
		if ($bF) $bF = ','.$bF;
		unset($data->scenes[$i]->v->{$id}->bF);
		$encoded = $loader->encode($data->scenes[$i]->v->{$id}, true);
		//check if present in lookup
		$fid = array_search($encoded, $sym_encoded);
		if(!$fid) {
			//new and create
			$sym[$id] = $data->scenes[$i]->v->{$id};
			$sym_encoded[$id] = $encoded;
			$fid = $id;
		}
		//reference	
		$data->scenes[$i]->v->{$id} = 'cl('.$fid.$bF.')';
	}
}

$lookup = 'window.sym = '.$loader->encode($sym, true).';';
$lookup .= 'function cl(c,a){var b=JSON.parse(JSON.stringify(sym[c]));a&&(b.bF=a);return b}';
// echo compressed file
echo $lookup."\n".$loader->get_hype_generated_script();

```

---

This snippet deletes scenes (beaware that deleteing scenes with the first occurance of a persistent symbol deletes it from the entire document!)

```php

require_once ("HypeDocumentLoader.php");
$loader = new HypeDocumentLoader('XYZ.hyperesources/XYZ_hype_generated_script.js');
$data = $loader->get_loader_object();

// $idx is the scene index to delete
$idx = 1;

// determin layouts to delete
$layoutsToDelete = $data->sceneContainers[$idx]->X;
print_r(array_keys($data->scenes));

// unset layouts
foreach ($layoutsToDelete as $j) unset($data->scenes[$j]);
print_r(array_keys($data->scenes));

// reindex layouts
$data->scenes = array_values($data->scenes);
print_r(array_keys($data->scenes));

// reduce index of layouts higher by count of deleted
for ($i = 0; $i < count($data->scenes); $i++) $data->scenes[$i]->_ = $i;

// unset and reindex sceneContainer
unset($data->sceneContainers[$idx]);
$data->sceneContainers = array_values($data->sceneContainers);

// walk over them and fix layout indexes
for ($i = $idx; $i < count($data->sceneContainers); $i++) {
	for ($j = 0; $j < count($data->sceneContainers[$i]->X); $j++) {
		if($data->sceneContainers[$i]->X[$j]>=$layoutsToDelete[0]) 
			$data->sceneContainers[$i]->X[$j] -= count($layoutsToDelete);
	}
}

// echo file
echo $loader->get_hype_generated_script();

```

