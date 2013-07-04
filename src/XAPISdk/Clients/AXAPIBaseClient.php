<?php
/**
 * PHP Mosaico X API (XAPI) SDK
 * Innobit s.r.l.
 * web: http://www.innobit.it
 * mail: info@innobit.it
 */



namespace XAPISdk\Clients;

use Httpful\Request;
use XAPISdk\Configuration\XAPISdkConfiguration;
use XAPISdk\Data\BusinessObjects\IBusinessObject;
use XAPISdk\Net\HttpCodes;
use XAPISdk\Security\SecurityManager;
use XAPISdk\Util\StringUtil;

abstract class AXAPIBaseClient implements IXAPIClient {

    // region -- CONSTANTS --

    const HEADER_NAME__APIKEY    = 'X_APIKEY';
    const HEADER_NAME__TIMESTAMP = 'X_TIMESTAMP';
    const HEADER_NAME__SIGNATURE = 'X_SIGNATURE';

    const PARAM_QUERY__NAME = 'q';
    const PARAM_QUERY__FIELD_SEP = '|';
    const PARAM_QUERY__FILTER_SEP = ',';

    const CLASS_NAME = __CLASS__;

    // endregion

    // region -- MEMBERS --

    protected $_xapiSdkConf;

    // endregion

    // region -- GETTERS/SETTERS --

    public function getXapiSdkConfiguration() {
        return $this->_xapiSdkConf;
    }

    // endregion

    // region -- METHODS --

    public function __construct(XAPISdkConfiguration $conf) {
        $this->_xapiSdkConf = $conf;
    }

    protected abstract function getResourceName();

    protected abstract function getBusinessObjectClassName();

    public function get($id) {
        $jsonObj = $this->getAsJsonObj($id);

        $businessObjClassName = $this->getBusinessObjectClassName();

        return $businessObjClassName::fromJson($jsonObj, $this);
    }

    public function getAsJsonObj($id) {
        $this->logDebug('Called get on resource [' . $this->getResourceName() . ']');

        $request = $this->createGetRequestJson($this->getResourceName(), $id);

        $this->logDebug('Request created');

        $response = $request->send();

        $this->logDebug('Request sent, response [' . $response->raw_body . ']');

        if ($response->code != HttpCodes::OK) {
            $e = new ClientException('Cannot get resource [' . $this->getResourceName() . '] with id [' . $id . '], xapi response [' . $response->raw_body . ']');
            $this->logError('Error trying to get resource', $e);
            throw $e;
        }

        if (sizeof($response->body) === 0) {
            $e = new ClientException('Resource [' . $this->getResourceName() . '] with id [' . $id . '] not found!');
            $this->logError('Cannot find resource', $e);
            throw $e;
        }

        if (is_array($response->body))
            return $response->body[0];

        return $response->body;
    }

    public function update(IBusinessObject $obj) {
        // TODO : implement the update on objects! [PUT]
        throw new \Exception('Not implemented yet!');
    }

    public function add(IBusinessObject $obj) {
        $this->logDebug('Called add on resource [' . $this->getResourceName() . ']');

        $request = $this->createPostRequestJson($this->getResourceName(), $obj->toJson());

        $this->logDebug('Request created');

        $response = $request->send();

        $this->logDebug('Request sent, response [' . $response->raw_body . ']');

        if ($response->code != HttpCodes::CREATED) {
            $e = new ClientException('Cannot add resource [' . $this->getResourceName() . '], xapi response [' . $response->raw_body . ']');
            $this->logError('Error trying to add resource', $e);
            throw $e;
        }

        $businessObjClassName = $this->getBusinessObjectClassName();

        return $businessObjClassName::fromJson($response->body, $this);
    }

    public function delete($id) {
        // TODO : implement the update on objects! [DELETE]
        throw new \Exception('Not implemented yet!');
    }

    public function listAll(array $kvpFilter = null) {
        $this->logDebug('Called listAll on resource [' . $this->getResourceName() . ']');

        $request = $this->createGetRequestJson($this->getResourceName(), null, $kvpFilter);

        $this->logDebug('Request created');

        $response = $request->send();

        $this->logDebug('Request sent, response [' . $response->raw_body . ']');

        if ($response->code != HttpCodes::OK) {
            $e = new ClientException('Cannot list resource[' . $this->getResourceName() . '], xapi response [' . $response->raw_body . ']');
            $this->logError('Error trying to list resource', $e);
            throw $e;
        }

        $res = array();
        $businessObjClassName = $this->getBusinessObjectClassName();

        foreach($response->body as $obj) {
            $res[] = $businessObjClassName::fromJson($obj, $this);
        }

        return $res;
    }

    protected function createPostRequestJson($resourceName, $jsonPostData) {
        $resourcePath = $this->calculateResourcePath($resourceName);
        $uri = $this->calculateUriForResourcePath($resourcePath);

        $request = Request::post($uri);

        $this->addAuthenticationHeadersToRequest($request, $resourcePath);

        $request->sendsJson()->body($jsonPostData);

        return $request;
    }

    protected function createGetRequestJson($resourceName, $resourceId = null, array $kvpFilter = null) {
        $resourcePath = $this->calculateResourcePath($resourceName, $resourceId);
        $uri = $this->calculateUriForResourcePath($resourcePath, $kvpFilter);

        $getRequest = Request::get($uri);

        $this->addAuthenticationHeadersToRequest($getRequest, $resourcePath);

        $getRequest->expectsJson();

        return $getRequest;
    }

    protected function addAuthenticationHeadersToRequest(\Httpful\Request &$request, $resourcePath) {
        $sm = new SecurityManager();

        $timestamp = new \DateTime();
        $timestamp = $timestamp->format('YmdHis');

        $xapiPublicKey = $this->_xapiSdkConf->getXapiPublicKey();
        $xapiPrivateKey = $this->_xapiSdkConf->getXapiPrivateKey();

        $hashSignature = $sm->calculateSignatureForRequest($resourcePath,
            $xapiPublicKey,
            $xapiPrivateKey,
            $timestamp);

        $request->addHeader(self::HEADER_NAME__APIKEY, $xapiPublicKey);
        $request->addHeader(self::HEADER_NAME__TIMESTAMP, $timestamp);
        $request->addHeader(self::HEADER_NAME__SIGNATURE, $hashSignature);
    }

    protected function calculateUriForResourcePath($resourcePath, array $kvpFilter = null) {
        $glue = '';

        $xapiUri = $this->_xapiSdkConf->getXapiUri();

        if (!StringUtil::endsWith($xapiUri, '/'))
            $glue = '/';

        $uri = $xapiUri . $glue . $resourcePath;

        if (!empty($kvpFilter))
            $uri .= '?' . $this->calculateQueryParamForKvpFilter($kvpFilter);

        return $uri;
    }

    protected function calculateQueryParamForKvpFilter(array $kvpFilter) {
        $res = self::PARAM_QUERY__NAME . '=';
        $sep = '';

        foreach($kvpFilter as $k => $v) {
            $res .= $sep . $k . self::PARAM_QUERY__FIELD_SEP . $v;
            $sep = self::PARAM_QUERY__FILTER_SEP;
        }

        return $res;
    }

    protected function calculateResourcePath($resourceName, $resourceId = null) {
        return $resourceName . '/' . $resourceId;
    }

    protected function logDebug($message) {
        if (!$this->isLogEnabled())
            return;

        $logger = $this->_xapiSdkConf->getLogger();

        $logger->debug($message);
    }

    protected function logInfo($message) {
        if (!$this->isLogEnabled())
            return;

        $logger = $this->_xapiSdkConf->getLogger();

        $logger->info($message);
    }

    protected function logError($message, \Exception $e) {
        if (!$this->isLogEnabled())
            return;

        $logger = $this->_xapiSdkConf->getLogger();

        $logger->error($message, $e);
    }

    protected function isLogEnabled() {
        return $this->_xapiSdkConf->getLogger() != null;
    }

    // endregion

}