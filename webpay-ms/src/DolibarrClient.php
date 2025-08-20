<?php
namespace App;

/**
 * Cliente ligero para la API REST de Dolibarr.
 * Autenticación vía cabecera DOLAPIKEY.
 */
class DolibarrClient
{
    private string $base;
    private string $key;

    /**
     * @param string $base URL base (ej. https://croquetasurspa2.with10.dolicloud.com)
     * @param string $key  DOLAPIKEY del usuario
     */
    public function __construct(string $base, string $key)
    {
        $this->base = rtrim($base, '/');
        $this->key  = $key;
    }

    /** Llamada genérica */
    private function call(string $method, string $path, array $body = null): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'DOLAPIKEY: ' . $this->key,
                'Content-Type: application/json'
            ]
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false) throw new \Exception(curl_error($ch));
        curl_close($ch);
        return [$code, json_decode($resp, true)];
    }

    /** Obtener una factura */
    public function getInvoice(int $id): array
    {
        [$c, $j] = $this->call('GET', "/api/index.php/invoices/$id");
        if ($c >= 400) throw new \Exception("GET invoice $id → HTTP $c");
        return $j;
    }

    /* =========================================================
     * Registrar pago: crea línea de ingreso en la cuenta
     * bancaria y la enlaza a la factura para marcarla pagada.
     * ========================================================= */
    public function createInvoicePayment(
        int    $invoiceId,
        float  $amount,
        string $note,
        int    $modeId = 4          // ID modo de pago “Tarjeta” (solo informativo)
    ): void
    {
        $bankId = BANK_ID_WEBPAY;   // constante definida en config.php

        /* ---------- 1) Crear la línea de ingreso ------------------------ */
        $payloadLine = [
            "date"   => time(),
            "type"   => "CB",               // código “CB” = pago con tarjeta
            "label"  => $note,
            "amount" => $amount
        ];

        [$codeLine, $jsonLine] = $this->call(
            'POST',
            "/api/index.php/bankaccounts/$bankId/lines",
            $payloadLine
        );
        if ($codeLine >= 400) {
            throw new \Exception("Crear línea banco → HTTP $codeLine");
        }

        /* El API puede devolver un número o {"id": n} */
        $lineId = is_array($jsonLine) ? (int)$jsonLine['id'] : (int)$jsonLine;

        /* ---------- 2) Enlazar la línea con la factura ------------------ */
        $factUrl = DOLI_URL . "/compta/facture/card.php?facid=$invoiceId";

        $payloadLink = [
            "url_id" => $invoiceId,
            "url"    => $factUrl,
            "label"  => "Factura $invoiceId",
            "type"   => "invoice"
        ];

        [$codeLink] = $this->call(
            'POST',
            "/api/index.php/bankaccounts/$bankId/lines/$lineId/links",
            $payloadLink
        );
        if ($codeLink >= 400) {
            throw new \Exception("Link pago-factura → HTTP $codeLink");
        }
        
    }
}
