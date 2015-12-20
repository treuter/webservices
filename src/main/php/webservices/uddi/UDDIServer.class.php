<?php namespace webservices\uddi;

use webservices\soap\xp\XPSoapMessage;
use webservices\soap\SOAPFaultException;
use peer\http\HttpConnection;
use util\log\Traceable;
use peer\http\HttpConstants;


/**
 * UDDI server
 *
 * Example:
 * <code>
 *   uses('webservices.uddi.UDDIServer', 'webservices.uddi.FindBusinessesCommand');
 *
 *   $c= new UDDIServer(
 *     'http://test.uddi.microsoft.com/inquire', 
 *     'https://test.uddi.microsoft.com/publish'
 *   );
 *   try {
 *     $r= $c->invoke(new FindBusinessesCommand(
 *       array('%Microsoft%'),
 *       array(SORT_BY_DATE_ASC, SORT_BY_NAME_ASC),
 *       5
 *     ));
 *   } catch(XPException $e) {
 *     $e->printStackTrace();
 *     exit(-1);
 *   }
 *   
 *   echo $r->toString();
 * </code>
 *
 * @see      xp://webservices.soap.SOAPClient
 * @purpose  Access to UDDI
 */
class UDDIServer extends \lang\Object implements Traceable {
  public
    $cat      = null,
    $conn     = [],
    $version  = 0;

  /**
   * Constructor
   *
   * @param   string inquiryUrl
   * @param   string publishUrl
   * @param   int version default 2
   */
  public function __construct($inquiryUrl, $publishUrl, $version= 2) {
    $this->conn['inquiry']= new HttpConnection($inquiryUrl);
    $this->conn['publish']= new HttpConnection($publishUrl);
    $this->version= $version;
  }

  /**
   * Set Version
   *
   * @param   int version
   */
  public function setVersion($version) {
    $this->version= $version;
  }

  /**
   * Get Version
   *
   * @return  int
   */
  public function getVersion() {
    return $this->version;
  }
  
  /**
   * Invoke a command
   *
   * @param   webservices.uddi.UDDICommand
   * @return  lang.Object
   * @throws  lang.IllegalArgumentException in case an illegal command was passed
   * @throws  io.IOException in case the HTTP request failed
   * @throws  webservices.soap.SOAPFaultException in case a SOAP fault was returned
   * @throws  xml.XMLFormatException in case the XML returned was not well-formed
   */
  public function invoke($command) {
    if (is('webservices.uddi.InquiryCommand', $command)) {
      $c= $this->conn['inquiry'];
    } else if (is('webservices.uddi.PublishCommand', $command)) {
      $c= $this->conn['publish'];
    } else {
      throw new \lang\IllegalArgumentException(
        'Unknown command type "'.\xp::typeOf($command).'"'
      );
    }
    
    // Create message
    with ($m= new XPSoapMessage()); {
      $m->encoding= 'utf-8';
      $m->root= new \xml\Node('soap:Envelope', null, [
        'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/'
      ]);
      $body= $m->root()->addChild(new \xml\Node('soap:Body'));
      $command->marshalTo($body->addChild(new \xml\Node('command', null, [
        'xmlns'      => UDDIConstants::namespaceFor($this->version),
        'generic'    => UDDIConstants::versionIdFor($this->version)
      ])));
    }
    
    // Assemble request
    with ($r= $c->request->create(new \peer\http\HttpRequest())); {
      $r->setMethod(HttpConstants::POST);
      $r->setParameters(new \peer\http\RequestData(
        $m->getDeclaration()."\n".
        $m->getSource(0)
      ));
      $r->setHeader('SOAPAction', '""');
      $r->setHeader('Content-Type', 'text/xml; charset='.$m->encoding);

      // Send it
      $this->cat && $this->cat->debug('>>>', $r->getRequestString());
      $response= $c->send($r);
    }

    // Read response
    $sc= $response->getStatusCode();
    $this->cat && $this->cat->debug('<<<', $response);
    $xml= '';
    while ($buf= $response->readData()) $xml.= $buf;
    $this->cat && $this->cat->debug('<<<', $xml);

    if ($answer= XPSoapMessage::fromString($xml)) {
      if (null !== ($content_type= $response->getHeader('Content-Type'))) {
        @list($type, $charset)= explode('; charset=', $content_type);
        if (!empty($charset)) $answer->setEncoding($charset);
      }
    }
    
    // Check for faults
    if (null !== ($fault= $answer->getFault())) {
      throw new SOAPFaultException($fault);
    }
    
    // Unmarshal response
    return $command->unmarshalFrom($answer->root()->nodeAt(0)->nodeAt(0));
  }

  /**
   * Set trace for debugging
   *
   * @param   util.log.LogCategory cat
   */
  public function setTrace($cat) {
    $this->cat= $cat;
  }
} 
