<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\UniWueTracking;

use Matomo\Cache\Lazy;
use Piwik\Common;
use Piwik\Db;
use Piwik\Request;
use Piwik\SettingsPiwik;

/**
 * Custom controller that dynamically generates the JS Tracker Code.
 */
class Controller extends \Piwik\Plugin\Controller
{
    private const string CACHE_KEY = 'UniWueTracking_LocationMap';
    private const int CACHE_DURATION = 24 * 60 * 60; // 1d
    private const int SITE_ALL = 358; // catch-all site

    private Lazy $cache;

    public function __construct(Lazy $cache) {
        $this->cache = $cache;
        parent::__construct();
    }

    /* endpoints */

    /**
     * Echoes the generated JS Snippet as plain text.
     *
     * @return never
     */
    public function getTrackingScript(): never
    {
        $location = Request::fromGet()->getStringParameter('location', '');
        
        header('Content-Type: text/plain; charset=utf-8;');

        if (empty($location)) {
            echo ("'location' parameter with the current URL must be provided!");
            exit;
        }
        $siteId = $this->getBestMatchingSiteId($location);

        echo ($this->generateTrackingScript($siteId));
        exit;
    }

    /* utility */

    /**
     * Returns the best matching site ID for the given location from either:
     * - cache (priority) OR
     * - SQL query (fallback)
     *
     * @param string $location
     * @return integer|null
     */
    private function getBestMatchingSiteId(string $location): ?int
    {
        // this should work, but doesn't: https://github.com/matomo-org/matomo/issues/21979
        // TODO: invalidate cache on site modification
        $locationMap = $this->cache->fetch(self::CACHE_KEY) ?: [];

        if (!isset($locationMap[$location])) {
            $locationMap[$location] = $this->queryBestMatchingSiteId($location);
            $this->cache->save(self::CACHE_KEY, $locationMap, self::CACHE_DURATION);
        }

        return $locationMap[$location];
    }

    /**
     * Returns the best matching site ID depending on the given location by performing an SQL lookup.
     * 
     * This internally retrieves all site URLs that match the location and returns the one which is the longest,
     * which is equivalent to a "best match".
     *
     * @param string $location
     * @return integer|null
     */
    private function queryBestMatchingSiteId(string $location): ?int
    {
        $siteTable = Common::prefixTable('site');
        $siteUrlTable = Common::prefixTable('site_url');

        return Db::get()->fetchOne(
            "SELECT tmp.idsite
             FROM (
                (SELECT DISTINCT idsite, main_url AS url FROM " . $siteTable . " WHERE idsite <> ? AND INSTR(?, main_url) > 0)
                UNION DISTINCT
                (SELECT DISTINCT idsite, url FROM " . $siteUrlTable . " WHERE idsite <> ? AND INSTR(?, url) > 0)
             ) tmp
             ORDER BY LENGTH(tmp.url) DESC
            ",
            [
                self::SITE_ALL,
                $location,
                self::SITE_ALL,
                $location
            ]
        );
    }

    /**
     * Returns the JS Tracker snippet that tracks the visit to:
     * - the catch-all site
     * - the provided (best-matching) site ID (if any)
     *
     * @param integer|null $siteId
     * @return string
     */
    private function generateTrackingScript(?int $siteId): string
    {
        $if = function ($condition, $true, $false) {
            return $condition ? $true : $false;
        };
        $siteIdAll = self::SITE_ALL;

        return <<<HTML
            <script type='text/javascript' defer='defer'>
                var _paq = _paq || [];
                _paq.push(['setTrackerUrl', '{$this->getMatomoTrackingEndpoint()}']);
                _paq.push(['setSiteId', {$siteIdAll}]);
                _paq.push(['disableCookies']);
                _paq.push(['enableLinkTracking']);
                _paq.push(['trackPageView']);
                {$if($siteId, "_paq.push(['addTracker', '{$this->getMatomoTrackingEndpoint()}', {$siteId}]);", "")}
            </script>
            <script type='text/javascript' defer='defer' src='{$this->getMatomoClientEndpoint()}'></script>
        HTML;
    }

    /**
     * Returns the URL to Matomo's JS client.
     *
     * @return string
     */
    private function getMatomoClientEndpoint(): string
    {
        return SettingsPiwik::getPiwikUrl() . 'matomo.js';
    }

    /**
     * Returns the URL to Matomo's Tracking endpoint.
     *
     * @return string
     */
    private function getMatomoTrackingEndpoint(): string
    {
        return SettingsPiwik::getPiwikUrl() . 'matomo.php';
    }
}
