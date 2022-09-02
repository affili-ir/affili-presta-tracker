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
		$this->is_configurable = 1;

        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => '1.7.99',
        ];
        $this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Affili');
		$this->description = $this->l('Integrate the Affili tracking script in your shop');

		$this->confirmUninstall = $this->l('Are you sure you want to delete Affili from your shop?');

		if ($this->id && !Configuration::get('ACCOUNT_ID')) {
			$this->warning = $this->l('You have not yet set your Affili Account ID');
		}
	}

	public function install()
	{
		return (parent::install() && $this->registerHook('displayHeader') && $this->registerHook('displayOrderConfirmation'));
	}

	public function uninstall()
	{
		return (
			parent::uninstall()
			&& Configuration::deleteByName('ACCOUNT_ID')
			&& Configuration::deleteByName('AFFILI_SETTINGS')
		);
	}

	public function getContent()
	{
		$output = '<h2 style="border-bottom: 1px solid #ddd; margin-bottom:20px;">'.$this->l('Affili').'</h2>';

		// check if is subnit save data
		if (Tools::isSubmit('submitAff')) {
			Configuration::updateValue('ACCOUNT_ID', Tools::getValue('accountId'));
			$output .= '
			<div class="conf confirm" style="text-align: center;margin-bottom: 8px;background: green;padding: 10px;color: #fff;font-size: 15px;">
				'.$this->l('Settings updated').'
			</div>';
		}

		return $output.$this->displayForm();
	}

	public function displayForm()
	{
		$output = '
		<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
			<input type="hidden" value="accountId" />
			<div class="form-group">
				<label for="accountId">'.$this->l('Your Affili Account ID').'</label>
				<input required type="text" class="form-control" name="accountId" id="accountId" value="'.Tools::safeOutput(Tools::getValue('accountId', Configuration::get('ACCOUNT_ID'))).'">
				<small id="accountIdHelp" class="form-text text-muted">'.$this->l('Copy the Account Id from the affili panel.').'</small>
			</div>

			<input type="submit" name="submitAff" value="'.$this->l('Save').'" class="button" />
		</form>';

		$output = '<div class="row"><div class="col-md-6 col-sm-12">'.$output.'</div></div>';

		return $output;
	}

	public function hookDisplayHeader()
	{
		if (!$this->context->smarty->getTemplateVars('is_order')) {
			$this->context->smarty->assign('account_id', Configuration::get('ACCOUNT_ID'));
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
			$currency = Currency::getCurrencyInstance((int)$order->id_currency);
			$multiplier = $currency->iso_code === 'IRR' ? 1 : 10;

			$id_cart_rule = current($order->getCartRules())['id_cart_rule'] ?? 0;
			$code = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `code` FROM `'._DB_PREFIX_.'cart_rule` WHERE `id_cart_rule` = \''.$id_cart_rule.'\'');

			$amount = (isset($order->total_paid_tax_excl)) ? $order->total_paid_tax_excl : 0;
			$shipping = (isset($order->total_shipping_tax_incl)) ? $order->total_shipping_tax_incl : 0;
			$conversion_amount = ($amount - $shipping) * $multiplier;

			$products = [];
			foreach ($order->getProducts() as $product) {
				$products[] = [
					'sku' => $product['product_name'],
					'unit_price' => $product['product_price'] * $multiplier,
					'quantity' => $product['product_quantity'],
					'total_price' => $product['product_price'] * $product['product_quantity'] * $multiplier,
				];
			}

			$options = [
				'coupon' => $code,
				'products' => $products,
			];
			$options = count($options) ? json_encode($options) : json_encode($options, JSON_FORCE_OBJECT);

			$account_id = Configuration::get('ACCOUNT_ID');

			$this->context->smarty->assign('external_id', $order->id);
			$this->context->smarty->assign('conversion_amount', $conversion_amount);
			$this->context->smarty->assign('account_id', $account_id);
			$this->context->smarty->assign('is_order', true);
			$this->context->smarty->assign('options', $options);

			return $this->display(__FILE__, 'views/templates/confirmed.tpl');
		}
	}
}