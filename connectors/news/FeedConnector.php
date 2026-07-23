<?php
declare(strict_types=1);

require_once __DIR__ . '/ConnectorInterface.php';
require_once __DIR__ . '/../../classes/News/Support.php';
require_once __DIR__ . '/../../classes/News/UrlGuard.php';

final class FeedConnector implements NewsConnectorInterface
{
    public function __construct(private int $timeoutSeconds = 12, private int $maxBytes = 5242880) {}

    public function fetch(array $source): array
    {
        $url = trim((string) ($source['feed_url'] ?? ''));
        NewsUrlGuard::assertPublicHttpUrl($url);
        $xml = $this->download($url);
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$document) throw new RuntimeException('The source returned invalid RSS or Atom XML.');

        $isRss = isset($document->channel);
        $items = $isRss ? $document->channel->item : $document->children('http://www.w3.org/2005/Atom')->entry;
        $normalized = [];
        foreach ($items as $item) {
            $entry = $isRss ? $this->rssItem($item) : $this->atomItem($item);
            if ($entry['headline'] === '' || $entry['source_url'] === '') continue;
            $normalized[] = $entry;
        }
        return $normalized;
    }

    private function download(string $url): string
    {
        if (function_exists('curl_init')) {
            $body = '';
            for ($redirects = 0; $redirects <= 3; $redirects++) {
                NewsUrlGuard::assertPublicHttpUrl($url); $headers=[];
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => $this->timeoutSeconds, CURLOPT_MAXREDIRS => 0, CURLOPT_USERAGENT => 'NormanAndCompanyNewsAggregator/1.0',
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_HEADERFUNCTION=>static function($handle,string $line)use(&$headers):int{$length=strlen($line);$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return $length;}]);
                $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
                if (in_array($status,[301,302,303,307,308],true)&&isset($headers['location'])) {
                    $next=$headers['location'];if(str_starts_with($next,'/')){$parts=parse_url($url);$next=($parts['scheme']??'https').'://'.$parts['host'].$next;}$url=$next;continue;
                }
                if (!is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException('Feed request failed' . ($error ? ': ' . $error : ' (HTTP ' . $status . ')'));
                break;
            }
            if($redirects>3)throw new RuntimeException('Feed exceeded the redirect limit.');
        } else {
            $context = stream_context_create(['http' => ['timeout' => $this->timeoutSeconds, 'follow_location' => 0, 'user_agent' => 'NormanAndCompanyNewsAggregator/1.0']]);
            $body = @file_get_contents($url, false, $context, 0, $this->maxBytes);
            if (!is_string($body)) throw new RuntimeException('Feed request failed.');
        }
        if (strlen($body) > $this->maxBytes) throw new RuntimeException('Feed exceeded the 5 MB safety limit.');
        return $body;
    }

    private function rssItem(SimpleXMLElement $item): array
    {
        $media = $item->children('http://search.yahoo.com/mrss/');
        return $this->normalize((string) $item->guid, (string) $item->title, (string) $item->description, (string) $item->link,
            (string) ($item->author ?: $item->children('http://purl.org/dc/elements/1.1/')->creator), (string) $item->pubDate,
            (string) ($media->content['url'] ?? $media->thumbnail['url'] ?? ''), array_map('strval', iterator_to_array($item->category)));
    }

    private function atomItem(SimpleXMLElement $item): array
    {
        $item = $item->children('http://www.w3.org/2005/Atom');
        $link = '';
        foreach ($item->link as $candidate) if ((string) $candidate['rel'] === '' || (string) $candidate['rel'] === 'alternate') { $link = (string) $candidate['href']; break; }
        $categories=[];foreach($item->category as $category)$categories[]=(string)($category['term']??$category);
        return $this->normalize((string) $item->id, (string) $item->title, (string) ($item->summary ?: $item->content), $link,
            (string) $item->author->name, (string) ($item->published ?: $item->updated), '', $categories);
    }

    private function normalize(string $id, string $title, string $summary, string $url, string $author, string $date, string $image, array $categories): array
    {
        return ['external_id' => trim($id) ?: null, 'headline' => NewsSupport::cleanText($title, 500),
            'summary' => NewsSupport::excerpt($summary, 1000), 'source_url' => trim($url), 'canonical_url' => NewsSupport::normalizeUrl($url),
            'author' => NewsSupport::cleanText($author, 255) ?: null, 'published_at' => NewsSupport::dateOrNull($date),
            'image_url' => filter_var($image, FILTER_VALIDATE_URL) ? $image : null, 'categories' => array_values(array_filter(array_map([NewsSupport::class, 'cleanText'], $categories))), 'keywords' => []];
    }
}
