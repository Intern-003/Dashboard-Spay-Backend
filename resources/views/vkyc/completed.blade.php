<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Video KYC Status</title>

  <!-- ✅ Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body{
      min-height:100vh;
      background: radial-gradient(circle at top, #0d6efd 0%, #0b2c6f 55%, #081b3d 100%);
    }
    .card-shadow{
      box-shadow: 0 18px 50px rgba(0,0,0,.25);
      border: 0;
      border-radius: 18px;
    }
    .icon-circle{
      width: 84px;
      height: 84px;
      border-radius: 50%;
      display:flex;
      align-items:center;
      justify-content:center;
      margin: 0 auto 14px;
      font-size: 42px;
    }
    .soft-success{ background: rgba(25,135,84,.12); color:#198754; }
    .soft-warning{ background: rgba(255,193,7,.15); color:#ffc107; }
    .badge-soft{
      background: rgba(13,110,253,.12);
      color:#0d6efd;
      border: 1px solid rgba(13,110,253,.25);
      font-weight: 600;
    }
    .small-muted{ color: rgba(255,255,255,.75); }
  </style>
</head>

<body class="d-flex align-items-center">
  <div class="container py-4">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-7 col-lg-5">
        <div class="card card-shadow">
          <div class="card-body p-4 p-md-5 text-center">

            @php
              $isCompleted = in_array($session->status, ['uploaded','verified']);
              $redirectUrl = $session->redirect_url ?? null;
            @endphp

            @if($isCompleted)
              <div class="icon-circle soft-success">✅</div>
              <h4 class="fw-bold mb-2">Video KYC Completed</h4>
              <p class="text-secondary mb-3">This link has already been used and cannot be opened again.</p>
            @else
              <div class="icon-circle soft-warning">⚠️</div>
              <h4 class="fw-bold mb-2">Video KYC Not Available</h4>
              <p class="text-secondary mb-3">This link is invalid or already used.</p>
            @endif

            <div class="d-flex justify-content-center mb-3">
              <span class="badge rounded-pill badge-soft px-3 py-2">
                Status: {{ ucfirst($session->status) }}
              </span>
            </div>
            
            <div class="alert alert-light border d-flex align-items-center justify-content-center gap-2 mb-3">
              <span class="text-secondary">This window will close in</span>
              <span class="fw-bold" id="countdown">3</span>
              <span class="text-secondary">seconds...</span>
            </div>

            <button type="button" class="btn btn-primary w-100 py-2 fw-semibold" onclick="window.close();">
              Close Window
            </button>

            <p class="small small-muted mt-3 mb-0">
              If it doesn't close automatically, click <b>Close Window</b>.
            </p>

            <script>
              (function(){
                let sec = 3;
                const el = document.getElementById('countdown');

                const timer = setInterval(() => {
                  sec--;
                  if (el) el.textContent = sec;
                  if (sec <= 0) {
                    clearInterval(timer);
                    window.close();
                  }
                }, 1000);
              })();
            </script>

          </div>
        </div>

        <div class="text-center mt-3">
          <span class="small small-muted">© {{ date('Y') }} Spay Fintech Pvt Ltd</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ✅ Bootstrap JS (optional) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>