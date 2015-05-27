<?php

/*
 * This file is part of the puli/url-generator package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\UrlGenerator;

use Puli\Discovery\Api\Binding\ResourceBinding;
use Puli\Discovery\Api\ResourceDiscovery;
use Puli\UrlGenerator\Api\UrlGenerator;
use Puli\UrlGenerator\Api\CannotGenerateUrlException;
use Webmozart\Glob\Glob;

/**
 * A resource URL generator that uses a {@link ResourceDiscovery} as backend.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryUrlGenerator implements UrlGenerator
{
    /**
     * The binding type of public resources.
     */
    const BINDING_TYPE = 'puli/public-resource';

    /**
     * The binding parameter used for the server name.
     */
    const SERVER_PARAMETER = 'server';

    /**
     * The binding parameter used for the public path.
     */
    const PATH_PARAMETER = 'path';

    /**
     * @var ResourceDiscovery
     */
    private $discovery;

    /**
     * @var string[]
     */
    private $urlFormats;

    /**
     * Creates the URL generator.
     *
     * @param ResourceDiscovery $discovery  The resource discovery.
     * @param string[]          $urlFormats The URL formats indexed by the
     *                                      server names.
     */
    public function __construct(ResourceDiscovery $discovery, array $urlFormats)
    {
        $this->discovery = $discovery;
        $this->urlFormats = $urlFormats;
    }

    /**
     * {@inheritdoc}
     */
    public function generateUrl($repositoryPath, $currentUrl = null)
    {
        $bindings = $this->discovery->findByPath($repositoryPath, self::BINDING_TYPE);
        $count = count($bindings);

        if (0 === $count) {
            throw new CannotGenerateUrlException(sprintf(
                'Cannot generate URL for "%s". The path is not public.',
                $repositoryPath
            ));
        }

        // We can't prevent a resource to be mapped to more than one public path
        // For now, we'll just take the first one and make the user responsible
        // for preventing duplicates
        $url = $this->generateUrlForBinding(reset($bindings), $repositoryPath);

        if ($currentUrl) {
            // TODO use Url::makeRelative() once it exists
        }

        return $url;
    }

    private function generateUrlForBinding(ResourceBinding $binding, $repositoryPath)
    {
        $serverName = $binding->getParameterValue(self::SERVER_PARAMETER);

        if (!isset($this->urlFormats[$serverName])) {
            throw new CannotGenerateUrlException(sprintf(
                'The server "%s" mapped for path "%s" does not exist.',
                $serverName,
                $repositoryPath
            ));
        }

        $repoBasePath = Glob::getStaticPrefix($binding->getQuery());
        $serverBasePath = trim($binding->getParameterValue(self::PATH_PARAMETER), '/');

        // The server path is generated by replacing the base repository path
        // (= the path of the binding) by the stored server base path in the
        // repository path of the resource.
        //
        // Example:
        //
        // resource path: /acme/blog/public/css/style.css
        // binding path: /acme/blog/public{,/**/*}
        // repo base path: /acme/blog/public
        // server base path: /blog
        //
        // final server path: /blog/css/style.css

        $serverPath = substr_replace($repositoryPath, $serverBasePath, 0, strlen($repoBasePath));

        // The server path is inserted into the "%s" parameter of the URL format
        return sprintf($this->urlFormats[$serverName], ltrim($serverPath, '/'));
    }
}
