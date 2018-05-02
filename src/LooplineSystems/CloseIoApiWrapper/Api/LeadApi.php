<?php
/**
 * Close.io Api Wrapper - LLS Internet GmbH - Loopline Systems
 *
 * @link      https://github.com/loopline-systems/closeio-api-wrapper for the canonical source repository
 * @copyright Copyright (c) 2014 LLS Internet GmbH - Loopline Systems (http://www.loopline-systems.com)
 * @license   https://github.com/loopline-systems/closeio-api-wrapper/blob/master/LICENSE (MIT Licence)
 */

namespace LooplineSystems\CloseIoApiWrapper\Api;

use LooplineSystems\CloseIoApiWrapper\CloseIoResponse;
use LooplineSystems\CloseIoApiWrapper\Library\Api\AbstractApi;
use LooplineSystems\CloseIoApiWrapper\Library\Curl\Curl;
use LooplineSystems\CloseIoApiWrapper\Library\Exception\InvalidNewLeadPropertyException;
use LooplineSystems\CloseIoApiWrapper\Library\Exception\InvalidParamException;
use LooplineSystems\CloseIoApiWrapper\Library\Exception\ResourceNotFoundException;
use LooplineSystems\CloseIoApiWrapper\Library\Exception\UrlNotSetException;
use LooplineSystems\CloseIoApiWrapper\Model\Lead;

class LeadApi extends AbstractApi
{
    const NAME = 'LeadApi';

    /**
     * {@inheritdoc}
     */
    protected function initUrls()
    {
        $this->urls = [
            'get-leads' => '/lead/',
            'add-lead' => '/lead/',
            'get-lead' => '/lead/[:id]/',
            'update-lead' => '/lead/[:id]/',
            'delete-lead' => '/lead/[:id]/',
            'merge-leads' => '/lead/merge/',
        ];
    }

    /**
     * @param null|int $limit
     * @param null|int $skip
     * @param null|array|string $query
     *
     * @return Lead[]
     *
     * @throws InvalidParamException
     * @throws UrlNotSetException
     */
    public function getAllLeads($limit = null, $skip = null, $query = null)
    {
        /** @var Lead[] $leads */
        $leads = [];

        $filters = [];
        if (!empty($limit)) {
            $filters['_limit'] = $limit;
        }

        if (!empty($skip)) {
            $filters['_skip'] = $skip;
        }

        if (!empty($query)) {
            $filters['query'] = $query;
        }

        $apiRequest = $this->prepareRequest('get-leads', null, [], $filters);

        $result = $this->triggerGet($apiRequest);

        if ($result->getReturnCode() == 200) {
            $rawData = $result->getData()[CloseIoResponse::GET_RESPONSE_DATA_KEY];

            foreach ($rawData as $lead) {
                $leads[] = new Lead($lead);
            }
        }

        return $leads;
    }

    /**
     * @param array $queryParams
     *
     * @return Lead[]
     *
     * @throws InvalidParamException
     * @throws UrlNotSetException
     */
    public function findLeads(array $queryParams)
    {
        /** @var Lead[] $leads */
        $leads = [];
        if (count($queryParams) > 0) {
            $queryParams = ['query' => $this->buildQueryString($queryParams)];
        }
        $apiRequest = $this->prepareRequest('get-leads', '', [], $queryParams);

        $result = $this->triggerGet($apiRequest);
        if ($result->getReturnCode() == 200) {
            $rawData = $result->getData()['data'];
            foreach ($rawData as $lead) {
                $leads[] = new Lead($lead);
            }
        }

        return $leads;
    }

    /**
     * @param string $id
     *
     * @return Lead
     *
     * @throws InvalidParamException
     * @throws UrlNotSetException
     */
    public function getLead($id)
    {
        $apiRequest = $this->prepareRequest('get-lead', null, ['id' => $id]);

        $result = $this->triggerGet($apiRequest);

        return new Lead($result->getData());
    }

    /**
     * @param Lead $lead
     *
     * @return Lead
     *
     * @throws InvalidParamException
     * @throws UrlNotSetException
     * @throws InvalidNewLeadPropertyException
     */
    public function addLead(Lead $lead)
    {
        $this->validateLeadForPost($lead);

        $lead = json_encode($lead);
        $apiRequest = $this->prepareRequest('add-lead', $lead);

        return new Lead($this->triggerPost($apiRequest)->getData());
    }

    /**
     * @param Lead $lead
     *
     * @return Lead
     *
     * @throws InvalidParamException
     * @throws UrlNotSetException
     */
    public function updateLead(Lead $lead)
    {
        // check if lead has id
        if ($lead->getId() == null) {
            throw new InvalidParamException('When updating a lead you must provide the lead ID');
        }
        // remove id from lead since it won't be part of the patch data
        $id = $lead->getId();
        $lead->setId(null);

        $lead = json_encode($lead);
        $apiRequest = $this->prepareRequest('update-lead', $lead, ['id' => $id]);
        $response = $this->triggerPut($apiRequest);

        return new Lead($response->getData());
    }

    /**
     * @param string $id
     *
     * @throws InvalidParamException
     * @throws UrlNotSetException
     */
    public function deleteLead($id)
    {
        $apiRequest = $this->prepareRequest('delete-lead', null, ['id' => $id]);

        $this->triggerDelete($apiRequest);
    }

    /**
     * @param Curl $curl
     */
    public function setCurl($curl)
    {
        $this->curl = $curl;
    }

    /**
     * @param Lead $lead
     *
     * @throws InvalidNewLeadPropertyException
     */
    public function validateLeadForPost(Lead $lead)
    {
        $invalidProperties = ['id', 'organization', 'tasks', 'opportunities'];
        foreach ($invalidProperties as $invalidProperty) {
            $getter = 'get' . ucfirst($invalidProperty);
            if ($lead->$getter()) {
                throw new InvalidNewLeadPropertyException('Cannot post ' . $invalidProperty . ' to new lead.');
            }
        }
    }

    /**
     * @param array $params
     *
     * @return string
     */
    private function buildQueryString(array $params)
    {
        $flattened = [];
        foreach ($params as $key => $value) {
            $flattened[] = $key . '=' . $value;
        }
        $queryString = implode('&', $flattened);

        return $queryString;
    }

    /**
     * @param Lead $source the lead to be merged (and deleted)
     * @param Lead $destination the lead to merge the $source with
     *
     * @return CloseIoResponse
     *
     * @throws InvalidParamException in case of invalid lead IDs
     * @throws ResourceNotFoundException in case of merge fail
     * @throws UrlNotSetException
     */
    public function mergeLeads(Lead $source, Lead $destination)
    {
        if (empty($source->getId()) or empty($destination->getId())) {
            throw new InvalidParamException('You need to specify two already existing leads in order to merge them');
        }
        $apiRequest = $this->prepareRequest('merge-leads', json_encode([
            'destination' => $destination->getId(),
            'source' => $source->getId(),
        ]));
        $result = $this->triggerPost($apiRequest);
        if ($result->getReturnCode() !== 200) {
            throw new ResourceNotFoundException();
        }
        return $result;
    }
}
