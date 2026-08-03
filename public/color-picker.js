(function () {
  const modalBtn = document.getElementById('custom-theme-btn');
  const modal = document.getElementById('color-modal');
  const canvas = document.getElementById('color-sv');
  const cursor = document.getElementById('color-sv-cursor');
  const hueSlider = document.getElementById('color-hue');
  const hexInput = document.getElementById('color-hex');
  const nativeColorPicker = document.getElementById('native-color-picker');
  const preview = document.getElementById('color-preview');
  const cancelBtn = document.getElementById('color-cancel');
  const formHexInputs = document.querySelectorAll('.custom-form-hex');

  let hue = 220;
  let sat = 100;
  let val = 100;

  function hsvToRgb(h, s, v) {
    s /= 100; v /= 100;
    const c = v * s;
    const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
    const m = v - c;
    let r, g, b;
    if (h < 60) { r = c; g = x; b = 0; }
    else if (h < 120) { r = x; g = c; b = 0; }
    else if (h < 180) { r = 0; g = c; b = x; }
    else if (h < 240) { r = 0; g = x; b = c; }
    else if (h < 300) { r = x; g = 0; b = c; }
    else { r = c; g = 0; b = x; }
    return [Math.round((r + m) * 255), Math.round((g + m) * 255), Math.round((b + m) * 255)];
  }

  function rgbToHex(r, g, b) {
    return [r, g, b].map((n) => n.toString(16).padStart(2, '0')).join('');
  }

  function hexToRgb(hex) {
    hex = hex.replace('#', '');
    if (hex.length !== 6) return null;
    const num = parseInt(hex, 16);
    if (isNaN(num)) return null;
    return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
  }

  function rgbToHsv(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    const d = max - min;
    let h = 0;
    if (d !== 0) {
      if (max === r) h = 60 * (((g - b) / d) % 6);
      else if (max === g) h = 60 * ((b - r) / d + 2);
      else h = 60 * ((r - g) / d + 4);
    }
    if (h < 0) h += 360;
    const s = max === 0 ? 0 : d / max;
    return [h, s * 100, max * 100];
  }

  function applyLiveThemeFromHex(hex) {
    hex = hex.replace('#', '');
    if (hex.length !== 6) return;
    const rgb = hexToRgb(hex);
    if (!rgb) return;
    let r1 = rgb[0] / 255, g1 = rgb[1] / 255, b1 = rgb[2] / 255;
    let max = Math.max(r1, g1, b1), min = Math.min(r1, g1, b1);
    let h = 0, s = 0, l = (max + min) / 2;
    if (max !== min) {
      let d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      switch (max) {
        case r1: h = (g1 - b1) / d + (g1 < b1 ? 6 : 0); break;
        case g1: h = (b1 - r1) / d + 2; break;
        case b1: h = (r1 - g1) / d + 4; break;
      }
      h /= 6;
    }
    h *= 360; s *= 100; l *= 100;

    const accentSat = Math.max(s, 35);
    const accentL = Math.max(35, Math.min(65, l));

    function hslToHex(h, s, l) {
      h /= 360; s /= 100; l /= 100;
      let r, g, b;
      if (s === 0) { r = g = b = l; } else {
        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;
        const hue2rgb = (p, q, t) => {
          if (t < 0) t += 1; if (t > 1) t -= 1;
          if (t < 1/6) return p + (q - p) * 6 * t;
          if (t < 1/2) return q;
          if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
          return p;
        };
        r = hue2rgb(p, q, h + 1/3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1/3);
      }
      const toHex = x => Math.round(x * 255).toString(16).padStart(2, '0');
      return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    }

    const accentHex = hslToHex(h, accentSat, accentL);
    const accentDarkHex = hslToHex(h, accentSat, Math.max(accentL - 18, 20));
    const accentSoftHex = hslToHex(h, Math.max(Math.min(s, 60), 20), Math.min(accentL + 32, 92));

    document.documentElement.style.setProperty('--accent', accentHex);
    document.documentElement.style.setProperty('--accent-dark', accentDarkHex);
    document.documentElement.style.setProperty('--accent-soft', accentSoftHex);

    // Only live-preview the background tint in light mode - dark mode's neutral
    // palette (--cream, --surface, --ink, etc.) is governed by the .dark-mode
    // class, and overriding --cream here with an inline style would permanently
    // fight that class rule until the next full page load cleared it.
    if (!document.documentElement.classList.contains('dark-mode')) {
        const creamHex = hslToHex(h, Math.min(s, 15), 97);
        document.documentElement.style.setProperty('--cream', creamHex);
    }

  function drawBox() {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const [r, g, b] = hsvToRgb(hue, 100, 100);
    ctx.fillStyle = `rgb(${r},${g},${b})`;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    const whiteGrad = ctx.createLinearGradient(0, 0, canvas.width, 0);
    whiteGrad.addColorStop(0, 'rgba(255,255,255,1)');
    whiteGrad.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = whiteGrad;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    const blackGrad = ctx.createLinearGradient(0, 0, 0, canvas.height);
    blackGrad.addColorStop(0, 'rgba(0,0,0,0)');
    blackGrad.addColorStop(1, 'rgba(0,0,0,1)');
    ctx.fillStyle = blackGrad;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
  }

  function updateCursor() {
    if (cursor) {
      cursor.style.left = sat + '%';
      cursor.style.top = (100 - val) + '%';
    }
  }

  function updatePreviewAndHex() {
    const [r, g, b] = hsvToRgb(hue, sat, val);
    const hex = rgbToHex(r, g, b);
    if (preview) preview.style.background = '#' + hex;
    if (hexInput) hexInput.value = hex.toUpperCase();
    if (nativeColorPicker) nativeColorPicker.value = '#' + hex;

    formHexInputs.forEach(input => {
      input.value = '#' + hex;
    });

    applyLiveThemeFromHex(hex);
  }

  function setFromHex(hex) {
    hex = hex.replace('#', '');
    const rgb = hexToRgb(hex);
    if (!rgb) return;
    const [h, s, v] = rgbToHsv(rgb[0], rgb[1], rgb[2]);
    hue = h; sat = s; val = v;
    if (hueSlider) hueSlider.value = Math.round(hue);
    drawBox();
    updateCursor();
    updatePreviewAndHex();
  }

  if (canvas) {
    function pickFromEvent(e) {
      const rect = canvas.getBoundingClientRect();
      const clientX = e.touches ? e.touches[0].clientX : e.clientX;
      const clientY = e.touches ? e.touches[0].clientY : e.clientY;
      let xRatio = (clientX - rect.left) / rect.width;
      let yRatio = (clientY - rect.top) / rect.height;
      xRatio = Math.max(0, Math.min(1, xRatio));
      yRatio = Math.max(0, Math.min(1, yRatio));
      sat = xRatio * 100;
      val = (1 - yRatio) * 100;
      updateCursor();
      updatePreviewAndHex();
    }

    let dragging = false;
    canvas.addEventListener('mousedown', (e) => { dragging = true; pickFromEvent(e); });
    window.addEventListener('mousemove', (e) => { if (dragging) pickFromEvent(e); });
    window.addEventListener('mouseup', () => { dragging = false; });
    canvas.addEventListener('touchstart', (e) => { dragging = true; pickFromEvent(e); });
    canvas.addEventListener('touchmove', (e) => { if (dragging) { pickFromEvent(e); e.preventDefault(); } }, { passive: false });
    window.addEventListener('touchend', () => { dragging = false; });
  }

  if (hueSlider) {
    hueSlider.addEventListener('input', () => {
      hue = parseFloat(hueSlider.value);
      drawBox();
      updatePreviewAndHex();
    });
  }

  if (hexInput) {
    hexInput.addEventListener('input', () => {
      const clean = hexInput.value.replace(/[^0-9a-fA-F]/g, '').slice(0, 6);
      if (clean.length === 6) {
        setFromHex(clean);
      }
    });
  }

  if (nativeColorPicker) {
    nativeColorPicker.addEventListener('input', () => {
      setFromHex(nativeColorPicker.value);
    });
  }

  if (modalBtn && modal) {
    modalBtn.addEventListener('click', () => {
      const existing = (window.CURRENT_CUSTOM_COLOR || hexInput?.value || '5865F2').replace('#', '');
      modal.classList.add('active');
      drawBox();
      if (existing.length === 6) {
        setFromHex(existing);
      } else {
        updateCursor();
        updatePreviewAndHex();
      }
    });
  }

  if (cancelBtn && modal) {
    cancelBtn.addEventListener('click', () => {
      modal.classList.remove('active');
    });
  }

  // Initialize with initial hex value if present
  const initHex = (window.CURRENT_CUSTOM_COLOR || '5865F2').replace('#', '');
  if (initHex.length === 6 && hexInput) {
    hexInput.value = initHex.toUpperCase();
  }
})();