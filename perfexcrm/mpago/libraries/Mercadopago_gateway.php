<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Mercadopago_gateway extends App_gateway
{
    protected $sandbox_url = 'https://www.mercadopago.eu/vmp/checkout-test';
    protected $production_url = 'https://www.mercadopago.eu/vmp/checkout';
    
    protected $private_key_url = '/keys/store_private_key.pem';
    protected $public_key_url = '/keys/api_public_key.pem';

    public function __construct()
    {
        /**
        * Call App_gateway __construct function
        */
        parent::__construct();
        
        $this->ci = & get_instance();
        
        /**
         * Gateway unique id - REQUIRED
         */
        $this->setId('mercadopago');

        /**
         * REQUIRED
         * Gateway name
         */
        $this->setName('Mercado Pago');

        /**
         * Add gateway settings
         */
        $this->setSettings([
            [
                'name'  => 'client_id',
                'label' => 'Client ID',
                'type'  => 'input'
            ],
            [
                'name'  => 'client_secret',
                'label' => 'Client Secret',
                'type'  => 'input'
            ],
            [
                'name'  => 'access_token',
                'label' => 'Access Token',
                'type'  => 'input',
            ],
            // Outros campos de configuração, se necessário
        ]);
    }

    private function storeKeys()
{
    $client_id = $this->getSetting('client_id');
    $client_secret = $this->getSetting('client_secret');
    $access_token = $this->getSetting('access_token');

    // Validar se as chaves estão presentes antes de proceder
    if (empty($client_id) || empty($client_secret) || empty($access_token)) {
        return false;
    }

    // Exemplo de armazenamento em variáveis de classe
    $this->client_id = $client_id;
    $this->client_secret = $client_secret;
    $this->access_token = $access_token;

    // Exemplo de armazenamento em um arquivo de configuração
    $config_data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'access_token' => $access_token,
    ];

    $config_path = '/controllers/Process.php'; // Substitua pelo caminho correto
    file_put_contents($config_path, '<?php return ' . var_export($config_data, true) . ';');

    return true; // Indica que as chaves foram armazenadas com sucesso
}

    
    public function process_payment($data)
    {
        $this->storeKeys();
        $cnf = new \Mercadopago\IPC\Config(); 
        $cnf->setIpcURL($this->get_action_url()); 
        $cnf->setLang('en'); 
        $cnf->setPrivateKeyPath(dirname(__FILE__) . $this->private_key_url); 
        $cnf->setAPIPublicKeyPath(dirname(__FILE__) . $this->public_key_url); 
        $cnf->setKeyIndex(1);
        $cnf->setSid($this->getSetting('api_store_id'));
        $cnf->setVersion('1.3'); 
        $cnf->setWallet($this->getSetting('api_wallet_number'));
        
        $customer = new \Mercadopago\IPC\Customer();
        
        if (is_client_logged_in()) {
            $contact    = $this->ci->clients_model->get_contact(get_contact_user_id());
            if ($contact->firstname) {
                $customer->setFirstName($contact->firstname);
            } else {
                $customer->setFirstName('John Santamaria');
            }
            if ($contact->lastname) {
                $customer->setLastName($contact->lastname);
            } else {
                $customer->setLastName('Smith');
            }
        } else {
            $contacts = $this->ci->clients_model->get_contacts($data['invoice']->clientid);
            if (count($contacts) == 1) {
                $contact    = $contacts[0];
                if ($contact['firstname']) {
                    $customer->setFirstName($contact['firstname']);
                } else {
                    $customer->setFirstName('John Santamaria');
                }
                if ($contact['lastname']) {
                    $customer->setLastName($contact['lastname']);
                } else {
                    $customer->setLastName('Smith');
                }
            } else {
                if ($data['invoice']->client->company) {
                    $customer->setFirstName($data['invoice']->client->company);
                    $customer->setLastName($data['invoice']->client->company);
                } else {
                    $customer->setFirstName('John Santamaria');
                    $customer->setLastName('Smith');
                }
            }
        }

        $email = 'name@website.com';
        if (isset($data['invoice']->client->email)) {
            if ($data['invoice']->client->email) {
                $email = $data['invoice']->client->email;
            }
        }
        $customer->setEmail($email);
        $phone = '23568956958';
        if (isset($data['invoice']->client->phonenumber)) {
            if ($data['invoice']->client->phonenumber) {
                $phone = preg_replace('/\s+/', '', $data['invoice']->client->phonenumber);
            }
        }
        $customer->setPhone($phone);
        $country = 'DEU';
        if (isset($data['invoice']->client->country)) {
            if ($data['invoice']->client->country) {
                $ccountry = get_country($data['invoice']->client->country);
                $country = $ccountry->iso3;
            }
        }
        $customer->setCountry($country);
        $address = 'Kleine Bahnstr. 41';
        if (isset($data['invoice']->client->address)) {
            if ($data['invoice']->client->address) {
                $address = $data['invoice']->client->address;
            }
        }
        $customer->setAddress($address);
        $city = 'Hamburg';
        if (isset($data['invoice']->client->city)) {
            if ($data['invoice']->client->city) {
                $city = $data['invoice']->client->city;
            }
        }
        $customer->setCity($city);
        $zip = '20095';
        if (isset($data['invoice']->client->zip)) {
            if ($data['invoice']->client->zip) {
                $zip = $data['invoice']->client->zip;
            }
        }
        $customer->setZip($zip);
        
        $cart = new \Mercadopago\IPC\Cart;
        foreach ($data['invoice']->items as $item) {
            $rate = $item['rate'];
            if ((int)$item['qty'] . '' == $item['qty']) {
                $cart->add($item['description'], (int)$item['qty'], number_format(str_replace(",", "", $item['rate']), 2, ".", ""));
            } else {
                $qty = (int)$item['qty'];
                if ($qty) {
                    $rate = str_replace(",", "", $item['rate']) * (float)$item['qty'] / $qty;
                    $cart->add($item['description'], $qty, number_format($rate, 2, ".", ""));
                } else {
                    $rate = str_replace(",", "", $item['rate']) * (float)$item['qty'];
                    $cart->add($item['description'], 1, number_format($rate, 2, ".", ""));
                }
            }
            
            $tax_data = get_invoice_item_taxes($item['id']);
            if (is_array($tax_data)) {
                if (count($tax_data)) {
                    foreach ($tax_data as $tax) {
                        $cart->add($tax['taxname'], 1, number_format($rate * $tax['taxrate'] / 100, 2, ".", ""));
                    }
                }
            }
        }
        if (isset($data['invoice']->payments)) {
            foreach ($data['invoice']->payments as $payment) {
                $cart->add($payment['name'], 1, number_format(-(float)$payment['amount'], 2, ".", ""));
            }
        }

        $purchase = new \Mercadopago\IPC\Purchase($cnf);
        $purchase->setUrlCancel(site_url('mercadopago_gateway/process/complete_purchase/'));
        $purchase->setUrlOk(site_url('mercadopago_gateway/process/complete_purchase/'));
        $purchase->setUrlNotify(site_url('mercadopago_gateway/process/complete_purchase/'));
        $purchase->setOrderID('Invoice-ID-' . date("YmdHis") . "-". $data['invoice']->id);
        $purchase->setCurrency($data['invoice']->currency_name); 
        $purchase->setNote($data['invoice']->clientnote);
        $purchase->setCustomer($customer); 
        $purchase->setCart($cart);
        $purchase->setCardTokenRequest(\Mercadopago\IPC\Purchase::CARD_TOKEN_REQUEST_PAY_AND_STORE); 
        $purchase->setPaymentParametersRequired(\Mercadopago\IPC\Purchase::PURCHASE_TYPE_FULL);
        $purchase->setPaymentMethod(\Mercadopago\IPC\Purchase::PAYMENT_METHOD_BOTH);
        
        try {
            $purchase->process(); 
        } catch(\Mercadopago\IPC\IPC_Exception $ex) {
            echo $ex->getMessage();
        }
    }

    public function get_action_url()
    {
        return $this->getSetting('test_mode_enabled') == '1' ? $this->sandbox_url : $this->production_url;
    }

    public function finish_payment($post_data)
    {
        $cnf = new \Mercadopago\IPC\Config(); 
        $cnf->setIpcURL($this->get_action_url()); 
        $cnf->setLang('en'); 
        $cnf->setPrivateKeyPath(dirname(__FILE__) . '/keys/store_private_key.pem'); 
        $cnf->setAPIPublicKeyPath(dirname(__FILE__) . '/keys/api_public_key.pem'); 
        $cnf->setKeyIndex(1);
        $cnf->setSid($this->getSetting('api_store_id')); 
        $cnf->setVersion('1.3'); 
        $cnf->setWallet($this->getSetting('api_wallet_number'));
        
        try {
            $responce = Mercadopago\IPC\Response::getInstance($cnf, $post_data, \Mercadopago\IPC\Defines::COMMUNICATION_FORMAT_POST);
        } catch(\Satabank\IPC\IPC_Exception $e) {
            echo 'Error';
        }
        
        $data = $responce->getData(CASE_UPPER);
        
        $invoiceid = substr(str_replace("Invoice-ID-", "", $data['ORDERID']), 15);
        $data['ORDERID'] = $invoiceid;
        
        return $data;
    }
}