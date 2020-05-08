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

    const PRICE_PATTERN = '/^\s*.?\s*(\d+(\.\d{2})?)\s*$/';

    static function process(array $request)
    {
        $response = [];
        $items = [];
        foreach ($request as $field => $value) {
            switch ($field) {
                case 'items':
                    $parts = explode("\n", $value);
                    foreach ($parts as $part) {
                        if (preg_match('/^\s*(\d+)\s+(.*)$/', $part, $matches)) {
                            $items[] = [
                                self::QUANTITY => $matches[1],
                                self::URL => $matches[2]
                            ];
                        } elseif (false === empty(trim($part))) {
                            $items[] = [
                                self::QUANTITY => 1,
                                self::URL => trim($part)
                            ];
                        }
                    }
                    break;
                default:
                    $response[$field] = $value;
            }
        }

        $handle = curl_multi_init();
        foreach ($items as $i => $item) {
            if (false === empty($item[self::URL])) {
                $h = curl_init();
                curl_setopt_array($h, [
                    // i'm a real boy!
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0',
                    CURLOPT_REFERER => 'http://robotics.gannacademy.org/requisition.html',
                    //CURLOPT_COOKIEFILE => 'cookie.txt',
                    //CURLOPT_COOKIEJAR => 'cookie.txt',

                    // what do we want?
                    CURLOPT_URL => $item[self::URL],
                    CURLOPT_HEADER => false,
                    CURLOPT_RETURNTRANSFER => true,

                    // we'll go wherever it takes us
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_AUTOREFERER => true,
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_CONNECTTIMEOUT => 120,
                    CURLOPT_TIMEOUT => 120

                ]);
                curl_multi_add_handle($handle, $h);
                $items[$i][self::CURL] = $h;
            }
        }

        // process all requests
        do {
            $status = curl_multi_exec($handle, $active);
            if ($active) {
                curl_multi_select($handle);
            }
        } while ($active && $status === CURLM_OK);
        if ($status !== CURLM_OK) {
            throw new Exception(curl_multi_strerror($status), curl_multi_errno($handle));
        }

        foreach ($items as $i => $item) {
            if (false === empty($item[self::CURL])) {
                $items[$i][self::HTML] = curl_multi_getcontent($item[self::CURL]);
                curl_multi_remove_handle($handle, $item[self::CURL]);
                unset($items[$i][self::CURL]);
            }
        }
        curl_multi_close($handle);

        foreach ($items as $i => $item) {
            if (false === empty($item[self::HTML])) {
                $site = strtolower(preg_replace('/(.*\.)*([^.]+)\.[a-z]{3}/i', '$2', parse_url($item[self::URL],
                    PHP_URL_HOST)));
                $items[$i][self::DOM] = new DOMDocument();
                $error = error_reporting(E_ERROR); // ignore finicky HTML errors
                $items[$i][self::DOM]->loadHTML($item[self::HTML]);
                unset($items[$i][self::HTML]);
                error_reporting($error); // restore prior error reporting level

                switch ($site) {
                    case 'amazon':
                        $items[$i] = self::amazon($items[$i]);
                        break;
                    case 'andymark':
                        $items[$i] = self::andymark($items[$i]);
                        break;
                    case 'gobilda':
                        $items[$i] = self::gobilda($items[$i]);
                        break;
                }
                unset($items[$i][self::DOM]);
            }
        }
        $response['items'] = $items;
        header('Content-type: application/json');
        echo json_encode($response);
        exit;
    }

    private static function amazon($item)
    {
        $item[self::SOURCE] = 'Amazon';
        if ($name = $item[self::DOM]->getElementById('productTitle')) {
            $item[self::NAME] = trim($name->textContent);
        }
        if ($price = $item[self::DOM]->getElementById('priceblock_ourprice')) {
            $item[self::UNIT_COST] = preg_replace(self::PRICE_PATTERN, '$1', $price->textContent);
        }
        if ($preview = $item[self::DOM]->getElementById('landingImage')) {
            $item[self::PREVIEW] = trim($preview->getAttribute('data-old-hires'));
        }
        // echo '<img src="' . preg_replace('/(.*)_SL\d+_\.jpg$/', '$1_SL100_.jpg', $item['preview']) . '">';
        return $item;
    }

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
