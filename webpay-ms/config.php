<?php
/* ========= CONFIGURACIÓN ========= */

/* Dolibarr (DoliCloud) */
const DOLI_URL    = 'https://croquetasurspa2.with10.dolicloud.com';  // sin “/” final
const DOLI_APIKEY = '5c95SYMJ24Q06ghwhlDTzndcd7BVXW09';                       // 32-caracteres

/* ID de la cuenta bancaria o caja donde caerá el abono Webpay
   (lo ves en Finanzas ▸ Banco y caja ▸ Cuentas → la URL termina en id=NUM) */
const BANK_ID_WEBPAY = 1;          // ← cambia 1 por el ID real de tu cuenta “Caja” o banco

/* Transbank – Sandbox */
const TBK_COMMERCE_CODE = '597055555532';
const TBK_API_KEY       = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';

/* Base pública del microservicio  (URL que abre GitHub Codespaces) */
const MS_PUBLIC_BASE    = 'https://verbose-xylophone-r944p47r5rp3xgg9-8080.app.github.dev/';

/* URLs que Transbank llamará */
const TBK_RETURN_URL = MS_PUBLIC_BASE.'/index.php?r=commit';
const TBK_FINAL_URL  = MS_PUBLIC_BASE.'/index.php?r=final';

