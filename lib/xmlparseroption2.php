<?php
namespace Gaz;

use Exception;

class XmlParserOption2 extends XmlParser
{
    /**
     * @return array
     * @throws Exception
     */
    function XmlToArray():array
    {
        $previous_value = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->loadXml($this->xmlString);
        libxml_use_internal_errors($previous_value);
        if (libxml_get_errors()) {
            throw new Exception('libxml_use_internal error!');
        }
        return $this->DomToArray($dom);
    }

    /**
     * @param $root
     * @return mixed
     */
    function DomToArray($root)
    {
        $result = array();

        if ($root->hasAttributes()) {
            $attrs = $root->attributes;
            foreach ($attrs as $attr) {
                $result['@attributes'][$attr->name] = $attr->value;
            }
        }

        if ($root->hasChildNodes()) {
            $children = $root->childNodes;
            if ($children->length == 1) {
                $child = $children->item(0);
                if (in_array($child->nodeType,[XML_TEXT_NODE,XML_CDATA_SECTION_NODE])) {
                    $result['_value'] = $child->nodeValue;
                    return count($result) == 1
                        ? $result['_value']
                        : $result;
                }

            }
            $groups = array();
            foreach ($children as $child) {
                if (!isset($result[$child->nodeName])) {
                    $result[$child->nodeName] = $this->DomToArray($child);
                } else {
                    if (!isset($groups[$child->nodeName])) {
                        $result[$child->nodeName] = array($result[$child->nodeName]);
                        $groups[$child->nodeName] = 1;
                    }
                    $result[$child->nodeName][] = $this->DomToArray($child);
                }
            }
        }
        return $result;
    }

    /**
     * строку xml в массив
     * @throws Exception
     */
    public function getParsedArray()
    {
        $item = [];
        list($this->array,) = XmlParser::searchKey('item',$this->XmlToArray(), $item);;
    }
}