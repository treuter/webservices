<?php namespace webservices\unittest\rpc;

use scriptlet\ScriptletException;
use webservices\soap\xp\XPSoapMessage;
use webservices\unittest\rpc\mock\SoapRpcRouterMock;

/**
 * Test case for SoapRpcRouter
 *
 * @see      xp://webservices.soap.rpc.SoapRpcRouter
 */
class SoapRpcRouterTest extends MockedRpcRouterTest {
  protected
    $router = null;

  /**
   * Setup test fixture
   *
   */
  public function setUp() {
    $this->router= new SoapRpcRouterMock('webservices.unittest.rpc.impl');
    $this->router->setMockMethod(\peer\http\HttpConstants::POST);
    $this->router->setMockHeaders([
      'SOAPAction'    => 'DummyRpcImplementation#getImplementationName',
      'Content-Type'  => 'text/xml; charset=utf-8'
    ]);
    $this->router->setMockData('<?xml version="1.0" encoding="utf-8"?>
      <SOAP-ENV:Envelope
       xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
       xmlns:xsd="http://www.w3.org/2001/XMLSchema"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
       xmlns:si="http://soapinterop.org/xsd"
       SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
       xmlns:ctl="DummyRpcImplementation"
      >
        <SOAP-ENV:Body>  
          <ctl:foo/>
        </SOAP-ENV:Body>
      </SOAP-ENV:Envelope>
    ');
  }
  
  #[@test, @ignore('Process missing')]
  public function basicPostRequest() {
    $this->router->init();
    $response= $this->router->process();
    $this->assertEquals(200, $response->statusCode);
    $this->assertHasHeader($response->headers, 'Content-type: text/xml');
  }

  #[@test, @ignore('Process missing'), @expect(ScriptletException::class)]
  public function basicGetRequest() {
    $this->router->setMockMethod(\peer\http\HttpConstants::GET);
    $this->router->init();
    $response= $this->router->process();
  }
  
  #[@test, @ignore('Process missing')]
  public function callNonexistingClass() {
    $this->router->setMockHeaders([
      'SOAPAction'    => 'NonExistingClass#getImplementationName',
      'Content-Type'  => 'text/xml; charset=utf-8'
    ]);
    
    $this->router->init();
    $response= $this->router->process();
    
    $this->assertEquals(500, $response->statusCode);
  }
  
  #[@test, @ignore('Process missing')]
  public function callNonexistingMethod() {
    $this->router->setMockHeaders([
      'SOAPAction'    => 'DummyRpcImplementation#nonExistingMethod',
      'Content-Type'  => 'text/xml; charset=utf-8'
    ]);
    $this->router->init();
    $response= $this->router->process();
    
    $this->assertEquals(500, $response->statusCode);
  }

  #[@test, @ignore('Process missing')]
  public function callNonWebmethodMethod() {
    $this->router->setMockHeaders([
      'SOAPAction'    => 'DummyRpcImplementation#methodExistsButIsNotAWebmethod',
      'Content-Type'  => 'text/xml; charset=utf-8'
    ]);
    $this->router->init();
    $response= $this->router->process();
    
    $this->assertEquals(500, $response->statusCode);
  }

  #[@test, @ignore('Process missing')]
  public function callFailingMethod() {
    $this->router->setMockHeaders([
      'SOAPAction'    => 'DummyRpcImplementation#giveMeFault',
      'Content-Type'  => 'text/xml; charset=utf-8'
    ]);
    $this->router->init();
    $response= $this->router->process();
    $this->assertEquals(500, $response->statusCode);
    
    $message= XPSoapMessage::fromString($response->getContent());
    $fault= $message->getFault();
    $this->assertEquals(403, $fault->getFaultCode());
    $this->assertEquals('This is a intentionally caused exception.', $fault->getFaultString());
  }

  #[@test, @ignore('Process missing')]
  public function multipleParameters() {
    $this->router->setMockHeaders([
      'SOAPAction'    => 'DummyRpcImplementation#checkMultipleParameters',
      'Content-Type'  => 'text/xml; charset=utf-8'
    ]);
    $this->router->setMockData('<?xml version="1.0" encoding="utf-8"?>
<SOAP-ENV:Envelope
 xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
 xmlns:xsd="http://www.w3.org/2001/XMLSchema"
 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
 xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
 xmlns:si="http://soapinterop.org/xsd"
 SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
 xmlns:ctl="DummyRpcImplementation"
>
<SOAP-ENV:Body>  
  <ctl:checkMultipleParameters>    
    <item xsi:type="xsd:string">Lalala</item>
    <item xsi:type="xsd:int">1</item>
    <item xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="xsd:anyType[4]">        
      <item xsi:type="xsd:int">12</item>
      <item xsi:type="xsd:string">Egypt</item>
      <item xsi:type="xsd:boolean">false</item>
      <item xsi:type="xsd:int">-31</item>
    </item>
    <item xsi:type="xsd:struct">        
      <lowerBound xsi:type="xsd:int">18</lowerBound>
      <upperBound xsi:type="xsd:int">139</upperBound>
    </item>
  </ctl:checkMultipleParameters>
</SOAP-ENV:Body>
</SOAP-ENV:Envelope>
    ');
    $this->router->init();
    $response= $this->router->process();

    $this->assertEquals(200, $response->statusCode);

    $msg= XPSoapMessage::fromString($response->getContent());
    $data= current($msg->getData());
    
    $this->assertEquals('Lalala', $data[0]) &&
    $this->assertEquals(1, $data[1]) &&
    $this->assertEquals([12, 'Egypt', false, -31], $data[2]) &&
    $this->assertEquals(['lowerBound' => 18, 'upperBound' => 139], $data[3]);
  }

  #[@test, @ignore('Process missing')]
  public function handleIso88591Message() {
    $this->router->setMockHeaders([
      'Host'          => 'outage.xp-framework.net',
      'Connection'    => 'Keep-Alive',
      'Content-Type'  => 'text/xml; charset=utf-8',
      'SOAPAction'    => 'DummyRpcImplementation#checkUTF8Content',
      'User-Agent'    => 'PHP SOAP 0.1'
    ]);
    $this->router->setMockData('<?xml version="1.0" encoding="utf-8"?>
      <SOAP-ENV:Envelope 
       xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" 
       xmlns:ns1="urn:Outage" 
       xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
       xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" 
       SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
        <SOAP-ENV:Body>
          <ns1:createDSLOutageStringDate>
            <description xsi:type="xsd:string">Störung in Düsseldorf</description>
          </ns1:createDSLOutageStringDate>
        </SOAP-ENV:Body>
      </SOAP-ENV:Envelope>
    ');
    
    $this->router->init();
    $response= $this->router->process();

    $this->assertEquals(200, $response->statusCode, \xp::stringOf($response->message));
    $this->assertHasHeader($response->headers, 'Content-type: text/xml');
  }
  

  #[@test, @ignore('Process missing')]
  public function handleUTF8Message() {
    $this->router->setMockHeaders([
      'Host'          => 'outage.xp-framework.net',
      'Connection'    => 'Keep-Alive',
      'Content-Type'  => 'text/xml; charset=utf-8',
      'SOAPAction'    => 'DummyRpcImplementation#checkUTF8Content',
      'User-Agent'    => 'PHP SOAP 0.1'
    ]);
    $this->router->setMockData('<?xml version="1.0" encoding="UTF-8"?>
      <SOAP-ENV:Envelope 
       xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" 
       xmlns:ns1="urn:Outage" 
       xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
       xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" 
       SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
        <SOAP-ENV:Body>
          <ns1:createDSLOutageStringDate>
            <description xsi:type="xsd:string">Störung in Düsseldorf</description>
          </ns1:createDSLOutageStringDate>
        </SOAP-ENV:Body>
      </SOAP-ENV:Envelope>
    ');
    
    $this->router->init();
    $response= $this->router->process();
    
    $this->assertEquals(200, $response->statusCode, \xp::stringOf($response->message));
    
    // $this->assertHasHeader($response->headers, 'Content-type: text/xml; charset=utf-8');
    $this->assertStringContained(
      new \lang\types\String('Störung in Düsseldorf', 'utf-8'),
      new \lang\types\String($response->getContent(), 'utf-8')
    );
  }
}
