DOMi, DOM Improved
===============================================================================

* Author: Steve Phillips  
  <http://stevephillips.me>
* Version: 1.2.0
* License: GPL 3

What Is DOMi?
-------------------------------------------------------------------------------
DOMi is an improved DOM object for PHP that combines and improves upon the 
capabilities of three existing PHP objects: DOMDocument, DOMXpath and 
XSLTProcessor. These three objects, combined with the XSL language, create a
templating system that can be used to render HTML pages.

Example
-------------------------------------------------------------------------------

### Creating the DOMi object

The first step to using DOMi to create an XSL template is to include the DOMi 
class and create an instance of the DOMi object. DOMi's constructor requires a 
single parameter to be passed over. This parameter is used to set up the 
DOMDocument object that DOMi will use to build the XML tree. Three different 
kinds of parameters can be passed to create a DOMi object.

The first way to create a DOMi object is passing a string to be used as the 
node name of the root node of the XML document, as in the example below.

    $DOMi = new DOMi('root');

The second way to create a DOMi object is by passing a string that is the 
filename of a local XML document.

    $DOMi = new DOMi();
    $DOMi->load('/var/www/xml/document.xml');

The third way to create a DOMi object is by passing a DOMDocument object, and 
DOMi will use this DOMDocument as it's primary DOMDocument

    $DOMDocument = new DOMDocument('1.0', 'UTF-8');
    $DOMi = new DOMi($DOMDocument);

In all three events, DOMi is created with a built in DOMDocument, DOMXpath and 
XSLTProcessor. Each of these objects is a member property that can be accessed 
transparently through DOMi. For instance, if you wanted to use the 
DOMXpath::query method, you could access it in one of two ways

    $DOMi->Xpath->query()
    $DOMi->query()

Both of these methods are identical. When an unknown method is requested of 
DOMi, it will check to see if that method exists within it's member objects, 
and if so, return the result of the member object invoking the requested method. 
Through this system, legacy support for old DOMDocument setups is 100%.

### Adding data to DOMi

Once the DOMi object exists, the next step is to begin adding data to the 
DOMDocument, and the best way to do this is through the DOMi::AttachToXml 
method. This method will accept a PHP data structure and transform it into an 
XML tree and attach it to the DOMDocument inside DOMi. Currently, 
AttachToXml supports the following PHP data structures

* Array
* String
* Int
* Null
* DOMDocument
* DOMElement
* Any PHP object

AttachToXml accepts two required parameters and a third optional parameter. The 
first parameter is the data to be converted into an XML tree. The second 
parameter is the name to be used for the node. The third parameter is a DOMNode 
within DOMi's DOMDocument where the new node will be attached, this will default
to the root node.

    $DOMi->AttachToXml($_SERVER, 'server');

This code will make a new node named &lt;server&gt;, attach that to the root 
node of the DOMDocument, and create new nodes for each element within the 
$_SERVER superglobal array.

### Adding stylesheets

DOMi can import a stylesheet upon rendering, or a manual call can be made to 
add the stylesheet. If you plan to add upon rendering, which is a simpler, 
cleaner method, you may skip to the Rendering section.

As mentioned above, DOMi is capable of transparently accessing any of the 
methods of DOMDocument, DOMXpath and XSLTProcessor. This means that stylesheets
are added through the same method as XSLTProcessor - the importStylesheet() 
method.

    $DOMi->importStylesheet($DOMStylesheet);

However, DOMi has a method that allows for easier creation of DOMDocument 
objects that are to be used as stylesheets. The GenerateXsl() method accepts 
either a string or an array of filenames that are to be included. This method
will then dynamically create a DOMDocument that includes each of the provided
stylesheets, which is useful when the list of stylesheets to be included will
vary based on what page the user is looking at. GenerateXsl will, by default,
then import these stylesheets, although a second parameter can be passed to
disable this feature.

    $DOMi->GenerateXsl('/var/www/xsl/skin.xsl');

### Rendering

Once DOMi has been created, data has been added, and a stylesheet has been 
imported, the only thing left to do is render the page. DOMi::Render() is the 
method that DOMi uses to run the XSLTProcessor and return the output. 
Render() accepts two optional parameters - the file location for an XSL 
stylesheet and a rendering flag. The first parameter can be used to provide 
the stylesheet upon rendering, if GenerateXsl() or importStylesheet() is not 
called. The second flag can be set to one of the following three values

* DOMi::RENDER_HTML - the default setting that will run the XML and XSL 
  stylesheet through the XSLTProcessor and return the output.
* DOMi::RENDER_XML - display the DOMDocument contents with the content type set
  as XML, this is the useful for debugging as it lets you see your XML tree to 
  help you write your XSL.
* DOMi::RENDER_VIEW - display the result of the XML / XSL / XSLTProcessor 
  conversion with the content type set as XML, this is primarily used for 
  writing APIs.

