<?php

declare(strict_types=1);

namespace PrestoWorld\Marketplace\WpOrg;

use PrestoWorld\Contracts\Plugin\PluginRepositoryInterface;
use Prestoworld\MarketplaceSdk\MarketplaceClient;

/**
 * WordPress.org Plugin Repository via PrestoWorld Marketplace Proxy.
 *
 * This implementation uses the PrestoWorld Marketplace's built-in
 * WordPress.org proxy to discover, fetch, and check updates for
 * plugins from the official WordPress.org plugin directory.
 */
class WpOrgRepository implements PluginRepositoryInterface
{
    private MarketplaceClient $client;

    private array $config = [];

    public function __construct(?MarketplaceClient $client = null)
    {
        $this->client = $client ?? new MarketplaceClient();
    }

    public function getName(): string
    {
        return 'wporg';
    }

    public function getLabel(): string
    {
        return 'WordPress.org Plugin Directory';
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;

        if (isset($config['base_url'])) {
            $this->client->setBaseUrl($config['base_url']);
        }
    }

    /**
     * Discover popular plugins from WordPress.org.
     *
     * @return array
     */
    public function discover(): array
    {
        $result = $this->client->wporgProxy('plugins/info/1.2', [
            'action' => 'query_plugins',
            'request' => json_encode([
                'per_page' => $this->config['per_page'] ?? 30,
                'page' => $this->config['page'] ?? 1,
                'browse' => $this->config['browse'] ?? 'popular',
            ]),
        ]);

        return $result['plugins'] ?? [];
    }

    /**
     * Fetch a plugin download URL.
     */
    public function fetch(string $pluginName, string $version): ?string
    {
        // Use the marketplace proxy download endpoint
        return $this->client->getDownloadUrl("wporg-plugin-{$pluginName}", $version)
            ?? "https://downloads.wordpress.org/plugin/{$pluginName}.{$version}.zip";
    }

    /**
     * Check if an update is available via WordPress.org.
     */
    public function hasUpdate(string $pluginName, string $currentVersion): ?string
    {
        $info = $this->getPluginInfo($pluginName);
        if ($info === null || !isset($info['version'])) {
            return null;
        }

        return version_compare($info['version'], $currentVersion, '>')
            ? $info['version']
            : null;
    }

    /**
     * Get plugin information from WordPress.org via proxy.
     */
    public function getPluginInfo(string $pluginName): ?array
    {
        try {
            return $this->client->wporgProxy('plugins/info/1.2', [
                'action' => 'plugin_information',
                'request' => json_encode(['slug' => $pluginName]),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
