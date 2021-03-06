<?php namespace webservices\xmlrpc\rpc;
 
use webservices\rpc\AbstractRpcResponse;
use peer\http\HttpConstants;


/**
 * Wraps XML-RPC response
 *
 * @see scriptlet.HttpScriptletResponse  
 */
class XmlRpcResponse extends AbstractRpcResponse {
  
  /**
   * Make sure a fault is passed as "500 Internal Server Error"
   *
   * @see     scriptlet.HttpScriptletResponse#process
   */
  public function process() {
    if (!$this->message) return;

    if (null !== $this->message->getFault()) {
      $this->setStatus(HttpConstants::STATUS_INTERNAL_SERVER_ERROR);
    }
    $this->content= $this->message->serializeData();
    $this->cat && $this->cat->debug('>>> ', $this->content);
  }
}
