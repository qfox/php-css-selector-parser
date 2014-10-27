php-css-selector-parser
=======================

Fast and low memory CSS selector parser.

Parses CSS selector into object-model.

Installation
------------

```
composer install css-selector-parser
```

Usage
-----

```php
use \CSSSelector\Parser;

$parser = new Parser();

$parser->registerSelectorPseudos('has');
$parser->registerNestingOperators('>', '+', '~');
$parser->registerAttrEqualityMods('^', '$', '*', '~');
$parser->enableSubstitutes();

$css = 'a[href^=/], .container:has(nav) > a[href]:lt($var)';
var_dump(json_encode($parser->parse($css), JSON_PRETTY_PRINT));
```

Produces:

```
{ type: 'selectors',
  selectors:
   [ { type: 'ruleSet',
       rule:
        { tagName: 'a',
          attrs: [ { name: 'href', operator: '^=', valueType: 'string', value: '/' } ],
          type: 'rule' } },
     { type: 'ruleSet',
       rule:
        { classNames: [ 'container' ],
          pseudos:
           [ { name: 'has',
               valueType: 'selector',
               value: { type: 'ruleSet', rule: { tagName: 'nav', type: 'rule' } } } ],
          type: 'rule',
          rule:
           { tagName: 'a',
             attrs: [ { name: 'href' } ],
             pseudos: [ { name: 'lt', valueType: 'substitute', value: 'var' } ],
             nestingOperator: '>',
             type: 'rule' } } } ] }
```

Token description
-----------------

*type* may be one of:

* *selectors* — list of selectors, token contains *selectors* array of *ruleSet* tokens (based on "," operator).
* *ruleSet* — selector, token contains *rule* field with *rule*-type object.
* *rule* — single rule.

Fields for *rule* type.

* *tagName* — tag name for the rule (e.g. "div"), may be '*'.
* *classNames* — list of CSS class names for the rule.
* *attrs* — list of attribute rules; rule may contain fields:
  * *name* — attribute name, required field.
  * *valueType* — type of comparison value ("string" or "substitute").
  * *operator* — attribute value comparison operator.
  * *value* — comparison attribute value.
* *pseudos* — list of pseudo class rules; rule may contain fields:
  * *name* — pseudo name, required field.
  * *valueType* — argument type ("string", "selector" or "substitute").
  * *value* — pseudo argument.
* *nestingOperator* — the operator used to nest this rule (e.g. in selector "tag1 > tag2", tag2 will have nestingOperator=">")
* *rule* — nested rule.

License
-------

MIT
