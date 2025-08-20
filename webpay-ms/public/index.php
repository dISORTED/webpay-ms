<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

use App\DolibarrClient;
use App\WebpayCtrl;

session_start();
$doli = new DolibarrClient(DOLI_URL, DOLI_APIKEY);
$wb   = new WebpayCtrl();

$r = $_GET['r'] ?? 'pay';

try {
    /* ───────────────── 1. Mostrar factura + botón Webpay ───────────────── */
    if ($r === 'pay') {
        $fac = (int)($_GET['facid'] ?? 0);
        $inv = $doli->getInvoice($fac);
        $monto = number_format($inv['total_ttc'], 0, ',', '.');

        echo <<<HTML
<!DOCTYPE html><html lang="es"><head>
<meta charset="utf-8">
<title>Pagar factura #{$inv['id']}</title>
<style>
 @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap');
 *{box-sizing:border-box}
 body{margin:0;font-family:'Inter',sans-serif;background:#f8fafc;
      display:flex;justify-content:center;align-items:center;height:100vh}
 .card{background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.12);
       padding:48px 64px;text-align:center;max-width:460px;animation:fadeIn .6s ease}
 h1{margin:0 0 12px;font-size:26px;font-weight:600;color:#111827}
 p{margin:0 0 28px;font-size:16px;color:#374151}
 .btn{display:inline-block;padding:14px 28px;border-radius:8px;font-size:15px;
      background:#2563eb;color:#fff;text-decoration:none;border:none;cursor:pointer;
      transition:all .25s}
 .btn:hover{filter:brightness(110%)}
 @keyframes fadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
</style></head><body>
  <div class="card">
    <h1>Factura #{$inv['id']}</h1>
    <p>Monto a pagar: <strong>$monto&nbsp;CLP</strong></p>
    <form method="post" action="?r=init">
      <input type="hidden" name="facid" value="{$inv['id']}">
      <button class="btn">Pagar con Webpay (Sandbox)</button>
    </form>
  </div>
</body></html>
HTML;
        exit;
    }

    /* ───────────────── 2. Crear transacción en Webpay ─────────────────── */
    if ($r === 'init' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $fac = (int)$_POST['facid'];
        $wb->init($doli->getInvoice($fac));
    }

    /* ───────────────── 3. Confirmación Webpay (token_ws) ─────────────── */
    if ($r === 'commit') {
        $token = $_POST['token_ws'] ?? $_GET['token_ws'] ?? '';
        if (!$token) throw new Exception('Falta token_ws');

        $res    = $wb->commit($token);
        $status = method_exists($res,'isApproved')
                    ? $res->isApproved() : ($res->status === 'AUTHORIZED');

        if (!preg_match('/^INV-(\d+)-/', $res->getBuyOrder(), $m)) {
            throw new Exception('No se pudo inferir la factura desde buyOrder');
        }
        $facid  = (int)$m[1];

        if ($status) {
            $amount = (float)$res->getAmount();
            $auth   = $res->getAuthorizationCode();
            $doli->createInvoicePayment($facid, $amount, "Webpay auth=$auth");
            $doli->setInvoicePaid($facid);                // marca la factura pagada
            header('Location: '.MS_PUBLIC_BASE."/index.php?r=final&ok=1&facid=$facid&amount=$amount");
        } else {
            header('Location: '.MS_PUBLIC_BASE.'/index.php?r=final&ok=0');
        }
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo '<pre>'.$e->getMessage().'</pre>';
    exit;
}

/* ───────────────── 4. Pantalla final (éxito / fracaso) ───────────────── */
if ($r === 'final') {
    $ok     = (int)($_GET['ok'] ?? 0);
    $facid  = (int)($_GET['facid'] ?? 0);
    $amount = htmlspecialchars($_GET['amount'] ?? '');

    $bg     = $ok ? '#f0fff5' : '#fff5f5';
    $color  = $ok ? '#1e7e34' : '#c82333';
    $title  = $ok ? '¡Pago aprobado!' : 'Pago rechazado';
    $emoji  = $ok ? '✅' : '❌';
    $texto  = $ok
        ? "Hemos recibido tu pago por <strong>$amount&nbsp;CLP</strong> correspondiente a la factura&nbsp;#$facid."
        : "Tu transacción no pudo completarse. Si lo deseas, puedes volver e intentarlo nuevamente.";

    echo <<<HTML
<!DOCTYPE html><html lang="es"><head>
<meta charset="utf-8"><title>$title</title>
<style>
 @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap');
 *{box-sizing:border-box}
 body{margin:0;font-family:'Inter',sans-serif;background:$bg;
      display:flex;justify-content:center;align-items:center;height:100vh}
 .card{background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.12);
       padding:48px 64px;text-align:center;max-width:460px;animation:fadeIn .6s ease}
 h1{color:$color;margin:0 0 12px;font-size:28px;font-weight:600}
 p{margin:0 0 22px;font-size:16px;line-height:1.45}
 .btn{display:inline-block;padding:12px 22px;border-radius:8px;font-size:15px;
      background:$color;color:#fff;text-decoration:none;transition:all .25s}
 .btn:hover{filter:brightness(110%)}
 @keyframes fadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
</style></head><body>
  <div class="card">
    <div style="font-size:56px;line-height:1">$emoji</div>
    <h1>$title</h1>
    <p>$texto</p>
HTML;

    if ($ok) {
        echo '<a class="btn" href="'.DOLI_URL.'" target="_blank">Volver al sitio</a>';
    } else {
        echo '<a class="btn" href="javascript:history.back()">Intentar de nuevo</a>';
    }

    echo '</div></body></html>'; exit;
}

