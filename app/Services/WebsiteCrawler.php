<?php

namespace App\Services;

use App\Exceptions\KnowledgeExtractionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class WebsiteCrawler
{
    public function __construct(private readonly UrlSafetyService $safety) {}

    public function crawl(string $url, bool $sitemap, int $pageLimit): string
    {
        $root = $this->safety->assertSafe($url);
        $pageLimit = max(1, min($pageLimit, (int) config('neuraldesk.knowledge.website.maximum_page_limit', 25)));
        $urls = $sitemap ? $this->sitemapUrls($root, $pageLimit) : [$root];
        $origin = $this->origin($root);
        $documents = [];

        foreach (array_slice(array_values(array_unique($urls)), 0, $pageLimit) as $pageUrl) {
            $pageUrl = $this->safety->assertSafe($pageUrl);
            if ($this->origin($pageUrl) !== $origin) {
                continue;
            }
            $response = $this->fetch($pageUrl);
            $type = strtolower((string) $response->header('Content-Type'));
            if (! str_contains($type, 'text/html') && ! str_contains($type, 'text/plain') && $type !== '') {
                continue;
            }
            $body = $response->body();
            $text = str_contains($type, 'html') || str_contains(strtolower($body), '<html')
                ? $this->htmlToText($body)
                : $body;
            if (trim($text) !== '') {
                $documents[] = $pageUrl."\n\n".$text;
            }
        }

        if ($documents === []) {
            throw new KnowledgeExtractionException('The website did not contain readable public text.');
        }

        return implode("\n\n---\n\n", $documents);
    }

    /** @return list<string> */
    private function sitemapUrls(string $url, int $limit): array
    {
        $response = $this->fetch($url);
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), null, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            throw new KnowledgeExtractionException('The sitemap XML could not be parsed.');
        }
        $locations = $xml->xpath('//*[local-name()="loc"]') ?: [];

        return array_slice(array_values(array_filter(array_map(
            fn ($location): string => trim((string) $location),
            $locations,
        ))), 0, $limit);
    }

    private function fetch(string $url): Response
    {
        $maxRedirects = (int) config('neuraldesk.knowledge.website.maximum_redirects', 3);
        for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
            $url = $this->safety->assertSafe($url);
            $options = ['allow_redirects' => false];
            if (defined('CURLOPT_RESOLVE')) {
                $host = (string) parse_url($url, PHP_URL_HOST);
                $port = (int) (parse_url($url, PHP_URL_PORT) ?: (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80));
                $address = $this->safety->publicAddresses($url)[0];
                $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]];
            }
            $response = Http::withOptions($options)
                ->connectTimeout((int) config('neuraldesk.knowledge.website.connect_timeout', 5))
                ->timeout((int) config('neuraldesk.knowledge.website.request_timeout', 15))
                ->withHeaders(['User-Agent' => 'NeuralDesk-KnowledgeBot/1.0'])
                ->get($url);
            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');
                if (! is_string($location) || $location === '' || $redirect === $maxRedirects) {
                    throw new KnowledgeExtractionException('The website redirected too many times.');
                }
                $url = $this->absoluteUrl($url, $location);

                continue;
            }
            if (! $response->successful()) {
                throw new KnowledgeExtractionException('The website could not be retrieved.');
            }
            $maxBytes = (int) config('neuraldesk.knowledge.website.maximum_response_bytes', 2097152);
            $contentLength = (int) $response->header('Content-Length');
            if ($contentLength > $maxBytes) {
                throw new KnowledgeExtractionException('The website response exceeded the allowed size.');
            }
            if (strlen($response->body()) > $maxBytes) {
                throw new KnowledgeExtractionException('The website response exceeded the allowed size.');
            }

            return $response;
        }

        throw new KnowledgeExtractionException('The website could not be retrieved safely.');
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|template)[^>]*>.*?</\1>#is', ' ', $html) ?? '';
        $html = preg_replace('#</(p|div|section|article|li|h[1-6]|br)>#i', "\n", $html) ?? '';

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME)) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin.'/'.ltrim($location, '/');
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').':'.($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80)));
    }
}
