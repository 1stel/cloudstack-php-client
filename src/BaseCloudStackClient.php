<?php

namespace Cloudstack;

/*
 * This file is part of the CloudStack PHP Client.
 *
 * (c) Quentin Pleplé <quentin.pleple@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class BaseCloudStackClient {
    public $apiKey;
    public $secretKey;
    public $endpoint;
    protected $lastUrl;
    
    public function __construct($endpoint, $apiKey = null, $secretKey = null) {
        // API endpoint
        if (empty($endpoint)) {
            throw new CloudStackClientException(ENDPOINT_EMPTY_MSG, ENDPOINT_EMPTY);
        }
        
        if (!preg_match('/^(http|https):\/\/.*$/', $endpoint)) {
            throw new CloudStackClientException(sprintf(ENDPOINT_NOT_URL_MSG, $endpoint), ENDPOINT_NOT_URL);
        }
        
        // $endpoint does not ends with a "/"
        $this->endpoint = substr($endpoint, -1) == '/' ? substr($endpoint, 0, -1) : $endpoint;

        // API key
        if (!empty($apiKey)) {
            $this->apiKey = $apiKey;
        }

        // API secret
        if (!empty($secretKey)) {
            $this->secretKey = $secretKey;
        }
    }

    public function setKeys($apiKey, $secretKey)
    {
        // Set access keys
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
    }
    
    public function getSignature($queryString) {
        if (empty($queryString)) {
            throw new CloudStackClientException(STRTOSIGN_EMPTY_MSG, STRTOSIGN_EMPTY);
        }
        
        $hash = @hash_hmac('SHA1', $queryString, $this->secretKey, true);
        $base64encoded = base64_encode($hash);
        return urlencode($base64encoded);
    }

    public function request($command, $args = array()) {
        if (empty($command)) {
            throw new CloudStackClientException(NO_COMMAND_MSG, NO_COMMAND);
        }
        
        if (!is_array($args)) {
            throw new CloudStackClientException(sprintf(WRONG_REQUEST_ARGS_MSG, $args), WRONG_REQUEST_ARGS);
        }
        
        foreach ($args as $key => $value) {
            if (strlen($value) == 0) {
                unset($args[$key]);
            }
        }
        
        // Building the query
        $args['apikey'] = $this->apiKey;
        $args['command'] = $command;
        $args['response'] = 'json';
        ksort($args);
        $query = http_build_query($args);
        $query = str_replace('+', '%20', $query);
        $query .= '&signature=' . $this->getSignature(strtolower($query));
    
        $url = $this->endpoint . "?" . $query;
        $this->lastUrl = $url; // Cache the last url in case you want to see it

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (empty($data)) {
            throw new CloudStackClientException(NO_DATA_RECEIVED_MSG, NO_DATA_RECEIVED);
        }

        $result = @json_decode($data);
        if (empty($result)) {
            throw new CloudStackClientException(NO_VALID_JSON_RECEIVED_MSG, NO_VALID_JSON_RECEIVED);
        }
        
        if ($command === 'restoreVirtualMachine') {
            $propertyResponse = 'restorevmresponse';
        } else {
            $propertyResponse = strtolower($command) . 'response';
        }
        
        if (!property_exists($result, $propertyResponse)) {
            if (property_exists($result, 'errorresponse') && property_exists($result->errorresponse, 'errortext')) {
                throw new CloudStackClientException($result->errorresponse->errortext);
            } else {
                throw new CloudStackClientException(sprintf('Unable to parse the response. Got code %d and message: %s', $code, $data));
            }
        }
        
        $response = $result->{$propertyResponse};
        
        // list handling : most of lists are on the same pattern as listVirtualMachines :
        // { "listvirtualmachinesresponse" : { "virtualmachine" : [ ... ] } }
        preg_match('/list(\w+)s/', strtolower($command), $listMatches);
        if (!empty($listMatches)) {
            $objectName = $listMatches[1];
            if (property_exists($response, $objectName)) {
                $resultArray = $response->{$objectName};
                if (is_array($resultArray)) {
                    return $resultArray;
                }
            } else {
                // sometimes, the 's' is kept, as in :
                // { "listasyncjobsresponse" : { "asyncjobs" : [ ... ] } }
                $objectName = $listMatches[1] . "s";
                if (property_exists($response, $objectName)) {
                    $resultArray = $response->{$objectName};
                    if (is_array($resultArray)) {
                        return $resultArray;
                    }
                }
            }
        }
        
        return $response;
    }

    public function getLastUrl() {
        return isset($this->lastUrl) ? $this->lastUrl : false;
    }
}
