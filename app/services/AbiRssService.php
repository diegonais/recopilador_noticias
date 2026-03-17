<?php

class AbiRssService
{
    private $articleImageCache = array();
    private $articlePageCache = array();

    public function fetchNews()
    {
        $content = $this->loadRemoteContent(ABI_RSS_URL);

        if ($content === null) {
            return array(
                'success' => false,
                'items' => array(),
                'error' => 'No se pudo leer el feed RSS de ABI.',
            );
        }

        $xml = $this->parseXml($content);

        if (!$xml instanceof SimpleXMLElement) {
            return array(
                'success' => false,
                'items' => array(),
                'error' => 'No se pudo interpretar el XML del feed RSS de ABI.',
            );
        }

        $items = array();

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = $this->mapItem($item);
            }
        }

        return array(
            'success' => true,
            'items' => $items,
            'error' => null,
        );
    }

    private function loadRemoteContent($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => APP_NAME . ' RSS Reader/1.0',
            ));

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            curl_close($ch);

            if (is_string($response) && $response !== '' && $statusCode >= 200 && $statusCode < 400) {
                return $response;
            }

            if ($error !== '') {
                return null;
            }
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 20,
                'ignore_errors' => true,
                'header' => "User-Agent: " . APP_NAME . " RSS Reader/1.0\r\n",
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));

        $response = @file_get_contents($url, false, $context);

        return is_string($response) && $response !== '' ? $response : null;
    }

    private function parseXml($content)
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            libxml_clear_errors();

            return null;
        }

        libxml_clear_errors();

        return $xml;
    }

    private function mapItem(SimpleXMLElement $item)
    {
        $title = TextHelper::fallback(TextHelper::cleanHtml(isset($item->title) ? (string) $item->title : ''), 'Sin titulo');
        $description = isset($item->description) ? (string) $item->description : '';
        $contentEncoded = $this->getContentEncoded($item);
        $link = trim(isset($item->link) ? (string) $item->link : '');
        $guid = trim(isset($item->guid) ? (string) $item->guid : '');
        $publishedRaw = '';

        if (isset($item->pubDate)) {
            $publishedRaw = (string) $item->pubDate;
        } elseif (isset($item->published)) {
            $publishedRaw = (string) $item->published;
        } elseif (isset($item->updated)) {
            $publishedRaw = (string) $item->updated;
        }

        $publishedAt = DateHelper::toStorageFormat($publishedRaw);

        if ($guid === '') {
            $guid = sha1($link . '|' . $title . '|' . $publishedAt);
        }

        $summary = $this->buildSummary($description, $contentEncoded, $link);

        return array(
            'title' => $title,
            'summary' => $summary,
            'link' => $link,
            'source' => 'ABI',
            'published_at' => $publishedAt,
            'image' => $this->extractImage($item, $description, $contentEncoded, $link),
            'guid' => $guid,
        );
    }

    private function buildSummary($description, $contentEncoded, $link)
    {
        $articleText = $this->extractArticleTextFromPage($link);

        if ($articleText !== '') {
            return $articleText;
        }

        $summarySource = $description !== '' ? $description : $contentEncoded;

        return TextHelper::fallback(
            TextHelper::removeFeedFooter(
                TextHelper::cleanHtml($summarySource)
            )
        );
    }

    private function getContentEncoded(SimpleXMLElement $item)
    {
        $namespaces = $item->getNamespaces(true);

        if (!isset($namespaces['content'])) {
            return '';
        }

        $content = $item->children($namespaces['content']);

        return isset($content->encoded) ? (string) $content->encoded : '';
    }

    private function extractImage(SimpleXMLElement $item, $description, $contentEncoded, $link)
    {
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);

            foreach (array('content', 'thumbnail') as $nodeName) {
                if (!isset($media->{$nodeName})) {
                    continue;
                }

                foreach ($media->{$nodeName} as $node) {
                    $attributes = $node->attributes();
                    $url = trim(isset($attributes['url']) ? (string) $attributes['url'] : '');

                    if ($url !== '') {
                        return $url;
                    }
                }
            }
        }

        if (isset($item->enclosure)) {
            foreach ($item->enclosure as $enclosure) {
                $attributes = $enclosure->attributes();
                $url = trim(isset($attributes['url']) ? (string) $attributes['url'] : '');

                if ($url !== '') {
                    return $url;
                }
            }
        }

        $htmlSources = array($description, $contentEncoded);

        foreach ($htmlSources as $html) {
            if (preg_match("/<img[^>]+src=[\"']([^\"']+)[\"']/i", $html, $matches) === 1) {
                return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $this->extractImageFromArticlePage($link);
    }

    private function extractImageFromArticlePage($link)
    {
        $link = trim((string) $link);

        if ($link === '') {
            return '';
        }

        if (isset($this->articleImageCache[$link])) {
            return $this->articleImageCache[$link];
        }

        $html = $this->getArticlePageHtml($link);

        if ($html === '') {
            $this->articleImageCache[$link] = '';
            return '';
        }

        $patterns = array(
            '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
            '/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']/i',
            '/<img[^>]+class=["\'][^"\']*wp-post-image[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i',
            '/<img[^>]+src=["\']([^"\']+)["\'][^>]+class=["\'][^"\']*wp-post-image[^"\']*["\']/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                $image = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $image = trim($image);

                if ($image !== '') {
                    $this->articleImageCache[$link] = $image;

                    return $image;
                }
            }
        }

        $this->articleImageCache[$link] = '';

        return '';
    }

    private function extractArticleTextFromPage($link)
    {
        $html = $this->getArticlePageHtml($link);

        if ($html === '') {
            return '';
        }

        if (preg_match('/<article[^>]+class=["\'][^"\']*page-content-single[^"\']*["\'][^>]*>(.*?)<\/article>/is', $html, $matches) !== 1) {
            return '';
        }

        $articleHtml = $matches[1];
        $articleHtml = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $articleHtml);
        $articleHtml = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $articleHtml);
        $articleHtml = preg_replace('/<div[^>]+class=["\'][^"\']*post-share[^"\']*["\'][^>]*>.*?<\/div>/is', ' ', $articleHtml);

        $text = TextHelper::cleanHtml($articleHtml);
        $text = TextHelper::removeFeedFooter($text);
        $text = preg_replace('/\s+[A-ZÃÃ‰ÃÃ“ÃšÃ‘][A-Za-zÃÃ‰ÃÃ“ÃšÃ‘a-zÃ¡Ã©Ã­Ã³ÃºÃ±]{1,5}(?:\/[A-ZÃÃ‰ÃÃ“ÃšÃ‘][A-Za-zÃÃ‰ÃÃ“ÃšÃ‘a-zÃ¡Ã©Ã­Ã³ÃºÃ±]{1,5})+$/u', '', $text);

        return trim((string) $text);
    }

    private function getArticlePageHtml($link)
    {
        $link = trim((string) $link);

        if ($link === '') {
            return '';
        }

        if (isset($this->articlePageCache[$link])) {
            return $this->articlePageCache[$link];
        }

        $cacheFile = CACHE_PATH . DIRECTORY_SEPARATOR . 'page_' . sha1($link) . '.html';

        if (is_file($cacheFile) && is_readable($cacheFile) && filemtime($cacheFile) !== false) {
            $age = time() - filemtime($cacheFile);

            if ($age < 21600) {
                $cachedHtml = (string) file_get_contents($cacheFile);
                $this->articlePageCache[$link] = $cachedHtml;

                return $cachedHtml;
            }
        }

        $html = $this->loadRemoteContent($link);

        if (!is_string($html) || $html === '') {
            $this->articlePageCache[$link] = '';
            return '';
        }

        $this->articlePageCache[$link] = $html;
        @file_put_contents($cacheFile, $html, LOCK_EX);

        return $html;
    }
}