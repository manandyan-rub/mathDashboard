param(
    [string] $BaseUrl = "http://127.0.0.1:8000",
    [string] $Email = "admin@example.com",
    [Parameter(Mandatory = $true)]
    [string] $Password
)

$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.Drawing

$chromePath = "C:\Program Files\Google\Chrome\Application\chrome.exe"
if (-not (Test-Path -LiteralPath $chromePath)) {
    throw "Google Chrome was not found at $chromePath."
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..\..")
$outputDirectory = Join-Path $repoRoot "storage\framework\testing\three-d-visual"
$profileDirectory = Join-Path $outputDirectory "chrome-profile"
$debugPort = 9331
$script:cdpId = 0

New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null
New-Item -ItemType Directory -Force -Path $profileDirectory | Out-Null

$chrome = Start-Process `
    -FilePath $chromePath `
    -ArgumentList @(
        "--headless=new",
        "--remote-debugging-port=$debugPort",
        "--remote-allow-origins=*",
        "--user-data-dir=$profileDirectory",
        "--no-first-run",
        "--no-default-browser-check",
        "--hide-scrollbars",
        "about:blank"
    ) `
    -WindowStyle Hidden `
    -PassThru

function Wait-ForDebugEndpoint {
    for ($attempt = 0; $attempt -lt 40; $attempt += 1) {
        try {
            return Invoke-RestMethod "http://127.0.0.1:$debugPort/json"
        } catch {
            Start-Sleep -Milliseconds 250
        }
    }

    throw "Chrome DevTools did not become available."
}

function Receive-CdpMessage {
    param([System.Net.WebSockets.ClientWebSocket] $Socket)

    $buffer = New-Object byte[] 65536
    $builder = [System.Text.StringBuilder]::new()

    do {
        $segment = [System.ArraySegment[byte]]::new($buffer)
        $result = $Socket.ReceiveAsync(
            $segment,
            [System.Threading.CancellationToken]::None
        ).GetAwaiter().GetResult()

        if ($result.MessageType -eq [System.Net.WebSockets.WebSocketMessageType]::Close) {
            throw "Chrome closed the DevTools connection."
        }

        [void] $builder.Append(
            [System.Text.Encoding]::UTF8.GetString(
                $buffer,
                0,
                $result.Count
            )
        )
    } while (-not $result.EndOfMessage)

    return $builder.ToString() | ConvertFrom-Json
}

function Send-CdpCommand {
    param(
        [System.Net.WebSockets.ClientWebSocket] $Socket,
        [string] $Method,
        [hashtable] $Params = @{}
    )

    $script:cdpId += 1
    $id = $script:cdpId
    $payload = @{
        id = $id
        method = $Method
        params = $Params
    } | ConvertTo-Json -Depth 20 -Compress
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
    $segment = [System.ArraySegment[byte]]::new($bytes)

    [void] $Socket.SendAsync(
        $segment,
        [System.Net.WebSockets.WebSocketMessageType]::Text,
        $true,
        [System.Threading.CancellationToken]::None
    ).GetAwaiter().GetResult()

    do {
        $message = Receive-CdpMessage -Socket $Socket
    } while ($message.id -ne $id)

    if ($message.error) {
        throw "$Method failed: $($message.error.message)"
    }

    return $message.result
}

function Invoke-JavaScript {
    param(
        [System.Net.WebSockets.ClientWebSocket] $Socket,
        [string] $Expression
    )

    $result = Send-CdpCommand `
        -Socket $Socket `
        -Method "Runtime.evaluate" `
        -Params @{
            expression = $Expression
            awaitPromise = $true
            returnByValue = $true
        }

    if ($result.exceptionDetails) {
        throw "Browser evaluation failed: $($result.exceptionDetails | ConvertTo-Json -Depth 10 -Compress)"
    }

    return $result.result.value
}

function Navigate {
    param(
        [System.Net.WebSockets.ClientWebSocket] $Socket,
        [string] $Url,
        [int] $WaitMilliseconds = 1800
    )

    Send-CdpCommand `
        -Socket $Socket `
        -Method "Page.navigate" `
        -Params @{ url = $Url } | Out-Null
    Start-Sleep -Milliseconds $WaitMilliseconds
}

function Save-Screenshot {
    param(
        [System.Net.WebSockets.ClientWebSocket] $Socket,
        [string] $Name
    )

    $capture = Send-CdpCommand `
        -Socket $Socket `
        -Method "Page.captureScreenshot" `
        -Params @{
            format = "png"
            captureBeyondViewport = $false
        }
    $path = Join-Path $outputDirectory "$Name.png"
    [System.IO.File]::WriteAllBytes(
        $path,
        [Convert]::FromBase64String($capture.data)
    )

    return $path
}

function Assert-Canvas {
    param(
        [System.Net.WebSockets.ClientWebSocket] $Socket,
        [string] $Shape
    )

    $result = Invoke-JavaScript -Socket $Socket -Expression @"
(() => {
  const canvas = document.querySelector(".geometry-canvas-shell canvas");
  if (!canvas) return { exists: false };
  const bounds = canvas.getBoundingClientRect();
  return {
    exists: true,
    width: canvas.width,
    height: canvas.height,
    x: bounds.left + window.scrollX,
    y: bounds.top + window.scrollY,
    renderedWidth: bounds.width,
    renderedHeight: bounds.height
  };
})()
"@

    if (
        -not $result.exists -or
        $result.renderedWidth -lt 100 -or
        $result.renderedHeight -lt 100
    ) {
        throw "$Shape produced a blank or incorrectly sized 3D canvas: $($result | ConvertTo-Json -Compress)"
    }

    $capture = Send-CdpCommand `
        -Socket $Socket `
        -Method "Page.captureScreenshot" `
        -Params @{
            format = "png"
            captureBeyondViewport = $true
            clip = @{
                x = [Math]::Max(0, [double] $result.x)
                y = [Math]::Max(0, [double] $result.y)
                width = [double] $result.renderedWidth
                height = [double] $result.renderedHeight
                scale = 1
            }
        }
    $bytes = [Convert]::FromBase64String($capture.data)
    $stream = [System.IO.MemoryStream]::new($bytes)
    $bitmap = [System.Drawing.Bitmap]::new($stream)
    $minimum = 255.0
    $maximum = 0.0
    $xStep = [Math]::Max(1, [Math]::Floor($bitmap.Width / 64))
    $yStep = [Math]::Max(1, [Math]::Floor($bitmap.Height / 64))

    for ($x = 0; $x -lt $bitmap.Width; $x += $xStep) {
        for ($y = 0; $y -lt $bitmap.Height; $y += $yStep) {
            $pixel = $bitmap.GetPixel($x, $y)
            $luminance =
                0.2126 * $pixel.R +
                0.7152 * $pixel.G +
                0.0722 * $pixel.B
            $minimum = [Math]::Min($minimum, $luminance)
            $maximum = [Math]::Max($maximum, $luminance)
        }
    }

    $bitmap.Dispose()
    $stream.Dispose()
    $luminanceRange = $maximum - $minimum
    if ($luminanceRange -lt 5) {
        throw "$Shape produced a visually blank 3D canvas (luminance range $luminanceRange)."
    }

    Write-Output "$Shape canvas: $($result.renderedWidth)x$($result.renderedHeight), luminance range $([Math]::Round($luminanceRange, 2))"
}

try {
    $targets = Wait-ForDebugEndpoint
    $target = $targets | Where-Object { $_.type -eq "page" } | Select-Object -First 1
    if (-not $target) {
        throw "Chrome did not expose a page target."
    }

    $socket = [System.Net.WebSockets.ClientWebSocket]::new()
    [void] $socket.ConnectAsync(
        [Uri] $target.webSocketDebuggerUrl,
        [System.Threading.CancellationToken]::None
    ).GetAwaiter().GetResult()

    Send-CdpCommand -Socket $socket -Method "Page.enable" | Out-Null
    Send-CdpCommand -Socket $socket -Method "Runtime.enable" | Out-Null
    Send-CdpCommand `
        -Socket $socket `
        -Method "Emulation.setDeviceMetricsOverride" `
        -Params @{
            width = 1440
            height = 1000
            deviceScaleFactor = 1
            mobile = $false
        } | Out-Null

    Navigate -Socket $socket -Url "$BaseUrl/login"
    for ($attempt = 0; $attempt -lt 20; $attempt += 1) {
        $loginReady = Invoke-JavaScript `
            -Socket $socket `
            -Expression 'Boolean(document.querySelector(''input[type="email"]'') && document.querySelector(''input[type="password"]''))'
        if ($loginReady) {
            break
        }
        Start-Sleep -Milliseconds 250
    }
    if (-not $loginReady) {
        Save-Screenshot -Socket $socket -Name "login-not-ready" | Out-Null
        $diagnostic = Invoke-JavaScript `
            -Socket $socket `
            -Expression '({ href: location.href, title: document.title, text: document.body?.innerText?.slice(0, 500) })'
        throw "The login page did not render its inputs: $($diagnostic | ConvertTo-Json -Compress)"
    }

    $emailJson = $Email | ConvertTo-Json -Compress
    $passwordJson = $Password | ConvertTo-Json -Compress
    $loginSubmitted = Invoke-JavaScript -Socket $socket -Expression @"
(() => {
  const email = document.querySelector('input[type="email"]');
  const password = document.querySelector('input[type="password"]');
  const submit = document.querySelector("form button");
  if (!email || !password || !submit) return false;
  const setValue = (selector, value) => {
    const input = document.querySelector(selector);
    const setter = Object.getOwnPropertyDescriptor(
      HTMLInputElement.prototype,
      "value"
    ).set;
    setter.call(input, value);
    input.dispatchEvent(new Event("input", { bubbles: true }));
  };
  setValue('input[type="email"]', $emailJson);
  setValue('input[type="password"]', $passwordJson);
  submit.click();
  return true;
})()
"@
    if (-not $loginSubmitted) {
        throw "The visual smoke test could not submit the login form."
    }

    for ($attempt = 0; $attempt -lt 30; $attempt += 1) {
        Start-Sleep -Milliseconds 250
        $path = Invoke-JavaScript -Socket $socket -Expression "location.pathname"
        if ($path -ne "/login") {
            break
        }
    }

    if ($path -eq "/login") {
        throw "The visual smoke test could not authenticate."
    }

    Navigate -Socket $socket -Url "$BaseUrl/mathematics/threeD" -WaitMilliseconds 3500
    Invoke-JavaScript -Socket $socket -Expression @"
(() => {
  window.__visualSmokeErrors = [];
  window.addEventListener("error", (event) => {
    window.__visualSmokeErrors.push(
      event.error?.stack || event.message || "Unknown browser error"
    );
  });
  window.addEventListener("unhandledrejection", (event) => {
    window.__visualSmokeErrors.push(
      event.reason?.stack || String(event.reason)
    );
  });
  return true;
})()
"@ | Out-Null

    foreach ($shape in @("Cuboid", "Cube", "Sphere", "Cone", "Cylinder", "Stairs")) {
        $shapeJson = $shape | ConvertTo-Json -Compress
        $clicked = Invoke-JavaScript -Socket $socket -Expression @"
(() => {
  const label = $shapeJson;
  const button = [...document.querySelectorAll(".geometry-shape-button")].find(
    (item) => item.querySelector("span")?.textContent.trim() === label
  );
  if (!button) return false;
  button.click();
  return true;
})()
"@
        if (-not $clicked) {
            throw "Could not activate the $shape calculator."
        }

        Start-Sleep -Milliseconds 900
        $activeLabel = Invoke-JavaScript `
            -Socket $socket `
            -Expression 'document.querySelector(".geometry-preview-header h3")?.textContent?.trim()'
        if ($activeLabel -ne $shape) {
            $diagnostic = Invoke-JavaScript `
                -Socket $socket `
                -Expression 'window.__visualSmokeErrors'
            throw "The $shape control did not activate; preview still shows $activeLabel. $($diagnostic | ConvertTo-Json -Depth 10 -Compress)"
        }
        Assert-Canvas -Socket $socket -Shape $shape
        Save-Screenshot `
            -Socket $socket `
            -Name ("desktop-" + $shape.ToLowerInvariant()) | Out-Null
    }

    Send-CdpCommand `
        -Socket $socket `
        -Method "Emulation.setDeviceMetricsOverride" `
        -Params @{
            width = 390
            height = 844
            deviceScaleFactor = 1
            mobile = $true
        } | Out-Null
    Navigate -Socket $socket -Url "$BaseUrl/mathematics/threeD" -WaitMilliseconds 2500
    $mobileActivated = Invoke-JavaScript -Socket $socket -Expression @"
(() => {
  const button = [...document.querySelectorAll(".geometry-shape-button")].find(
    (item) => item.querySelector("span")?.textContent.trim() === "Cuboid"
  );
  if (!button) return false;
  button.click();
  return true;
})()
"@
    if (-not $mobileActivated) {
        throw "Could not activate the mobile Cuboid preview."
    }
    Start-Sleep -Milliseconds 700
    Assert-Canvas -Socket $socket -Shape "Cuboid mobile"
    Save-Screenshot -Socket $socket -Name "mobile-cuboid" | Out-Null

    Write-Output "Screenshots: $outputDirectory"
} finally {
    if ($socket) {
        $socket.Dispose()
    }
    if ($chrome -and -not $chrome.HasExited) {
        Stop-Process -Id $chrome.Id -Force
    }
}
