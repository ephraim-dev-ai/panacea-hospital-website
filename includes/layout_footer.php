  </div><!-- /content -->
</div><!-- /main-wrap -->

<!-- SESSION TIMEOUT WARNING -->
<div id="sessionWarning" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:99999;display:none;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:36px;max-width:420px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <div style="width:64px;height:64px;background:#fff8e8;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 16px">
      ⏰
    </div>
    <h4 style="font-family:'Playfair Display',serif;color:#0a2e5c;margin-bottom:8px">Session Expiring</h4>
    <p style="color:#7a8da8;font-size:.9rem;margin-bottom:20px">
      Your session will expire in <strong id="countdown" style="color:#c0162c">60</strong> seconds due to inactivity.
    </p>
    <div style="display:flex;gap:12px;justify-content:center">
      <button onclick="extendSession()"
              style="background:linear-gradient(135deg,#1a5fa0,#2e8dd4);color:#fff;border:none;padding:11px 28px;border-radius:9px;font-weight:600;cursor:pointer">
        Stay Logged In
      </button>
      <a href="/panacea/admin/logout.php"
         style="background:#f0f4f9;color:#3d4f6b;border:none;padding:11px 28px;border-radius:9px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center">
        Logout Now
      </a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ── Auto-dismiss alerts ──────────────────────────────────
  document.querySelectorAll('.alert').forEach(el => setTimeout(() => {
    try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e){}
  }, 5000));

  // ── Confirm deletes ───────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!confirm(btn.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
  });

  // ── Session Timeout Warning ───────────────────────────────
  const SESSION_TIMEOUT  = 3600;  // 1 hour (match PHP constant)
  const WARNING_BEFORE   = 60;    // Warn 60 seconds before expiry
  let idleTime           = 0;
  let warningShown       = false;
  let countdownInterval  = null;

  function resetIdleTimer() {
    idleTime = 0;
    if (warningShown) {
      hideWarning();
    }
  }

  function showWarning() {
    warningShown = true;
    const modal  = document.getElementById('sessionWarning');
    modal.style.display = 'flex';
    let secs = WARNING_BEFORE;
    document.getElementById('countdown').textContent = secs;
    countdownInterval = setInterval(() => {
      secs--;
      document.getElementById('countdown').textContent = secs;
      if (secs <= 0) {
        clearInterval(countdownInterval);
        window.location.href = '/panacea/admin/logout.php?timeout=1';
      }
    }, 1000);
  }

  function hideWarning() {
    warningShown = false;
    document.getElementById('sessionWarning').style.display = 'none';
    clearInterval(countdownInterval);
  }

  function extendSession() {
    // Ping server to reset session
    fetch('/panacea/admin/ping.php')
      .then(() => { hideWarning(); idleTime = 0; })
      .catch(() => hideWarning());
  }

  // Track idle time
  setInterval(() => {
    idleTime++;
    const timeLeft = SESSION_TIMEOUT - idleTime;
    if (timeLeft <= WARNING_BEFORE && !warningShown) {
      showWarning();
    }
  }, 1000);

  // Reset on user activity
  ['mousemove','keypress','click','scroll','touchstart'].forEach(evt => {
    document.addEventListener(evt, resetIdleTimer, { passive: true });
  });
</script>
</body>
</html>
