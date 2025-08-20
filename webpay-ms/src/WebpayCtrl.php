<?php
namespace App;

use Transbank\Webpay\WebpayPlus\Transaction;
use Transbank\Webpay\WebpayPlus;

class WebpayCtrl
{
    public function __construct()
    {
        WebpayPlus::configureForIntegration(TBK_COMMERCE_CODE, TBK_API_KEY);
    }

    /* Crear transacción y redirigir */
    public function init(array $invoice): void
    {
        $id      = (int)$invoice['id'];
        $amount = (int) round($invoice['total_ttc']);
        if ($amount <= 0) throw new \Exception('Monto de factura inválido');
        $buy     = "INV-$id-".time();
        $sess    = "DOLI-$id-".session_id();

        $tx  = new Transaction();
        $res = $tx->create($buy, $sess, $amount, TBK_RETURN_URL);

        header('Location: '.$res->getUrl().'?token_ws='.$res->getToken());
        exit;
    }

    /* Confirmar transacción */
    public function commit(string $token)
    {
        $tx = new Transaction();
        return $tx->commit($token);
    }
}
