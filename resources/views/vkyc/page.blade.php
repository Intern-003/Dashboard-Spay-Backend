<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Video KYC</title>

  <!-- ✅ Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body{
      min-height:100vh;
      background: radial-gradient(circle at top, #0d6efd 0%, #0b2c6f 55%, #081b3d 100%);
    }
    .card-kyc{
      border:0;
      border-radius:18px;
      box-shadow: 0 18px 50px rgba(0,0,0,.25);
      overflow:hidden;
    }
    .header{
      background: rgba(255,255,255,.06);
      color: #fff;
      padding: 14px 16px;
    }
    .video-wrap{
      background:#000;
      border-radius:14px;
      overflow:hidden;
    }
    video{ width:100%; height:auto; display:block; }
    .prompt-box{
      background:#f8f9fa;
      border:1px solid #e9ecef;
      border-radius:14px;
      padding:14px;
    }
    .badge-soft{
      background: rgba(13,110,253,.12);
      color:#0d6efd;
      border: 1px solid rgba(13,110,253,.25);
      font-weight: 600;
    }
    .small-muted{ color: rgba(255,255,255,.75); }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
	#preview{ transform: scaleX(-1) !important; }
  </style>
</head>

<body class="d-flex align-items-center">
  <div class="container py-4">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-6">

        <div class="card card-kyc">
          <div class="header d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-bold">Video KYC</div>
              <div class="small small-muted">Session: <span class="mono">{{ $session->session_id }}</span></div>
            </div>
            <div class="text-end">
              <div class="small small-muted">Current Time</div>
              <div class="fw-semibold mono" id="clock">--:--:--</div>
            </div>
          </div>

          <div class="card-body p-3 p-md-4 bg-white">
            <div class="video-wrap mb-3">
              <video id="preview" playsinline autoplay muted></video>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
              <span class="badge rounded-pill badge-soft px-3 py-2">
                Recording: <span class="mono" id="recTimer">00:00</span>
              </span>

              <div class="form-check form-switch ms-auto">
                <input class="form-check-input" type="checkbox" role="switch" id="voiceToggle" checked>
                <label class="form-check-label" for="voiceToggle">Voice Prompts</label>
              </div>
            </div>

            <div class="prompt-box mb-3">
              <div class="fw-semibold mb-1">Instruction</div>
              <div id="prompt">Click <b>Start</b> to begin.</div>
              <div class="mt-2 small text-secondary">
                Tip: Keep your face visible. For PAN/Aadhaar, cover sensitive digits if required.
              </div>
            </div>

            <div class="d-grid gap-2 d-sm-flex">
              <button id="startBtn" class="btn btn-primary btn-lg flex-fill">Start</button>
              <button id="stopBtn" class="btn btn-danger btn-lg flex-fill" disabled>Stop & Upload</button>
            </div>

            <div class="mt-3">
              <div class="alert alert-info py-2 mb-2" id="statusBox" style="display:none;"></div>
              <div class="alert alert-danger py-2 mb-0" id="warnBox" style="display:none;"></div>
            </div>
          </div>
        </div>

        <div class="text-center mt-3">
          <span class="small small-muted">© {{ date('Y') }} Spay Fintech Pvt Ltd</span>
        </div>

      </div>
    </div>
  </div>
<script>
const sessionId = @json($session->session_id);

const preview   = document.getElementById('preview');
const promptEl  = document.getElementById('prompt');
const startBtn  = document.getElementById('startBtn');
const stopBtn   = document.getElementById('stopBtn');

const statusBox = document.getElementById('statusBox');
const warnBox   = document.getElementById('warnBox');

const clockEl   = document.getElementById('clock');
const recTimerEl= document.getElementById('recTimer');
const voiceToggle = document.getElementById('voiceToggle');

let mediaStream = null;
let mediaRecorder = null;
let chunks = [];
let promptTimer = null;

let recStartMs = null;
let recTick = null;

let isStopping = false;   // prevent double stop
let autoStopTimeout = null;

// ✅ Step prompts with AUTO END at 130s
const prompts = [
  { t: 0,   text: "Step 1: Start. Say: Hello, today’s date is [DD/MM/YYYY] and current time is [HH:MM]. I am recording this video for Video KYC to onboard on SPAY e-commerce platform." },

//   { t: 10,  text: "Step 2: Your Introduction. Say: My full name is [Your Name]. Father’s name is [Father Name]. Date of birth is [DOB]. Registered mobile number is [Mobile]. Registered email ID is [Email]. My residential address is [Full Address]." },
 {  t: 20,   text: "Step 2: Your Introduction. Say: My full name is [Your Name]. My company name is [Company Name]. My company type is [Company Type]."},
{  t: 30,  text: "Step 3: Declaration. Say: I confirm that I am onboarding my eCommerce company at Spay fintech Pvt Ltd. I understand that if my company is found to be involved in any illegal activity at any time, Spay shall not be held responsible. I further acknowledge that if my account is suspended or frozen due to such activities, Spay will bear no liability." },
  { t: 55, text: "Final: Thank you for completing your Video KYC process. Your Video KYC is now successfully completed. We will review and verify your submitted documents, and once verification is approved, you will be officially onboarded on our E-Commerce panel." },
];

// ✅ AUTO STOP after this duration (seconds)
// const AUTO_STOP_SEC = 130;
const AUTO_STOP_SEC = 90;


function setPrompt(text){
  promptEl.innerHTML = text;
  speak(text.replace(/<[^>]*>/g,''));
}

function showStatus(msg){
  statusBox.style.display = 'block';
  statusBox.textContent = msg;
}
function hideStatus(){
  statusBox.style.display = 'none';
  statusBox.textContent = '';
}
function showWarn(msg){
  warnBox.style.display = 'block';
  warnBox.textContent = msg;
}
function hideWarn(){
  warnBox.style.display = 'none';
  warnBox.textContent = '';
}

function isSecureContextOk() {
  const isLocalhost = location.hostname === "localhost" || location.hostname === "127.0.0.1";
  return window.isSecureContext || isLocalhost;
}

function explainGetUserMediaError(err){
  const name = err?.name || "";
  const msg  = err?.message || "";

  if (!isSecureContextOk()) return "Camera/Mic require HTTPS. Open this page on https:// (or localhost).";
  if (name === "NotAllowedError" || name === "PermissionDeniedError")
    return "Permission denied. Allow Camera + Microphone from browser site settings (🔒 lock icon) then refresh.";
  if (name === "NotFoundError" || name === "DevicesNotFoundError")
    return "No camera/mic detected. Try on phone or laptop with camera.";
  if (name === "NotReadableError")
    return "Camera/Mic is busy (used by another app like WhatsApp/Zoom). Close other apps and refresh.";
  return "Camera/Mic error: " + (msg || name || "Unknown error");
}

async function checkDevices() {
  const devices = await navigator.mediaDevices.enumerateDevices();
  const hasVideo = devices.some(d => d.kind === "videoinput");
  const hasAudio = devices.some(d => d.kind === "audioinput");
  return { hasVideo, hasAudio };
}

/**
 * ✅ BIG FIX: use lower resolution + fps for smaller file (faster upload)
 */
async function getStreamSafe() {
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia)
    throw new Error("Your browser does not support camera/microphone access.");
  if (!isSecureContextOk())
    throw new Error("Camera/Mic require HTTPS. Please open the HTTPS link.");

  const { hasVideo, hasAudio } = await checkDevices();

  try {
    return await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: "user",
        width:  { ideal: 640 },
        height: { ideal: 480 },
        frameRate: { ideal: 20, max: 24 }
      },
      audio: {
        echoCancellation: true,
        noiseSuppression: true
      }
    });
  } catch (e1) {
    if (hasVideo) {
      try {
        return await navigator.mediaDevices.getUserMedia({
          video: { width:{ideal:640}, height:{ideal:480}, frameRate:{ideal:20,max:24} },
          audio: false
        });
      } catch (_) {}
    }
    if (hasAudio) {
      return await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
    }
    throw e1;
  }
}

async function markStarted(){
  await fetch(`/api/vkyc/${sessionId}/start`, {
    method:'POST',
    headers:{'Accept':'application/json'}
  });
}

/**
 * ✅ Upload with progress (XHR)
 */
function uploadWithProgress(blob){
  return new Promise((resolve, reject) => {
    const fd = new FormData();
    fd.append('video', blob, 'vkyc.webm');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', `/api/vkyc/${sessionId}/upload`, true);
    xhr.setRequestHeader('Accept', 'application/json');

    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) {
        const pct = Math.round((e.loaded / e.total) * 100);
        showStatus(`Uploading video... ${pct}%`);
      } else {
        showStatus("Uploading video...");
      }
    };

    xhr.onload = () => {
      try {
        const json = JSON.parse(xhr.responseText || "{}");
        resolve(json);
      } catch {
        reject(new Error("Invalid server response"));
      }
    };

    xhr.onerror = () => reject(new Error("Upload failed (network error)"));
    xhr.send(fd);
  });
}

function stopTracks() {
  if (mediaStream) {
    mediaStream.getTracks().forEach(t => t.stop());
    mediaStream = null;
  }
  preview.srcObject = null;
}

function startPromptTimer() {
  const startTime = Date.now();
  let lastText = "";

  promptTimer = setInterval(() => {
    const sec = (Date.now() - startTime) / 1000;
    const p = prompts.slice().reverse().find(x => sec >= x.t);
    if (p && p.text !== lastText) {
      lastText = p.text;
      setPrompt(p.text);
    }
  }, 300);
}

function stopPromptTimer() {
  if (promptTimer) clearInterval(promptTimer);
  promptTimer = null;
}

function startRecTimer(){
  recStartMs = Date.now();
  recTimerEl.textContent = "00:00";
  if (recTick) clearInterval(recTick);
  recTick = setInterval(() => {
    const s = Math.floor((Date.now() - recStartMs) / 1000);
    const mm = String(Math.floor(s / 60)).padStart(2,'0');
    const ss = String(s % 60).padStart(2,'0');
    recTimerEl.textContent = `${mm}:${ss}`;
  }, 500);
}

function stopRecTimer(){
  if (recTick) clearInterval(recTick);
  recTick = null;
  recStartMs = null;
}

function speak(text){
  if (!voiceToggle.checked) return;
  if (!('speechSynthesis' in window)) return;

  window.speechSynthesis.cancel();
  const u = new SpeechSynthesisUtterance(text);
  u.rate = 1;
  u.pitch = 1;
  u.volume = 1;
  window.speechSynthesis.speak(u);
}

// ✅ live clock
setInterval(() => {
  const d = new Date();
  clockEl.textContent = d.toLocaleTimeString();
}, 500);

/**
 * ✅ Centralized stop+upload (manual stop button OR auto stop)
 */
async function stopAndUpload(){
  if (isStopping) return;
  isStopping = true;

  try {
    stopBtn.disabled = true;
    showStatus("Stopping recording...");
    stopPromptTimer();
    stopRecTimer();
    setPrompt("Uploading... please wait.");

    if (!mediaRecorder) {
      showWarn("Recorder not started.");
      isStopping = false;
      return;
    }

    mediaRecorder.onstop = async () => {
      try {
        const blob = new Blob(chunks, { type: 'video/webm' });
        stopTracks();

        showStatus("Uploading video...");
        const out = await uploadWithProgress(blob);

        if (out.status === 'success') {
          hideWarn();
          showStatus("Uploaded successfully ✅ Redirecting...");

          // ✅ Prefer completed_url if backend returns it
          if (out.data && out.data.completed_url) {
            window.location.href = out.data.completed_url;
            return;
          }
          if (out.data && out.data.redirect_url) {
            window.location.href = out.data.redirect_url;
            return;
          }

          setPrompt("Done. You can close this page.");
        } else {
          hideStatus();
          showWarn("Upload failed: " + (out.message || "unknown"));
          setPrompt("Please try again.");
          startBtn.disabled = false;
          isStopping = false;
        }
      } catch (e) {
        console.error(e);
        hideStatus();
        showWarn("Upload error: " + (e.message || "unknown"));
        setPrompt("Please try again.");
        startBtn.disabled = false;
        isStopping = false;
      }
    };

    mediaRecorder.stop();
  } catch (err) {
    console.error(err);
    hideStatus();
    showWarn("Stop error: " + (err.message || "unknown"));
    startBtn.disabled = false;
    isStopping = false;
  }
}

startBtn.onclick = async () => {
  try {
    hideWarn();
    showStatus("Checking devices & requesting permission...");
    setPrompt("Preparing camera & microphone...");

    mediaStream = await getStreamSafe();
    preview.srcObject = mediaStream;

    let options = {};
    if (MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus')) {
      options.mimeType = 'video/webm;codecs=vp8,opus';
    } else if (MediaRecorder.isTypeSupported('video/webm')) {
      options.mimeType = 'video/webm';
    } else {
      options = {};
    }

    await markStarted();

    chunks = [];
    isStopping = false;

    /**
     * ✅ BIG FIX: set bitrate (smaller file = faster upload)
     */
    mediaRecorder = new MediaRecorder(mediaStream, {
      ...options,
      videoBitsPerSecond: 600000, // 0.6 Mbps
      audioBitsPerSecond: 48000
    });

    mediaRecorder.ondataavailable = (e) => { if (e.data && e.data.size > 0) chunks.push(e.data); };
    mediaRecorder.onerror = (e) => showWarn("Recorder error: " + (e?.error?.message || "unknown"));

    mediaRecorder.start(1000);

    startBtn.disabled = true;
    stopBtn.disabled = false;

    showStatus("Recording started ✅ Follow the instructions.");
    startRecTimer();
    startPromptTimer();

    // ✅ AUTO STOP after last step (130 sec)
    if (autoStopTimeout) clearTimeout(autoStopTimeout);
    autoStopTimeout = setTimeout(() => {
      setPrompt("Auto finishing... Upload starting now.");
      stopAndUpload();
    }, AUTO_STOP_SEC * 1000);

  } catch (err) {
    console.error(err);
    hideStatus();
    showWarn(explainGetUserMediaError(err));
    setPrompt("Fix issue and try again.");
    stopTracks();
    startBtn.disabled = false;
    stopBtn.disabled = true;
  }
};

stopBtn.onclick = async () => {
  if (autoStopTimeout) clearTimeout(autoStopTimeout);
  await stopAndUpload();
};
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>