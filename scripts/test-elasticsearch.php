<?php
/**
 * Test script to verify Elasticsearch connectivity and indexing functionality
 */

require_once __DIR__ . '/../vendor/autoload.php';

class ElasticsearchTester
{
    private string $elasticsearchUrl;
    private string $indexName;

    public function __construct()
    {
        $this->elasticsearchUrl = $_ENV['ELASTICSEARCH_URL'] ?? 'http://elasticsearch:9200';
        $this->indexName = 'nostr-events-test';
    }

    private function log(string $message): void
    {
        $timestamp = date('c');
        echo "[{$timestamp}] {$message}" . PHP_EOL;
    }

    public function testConnection(): bool
    {
        $this->log("Testing Elasticsearch connection...");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->elasticsearchUrl . '/_cluster/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            $this->log("❌ Connection failed: {$error}");
            return false;
        }
        
        if ($httpCode !== 200) {
            $this->log("❌ HTTP error: {$httpCode}");
            return false;
        }
        
        $data = json_decode($response, true);
        if ($data && isset($data['status'])) {
            $this->log("✅ Elasticsearch connected successfully (status: {$data['status']})");
            return true;
        }
        
        $this->log("❌ Invalid response format");
        return false;
    }

    public function testIndexCreation(): bool
    {
        $this->log("Testing index creation...");
        
        $indexMapping = [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'pubkey' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'kind' => ['type' => 'integer'],
                    'content' => ['type' => 'text'],
                    'test_field' => ['type' => 'keyword']
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->elasticsearchUrl . '/' . $this->indexName);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($indexMapping));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            $this->log("❌ Index creation failed: curl error");
            return false;
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['acknowledged']) && $responseData['acknowledged']) {
            $this->log("✅ Index created successfully");
            return true;
        } elseif (isset($responseData['error']['type']) && 
                  strpos($responseData['error']['type'], 'resource_already_exists_exception') !== false) {
            $this->log("✅ Index already exists (expected)");
            return true;
        } else {
            $this->log("❌ Index creation failed: " . $response);
            return false;
        }
    }

    public function testDocumentIndexing(): bool
    {
        $this->log("Testing document indexing...");
        
        $testDoc = [
            'id' => 'test-' . time(),
            'pubkey' => 'test-pubkey',
            'created_at' => date('c'),
            'kind' => 1,
            'content' => 'This is a test document for Elasticsearch indexing',
            'test_field' => 'test-value'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->elasticsearchUrl . '/' . $this->indexName . '/_doc/' . $testDoc['id']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testDoc));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            $this->log("❌ Document indexing failed: curl error");
            return false;
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 || $httpCode === 201) {
            $this->log("✅ Document indexed successfully");
            return true;
        } else {
            $this->log("❌ Document indexing failed: " . $response);
            return false;
        }
    }

    public function testDocumentRetrieval(): bool
    {
        $this->log("Testing document retrieval...");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->elasticsearchUrl . '/' . $this->indexName . '/_search');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => ['match_all' => []]]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            $this->log("❌ Document retrieval failed: curl error");
            return false;
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['hits']['total']['value'])) {
            $count = $responseData['hits']['total']['value'];
            $this->log("✅ Document retrieval successful (found {$count} documents)");
            return true;
        } else {
            $this->log("❌ Document retrieval failed: " . $response);
            return false;
        }
    }

    public function cleanup(): void
    {
        $this->log("Cleaning up test index...");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->elasticsearchUrl . '/' . $this->indexName);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $this->log("✅ Test index cleaned up");
        } else {
            $this->log("⚠️  Test index cleanup failed (HTTP {$httpCode})");
        }
    }

    public function runAllTests(): bool
    {
        $this->log("Starting Elasticsearch integration tests...");
        
        $tests = [
            'Connection' => [$this, 'testConnection'],
            'Index Creation' => [$this, 'testIndexCreation'],
            'Document Indexing' => [$this, 'testDocumentIndexing'],
            'Document Retrieval' => [$this, 'testDocumentRetrieval']
        ];
        
        $allPassed = true;
        
        foreach ($tests as $testName => $testMethod) {
            $this->log("Running test: {$testName}");
            if (!$testMethod()) {
                $allPassed = false;
                $this->log("❌ Test failed: {$testName}");
                break;
            }
        }
        
        $this->cleanup();
        
        if ($allPassed) {
            $this->log("✅ All Elasticsearch tests passed!");
        } else {
            $this->log("❌ Some tests failed");
        }
        
        return $allPassed;
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new ElasticsearchTester();
    $success = $tester->runAllTests();
    exit($success ? 0 : 1);
}
