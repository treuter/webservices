<?php namespace webservices\wddx;

use xml\Tree;
use text\parser\DateParser;


/**
 * Class representing wddx messages. It can handle serialization and
 * deserialization to/from wddx.
 *
 * @ext      xml
 * @see      http://www.openwddx.org/downloads/dtd/wddx_dtd_10.txt
 * @purpose  Serialize wddx packets.
 */
class WddxMessage extends Tree {

  /**
   * Constructor.
   *
   */
  public function __construct() {
    parent::__construct('wddxPacket');
    $this->root()->setAttribute('version', '1.0');
  }
  
  /**
   * Create a WddxMessage object from an XML document.
   *
   * @param   string string
   * @return  webservices.wddx.WddxMessage
   */
  public static function fromString($string) {
    return parent::fromString($string, 'WddxMessage');
  }    
  
  /**
   * Sets the comment in a Wddx packet
   *
   * @param   string comment
   */
  public function create($comment= null) {
    $h= $this->root()->addChild(new \xml\Node('header'));
    if ($comment) $h->addChild(new \xml\Node('comment', $comment));
  }
  
  /**
   * Set data for the message
   *
   * @param   var[] arr
   */
  public function setData($arr) {
    $d= $this->root()->addChild(new \xml\Node('data'));
    if (sizeof($arr)) foreach (array_keys($arr) as $idx) {
      $this->_marshall($d, $arr[$idx]);
    }
  }
  
  /**
   * Marshall method to serialize data into the Wddx message.
   *
   * @param   xml.Node node
   * @param   var data
   * @throws  lang.IllegalArgumentException if passed data could not be serialized
   */
  protected function _marshall($node, $data) {
    switch (\xp::typeOf($data)) {
      case 'NULL':
        $node->addChild(new \xml\Node('null'));
        break;
      
      case 'boolean':
        $node->addChild(new \xml\Node('boolean', null, [
          'value' => $data ? 'true' : 'false'
        ]));
        break;
      
      case 'string':
        $node->addChild(new \xml\Node('string', $data));
        break;
      
      case 'double':
      case 'integer':
        $node->addChild(new \xml\Node('number', $data));
        break;
      
      case 'array':
        $s= $node->addChild(new \xml\Node('struct'));
        foreach (array_keys($data) as $idx) {
          $this->_marshall($s->addChild(new \xml\Node('var', null, [
            'name'  => $idx
          ])), $data[$idx]);
        }
        break;
      
      case 'util.Date':
        
        // FIXME
        $node->addChild(new \xml\Node('dateTime', $data->toString('r')));
        break;
      
      default:
        throw new \lang\IllegalArgumentException('Found datatype which cannot be serialized: '.\xp::typeOf($data));
    }
  }
  
  /**
   * Retrieve data from wddx message.
   *
   * @return  var[]
   * @throws  lang.IllegalStateException if no payload data could be found in the message
   */
  public function getData() {
    $ret= [];
    foreach (array_keys($this->root()->getChildren()) as $idx) {
      if ('header' == $this->root()->nodeAt($idx)->getName())
        continue;
      
      // Process params node
      foreach (array_keys($this->root()->nodeAt($idx)->getChildren()) as $params) {
        $ret[]= $this->_unmarshall($this->root()->nodeAt($idx)->nodeAt($params));
      }
      
      return $ret;
    }
    
    throw new \lang\IllegalStateException('No payload found.');
  }
  
  /**
   * Umarshall method for deserialize data from wddx message
   *
   * @param   xml.Node node
   * @return  var[]
   * @throws  lang.IllegalArgumentException if document is not well-formed
   */
  protected function _unmarshall($node) {
    switch ($node->getName()) {
      case 'null': return null;
      case 'boolean': return ($node->getContent() == 'true' ? true : false);
      case 'string': return $node->getContent();
      case 'dateTime': 
        $parser= new DateParser();
        return $parser->parse($node->getContent());
      
      case 'number':
        if ($node->getContent() == intval($node->getContent())) return intval($node->getContent());
        return (double)$node->getContent();
      
      case 'char':
        return chr($node->getAttribute('code'));
      
      case 'binary':
        // TBI
        return;
      
      case 'array':
        $arr= [];
        foreach (array_keys($node->getChildren()) as $idx) {
          $arr[]= $this->_unmarshall($node->nodeAt($idx));
        }
        return $arr;
      
      case 'struct':
        $struct= [];
        foreach (array_keys($node->getChildren()) as $idx) {
          $struct[$node->nodeAt($idx)->getAttribute('name')]= $this->_unmarshall($node->nodeAt($idx));
        }
        return $struct;
    }
    
    throw new \lang\IllegalArgumentException('Cannot unserialize not well-formed WDDX document');
  }
}
