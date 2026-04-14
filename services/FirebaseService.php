<?php

class FirebaseService {
    protected $client;
    protected $auth;

    public function __construct($client, $auth = null) {
        $this->client = $client;
        $this->auth = $auth;
    }

    /**
     * Get a collection via REST
     */
    public function getAllDocuments($collectionName) {
        try {
            $data = [];
            $pageToken = null;

            do {
                $query = ['pageSize' => 1000];
                if ($pageToken) {
                    $query['pageToken'] = $pageToken;
                }

                $response = $this->client->get($collectionName, [
                    'query' => $query
                ]);
                
                $body = json_decode($response->getBody(), true);
                
                if (isset($body['documents'])) {
                    foreach ($body['documents'] as $doc) {
                        $parsed = $this->parseFirestoreDocument($doc);
                        $data[] = $parsed;
                    }
                }

                $pageToken = $body['nextPageToken'] ?? null;
            } while ($pageToken);

            return $data;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Save/Update a document via REST
     */
    public function saveDocument($collection, $uid, $data) {
        try {
            $fields = $this->prepareFirestoreFields($data);
            $body = ['fields' => $fields];
            
            // Using PATCH for Upsert behavior
            $response = $this->client->patch($collection . '/' . $uid, [
                'json' => $body
            ]);
            
            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            throw new Exception("Error saving document: " . $e->getMessage());
        }
    }

    /**
     * Delete a document via REST
     */
    public function deleteDocument($collection, $uid) {
        try {
            $this->client->delete($collection . '/' . $uid);
            return true;
        } catch (Exception $e) {
            throw new Exception("Error deleting document: " . $e->getMessage());
        }
    }

    /**
     * Convert PHP array to Firestore REST's strict JSON format
     */
    private function prepareFirestoreFields($data) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $this->convertToFirestoreValue($value);
        }
        return $fields;
    }

    private function convertToFirestoreValue($value) {
        if (is_string($value)) {
            return ['stringValue' => $value];
        } elseif (is_int($value)) {
            return ['integerValue' => (string)$value];
        } elseif (is_double($value)) {
            return ['doubleValue' => $value];
        } elseif (is_bool($value)) {
            return ['booleanValue' => $value];
        } elseif (is_array($value)) {
            // Check if associative array (map) or sequential array (array data)
            if (array_keys($value) !== range(0, count($value) - 1) && !empty($value)) {
                // Map
                $mapFields = [];
                foreach ($value as $k => $v) {
                    $mapFields[$k] = $this->convertToFirestoreValue($v);
                }
                return ['mapValue' => ['fields' => $mapFields]];
            } else {
                // Array
                $arrValues = [];
                foreach ($value as $v) {
                    $arrValues[] = $this->convertToFirestoreValue($v);
                }
                return ['arrayValue' => ['values' => $arrValues]];
            }
        } elseif (is_null($value)) {
            return ['nullValue' => null];
        }
        return ['stringValue' => (string)$value];
    }

    /**
     * Parse Firestore JSON to PHP array
     */
    private function parseFirestoreDocument($document) {
        $id = basename($document['name']);
        $fields = $document['fields'] ?? [];
        $data = ['id' => $id];
        
        foreach ($fields as $key => $value) {
            $data[$key] = $this->parseFirestoreValue($value);
        }
        return $data;
    }

    private function parseFirestoreValue($value) {
        if (isset($value['stringValue'])) return $value['stringValue'];
        if (isset($value['integerValue'])) return (int)$value['integerValue'];
        if (isset($value['doubleValue'])) return (float)$value['doubleValue'];
        if (isset($value['booleanValue'])) return (bool)$value['booleanValue'];
        if (isset($value['timestampValue'])) return $value['timestampValue'];
        if (isset($value['nullValue'])) return null;
        if (isset($value['mapValue']['fields'])) {
            $parsedMap = [];
            foreach ($value['mapValue']['fields'] as $k => $v) {
                $parsedMap[$k] = $this->parseFirestoreValue($v);
            }
            return $parsedMap;
        }
        if (isset($value['arrayValue']['values'])) {
            $parsedArr = [];
            foreach ($value['arrayValue']['values'] as $v) {
                $parsedArr[] = $this->parseFirestoreValue($v);
            }
            return $parsedArr;
        }
        if (array_key_exists('arrayValue', $value) && empty($value['arrayValue'])) return [];
        if (array_key_exists('mapValue', $value) && empty($value['mapValue'])) return [];
        return null;
    }
}
?>
