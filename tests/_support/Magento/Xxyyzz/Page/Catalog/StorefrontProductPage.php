<?php
namespace Magento\Xxyyzz\Page\Catalog;

use Magento\Xxyyzz\Page\AbstractFrontendPage;

class StorefrontProductPage extends AbstractFrontendPage
{
    /**
     * Declare UI map for this page here. CSS or XPath allowed.
     */
    public static $addToCartButton              = '#product-addtocart-button';

    public static $productName                  = '.base';
    public static $productPrice                 = '.price';
    public static $productStockStatus           = '.stock[title=Availability]>span';
    public static $productSku                   = '.product.attribute.sku>.value';

    public function amOnProductPage($categoryUrlKey)
    {
        $I = $this->acceptanceTester;
        $I->amOnPage(self::route($categoryUrlKey . '.html'));
        $I->waitForPageLoad();
    }

    public function seeProductNameInPage($name)
    {
        $I = $this->acceptanceTester;
        $I->seeElement(sprintf(self::$productName, $name));
    }

    public function seeProductPriceInPage($price)
    {
        $I = $this->acceptanceTester;
        $actualPrice = $I->grabTextFrom(sprintf(self::$productPrice));
        $actualPrice = $I->parseFloat(substr($actualPrice, 1));
        $price = $I->parseFloat($price);
        $pos = strpos($actualPrice, '.');
        if ( $pos !== false) {
            $actualPrice = substr($actualPrice, 0, $pos+3);
        }
        $I->assertEquals($price, $actualPrice);
    }

    public function seeProductStockStatusInPage($status)
    {
        $I = $this->acceptanceTester;
        $I->seeElement(sprintf(self::$productStockStatus, $status));
    }

    public function seeProductSkuInPage($sku)
    {
        $I = $this->acceptanceTester;
        $I->seeElement(sprintf(self::$productSku, $sku));
    }
}