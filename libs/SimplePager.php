<?php
namespace DJ;

/*
 * SimplePager
 * Dominik Janák
 *
 *  For the full license information, view the LICENSE file that was distributed
 *  with this source code.
 */

use BadMethodCallException;

/**
 * SimplePager
 */
class SimplePager
{
    const CACHE_CLEAR_ROUTE = 'cache-clear';
    private array $config = [];
    private array $params = [];
    private ?object $parsedown = null;
    private ?object $minifier = null;

    /**
     * Configure SimplePager
     *
     * @param object $parsedown class to parse md to html
     * @param array $config configuration
     * @param array $rewrites custom rewrites
     * @param object|null $minifier class to minify output html
     */
    public function __construct(object $parsedown, array $config = [], array $rewrites = [], object $minifier = null)
    {
        $this->parsedown = $parsedown;
        $this->minifier = $minifier;
        $this->applyDefaultConfig();

        foreach ($config as $key => $val) {
            if (array_key_exists($key, $this->config)) {
                $this->config[$key] = $val;
            }
        }

        foreach ($rewrites as $key => $val) {
            if (($k = array_search($key, $this->params['rewrites']['search'])) !== false) {
                unset($this->params['rewrites']['search'][$k]);
                unset($this->params['rewrites']['replace'][$k]);
            }
            $this->params['rewrites']['search'][] = $key;
            $this->params['rewrites']['replace'][] = $val;
        }
    }

    /**
     * Get generated or cached content by applied route
     *
     * @return string html code
     */
    public function getPageCode() :string
    {
        if ($this->params['route'] == self::CACHE_CLEAR_ROUTE) {
            return 'Cache cleared!';
        }

        $output = $this->getCache(true);

        if (empty($output)) {
            $title = $this->getTitle();
            $output = $this->generateCode($title);
            if (!empty($output)) {
                $this->saveCache($output);
            } else {
                $output = $this->getCache(false);
            }
        }

        return $output;
    }

    /**
     * Apply and validate route
     *
     * @param string $route
     */
    public function applyRoute(string $route = 'index') :bool
    {
        $route = $this->router($route);
        $this->params['route'] = $route;

        $this->params['cache'] = sprintf("%s/%s",
            $this->config['cache'],
            $this->config['pages_source']
        );

        if ($route == self::CACHE_CLEAR_ROUTE) {
            if (file_exists($this->params['cache'])) {
                $this->clearCache($this->params['cache'], true);
            }
            $this->params['route_valid'] = true;
            return true;
        }

        $this->params['source'] = sprintf("%s/%s.%s",
            $this->config['pages_source'],
            $route,
            $this->config['source_type']
        );

        $this->params['modification_file'] = sprintf("%s/%s.mod.txt",
            $this->params['cache'],
            $route
        );

        $this->params['cache_file'] = sprintf("%s/%s.cache.html",
            $this->params['cache'],
            $route
        );

        if ((!file_exists($this->params['cache_file']) || !$this->config['use_cache']) && !file_exists($this->params['source'])) {
            $this->params['route_valid'] = false;
            return false;
        }

        $this->params['route_valid'] = true;
        return true;
    }

    /**
     * Check if route is valid
     *
     * @return bool
     */
    public function isRouteValid() :bool
    {
        return $this->params['route_valid'];
    }

    /**
     * Load casched code by route
     *
     * @param bool $checkUpdate false: always use cache if exists
     * @return string html code
     */
    private function getCache(bool $checkUpdate) :string
    {
        if (!$checkUpdate && $this->config['use_cache'] && file_exists($this->params['cache_file'])) {
            return file_get_contents($this->params['cache_file']);
        }

        if ($this->config['use_cache']
            && file_exists($this->params['cache_file'])
            && file_exists($this->params['source'])
            && file_exists($this->params['modification_file'])
            && file_get_contents($this->params['modification_file']) == filemtime($this->params['source']))
        {
            return file_get_contents($this->params['cache_file']);
        }
        return '';
    }

    /**
     * Get page title
     *
     * @return string
     */
    private function getTitle() :string
    {
        $route = $this->params['route'];

        if (!empty($route)) {
            return sprintf('%s :: %s', $this->config['title'], $route);
        }
        return $this->config['title'];
    }

    /**
     * Generate html from source file
     *
     * @param string $title page title
     * @return string
     */
    private function generateCode(string $title) :string
    {
        if (!$this->config['use_cache'] || file_exists($this->params['source'])) {
            $md = file_get_contents($this->params['source']);

            if ($this->parsedown) {
                $html = $this->parsedown->text($md);
            } else {
                throw new BadMethodCallException("Parser not provided!");
            }

            if ($this->config['template'] && file_exists($this->config['template'])) {
                $template = file_get_contents($this->config['template']);
            } else {
                $template = '{{content}}';
            }

            $html = str_replace(
                array_merge(["{{content}}", '{{title}}'], $this->params['rewrites']['search']),
                array_merge([$html, $title], $this->params['rewrites']['replace']),
                $template);

            if ($this->minifier) {
                $html = $this->minifier->minify($html);
            }
            return $html;
        }
        return '';
    }

    /**
     * Save cache file
     *
     * @param string $code html code
     */
    private function saveCache(string $code) :void
    {
        $mask = umask();
        umask(2);

        if (!file_exists($this->params['cache'])) {
            mkdir($this->params['cache']);
        }

        file_put_contents($this->params['cache_file'], $code);
        file_put_contents($this->params['modification_file'], filemtime($this->params['source']));

        umask($mask);
    }

    /**
     * Delete all cached files
     *
     * @param string $directory root directory
     * @param bool $delete delete root directory
     */
    private function clearCache(string $directory, bool $delete = false) :void
    {
        $contents = glob($directory . '/*');

        foreach ($contents as $item) {
            if (is_dir($item))  {
                // recursively delete sub directories
                $this->clearCache($item . '/', true);
            } else {
                unlink($item);
            }
        }
        if ($delete === true) {
            rmdir($directory);
        }
    }

    /**
     * Get valid route
     *
     * @param string $route actual route
     * @return string
     */
    private function router(string $route) :string
    {
        $route = trim($route, '/');
        if (empty($route)) {
            $route = $this->config['index'];
        }
        return $route;
    }

    /**
     * Default/initial configuration
     */
    private function applyDefaultConfig() :void
    {
        $this->config = [
            'use_cache'    => true,
            'cache'        => 'cache',
            'pages_source' => 'pages',
            'index'        => 'index',
            'source_type'  => 'md',
            'template'     => null,
            'title'        => 'Pager',
        ];

        $this->params = [
            'cache_file'        => null,
            'modification_file' => null,
            'source'            => null,
            'cache'             => null,
            'route_valid'       => false,
            'route'             => null,
            'rewrites'          => [
                'search'  => ['::br::'],
                'replace' => ['<br />']
            ]
        ];
    }
}