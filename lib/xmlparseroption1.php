<?php
namespace Gaz;

class XmlParserOption1 extends XmlParser
{
    /**
     * строку xml в массив
     */
    public function getParsedArray()
    {
        $parsedArray = json_decode(json_encode(simplexml_load_string($this->xmlString, 'SimpleXMLElement', LIBXML_NOCDATA)),TRUE);
        $item = [];
        list($this->array,) = XmlParser::searchKey('item',$parsedArray, $item);;
    }
}