<?php
// Initialize dependencies
require_once __DIR__ . '/../../api/key.php';
require_once __DIR__ . '/../../api/api.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/qrcode.php';

// Get confirmation code from query string
$confirmation = $_GET['confirmation'] ?? null;

// Validate confirmation code exists
if (!$confirmation) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Error</title>
    <style>body{font-family:Arial,sans-serif;background:#0f1724;color:#e6eef8;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    .box{text-align:center;padding:40px;border:1px solid #374151;border-radius:12px}
    h1{color:#f87171;margin-bottom:8px}p{color:#94a3b8}</style></head>
    <body><div class="box">
        <h1>Ticket Generation Failed</h1>
        <p>Confirmation code is required</p>
    </div></body></html>';
    exit;
}

// Fetch ticket from database
$stmt = $pdo->prepare('SELECT * FROM "Tickets" WHERE confirmation_code = ? LIMIT 1');
$stmt->execute([$confirmation]);
$ticketRow = $stmt->fetch();

// Ticket not found
if (!$ticketRow) {
    http_response_code(404);
    echo '';
    exit;
}

// QR code data
$qrData = 'Your confirmation code is ' . $confirmation; 

// Fetch flight details from API
$flightId = $ticketRow['flight_id'] ?? null;

$api = new AirportsAPI(AIRPORTS_API_KEY);
$flight = null;
if (!empty($flightId)) {
    try {
        $flight = $api->getFlightById($flightId);
    } catch (Throwable $e) {
        $flight = null;
    }
}

// Build passenger name from parts
$passengerName = trim(
    ($ticketRow['name_first'] ?? '') . ' ' .
    ($ticketRow['name_middle'] ?? '') . ' ' .
    ($ticketRow['name_last'] ?? '')
);

if ($passengerName === '') $passengerName = 'Passenger';

date_default_timezone_set('America/New_York');

// Format milliseconds to time string
function fmtTime($ms) {
    if (!$ms) return 'TBD';
    return date('h:i A', $ms / 1000);
}

$dest = $flight['landingAt'];
$generated = date('d-m-Y h:i A');

// Extract flight times
$ticket = [
    'departure_time' => $flight['departFromSender']
        ? date('h:i A', $flight['departFromSender'] / 1000)
        : 'TBD',
    'arrival_time' => $flight['arriveAtReceiver']
        ? date('h:i A', $flight['arriveAtReceiver'] / 1000)
        : 'TBD',
      ];

// Generate HTML ticket (fallback format)
$html = <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket {$passengerName}</title>
    <style>
        body{font-family: Inter, Arial, Helvetica, sans-serif;background:#0f1724;color:#e6eef8;padding:20px}
        .card{max-width:760px;margin:0 auto;background:linear-gradient(90deg,#0b1220,#0f1724);border:1px solid #374151;border-radius:12px;padding:28px}
        .brand{letter-spacing:0.2em;color:#93c5fd;font-size:12px;margin-bottom:8px}
        .title{font-size:28px;font-weight:700;margin:0 0 6px}
        .passenger{font-size:20px;font-weight:700;margin-bottom:12px}
        .row{display:flex;justify-content:space-between;align-items:center}
        .seat-area{min-width:200px;text-align:center}
        .seat{font-size:48px;color:#60a5fa;font-weight:800}
        .gate{display:inline-block;padding:6px 10px;border-radius:9999px;border:1px solid #334155;color:#60a5fa;margin-top:8px}
        .info{margin-top:18px;background:transparent;padding:12px;border-radius:8px}
        .label{color:#94a3b8;font-size:12px}
        .value{color:#e6eef8;font-weight:600}
        .small{color:#94a3b8;font-size:11px}
        .footer{margin-top:18px;color:#9ca3af;font-size:12px}
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">BDPA AIRPORTS</div>
        <div class="title">Flight Ticket</div>
        <div class="passenger">{$passengerName}</div>

        <div class="row">
            <div class="seat-area">
                <div class="label">Seat</div>
                <div class="seat">{$ticketRow['seat']}</div>
                <div class="gate">Gate {$flight['gate']}</div>
                <div style="margin-top:8px;color:#cbd5e1">From <strong style="color:#e6eef8">{$flight['comingFrom']}</strong> &rarr; To <strong style="color:#e6eef8">{$dest}</strong></div>
            </div>

            <div style="text-align:right">
                <div style="font-size:20px;font-weight:700">{$flight['airline']} </div>
                <div class="label" style="margin-top:8px">Status</div>
                <div class="value">{$flight['status']}</div>
            </div>
        </div>

        <div class="info">
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #0b1220">
                <div><div class="label">Confirmation</div><div class="value">{$confirmation}</div></div>
                <div><div class="label">Ticket ID</div><div class="value">{$ticketRow['ticket_id']}</div></div>
                <div><div class="label">Flight ID</div><div class="value">{$flightId}</div></div>
            </div>

            <div style="display:flex;justify-content:space-between;padding:6px 0">
                <div><div class="label">Departure</div><div class="value">{$ticket['departure_time']}</div></div>
                <div><div class="label">Arrival</div><div class="value">{$ticket['arrival_time']}</div></div>
            </div>
        </div>

        <div class="footer">Generated: {$generated}</div>
    </div>
</body>
</html>
HTML;

// Return HTML if requested via query parameter
if (isset($_GET['format']) && $_GET['format'] === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

// Fallback to HTML if GD image library unavailable
if (!function_exists('imagecreatetruecolor')) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="ticket-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $confirmation) . '.html"');
    echo $html;
    exit;
}

// Create PNG image for ticket
$w = 1400; $h = 520;
$img = imagecreatetruecolor($w, $h);

// Convert hex color to RGB values
function hex2rgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $r = hexdec(str_repeat($hex[0],2));
        $g = hexdec(str_repeat($hex[1],2));
        $b = hexdec(str_repeat($hex[2],2));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return [$r,$g,$b];
}

// Draw gradient background
$top = hex2rgb('#ffffff');
$bottom = hex2rgb('#b6deff');
for ($y=0;$y<$h;$y++) {
    $t = $y / ($h-1);
    $r = (int)($top[0]*(1-$t) + $bottom[0]*$t);
    $g = (int)($top[1]*(1-$t) + $bottom[1]*$t);
    $b = (int)($top[2]*(1-$t) + $bottom[2]*$t);
    $col = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $w, $y, $col);
}

// Draw main card background
$cardCol = imagecolorallocate($img, 15, 23, 36);
imagefilledrectangle($img, 30, 40, $w-30, $h-60, $cardCol);

// Draw vertical divider line
$black = imagecolorallocate($img,0,0,0);
$dividerX = 180;
$dividerColor = imagecolorallocate($img,60,70,80);
imageline($img, $dividerX, 80, $dividerX, $h-100, $dividerColor);

// Define text colors
$blue = imagecolorallocate($img, 38, 131, 255);
$orange = imagecolorallocate($img, 255, 140, 40);
$textWhite = imagecolorallocate($img, 240,245,250);
$muted = imagecolorallocate($img, 150,165,180);

// Column positions
$leftCol = 200; $center = 700; $rightCol = 980;

// Bitmap font scaling function
function draw_scaled_text($dst, $text, $x, $y, $scale, $color) {
    $font = 5;
    $fw = imagefontwidth($font);
    $fh = imagefontheight($font);
    $tw = max(1, $fw * strlen($text));
    $th = $fh;
    $tmp = imagecreatetruecolor($tw, $th);
    $bg = imagecolorallocate($tmp, 15, 23, 36);
    imagefilledrectangle($tmp, 0, 0, $tw, $th, $bg);
    $fg = imagecolorallocate($tmp, 255, 255, 255);
    imagestring($tmp, $font, 0, 0, $text, $fg);
    $sw = max(1, (int)($tw * $scale));
    $sh = max(1, (int)($th * $scale));
    $scaled = imagecreatetruecolor($sw, $sh);
    imagecopyresampled($scaled, $tmp, 0,0,0,0, $sw, $sh, $tw, $th);
    imagecopy($dst, $scaled, $x, $y, 0, 0, $sw, $sh);
    imagedestroy($tmp);
    imagedestroy($scaled);
}

// Draw left column text
$y = 100;
draw_scaled_text($img, 'BOARDING PASS', $leftCol, $y, 3.2, $textWhite); $y += 90;
draw_scaled_text($img, ($flight['airline'] ?? ''), $leftCol, $y, 2.2, $muted); $y += 70;
draw_scaled_text($img, 'Passenger: ' . $passengerName, $leftCol, $y, 1.6, $textWhite); $y += 60;
draw_scaled_text($img, 'From: ' . ($flight['comingFrom'] ?? ''), $leftCol, $y, 1.4, $textWhite); $y += 48;
draw_scaled_text($img, 'To: ' . $dest, $leftCol, $y, 1.4, $textWhite); $y += 48;

// Draw center and right columns
$ry = 160;
draw_scaled_text($img, 'Confirmation #: ' . ($confirmation ?? 'TBD'), $center, $ry, 1.8, $textWhite);
draw_scaled_text($img, 'Gate: ' . (strtoupper($flight['gate'] ?? 'TBD')), $center, $ry+80, 2.6, $textWhite);
draw_scaled_text($img, 'Seat: ' . ($ticketRow['seat'] ?? 'TBD'), $rightCol, $ry+80, 2.6, $textWhite);

// Draw footer timestamp
draw_scaled_text($img, 'Generated: ' . $generated, $leftCol, $h-60, 1.0, $muted);

// Output PNG with QR code
if (ob_get_length()) ob_end_clean();
try { 
    // Generate and embed QR code
    $qrGen = new QRCode($qrData, ['s' => 'qr-m', 'sf' => 8, 'p' => 1]);
    $qrGd  = $qrGen->render_image();

    $qrSize = 130;
    $qrX    = $w - 30 - $qrSize - 20;
    $qrY    = $h - 60 - $qrSize - 15;

    imagecopyresampled($img, $qrGd, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qrGd), imagesy($qrGd));
    imagedestroy($qrGd);

    // Send PNG response
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="ticket-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $passengerName) . '.png"');
    imagepng($img);
    imagedestroy($img);
} catch (Throwable $e) {
    // Error handling on QR generation
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Error</title>
    <style>body{font-family:Arial,sans-serif;background:#0f1724;color:#e6eef8;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    .box{text-align:center;padding:40px;border:1px solid #374151;border-radius:12px}
    h1{color:#f87171;margin-bottom:8px}p{color:#94a3b8}</style></head>
    <body><div class="box">
        <h1>Ticket Generation Failed</h1>
        <p>Could not generate ticket for confirmation <strong style="color:#e6eef8">' . htmlspecialchars($confirmation) . '</strong></p>
        <p style="font-size:12px;margin-top:16px">' . htmlspecialchars($e->getMessage()) . '</p>
    </div></body></html>';
}

?>