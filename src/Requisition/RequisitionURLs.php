<?php

namespace Gann\CDF\Robotics\Requisition;

use DOMDocument;
use DOMXPath;
use Exception;
use Gann\CDF\Robotics\FormProcessor;

class RequisitionURLs implements FormProcessor
{
    const URL = 'url';
    const SOURCE = 'source';
    const NAME = 'name';
    const PREVIEW = 'preview';
    const SKU = 'sku';
    const UNIT_COST = 'unit-cost';
    const QUANTITY = 'quantity';
    const DOM = 'dom';
    const HTML = 'html';
    const CURL = 'curl';
    const INDEX = 'index';

    const ITEM_PATTERN = '/^\s*(\d+)\s+(.*)\s*$/';
    const PRICE_PATTERN = '/^\s*.?\s*(\d+(\.\d{2})?)\s*$/';
    const TLD_PATTERN = '/(.*\.)*([^.]+)(\.[a-z]{3})/i';

    static function process(array $request)
    {
        header('Content-type: application/json');
        $response = [];
        $items = [];
        $scrape = [];
        foreach ($request as $field => $value) {
            switch ($field) {
                case 'items':
                    $parts = explode("\n", $value);
                    foreach ($parts as $part) {
                        $item = [self::QUANTITY => 1];
                        if (preg_match(self::ITEM_PATTERN, $part, $matches)) {
                            $item[self::QUANTITY] = $matches[1];
                            $item[self::URL] = $matches[2];
                        } elseif (false === empty(trim($part))) {
                            $item[self::URL] = trim($part);
                        }
                        preg_match(self::TLD_PATTERN, parse_url($item[self::URL], PHP_URL_HOST), $tld);
                        $site = strtolower($tld[2]);
                        switch ($site) {
                            case 'amazon':
                            case 'andymark':
                            case 'gobilda':
                                $item[self::SOURCE] = $site;
                                $item[self::INDEX] = sizeof($items);
                                $scrape[] = $item;
                                break;
                            default:
                                $item[self::SOURCE] = "{$tld[2]}{$tld[3]}";
                        }
                        $items[] = $item;
                    }
                    break;
                default:
                    $response[$field] = $value;
            }
        }

        $handle = curl_multi_init();
        foreach ($scrape as $i => $item) {
            $h = curl_init();
            curl_setopt_array($h, [
                // i'm a real boy!
                CURLOPT_USERAGENT => getenv('USER_AGENT'),
                CURLOPT_REFERER => $_SERVER['PHP_SELF'],

                // what do we want?
                CURLOPT_URL => $item[self::URL],
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,

                // we'll go wherever it takes us
                CURLOPT_FOLLOWLOCATION => true, // required for Amazon

            ]);
            curl_multi_add_handle($handle, $h);
            $scrape[$i][self::CURL] = $h;
        }

        // process all requests
        do {
            $status = curl_multi_exec($handle, $active);
            if ($active) {
                curl_multi_select($handle);
            }
        } while ($active && $status === CURLM_OK);
        if ($status !== CURLM_OK) {
            echo json_encode(['error' => [
                'errno' => curl_multi_errno($handle),
                'message' => curl_multi_strerror($status)
            ]]);
            exit;
        }

        // scrape downloaded HTML
        foreach ($scrape as $item) {
            // collect downloaded HTML
            $item[self::HTML] = curl_multi_getcontent($item[self::CURL]);
            curl_multi_remove_handle($handle, $item[self::CURL]);
            unset($item[self::CURL]);

            // if we have downloaded HTML, process it with designated scraping function
            if (false === empty($item[self::HTML])) {
                $item[self::DOM] = new DOMDocument();
                $error = error_reporting(E_ERROR); // ignore finicky HTML errors
                $item[self::DOM]->loadHTML($item[self::HTML]);
                error_reporting($error); // restore prior error reporting level

            }
            $i = $item[self::INDEX];
            unset($item[self::INDEX]);
            unset($item[self::HTML]);
            $item = call_user_func([RequisitionURLs::class, $item[self::SOURCE]], $item);
            unset($item[self::DOM]);
            $items[$i] = $item;
        }
        curl_multi_close($handle);

        $response['items'] = $items;
        echo json_encode($response);
        exit;
    }

    /**
     * @param string[]|DOMDocument[] $item
     * @return array
     */
    private static function amazon($item)
    {
        $item[self::SOURCE] = 'Amazon';
        if ($name = $item[self::DOM]->getElementById('productTitle')) {
            $item[self::NAME] = trim($name->textContent);
        }
        preg_match('@.*/dp/([^/?]+).*@', parse_url($item[self::URL], PHP_URL_PATH), $sku);
        if (false === empty($sku[1])) {
            $item[self::SKU] = $sku[1];
        }
        if ($price = $item[self::DOM]->getElementById('priceblock_ourprice')) {
            $item[self::UNIT_COST] = preg_replace(self::PRICE_PATTERN, '$1', $price->textContent);
        }
        if ($preview = $item[self::DOM]->getElementById('landingImage')) {
            $item[self::PREVIEW] = trim($preview->getAttribute('data-old-hires'));
        }
        return $item;
    }

    /**
     * @param string[]|DOMDocument[] $item
     * @return array
     */
    private static function andymark($item)
    {
        $xpath = new DOMXPath($item[self::DOM]);
        $item[self::SOURCE] = 'AndyMark';
        if ($name = $xpath->query("//h1[contains(@class, 'product-details__heading')]")->item(0)) {
            $item[self::NAME] = trim($name->textContent);
        }
        if ($sku = $xpath->query("//p[contains(@class, 'product-details__id')]")->item(0)) {
            $item[self::SKU] = trim($sku->textContent);
        } else {
            parse_str(parse_url($item[self::URL], PHP_URL_QUERY), $item[self::SKU]);
        }
        if ($price = $xpath->query("//p[contains(@class, 'product-prices__price')]")->item(0)) {
            $item[self::UNIT_COST] = preg_replace(self::PRICE_PATTERN, '$1', $price->textContent);
        }
        if ($preview = $xpath->query("//img[contains(@class, 'product-media__primary-image-link-image')]")->item(0)) {
            $item[self::PREVIEW] = trim($preview->attributes->getNamedItem('src')->textContent);
        }
        return $item;
    }

    /**
     * @param string[]|DOMDocument[] $item
     * @return array
     */
    private static function gobilda($item)
    {
        $xpath = new DOMXPath($item[self::DOM]);
        $item[self::SOURCE] = 'GoBilda';
        if ($name = $xpath->query("//h1[contains(@class, 'productView-title')]")->item(0)) {
            $item[self::NAME] = trim($name->textContent);
        }
        if ($preview = $xpath->query("//img[contains(@class, 'imageGallery-small-image')]")->item(0)) {
            $item[self::PREVIEW] = trim($preview->attributes->getNamedItem('src')->textContent);
        }
        if (false === empty($item[self::PREVIEW])) {
            $item[self::SKU] = preg_replace('@.*/(.+)__\d+\.\d+\.jpg\??.*@', '$1', $item[self::PREVIEW]);
        }
        if ($price = $xpath->query("//span[contains(@class, 'price price--withoutTax')]")->item(0)) {
            $item[self::UNIT_COST] = preg_replace(self::PRICE_PATTERN, '$1', $price->textContent);
        }
        return $item;
    }
}
