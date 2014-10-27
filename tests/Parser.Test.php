<?php

use CSSSelectorParser\Parser;

class ParserTest extends PHPUnit_Framework_TestCase {

	function setUp () {
		$this->parser = new Parser();
		$this->parser->registerAttrEqualityMods('^', '$', '*', '~');
		$this->parser->registerNestingOperators('>', '+', '~');
	}

	function testClassParsing () {
		$this->assertEquals('.class', $this->parser->render($this->parser->parse('.class')));
		$this->assertEquals('.class1.class2', $this->parser->render($this->parser->parse('.class1.class2')));
	}

	function testTagIdParsing () {
		$this->assertEquals('tag.class', $this->parser->render($this->parser->parse('tag.class')));
		$this->assertEquals('tag#id.class', $this->parser->render($this->parser->parse('tag#id.class')));
	}

	function testAttrParsing () {
		$this->assertEquals('tag#id.class[attr]', $this->parser->render($this->parser->parse('tag#id.class[attr]')));
		$this->assertEquals('tag#id.class[attr]', $this->parser->render($this->parser->parse('tag#id.class[ attr ]')));
		$this->assertEquals('tag#id.class[attr="value"]', $this->parser->render($this->parser->parse('tag#id.class[attr=value]')));
		$this->assertEquals('tag#id.class[attr~="value"]', $this->parser->render($this->parser->parse('tag#id.class[attr~=value]')));
		$this->assertEquals('tag#id.class[attr*="value"]', $this->parser->render($this->parser->parse('tag#id.class[attr*=value]')));
		$this->assertEquals('tag#id.class[attr^="value"]', $this->parser->render($this->parser->parse('tag#id.class[attr^=value]')));
		$this->assertEquals('tag#id.class[attr$="value"]', $this->parser->render($this->parser->parse('tag#id.class[attr$=value]')));
	}

	function testMoreAttrParsing () {
		$this->assertEquals('tagname[x="y"]', $this->parser->render($this->parser->parse('tagname[   x =  y  ]')));
		$this->assertEquals('tagname[x="y"]', $this->parser->render($this->parser->parse('tagname[x="y"]')));
		$this->assertEquals('tagname[x="y"][z]', $this->parser->render($this->parser->parse('tagname[x="y"][z]')));
		$this->assertEquals('tagname[x="y "]', $this->parser->render($this->parser->parse('tagname[x="y "]')));
		$this->assertEquals('tagname[x="y \\""]', $this->parser->render($this->parser->parse('tagname[x="y \\""]')));
		$this->assertEquals('tagname[x="y\'"]', $this->parser->render($this->parser->parse('tagname[x="y\'"]')));
	}

	function testNestedParsing () {
		$this->assertEquals('tag1 tag2', $this->parser->render($this->parser->parse('tag1   tag2')));
		$this->assertEquals('tag1 > tag2', $this->parser->render($this->parser->parse('tag1>tag2')));
		$this->assertEquals('tag1 + tag2', $this->parser->render($this->parser->parse('tag1+tag2')));
		$this->assertEquals('tag1 ~ tag2', $this->parser->render($this->parser->parse('tag1~tag2')));
	}

	function testSimplePseudosParsing () {
		$this->assertEquals('tag1:first', $this->parser->render($this->parser->parse('tag1:first')));
		$this->assertEquals('tag1:lt("3")', $this->parser->render($this->parser->parse('tag1:lt(3)')));
		$this->assertEquals('tag1:lt("3")', $this->parser->render($this->parser->parse('tag1:lt( 3 )')));
		$this->assertEquals('tag1:lt("3")', $this->parser->render($this->parser->parse('tag1:lt(\'3\')')));
		$this->assertEquals('tag1:lt("3")', $this->parser->render($this->parser->parse('tag1:lt("3" )')));

	}

	function testAdvancedPseudosParsing () {
		$this->assertEquals('tag1:has(".class")', $this->parser->render($this->parser->parse('tag1:has(.class)')));

		$this->parser->registerSelectorPseudos('has');
		$this->assertEquals('tag1:has(.class)', $this->parser->render($this->parser->parse('tag1:has(.class)')));
		$this->assertEquals('tag1:has(.class, .class2)',
			$this->parser->render($this->parser->parse('tag1:has(.class,.class2)')));
		$this->assertEquals('tag1:has(.class:has(.subcls), .class2)',
			$this->parser->render($this->parser->parse('tag1:has(.class:has(.subcls),.class2)')));
	}

	function testInvalidPseudo1 () {
		$this->setExpectedException('Exception', 'Expected ")" but end of file reached.');
		$this->parser->parse(':has(.class');
	}

	function testInvalidPseudo2 () {
		$this->setExpectedException('Exception', 'Expected ")" but end of file reached.');
		$this->parser->parse(':has(:has(');
	}

	function testUnregisteringPseudos () {
		$this->parser->unregisterSelectorPseudos('has');
		$this->assertEquals('tag1:has(".class,.class2")',
			$this->parser->render($this->parser->parse('tag1:has(.class,.class2)')));
	}


	function testException1 () {
		$this->setExpectedException('Exception', 'Expected "]" but "!" found.');
		$this->parser->parse('[attr="val"!');
	}
	function testException2 () {
		$this->setExpectedException('Exception', 'Expected "]" but end of file reached.');
		$this->parser->parse('[attr="val"');
	}
	function testException3 () {
		$this->setExpectedException('Exception', 'Expected "=" but "!" found.');
		$this->parser->parse('[attr!="val"]');
	}
	function testException4 () {
		$this->setExpectedException('Exception', 'Expected "=" but end of file reached.');
		$this->parser->parse('[attr');
	}
	function testException5 () {
		$this->setExpectedException('Exception', 'Expected ")" but "!" found.');
		$this->parser->parse(':pseudoName("pseudoValue"!');
	}
	function testException6 () {
		$this->setExpectedException('Exception', 'Expected ")" but end of file reached.');
		$this->parser->parse(':pseudoName("pseudoValue"');
	}
	function testException7 () {
		$this->setExpectedException('Exception', 'Rule expected after ">".');
		$this->parser->parse('tag>');
	}
	function testException8 () {
		$this->setExpectedException('Exception', 'Rule expected after "+".');
		$this->parser->parse('tag+');
	}
	function testException9 () {
		$this->setExpectedException('Exception', 'Rule expected after "~".');
		$this->parser->parse('tag~');
	}
	function testException10 () {
		$this->setExpectedException('Exception', 'Rule expected but "!" found.');
		$this->parser->parse('tag !');
	}
	function testException11 () {
		$this->setExpectedException('Exception', 'Rule expected but "!" found.');
		$this->parser->parse('tag!');
	}
	function testException12 () {
		$this->setExpectedException('Exception', 'Rule expected after ",".');
		$this->parser->parse('tag,');
	}

	function testEscaping () {
		$this->assertEquals('tag\\n\\\\name\\.\\[',
			$this->parser->render($this->parser->parse('tag\\n\\\\name\\.\\[')));
		$this->assertEquals('.cls\\n\\\\name\\.\\[',
			$this->parser->render($this->parser->parse('.cls\\n\\\\name\\.\\[')));
		$this->assertEquals('[attr\\n\\\\name\\.\\[="1"]',
			$this->parser->render($this->parser->parse('[attr\\n\\\\name\\.\\[=1]')));
		$this->assertEquals(':pseudo\\n\\\\name\\.\\[\\(("123")',
			$this->parser->render($this->parser->parse(':pseudo\\n\\\\name\\.\\[\\((123)')));
		$this->assertEquals('[attr="val\\nval"]',
			$this->parser->render($this->parser->parse('[attr="val\nval"]')));
		$this->assertEquals('[attr="val\\"val"]',
			$this->parser->render($this->parser->parse('[attr="val\\"val"]')));
		$this->assertEquals('[attr="val val"]',
			$this->parser->render($this->parser->parse('[attr="val\\00a0val"]')));

		// skipped cuz bugs:
		// assertEquals('tag\\a0 tag', $parser->render($parser->parse('tag\\00a0 tag')));
		// assertEquals('.class\\a0 class', $parser->render($parser->parse('.class\\00a0 class')));
		// assertEquals('[attr\\a0 attr]', $parser->render($parser->parse('[attr\\a0 attr]')));
	}

	function testSubstitutes () {
		$this->assertEquals('[attr="$var"]',
			$this->parser->render($this->parser->parse('[attr=$var]')));
		$this->assertEquals(':has("$var")',
			$this->parser->render($this->parser->parse(':has($var)')));

		$this->parser->enableSubstitutes();
		$this->assertEquals('[attr=$var]',
			$this->parser->render($this->parser->parse('[attr=$var]')));
		$this->assertEquals(':has($var)',
			$this->parser->render($this->parser->parse(':has($var)')));

		$this->parser->disableSubstitutes();
		$this->assertEquals('[attr="$var"]',
			$this->parser->render($this->parser->parse('[attr=$var]')));
		$this->assertEquals(':has("$var")',
			$this->parser->render($this->parser->parse(':has($var)')));
	}

	function testNestingOperators () {
		$this->parser->registerNestingOperators(';');
		$this->assertEquals('tag1 ; tag2',
			$this->parser->render($this->parser->parse('tag1 ; tag2')));
		$this->parser->unregisterNestingOperators(';');

		$this->setExpectedException('Exception', 'Rule expected but ";" found.');
		$this->parser->parse('tag1 ; tag2');
	}

	function testAttrEqualityMods () {
		$this->parser->registerAttrEqualityMods(';');
		$this->assertEquals('[attr;="val"]',
			$this->parser->render($this->parser->parse('[attr;=val]')));
		$this->parser->unregisterAttrEqualityMods(';');

		$this->setExpectedException('Exception', 'Expected "=" but ";" found.');
		$this->parser->parse('[attr;=val]');
	}

	function testComplexSelector () {
		$this->assertEquals('#y.cls1.cls2 .cls3 + abc#def[x="y"] > yy, ff',
			$this->parser->render($this->parser->parse('.cls1.cls2#y .cls3+abc#def[x=y]>yy,ff')));
	}

	function testNestedStar1 () {
		$this->assertEquals('* > tag', $this->parser->render($this->parser->parse('*>tag')));
	}

	function testNestedStar2 () {
		$this->assertEquals('tag > *', $this->parser->render($this->parser->parse('tag>*')));
	}
}
