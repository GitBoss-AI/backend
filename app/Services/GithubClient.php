<?php
namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GithubClient {
    private $client;
    private $githubUrl;
    private $githubToken;

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
        if (!isset($query['since']) && str_contains($endpoint, '/commits')) {
            $query['since'] = date('c', strtotime('-1 day'));
        }
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    public function getPaginated(string $endpoint, array $query = []): array {
        $results = [];
        $page = 1;

        if (!isset($query['since']) && str_contains($endpoint, '/commits')) {
            $query['since'] = date('c', strtotime('-1 day'));
        }

        do {
            $query['page'] = $page;
            $query['per_page'] = $query['per_page'] ?? 100;

            $response = $this->client->request('GET', $endpoint, ['query' => $query]);
            $data = json_decode($response->getBody(), true);
            $results = array_merge($results, $data);

            $linkHeader = $response->getHeaderLine('Link');
            $hasNextPage = str_contains($linkHeader, 'rel="next"');

            $page++;
        } while ($hasNextPage);

        return $results;
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
            throw new \Exception($msg);
        }
    }
}
