<?php

if (!defined('_PS_VERSION_'))
    exit;

class Affili extends Module
{
    public function __construct()
    {
        $this->name = 'affili';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'Affili';
        $this->need_instance = 1;

        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => '1.7.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Affili');
        $this->description = $this->l('Integrate the Affili tracking script in your shop');

        $this->confirmUninstall = $this->l('Are you sure you want to delete Affili from your shop?');
    }

    public function install()
    {
        return (parent::install() && $this->registerHook('displayHeader') && $this->registerHook('displayOrderConfirmation'));
    }

    public function uninstall()
    {
        return (
            parent::uninstall() && Configuration::deleteByName('AFFILI_SETTINGS')
        );
    }

    public function hookDisplayHeader()
    {
        if (!$this->context->smarty->getTemplateVars('is_order')) {
            $this->context->smarty->assign('is_order', false);

            return $this->display(__FILE__, 'views/templates/snippet.tpl');
        }
    }

    public function hookDisplayOrderConfirmation($params)
    {
        // Setting parameters
        $order = isset($params['order']) ? $params['order'] : (
            isset($params['objOrder']) ? $params['objOrder'] : null
        );

        if ($order) {
            $id_cart_rule = current($order->getCartRules())['id_cart_rule'] ?? 0;
            $code = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `code` FROM `'._DB_PREFIX_.'cart_rule` WHERE `id_cart_rule` = \''.$id_cart_rule.'\'');

            $amount = (isset($order->total_paid_tax_excl)) ? $order->total_paid_tax_excl : 0;
            $shipping = (isset($order->total_shipping_tax_incl)) ? $order->total_shipping_tax_incl : 0;
            $conversion_amount = $amount - $shipping;

            $products = [];
            foreach ($order->getProducts() as $product) {
                $products[] = [
                    'name' => $product['product_name'],
                    'unit_price' => $product['product_price'],
                    'quantity' => $product['product_quantity'],
                    'total_price' => $product['product_price'] * $product['product_quantity'],
                ];
            }

            $options = [
                'coupon' => $code,
                'products' => $products,
            ];
            $options = count($options) ? json_encode($options) : json_encode($options, JSON_FORCE_OBJECT);

            $this->context->smarty->assign('external_id', $order->id);
            $this->context->smarty->assign('conversion_amount', $conversion_amount);
            $this->context->smarty->assign('is_order', true);
            $this->context->smarty->assign('options', $options);

            return $this->display(__FILE__, 'views/templates/confirmed.tpl');
        }
    }
}