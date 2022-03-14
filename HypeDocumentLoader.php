<?php

/**
* Hype Document Loader for PHP v1.0.5
* Modified to render and read JavaScript objects from generated script by Tumult Hype 4
*
* @author	 Max Ziebell <mail@maxziebell.de>
* based on CJSON and refactored/tweaked to Hype Document Loader (see below)
*
*/

/*
Version history:
1.0.0 Initial release under existing CJSON license
1.0.1 fixes on indices and rendering return values
1.0.2 added loaded in constructor, add fetch_generated_script
1.0.3 Refactored to preg_split over match, additional nameValue rules
1.0.4 added injection option, added insert_into_document_loader
1.0.5 added get_document_name and insert_into_generated_script
1.0.6 fixed newline introduced in 4.18 (738)
*/


/**
* LICENSE: Redistribution and use in source and binary forms, with or
* without modification, are permitted provided that the following
* conditions are met: Redistributions of source code must retain the
* above copyright notice, this list of conditions and the following
* disclaimer. Redistributions in binary form must reproduce the above
* copyright notice, this list of conditions and the following disclaimer
* in the documentation and/or other materials provided with the
* distribution.
*
* THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
* WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
* MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
* NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
* INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
* BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
* OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
* ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
* TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
* USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
* DAMAGE.
*
* CJSON converts PHP data to and from JSON format.
*
* @author	  Michal Migurski <mike-json@teczno.com>
* @author	  Matt Knapp <mdknapp[at]gmail[dot]com>
* @author	  Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
* @copyright  2005 Michal Migurski
* @license	 http://www.opensource.org/licenses/bsd-license.php
*/

class HypeDocumentLoader
{

	private $hype_generated_script;
	public $document_name;
	public $hype_generated_script_parts=[];
	private $loader_param_array=[];
	private $code_parts_for_document_loader=[];
	private $code_parts_for_generated_script=[];

	private $loader_property_map = [
		/*  0 */	'documentName',
		/*  1 */	'mainContainerID',
		/*  2 */	'resources',
		/*  3 */	'resourcesFolderName',
		/*  4 */	'headAdditions',
		/*  5 */	'functions',
		/*  6 */	'sceneContainers',
		/*  7 */	'scenes',
		/*  8 */	'persistentSymbolDescendants',
		/*  9 */	'javascriptMapping',
		/* 10 */	'timingFunctions',
		/* 11 */	'loadingScreenFunction',
		/* 12 */	'hasPhysics',
		/* 13 */	'drawSceneBackgrounds',
		/* 14 */	'initialSceneIndex',
		/* 15 */	'useGraphicsAcceleration',
		/* 16 */	'useCSSReset',
		/* 17 */	'useCSSPositioning',
		/* 18 */	'useWebAudioAPI',
		/* 19 */	'useTouchEvents',
	];


	function __construct($hype_generated_script='') {
		if ($hype_generated_script) {
			if (!$this->fetch_generated_script($hype_generated_script)){
				$this->parse_generated_script($hype_generated_script);
			}
		}
	}

	public function fetch_generated_script($filepath){
		if (substr($filepath, -3) === '.js' && is_file($filepath)){
			return $this->parse_generated_script(file_get_contents($filepath));
		}
		return false;
	}

	public function parse_generated_script($hype_generated_script){

		$this->hype_generated_script = $hype_generated_script;
		$parts1 = preg_split('/(\w=new\s+window\[\"HYPE_(\d+)\"\+\w\]\()/i', $hype_generated_script, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		if (count($parts1)!=4) return false;
		$parts2 = preg_split('/(\);\w\[\w\]=\w\.API;document[\w\W]+)/i', $parts1[3], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		if (count($parts2)!=2) return false;

		$this->hype_generated_script_parts = (object) [
			'build'						=>	$parts1[2],
			'loader_begin_string'		=>	$parts1[0],
			'loader_delim_string'		=>	$parts1[1],
			'loader_param_string'		=>	$parts2[0],
			'loader_end_string'			=>	$parts2[1],
		];
		
		// Fix newline introduced in Hype 4.18 (738)
		$parts2[0] = str_replace(array("\r", "\n"), '', $parts2[0]);
		
		$this->loader_param_array = self::decode_toplevel('['.$parts2[0].']', false);

		//get document name
		preg_match ('#\/\/\sHYPE\.documents\[\"([A-Za-z0-9-_]+)\"\]#', $this->hype_generated_script_parts->loader_begin_string, $match);
		if (count($match)==2) $this->document_name = $match[1];
		
		// alternative if the document name is missing as comment
		if (empty($this->document_name)) {
			preg_match ('#\.hyperesources\"\,\w="(.+)",#', $this->hype_generated_script_parts->loader_begin_string, $match);
			if (count($match)==2) $this->document_name = $match[1];
		}
		
		return true;
	}

	public function get_hype_generated_script(){
		$generated = '';
		if (isset($this->hype_generated_script_parts)) {
			if (count($this->code_parts_for_generated_script)) $generated .= implode('', $this->code_parts_for_generated_script)."\n";
			$generated .= $this->hype_generated_script_parts->loader_begin_string;
			if (count($this->code_parts_for_document_loader)) $generated .= implode('', $this->code_parts_for_document_loader);
			$generated .= $this->hype_generated_script_parts->loader_delim_string;
			$generated .= self::encode_toplevel($this->loader_param_array);
			$generated .= $this->hype_generated_script_parts->loader_end_string;
		}
		return $generated;
	}
	
	public function get_document_name(){
		$loader_begin_string = $this->hype_generated_script_parts->loader_begin_string;
		preg_match ('\/\/\sHYPE\.documents\[\"([A-Za-z0-9-_]+)\"\]',$match);
		if (count($match)) return $match['0'];
	}

	public function insert_into_document_loader($part){
		$this->code_parts_for_document_loader[] = $part;
	}

	public function insert_into_generated_script($part){
		$this->code_parts_for_generated_script[] = $part;
	}

	/**
	 * This function maps the extracted data from the runtime init (signature array)
	 * back to human readable wording for convience. 
	 *
	 * @return {object} The data from the runtime init as object with descriptive keys
	 */
	public function get_loader_object(){
		$obj = (object) [];
		if ($this->loader_param_array){
			foreach($this->loader_param_array as $index => &$val){
				$obj->{$this->loader_property_map[$index]} = &$val;
			}
		}
		return $obj;
	}

	// Max Ziebell tweak: call but don't encode top level strings
	public static function encode_toplevel($arr){
		$retarr = [];
		foreach($arr as $key => $val){
			$retarr[$key] = self::encode($val, true);
		}
		return join(',', $retarr);
	}

	// Max Ziebell tweak: call decode but treat toplevel different
	public static function decode_toplevel($str, $useArray){
		return self::decode($str, $useArray, true);
	}

	/**
	 * Marker constant for JSON::decode(), used to flag stack state
	 */
	const JSON_SLICE = 1;

	/**
	* Marker constant for JSON::decode(), used to flag stack state
	*/
	const JSON_IN_STR = 2;

	/**
	* Marker constant for JSON::decode(), used to flag stack state
	*/
	const JSON_IN_ARR = 4;

	/**
	* Marker constant for JSON::decode(), used to flag stack state
	*/
	const JSON_IN_OBJ = 8;

	/**
	* Marker constant for JSON::decode(), used to flag stack state
	*/
	const JSON_IN_CMT = 16;

	/**
	 * Encodes an arbitrary variable into JSON format
	 *
	 * @param mixed $var any number, boolean, string, array, or object to be encoded.
	 * If var is a string, it will be converted to UTF-8 format first before being encoded.
	 * @return string JSON string representation of input var
	 */
	public static function encode($var, $topLevel=false)
	{
		switch (gettype($var)) {
			case 'boolean':
				return $var ? 'true' : 'false';

			case 'NULL':
				return 'null';

			case 'integer':
				return (int) $var;

			case 'double':
			case 'float':
				return str_replace(',','.',(float)$var); // locale-independent representation

			case 'string':
				//if (($enc=strtoupper(Yii::app()->charset))!=='UTF-8')
				//	$var=iconv($enc, 'UTF-8', $var);

				//if(function_exists('json_encode'))
				//	return json_encode($var);

				// STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT
				$ascii = '';
				$strlen_var = strlen($var);

			   /*
				* Iterate over every character in the string,
				* escaping with a slash or encoding to UTF-8 where necessary
				*/
				for ($c = 0; $c < $strlen_var; ++$c) {

					$ord_var_c = ord($var{$c});

					switch (true) {
						case $ord_var_c == 0x08:
							$ascii .= '\b';
							break;
						case $ord_var_c == 0x09:
							$ascii .= '\t';
							break;
						case $ord_var_c == 0x0A:
							$ascii .= '\n';
							break;
						case $ord_var_c == 0x0C:
							$ascii .= '\f';
							break;
						case $ord_var_c == 0x0D:
							$ascii .= '\r';
							break;

						case $ord_var_c == 0x22:
						case $ord_var_c == 0x2F:
						case $ord_var_c == 0x5C:
							// double quote, slash, slosh
							$ascii .= '\\'.$var{$c};
							break;

						case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
							// characters U-00000000 - U-0000007F (same as ASCII)
							$ascii .= $var{$c};
							break;

						case (($ord_var_c & 0xE0) == 0xC0):
							// characters U-00000080 - U-000007FF, mask 110XXXXX
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c, ord($var{$c+1}));
							$c+=1;
							$utf16 =  self::utf8ToUTF16BE($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xF0) == 0xE0):
							// characters U-00000800 - U-0000FFFF, mask 1110XXXX
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c,
										 ord($var{$c+1}),
										 ord($var{$c+2}));
							$c+=2;
							$utf16 = self::utf8ToUTF16BE($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xF8) == 0xF0):
							// characters U-00010000 - U-001FFFFF, mask 11110XXX
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c,
										 ord($var{$c+1}),
										 ord($var{$c+2}),
										 ord($var{$c+3}));
							$c+=3;
							$utf16 = self::utf8ToUTF16BE($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xFC) == 0xF8):
							// characters U-00200000 - U-03FFFFFF, mask 111110XX
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c,
										 ord($var{$c+1}),
										 ord($var{$c+2}),
										 ord($var{$c+3}),
										 ord($var{$c+4}));
							$c+=4;
							$utf16 = self::utf8ToUTF16BE($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xFE) == 0xFC):
							// characters U-04000000 - U-7FFFFFFF, mask 1111110X
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c,
										 ord($var{$c+1}),
										 ord($var{$c+2}),
										 ord($var{$c+3}),
										 ord($var{$c+4}),
										 ord($var{$c+5}));
							$c+=5;
							$utf16 = self::utf8ToUTF16BE($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;
					}
				}


				if ($topLevel) {
					return $ascii;	
				} else {
					return '"'.$ascii.'"';
				}
				

			case 'array':
			   /*
				* As per JSON spec if any array key is not an integer
				* we must treat the the whole array as an object. We
				* also try to catch a sparsely populated associative
				* array with numeric keys here because some JS engines
				* will create an array with empty indexes up to
				* max_index which can cause memory issues and because
				* the keys, which may be relevant, will be remapped
				* otherwise.
				*
				* As per the ECMA and JSON specification an object may
				* have any string as a property. Unfortunately due to
				* a hole in the ECMA specification if the key is a
				* ECMA reserved word or starts with a digit the
				* parameter is only accessible using ECMAScript's
				* bracket notation.
				*/

				// treat as a JSON object
				if (is_array($var) && count($var) && (array_keys($var) !== range(0, sizeof($var) - 1))) {
					return '{' .
						   join(',', array_map(array('HypeDocumentLoader', 'nameValue'),
											   array_keys($var),
											   array_values($var)))
						   . '}';
				}

				// treat it like a regular array
				return '[' . join(',', array_map(array('HypeDocumentLoader', 'encode'), $var)) . ']';

			case 'object':
				if ($var instanceof Traversable)
				{
					$vars = array();
					foreach ($var as $k=>$v)
						$vars[$k] = $v;
				}
				else
					$vars = get_object_vars($var);
				return '{' .
					   join(',', array_map(array('HypeDocumentLoader', 'nameValue'),
										   array_keys($vars),
										   array_values($vars)))
					   . '}';

			default:
				return '';
		}
	}

	/**
	 * array-walking function for use in generating JSON-formatted name-value pairs
	 *
	 * @param string $name  name of key to use
	 * @param mixed $value reference to an array element to be encoded
	 *
	 * @return   string  JSON-formatted name-value pair, like '"name":value'
	 * @access   private
	 */
	protected static function nameValue($name, $value)
	{
		// Max Ziebell tweak: return as JavaScript notation and only enclose numbers and -_ with quotations
		//if (is_string($value) && (strpos($value, '$(') !== 0 || strpos($value, '_[') !== false)) {
		if (is_string($value) && (preg_match( '/^\$\(.+\)$/', $value )  || preg_match( '/^_\[.+\]$/', $value ) )) {
			$val = $value;
		} else {
			$val = self::encode($value);
		}

		// set name into quotes if it starts with a number contains spaces or dots
		if(preg_match( '/^[0-9-_]/', $name ) || strpos($name, ' ') !== false || strpos($name, '.') !== false){
			return self::encode(strval($name)) . ':' . $val;
		} else {
			return strval($name) . ':' .  $val;
		}
	}

	/**
	 * reduce a string by removing leading and trailing comments and whitespace
	 *
	 * @param string $str string value to strip of comments and whitespace
	 *
	 * @return string string value stripped of comments and whitespace
	 * @access   private
	 */
	protected static function reduceString($str)
	{
		$str = preg_replace(array(

				// eliminate single line comments in '// ...' form
				'#^\s*//(.+)$#m',

				// eliminate multi-line comments in '/* ... */' form, at start of string
				'#^\s*/\*(.+)\*/#Us',

				// eliminate multi-line comments in '/* ... */' form, at end of string
				'#/\*(.+)\*/\s*$#Us'

			), '', $str);

		// eliminate extraneous space
		return trim($str);
	}

	/**
	 * decodes a JSON string into appropriate variable
	 *
	 * @param string $str  JSON-formatted string
	 * @param boolean $useArray  whether to use associative array to represent object data
	 * @return mixed   number, boolean, string, array, or object corresponding to given JSON input string.
	 *    Note that decode() always returns strings in ASCII or UTF-8 format!
	 * @access   public
	 */
	public static function decode($str, $useArray=true, $topLevel=false)
	{

		$str = self::reduceString($str);

		switch (strtolower($str)) {
			case 'true':
				return true;

			case 'false':
				return false;

			case 'null':
				return null;

			default:
				if (is_numeric($str)) {
					// Lookie-loo, it's a number

					// This would work on its own, but I'm trying to be
					// good about returning integers where appropriate:
					// return (float)$str;

					// Return float or int, as appropriate
					return ((float)$str == (integer)$str)
						? (integer)$str
						: (float)$str;

				} elseif (preg_match('/^("|\').+(\1)$/s', $str, $m) && $m[1] == $m[2]) {
					// STRINGS RETURNED IN UTF-8 FORMAT
					$delim = substr($str, 0, 1);
					$chrs = substr($str, 1, -1);
					$utf8 = '';
					$strlen_chrs = strlen($chrs);

					for ($c = 0; $c < $strlen_chrs; ++$c) {

						$substr_chrs_c_2 = substr($chrs, $c, 2);
						$ord_chrs_c = ord($chrs{$c});

						switch (true) {
							case $substr_chrs_c_2 == '\b':
								$utf8 .= chr(0x08);
								++$c;
								break;
							case $substr_chrs_c_2 == '\t':
								$utf8 .= chr(0x09);
								++$c;
								break;
							case $substr_chrs_c_2 == '\n':
								$utf8 .= chr(0x0A);
								++$c;
								break;
							case $substr_chrs_c_2 == '\f':
								$utf8 .= chr(0x0C);
								++$c;
								break;
							case $substr_chrs_c_2 == '\r':
								$utf8 .= chr(0x0D);
								++$c;
								break;

							case $substr_chrs_c_2 == '\\"':
							case $substr_chrs_c_2 == '\\\'':
							case $substr_chrs_c_2 == '\\\\':
							case $substr_chrs_c_2 == '\\/':
								if (($delim == '"' && $substr_chrs_c_2 != '\\\'') ||
								   ($delim == "'" && $substr_chrs_c_2 != '\\"')) {
									$utf8 .= $chrs{++$c};
								}
								break;

							case preg_match('/\\\u[0-9A-F]{4}/i', substr($chrs, $c, 6)):
								// single, escaped unicode character
								$utf16 = chr(hexdec(substr($chrs, ($c+2), 2)))
									   . chr(hexdec(substr($chrs, ($c+4), 2)));
								$utf8 .= self::utf16beToUTF8($utf16);
								$c+=5;
								break;

							case ($ord_chrs_c >= 0x20) && ($ord_chrs_c <= 0x7F):
								$utf8 .= $chrs{$c};
								break;

							case ($ord_chrs_c & 0xE0) == 0xC0:
								// characters U-00000080 - U-000007FF, mask 110XXXXX
								//see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 2);
								++$c;
								break;

							case ($ord_chrs_c & 0xF0) == 0xE0:
								// characters U-00000800 - U-0000FFFF, mask 1110XXXX
								// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 3);
								$c += 2;
								break;

							case ($ord_chrs_c & 0xF8) == 0xF0:
								// characters U-00010000 - U-001FFFFF, mask 11110XXX
								// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 4);
								$c += 3;
								break;

							case ($ord_chrs_c & 0xFC) == 0xF8:
								// characters U-00200000 - U-03FFFFFF, mask 111110XX
								// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 5);
								$c += 4;
								break;

							case ($ord_chrs_c & 0xFE) == 0xFC:
								// characters U-04000000 - U-7FFFFFFF, mask 1111110X
								// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 6);
								$c += 5;
								break;

						}

					}

					return $utf8;

				} elseif (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
					// array, or object notation

					if ($str{0} == '[') {
						$stk = array(self::JSON_IN_ARR);
						$arr = array();

					} else {
						if ($useArray) {
							$stk = array(self::JSON_IN_OBJ);
							$obj = array();
						} else {
							$stk = array(self::JSON_IN_OBJ);
							$obj = new stdClass();
						}
					}

					$stk[] = array('what' => self::JSON_SLICE, 'where' => 0, 'delim' => false);

					$chrs = substr($str, 1, -1);
					$chrs = self::reduceString($chrs);

					if ($chrs == '') {
						if (reset($stk) == self::JSON_IN_ARR) {
							return $arr;

						} else {
							return $obj;

						}
					}

					//print("\nparsing {$chrs}\n");

					$strlen_chrs = strlen($chrs);

					for ($c = 0; $c <= $strlen_chrs; ++$c) {

						$top = end($stk);
						$substr_chrs_c_2 = substr($chrs, $c, 2);

						if (($c == $strlen_chrs) || (($chrs{$c} == ',') && ($top['what'] == self::JSON_SLICE))) {
							// found a comma that is not inside a string, array, etc.,
							// OR we've reached the end of the character list
							$slice = substr($chrs, $top['where'], ($c - $top['where']));
							$stk[] = array('what' => self::JSON_SLICE, 'where' => ($c + 1), 'delim' => false);
							//print("Found split at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

							if (reset($stk) == self::JSON_IN_ARR) {
								// we are in an array, so just push an element onto the stack
								
								// Max Ziebell tweak: If we only want the top level values return the strings only
								$decoded = self::decode($slice,$useArray);
								if ($topLevel && (is_scalar($decoded) || !isset($decoded))) {
									$arr[] = $slice;
								} else {
									$arr[] = $decoded;	
								}
								

							} elseif (reset($stk) == self::JSON_IN_OBJ) {
								// we are in an object, so figure
								// out the property name and set an
								// element in an associative array,
								// for now
								if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
									// "name":value pair
									$key = self::decode($parts[1],$useArray);
									$val = self::decode($parts[2],$useArray);

									if ($useArray) {
										$obj[$key] = $val;
									} else {
										$obj->$key = $val;
									}
								} elseif (preg_match('/^\s*(\w+)\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
									// name:value pair, where name is unquoted
									$key = $parts[1];
									$val = self::decode($parts[2],$useArray);

									if ($useArray) {
										$obj[$key] = $val;
									} else {
										$obj->$key = $val;
									}
								}

							}

						} elseif ((($chrs{$c} == '"') || ($chrs{$c} == "'")) && ($top['what'] != self::JSON_IN_STR)) {
							// found a quote, and we are not inside a string
							$stk[] = array('what' => self::JSON_IN_STR, 'where' => $c, 'delim' => $chrs{$c});
							//print("Found start of string at {$c}\n");

						} elseif (($chrs{$c} == $top['delim']) &&
								 ($top['what'] == self::JSON_IN_STR) &&
								 (($chrs{$c - 1} != "\\") ||
								 ($chrs{$c - 1} == "\\" && $chrs{$c - 2} == "\\"))) {
							// found a quote, we're in a string, and it's not escaped
							array_pop($stk);
							//print("Found end of string at {$c}: ".substr($chrs, $top['where'], (1 + 1 + $c - $top['where']))."\n");

						} elseif (($chrs{$c} == '[') &&
								 in_array($top['what'], array(self::JSON_SLICE, self::JSON_IN_ARR, self::JSON_IN_OBJ))) {
							// found a left-bracket, and we are in an array, object, or slice
							$stk[] = array('what' => self::JSON_IN_ARR, 'where' => $c, 'delim' => false);
							//print("Found start of array at {$c}\n");

						} elseif (($chrs{$c} == ']') && ($top['what'] == self::JSON_IN_ARR)) {
							// found a right-bracket, and we're in an array
							array_pop($stk);
							//print("Found end of array at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

						} elseif (($chrs{$c} == '{') &&
								 in_array($top['what'], array(self::JSON_SLICE, self::JSON_IN_ARR, self::JSON_IN_OBJ))) {
							// found a left-brace, and we are in an array, object, or slice
							$stk[] = array('what' => self::JSON_IN_OBJ, 'where' => $c, 'delim' => false);
							//print("Found start of object at {$c}\n");

						} elseif (($chrs{$c} == '}') && ($top['what'] == self::JSON_IN_OBJ)) {
							// found a right-brace, and we're in an object
							array_pop($stk);
							//print("Found end of object at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

						} elseif (($substr_chrs_c_2 == '/*') &&
								 in_array($top['what'], array(self::JSON_SLICE, self::JSON_IN_ARR, self::JSON_IN_OBJ))) {
							// found a comment start, and we are in an array, object, or slice
							$stk[] = array('what' => self::JSON_IN_CMT, 'where' => $c, 'delim' => false);
							$c++;
							//print("Found start of comment at {$c}\n");

						} elseif (($substr_chrs_c_2 == '*/') && ($top['what'] == self::JSON_IN_CMT)) {
							// found a comment end, and we're in one now
							array_pop($stk);
							$c++;

							for ($i = $top['where']; $i <= $c; ++$i)
								$chrs = substr_replace($chrs, ' ', $i, 1);

							//print("Found end of comment at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

						}

					}

					if (reset($stk) == self::JSON_IN_ARR) {
						return $arr;

					} elseif (reset($stk) == self::JSON_IN_OBJ) {
						return $obj;

					}

				}
		}
	}

	/**
	 * This function returns any UTF-8 encoded text as a list of
	 * Unicode values:
	 * @param string $str string to convert
	 * @return string
	 * @author Scott Michael Reynen <scott@randomchaos.com>
	 * @link   http://www.randomchaos.com/document.php?source=php_and_unicode
	 * @see	unicodeToUTF8()
	 */
	protected static function utf8ToUnicode( &$str )
	{
		$unicode = array();
		$values = array();
		$lookingFor = 1;

		for ($i = 0; $i < strlen( $str ); $i++ )
		{
			$thisValue = ord( $str[ $i ] );
			if ( $thisValue < 128 )
				$unicode[] = $thisValue;
			else
			{
				if ( count( $values ) == 0 )
					$lookingFor = ( $thisValue < 224 ) ? 2 : 3;
				$values[] = $thisValue;
				if ( count( $values ) == $lookingFor )
				{
					$number = ( $lookingFor == 3 ) ?
						( ( $values[0] % 16 ) * 4096 ) + ( ( $values[1] % 64 ) * 64 ) + ( $values[2] % 64 ):
						( ( $values[0] % 32 ) * 64 ) + ( $values[1] % 64 );
					$unicode[] = $number;
					$values = array();
					$lookingFor = 1;
				}
			}
		}
		return $unicode;
	}

	/**
	 * This function converts a Unicode array back to its UTF-8 representation
	 * @param string $str string to convert
	 * @return string
	 * @author Scott Michael Reynen <scott@randomchaos.com>
	 * @link   http://www.randomchaos.com/document.php?source=php_and_unicode
	 * @see	utf8ToUnicode()
	 */
	protected static function unicodeToUTF8( &$str )
	{
		$utf8 = '';
		foreach( $str as $unicode )
		{
			if ( $unicode < 128 )
			{
				$utf8.= chr( $unicode );
			}
			elseif ( $unicode < 2048 )
			{
				$utf8.= chr( 192 +  ( ( $unicode - ( $unicode % 64 ) ) / 64 ) );
				$utf8.= chr( 128 + ( $unicode % 64 ) );
			}
			else
			{
				$utf8.= chr( 224 + ( ( $unicode - ( $unicode % 4096 ) ) / 4096 ) );
				$utf8.= chr( 128 + ( ( ( $unicode % 4096 ) - ( $unicode % 64 ) ) / 64 ) );
				$utf8.= chr( 128 + ( $unicode % 64 ) );
			}
		}
		return $utf8;
	}

	/**
	 * UTF-8 to UTF-16BE conversion.
	 *
	 * Maybe really UCS-2 without mb_string due to utf8ToUnicode limits
	 * @param string $str string to convert
	 * @param boolean $bom whether to output BOM header
	 * @return string
	 */
	protected static function utf8ToUTF16BE(&$str, $bom = false)
	{
		$out = $bom ? "\xFE\xFF" : '';
		if(function_exists('mb_convert_encoding'))
			return $out.mb_convert_encoding($str,'UTF-16BE','UTF-8');

		$uni = self::utf8ToUnicode($str);
		foreach($uni as $cp)
			$out .= pack('n',$cp);
		return $out;
	}

	/**
	 * UTF-8 to UTF-16BE conversion.
	 *
	 * Maybe really UCS-2 without mb_string due to utf8ToUnicode limits
	 * @param string $str string to convert
	 * @return string
	 */
	protected static function utf16beToUTF8(&$str)
	{
		$uni = unpack('n*',$str);
		return self::unicodeToUTF8($uni);
	}
}
