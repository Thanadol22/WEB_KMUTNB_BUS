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
            if (is_string($value)) {
                $fields[$key] = ['stringValue' => $value];
            } elseif (is_int($value)) {
                $fields[$key] = ['integerValue' => (string)$value];
            } elseif (is_bool($value)) {
                $fields[$key] = ['booleanValue' => $value];
            } elseif (is_double($value)) {
                $fields[$key] = ['doubleValue' => $value];
            }
            // Add more types if needed
        }
        return $fields;
    }

    /**
     * Parse Firestore JSON to PHP array
     */
    private function parseFirestoreDocument($document) {
        $id = basename($document['name']);
        $fields = $document['fields'] ?? [];
        $data = ['id' => $id];
        
        foreach ($fields as $key => $value) {
            if (isset($value['stringValue'])) $data[$key] = $value['stringValue'];
            elseif (isset($value['integerValue'])) $data[$key] = (int)$value['integerValue'];
            elseif (isset($value['doubleValue'])) $data[$key] = (float)$value['doubleValue'];
            elseif (isset($value['booleanValue'])) $data[$key] = (bool)$value['booleanValue'];
            elseif (isset($value['timestampValue'])) $data[$key] = $value['timestampValue'];
        }
        return $data;
    }
}
?>
