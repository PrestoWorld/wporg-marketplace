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
    protected string $apiBase = 'https://api.wordpress.org/plugins/info/1.2/';

    public function getProviderId(): string { return 'wporg'; }

    public function fetchAll(array $filters = []): array
    {
        // For plugins, we typically use the search or browse API
        $query = [
            'action' => 'query_plugins',
            'request' => serialize((object)[
                'page' => $filters['page'] ?? 1,
                'per_page' => $filters['per_page'] ?? 12,
                'browse' => 'popular',
            ])
        ];

        $response = $this->query($query);
        
        if (!isset($response['plugins'])) {
            return [];
        }

        return array_map(function($p) {
            return new MarketplaceExtension([
                'id' => $p['slug'],
                'slug' => $p['slug'],
                'name' => $p['name'],
                'version' => $p['version'],
                'author' => ['name' => $p['author']],
                'description' => $p['short_description'] ?? '',
                'latest_version' => $p['version'],
                'icon_svg' => $p['icons']['svg'] ?? ($p['icons']['default'] ?? ''),
                'install_count' => (int)($p['active_installs'] ?? 0),
                'rating' => (float)($p['rating'] ?? 0),
                'is_premium' => false,
                'type' => 'plugin'
            ]);
        }, $response['plugins']);
    }

    public function findBySlug(string $slug, string $type = 'any'): ?RepositoryItemInterface
    {
        $query = [
            'action' => 'plugin_information',
            'request' => serialize((object)['slug' => $slug])
        ];

        $data = $this->query($query);
        
        if (!isset($data['slug'])) {
            return null;
        }

        return new MarketplaceExtension([
            'id' => $data['slug'],
            'slug' => $data['slug'],
            'name' => $data['name'],
            'version' => $data['version'],
            'author' => ['name' => $data['author']],
            'description' => $data['sections']['description'] ?? '',
            'latest_version' => $data['version'],
            'icon_svg' => $data['icons']['svg'] ?? ($data['icons']['default'] ?? ''),
            'install_count' => (int)($data['active_installs'] ?? 0),
            'rating' => (float)($data['rating'] ?? 0),
            'download_url' => $data['download_link'] ?? '',
            'type' => 'plugin'
        ]);
    }

    public function count(array $filters = []): int
    {
        return 100;
    }

    protected function query(array $params): array
    {
        $url = $this->apiBase . '?' . http_build_query($params);
        $res = @file_get_contents($url);
        return $res ? unserialize($res) : [];
    }
}
