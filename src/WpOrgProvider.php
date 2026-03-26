<?php

namespace PrestoWorld\Marketplace\WpOrg;

use Prestoworld\MarketplaceSdk\Contracts\RepositoryProviderInterface;
use Prestoworld\MarketplaceSdk\Contracts\RepositoryItemInterface;
use Prestoworld\MarketplaceSdk\Common\MarketplaceExtension;

/**
 * Class WpOrgProvider
 * 
 * Implements the official WordPress.org (WpOrg) repository 
 * for the PrestoWorld Hub.
 */
class WpOrgProvider implements RepositoryProviderInterface
{
    protected string $pluginApi = 'https://api.wordpress.org/plugins/info/1.2/';
    protected string $themeApi  = 'https://api.wordpress.org/themes/info/1.1/';

    public function getProviderId(): string { return 'wporg'; }

    public function fetchAll(array $filters = []): array
    {
        $type = $filters['type'] ?? 'plugin';
        $api  = ($type === 'theme') ? $this->themeApi : $this->pluginApi;
        
        $params = [
            'action' => ($type === 'theme') ? 'query_themes' : 'query_plugins',
            'request' => array_filter([
                'page'       => (int)($filters['page'] ?? 1),
                'per_page'   => (int)($filters['per_page'] ?? 15),
                'browse'     => empty($filters['search']) ? ($filters['tab'] ?? 'featured') : null,
                'search'     => $filters['search'] ?? null,
                'locale'     => $filters['locale'] ?? 'en_US',
                'wp_version' => '6.4',
                'fields'     => [
                    'short_description' => true,
                    'active_installs'   => true,
                    'icons'             => true,
                    'rating'            => true,
                    'author'            => true,
                    'author_profile'    => true,
                    'banners'           => true,
                ]
            ])
        ];

        $response = $this->query($api, $params);
        $items = ($type === 'theme') ? ($response['themes'] ?? []) : ($response['plugins'] ?? []);
        
        return array_map(function($p) use ($type) {
            return new MarketplaceExtension([
                'id' => $p['slug'],
                'slug' => $p['slug'],
                'name' => html_entity_decode($p['name'], ENT_QUOTES, 'UTF-8'),
                'version' => $p['version'],
                'author' => ['name' => strip_tags(html_entity_decode($p['author'] ?? ($p['author_name'] ?? 'PrestoWorld'), ENT_QUOTES, 'UTF-8'))],
                'description' => strip_tags(html_entity_decode($p['short_description'] ?? ($p['description'] ?? ''), ENT_QUOTES, 'UTF-8')),
                'latest_version' => $p['version'],
                'icon_svg' => ($type === 'theme') ? ($p['screenshot_url'] ?? '') : ($p['icons']['svg'] ?? ($p['icons']['default'] ?? '')),
                'install_count' => (int)($p['active_installs'] ?? 0),
                'rating' => (float)($p['rating'] ?? 0),
                'is_premium' => false,
                'type' => $type
            ]);
        }, $items);
    }

    public function findBySlug(string $slug, string $type = 'any'): ?RepositoryItemInterface
    {
        if ($type === 'any' || $type === 'plugin') {
            $data = $this->query($this->pluginApi, [
                'action' => 'plugin_information',
                'request' => ['slug' => $slug]
            ]);
            if (!empty($data) && isset($data['slug'])) return $this->mapItem($data, 'plugin');
        }

        if ($type === 'any' || $type === 'theme') {
            $data = $this->query($this->themeApi, [
                'action' => 'theme_information',
                'request' => ['slug' => $slug]
            ]);
            if (!empty($data) && isset($data['slug'])) return $this->mapItem($data, 'theme');
        }

        return null;
    }

    protected function mapItem(array $data, string $type): MarketplaceExtension
    {
        return new MarketplaceExtension([
            'id' => $data['slug'],
            'slug' => $data['slug'],
            'name' => $data['name'],
            'version' => $data['version'],
            'author' => [
                'name' => strip_tags($data['author'] ?? ($data['author_name'] ?? 'PrestoWorld')),
                'url'  => $data['author_profile'] ?? ($data['author_url'] ?? '#')
            ],
            'description' => $data['description'] ?? '',
            'latest_version' => $data['version'],
            'icon_svg' => ($type === 'theme') ? ($data['screenshot_url'] ?? '') : ($data['icons']['svg'] ?? ($data['icons']['default'] ?? '')),
            'install_count' => (int)($data['active_installs'] ?? 0),
            'rating' => (float)($data['rating'] ?? 0),
            'download_url' => $data['download_link'] ?? '',
            'type' => $type
        ]);
    }

    public function count(array $filters = []): int
    {
        return 100;
    }

    protected function query(string $url, array $params): array
    {
        $cacheKey = md5($url . serialize($params));
        $cacheDir = dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/storage/framework/cache/wporg';
        $cacheFile = $cacheDir . '/' . $cacheKey . '.json';
        $ttl = 3600; // 1 hour

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            return json_decode(file_get_contents($cacheFile), true) ?: [];
        }

        $apiUrl = $url . '?' . http_build_query($params);
        $res = @file_get_contents($apiUrl);
        if (!$res) return [];

        $data = json_decode($res, true);

        if (!empty($data) && !is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        if (!empty($data)) {
            file_put_contents($cacheFile, $res);
        }

        return is_array($data) ? $data : [];
    }
}
