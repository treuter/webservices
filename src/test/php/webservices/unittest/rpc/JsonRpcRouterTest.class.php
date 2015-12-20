<?php namespace webservices\unittest\rpc;

use scriptlet\ScriptletException;
use webservices\json\JsonFactory;
use webservices\unittest\rpc\mock\JsonRpcRouterMock;

/**
 * Test case for JsonRpcRpcRouter
 *
 * @see  xp://webservices.json.rpc.JsonRpcRouter
 */
class JsonRpcRouterTest extends MockedRpcRouterTest {
  protected $router = null;
    
  /**
   * Setup test fixture
   */
  public function setUp() {
    $this->router= new JsonRpcRouterMock('webservices.unittest.rpc.impl');
    $this->router->setMockMethod(\peer\http\HttpConstants::POST);
    $this->router->setMockData('{ "method" : "DummyRpcImplementation.getImplementationName", "params" : [ ], "id" : 1 }');
  }
  
  #[@test, @ignore('Process missing')]
  public function basicPostRequest() {
    $this->router->init();
    $response= $this->router->process();
    
    $this->assertEquals(200, $response->statusCode);
    $this->assertEquals(
      '{ "result" : "webservices.unittest.rpc.impl.DummyRpcImplementationHandler" , "error" : null , "id" : 1 }',
      $response->getContent()
    );
    $this->assertHasHeader($response->headers, 'Content-type: application/json; charset=utf-8');
  }
  
  #[@test, @ignore('Process missing')]
  public function basicEchoTest() {
    $this->router->setMockData('{ "method" : "DummyRpcImplementation.passBackMethod", "params" : [ "string" , 1 , { "object" : "object" } , [ 1, 2, 3, 4, 5 ] ] , "id" : 1 }');
    $this->router->init();
    $response= $this->router->process();
    
    $this->assertEquals(200, $response->statusCode);
    $str= $response->getContent();
    
    $decoder= JsonFactory::create();
    $data= $decoder->decode($str);
  }    

  #[@test, @ignore('Process missing'), @expect(ScriptletException::class)]
  public function basicGetRequest() {
    $this->router->setMockMethod(\peer\http\HttpConstants::GET);
    $this->router->init();
    $response= $this->router->process();
  }
  
  #[@test, @ignore('Process missing')]
  public function callNonexistingClass() {
    $this->router->setMockData('{ "method" : "ClassDoesNotExist.getImplementationName", "params" : [ ], "id" : 1 }');
    $this->router->init();
    $response= $this->router->process();
    
    $this->assertEquals(500, $response->statusCode);
  }
  
  #[@test, @ignore('Process missing')]
  public function callNonexistingMethod() {
    $this->router->setMockData('{ "method" : "DummyRpcImplementation.methodDoesNotExist", "params" : [ ], "id" : 1 }');
    $this->router->init();
    $response= $this->router->process();
    
    $this->assertEquals(500, $response->statusCode);
  }

  #[@test, @ignore('Process missing')]
  public function callNonWebmethodMethod() {
    $this->router->setMockData('{ "method" : "DummyRpcImplementation.methodExistsButIsNotAWebmethod", "params" : [ ], "id" : 1 }');
    $this->router->init();
    $response= $this->router->process();
    
    $this->assertEquals(500, $response->statusCode);
  }

  #[@test, @ignore('Process missing')]
  public function callFailingMethod() {
    $this->router->setMockData('{ "method" : "DummyRpcImplementation.giveMeFault", "params" : [ ], "id" : 1 }');
    
    $this->router->init();
    $response= $this->router->process();
    $this->assertEquals(500, $response->statusCode);

    // Check for correct fault code
    $message= \webservices\json\rpc\JsonResponseMessage::fromString($response->getContent());
    $fault= $message->getFault();
    $this->assertEquals(403, $fault->getFaultcode());
  }
  
  
  #[@test, @ignore('Not forward compatible w/ unicode branch')]
  public function multipleParameters() {
    $this->router->setMockData('{ "method" : "DummyRpcImplementation.checkMultipleParameters", "params" : [ "Lalala", 1, [ 12, "Egypt", false, -31 ], { "lowerBound" : 18, "upperBound" : 139 } ], "id" : 12 }');
    $this->router->init();
    $response= $this->router->process();

    $this->assertHasHeader($response->headers, 'Content-type: application/json; charset=utf-8');
    $this->assertEquals(200, $response->statusCode);
    
    $msg= \webservices\json\rpc\JsonResponseMessage::fromString($response->getContent());
    $data= $msg->getData();
    $this->assertEquals('Lalala', (string)$data[0]);
    $this->assertEquals(1, $data[1]);
    $this->assertEquals([12, 'Egypt', false, -31], $data[2]);
    $this->assertEquals(['lowerBound' => 18, 'upperBound' => 139], $data[3]);
  }
}
