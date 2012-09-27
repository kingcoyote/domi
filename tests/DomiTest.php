<?php

use Domi\Domi;

class DomiTest extends PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        // test constructor formats
        $this->assertInstanceOf('Domi\Domi', new Domi());
        $this->assertInstanceOf('Domi\Domi', new Domi('foo'));
        $this->assertInstanceOf('Domi\Domi', new Domi('testInitialize.xml'));

        // test custom root node name
        $domi = new Domi('bar');
        $this->assertEquals($domi->dom->firstChild->nodeName, 'bar');

        // test character encodings
    }

    public function testAttachToXml()
    {
        // test simple string
        $domi = new Domi();
        $domi->attachToXml('bar', 'foo');

        $testDom = new DOMDocument('1.0', 'UTF-8');
        $root = $testDom->createElement('root');
        $root->appendChild($testDom->createElement('foo', 'bar'));
        $testDom->appendChild($root);

        $this->assertEqualXMLStructure($domi->dom->firstChild, $testDom->firstChild);

        unset($domi, $root, $testDom);

        // test boolean
        $domi = new Domi();
        $domi->attachToXml(true, 'foo');
        $domi->attachToXml(false, 'bar');

        $testDom = new DOMDocument('1.0', 'UTF-8');
        $testDom->appendChild($testDom->createElement('root'));
        $testDom->firstChild->appendChild($testDom->createElement('foo', 'TRUE'));
        $testDom->firstChild->appendChild($testDom->createElement('bar', 'FALSE'));
        
        $this->assertEqualXMLStructure($domi->dom->firstChild, $testDom->firstChild);

        unset($domi, $testDom);

        // test single dimensional non-associative array
        $domi = new Domi();
        $domi->attachToXml(array('one', 'two', 'three'), 'array');

        $testDom = new DOMDocument('1.0', 'UTF-8');
        $root = $testDom->createElement('root');
        $arrayList = $testDom->createElement('array-list');
        $arrayList->appendChild($testDom->createElement('array', 'one'));
        $arrayList->appendChild($testDom->createElement('array', 'two'));
        $arrayList->appendChild($testDom->createElement('array', 'three'));
        $root->appendChild($arrayList);
        $testDom->appendChild($root);

        $this->assertEqualXMLStructure($domi->dom->firstChild, $testDom->firstChild);
        
        unset($domi, $testDom, $root, $arrayList);


        // test single dimensional associative array
        $domi = new Domi();
        $domi->attachToXml(array('one'=>'uno', 'two'=>'dos', 'three'=>'tres'), 'array');

        $testDom = new DOMDocument('1.0', 'UTF-8');
        $root = $testDom->createElement('root');
        $arrayList = $testDom->createElement('array');
        $arrayList->appendChild($testDom->createElement('one', 'uno'));
        $arrayList->appendChild($testDom->createElement('two', 'dos'));
        $arrayList->appendChild($testDom->createElement('three', 'tres'));
        $root->appendChild($arrayList);
        $testDom->appendChild($root);

        $this->assertEqualXMLStructure($domi->dom->firstChild, $testDom->firstChild);
        
        unset($domi, $testDom, $root, $arrayList);

        // test multi dimensional non-associative array
        $domi = new Domi();
        $domi->attachToXml(array('one', array('two', 'three')), 'array');

        $testDom = new DOMDocument('1.0', 'UTF-8');
        $root = $testDom->createElement('root');
        $arrayList = $testDom->createElement('array-list');
        $arrayList->appendChild($testDom->createElement('array', 'one'));
        $two = $testDom->createElement('array-list');
        $arrayList->appendChild($two);
        $two->appendChild($testDom->createElement('array', 'two'));
        $two->appendChild($testDom->createElement('array', 'three'));
        $root->appendChild($arrayList);
        $testDom->appendChild($root);

        $this->assertEqualXMLStructure($domi->dom->firstChild, $testDom->firstChild);
        
        unset($domi, $testDom, $root, $arrayList, $two);

        // test multi-dimensional associative array

        // test object

        // test domi

        // test domnode

        // test domdocument

        // test invalid prefix
    }

    public function testRender()
    {
        // test xml
        // test xsl
        // test view
    }

    public function testListSuffix()
    {
        $domi = new Domi();
        $domi->listSuffix = "-foobar";
        $domi->attachToXml(array('one', 'two'), 'list');

        $testDom = new DOMDocument('1.0', 'UTF-8');
        $testDom->appendChild($testDom->createElement('root'));
        $testDom->firstChild->appendChild($testDom->createElement('list-foobar'));
        $testDom->firstChild->firstChild->appendChild($testDom->createElement('list', 'one'));
        $testDom->firstChild->firstChild->appendChild($testDom->createElement('list', 'two'));

        $this->assertEqualXMLStructure($domi->firstChild, $testDom->firstChild);
    }
}

