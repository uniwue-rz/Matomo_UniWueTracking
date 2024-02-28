<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\UniWueTracking;

use Piwik\Common;
use Piwik\Db;
use Piwik\Request;

/**
 * A controller lets you for example create a page that can be added to a menu. For more information read our guide
 * http://developer.piwik.org/guides/mvc-in-piwik or have a look at the our API references for controller and view:
 * http://developer.piwik.org/api-reference/Piwik/Plugin/Controller and
 * http://developer.piwik.org/api-reference/Piwik/View
 */
class Controller extends \Piwik\Plugin\Controller
{
    private const int SITE_ALL = 358;


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

    private function getBestMatchingSiteId(string $location): ?int
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

    private function generateTrackingScript(?int $siteId): string
    {
        $if = function ($condition, $true, $false) {
            return $condition ? $true : $false;
        };
        $siteIdAll = self::SITE_ALL;

        return <<<HTML
            <script type='text/javascript' defer='defer'>
                var _paq = _paq || [];
                _paq.push(['setTrackerUrl', '{$this->getMatomoTrackerUrl()}']);
                _paq.push(['setSiteId', {$siteIdAll}]);
                _paq.push(['disableCookies']);
                _paq.push(['enableLinkTracking']);
                _paq.push(['trackPageView']);
                {$if($siteId, "_paq.push(['addTracker', '{$this->getMatomoTrackerUrl()}', {$siteId}]);", "")}
            </script>
            <script type='text/javascript' defer='defer' src='{$this->getMatomoScriptUrl()}'></script>
        HTML;
    }

    private function getMatomoScriptUrl(): string
    {
        return $this->getMatomoBaseUrl() . '/matomo.js';
    }

    private function getMatomoTrackerUrl(): string
    {
        return $this->getMatomoBaseUrl() . '/matomo.php';
    }

    private function getMatomoBaseUrl(): string
    {
        return (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://" . $_SERVER['HTTP_HOST'];
    }
}
