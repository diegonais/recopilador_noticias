<?php

class NewsService
{
    public function normalizeCollection(array $items)
    {
        $normalized = array();

        foreach ($items as $item) {
            $validated = $this->validateAndNormalize($item);

            if ($validated !== null) {
                $normalized[] = $validated;
            }
        }

        return $this->deduplicateAndSort($normalized);
    }

    public function mergeNews(array $existingNews, array $incomingNews)
    {
        $merged = array_merge(
            $this->normalizeCollection($existingNews),
            $this->normalizeCollection($incomingNews)
        );

        $merged = $this->deduplicateAndSort($merged);

        return array_slice($merged, 0, MAX_NEWS_ITEMS);
    }

    public function countNewItems(array $existingNews, array $incomingNews)
    {
        $existingMap = array();

        foreach ($this->normalizeCollection($existingNews) as $item) {
            $existingMap[$this->makeUniqueKey($item)] = true;
        }

        $count = 0;

        foreach ($this->normalizeCollection($incomingNews) as $item) {
            $key = $this->makeUniqueKey($item);

            if (!isset($existingMap[$key])) {
                $existingMap[$key] = true;
                $count++;
            }
        }

        return $count;
    }

    private function deduplicateAndSort(array $items)
    {
        $unique = array();

        foreach ($items as $item) {
            $unique[$this->makeUniqueKey($item)] = $item;
        }

        $items = array_values($unique);

        usort($items, function ($left, $right) {
            return DateHelper::toTimestamp(isset($right['published_at']) ? $right['published_at'] : '') <=> DateHelper::toTimestamp(isset($left['published_at']) ? $left['published_at'] : '');
        });

        return $items;
    }

    private function validateAndNormalize(array $item)
    {
        $title = TextHelper::fallback(TextHelper::cleanHtml(isset($item['title']) ? $item['title'] : ''), '');
        $link = trim(isset($item['link']) ? (string) $item['link'] : '');
        $publishedAt = DateHelper::toStorageFormat(isset($item['published_at']) ? $item['published_at'] : '');

        if ($title === '' || $link === '' || $publishedAt === '') {
            return null;
        }

        $summary = TextHelper::fallback(
            TextHelper::removeFeedFooter(
                TextHelper::cleanHtml(isset($item['summary']) ? $item['summary'] : '')
            ),
            'Sin resumen disponible.'
        );
        $guid = trim(isset($item['guid']) ? (string) $item['guid'] : '');

        if ($guid === '') {
            $guid = sha1($link . '|' . $publishedAt);
        }

        return array(
            'title' => $title,
            'summary' => $summary,
            'link' => $link,
            'source' => TextHelper::fallback(isset($item['source']) ? $item['source'] : '', 'ABI'),
            'published_at' => $publishedAt,
            'image' => trim(isset($item['image']) ? (string) $item['image'] : ''),
            'guid' => $guid,
        );
    }

    private function makeUniqueKey(array $item)
    {
        $guid = trim(isset($item['guid']) ? (string) $item['guid'] : '');

        if ($guid !== '') {
            return 'guid:' . $guid;
        }

        $link = trim(isset($item['link']) ? (string) $item['link'] : '');

        if ($link !== '') {
            return 'link:' . $link;
        }

        return sha1(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
