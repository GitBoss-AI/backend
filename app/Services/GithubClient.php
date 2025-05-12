<?php
namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Logger\Logger;

class GithubClient {
    private $client;
    private $githubUrl;
    private $githubToken;
    private $logFile = 'githubclient';

    public function __construct() {
        $this->githubUrl = $_ENV['GITHUB_API_URL'];
        $this->githubToken = $_ENV['GITHUB_TOKEN'];

        $this->client = new Client([
            'base_uri' => $this->githubUrl,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $this->githubToken,
                'X-GitHub-Api-Version' => '2022-11-28'
            ]
        ]);
    }

    public function get(string $endpoint, array $query = []): array {
        Logger::info($this->logFile, "GET $endpoint with query: " . json_encode($query));
        $query['since'] = date('c', strtotime('-1 day'));

        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    public function getPaginated(string $endpoint, array $query = []): array {
        Logger::info($this->logFile, "GET paginated $endpoint with query: " . json_encode($query));
        $results = [];
        $page = 1;
        $maxPages = 30;
        
        try {
            do {
                $query['page'] = $page;
                $query['per_page'] = $query['per_page'] ?? 100;

                $response = $this->client->request('GET', $endpoint, ['query' => $query]);
                $data = json_decode($response->getBody(), true);
                $results = array_merge($results, $data);

                Logger::info($this->logFile, "Fetched page $page of $endpoint, count=" . count($data));

                $linkHeader = $response->getHeaderLine('Link');
                $hasNextPage = str_contains($linkHeader, 'rel="next"');
                $page++;

            } while ($hasNextPage && $page <= $maxPages);

            return $results;

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $msg = 'GitHub API request failed.';
            if ($response) {
                $body = (string) $response->getBody();
                if (!empty($body)) {
                    $msg = "GitHub API error: $body";
                }
            } else {
                $msg = "GitHub API exception: " . $e->getMessage();
            }

            Logger::error($this->logFile, "Paginated GET $endpoint failed: $msg");
            throw new \Exception($msg);
        }
    }


    private function request(string $method, string $endpoint, array $options = []) {
        try {
            $response = $this->client->request($method, $endpoint, $options);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $msg = 'GitHub API request failed.';

            $response = $e->getResponse();
            if ($response) {
                $body = (string) $response->getBody();
                if (!empty($body)) {
                    $msg = "GitHub API error: $body";
                }
            } else {
                $msg = "GitHub API exception: " . $e->getMessage();
            }

            Logger::error($this->logFile, "Request to $endpoint failed: $msg");
            throw new \Exception($msg);
        }
    }
}
