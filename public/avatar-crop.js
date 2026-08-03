(function () {
  const input = document.getElementById('avatar-input');
  const modal = document.getElementById('crop-modal');
  const image = document.getElementById('cropper-image');
  const zoomSlider = document.getElementById('crop-zoom');
  const cancelBtn = document.getElementById('crop-cancel');
  const saveBtn = document.getElementById('crop-save');

  if (!input || !modal) return;

  let cropper = null;
  let lastZoomValue = 0;

  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      image.src = e.target.result;
      modal.classList.add('active');

      if (cropper) {
        cropper.destroy();
      }
      lastZoomValue = 0;
      zoomSlider.value = 0;

      // Fixed circular window in the middle; the photo itself is what
      // drags and zooms underneath it - matches the familiar
      // Facebook/Twitter-style avatar picker rather than a resizable box.
      cropper = new Cropper(image, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        cropBoxMovable: false,
        cropBoxResizable: false,
        toggleDragModeOnDblclick: false,
        guides: false,
        center: false,
        highlight: false,
        background: false,
        autoCropArea: 1,
      });
    };
    reader.readAsDataURL(file);
  });

  zoomSlider.addEventListener('input', () => {
    if (!cropper) return;
    const value = parseFloat(zoomSlider.value);
    cropper.zoom((value - lastZoomValue) / 100);
    lastZoomValue = value;
  });

  function closeModal() {
    modal.classList.remove('active');
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
    input.value = '';
  }

  cancelBtn.addEventListener('click', closeModal);

  saveBtn.addEventListener('click', () => {
    if (!cropper) return;
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    cropper.getCroppedCanvas({ width: 256, height: 256, imageSmoothingQuality: 'high' }).toBlob((blob) => {
      const formData = new FormData();
      formData.append('csrf_token', window.CSRF_TOKEN || '');
      formData.append('avatar', blob, 'avatar.jpg');

      fetch('upload-avatar.php', { method: 'POST', body: formData })
        .then(() => { window.location.reload(); })
        .catch(() => {
          alert('Could not upload photo. Please try again.');
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save photo';
        });
    }, 'image/jpeg', 0.9);
  });
})();