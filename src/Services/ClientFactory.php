<?php

namespace SilverStripe\EnvironmentCheck\Services;

use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Client as GuzzleClient;
use SilverStripe\Core\Injector\Factory;
use SilverStripe\Core\Config\Configurable;

/**
 * Factory class for creating HTTP client which are injected into some env check classes. Inject via YAML,
 * arguments for Guzzle client can be supplied using "constructor" property or set as default_config.
 *
 * @see SilverStripe\EnvironmentCheck\Traits\Fetcher
 */
class ClientFactory implements Factory
{
    use Configurable;

    /**
     * Default config for Guzzle client.
     *
     * @var array
     */
    private static $default_config = [];

    /**
     * Wrapper to create a Guzzle client.
     *
     * {@inheritdoc}
     */
    public function create(string $service, array $params = []): ClientInterface
    {
        return new GuzzleClient($this->getConfig($params));
    }

    /**
     * Merge config provided from yaml with default config
     *
     * @param array $overrides
     * @return array
     */
    public function getConfig(array $overrides)
    {
        return array_merge(
            $this->config()->get('default_config'),
            $overrides
        );
    }
}
