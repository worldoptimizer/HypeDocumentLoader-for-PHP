# HypeDocumentLoader for PHP

Parse Hype generated script files using PHP (*works with Tumult Hype 4.x*). To understand what the shorthand notation in Hype stands for please check:
https://github.com/worldoptimizer/HypeCookBook/wiki/Hype-generated-Shorthands




## Examples

Load the generated script from a string

```php
require_once ("HypeDocumentLoader.php");

$hype_generated_script = file_get_contents('YOURFILE.hyperesources/YOURFILE_hype_generated_script.js');

$loader = new HypeDocumentLoader($hype_generated_script);

$data = $loader->get_loader_object();

print_r($data);

```

or using the file directly in the constructor (since v1.0.2)

```php
require_once ("HypeDocumentLoader.php");

$loader = new HypeDocumentLoader('YOURFILE.hyperesources/YOURFILE_hype_generated_script.js');

$data = $loader->get_loader_object();

print_r($data);

```

---

This snippet does a quick compression on symbols (50%+ file size reduction)
```php

require_once ("HypeDocumentLoader.php");
$loader = new HypeDocumentLoader('YOURFILE.hyperesources/YOURFILE_hype_generated_script.js');
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
		$encoded = $loader->encode($data->scenes[$i]->v->{$id});
		//check if present in lookup
		$fid = array_search($encoded, $sym_encoded);
		if(!$fid) {
			//new and create
			$sym[] = $data->scenes[$i]->v->{$id};
			$sym_encoded[] = $encoded;
			$fid = count($sym)-1;
		}
		//reference	
		$data->scenes[$i]->v->{$id} = '$('.$fid.$bF.')';
	}
}

$loader->inject_code_before_init('var sym='.$loader->encode($sym).';');
$loader->inject_code_before_init('function $(c,a){var b=JSON.parse(JSON.stringify(sym[c]));a&&(b.bF=a);return b}');
// echo compressed file
echo $loader->get_hype_generated_script();

```

---

This snippet deletes scenes and all associated layouts given an scene index (be aware that deleteing scenes with the first occurance of a persistent symbol deletes it from the entire document!)

```php

require_once ("HypeDocumentLoader.php");
$loader = new HypeDocumentLoader('YOURFILE.hyperesources/YOURFILE_hype_generated_script.js');
$data = $loader->get_loader_object();

// $idx is the scene index to delete in this example 1 (scene 2)... 0 would be the first secene
$idx = 1;

// determin layouts to delete
$layoutsToDelete = $data->sceneContainers[$idx]->X;

// unset layouts
foreach ($layoutsToDelete as $j) unset($data->scenes[$j]);

// reindex layouts
$data->scenes = array_values($data->scenes);

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

---
This snippet does is an extended compression with an additional string lookup. The gained yield of around 10% requires many more steps than the much simpler snippet above and probably isn't necessary for most as the simpler version is much easier to maintain. But I am still posting it here as we currently use the concepts found in the code below.

String lookup works as follows:
* count all occurances of a string
* push strings long and plenty enough into a lookup
* sort the lookup by count to get smaller lookup ids on items referenced more often
* apply the lookup to the data


```php

require_once ("HypeDocumentLoader.php");
$loader = new HypeDocumentLoader('YOURFILE.hyperesources/YOURFILE_hype_generated_script.js');
$data = $loader->get_loader_object();

$o = [];
$o_count = [];
$sym = [];
$sym_encoded = [];


$iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($data));
foreach($iterator as $key => $value) {
	if (preg_match('/^[0-9"]+$/',$value)){
		$iterator->getInnerIterator()->offsetSet($key, (int) $value);
		continue;
	}
	if(is_string($value)&&strlen($value)>3) $o_count[$value] +=1;
	if(is_string($key)&&strlen($key)>5) $o_count[$key] +=1;
}

$iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($data));
foreach($iterator as $key => $value) {
	if(is_string($value) && strlen($value)>3 && $o_count[$value] && $o_count[$value]>1){
		$fid = array_search($value, $o);
		if(!$fid) $o[] = $value;
	}
	if(is_string($key) && strlen($key)>5 && $o_count[$key] && $o_count[$key]>1){
		$fid = array_search($key, $o);
		if(!$fid) $o[] = $key;
	}
}

function sort_based_on_count($a, $b) {
	global $o_count;
	$aa = $o_count[$a];
	$bb = $o_count[$b];
	if ($aa == $bb) {
		return (strlen($a) < strlen($b)) ? -1 : 1;
	}
	return ($aa > $bb) ? -1 : 1;
}

usort($o, "sort_based_on_count");

$iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($data));
foreach($iterator as $key => $value) {
	if(is_string($value) && strlen($value)>3 && $o_count[$value] && $o_count[$value]>1){
		$fid = array_search($value, $o);
		$iterator->getInnerIterator()->offsetSet($key, '_['.$fid.']');
	}
	if(is_string($key) && strlen($key)>5 && $o_count[$key] && $o_count[$key]>1){
		$fid = array_search($key, $o);
		$iterator->getInnerIterator()->offsetUnset($key);
		$iterator->getInnerIterator()->offsetSet('[_['.$fid.']]', $value);
	}
}

// below here is nearly the same code as in the simple symbol compression above. Only contains some minor tweaks.
for ($i = 0; $i < count($data->scenes); $i++) {
	for ($j = 0; $j < count($data->scenes[$i]->O); $j++) {
		$id = $data->scenes[$i]->O[$j];
		$assign = (object)[];
		$bF = $data->scenes[$i]->v->{$id}->bF;
		if ($bF) $assign->bF = $bF;
		$cV = $data->scenes[$i]->v->{$id}->cV;
		if ($cV) $assign->cV = $cV;
		if (count((array)$assign)){
			if (count((array)$assign)==1 && isset($assign->bF)){
				$assign = $assign->bF;
			} else {
				$assign = $loader->encode($assign);
			}	
			$assign = ','.$assign;	
		} else {
			$assign = '';
		}
		unset($data->scenes[$i]->v->{$id}->bF);
		unset($data->scenes[$i]->v->{$id}->cV);
		$sort = new ArrayObject($data->scenes[$i]->v->{$id});
		$sort->ksort();
		$encoded = $loader->encode($data->scenes[$i]->v->{$id});
		$fid = array_search($encoded, $sym_encoded);
		if(!$fid) {
			$sym[] = $data->scenes[$i]->v->{$id};
			$sym_encoded[] = $encoded;
			$fid = count($sym)-1;
		}
		$data->scenes[$i]->v->{$id} = '$('.$fid.$assign.')';
	}
}

$loader->inject_code_before_init('var _='.$loader->encode($o).';');
$loader->inject_code_before_init('var sym='.$loader->encode($sym).';');
$loader->inject_code_before_init('function $(c,a){var b=JSON.parse(JSON.stringify(sym[c]));if(a&&!(a instanceof Object))a={bF:a};Object.assign(b,a);return b}');

echo $loader->get_hype_generated_script();

```
