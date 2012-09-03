<?php

namespace Domi;

use DOMDocument;
use DOMXpath;
use XSLTProcessor;
use DOMNode;
use Exception;

/**
 * Improved DOM object - a merger of DOMDocument, DOMXpath and XSLTProcessor, 
 * with extended functionality for converting PHP data types into an XML 
 * structure and convert the XML into the desired output format
 *
 * @author Steve Phillips <kingcoyote85@gmail.com>
 * @version 1.2.0
 */

class Domi 
{
    public $listSuffix = "-list"; ///< @var string suffix used when a list is made
    public $xslt;                 ///< @var XSLTProcessor internal XSLTProcessor 
    public $dom;                  ///< @var DOMDocument internal DOMDocument
    public $xpath;                ///< @var DOMXpath internal DOMXpath
    public $mainNode;             ///< @var DOMNode DOMDocument root node
    public $encoding;             ///< @var string character encoding (ie, UTF-8)
    
    protected $internalClasses = array('dom', 'xslt', 'xpath');
    
    /// @var const regular expression matching a valid node name
    const REGEX_PREFIX = '/^[a-zA-Z][-a-zA-Z0-9_.]*$/'; 
    
    const RENDER_VIEW = 1;
    const RENDER_HTML = 2;
    const RENDER_XML  = 4;
    const RENDER_JSON = 8;
    
    const DT_ARRAY       = 'array';
    const DT_ATTR_ARRAY  = 'attr-array';
    const DT_STRING      = 'string';
    const DT_DOMI        = 'domi';
    const DT_DOMNODE     = 'domnode';
    const DT_DOMDOCUMENT = 'domdocument';
    const DT_OBJECT      = 'object';
    const DT_BOOL        = 'bool';
    
    /**
     *  create an instance of the DOMi object
     *  @param string the name that will be used on the root node
     *  @param string character encoding for the DOMi object, ie UTF-8
     *  @retval DOMi the created DOMi object
     */
    public function __construct($mainNodeName='root', $encoding='UTF-8') 
    {
        if (self::isValidPrefix($mainNodeName)) {
            $this->encoding = $encoding;
            $this->dom = new DOMDocument('1.0', $this->encoding);
            $this->mainNode = $this->createElement($mainNodeName);
            $this->appendChild($this->mainNode);
            $this->xpath = new DOMXpath($this->dom);
            $this->xslt = new XSLTProcessor();
        } else {
            throw new exception("invalid prefix '$mainNodeName'");
        }
    }
    
    /**
     *  the heart of DOMi - take a complex data tree and build it into an
     *  XML tree with the specified prefix and attach it to either the
     *  specified node or the root node
     *
     *  @param mixed any PHP data type that is being converted to XML
     *  @param string the name of the node that the data will be built onto
     *  @param DOMNode node to attach the newly created onto
     *  @retval DOMNode the newly created node
     */
    public function attachToXml($data, $prefix, &$parentNode = false)
    {
        if (!$parentNode) {
            $parentNode = &$this->mainNode;
        }
        // i don't like how this is done, but i can't see an easy alternative
        // that is clean. if the prefix is attributes, instead of creating
        // a node, just put all of the data onto the parent node as attributes
        if (strtolower($prefix) == 'attributes') {
            // set all of the attributes onto the node
            foreach ($data as $key=>$val) {
                $parentNode->setAttribute($key, $val);
            }

            $node = &$parentNode;
        } else {
            $node = $this->convertToXml($data, $prefix);
            if ($node instanceof DOMNode) {
                $parentNode->appendChild($node);
            }
        }
        return $node;
    }
    
    /**
     *  convert a data tree to an XML tree with the name specified as the
     *  prefix
     *
     *  @param mixed any PHP data type that is being converted to XML
     *  @param string the name of the node that the data will be built onto
     *  @retval DOMNode the newly created node
     */
    public function convertToXml($data, $prefix)
    {
        $nodeName = $prefix;
        // figure out the prefix
        if (!self::isValidPrefix($prefix)) {
            throw new exception("invalid prefix '$prefix'");
        }
        
        // if the data needs a list node, change the name to use the list-suffix
        if (self::isListNode($data)) {
            $nodeName = $prefix . $this->listSuffix;
        }

        switch (self::getDataType($data)) {
            // if this array has attributes, do some additional work
            case self::DT_ATTR_ARRAY:
                // create the node, with the optionally specified value
                $node = $this->createElement(
                    $nodeName, 
                    isset($data['values']) ? $data['values'] : null
                );
                $data['attributes'] = 
                    isset($data['attributes']) ? 
                    $data['attributes'] : 
                    array();
                
                // set all of the attributes onto the node
                foreach ($data['attributes'] as $key=>$val) {
                    $node->setAttribute($key, $val);
                }

                // remove the attributes and value so they aren't repeated
                // as children of the element
                unset($data['attributes']);
                unset($data['values']);
            case self::DT_ARRAY:
                // in the case of DT_ATTR_ARRAY, the node is already created
                if (!isset($node)) {
                    $node = $this->createElement($nodeName);
                }

                // attach each child as a subnode
                foreach ($data as $k=>$d) {
                    // figure out the child prefix
                    $childPrefix = self::isValidPrefix($k) ? $k : $prefix;
                    // recurse and attach
                    $node->appendChild($this->convertToXml($d, $childPrefix));
                }
                break;
            
            // when converting DOMi or DOMDocuments, just get the root node
            case self::DT_DOMI:
                // no break
            case self::DT_DOMDOCUMENT:
                $data = $data->childNodes->item(0);
                // no break
            case self::DT_DOMNODE:
                // the node must be imported to be usable in this DOMDocument
                $domNode = $this->importNode($data, true);
                // only create a new node if the prefix and current root
                // node name aren't the same
                if ($prefix == $domNode->nodeName) {
                    $node = $domNode;
                } else {
                    $node = $this->createElement($prefix);
                    $node->appendChild($domNode);
                }
                break;
            
            case self::DT_OBJECT:
                $node = $this->convertToXml(
                    $this->convertObjectToArray($data), 
                    $prefix
                );
                break;
            
            case self::DT_BOOL:
                $data = $data ? 'TRUE' : 'FALSE';
                // no break
            default:
                $node = $this->createElement(
                    $nodeName, 
                    htmlspecialchars((string)$data)
                );
                break;
        }
        
        return $node;
    }
    
    /**
     *  process and return the output that will be sent to screen during
     *  the display process
     *
     *  @param mixed a string or array listing the XSL stylesheets to be used
     *      for the rendering process
     *  @param int a flag indicating the rendering type. acceptable values
     *      are DOMi::RENDER_HTML and DOMi::RENDER_XML
     *
     *  @retval string the result of the processing based on the stylesheets
     *      and the rendering mode
     */
    public function render($stylesheets=false, $mode=self::RENDER_HTML) 
    {
        $this->xslt->importStylesheet($this->generateXsl($stylesheets));
        return $this->generateOutput($mode);
    }
    
    private function isListNode($data) 
    {
        // if there are any invalid prefixes, a list must be used
        return 
            is_array($data) && 
            count(
                array_filter(
                    array_keys($data), 
                    array($this, 'isValidPrefix')
                )
            ) != count($data);
    }
    
    private function getDataType($data) 
    {
        $dataType = self::DT_STRING;
        
        if (is_array($data)) 
        {
            $dataType = 
                isset($data['attributes']) || isset($data['values']) ? 
                self::DT_ATTR_ARRAY : 
                self::DT_ARRAY;
        } elseif ($data INSTANCEOF DOMi) {
            $dataType = self::DT_DOMI;
        } elseif ($data INSTANCEOF DOMDocument) {
            $dataType = self::DT_DOMDOCUMENT;
        } elseif ($data INSTANCEOF DOMNode) {
            $dataType = self::DT_DOMNODE;
        } elseif (is_object($data)) {
            $dataType = self::DT_OBJECT;
        } elseif (is_bool($data)) {
            $dataType = self::DT_BOOL;
        }
        
        return $dataType;
    }
    
    private function generateXsl($stylesheets)
    {
        // create a DOMDocument that will include every specified stylesheet
        $dom = new DOMDocument('1.0', $this->encoding);
        $stylesheetNode = $dom->createElementNS(
            'http://www.w3.org/1999/XSL/Transform', 
            'xsl:stylesheet'
        );
        $stylesheetNode->setAttribute('version', '1.0');
        $stylesheetNode->setAttribute(
            'xmlns:xsl', 
            'http://www.w3.org/1999/XSL/Transform'
        );
        $dom->appendChild($stylesheetNode);
        
        if (!is_array($stylesheets)) {
            $stylesheets = array($stylesheets);
        }
        
        // create an include node for each non-false stylesheet specified
        foreach (array_filter($stylesheets) as $stylesheet) {
            $includeNode = $dom->createElementNS(
                'http://www.w3.org/1999/XSL/Transform', 
                'xsl:include'
            );
            $includeNode->setAttribute('href', (string)$stylesheet);
            $stylesheetNode->appendChild($includeNode);
        }
        
        return $dom;
    }
    
    private function generateOutput($mode) 
    {
        switch ($mode) {
            case self::RENDER_XML:
                $output = $this->saveXml();
                break;
            
            case self::RENDER_HTML:
                $output = $this->transformToXML($this->dom);
                break;
        }
        
        return $output;
    }
    
    private function convertObjectToArray(&$element) 
    {
        // the recursive call can't operate through objects, so they
        // must be handled specially
        if (is_object($element)) 
        {
            // typecast the array to an object, and clean up private and
            // protected keys
            $element = $this->keyCleanup((array)$element);
            // begin the recursion again to go through this object-turned-array
            // this is not strictly necesary, as removing it will cause the
            // recursion to happen in convertToXml, but putting it here makes it
            // more readable and ever so slightly faster.
            array_walk_recursive(
                $element, 
                array($this, 'convertObjectToArray')
            );
        }
        return $element;
    }
    
    private function keyCleanup($array) 
    {
        // find every invalid key (private and protected member properties)
        foreach( array_filter(array_keys($array), array($this, 'isInvalidKey')) 
            as $invalidKey) {
            // change the key name by copy / delete / create
            $data = $array[$invalidKey];
            unset($array[$invalidKey]);
            // find out the correct key name by getting the last chunk that
            // is only ascii 32 - 126, the standard set of printable characters
            // User�Types => Types
            $key = preg_replace(
                '/^.*[^\x20-\xFE]([\x20-\xFE]*)$/', 
                '\\1', 
                $invalidKey
            );
            $array[$key] = $data;
        }
        
        return $array;
    }
    
    private function isInvalidKey($key) 
    {
        // a key is invalid if it has any characters that are outside
        // of the ascii range 32 - 126, which is the standard set of printable
        // characters
        return preg_match('/[^\x20-\xFE]/', $key);
    }
    
    public function __call($method, $parameters) 
    {
        $obj = null;
        
        foreach ($this->internalClasses as $class) {
            if (method_exists($this->$class, $method)) {
                $obj = $class;
            }
        }
        
        if ($obj === null) {
            $backtrace = debug_backtrace();
            $exception = 
                "Call to undefined method DOMi::$method()"
                ." in {$backtrace[1]['file']}"
                ." on line {$backtrace[1]['line']}";
            throw new exception($exception);
        }
        
        // if it exists, transparently call and return that function
        return call_user_func_array(array($this->$obj, $method), $parameters);
    }
    
    public function __get($property) 
    {
        $get = null;
        
        foreach ($this->internalClasses as $class) {
            if (isset($this->$class->$property)) {
                $get = $this->$class->$property;
            }
        }
        
        if ($get === null && strtolower($property) == 'dom') {
            // legacy support for older versions of DOMi that used 
            // upper camel case for variable names
            $get = $this->dom;
        }
        
        return $get;
    }
    
    /**
     *  indicates whether a prefix is acceptable for XML node names
     *  
     *  @param string the prefix to be checked
     *  @retval bool whether or not the prefix is acceptable
     */
    static public function isValidPrefix($prefix) 
    {
        return preg_match(self::REGEX_PREFIX, $prefix);
    }
    
    /**
     *  generate either a domi object or a rendered string in a single line
     *  if templates are provided (or false specified for xml rendering), a 
     *  rendered string will be returned. otherwise, the domi object created
     *  will be returned
     *
     *  @param string the name of the root node of the domdocument
     *  @param string the prefix of the data to be attached to the domdocument
     *  @param mixed the data to be attached to the domdocument
     *  @param mixed xsl templates to be used for rendering
     *  @param integer render mode to be used when rendering
     */
    static public function generate($root=false, $data=false, $xsl=null, $render=false) 
    {
        $root = $root ? $root : 'root';
        $dom = new DOMi($root);
        
        if ($data) {
            $dom->attachToXml($data, $root);
        }
        
        if ($xsl !== null) {
            $render = $render ? $render : self::RENDER_HTML;
            return $dom->render($xsl, $render);
        } else {
            return $dom;
        }
    }
}

