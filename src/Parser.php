<?php

namespace CSSSelectorParser;

use \Exception;

function __isIdentStart ($c) {
	return ctype_alpha($c);
}

function __isIdent ($c) {
	return ctype_alnum($c) || $c == '-' || $c == '_';
}

function __isHex ($c) {
	return ctype_xdigit($c);
}

function __isAttrMatchOperator ($c) {
	return $c == '=' || $c == '^' || $c == '$' || $c == '*' || $c == '~';
}

if (!function_exists('mb_chr')) {
    function mb_chr($ord, $encoding = 'UTF-8') {
        if ($encoding === 'UCS-4BE') {
            return pack("N", $ord);
        }
        else {
            return mb_convert_encoding(mb_chr($ord, 'UCS-4BE'), $encoding, 'UCS-4BE');
        }
    }
}

if (!function_exists('mb_ord')) {
    function mb_ord($char, $encoding = 'UTF-8') {
        if ($encoding === 'UCS-4BE') {
            list(, $ord) = (strlen($char) === 4) ? @unpack('N', $char) : @unpack('n', $char);
            return $ord;
        }
        else {
            return mb_ord(mb_convert_encoding($char, 'UCS-4BE', $encoding), 'UCS-4BE');
        }
    }
}

class Context {
	protected $str;
	protected $l = 0;
	protected $pos = 0;
	protected $ch;

	protected $pseudos = array();
	protected $attrEqualityMods = array();
	protected $ruleNestingOperators = array();
	protected $substitutesEnabled = false;
	protected $identSpecialChars = array();
	protected $identReplacements = array();
	protected $identReplacementsRev = array();
	protected $strReplacementsRev = array();
	protected $singleQuoteEscapeChars = array();
	protected $doubleQuotesEscapeChars = array();

	public function __construct ($str, $pos, $conf) {
		$this->str = $str;
		$this->l = strlen($str);
		$this->pos = $pos;

		// parser
		foreach ($conf as $k => $v) {
			$this->$k = $v;
		}

		$this->nextCh(false);
	}

	protected function nextCh ($inc = true) {
		if ($inc) {
			$this->pos++;
		}
		$this->ch = isset($this->str[$this->pos]) ? $this->str[$this->pos] : null;
	}

	protected function getStr ($quote, $escapeTable) {
		$result = '';
		$this->nextCh();
		while ($this->pos < $this->l) {
			if ($this->ch == $quote) {
				$this->pos++;
				return $result;
			}
			elseif ($this->ch == '\\') {
				$this->nextCh();
				if ($this->ch == $quote) {
					$result .= $quote;
				}
				elseif (isset($escapeTable[$this->ch])) {
					$result .= $escapeTable[$this->ch];
				}
				elseif (__isHex($this->ch)) {
					$hex = $this->ch;
					$this->nextCh();
					while (__isHex($this->ch)) {
						$hex .= $this->ch;
						$this->nextCh();
					}
					if ($this->ch == ' ') {
						$this->nextCh();
					}
					$result .= mb_chr(hexdec($hex));
					continue;
				}
				else {
					$result .= $this->ch;
				}
			}
			else {
				$result .= $this->ch;
			}
			$this->nextCh();
		}
		return $result;
	}

	protected function getIdent () {
		$result = '';
		$this->nextCh(false);
		while ($this->pos < $this->l) {
			if (__isIdent($this->ch)) {
				$result .= $this->ch;
			}
			elseif ($this->ch == '\\') {
				$this->nextCh();
				if (isset($this->identSpecialChars[$this->ch])) {
					$result .= $this->ch;
				}
				elseif (isset($this->identReplacements[$this->ch])) {
					$result .= $this->identReplacements[$this->ch];
				}
				elseif (__isHex($this->ch)) {
					$hex = $this->ch;
					$this->nextCh();
					while (__isHex($this->ch)) {
						$hex .= $this->ch;
						$this->nextCh();
					}
					if ($this->ch == ' ') {
						$this->nextCh();
					}
					$result .= mb_chr(hexdec($hex));
					continue;
				}
				else {
					$result .= $this->ch;
				}
			}
			else {
				return $result;
			}
			$this->nextCh();
		}
		return $result;
	}

	protected function skipWhitespace () {
		$this->nextCh(false);
		$result = false;
		while ($this->ch == ' ' || $this->ch == "\t" || $this->ch == "\n" || $this->ch == "\r" || $this->ch == "\f") {
			$result = true;
			$this->nextCh();
		}
		return $result;
	}

	// @parse = ->
	public function parse () {
		$res = $this->parseSelector();
		if ($this->pos < $this->l) {
			throw new Exception('Rule expected but "' . $this->str[$this->pos] . '" found.');
		}
		return $res;
	}

	// @parseSelector = ->
	protected function parseSelector () {
		$selector = $res = $this->parseSingleSelector();
		$this->nextCh(false);
		while ($this->ch == ',') {
			$this->pos++;
			$this->skipWhitespace();
			if ($res['type'] != 'selectors') {
				$res = array(
					'type' => 'selectors',
					'selectors' => array($selector)
				);
			}
			$selector = $this->parseSingleSelector();
			if (!$selector) {
				throw new Exception('Rule expected after ",".');
			}
			$res['selectors'][] = $selector;
		}
		return $res;
	}

	// @parseSingleSelector = ->
	protected function parseSingleSelector () {
		$this->skipWhitespace();
		$selector = array('type' => 'ruleSet');
		$rule = $this->parseRule();
		if (!$rule) {
			return null;
		}

		$currentRule =& $selector;
		while ($rule) {
			$rule['type'] = 'rule';
			$currentRule['rule'] = $rule;
			$currentRule =& $currentRule['rule'];

			$this->skipWhitespace();
			$this->nextCh(false);
			if ($this->pos >= $this->l || $this->ch == ',' || $this->ch == ')') {
				break;
			}
			if (isset($this->ruleNestingOperators[$this->ch])) {
				$op = $this->ch;
				$this->pos++;
				$this->skipWhitespace();
				$rule = $this->parseRule();
				if (!$rule) {
					throw new Exception('Rule expected after "' . $op . '".');
				}
				$rule['nestingOperator'] = $op;
			}
			else {
				$rule = $this->parseRule();
				if ($rule) {
					$rule['nestingOperator'] = null;
				}
			}
		}
		return $selector;
	}

	function parseRule () {
		$rule = null;
		while ($this->pos < $this->l) {
			$this->nextCh(false);
			if ($this->ch == '*') {
				$rule = $rule ?: array();
				$rule['tagName'] = '*';
			}
			elseif (__isIdentStart($this->ch) || $this->ch == '\\') {
				$rule = $rule ?: array();
				$rule['tagName'] = $this->getIdent();
			}
			elseif ($this->ch == '.') {
				$this->pos++;
				$rule = $rule ?: array();
				$rule['classNames'] = @$rule['classNames'] ?: array();
				$rule['classNames'][] = $this->getIdent();
			}
			elseif ($this->ch == '#') {
				$this->nextCh();
				$id = '';
				while (__isIdent($this->ch)) {
					$id .= $this->ch;
					$this->nextCh();
				}
				$rule = $rule ?: array();
				$rule['id'] = $id;
			}
			elseif ($this->ch == '[') {
				$this->pos++;
				$this->skipWhitespace();
				$attr = array('name' => $this->getIdent());
				$this->skipWhitespace();
				if ($this->ch == ']') {
					$this->pos++;
				}
				else {
					$operator = '';
					if (isset($this->attrEqualityMods[$this->ch])) {
						$operator = $this->ch;
						$this->nextCh();
					}
					if ($this->pos >= $this->l) {
						throw new Exception('Expected "=" but end of file reached.');
					}
					if ($this->ch != '=') {
						throw new Exception('Expected "=" but "' . $this->ch . '" found.');
					}
					$attr['operator'] = $operator . '=';
					$this->pos++;
					$this->skipWhitespace();
					$attrValue = '';
					$attr['valueType'] = 'string';
					if ($this->ch == '"') {
						$attrValue = $this->getStr('"', $this->doubleQuotesEscapeChars);
					}
					elseif ($this->ch == '\'') {
						$attrValue = $this->getStr('\'', $this->singleQuoteEscapeChars);
					}
					elseif ($this->substitutesEnabled && $this->ch == '$') {
						$this->pos++;
						$attrValue = $this->getIdent();
						$attr['valueType'] = 'substitute';
					}
					else {
						while ($this->pos < $this->l) {
							if ($this->ch == ']') {
								break;
							}
							$attrValue .= $this->ch;
							$this->nextCh();
						}
						$attrValue = trim($attrValue);
					}
					$this->skipWhitespace();
					if ($this->pos >= $this->l) {
						throw new Exception('Expected "]" but end of file reached.');
					}
					if ($this->ch != ']') {
						throw new Exception('Expected "]" but "' . $this->ch . '" found.');
					}
					$this->pos++;
					$attr['value'] = $attrValue;
				}
				$rule = $rule ?: array();
				$rule['attrs'] = @$rule['attrs'] ?: array();
				$rule['attrs'][] = $attr;
			}
			elseif ($this->ch == ':') {
				$this->pos++;
				$pseudoName = $this->getIdent();
				$pseudo = array('name' => $pseudoName);
				if ($this->ch == '(') {
					$this->pos++;
					$value = '';
					$this->skipWhitespace();
					if (isset($this->pseudos[$pseudoName]) && $this->pseudos[$pseudoName] == 'selector') {
						$pseudo['valueType'] = 'selector';
						$value = $this->parseSelector();
					}
					else {
						$pseudo['valueType'] = 'string';
						if ($this->ch == '"') {
							$value = $this->getStr('"', $this->doubleQuotesEscapeChars);
						}
						elseif ($this->ch == '\'') {
							$value = $this->getStr('\'', $this->singleQuoteEscapeChars);
						}
						elseif ($this->substitutesEnabled && $this->ch == '$') {
							$this->pos++;
							$value = $this->getIdent();
							$pseudo['valueType'] = 'substitute';
						}
						else {
							while ($this->pos < $this->l) {
								if ($this->ch == ')') {
									break;
								}
								$value .= $this->ch;
								$this->nextCh();
							}
							$value = trim($value);
						}
						$this->skipWhitespace();
					}
					if ($this->pos >= $this->l) {
						throw new Exception('Expected ")" but end of file reached.');
					}
					if ($this->ch != ')') {
						throw new Exception('Expected ")" but "' . $this->ch . '" found.');
					}
					$this->pos++;
					$pseudo['value'] = $value;
				}
				$rule = $rule ?: array();
				$rule['pseudos'] = @$rule['pseudos'] ?: array();
				$rule['pseudos'][] = $pseudo;
			}
			else {
				break;
			}
		}
		return $rule;
	}
}

class Parser {
	protected $pseudos = array();
	protected $attrEqualityMods = array();
	protected $ruleNestingOperators = array();
	protected $substitutesEnabled = false;

	private $identSpecialChars = array(
		'!'  => true,
		'"'  => true,
		'#'  => true,
		'$'  => true,
		'%'  => true,
		'&'  => true,
		'\'' => true,
		'('  => true,
		')'  => true,
		'*'  => true,
		'+'  => true,
		','  => true,
		'.'  => true,
		'/'  => true,
		';'  => true,
		'<'  => true,
		'='  => true,
		'>'  => true,
		'?'  => true,
		'@'  => true,
		'['  => true,
		'\\' => true,
		']'  => true,
		'^'  => true,
		'`'  => true,
		'{'  => true,
		'|'  => true,
		'}'  => true,
		'~'  => true,
	);

	private $identReplacements = array(
		"n"  => "\n",
		"r"  => "\r",
		"t"  => "\t",
		" "  => " ",
		"f"  => "\f",
		"v"  => "\v",
	);

	private $identReplacementsRev = array(
		"\n" => "\\n",
		"\r" => "\\r",
		"\t" => "\\t",
		" "  => "\\ ",
		"\f" => "\\f",
		"\v" => "\\v",
	);

	private $strReplacementsRev = array(
		"\n" => "\\n",
		"\r" => "\\r",
		"\t" => "\\t",
		"\f" => "\\f",
		"\v" => "\\v",
	);

	private $singleQuoteEscapeChars = array(
		"n"  => "\n",
		"r"  => "\r",
		"t"  => "\t",
		"f"  => "\f",
		"\\" => "\\",
		"\'" => "\'",
	);

	private $doubleQuotesEscapeChars = array(
		"n"  => "\n",
		"r"  => "\r",
		"t"  => "\t",
		"f"  => "\f",
		"\\" => "\\",
		"\"" => "\"",
	);

	public function __construct () {
	}

	public function registerSelectorPseudos () {
		$arguments = func_get_args();
		foreach ($arguments as $name) {
			$this->pseudos[$name] = 'selector';
		}
		return $this;
	}

	public function unregisterSelectorPseudos () {
		$arguments = func_get_args();
		foreach ($arguments as $name) {
			unset($this->pseudos[$name]);
		}
		return $this;
	}

	public function registerNestingOperators () {
		$arguments = func_get_args();
		foreach ($arguments as $op) {
			$this->ruleNestingOperators[$op] = true;
		}
		return $this;
	}

	public function unregisterNestingOperators () {
		$arguments = func_get_args();
		foreach ($arguments as $op) {
			unset($this->ruleNestingOperators[$op]);
		}
		return $this;
	}

	public function registerAttrEqualityMods () {
		$arguments = func_get_args();
		foreach ($arguments as $mod) {
			$this->attrEqualityMods[$mod] = true;
		}
		return $this;
	}

	public function unregisterAttrEqualityMods () {
		$arguments = func_get_args();
		foreach ($arguments as $mod) {
			unset($this->attrEqualityMods[$mod]);
		}
		return $this;
	}

	public function enableSubstitutes () {
		$this->substitutesEnabled = true;
		return $this;
	}
	public function disableSubstitutes () {
		$this->substitutesEnabled = false;
		return $this;
	}

	public function parse ($str) {
		$conf = array(
			'pseudos'                 => $this->pseudos,
			'attrEqualityMods'        => $this->attrEqualityMods,
			'ruleNestingOperators'    => $this->ruleNestingOperators,
			'substitutesEnabled'      => $this->substitutesEnabled,
			'identSpecialChars'       => $this->identSpecialChars,
			'identReplacements'       => $this->identReplacements,
			'identReplacementsRev'    => $this->identReplacementsRev,
			'strReplacementsRev'      => $this->strReplacementsRev,
			'singleQuoteEscapeChars'  => $this->singleQuoteEscapeChars,
			'doubleQuotesEscapeChars' => $this->doubleQuotesEscapeChars,
		);
		$ctx = new Context($str, 0, $conf);
		return $ctx->parse();
	}

	public function render ($path) {
		$renderEntity = function ($entity) use (&$renderEntity) {
			$res = '';
			switch ($entity['type']) {
				case 'ruleSet':
					$currentEntity = $entity['rule'];
					$parts = array();
					while ($currentEntity) {
						if (isset($currentEntity['nestingOperator'])) {
							$parts[] = $currentEntity['nestingOperator'];
						}
						$parts[] = $renderEntity($currentEntity);
						$currentEntity = isset($currentEntity['rule']) ? $currentEntity['rule'] : null;
					}
					$res = join(' ', $parts);
					break;

				case 'selectors':
					$res = join(', ', array_map($renderEntity, $entity['selectors']));
					break;

				case 'rule':
					if (isset($entity['tagName'])) {
						$res = $this->escapeIdentifier($entity['tagName']);
					}
					if (isset($entity['id'])) {
						$res .= '#' . $this->escapeIdentifier($entity['id']);
					}
					if (isset($entity['classNames'])) {
						$that = $this;
						$res .= join('', array_map(function ($cn) use ($that) {
							return '.' . $that->escapeIdentifier($cn);
						}, $entity['classNames']));
					}
					if (isset($entity['attrs'])) {
						$res .= join('', array_map(function ($attr) {
							if (isset($attr['operator'])) {
								if ($attr['valueType'] == 'substitute') {
									return "[{$this->escapeIdentifier($attr['name'])}{$attr['operator']}\${$attr['value']}]";
								}
								else {
									return "[{$this->escapeIdentifier($attr['name'])}{$attr['operator']}{$this->escapeStr($attr['value'])}]";
								}
							}
							else {
								return "[{$this->escapeIdentifier($attr['name'])}]";
							}
						}, $entity['attrs']));
					}
					if (isset($entity['pseudos'])) {
						$res .= join('', array_map(function ($pseudo) use (&$renderEntity) {
							if (isset($pseudo['valueType'])) {
								if ($pseudo['valueType'] == 'selector') {
									return ":{$this->escapeIdentifier($pseudo['name'])}({$renderEntity($pseudo['value'])})";
								}
								elseif ($pseudo['valueType'] == 'substitute') {
									return ":{$this->escapeIdentifier($pseudo['name'])}(\${$pseudo['value']})";
								}
								else {
									return ":{$this->escapeIdentifier($pseudo['name'])}({$this->escapeStr($pseudo['value'])})";
								}
							}
							else {
								return ":{$this->escapeIdentifier($pseudo['name'])}";
							}
						}, $entity['pseudos']));
					}
					break;
				default:
					throw new Exception('Unknown entity type: "' . $entity['type'] . '".');
			}
			return $res;
		};
		$renderEntity->bindTo($this);

		return $renderEntity($path);
	}

	protected function escapeIdentifier ($s) {
		$result = '';
		$i = 0;
		$l = mb_strlen($s);
		while ($i < $l) {
			$c = mb_substr($s, $i, 1);
			if (isset($this->identSpecialChars[$c])) {
				$result .= '\\' . $c;
			}
			elseif (isset($this->identReplacementsRev[$c])) {
				$result .= $this->identReplacementsRev[$c];
			}
			elseif (($cc = mb_ord($c)) && ($cc < 32 || $cc > 126)) {
				$result .= '\\' . dechex($cc) . ' ';
			}
			else {
				$result .= $c;
			}
			$i++;
		}
		return $result;
	}

	public function escapeStr ($s) {
		$result = '';
		$i = 0;
		$l = mb_strlen($s);
		while ($i < $l) {
			$c = mb_substr($s, $i, 1);
			if ($c == '"') {
				$c = '\\"';
			}
			elseif ($c == '\\') {
				$c = '\\\\';
			}
			elseif (isset($this->strReplacementsRev[$c])) {
				$c = $this->strReplacementsRev[$c];
			}
			$result .= $c;
			$i++;
		}
		return "\"{$result}\"";
	}
}
